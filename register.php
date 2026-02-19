<?php
require_once 'config.php';
require_once 'helper.php';
require_once 'secure_data.php';

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

if (strlen($password) < 6) {
    json_response(false, '密码长度不能少于6位', null, 400);
}

// 读取用户数据
$users = secureReadData(USERS_FILE);

// 检查用户名是否已存在
if (isset($users[$username])) {
    json_response(false, '用户名已存在', null, 400);
}

// 创建新用户
$user_id = generate_uuid();
$users[$username] = [
    'id' => $user_id,
    'username' => $username,
    'password' => $password, // 实际应用中应该使用 password_hash()
    'email' => $email,
    'created_at' => date('Y-m-d H:i:s'),
    'role' => 'user'
];

// 保存用户数据
secureWriteData(USERS_FILE, $users);
json_response(true, '注册成功', [
    'user' => [
        'id' => $user_id,
        'username' => $username,
        'email' => $email
    ]
], 201);
?>
