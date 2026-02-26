<?php
header('Content-Type: application/json; charset=utf-8');

// 引入配置文件
require_once 'config.php';
require_once 'secure_data.php';

// 验证请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => '方法不允许'
    ]);
    exit;
}

// 获取请求数据
$data = json_decode(file_get_contents('php://input'), true);

// 验证必要参数
if (!isset($data['title'], $data['type'], $data['content'])) {
    echo json_encode([
        'success' => false,
        'message' => '缺少必要参数'
    ]);
    exit;
}

// 验证用户登录状态
$headers = getallheaders();
$token = $headers['Authorization'] ?? '';

if (strpos($token, 'Bearer ') === 0) {
    $token = substr($token, 7);
}

if (empty($token)) {
    echo json_encode([
        'success' => false,
        'message' => '请先登录'
    ]);
    exit;
}

// 检查会话
$sessions = secureReadData(SESSIONS_FILE);
$currentUser = $sessions[$token] ?? null;

if (!$currentUser) {
    echo json_encode([
        'success' => false,
        'message' => '请先登录'
    ]);
    exit;
}

// 检查管理员权限
if ($currentUser['role'] !== 'admin') {
    echo json_encode([
        'success' => false,
        'message' => '权限不足，只有管理员可以发送通知'
    ]);
    exit;
}

// 读取现有通知
$notifications = [];
$notificationsFile = dirname(__DIR__) . '/data/notifications.json';

if (file_exists($notificationsFile)) {
    $notifications = json_decode(file_get_contents($notificationsFile), true);
    if (!is_array($notifications)) {
        $notifications = [];
    }
}

// 生成通知ID
$maxId = 0;
foreach ($notifications as $notification) {
    if (isset($notification['id']) && $notification['id'] > $maxId) {
        $maxId = $notification['id'];
    }
}
$newId = $maxId + 1;

// 创建新通知
$newNotification = [
    'id' => $newId,
    'title' => $data['title'],
    'type' => $data['type'],
    'content' => $data['content'],
    'created_at' => date('Y-m-d H:i:s')
];

// 添加新通知到列表
array_unshift($notifications, $newNotification);

// 保存通知数据
$success = file_put_contents($notificationsFile, json_encode($notifications, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

if (!$success) {
    echo json_encode([
        'success' => false,
        'message' => '保存通知失败'
    ]);
    exit;
}

// 返回成功响应
echo json_encode([
    'success' => true,
    'message' => '通知发送成功',
    'data' => $newNotification
]);
?>