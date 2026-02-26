<?php
require_once 'config.php';
require_once 'helper.php';

// 设置 CORS 和安全头
set_cors_headers();
set_security_headers();

// 直接返回 JSON 响应
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Server is running',
    'status' => 'ok',
    'server' => 'PHP',
    'version' => phpversion(),
    'timestamp' => date('Y-m-d H:i:s')
], JSON_UNESCAPED_UNICODE);
?>
