<?php
require_once '../config.php';
require_once '../helper.php';
require_once '../secure_data.php';

header('Content-Type: application/json; charset=utf-8');

$debug = EYPA_OAUTH_DEBUG;
$debugInfo = [];

function debugLog($message, $data = null) {
    if (EYPA_OAUTH_DEBUG) {
        error_log("[EYPA OAuth] " . $message . ($data ? ": " . json_encode($data, JSON_UNESCAPED_UNICODE) : ''));
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
        // 第一遍：带 SSL 验证
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

        // SSL 证书问题 → 关掉验证重试（兼容 Windows 本地环境）
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

    // ── 方案 B：file_get_contents + stream context ──
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $json,
            'timeout' => 15,
            'ignore_errors' => true
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);

    $result = @file_get_contents($url, false, $context);
    $error = $result === false ? 'file_get_contents 请求失败' : '';
    // 从 $http_response_header 取状态码
    $httpCode = 0;
    if (isset($http_response_header[0]) && preg_match('#HTTP/\d+\.\d+ (\d+)#', $http_response_header[0], $m)) {
        $httpCode = (int)$m[1];
    }
    return ['result' => $result, 'http_code' => $httpCode, 'error' => $error];
}

try {
    $token = $_GET['token'] ?? $_GET['eu_token'] ?? '';

    if ($debug) {
        $debugInfo['step'] = 'initial';
        $debugInfo['token_received'] = $token ? substr($token, 0, 20) . '...' : 'empty';
        $debugInfo['token_length'] = strlen($token);
        $debugInfo['token_source'] = isset($_GET['token']) ? 'token' : (isset($_GET['eu_token']) ? 'eu_token' : 'none');
    }

    if (empty($token)) {
        debugLog('Token为空');
        echo json_encode(['success' => false, 'message' => '未收到授权令牌', 'debug' => $debug ? $debugInfo : null]);
        exit;
    }

    if (!EYPA_OAUTH_ENABLED) {
        debugLog('OAuth功能未启用');
        echo json_encode(['success' => false, 'message' => '马国记忆登录功能未启用', 'debug' => $debug ? $debugInfo : null]);
        exit;
    }

    if ($debug) {
        $debugInfo['step'] = 'calling_eypa_api';
        $debugInfo['eypa_endpoint'] = EYPA_API_ENDPOINT;
    }

    // 使用通用 HTTP 请求（cURL 优先，无 cURL 则自动 fallback）
    $httpResp = httpPost(EYPA_API_ENDPOINT, ['token' => $token]);
    $result = $httpResp['result'];
    $httpCode = $httpResp['http_code'];
    $httpError = $httpResp['error'];

    if ($debug) {
        $debugInfo['http_code'] = $httpCode;
        $debugInfo['http_error'] = $httpError;
        $debugInfo['response_preview'] = substr($result ?? '', 0, 200);
        $debugInfo['transport'] = function_exists('curl_init') ? 'curl' : 'file_get_contents';
    }

    if ($httpError) {
        debugLog('HTTP请求错误', ['error' => $httpError]);
        echo json_encode(['success' => false, 'message' => '连接马国记忆服务器失败: ' . $httpError, 'debug' => $debug ? $debugInfo : null]);
        exit;
    }

    $userData = json_decode($result, true);

    if ($debug) {
        $debugInfo['step'] = 'parsing_response';
        $debugInfo['user_data'] = $userData;
    }

    if ($httpCode !== 200 || isset($userData['error'])) {
        $errorMsg = $userData['error'] ?? '未知错误';
        debugLog('马国记忆API返回错误', ['http_code' => $httpCode, 'error' => $errorMsg]);
        echo json_encode(['success' => false, 'message' => '马国记忆授权失败: ' . $errorMsg, 'debug' => $debug ? $debugInfo : null]);
        exit;
    }

    $eypaUid = $userData['id'] ?? null;
    $eypaNickname = $userData['nickname'] ?? '';
    $eypaAvatar = $userData['avatar'] ?? '';

    if (!$eypaUid) {
        debugLog('用户数据缺少UID');
        echo json_encode(['success' => false, 'message' => '获取用户信息失败：缺少用户ID', 'debug' => $debug ? $debugInfo : null]);
        exit;
    }

    if (empty($eypaNickname)) {
        $eypaNickname = '用户' . $eypaUid;
    }

    if ($debug) {
        $debugInfo['step'] = 'checking_existing_user';
        $debugInfo['eypa_uid'] = $eypaUid;
        $debugInfo['eypa_nickname'] = $eypaNickname;
    }

    $users = secureReadData(USERS_FILE);
    $existingUser = null;
    $existingUsername = null;

    // 通过 eypa_uid 查找已有绑定用户
    foreach ($users as $uname => $u) {
        if (isset($u['eypa_uid']) && $u['eypa_uid'] == $eypaUid) {
            $existingUser = $u;
            $existingUsername = $uname;
            break;
        }
    }

    if ($debug) {
        $debugInfo['existing_user_found'] = $existingUser !== null;
        $debugInfo['existing_username'] = $existingUsername;
    }

    if ($existingUser) {
        // ── 已有绑定用户：更新信息 ──
        $username = $existingUsername;
        $user = $existingUser;

        // 如果 EYPA 昵称变了，需要迁移数组键
        if ($eypaNickname !== $username && !isset($users[$eypaNickname])) {
            // 新键可用，删除旧键
            unset($users[$username]);
            $username = $eypaNickname;
        }

        $user['username'] = $username;
        $user['eypa_nickname'] = $eypaNickname;
        $user['eypa_avatar'] = $eypaAvatar;
        $user['last_login'] = date('Y-m-d H:i:s');

        if ($debug) {
            $debugInfo['step'] = 'updating_existing_user';
            $debugInfo['new_key'] = $username;
        }
    } else {
        // ── 全新用户：创建账号 ──
        $baseUsername = $eypaNickname;
        $username = $baseUsername;
        $counter = 1;
        while (isset($users[$username])) {
            $username = $baseUsername . '_' . $counter;
            $counter++;
        }

        $user = [
            'id' => generate_uuid(),
            'username' => $username,
            'password' => null,
            'email' => null,
            'email_verified' => false,
            'created_at' => date('Y-m-d H:i:s'),
            'role' => 'user',
            'login_attempts' => 0,
            'lock_until' => null,
            'eypa_uid' => $eypaUid,
            'eypa_nickname' => $eypaNickname,
            'eypa_avatar' => $eypaAvatar,
            'eypa_bound_at' => date('Y-m-d H:i:s'),
            'last_login' => date('Y-m-d H:i:s')
        ];

        if ($debug) {
            $debugInfo['step'] = 'creating_new_user';
            $debugInfo['new_username'] = $username;
        }
    }

    $users[$username] = $user;

    if (!secureWriteData(USERS_FILE, $users)) {
        debugLog('保存用户数据失败');
        echo json_encode(['success' => false, 'message' => '保存用户数据失败', 'debug' => $debug ? $debugInfo : null]);
        exit;
    }

    // ── 创建会话 ──
    if ($debug) {
        $debugInfo['step'] = 'creating_session';
    }

    $sessionToken = bin2hex(random_bytes(32));
    $expiresAt = time() + (86400 * 7);

    $sessions = secureReadData(SESSIONS_FILE);
    $sessions[$sessionToken] = [
        'user_id' => $user['id'],
        'username' => $username,
        'role' => $user['role'] ?? 'user',
        'created_at' => time(),
        'expires_at' => $expiresAt,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
    ];

    if (!secureWriteData(SESSIONS_FILE, $sessions)) {
        debugLog('保存会话失败');
        echo json_encode(['success' => false, 'message' => '创建会话失败', 'debug' => $debug ? $debugInfo : null]);
        exit;
    }

    debugLog('登录成功', ['username' => $username, 'eypa_uid' => $eypaUid]);

    if ($debug) {
        $debugInfo['step'] = 'login_success';
        $debugInfo['username'] = $username;
        $debugInfo['session_token_preview'] = substr($sessionToken, 0, 20) . '...';
    }

    // ── 重定向到成功页 ──
    $redirectUrl = '../../pages/eypa_login_success.html';
    $redirectUrl .= '?token=' . urlencode($sessionToken);
    $redirectUrl .= '&username=' . urlencode($username);
    $redirectUrl .= '&nickname=' . urlencode($eypaNickname);
    $redirectUrl .= '&avatar=' . urlencode($eypaAvatar);
    $redirectUrl .= '&role=' . urlencode($user['role'] ?? 'user');
    $redirectUrl .= '&is_new=' . ($existingUser ? '0' : '1');

    if ($debug) {
        $redirectUrl .= '&debug=' . urlencode(json_encode($debugInfo, JSON_UNESCAPED_UNICODE));
    }

    header('Location: ' . $redirectUrl);
    exit;

} catch (Exception $e) {
    debugLog('异常', ['error' => $e->getMessage()]);
    echo json_encode(['success' => false, 'message' => '登录异常: ' . $e->getMessage(), 'debug' => $debug ? $debugInfo : null]);
}
