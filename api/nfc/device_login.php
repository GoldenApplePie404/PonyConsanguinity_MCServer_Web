<?php
/**
 * NFC 设备授权登录端点（单端点同时收 GET / POST）
 *
 *   Flow① 碰一下跳页：GET  ?dev=DEV001&tok=TOKEN
 *         —— 手机 OS 读取 NDEF 自动唤起浏览器访问该 URL。
 *
 *   Flow② 登录页 Web NFC：POST body {dev, tok}
 *         —— 前端用 Web NFC 读出 dev/tok 后 fetch，令牌不落 URL 栏。
 *
 * 行为：
 *   - 用 dev 查用户 → 校验 device_token → 仅调用 AuthHelper::createSession() 建会话
 *       （不复刻任何登录逻辑 / 不碰 password_verify 的账户体系）
 *   - GET 成功：302 到 pages/nfc_login_success.html（浏览器跳转）
 *   - POST 成功：返回 JSON，由前端写 localStorage 并跳转（复用同一成功页）
 *   - 失败：写调试日志；debug=1 回显 JSON，否则 GET 跳失败态 / POST 返 JSON
 *
 * 约束：仅新建文件，不修改 api/login.php / includes/auth_helper.php / data/users.php。
 */

require_once __DIR__ . '/../config.php';                // config + helper + secure_data
require_once __DIR__ . '/../../includes/auth_helper.php';
require_once __DIR__ . '/../../includes/nfc_auth.php';

// ── 调试开关：GET ?debug=1 或 POST body.debug=true ──
$debug = (isset($_GET['debug']) && $_GET['debug'] === '1');
if (!$debug && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $probe = json_decode((string)file_get_contents('php://input'), true);
    if (is_array($probe) && !empty($probe['debug'])) {
        $debug = true;
    }
}

$debugInfo = [
    'endpoint' => 'api/nfc/device_login.php',
    'method'   => $_SERVER['REQUEST_METHOD'],
    'time'     => date('Y-m-d H:i:s'),
    'debug'    => $debug,
];

/**
 * 统一失败出口：写日志 + 按模式返回。
 *
 * @param int    $code    HTTP 状态码
 * @param string $msg     失败原因（会给终端用户/调试）
 * @param array  $dInfo   调试信息（引用，便于附加 error 字段）
 */
function nfc_fail($code, $msg, array &$dInfo)
{
    global $debug;
    nfc_debug_log('fail', ['reason' => $msg, 'code' => $code]);
    $dInfo['error'] = $msg;

    if ($debug) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'message' => $msg,
            'debug'   => $dInfo,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        json_response(false, $msg, null, $code);
    }

    // GET（浏览器经 NDEF 跳转）：转到成功页的失败态
    $redirect = '../../pages/nfc_login_success.html?fail=1&msg=' . urlencode($msg);
    nfc_debug_log('redirect', ['target' => $redirect, 'mode' => 'fail_302']);
    header('Location: ' . $redirect);
    exit;
}

// ── 1) 收取 dev / tok ──
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $dev = trim($_GET['dev'] ?? '');
    $tok = trim($_GET['tok'] ?? '');
} else {
    $input = get_post_data();
    $dev = trim($input['dev'] ?? '');
    $tok = trim($input['tok'] ?? '');
}

$debugInfo['dev']        = $dev;
$debugInfo['tok_len']    = strlen($tok);
$debugInfo['tok_preview'] = $tok !== '' ? substr($tok, 0, 8) . '…' : '';
nfc_debug_log('request', [
    'method'  => $_SERVER['REQUEST_METHOD'],
    'dev'     => $dev,
    'tok_len' => strlen($tok),
]);

if ($dev === '' || $tok === '') {
    nfc_fail(400, '缺少设备标识 dev 或令牌 tok', $debugInfo);
}

// ── 2) 用 dev 查用户 ──
$found = nfc_find_user_by_device($dev);
if ($found === null) {
    nfc_fail(401, '设备未授权（未找到绑定用户）', $debugInfo);
}
$username = $found['username'];
$user     = $found['user'];
$debugInfo['username'] = $username;

// ── 3) 校验令牌 ──
$verify = nfc_verify_token($user, $tok);
if (!$verify['ok']) {
    nfc_fail(401, '设备令牌校验失败：' . $verify['reason'], $debugInfo);
}
$debugInfo['verify'] = 'ok';

// ── 4) 创建会话（仅调用既有 AuthHelper，不复刻登录逻辑）──
$sessionToken = AuthHelper::createSession([
    'id'       => $user['id'] ?? '',
    'username' => $user['username'] ?? $username,
    'role'     => $user['role'] ?? 'user',
]);
$debugInfo['session_created']          = true;
$debugInfo['session_token_preview']     = substr($sessionToken, 0, 8) . '…';
nfc_debug_log('session_created', [
    'username'      => $user['username'] ?? $username,
    'token_preview' => substr($sessionToken, 0, 8) . '…',
]);

// ── 5) 一次性令牌：用即废 ──
if (NFC_TOKEN_ONETIME) {
    nfc_mark_token_used($username);
    $debugInfo['token_marked_used'] = true;
}

// ── 6) 302 到成功页 / 返回 JSON ──
$redirect = '../../pages/nfc_login_success.html'
    . '?token='    . urlencode($sessionToken)
    . '&username=' . urlencode($user['username'] ?? $username)
    . '&nickname=' . urlencode($user['username'] ?? $username)
    . '&role='     . urlencode($user['role'] ?? 'user')
    . '&dev='      . urlencode($dev);

if ($debug) {
    $debugInfo['redirect_target'] = $redirect;
    nfc_debug_log('redirect', ['target' => $redirect, 'mode' => 'debug_json']);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'message' => 'NFC 设备登录成功',
        'data'    => [
            'token'    => $sessionToken,
            'username' => $user['username'] ?? $username,
            'role'     => $user['role'] ?? 'user',
            'dev'      => $dev,
            'redirect' => $redirect,
        ],
        'debug' => $debugInfo,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    nfc_debug_log('redirect', ['target' => $redirect, 'mode' => '302']);
    header('Location: ' . $redirect);
    exit;
}

// POST（Web NFC fetch）：返回 JSON，前端负责写 localStorage 并跳转
$debugInfo['redirect_target'] = $redirect;
json_response(true, 'NFC 设备登录成功', [
    'token'    => $sessionToken,
    'username' => $user['username'] ?? $username,
    'role'     => $user['role'] ?? 'user',
    'dev'      => $dev,
    'redirect' => $redirect,
]);
