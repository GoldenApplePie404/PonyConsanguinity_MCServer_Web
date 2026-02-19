<?php
require_once 'config.php';
require_once 'helper.php';
require_once 'secure_data.php';

// 只允许 GET 请求
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, '只允许 GET 请求', null, 405);
}

// 验证 Token
$token = get_token();
$session = verify_token($token);

if (!$session) {
    json_response(false, '无效的令牌', null, 401);
}

// 查找用户
$users = secureReadData(USERS_FILE);
$user = null;

foreach ($users as $username => $user_data) {
    if ($user_data['id'] === $session['user_id']) {
        $user = $user_data;
        break;
    }
}

if (!$user) {
    json_response(false, '用户不存在', null, 404);
}

json_response(true, '获取成功', [
    'user' => [
        'id' => $user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'created_at' => $user['created_at']
    ]
], 200);
?>
