<?php
// ── 环境兼容 & 错误控制 ───────────────────────────────────

error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// PHP 7.x 兼容
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }
}

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_RECOVERABLE_ERROR], true)) {
        if (headers_sent() === false) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
            echo json_encode([
                'jsonrpc' => '2.0',
                'error'   => [
                    'code'    => -32603,
                    'message' => 'Internal fatal error: ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line'],
                ],
                'id'      => null,
            ], JSON_UNESCAPED_UNICODE);
        }
    }
});

// ── 引导 ────────────────────────────────────────────────

$mcpRoot = __DIR__;
$projectRoot = dirname($mcpRoot);

define('ACCESS_ALLOWED', true);
require_once $projectRoot . '/config/config.php';
require_once $projectRoot . '/api/helper.php';

// ── 加载共享工具基底 ─────────────────────────────────────
// 自动加载所有 mcp/tools/*.php 并注册工具
require_once __DIR__ . '/toolbase.php';

// ── 鉴权：Service Key → 角色 ────────────────────────────

/**
 * 从 Authorization 头解析 Service Key，映射到角色
 * 查 config.php 的 MCP_SERVICE_KEYS（SHA256 hash => 角色）
 *
 * @return string 'admin' | 'user' | 'guest'
 */
function resolveRemotePermission(): string
{
    if (!defined('MCP_SERVICE_KEYS')) {
        return 'guest';
    }

    $keyMap = MCP_SERVICE_KEYS;
    if (!is_array($keyMap) || empty($keyMap)) {
        return 'guest';
    }

    // 从 Authorization 头提取 Bearer token
    $token = '';
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    if (isset($headers['Authorization'])) {
        $token = str_replace('Bearer ', '', $headers['Authorization']);
    } elseif (isset($headers['authorization'])) {
        $token = str_replace('Bearer ', '', $headers['authorization']);
    }
    if (empty($token) && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
    }
    if (empty($token) && !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $token = str_replace('Bearer ', '', $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    }

    if (empty($token)) {
        return 'guest';
    }

    // SHA256 hash 后查 MCP_SERVICE_KEYS
    $hash = hash('sha256', $token);
    if (isset($keyMap[$hash])) {
        return $keyMap[$hash];
    }

    return 'guest';
}

// ── JSON-RPC 分发入口 ───────────────────────────────────

header('Content-Type: application/json; charset=utf-8');
set_cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['jsonrpc' => '2.0', 'error' => ['code' => -32000, 'message' => 'Method not allowed'], 'id' => null]);
    exit;
}

$rawBody = file_get_contents('php://input');
$request = json_decode($rawBody, true);

if (!is_array($request) || !isset($request['jsonrpc']) || $request['jsonrpc'] !== '2.0') {
    http_response_code(400);
    echo json_encode(['jsonrpc' => '2.0', 'error' => ['code' => -32600, 'message' => 'Invalid Request'], 'id' => null]);
    exit;
}

$method = $request['method'] ?? '';
$params = $request['params'] ?? [];
$id     = $request['id'] ?? null;

try {
    switch ($method) {

        case 'initialize':
            $response = handleRemoteInitialize($params, $id);
            break;

        case 'tools/list':
            $response = handleRemoteToolsList($id);
            break;

        case 'tools/call':
            $response = handleRemoteToolsCall($params, $id);
            break;

        case 'notifications/initialized':
            http_response_code(202);
            exit;

        default:
            $response = [
                'jsonrpc' => '2.0',
                'error'   => ['code' => -32601, 'message' => "Method not found: {$method}"],
                'id'      => $id,
            ];
            break;
    }
} catch (\Throwable $e) {
    $response = [
        'jsonrpc' => '2.0',
        'error'   => ['code' => -32603, 'message' => 'Internal error: ' . $e->getMessage()],
        'id'      => $id ?? null,
    ];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;

// ═══════════════════════════════════════════════════════════
//  JSON-RPC 方法处理器（远程版）
// ═══════════════════════════════════════════════════════════

function handleRemoteInitialize(array $params, $id): array
{
    $role = resolveRemotePermission();

    return [
        'jsonrpc' => '2.0',
        'id'      => $id,
        'result'  => [
            'protocolVersion' => '2025-03-26',
            'capabilities'    => [
                'tools' => new stdClass(),
            ],
            'serverInfo' => getMcpServerInfo(),
            'meta'       => [
                'role'        => $role,
                'auth_method' => 'service_key',
            ],
        ],
    ];
}

function handleRemoteToolsList($id): array
{
    $role = resolveRemotePermission();
    if ($role === 'guest') {
        return [
            'jsonrpc' => '2.0',
            'error'   => ['code' => -32001, 'message' => 'Authentication required. Provide a valid Service Key in Authorization header.'],
            'id'      => $id,
        ];
    }

    return [
        'jsonrpc' => '2.0',
        'id'      => $id,
        'result'  => ['tools' => getToolsForRole($role)],
    ];
}

function handleRemoteToolsCall(array $params, $id): array
{
    global $TOOL_REGISTRY;

    $role = resolveRemotePermission();
    if ($role === 'guest') {
        return [
            'jsonrpc' => '2.0',
            'error'   => ['code' => -32001, 'message' => 'Authentication required. Provide a valid Service Key in Authorization header.'],
            'id'      => $id,
        ];
    }

    $toolName = $params['name'] ?? '';
    $args     = $params['arguments'] ?? [];

    if (empty($toolName) || !isset($TOOL_REGISTRY[$toolName])) {
        return [
            'jsonrpc' => '2.0',
            'error'   => ['code' => -32602, 'message' => "Tool not found: {$toolName}"],
            'id'      => $id,
        ];
    }

    $def = $TOOL_REGISTRY[$toolName];

    // admin_only 工具仅 admin 角色可调用
    if (($def['permission'] ?? '') === 'admin_only' && $role !== 'admin') {
        return [
            'jsonrpc' => '2.0',
            'error'   => ['code' => -32001, 'message' => '权限不足：需要管理员角色'],
            'id'      => $id,
        ];
    }

    // 委托给共享工具分发器
    try {
        $result = dispatchToolCall($toolName, $args);
        return [
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => [
                'content' => [
                    ['type' => 'text', 'text' => $result],
                ],
            ],
        ];
    } catch (\Throwable $e) {
        $code = ($e->getMessage() && strpos($e->getMessage(), 'Tool not found') !== false) ? -32602 : -32000;
        return [
            'jsonrpc' => '2.0',
            'error'   => ['code' => $code, 'message' => $e->getMessage()],
            'id'      => $id,
        ];
    }
}
