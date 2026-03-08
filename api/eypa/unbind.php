<?php
require_once '../config.php';
require_once '../helper.php';
require_once '../secure_data.php';

header('Content-Type: application/json; charset=utf-8');

$debug = EYPA_OAUTH_DEBUG;

function debugLog($message, $data = null) {
    if (EYPA_OAUTH_DEBUG) {
        error_log("[EYPA Unbind] " . $message . ($data ? ": " . json_encode($data, JSON_UNESCAPED_UNICODE) : ''));
    }
}

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

if (!isset($users[$username]['eypa_uid'])) {
    echo json_encode([
        'success' => false,
        'message' => '您尚未绑定马国记忆账号'
    ]);
    exit;
}

$users[$username]['eypa_uid'] = null;
$users[$username]['eypa_nickname'] = null;
$users[$username]['eypa_avatar'] = null;
$users[$username]['eypa_bound_at'] = null;

if (!secureWriteData(USERS_FILE, $users)) {
    echo json_encode([
        'success' => false,
        'message' => '解绑失败，请稍后重试'
    ]);
    exit;
}

debugLog('解绑成功', ['username' => $username]);

echo json_encode([
    'success' => true,
    'message' => '马国记忆账号已解绑'
]);
?>
