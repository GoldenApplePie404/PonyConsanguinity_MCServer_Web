<?php
require_once 'config.php';
require_once 'helper.php';
require_once 'secure_data.php';

// 设置 CORS 和安全头
set_cors_headers();
set_security_headers();

// 只允许 GET 请求
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, '只允许 GET 请求', null, 405);
}

// 获取 token
$headers = getallheaders();
$token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : '';

if (!$token) {
    json_response(false, '未提供认证令牌', null, 401);
}

// 读取会话数据
$sessions = secureReadData(SESSIONS_FILE);

if (!isset($sessions[$token])) {
    json_response(false, '令牌无效或已过期', null, 401);
}

$session = $sessions[$token];
$username = $session['username'];

// 查找用户
$users = secureReadData(USERS_FILE);

if (!isset($users[$username])) {
    json_response(false, '用户不存在', null, 404);
}

$user = $users[$username];

json_response(true, '获取成功', [
    'user' => [
        'id' => $user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'password' => $user['password'] ?? null,
        'created_at' => $user['created_at'],
        'eypa_uid' => $user['eypa_uid'] ?? null,
        'eypa_nickname' => $user['eypa_nickname'] ?? null,
        'eypa_avatar' => $user['eypa_avatar'] ?? null,
        'eypa_bound_at' => $user['eypa_bound_at'] ?? null
    ]
], 200);
?>
