<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'config.php';
require_once 'helper.php';
require_once 'secure_data.php';

$token = isset($_GET['token']) ? $_GET['token'] : '';

if (!$token) {
    echo json_encode([
        'success' => false, 
        'message' => '未提供验证令牌',
        'debug' => ['token' => null]
    ]);
    exit();
}

// 读取用户数据
$users = secureReadData(USERS_FILE);
$foundUser = null;
$foundUsername = null;

$debugInfo = [
    'request_token' => substr($token, 0, 30) . '...',
    'current_time' => date('Y-m-d H:i:s'),
    'current_timestamp' => time(),
    'users_count' => count($users),
    'matching_users' => []
];

// 查找包含此验证令牌的用户
foreach ($users as $username => $user) {
    if (isset($user['verify_token']) && $user['verify_token'] === $token) {
        $debugInfo['matching_users'][] = [
            'username' => $username,
            'token_match' => true,
            'verify_expires' => $user['verify_expires'] ?? null,
            'email_verified' => $user['email_verified'] ?? false
        ];
        
        // 检查令牌是否过期
        if (!empty($user['verify_expires'])) {
            $expiresTime = strtotime($user['verify_expires']);
            $currentTime = time();
            $isExpired = $expiresTime <= $currentTime;
            
            $debugInfo['time_check'] = [
                'expires_time' => $user['verify_expires'],
                'expires_timestamp' => $expiresTime,
                'current_timestamp' => $currentTime,
                'is_expired' => $isExpired
            ];
            
            if (!$isExpired) {
                $foundUser = $user;
                $foundUsername = $username;
            }
        } else {
            // 没有过期时间，直接验证
            $foundUser = $user;
            $foundUsername = $username;
            $debugInfo['no_expiry'] = '没有设置过期时间，直接验证';
        }
        break;
    }
}

if (!$foundUser) {
    echo json_encode([
        'success' => false, 
        'message' => '验证令牌无效或已过期',
        'debug' => $debugInfo
    ]);
    exit();
}

// 更新用户验证状态
$users[$foundUsername]['email_verified'] = true;
$users[$foundUsername]['verify_token'] = null;
$users[$foundUsername]['verify_expires'] = null;

$debugInfo['update_result'] = [
    'username' => $foundUsername,
    'email_verified' => true,
    'token_cleared' => true
];

if (secureWriteData(USERS_FILE, $users)) {
    echo json_encode([
        'success' => true, 
        'message' => '邮箱验证成功',
        'debug' => $debugInfo
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => '验证失败，请重试',
        'debug' => $debugInfo
    ]);
}
?>
