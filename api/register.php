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
$username = trim($data['username'] ?? '');
$password = $data['password'] ?? '';
$email = trim($data['email'] ?? '');

// 验证输入
if (empty($username) || empty($password)) {
    json_response(false, '用户名和密码不能为空', null, 400);
}

// 密码复杂度验证
if (strlen($password) < 8) {
    json_response(false, '密码长度不能少于8位', null, 400);
}

if (!preg_match('/[A-Z]/', $password)) {
    json_response(false, '密码必须包含至少一个大写字母', null, 400);
}

if (!preg_match('/[a-z]/', $password)) {
    json_response(false, '密码必须包含至少一个小写字母', null, 400);
}

if (!preg_match('/[0-9]/', $password)) {
    json_response(false, '密码必须包含至少一个数字', null, 400);
}

// 读取用户数据
$users = secureReadData(USERS_FILE);

// 检查用户名是否已存在
if (isset($users[$username])) {
    json_response(false, '用户名已存在', null, 400);
}

// 生成验证令牌
$verify_token = bin2hex(random_bytes(32));
$verify_expires = date('Y-m-d H:i:s', time() + VERIFY_TOKEN_EXPIRY);

// 创建新用户
$user_id = generate_uuid();
$users[$username] = [
    'id' => $user_id,
    'username' => $username,
    'password' => password_hash($password, PASSWORD_DEFAULT),
    'email' => $email,
    'email_verified' => false,
    'verify_token' => $verify_token,
    'verify_expires' => $verify_expires,
    'verify_sent_at' => date('Y-m-d H:i:s'),
    'verify_resend_count' => 1,
    'created_at' => date('Y-m-d H:i:s'),
    'role' => 'user',
    'login_attempts' => 0,
    'lock_until' => null
];

// 保存用户数据
secureWriteData(USERS_FILE, $users);

// 记录注册日志
$securityLog->logRegister($username, $email);

// 发送验证邮件（如果启用了邮箱验证）
$email_sent = false;
if (EMAIL_VERIFICATION_ENABLED && !empty($email)) {
    require_once __DIR__ . '/../includes/mail_helper.php';
    $mailHelper = MailHelper::getInstance();
    
    // 构建验证链接
    $protocol = IS_HTTPS ? 'https' : 'http';
    $verify_url = "{$protocol}://{$_SERVER['HTTP_HOST']}/verify.html?token={$verify_token}";
    
    // 发送邮件
    $mail_result = $mailHelper->sendVerificationEmail($email, $username, $verify_url);
    $email_sent = $mail_result['success'];
    
    if (!$email_sent) {
        // 记录邮件发送失败日志，但不影响注册流程
        error_log("验证邮件发送失败: " . ($mail_result['message'] ?? '未知错误'));
    }
}

// 返回响应
$response_data = [
    'user' => [
        'id' => $user_id,
        'username' => $username,
        'email' => $email,
        'email_verified' => false
    ]
];

// 如果启用了邮箱验证，添加相应提示
if (EMAIL_VERIFICATION_ENABLED && !empty($email)) {
    $response_data['email_verification'] = [
        'enabled' => true,
        'email_sent' => $email_sent,
        'message' => $email_sent ? '验证邮件已发送，请查收' : '验证邮件发送失败，请稍后重试'
    ];
    json_response(true, '注册成功，请验证您的邮箱', $response_data, 201);
} else {
    json_response(true, '注册成功', $response_data, 201);
}
?>
