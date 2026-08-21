<?php
/**
 * MCP Tool Base — 共享工具基底（传输无关）
 * ============================================================
 * 被 mcp-server.php（站内）和 mcp/remote.php（远程）共同引用。
 *
 * 只包含传输无关逻辑：
 *   - 工具注册表（$TOOL_REGISTRY / registerTool）
 *   - 工具角色过滤（getToolsForRole / getToolsForCustomer）
 *   - 工具分发（dispatchToolCall）
 *   - MCSM API 调用（mcsmApiCall）
 *   - 通用工具函数（formatBytes / formatUptime 等）
 *   - 工具模块自动发现（glob require mcp/tools/*.php）
 *
 * 不包含：鉴权、HTTP 路由、JSON-RPC 响应封装 —— 由各端点自行处理。
 *
 * 前置条件：调用方需在 require 本文件之前完成：
 *   1. define('ACCESS_ALLOWED', true);
 *   2. require config.php（提供 MCSM_API_KEY / MCSM_API_URL 等常量）
 *   3. require helper.php / secure_data.php / auth_helper.php（如端点需要）
 */

$mcpRoot   = __DIR__;
$projectRoot = dirname($mcpRoot);

// ── 工具注册表 ──────────────────────────────────────────

$TOOL_REGISTRY = [];

function registerTool(string $name, array $def): void
{
    global $TOOL_REGISTRY;
    $TOOL_REGISTRY[$name] = $def;
}

// ── 角色过滤 ────────────────────────────────────────────

/**
 * 根据角色获取允许的工具列表
 * admin → 全部；其它角色 → 仅 read_only
 *
 * @param string $role 'admin' | 'user'
 * @return array
 */
function getToolsForRole(string $role): array
{
    global $TOOL_REGISTRY;
    $tools = [];
    foreach ($TOOL_REGISTRY as $name => $def) {
        if ($role === 'admin' || ($def['permission'] ?? '') === 'read_only') {
            $tools[] = [
                'name'            => $def['name'],
                'description'     => $def['description'],
                'inputSchema'     => $def['inputSchema'],
                'requiresConfirm' => !empty($def['requiresConfirm']),
            ];
        }
    }
    return $tools;
}

/**
 * 按工具名白名单过滤（供客服渠道使用）
 *
 * @param string[] $allowedTools 允许的工具名列表
 * @return array
 */
function getToolsForCustomer(array $allowedTools): array
{
    global $TOOL_REGISTRY;
    $tools = [];
    foreach ($allowedTools as $name) {
        if (isset($TOOL_REGISTRY[$name])) {
            $def = $TOOL_REGISTRY[$name];
            $tools[] = [
                'name'        => $def['name'],
                'description' => $def['description'],
                'inputSchema' => $def['inputSchema'],
            ];
        }
    }
    return $tools;
}

// ── 工具分发 ────────────────────────────────────────────

/**
 * 执行指定工具的 handler，返回文本结果。
 * 不进行权限检查 —— 调用方应先完成鉴权。
 *
 * @param string $toolName 工具名
 * @param array  $args     调用参数
 * @return string handler 返回的文本
 * @throws \Exception
 */
function dispatchToolCall(string $toolName, array $args): string
{
    global $TOOL_REGISTRY;

    if (!isset($TOOL_REGISTRY[$toolName])) {
        throw new \Exception("Tool not found: {$toolName}");
    }

    $def     = $TOOL_REGISTRY[$toolName];
    $handler = $def['handler'];

    if (!function_exists($handler)) {
        throw new \Exception("Handler not found: {$handler}");
    }

    // 校验必填参数
    $schema   = $def['inputSchema'];
    $required = $schema['required'] ?? [];
    foreach ($required as $field) {
        if (!isset($args[$field]) || (is_string($args[$field]) && trim($args[$field]) === '')) {
            throw new \Exception("缺少必填参数: {$field}");
        }
    }

    return $handler($args);
}

// ── 服务器信息 ──────────────────────────────────────────

function getMcpServerInfo(): array
{
    return [
        'name'    => 'mcsm-mcp-server',
        'version' => '1.1.0',
    ];
}

// ── MCSManager API 调用 ──────────────────────────────────

/**
 * 调用 MCSManager 面板 API
 *
 * @param string $endpoint API 路径（如 /api/overview）
 * @param array  $query    GET 参数
 * @param string $method   HTTP 方法
 * @param array  $body     POST/PUT 请求体
 * @return array 解码后的响应 data 字段（已是数组）
 * @throws \Exception
 */
function mcsmApiCall(string $endpoint, array $query = [], string $method = 'GET', array $body = []): array
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
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json; charset=utf-8',
            'X-Requested-With: XMLHttpRequest',
        ],
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if (!empty($body)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        if (!empty($body)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        if (!empty($body)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
    }

    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new \Exception("MCSManager API 请求失败: {$error}");
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        throw new \Exception("MCSManager API 返回非 JSON 数据 (HTTP {$httpCode})");
    }

    if (($data['status'] ?? 0) !== 200) {
        throw new \Exception("MCSManager API 返回错误 (status: {$data['status']})");
    }

    $payload = $data['data'] ?? [];
    if (!is_array($payload)) {
        $payload = ['raw' => $payload];
    }
    return $payload;
}

// ── 通用工具函数 ────────────────────────────────────────

function normalizeCpu($cpu): ?float
{
    if ($cpu === null || $cpu === '' || !is_numeric($cpu)) {
        return null;
    }
    $cpu = (float) $cpu;
    if ($cpu >= 0 && $cpu <= 1) {
        return round($cpu * 100, 1);
    }
    return round($cpu, 1);
}

function formatBytes(int $bytes): string
{
    if ($bytes === 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 1) . ' ' . $units[(int)$i];
}

function formatUptime(int $seconds): string
{
    if ($seconds <= 0) return '0 秒';
    $days = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    $mins = floor(($seconds % 3600) / 60);

    $parts = [];
    if ($days > 0) $parts[] = "{$days} 天";
    if ($hours > 0) $parts[] = "{$hours} 小时";
    if ($mins > 0) $parts[] = "{$mins} 分钟";
    if (empty($parts)) $parts[] = "{$seconds} 秒";

    return implode(' ', $parts);
}

function calculateMemUsage(array $system): float
{
    $memUsage = $system['memUsage'] ?? 0;
    if ($memUsage > 0 && $memUsage <= 1) {
        return (float) $memUsage;
    }

    $total = $system['totalmem'] ?? 0;
    $free  = $system['freemem'] ?? 0;
    if ($total > 0 && $free >= 0 && $free <= $total) {
        return ($total - $free) / $total;
    }

    return 0.0;
}

// ── 自动发现并加载所有工具模块 ────────────────────────────
// 每个 modules file 顶层调用 registerTool() 自注册；
// 新增工具只需往 mcp/tools/ 丢一个 .php 文件即可。

foreach (glob(__DIR__ . '/tools/*.php') as $toolFile) {
    require_once $toolFile;
}
