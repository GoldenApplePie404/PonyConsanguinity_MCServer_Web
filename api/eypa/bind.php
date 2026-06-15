<?php
require_once '../config.php';
require_once '../helper.php';
require_once '../secure_data.php';

session_start();
require_once '../../includes/auth_helper.php';

$debug = EYPA_OAUTH_DEBUG;

function debugLog($message, $data = null) {
    if (EYPA_OAUTH_DEBUG) {
        error_log("[EYPA Bind] " . $message . ($data ? ": " . json_encode($data, JSON_UNESCAPED_UNICODE) : ''));
    }
}

/**
 * HTTP POST 请求（cURL 优先，SSL 失败自动降级，最终 fallback 到 file_get_contents）
 */
function httpPost($url, $data) {
    $json = json_encode($data);
    $headers = ['Content-Type: application/json'];

    // ── 方案 A：cURL ──
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // SSL 证书问题 → 关掉验证重试
        if ($error && strpos($error, 'SSL') !== false) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
        }

        if (!$error) {
            return ['result' => $result, 'http_code' => $httpCode, 'error' => ''];
        }
    }

    // ── 方案 B：file_get_contents ──
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $json,
            'timeout' => 15,
            'ignore_errors' => true
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
    ]);

    $result = @file_get_contents($url, false, $context);
    $error = $result === false ? 'file_get_contents 请求失败' : '';
    $httpCode = 0;
    if (isset($http_response_header[0]) && preg_match('#HTTP/\d+\.\d+ (\d+)#', $http_response_header[0], $m)) {
        $httpCode = (int)$m[1];
    }
    return ['result' => $result, 'http_code' => $httpCode, 'error' => $error];
}

// 从 GET 获取 token（马国记忆回调）
$token = $_GET['token'] ?? '';

if (empty($token)) {
    header('Location: ../../pages/profile.html?eypa_bind=error&msg=' . urlencode('未收到授权令牌'));
    exit;
}

// 验证登录状态
$currentUser = AuthHelper::getCurrentUser();
if (!$currentUser) {
    header('Location: ../../pages/login.html?redirect=bind');
    exit;
}
$username = $currentUser['username'];

debugLog('开始绑定', ['username' => $username, 'token_preview' => substr($token, 0, 20) . '...']);

// 调用马国记忆 API
$httpResp = httpPost(EYPA_API_ENDPOINT, ['token' => $token]);
$result = $httpResp['result'];
$httpCode = $httpResp['http_code'];
$httpError = $httpResp['error'];

if ($httpError) {
    header('Location: ../../pages/profile.html?eypa_bind=error&msg=' . urlencode('连接马国记忆服务器失败'));
    exit;
}

$userData = json_decode($result, true);

if ($httpCode !== 200 || isset($userData['error'])) {
    $errorMsg = $userData['error'] ?? '未知错误';
    header('Location: ../../pages/profile.html?eypa_bind=error&msg=' . urlencode('授权失败: ' . $errorMsg));
    exit;
}

$eypaUid = $userData['id'] ?? null;
$eypaNickname = $userData['nickname'] ?? '';
$eypaAvatar = $userData['avatar'] ?? '';

if (!$eypaUid) {
    header('Location: ../../pages/profile.html?eypa_bind=error&msg=' . urlencode('获取用户信息失败'));
    exit;
}

if (empty($eypaNickname)) {
    $eypaNickname = '用户' . $eypaUid;
}

$users = secureReadData(USERS_FILE);

if (!isset($users[$username])) {
    header('Location: ../../pages/profile.html?eypa_bind=error&msg=' . urlencode('用户不存在'));
    exit;
}

// 检查 EYPA 账号是否已被其他用户绑定
foreach ($users as $uname => $u) {
    if (isset($u['eypa_uid']) && $u['eypa_uid'] == $eypaUid && $uname !== $username) {
        header('Location: ../../pages/profile.html?eypa_bind=error&msg=' . urlencode('该马国记忆账号已被其他用户绑定'));
        exit;
    }
}

// 检查当前用户是否已绑定
if (isset($users[$username]['eypa_uid'])) {
    header('Location: ../../pages/profile.html?eypa_bind=error&msg=' . urlencode('您已绑定马国记忆账号，请先解绑'));
    exit;
}

// 执行绑定
$users[$username]['eypa_uid'] = $eypaUid;
$users[$username]['eypa_nickname'] = $eypaNickname;
$users[$username]['eypa_avatar'] = $eypaAvatar;
$users[$username]['eypa_bound_at'] = date('Y-m-d H:i:s');

if (!secureWriteData(USERS_FILE, $users)) {
    header('Location: ../../pages/profile.html?eypa_bind=error&msg=' . urlencode('保存绑定信息失败'));
    exit;
}

debugLog('绑定成功', ['username' => $username, 'eypa_uid' => $eypaUid]);

header('Location: ../../pages/profile.html?eypa_bind=success');
exit;
