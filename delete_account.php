<?php
// 账户注销API
require_once 'config.php';
require_once 'helper.php';
require_once 'secure_data.php';

function getCurrentUser() {
    $headers = getallheaders();
    $token = $headers['Authorization'] ?? '';
    
    if (strpos($token, 'Bearer ') === 0) {
        $token = substr($token, 7);
    }
    
    if (empty($token)) {
        return null;
    }
    
    $sessions = secureReadData(SESSIONS_FILE);
    return $sessions[$token] ?? null;
}

$user = getCurrentUser();

if (!$user) {
    json_response(false, '请先登录', null, 401);
}

$data = get_post_data();
$password = $data['password'] ?? '';

if (empty($password)) {
    json_response(false, '请输入密码', null, 400);
}

// 验证密码
$users = secureReadData(USERS_FILE);
$username = $user['username'];

// 使用与登录相同的方式验证用户
if (!isset($users[$username])) {
    json_response(false, '用户不存在', null, 404);
}

$userData = $users[$username];
if ($userData['password'] !== $password) {
    json_response(false, '密码错误', null, 401);
}

// 删除用户数据
unset($users[$username]);
if (!secureWriteData(USERS_FILE, $users)) {
    json_response(false, '删除用户数据失败', null, 500);
}

// 删除用户会话
$sessions = secureReadData(SESSIONS_FILE);
foreach ($sessions as $token => $sessionData) {
    if ($sessionData['username'] === $username) {
        unset($sessions[$token]);
    }
}

if (!secureWriteData(SESSIONS_FILE, $sessions)) {
    json_response(false, '清理会话数据失败', null, 500);
}

// 删除用户通知记录
$notifications = secureReadData(NOTIFICATIONS_FILE);
$updatedNotifications = [];

foreach ($notifications as $notification) {
    // 保留非当前用户的通知
    if (!isset($notification['username']) || $notification['username'] !== $username) {
        $updatedNotifications[] = $notification;
    }
}

if (!secureWriteData(NOTIFICATIONS_FILE, $updatedNotifications)) {
    json_response(false, '清理通知数据失败', null, 500);
}

// 删除用户通知文件
$userNotificationFile = dirname(__DIR__) . '/data/user_notifications/' . $username . '.json';
if (file_exists($userNotificationFile)) {
    if (!unlink($userNotificationFile)) {
        json_response(false, '删除用户通知文件失败', null, 500);
    }
}

json_response(true, '账户注销成功');

?>