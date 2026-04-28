<?php
require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');

$debug = EYPA_OAUTH_DEBUG;

function debugLog($message, $data = null) {
    if (EYPA_OAUTH_DEBUG) {
        error_log("[EYPA OAuth Init] " . $message . ($data ? ": " . json_encode($data, JSON_UNESCAPED_UNICODE) : ''));
    }
}

if (!EYPA_OAUTH_ENABLED) {
    debugLog('OAuth功能未启用');
    echo json_encode([
        'success' => false,
        'message' => '马国记忆登录功能未启用'
    ]);
    exit;
}

$redirectUri = EYPA_REDIRECT_URI;
$authUrl = EYPA_AUTH_URL . '?callback=' . urlencode($redirectUri);

$debugInfo = [
    'redirect_uri' => $redirectUri,
    'auth_url' => $authUrl,
    'https' => IS_HTTPS,
    'host' => $_SERVER['HTTP_HOST'] ?? 'unknown'
];

debugLog('生成授权URL', $debugInfo);

echo json_encode([
    'success' => true,
    'auth_url' => $authUrl,
    'debug' => $debug ? $debugInfo : null
]);
?>
