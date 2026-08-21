<?php
/**
 * 公开只读代理 — MCSManager 子服实例状态
 * ============================================================
 * 供状态页 status.html 展示万驹同源各个子服的运行情况（只读）。
 *
 * 数据流：
 *   1) /api/overview 获取所有节点（daemon）
 *   2) 逐节点 /api/service/remote_service_instances 拉取实例列表
 *   3) 对运行中（status=3）的实例并行请求 /api/instance 补充 processInfo（CPU/内存）
 *
 * 安全：
 *   - 面板密钥 MCSM_API_KEY 只留在服务端，不出现在响应中
 *   - 响应仅含公开摘要（名称/状态/版本/人数/CPU/内存），不含 uuid/daemonId/启动命令等内部信息
 *   - 写操作（启停/命令）不在此接口，走后台或 MCP
 *
 * 缓存：与 mcstatus.php 同惯例（data/*_cache.json，TTL 10 秒）
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
        'success'   => false,
        'message'   => '配置加载失败: ' . $e->getMessage(),
        'instances' => array(),
    ));
    exit;
}

if (!defined('MC_INSTANCES_CACHE_FILE')) {
    define('MC_INSTANCES_CACHE_FILE', dirname(__DIR__) . '/data/mc_instances_cache.json');
}
if (!defined('MC_INSTANCES_CACHE_TIME')) {
    define('MC_INSTANCES_CACHE_TIME', 10);
}

if (function_exists('set_cors_headers')) {
    set_cors_headers();
}
if (function_exists('set_security_headers')) {
    set_security_headers();
}

/* ── 轻量缓存（与 mcstatus.php 同惯例） ────────────────── */

function mc_instances_get_cache()
{
    if (!file_exists(MC_INSTANCES_CACHE_FILE)) {
        return null;
    }
    $content = @file_get_contents(MC_INSTANCES_CACHE_FILE);
    if ($content === false) {
        return null;
    }
    $data = json_decode($content, true);
    if (!is_array($data) || !isset($data['timestamp'])) {
        return null;
    }
    if ((time() - $data['timestamp']) < MC_INSTANCES_CACHE_TIME) {
        return $data['data'] ?? null;
    }
    return null;
}

function mc_instances_save_cache($data)
{
    $dir = dirname(MC_INSTANCES_CACHE_FILE);
    if (!file_exists($dir)) {
        @mkdir($dir, 0755, true);
    }
    return @file_put_contents(
        MC_INSTANCES_CACHE_FILE,
        json_encode(array('timestamp' => time(), 'data' => $data), JSON_UNESCAPED_UNICODE)
    );
}

/* ── MCSM API 调用（GET 只读，逻辑同 mcp/toolbase.php 的 mcsmApiCall） ── */

function mc_instances_api_call($endpoint, array $query = array())
{
    $query['apikey'] = MCSM_API_KEY;

    $cleanEndpoint = ltrim($endpoint, '/');
    if (str_starts_with($cleanEndpoint, 'api/')) {
        $cleanEndpoint = substr($cleanEndpoint, 4);
    }
    $url = rtrim(MCSM_API_URL, '/') . '/' . ltrim($cleanEndpoint, '/');
    if (!empty($query)) {
        $url .= '?' . http_build_query($query);
    }

    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER     => array(
            'Content-Type: application/json; charset=utf-8',
            'X-Requested-With: XMLHttpRequest',
        ),
    ));
    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        throw new Exception('MCSM 请求失败');
    }
    $json = json_decode($response, true);
    if ($httpCode !== 200 || !is_array($json) || ($json['status'] ?? 0) !== 200) {
        throw new Exception('MCSM 响应异常 (HTTP ' . $httpCode . ')');
    }
    return $json['data'] ?? array();
}

/* ── 并行补运行中实例详情（processInfo） ── */

function mc_instances_details_parallel(array $instances)
{
    $details = array();
    $handles = array();
    $multi = curl_multi_init();
    if (!$multi) {
        return $details;
    }

    foreach ($instances as $idx => $inst) {
        $uuid     = $inst['instanceUuid'] ?? '';
        $daemonId = $inst['daemonId'] ?? '';
        if ($uuid === '' || $daemonId === '') {
            continue;
        }
        $url = rtrim(MCSM_API_URL, '/') . '/instance';
        $url .= '?' . http_build_query(array(
            'apikey'   => MCSM_API_KEY,
            'uuid'     => $uuid,
            'daemonId' => $daemonId,
        ));

        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER     => array(
                'Content-Type: application/json; charset=utf-8',
                'X-Requested-With: XMLHttpRequest',
            ),
        ));
        curl_multi_add_handle($multi, $ch);
        $handles[$idx] = $ch;
    }

    if (empty($handles)) {
        curl_multi_close($multi);
        return $details;
    }

    $running = null;
    do {
        curl_multi_exec($multi, $running);
        if ($running > 0) {
            curl_multi_select($multi, 0.05);
        }
    } while ($running > 0);

    foreach ($handles as $idx => $ch) {
        $response = curl_multi_getcontent($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $data     = json_decode((string) $response, true);
        if ($httpCode === 200 && is_array($data) && ($data['status'] ?? 0) === 200) {
            $details[$idx] = $data['data'] ?? array();
        }
        curl_multi_remove_handle($multi, $ch);
        curl_close($ch);
    }

    curl_multi_close($multi);
    return $details;
}

/* ── 字节格式化（B/KB/MB/GB） ── */

