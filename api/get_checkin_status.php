<?php
if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}

require_once 'config.php';
require_once 'helper.php';
require_once 'secure_data.php';
require_once 'UserManager.php';

header('Content-Type: application/json; charset=utf-8');

session_start();
require_once '../includes/auth_helper.php';

// 验证登录
if (!AuthHelper::validateToken()) {
    echo json_encode([
        'success' => false,
        'message' => '请先登录'
    ]);
    exit;
}

$username = AuthHelper::getUsernameFromToken();
$manager = new UserManager();

// 获取签到状态
$result = $manager->getCheckinStatus($username);

if ($result['success']) {
    echo json_encode([
        'success' => true,
        'data' => [
            'points' => $result['points'],
            'experience' => $result['experience'],
            'has_checked_in' => $result['has_checked_in'],
            'last_checkin' => $result['last_checkin'],
            'email_verified' => $result['email_verified']
        ]
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => $result['message']
    ]);
}
?>
