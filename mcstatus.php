<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once 'config.php';

// 获取外部 API 响应
$api_url = MCSTATUS_API_URL . "?ip=" . urlencode(MC_SERVER_IP) . "&port=" . MC_SERVER_PORT;

$context = stream_context_create([
    'http' => [
        'timeout' => 5,
        'user_agent' => 'Mozilla/5.0'
    ]
]);

$response = @file_get_contents($api_url, false, $context);

if ($response === false) {
    // API 请求失败，返回默认数据
    echo json_encode([
        'success' => false,
        'message' => '无法连接到状态查询服务',
        'online' => false,
        'players' => ['online' => 0, 'max' => 50],
        'version' => '未知',
        'motd' => '服务器状态查询中...'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode($response, true);

if ($data === null) {
    echo json_encode([
        'success' => false,
        'message' => '状态数据解析失败',
        'online' => false,
        'players' => ['online' => 0, 'max' => 50],
        'version' => '未知',
        'motd' => '服务器状态查询中...'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 根据外部 API 的响应码处理
if (isset($data['code']) && $data['code'] === 200 && isset($data['data'])) {
    // 成功获取服务器状态（数据在 data 字段下）
    $server_data = $data['data'];
    echo json_encode([
        'success' => true,
        'online' => true,
        'players' => [
            'online' => $server_data['players']['online'] ?? 0,
            'max' => $server_data['players']['max'] ?? 50
        ],
        'version' => [
            'name' => $server_data['version'] ?? '未知',
            'protocol' => $server_data['protocol'] ?? 0
        ],
        'motd' => $server_data['motd'] ?? '欢迎',
        'favicon' => $server_data['favicon'] ?? null,
        'queryTime' => $data['queryTime'] ?? null
    ], JSON_UNESCAPED_UNICODE);
} elseif (isset($data['code']) && $data['code'] === 204) {
    // 服务器查询成功但未获取到信息
    echo json_encode([
        'success' => false,
        'message' => '服务器未响应',
        'online' => false,
        'players' => ['online' => 0, 'max' => 50],
        'version' => ['name' => '未知', 'protocol' => 0],
        'motd' => '服务器暂时无响应'
    ], JSON_UNESCAPED_UNICODE);
} else {
    // 服务器查询失败
    echo json_encode([
        'success' => false,
        'message' => '服务器查询失败',
        'online' => false,
        'players' => ['online' => 0, 'max' => 50],
        'version' => ['name' => '未知', 'protocol' => 0],
        'motd' => '服务器离线'
    ], JSON_UNESCAPED_UNICODE);
}
?>