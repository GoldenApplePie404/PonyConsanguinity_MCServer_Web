<?php
require_once 'config.php';
require_once 'helper.php';
require_once '../includes/auth_helper.php';

header('Content-Type: application/json');
set_cors_headers(); // 安全 CORS：仅允许受信任来源，禁止通配符 *

// 验证登录
$session = AuthHelper::requireLogin();
$username = $session['username'];

// 读取用户数据
$users = secureReadData(USERS_FILE);

if (!isset($users[$username])) {
    json_response(false, '用户不存在', null, 404);
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
