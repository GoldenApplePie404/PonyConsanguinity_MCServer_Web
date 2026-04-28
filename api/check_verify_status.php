<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'config.php';
require_once 'helper.php';
require_once 'secure_data.php';

// 获取 token
$headers = getallheaders();
$token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : '';

if (!$token) {
    echo json_encode(['success' => false, 'message' => '未提供认证令牌']);
    exit();
}

// 读取会话数据
$sessions = secureReadData(SESSIONS_FILE);

if (!isset($sessions[$token])) {
    echo json_encode(['success' => false, 'message' => '令牌无效或已过期']);
    exit();
}

$session = $sessions[$token];
$username = $session['username'];

// 读取用户数据
$users = secureReadData(USERS_FILE);

if (!isset($users[$username])) {
    echo json_encode(['success' => false, 'message' => '用户不存在']);
    exit();
}

$user = $users[$username];

// 检查验证状态
$email_verified = !empty($user['email_verified']);

echo json_encode([
    'success' => true,
    'verification_enabled' => EMAIL_VERIFICATION_ENABLED,
    'email_verified' => $email_verified,
    'email' => $user['email'] ?? ''
]);
?>
