<?php
require_once 'config.php';
require_once 'helper.php';
require_once 'secure_data.php';

// 设置 CORS 和安全头
set_cors_headers();
set_security_headers();

// 确保 sessions.php 文件存在
if (!file_exists(SESSIONS_FILE)) {
    $content = "<?php\n";
    $content .= "if (!defined('ACCESS_ALLOWED')) {\n";
    $content .= "    header('HTTP/1.1 403 Forbidden');\n";
    $content .= "    exit;\n";
    $content .= "}\n\n";
    $content .= "return [];\n";
    $content .= "?>";
    file_put_contents(SESSIONS_FILE, $content);
}

// 只允许 POST 请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, '只允许 POST 请求', null, 405);
}

$data = get_post_data();
$username = trim($data['username'] ?? '');
$password = $data['password'] ?? '';

// 验证输入
if (empty($username) || empty($password)) {
    json_response(false, '用户名和密码不能为空', null, 400);
}

// 读取用户数据
$users = secureReadData(USERS_FILE);

// 验证用户
if (!isset($users[$username])) {
    json_response(false, '用户名或密码错误', null, 401);
}

$user = $users[$username];
if ($user['password'] !== $password) {
    json_response(false, '用户名或密码错误', null, 401);
}

// 创建会话
$sessions = secureReadData(SESSIONS_FILE);

// FIFO会话清理：如果会话数量超过限制，删除最旧的会话
if (count($sessions) >= MAX_SESSIONS) {
    // 按创建时间排序，找到最旧的会话
    uasort($sessions, function($a, $b) {
        return strtotime($a['created_at']) - strtotime($b['created_at']);
    });
    
    // 删除最旧的会话（第一个）- 兼容 PHP 7.2
    reset($sessions);
    $oldestToken = key($sessions);
    unset($sessions[$oldestToken]);
}

// 添加新会话
$token = generate_uuid();
$sessions[$token] = [
    'user_id' => $user['id'],
    'username' => $user['username'],
    'role' => $user['role'] ?? 'user',
    'created_at' => date('Y-m-d H:i:s')
];

if (!secureWriteData(SESSIONS_FILE, $sessions)) {
    json_response(false, '会话创建失败', null, 500);
}

// 返回成功响应
json_response(true, '登录成功', [
    'token' => $token,
    'user' => [
        'id' => $user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'role' => $user['role']
    ]
], 200);
?>
