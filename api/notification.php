<?php
// 通知API
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

function getUserNotificationFile($username) {
    $userNotificationsDir = dirname(__DIR__) . '/data/user_notifications';
    return $userNotificationsDir . '/' . $username . '.json';
}

function ensureUserNotificationFile($username) {
    $userNotificationsDir = dirname(__DIR__) . '/data/user_notifications';
    
    if (!is_dir($userNotificationsDir)) {
        mkdir($userNotificationsDir, 0755, true);
    }
    
    $userFile = getUserNotificationFile($username);
    if (!file_exists($userFile)) {
        write_json($userFile, []);
    }
    
    return $userFile;
}

$action = $_GET['action'] ?? '';
$user = getCurrentUser();

if (!$user) {
    json_response(false, '请先登录', null, 401);
}

$notificationsFile = dirname(__DIR__) . '/data/notifications.json';
$username = $user['username'];

// 初始化 notifications.json 文件
if (!file_exists($notificationsFile)) {
    $defaultNotifications = [
        [
            "id" => 1,
            "title" => "欢迎加入服务器",
            "type" => "system",
            "content" => "亲爱的玩家，欢迎加入我们的服务器！",
            "created_at" => "2026-01-30 10:00:00"
        ],
        [
            "id" => 2,
            "title" => "服务器V3.5更新",
            "type" => "system",
            "content" => "服务器已更新到最新版本，更多内容请在公告页查看。",
            "created_at" => "2026-02-01 12:00:00"
        ]
    ];
    write_json($notificationsFile, $defaultNotifications);
}

// 确保用户的通知文件存在
$userNotificationFile = ensureUserNotificationFile($username);

switch ($action) {
    case 'list':
        $notifications = read_json($notificationsFile);
        $userNotifications = read_json($userNotificationFile);
        
        $readNotificationIds = [];
        foreach ($userNotifications as $notificationId) {
            $readNotificationIds[$notificationId] = true;
        }
        
        $result = [];
        foreach ($notifications as $notification) {
            // 替换占位符
            $content = $notification['content'];
            $content = str_replace('{username}', $username, $content);
            $content = str_replace('{server_name}', '万驹同源', $content);
            $content = str_replace('{server_ip}', 'mc.eqmemory.cn', $content);
            $content = str_replace('{current_date}', date('Y-m-d'), $content);
            $content = str_replace('{current_time}', date('H:i:s'), $content);
            
            $result[] = [
                'id' => $notification['id'],
                'title' => $notification['title'],
                'type' => $notification['type'],
                'content' => $content,
                'created_at' => $notification['created_at'],
                'read' => isset($readNotificationIds[$notification['id']])
            ];
        }
        
        usort($result, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        json_response(true, '获取成功', ['notifications' => $result]);
        break;
        
    case 'mark_read':
        $data = get_post_data();
        $notificationId = $data['notification_id'] ?? 0;
        
        if (!$notificationId) {
            json_response(false, '参数错误', null, 400);
        }
        
        $userNotifications = read_json($userNotificationFile);
        
        if (!in_array($notificationId, $userNotifications)) {
            $userNotifications[] = $notificationId;
            write_json($userNotificationFile, $userNotifications);
        }
        
        json_response(true, '标记成功');
        break;
        
    default:
        json_response(false, '无效的操作', null, 400);
}
?>