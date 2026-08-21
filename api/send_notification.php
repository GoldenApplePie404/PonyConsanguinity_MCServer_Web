<?php
header('Content-Type: application/json; charset=utf-8');

// 引入配置文件
require_once 'config.php';
require_once 'helper.php';
require_once '../includes/auth_helper.php';

// 验证请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, '方法不允许', null, 405);
}

// 获取请求数据
$data = json_decode(file_get_contents('php://input'), true);

// 验证必要参数
if (!isset($data['title'], $data['type'], $data['content'])) {
    json_response(false, '缺少必要参数', null, 400);
}

// 验证管理员权限
AuthHelper::requireAdmin();

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