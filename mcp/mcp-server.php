<?php
/**
 * MCP Server — JSON-RPC 2.0 协议实现
 *
 * 基于 MCP (Model Context Protocol) 规范，将 MCSManager API 封装为标准工具，
 * 供 AI 客服（kefu.html）和 AI 管理控制台通过 JSON-RPC 调用。
 *
 * 传输方式：Streamable HTTP（请求-响应模式）
 * 协议版本：2025-03-26
 *
 * 引入方式：直接部署在 web 目录下，通过 POST 访问
 * 示例：curl -X POST https://domain/mcp/mcp-server.php -H "Content-Type: application/json" -d '{...}'
 */

// ── 环境兼容 & 错误控制 ───────────────────────────────────

// 抑制 PHP 错误输出，确保 MCP 始终返回 JSON（即使出现致命错误也尽量不吐 HTML）
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// PHP 7.x 兼容：str_starts_with 是 PHP 8.0+ 函数
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }
}

// 注册 shutdown handler：发生致命错误时返回 JSON-RPC 错误而非 HTML
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

// config.php 中有敏感信息，必须在 ACCESS_ALLOWED 保护下
// 这里直接从正确的相对路径引入
$mcpRoot = __DIR__;
$projectRoot = dirname($mcpRoot);

define('ACCESS_ALLOWED', true);
require_once $projectRoot . '/config/config.php';
require_once $projectRoot . '/api/helper.php';
require_once $projectRoot . '/api/secure_data.php';
require_once $projectRoot . '/includes/auth_helper.php';

// ── 加载共享工具基底 ─────────────────────────────────────
// toolbase.php 自动加载 mcp/tools/*.php（工具模块顶层自注册），
// 提供 registerTool / getToolsForRole / dispatchToolCall / mcsmApiCall 等共享函数。
require_once __DIR__ . '/toolbase.php';

// ── 会话状态（单请求生命周期） ──────────────────────────

/**
 * 从当前请求中解析权限等级
 *
 * PHP 无状态，每次请求独立解析 token。
 * 优先从 Authorization 请求头获取，其次从 JSON-RPC params 获取。
 *
 * @param array $jsonParams  JSON-RPC 请求的 params（某些方法如 initialize 会传 auth_token）
 * @return string 'admin' | 'user' | 'guest'
 */
