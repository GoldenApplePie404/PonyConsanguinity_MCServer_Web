<?php
/**
 * 重置密码 - 执行重置
 * POST token + password
 * 安全设计：
 *  - 令牌一次性使用：成功后立即销毁
 *  - 30 分钟有效期
 *  - 成功后解锁账户（清空登录失败次数与锁定时间）
 *  - 成功后强制该用户所有会话下线
 */
require_once 'config.php';
require_once 'helper.php';
require_once 'secure_data.php';
require_once __DIR__ . '/../includes/security_logger.php';
require_once __DIR__ . '/../includes/auth_helper.php';
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
$token = trim($data['token'] ?? '');
$password = $data['password'] ?? '';

// 人机验证：校验滑块验证码令牌（一次性）
$captchaToken = $data['captcha_token'] ?? '';
if (!CaptchaHelper::consumeToken($captchaToken)) {
    json_response(false, '请先完成滑块验证', null, 400);
}

// 验证输入
if (empty($token)) {
    json_response(false, '缺少重置令牌', null, 400);
}

if (empty($password)) {
    json_response(false, '密码不能为空', null, 400);
}

// 密码复杂度验证（与注册规则一致）
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

// 查找包含此重置令牌的用户
$foundUsername = null;
foreach ($users as $username => $user) {
    if (isset($user['reset_token']) && $user['reset_token'] === $token) {
        $foundUsername = $username;
        break;
    }
}

if ($foundUsername === null) {
    json_response(false, '重置链接无效或已使用', null, 400);
}

$user = $users[$foundUsername];

// 检查令牌是否过期
if (!empty($user['reset_expires'])) {
    $expiresTime = strtotime($user['reset_expires']);
    if ($expiresTime !== false && $expiresTime <= time()) {
        json_response(false, '重置链接已过期，请重新申请', null, 400);
    }
} else {
    // 没有过期时间则视为无效（安全优先）
    json_response(false, '重置链接无效', null, 400);
}

// 更新密码 + 销毁令牌 + 解锁账户
$users[$foundUsername]['password'] = password_hash($password, PASSWORD_DEFAULT);
$users[$foundUsername]['reset_token'] = null;
$users[$foundUsername]['reset_expires'] = null;
$users[$foundUsername]['reset_sent_at'] = null;
$users[$foundUsername]['login_attempts'] = 0;
$users[$foundUsername]['lock_until'] = null;

if (!secureWriteData(USERS_FILE, $users)) {
    json_response(false, '保存失败，请稍后再试', null, 500);
}

// 强制该用户所有会话下线
$removedSessions = AuthHelper::destroyAllSessionsByUsername($foundUsername);

// 记录安全日志
$securityLog->logAccountUpdate($foundUsername, [
    'action' => 'password_reset',
    'sessions_removed' => $removedSessions,
    'ip' => $securityLog->getClientIP()
]);

// 返回成功
json_response(true, '密码重置成功，请使用新密码登录', [
    'sessions_removed' => $removedSessions
], 200);
?>
