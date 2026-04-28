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
        echo json_encode([
            'success' => false,
            'message' => '未收到授权令牌',
            'debug' => $debug ? $debugInfo : null
        ]);
        exit;
    }
    
    if (!EYPA_OAUTH_ENABLED) {
        debugLog('OAuth功能未启用');
        echo json_encode([
            'success' => false,
            'message' => '马国记忆登录功能未启用',
            'debug' => $debug ? $debugInfo : null
        ]);
        exit;
    }
    
    if ($debug) {
        $debugInfo['step'] = 'calling_eypa_api';
        $debugInfo['eypa_endpoint'] = EYPA_API_ENDPOINT;
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
        $debugInfo['response_preview'] = substr($result, 0, 200);
    }
    
    if ($curlError) {
        debugLog('cURL错误', ['error' => $curlError]);
        echo json_encode([
            'success' => false,
            'message' => '连接马国记忆服务器失败: ' . $curlError,
            'debug' => $debug ? $debugInfo : null
        ]);
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
        debugLog('用户数据缺少UID');
        echo json_encode([
            'success' => false,
            'message' => '获取用户信息失败：缺少用户ID',
            'debug' => $debug ? $debugInfo : null
        ]);
        exit;
    }
    
    // 如果昵称为空，使用默认昵称
    if (empty($eypaNickname)) {
        $eypaNickname = '用户' . $eypaUid;
    }
    
    if ($debug) {
        $debugInfo['step'] = 'checking_existing_user';
        $debugInfo['eypa_uid'] = $eypaUid;
        $debugInfo['eypa_nickname'] = $eypaNickname;
        $debugInfo['eypa_nickname_source'] = empty($userData['nickname'] ?? '') ? 'default' : 'api';
    }
    
    $users = secureReadData(USERS_FILE);
    $existingUser = null;
    $existingUsername = null;
    
    foreach ($users as $username => $user) {
        if (isset($user['eypa_uid']) && $user['eypa_uid'] == $eypaUid) {
            $existingUser = $user;
            $existingUsername = $username;
            break;
        }
    }
    
    if ($debug) {
        $debugInfo['existing_user_found'] = $existingUser !== null;
        $debugInfo['existing_username'] = $existingUsername;
    }
    
    if ($existingUser) {
        $user = $existingUser;
        $username = $existingUsername;
        
        // 更新eypa信息
        $user['eypa_nickname'] = $eypaNickname;
        $user['eypa_avatar'] = $eypaAvatar;
        $user['last_login'] = date('Y-m-d H:i:s');
        
        // 更新username为eypa_nickname
        $user['username'] = $eypaNickname;
        
        if ($debug) {
            $debugInfo['step'] = 'updating_existing_user';
        }
    } else {
        // 直接使用马国记忆昵称作为用户名
        $baseUsername = $eypaNickname;
        
        if ($debug) {
            $debugInfo['original_nickname'] = $eypaNickname;
            $debugInfo['final_base_username'] = $baseUsername;
        }
        
        // 直接使用昵称作为用户名键
        $username = $baseUsername;
        $counter = 1;
        while (isset($users[$username])) {
            $username = $baseUsername . '_' . $counter;
            $counter++;
        }
        
        $user = [
            'id' => generate_uuid(),
            'username' => $baseUsername, // 使用原始昵称作为username
            'password' => null,
            'email' => null,
            'email_verified' => false,
            'created_at' => date('Y-m-d H:i:s'),
            'role' => 'user',
            'login_attempts' => 0,
            'lock_until' => null,
            'eypa_uid' => $eypaUid,
            'eypa_nickname' => $baseUsername, // eypa_nickname与username一致
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
        echo json_encode([
            'success' => false,
            'message' => '保存用户数据失败',
            'debug' => $debug ? $debugInfo : null
        ]);
        exit;
    }
    
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
        echo json_encode([
            'success' => false,
            'message' => '创建会话失败',
            'debug' => $debug ? $debugInfo : null
        ]);
        exit;
    }
    
    debugLog('登录成功', ['username' => $username, 'eypa_uid' => $eypaUid]);
    
    if ($debug) {
        $debugInfo['step'] = 'login_success';
        $debugInfo['username'] = $username;
        $debugInfo['session_token_preview'] = substr($sessionToken, 0, 20) . '...';
    }
    
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
    echo json_encode([
        'success' => false,
        'message' => '登录异常: ' . $e->getMessage(),
        'debug' => $debug ? $debugInfo : null
    ]);
}
?>
