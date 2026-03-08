<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once 'config.php';
    require_once 'helper.php';
} catch (Exception $e) {
    echo json_encode(array(
        'success' => false,
        'message' => '配置加载失败: ' . $e->getMessage(),
        'online' => false,
        'players' => array('online' => 0, 'max' => 50),
        'version' => array('name' => '未知', 'protocol' => 0),
        'motd' => '配置加载失败'
    ));
    exit;
}

if (function_exists('set_cors_headers')) {
    set_cors_headers();
}
if (function_exists('set_security_headers')) {
    set_security_headers();
}

function get_cached_data() {
    if (!defined('MCSTATUS_CACHE_FILE') || !file_exists(MCSTATUS_CACHE_FILE)) {
        return null;
    }
    
    $cache_content = @file_get_contents(MCSTATUS_CACHE_FILE);
    if ($cache_content === false) {
        return null;
    }
    
    $cache_data = json_decode($cache_content, true);
    if ($cache_data === null) {
        return null;
    }
    
    $current_time = time();
    if (isset($cache_data['timestamp']) && ($current_time - $cache_data['timestamp']) < MCSTATUS_CACHE_TIME) {
        return $cache_data;
    }
    
    return null;
}

function save_cached_data($data) {
    if (!defined('MCSTATUS_CACHE_FILE')) {
        return false;
    }
    
    $cache_data = [
        'timestamp' => time(),
        'data' => $data
    ];
    
    $cache_dir = dirname(MCSTATUS_CACHE_FILE);
    if (!file_exists($cache_dir)) {
        @mkdir($cache_dir, 0755, true);
    }
    
    return @file_put_contents(MCSTATUS_CACHE_FILE, json_encode($cache_data, JSON_UNESCAPED_UNICODE));
}

function fetch_mcstatus($retry_count = 0) {
    if (!defined('MCSTATUS_API_URL') || !defined('MC_SERVER_IP') || !defined('MC_SERVER_PORT')) {
        return null;
    }
    
    $api_url = MCSTATUS_API_URL . "?ip=" . urlencode(MC_SERVER_IP) . "&port=" . MC_SERVER_PORT;
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'Mozilla/5.0'
        ]
    ]);
    
    $response = @file_get_contents($api_url, false, $context);
    
    if ($response === false) {
        if ($retry_count < MCSTATUS_MAX_RETRIES) {
            sleep(1);
            return fetch_mcstatus($retry_count + 1);
        }
        return null;
    }
    
    return $response;
}

$cached_data = get_cached_data();

if ($cached_data !== null) {
    echo json_encode($cached_data['data'], JSON_UNESCAPED_UNICODE);
    exit;
}

$response = fetch_mcstatus();

if ($response === null) {
    $error_data = [
        'success' => false,
        'message' => '无法连接到状态查询服务',
        'online' => false,
        'players' => ['online' => 0, 'max' => 50],
        'version' => ['name' => '未知', 'protocol' => 0],
        'motd' => '服务器状态查询中...',
        'from_cache' => false
    ];
    
    echo json_encode($error_data, JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode($response, true);

if ($data === null) {
    $error_data = [
        'success' => false,
        'message' => '状态数据解析失败',
        'online' => false,
        'players' => ['online' => 0, 'max' => 50],
        'version' => ['name' => '未知', 'protocol' => 0],
        'motd' => '服务器状态查询中...',
        'from_cache' => false
    ];
    
    echo json_encode($error_data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($data['code']) && $data['code'] === 200 && isset($data['data'])) {
    $server_data = $data['data'];
    $result = [
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
        'queryTime' => $data['queryTime'] ?? null,
        'from_cache' => false
    ];
    
    save_cached_data($result);
    
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} elseif (isset($data['code']) && $data['code'] === 204) {
    $result = [
        'success' => false,
        'message' => '服务器未响应',
        'online' => false,
        'players' => ['online' => 0, 'max' => 50],
        'version' => ['name' => '未知', 'protocol' => 0],
        'motd' => '服务器暂时无响应',
        'from_cache' => false
    ];
    
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} else {
    $result = [
        'success' => false,
        'message' => '服务器查询失败',
        'online' => false,
        'players' => ['online' => 0, 'max' => 50],
        'version' => ['name' => '未知', 'protocol' => 0],
        'motd' => '服务器离线',
        'from_cache' => false
    ];
    
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
}
?>
