<?php
// ⚠️ 已弃用：早期连通性测试残留，前端/后端零引用，仅保留存档。
// 测试API接口和日志系统
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'status' => 'success',
    'message' => '测试接口正常工作',
    'timestamp' => date('Y-m-d H:i:s'),
    'server_info' => [
        'php_version' => phpversion(),
        'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'N/A',
        'script_filename' => $_SERVER['SCRIPT_FILENAME'] ?? 'N/A',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'N/A'
    ]
]);
?>