<?php
require_once 'config.php';
require_once 'helper.php';
require_once 'secure_data.php';
require_once __DIR__ . '/../includes/security_logger.php';

$securityLog = SecurityLogger::getInstance();

// 设置 CORS 和安全头
set_cors_headers();
set_security_headers();

// 只允许 POST 请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, '只允许 POST 请求', null, 405);
}

$data = get_post_data();
$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';

// 获取token（支持从Authorization header或POST body中获取）
$headers = getallheaders();
$token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : ($data['token'] ?? '');

// 验证输入
if (empty($token) || empty($email) || empty($password)) {
    json_response(false, '缺少必要参数', null, 400);
}

// 验证邮箱格式
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(false, '邮箱格式不正确', null, 400);
}

// 验证密码长度
if (strlen($password) < 6) {
    json_response(false, '密码长度不能少于6位', null, 400);
}

// 读取会话数据
$sessions = secureReadData(SESSIONS_FILE);
$session = null;

foreach ($sessions as $sessionToken => $sessionData) {
    if ($sessionToken === $token) {
        $session = $sessionData;
        break;
    }
}

if (!$session) {
    json_response(false, '无效的令牌', null, 401);
}

// 读取用户数据
$users = secureReadData(USERS_FILE);
$username = $session['username'];

if (!isset($users[$username])) {
    json_response(false, '用户不存在', null, 404);
}

$user = $users[$username];

// 检查邮箱是否已被使用
foreach ($users as $otherUsername => $otherUser) {
    if ($otherUsername !== $username && isset($otherUser['email']) && $otherUser['email'] === $email) {
        json_response(false, '邮箱已被其他用户使用', null, 400);
    }
}

// 更新用户信息
$user['email'] = $email;
$user['password'] = password_hash($password, PASSWORD_DEFAULT);
$user['email_verified'] = false; // 新邮箱需要验证

// 生成验证令牌
$verifyToken = bin2hex(random_bytes(32));
$user['verify_token'] = $verifyToken;
$user['verify_expires'] = date('Y-m-d H:i:s', time() + 86400); // 24小时有效期
$user['verify_sent_at'] = date('Y-m-d H:i:s');
$user['verify_resend_count'] = 0;

// 保存用户数据
$users[$username] = $user;
if (!secureWriteData(USERS_FILE, $users)) {
    json_response(false, '保存用户数据失败', null, 500);
}

// 记录操作
$securityLog->logAccountUpdate($username, [
    'action' => 'complete_profile',
    'email' => $email
]);

// 构建用户数据
$user_data = [
    'id' => $user['id'],
    'username' => $user['username'],
    'email' => $user['email'],
    'role' => $user['role'],
    'email_verified' => false
];

// 返回成功响应
json_response(true, '账户信息已完善', [
    'user' => $user_data,
    'email_verification' => [
        'enabled' => EMAIL_VERIFICATION_ENABLED,
        'verified' => false
    ]
], 200);
?>