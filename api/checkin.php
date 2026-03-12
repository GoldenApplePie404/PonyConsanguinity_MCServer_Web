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

// 检查用户是否存在
$user = $manager->getUser($username);
if (!$user) {
    echo json_encode([
        'success' => false,
        'message' => '用户不存在'
    ]);
    exit;
}

// 检查邮箱是否验证
if (!isset($user['email_verified']) || $user['email_verified'] !== true) {
    echo json_encode([
        'success' => false,
        'message' => '请先验证邮箱后再签到'
    ]);
    exit;
}

// 执行签到
$result = $manager->checkin($username);

if ($result['success']) {
    echo json_encode([
        'success' => true,
        'message' => '签到成功',
        'data' => [
            'points' => $result['points'],
            'experience' => $result['experience'],
            'reward' => $result['reward'],
            'reward_experience' => $result['reward_experience'],
            'checkin_date' => date('Y-m-d')
        ]
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => $result['message']
    ]);
}
?>
