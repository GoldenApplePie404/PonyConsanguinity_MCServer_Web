<?php
require_once '../config.php';
require_once '../helper.php';
require_once '../secure_data.php';

header('Content-Type: application/json; charset=utf-8');

session_start();
require_once '../../includes/auth_helper.php';

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

$isBound = !empty($user['eypa_uid']);

echo json_encode([
    'success' => true,
    'data' => [
        'is_bound' => $isBound,
        'eypa_uid' => $user['eypa_uid'] ?? null,
        'eypa_nickname' => $user['eypa_nickname'] ?? null,
        'eypa_avatar' => $user['eypa_avatar'] ?? null,
        'eypa_bound_at' => $user['eypa_bound_at'] ?? null
    ]
]);
?>