function resolvePermission(array $jsonParams = []): string
{
    // 1) 优先复用 AuthHelper 的规范会话校验
    //    —— 它能正确处理 expires_at（整数时间戳）与 created_at（字符串）两种格式，
    //       并兼容 Authorization 请求头注入，避免重复实现导致的不一致。
    if (class_exists('AuthHelper')) {
        $session = AuthHelper::getSession();
        if ($session !== null) {
            return ($session['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
        }
    }

    // 2) 兜底：从 Authorization 头或 JSON-RPC params.auth_token 解析
    $token = '';

    $headers = function_exists('getallheaders') ? getallheaders() : [];
    if (isset($headers['Authorization'])) {
        $token = str_replace('Bearer ', '', $headers['Authorization']);
    } elseif (isset($headers['authorization'])) {
        $token = str_replace('Bearer ', '', $headers['authorization']);
    }

    // CGI/FastCGI 模式（Nginx 等）
    if (empty($token) && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
    }
    if (empty($token) && !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $token = str_replace('Bearer ', '', $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    }

    // 从 params 获取（前端在 JSON-RPC 体中额外携带 auth_token，防止 Authorization 头被服务器剥离）
    if (empty($token) && !empty($jsonParams['auth_token'])) {
        $token = $jsonParams['auth_token'];
    }

    if (empty($token)) {
        return 'guest';
    }

    $sessions = secureReadData(SESSIONS_FILE);
    if (!isset($sessions[$token])) {
        return 'guest';
    }

    $session = $sessions[$token];

    // 兼容 created_at 既可能是字符串(Y-m-d H:i:s)也可能是整数时间戳的历史数据
    $created = parseSessionTime($session['created_at'] ?? null);
    $expires = parseSessionTime($session['expires_at'] ?? null);

    if ($expires !== null && $expires < time()) {
        return 'guest';
    }
    if ($created === null || (time() - $created) > 86400) {
        return 'guest';
    }

    return ($session['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
}

/**
 * 将会话时间字段解析为 Unix 时间戳
 * 兼容：字符串 'Y-m-d H:i:s' 与整数时间戳两种格式
 */
function parseSessionTime($value): ?int
{
    if ($value === null) {
        return null;
    }
    if (is_numeric($value)) {
        return (int) $value;
    }
    $t = strtotime((string) $value);
    return ($t === false) ? null : $t;
}

// ── 人格（persona）解析 ───────────────────────────────
//
// 客服渠道由专用入口 api/ai/mcp-customer.php 通过 MCP_PERSONA_FORCED 强制为 'customer'，
// 即便管理员调用该入口也只允许只读工具；管理渠道默认 'admin'，写操作需管理员角色。
// 攻击者若将管理入口的 persona 改成 customer，只会得到只读结果（安全）；改成 admin 则需管理员 token。
$MCP_PERSONA = defined('MCP_PERSONA_FORCED')
    ? MCP_PERSONA_FORCED
    : ($_GET['persona'] ?? 'admin');
if (!in_array($MCP_PERSONA, ['customer', 'admin'], true)) {
    $MCP_PERSONA = 'admin';
}

// ── JSON-RPC 分发入口 ───────────────────────────────────

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
set_cors_headers(); // 安全 CORS：仅允许受信任来源，禁止通配符 *

// 只接受 POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['jsonrpc' => '2.0', 'error' => ['code' => -32000, 'message' => 'Method not allowed'], 'id' => null]);
    exit;
}

// 读取请求体
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
            $response = handleInitialize($params, $id);
            break;

        case 'tools/list':
            $response = handleToolsList($params, $id);
            break;

        case 'tools/call':
            $response = handleToolsCall($params, $id);
            break;

        case 'notifications/initialized':
            // notifications 不需要响应
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
//  JSON-RPC 方法处理器
// ═══════════════════════════════════════════════════════════

/**
 * initialize — 协议初始化 + 客户端认证
 */
function handleInitialize(array $params, $id): array
{
    resolvePermission($params); // token 校验

    return [
        'jsonrpc' => '2.0',
        'id'      => $id,
        'result'  => [
            'protocolVersion' => '2025-03-26',
            'capabilities'    => [
                'tools' => new stdClass(),
            ],
            'serverInfo' => getMcpServerInfo(),
        ],
    ];
}

/**
 * tools/list — 根据当前请求的权限等级返回可用工具列表
 */
function handleToolsList($params, $id): array
{
    global $MCP_PERSONA;

    // 客服渠道：服务端强制只读白名单
    if ($MCP_PERSONA === 'customer') {
        $allowed = getAiAllowedTools('customer') ?? [];
        return [
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => ['tools' => getToolsForCustomer($allowed)],
        ];
    }

    $permission = resolvePermission($params);
    $role = ($permission === 'admin') ? 'admin' : 'user';

    return [
        'jsonrpc' => '2.0',
        'id'      => $id,
        'result'  => ['tools' => getToolsForRole($role)],
    ];
}

/**
 * tools/call — 调用指定工具
 */
function handleToolsCall(array $params, $id): array
{
    global $TOOL_REGISTRY, $MCP_PERSONA;

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

    // 客服渠道：服务端强制白名单
    if ($MCP_PERSONA === 'customer') {
        $allowed = getAiAllowedTools('customer') ?? [];
        if (!in_array($toolName, $allowed, true)) {
            return [
                'jsonrpc' => '2.0',
                'error'   => [
                    'code'    => -32001,
                    'message' => '客服渠道仅允许只读工具，拒绝执行管理操作：' . $toolName,
                ],
                'id'      => $id,
            ];
        }
    } else {
        // 管理渠道：写操作需管理员角色
        $permission = resolvePermission($params);
        if (($def['permission'] ?? '') === 'admin_only' && $permission !== 'admin') {
            return [
                'jsonrpc' => '2.0',
                'error'   => ['code' => -32001, 'message' => '权限不足：需要管理员权限'],
                'id'      => $id,
            ];
        }
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

