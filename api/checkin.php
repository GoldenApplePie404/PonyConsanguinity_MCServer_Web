<?php
if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}

require_once 'config.php';
require_once 'helper.php';
require_once 'secure_data.php';

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
$users = secureReadData(USERS_FILE);

if (!isset($users[$username])) {
    echo json_encode([
        'success' => false,
        'message' => '用户不存在'
    ]);
    exit;
}

$user = $users[$username];

// 检查邮箱是否验证
if (!isset($user['email_verified']) || $user['email_verified'] !== true) {
    echo json_encode([
        'success' => false,
        'message' => '请先验证邮箱后再签到'
    ]);
    exit;
}

// 检查今天是否已经签到
$today = date('Y-m-d');
$lastCheckin = isset($user['check_t']) ? $user['check_t'] : '';

if ($lastCheckin === $today) {
    echo json_encode([
        'success' => false,
        'message' => '今天已经签到过了，明天再来吧'
    ]);
    exit;
}

// 签到奖励积分
$points = isset($user['points']) ? intval($user['points']) : 0;
$rewardPoints = 10; // 每次签到获得10积分
$newPoints = $points + $rewardPoints;

// 更新用户数据
$users[$username]['check_t'] = $today;
$users[$username]['points'] = $newPoints;

if (!secureWriteData(USERS_FILE, $users)) {
    echo json_encode([
        'success' => false,
        'message' => '签到失败，请稍后重试'
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => '签到成功',
    'data' => [
        'points' => $newPoints,
        'reward' => $rewardPoints,
        'checkin_date' => $today
    ]
]);
?>
