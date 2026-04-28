<?php
require_once '../config.php';
require_once '../helper.php';
require_once '../secure_data.php';

header('Content-Type: application/json; charset=utf-8');

$debug = EYPA_OAUTH_DEBUG;

function debugLog($message, $data = null) {
    if (EYPA_OAUTH_DEBUG) {
        error_log("[EYPA Bind] " . $message . ($data ? ": " . json_encode($data, JSON_UNESCAPED_UNICODE) : ''));
    }
}

session_start();
require_once '../../includes/auth_helper.php';

if (!AuthHelper::validateToken()) {
    echo json_encode([
        'success' => false,
        'message' => '请先登录'
    ]);
    exit;
}

$username = AuthHelper::getUsernameFromToken();

$debugInfo = [
    'username' => $username,
    'step' => 'initial'
];

$token = $_GET['token'] ?? '';

if (empty($token)) {
    echo json_encode([
        'success' => false,
        'message' => '未收到授权令牌',
        'debug' => $debug ? $debugInfo : null
    ]);
    exit;
}

if ($debug) {
    $debugInfo['step'] = 'calling_eypa_api';
    $debugInfo['token_preview'] = substr($token, 0, 20) . '...';
}

$ch = curl_init(EYPA_API_ENDPOINT);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['token' => $token]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($debug) {
    $debugInfo['http_code'] = $httpCode;
    $debugInfo['curl_error'] = $curlError;
}

if ($curlError) {
    echo json_encode([
        'success' => false,
        'message' => '连接马国记忆服务器失败: ' . $curlError,
        'debug' => $debug ? $debugInfo : null
    ]);
    exit;
}

$userData = json_decode($result, true);

if ($httpCode !== 200 || isset($userData['error'])) {
    $errorMsg = $userData['error'] ?? '未知错误';
    echo json_encode([
        'success' => false,
        'message' => '马国记忆授权失败: ' . $errorMsg,
        'debug' => $debug ? $debugInfo : null
    ]);
    exit;
}

$eypaUid = $userData['id'] ?? null;
$eypaNickname = $userData['nickname'] ?? '';
$eypaAvatar = $userData['avatar'] ?? '';

if (!$eypaUid) {
    echo json_encode([
        'success' => false,
        'message' => '获取用户信息失败：缺少用户ID',
        'debug' => $debug ? $debugInfo : null
    ]);
    exit;
}

if ($debug) {
    $debugInfo['step'] = 'checking_binding';
    $debugInfo['eypa_uid'] = $eypaUid;
}

$users = secureReadData(USERS_FILE);

if (!isset($users[$username])) {
    echo json_encode([
        'success' => false,
        'message' => '用户不存在',
        'debug' => $debug ? $debugInfo : null
    ]);
    exit;
}

foreach ($users as $uname => $user) {
    if (isset($user['eypa_uid']) && $user['eypa_uid'] == $eypaUid && $uname !== $username) {
        echo json_encode([
            'success' => false,
            'message' => '该马国记忆账号已被其他用户绑定',
            'debug' => $debug ? $debugInfo : null
        ]);
        exit;
    }
}

if (isset($users[$username]['eypa_uid'])) {
    echo json_encode([
        'success' => false,
        'message' => '您已绑定马国记忆账号，请先解绑后再绑定新账号',
        'debug' => $debug ? $debugInfo : null
    ]);
    exit;
}

$users[$username]['eypa_uid'] = $eypaUid;
$users[$username]['eypa_nickname'] = $eypaNickname;
$users[$username]['eypa_avatar'] = $eypaAvatar;
$users[$username]['eypa_bound_at'] = date('Y-m-d H:i:s');

if (!secureWriteData(USERS_FILE, $users)) {
    echo json_encode([
        'success' => false,
        'message' => '保存绑定信息失败',
        'debug' => $debug ? $debugInfo : null
    ]);
    exit;
}

if ($debug) {
    $debugInfo['step'] = 'binding_success';
}

debugLog('绑定成功', ['username' => $username, 'eypa_uid' => $eypaUid]);

echo json_encode([
    'success' => true,
    'message' => '马国记忆账号绑定成功',
    'data' => [
        'eypa_uid' => $eypaUid,
        'eypa_nickname' => $eypaNickname,
        'eypa_avatar' => $eypaAvatar
    ],
    'debug' => $debug ? $debugInfo : null
]);
?>
