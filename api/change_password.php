<?php
/**
 * 修改密码（个人中心，已登录用户）
 * POST old_password + new_password + captcha_token（+ 可选 token 用于保留当前会话）
 * 安全设计：
 *  - 必须登录
 *  - 校验旧密码（password_verify）
 *  - 新密码复杂度与注册一致（8位 + 大小写 + 数字）
 *  - 滑块验证码令牌（一次性）
 *  - 成功后踢掉该用户其他设备会话，保留当前会话
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

// 人机验证：校验滑块验证码令牌（一次性）
$captchaToken = $data['captcha_token'] ?? '';
if (!CaptchaHelper::consumeToken($captchaToken)) {
    json_response(false, '请先完成滑块验证', null, 400);
}

// 验证登录
$session = AuthHelper::requireLogin();
$username = $session['username'];

$oldPassword = $data['old_password'] ?? '';
$newPassword = $data['new_password'] ?? '';
$currentToken = trim($data['token'] ?? '');

// 验证输入
if (empty($oldPassword) || empty($newPassword)) {
    json_response(false, '请填写完整密码信息', null, 400);
}

// 新旧密码不能相同
if ($oldPassword === $newPassword) {
    json_response(false, '新密码不能与旧密码相同', null, 400);
}

// 新密码复杂度验证（与注册规则一致）
if (strlen($newPassword) < 8) {
    json_response(false, '密码长度不能少于8位', null, 400);
}

if (!preg_match('/[A-Z]/', $newPassword)) {
    json_response(false, '密码必须包含至少一个大写字母', null, 400);
}

if (!preg_match('/[a-z]/', $newPassword)) {
    json_response(false, '密码必须包含至少一个小写字母', null, 400);
}

if (!preg_match('/[0-9]/', $newPassword)) {
    json_response(false, '密码必须包含至少一个数字', null, 400);
}

// 读取用户数据
$users = secureReadData(USERS_FILE);

if (!isset($users[$username])) {
    json_response(false, '用户不存在', null, 404);
}

$user = $users[$username];

// 校验旧密码
if (!password_verify($oldPassword, $user['password'])) {
    $securityLog->logLoginFailure($username, '修改密码时旧密码错误');
    json_response(false, '旧密码不正确', null, 400);
}

// 更新密码
$users[$username]['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
// 重置登录失败计数（防误判）
$users[$username]['login_attempts'] = 0;
$users[$username]['lock_until'] = null;

if (!secureWriteData(USERS_FILE, $users)) {
    json_response(false, '保存失败，请稍后再试', null, 500);
}

// 踢掉该用户其他设备会话（保留当前会话）
$removedSessions = 0;
if ($currentToken !== '') {
    $removedSessions = AuthHelper::destroyOtherSessionsByUsername($username, $currentToken);
} else {
    $removedSessions = AuthHelper::destroyAllSessionsByUsername($username);
}

// 记录安全日志
$securityLog->logAccountUpdate($username, [
    'action' => 'change_password',
    'other_sessions_removed' => $removedSessions,
    'ip' => $securityLog->getClientIP()
]);

// 返回成功
json_response(true, '密码修改成功，其他设备已下线', [
    'other_sessions_removed' => $removedSessions
], 200);
?>
