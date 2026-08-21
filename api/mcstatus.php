<?php
/**
 * 服务器状态代理 — mcstatus.php
 * ============================================================
 * 供状态页 status.html / mod.html / admin-hub.html 展示万驹同源主服状态（只读）。
 *
 * 数据流：
 *   - 内部调用 mcstatus API（MCSTATUS_API_URL），参数 ?host=&port=（2026-08-21 起迁移自旧 mcstatus.goldenapplepie.xyz）
 *   - 对外响应结构保持不变（success/online/players/version/motd/favicon），前端无需改动
 *
 * 新 API 返回结构（data 内）：
 *   online / latency_ms / version{name,protocol,brand} / players{online,max,sample}
 *   / motd{raw,plain_text,html} / favicon{base64,saved_path,url} / protocol_used / srv_used
 *
 * 缓存：data/mcstatus_cache.json（TTL MCSTATUS_CACHE_TIME，默认 60 秒）
 */

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

/**
 * 调用新 mcstatus API（参数 ?host=&port=，返回 success/code/message/data 结构）
 */
function fetch_mcstatus($retry_count = 0, $ip = null, $port = null) {
    if (!defined('MCSTATUS_API_URL')) {
        return null;
    }
    $ip = $ip ?: (defined('MC_SERVER_IP') ? MC_SERVER_IP : null);
    $port = $port ?: (defined('MC_SERVER_PORT') ? MC_SERVER_PORT : 25565);
    if (!$ip) {
        return null;
    }
    
    $api_url = rtrim(MCSTATUS_API_URL, '/') . '/ping?host=' . urlencode($ip) . '&port=' . (int)$port;
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'Mozilla/5.0'
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ]
    ]);
    
    $response = @file_get_contents($api_url, false, $context);
    
    if ($response === false) {
        if ($retry_count < MCSTATUS_MAX_RETRIES) {
            sleep(1);
            return fetch_mcstatus($retry_count + 1, $ip, $port);
        }
        return null;
    }
    
    return $response;
}

/**
 * 解析新 API 响应 → 兼容的对外结构
 */
function parse_mcstatus_response($rawJson) {
    $data = json_decode($rawJson, true);
    if (!is_array($data)) {
        return null;
    }

    // 新 API 成功判定：success=true 且 code=0；失败时 data.online=false 仍有 data 主体
    $isSuccess = (($data['success'] ?? false) === true) && (($data['code'] ?? -1) === 0);
    $sd = $data['data'] ?? array();

    if ($isSuccess && !empty($sd['online'])) {
        $result = [
            'success' => true,
            'online' => true,
            'players' => [
                'online' => $sd['players']['online'] ?? 0,
                'max' => $sd['players']['max'] ?? 50
            ],
            'version' => [
                'name' => $sd['version']['name'] ?? '未知',
                'protocol' => $sd['version']['protocol'] ?? 0
            ],
            'motd' => $sd['motd']['plain_text'] ?? '欢迎',
            'favicon' => $sd['favicon']['url'] ?? null,
            'latency_ms' => $sd['latency_ms'] ?? null,
            'queryTime' => time(),
            'from_cache' => false
        ];
        return $result;
    }

    // 离线 / 查询失败（新 API 返回 success=false，data 内 online=false）
    if (isset($sd['online']) && $sd['online'] === false) {
        return [
            'success' => false,
            'message' => $data['message'] ?? '服务器离线',
            'online' => false,
            'players' => ['online' => 0, 'max' => 50],
            'version' => ['name' => '未知', 'protocol' => 0],
            'motd' => '服务器离线',
            'from_cache' => false
        ];
    }

    return null;
}

// mod.html 专用：?server=mc 查询 mc 子服真实数据（实时，不走缓存）
$req_server = isset($_GET['server']) ? preg_replace('/[^a-z0-9]/i', '', $_GET['server']) : '';
if ($req_server === 'mc') {
    $response = fetch_mcstatus(0, 'mc.goldenapplepie.xyz', 25565);
    if ($response === null) {
        echo json_encode(array(
            'success' => false, 'message' => '无法连接到状态查询服务',
            'online' => false, 'players' => array('online' => 0, 'max' => 10),
            'version' => array('name' => '未知', 'protocol' => 0),
            'motd' => '服务器状态查询中...', 'from_cache' => false
        ), JSON_UNESCAPED_UNICODE);
        exit;
    }
    $parsed = parse_mcstatus_response($response);
    if ($parsed !== null && $parsed['online']) {
        echo json_encode($parsed, JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(array(
        'success' => false, 'message' => $parsed['message'] ?? '服务器查询失败',
        'online' => false, 'players' => array('online' => 0, 'max' => 10),
        'version' => array('name' => '未知', 'protocol' => 0), 'motd' => '服务器离线', 'from_cache' => false
    ), JSON_UNESCAPED_UNICODE);
    exit;
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

$parsed = parse_mcstatus_response($response);

if ($parsed === null) {
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

if ($parsed['online']) {
    save_cached_data($parsed);
}

echo json_encode($parsed, JSON_UNESCAPED_UNICODE);
?>
