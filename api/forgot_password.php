<?php
/**
 * 忘记密码 - 发起重置请求
 * POST email
 * 安全设计：
 *  - 防枚举：无论邮箱是否存在，均返回相同的成功提示
 *  - 频率限制：同一邮箱 2 分钟内只能发送一次
 *  - 令牌 30 分钟有效期，一次性使用
 */
require_once 'config.php';
require_once 'helper.php';
require_once 'secure_data.php';
require_once __DIR__ . '/../includes/security_logger.php';
require_once __DIR__ . '/../includes/captcha_helper.php';

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

// 人机验证：校验滑块验证码令牌（一次性）
$captchaToken = $data['captcha_token'] ?? '';
if (!CaptchaHelper::consumeToken($captchaToken)) {
    json_response(false, '请先完成滑块验证', null, 400);
}

// 验证输入
if (empty($email)) {
    json_response(false, '邮箱不能为空', null, 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(false, '邮箱格式不正确', null, 400);
}

// 统一成功提示文案（防枚举：不区分邮箱是否存在）
$genericSuccess = '如果该邮箱已注册，重置邮件已发送，请查收（包括垃圾箱）';

// 读取用户数据
$users = secureReadData(USERS_FILE);

// 查找邮箱对应的用户（正常情况邮箱唯一）
// 防御：若数据异常出现同邮箱多账号，要求用户名精确定位，避免重置错账号
$foundUsernames = [];
foreach ($users as $username => $user) {
    if (isset($user['email']) && strtolower($user['email']) === strtolower($email)) {
        $foundUsernames[] = $username;
    }
}

// 邮箱不存在：统一提示，不暴露信息
if (count($foundUsernames) === 0) {
    json_response(true, $genericSuccess, null, 200);
}

// 数据异常：同邮箱多账号，要求补充用户名定位
if (count($foundUsernames) > 1) {
    $requestedUsername = trim($data['username'] ?? '');
    if ($requestedUsername === '') {
        json_response(false, '该邮箱绑定多个账号，请同时输入用户名', ['need_username' => true], 400);
    }
    if (!in_array($requestedUsername, $foundUsernames, true)) {
        // 用户名不匹配：统一提示，不暴露邮箱绑定关系
        json_response(true, $genericSuccess, null, 200);
    }
    $foundUsername = $requestedUsername;
} else {
    $foundUsername = $foundUsernames[0];
}

$user = $users[$foundUsername];

// 频率限制检查（2 分钟内只能发送一次）
// 注意：受限时也返回统一成功文案，避免通过响应差异枚举邮箱
if (!empty($user['reset_sent_at'])) {
    $lastSent = strtotime($user['reset_sent_at']);
    $now = time();
    if (($now - $lastSent) < RESET_RESEND_INTERVAL) {
        $waitSeconds = RESET_RESEND_INTERVAL - ($now - $lastSent);
        json_response(true, $genericSuccess, ['wait_seconds' => $waitSeconds], 200);
    }
}

// 生成重置令牌
$resetToken = bin2hex(random_bytes(32));
$resetExpires = date('Y-m-d H:i:s', time() + RESET_TOKEN_EXPIRY);

// 更新用户数据
$users[$foundUsername]['reset_token'] = $resetToken;
$users[$foundUsername]['reset_expires'] = $resetExpires;
$users[$foundUsername]['reset_sent_at'] = date('Y-m-d H:i:s');

if (!secureWriteData(USERS_FILE, $users)) {
    json_response(false, '服务器繁忙，请稍后再试', null, 500);
}

// 记录安全日志
$securityLog->logSecurityAlert($foundUsername, 'password_reset_requested', [
    'email' => $email,
    'ip' => $securityLog->getClientIP()
]);

// 检查邮件功能是否启用
if (!EMAIL_VERIFICATION_ENABLED) {
    // 邮件功能未启用：直接返回成功（不暴露细节），但开发环境下附上链接便于测试
    $isLocalhost = isset($_SERVER['HTTP_HOST']) && (
        strpos($_SERVER['HTTP_HOST'], 'localhost') !== false ||
        strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false
    );
    $response = ['success' => true, 'message' => $genericSuccess];
    if ($isLocalhost) {
        $resetUrl = SITE_URL . '/pages/login.html?reset_token=' . $resetToken;
        $response['reset_link'] = $resetUrl;
        $response['message'] = '开发模式：邮件功能未启用，请使用以下链接重置';
    }
    json_response(true, $response['message'], $response, 200);
}

// 构建重置链接（登录页内嵌重置表单，带 token）
$resetUrl = SITE_URL . '/pages/login.html?reset_token=' . $resetToken;

// 发送邮件
try {
    require_once __DIR__ . '/../includes/mail_helper.php';
    $mailHelper = MailHelper::getInstance();
    $result = $mailHelper->sendResetPasswordEmail($email, $foundUsername, $resetUrl);

    $isLocalhost = isset($_SERVER['HTTP_HOST']) && (
        strpos($_SERVER['HTTP_HOST'], 'localhost') !== false ||
        strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false
    );

    if ($result['success']) {
        $response = ['success' => true, 'message' => $genericSuccess];
        if ($isLocalhost) {
            $response['reset_link'] = $resetUrl;
            $response['message'] = '开发模式：请使用以下链接重置密码';
        }
        json_response(true, $response['message'], $response, 200);
    } else {
        error_log("重置邮件发送失败: " . ($result['message'] ?? '未知错误'));
        json_response(false, '邮件发送失败，请稍后再试', null, 500);
    }
} catch (Throwable $e) {
    error_log("重置邮件发送异常: " . $e->getMessage());
    json_response(false, '邮件发送失败，请稍后再试', null, 500);
}
?>
