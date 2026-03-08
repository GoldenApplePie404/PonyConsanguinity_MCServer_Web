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

// 调试信息
error_log('get_checkin_status - username: ' . $username);
error_log('get_checkin_status - user email_verified: ' . var_export($user['email_verified'] ?? 'not set', true));
error_log('get_checkin_status - user points: ' . var_export($user['points'] ?? 'not set', true));
error_log('get_checkin_status - user check_t: ' . var_export($user['check_t'] ?? 'not set', true));

$today = date('Y-m-d');
$lastCheckin = isset($user['check_t']) ? $user['check_t'] : '';
$hasCheckedIn = ($lastCheckin === $today);

echo json_encode([
    'success' => true,
    'data' => [
        'points' => isset($user['points']) ? intval($user['points']) : 0,
        'has_checked_in' => $hasCheckedIn,
        'last_checkin' => $lastCheckin,
        'email_verified' => isset($user['email_verified']) && $user['email_verified'] === true
    ]
]);
?>