function mc_instances_format_bytes($bytes)
{
    if ($bytes === null || $bytes === '' || !is_numeric($bytes)) {
        return null;
    }
    $bytes = (float) $bytes;
    if ($bytes < 1024) {
        return round($bytes) . ' B';
    }
    $units = array('KB', 'MB', 'GB', 'TB');
    $i = -1;
    do {
        $bytes /= 1024;
        $i++;
    } while ($bytes >= 1024 && $i < count($units) - 1);
    return round($bytes, 1) . ' ' . $units[$i];
}

/* ── 主流程 ── */

try {
    // 缓存命中直接返回
    $cached = mc_instances_get_cache();
    if ($cached !== null) {
        echo json_encode($cached, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $statusMap = array(-1 => '忙碌', 0 => '停止', 1 => '停止中', 2 => '启动中', 3 => '运行中');
    $collected = array();

    // 1) 全部节点
    $overview = mc_instances_api_call('/api/overview');
    $remotes  = $overview['remote'] ?? array();

    // 2) 逐节点拉实例列表并合并
    foreach ($remotes as $node) {
        $did = $node['uuid'] ?? '';
        if ($did === '') {
            continue;
        }
        $data = mc_instances_api_call('/api/service/remote_service_instances', array(
            'page'      => 1,
            'page_size' => 100,
            'status'    => '',
            'daemonId'  => $did,
        ));
        foreach (($data['data'] ?? array()) as $inst) {
            $inst['daemonId'] = $did;
            $collected[] = $inst;
        }
    }

    // 3) 对所有实例并行请求详情（详情接口反映 daemon 实时状态，列表接口的 status 可能滞后）
    $allIdx = array();
    foreach ($collected as $idx => $inst) {
        $allIdx[$idx] = $inst;
    }
    $details = mc_instances_details_parallel($allIdx);
    foreach ($details as $idx => $detail) {
        if (isset($detail['status'])) {
            $collected[$idx]['status'] = $detail['status'];
        }
        if (!empty($detail['processInfo']) && is_array($detail['processInfo'])) {
            $collected[$idx]['processInfo'] = $detail['processInfo'];
        }
        if (!empty($detail['info']) && is_array($detail['info'])) {
            $collected[$idx]['info'] = $detail['info'];
        }
    }

    // 组装公开摘要（不含 uuid / daemonId / 启动命令等内部信息）
    // 只展示 config.php 的 MC_DISPLAY_INSTANCES 中列出的实例，展示顺序 = 配置顺序
    $displayMap = defined('MC_DISPLAY_INSTANCES') ? MC_DISPLAY_INSTANCES : array();

    // 原始实例名 → 实例数据 映射（按配置顺序取用）
    $byNickname = array();
    foreach ($collected as $inst) {
        $nick = $inst['config']['nickname'] ?? '';
        if ($nick !== '' && !isset($byNickname[$nick])) {
            $byNickname[$nick] = $inst;
        }
    }

    // 未配置时退化为全部展示（保持原有行为）
    if (empty($displayMap)) {
        foreach (array_keys($byNickname) as $nick) {
            $displayMap[$nick] = $nick;
        }
    }

    // 子服「核心」自定义（MCSM 无核心字段）
    $cores = defined('MC_INSTANCE_CORES') ? MC_INSTANCE_CORES : array();
    // 子服「版本」硬编码映射（不读 MCSM 的 version；未配置默认 1.12.x~1.21.1）
    $versions = defined('MC_INSTANCE_VERSIONS') ? MC_INSTANCE_VERSIONS : array();

    $instances = array();
    foreach ($displayMap as $rawName => $showName) {
        if (!isset($byNickname[$rawName])) {
            continue; // 面板中不存在该实例则跳过（配置可超前，不影响展示）
        }
        $inst    = $byNickname[$rawName];
        $info    = $inst['info'] ?? array();
        $config  = $inst['config'] ?? array();
        $process = $inst['processInfo'] ?? array();
        // 运行判定：详情接口 status=3 视为运行中；即便 status 滞后，只要进程存活（pid>0）也视为运行中
        $status  = (int) ($inst['status'] ?? 0);
        $running = ($status === 3);
        $pid     = (int) ($process['pid'] ?? 0);
        if (!$running && $pid > 0) {
            $running = true;
            $status  = 3;
        }

        $cpu = null;
        if (isset($process['cpu']) && is_numeric($process['cpu'])) {
            $cpu = round((float) $process['cpu'], 1);
        }
        $mem = mc_instances_format_bytes($process['memory'] ?? null);

        $instances[] = array(
            'name'        => $showName,
            'raw_name'    => $rawName,
            'status'      => $statusMap[$status] ?? '未知',
            'status_code' => $status,
            'running'     => $running,
            'players'     => array(
                'current' => $info['currentPlayers'] ?? -1,
                'max'     => $info['maxPlayers'] ?? -1,
            ),
            'version'     => $versions[$rawName] ?? '1.12.x~1.21.1',
            'core'        => $cores[$rawName] ?? '',
            'type'        => $config['type'] ?? 'universal',
            'process'     => $running ? array('cpu' => $cpu, 'memory' => $mem) : null,
        );
    }

    $runningCount = 0;
    foreach ($instances as $i) {
        if ($i['running']) {
            $runningCount++;
        }
    }

    $result = array(
        'success'   => true,
        'ts'        => time(),
        'meta'      => array('total' => count($instances), 'running' => $runningCount),
        'instances' => $instances,
    );

    mc_instances_save_cache($result);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    echo json_encode(array(
        'success'   => false,
        'message'   => '获取子服状态失败: ' . $e->getMessage(),
        'instances' => array(),
    ));
}
