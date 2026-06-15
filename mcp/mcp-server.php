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

// ── 工具注册表 ──────────────────────────────────────────

require_once __DIR__ . '/tools/dashboard.php';
require_once __DIR__ . '/tools/instances.php';
require_once __DIR__ . '/tools/website.php';

// 全局工具列表：name => [定义]
$TOOL_REGISTRY = [];

function registerTool(string $name, array $def): void {
    global $TOOL_REGISTRY;
    $TOOL_REGISTRY[$name] = $def;
}

// 注册所有工具
registerTool('get_dashboard', [
    'name'        => 'get_dashboard',
    'description' => '获取 MCSManager 面板概览数据，包括面板版本、系统信息、CPU/内存使用率、所有节点状态',
    'inputSchema' => ['type' => 'object', 'properties' => (object)[], 'required' => []],
    'permission'  => 'read_only',
    'handler'     => 'handle_get_dashboard',
]);

registerTool('get_nodes_status', [
    'name'        => 'get_nodes_status',
    'description' => '获取所有节点的详细状态，包括每个节点的 CPU 使用率、内存使用率、运行/总实例数、系统信息',
    'inputSchema' => ['type' => 'object', 'properties' => (object)[], 'required' => []],
    'permission'  => 'read_only',
    'handler'     => 'handle_get_nodes_status',
]);

registerTool('list_instances', [
    'name'        => 'list_instances',
    'description' => '获取指定节点下所有游戏服务器实例列表，包含实例名称、UUID、运行状态、当前玩家数等',
    'inputSchema' => [
        'type'       => 'object',
        'properties' => [
            'daemonId'   => ['type' => 'string', 'description' => '节点 UUID（可选，不传则返回所有节点的实例）'],
            'page'       => ['type' => 'integer', 'description' => '页码（默认 1）'],
            'page_size'  => ['type' => 'integer', 'description' => '每页数量（默认 50）'],
        ],
    ],
    'permission'  => 'read_only',
    'handler'     => 'handle_list_instances',
]);

registerTool('get_instance_detail', [
    'name'        => 'get_instance_detail',
    'description' => '获取单个实例的详细信息，包括配置、运行状态、进程资源占用（CPU/内存）、启动次数等',
    'inputSchema' => [
        'type'       => 'object',
        'properties' => [
            'uuid'     => ['type' => 'string', 'description' => '实例 UUID'],
            'daemonId' => ['type' => 'string', 'description' => '实例所在节点 UUID'],
        ],
        'required'   => ['uuid', 'daemonId'],
    ],
    'permission'  => 'read_only',
    'handler'     => 'handle_get_instance_detail',
]);

registerTool('get_instance_log', [
    'name'        => 'get_instance_log',
    'description' => '获取实例最近的控制台输出日志，用于排查问题或查看服务器启动情况',
    'inputSchema' => [
        'type'       => 'object',
        'properties' => [
            'uuid'     => ['type' => 'string', 'description' => '实例 UUID'],
            'daemonId' => ['type' => 'string', 'description' => '实例所在节点 UUID'],
            'size'     => ['type' => 'integer', 'description' => '获取的日志大小（KB），默认 100，范围 1~2048'],
        ],
        'required'   => ['uuid', 'daemonId'],
    ],
    'permission'  => 'read_only',
    'handler'     => 'handle_get_instance_log',
]);

registerTool('start_instance', [
    'name'        => 'start_instance',
    'description' => '启动指定的游戏服务器实例',
    'inputSchema' => [
        'type'       => 'object',
        'properties' => [
            'uuid'     => ['type' => 'string', 'description' => '实例 UUID'],
            'daemonId' => ['type' => 'string', 'description' => '实例所在节点 UUID'],
        ],
        'required'   => ['uuid', 'daemonId'],
    ],
    'permission'  => 'admin_only',
    'handler'     => 'handle_start_instance',
]);

registerTool('stop_instance', [
    'name'        => 'stop_instance',
    'description' => '停止指定的游戏服务器实例',
    'inputSchema' => [
        'type'       => 'object',
        'properties' => [
            'uuid'     => ['type' => 'string', 'description' => '实例 UUID'],
            'daemonId' => ['type' => 'string', 'description' => '实例所在节点 UUID'],
        ],
        'required'   => ['uuid', 'daemonId'],
    ],
    'permission'  => 'admin_only',
    'handler'     => 'handle_stop_instance',
]);

registerTool('restart_instance', [
    'name'        => 'restart_instance',
    'description' => '重启指定的游戏服务器实例',
    'inputSchema' => [
        'type'       => 'object',
        'properties' => [
            'uuid'     => ['type' => 'string', 'description' => '实例 UUID'],
            'daemonId' => ['type' => 'string', 'description' => '实例所在节点 UUID'],
        ],
        'required'   => ['uuid', 'daemonId'],
    ],
    'permission'  => 'admin_only',
    'handler'     => 'handle_restart_instance',
]);

registerTool('send_command', [
    'name'        => 'send_command',
    'description' => '向指定游戏服务器发送控制台命令（如 /say 公告、/op 玩家名 等）',
    'inputSchema' => [
        'type'       => 'object',
        'properties' => [
            'uuid'     => ['type' => 'string', 'description' => '实例 UUID'],
            'daemonId' => ['type' => 'string', 'description' => '实例所在节点 UUID'],
            'command'  => ['type' => 'string', 'description' => '要发送的命令内容'],
        ],
        'required'   => ['uuid', 'daemonId', 'command'],
    ],
    'permission'  => 'admin_only',
    'handler'     => 'handle_send_command',
]);

registerTool('list_announcements', [
    'name'        => 'list_announcements',
    'description' => '获取所有公告列表，返回每条公告的 ID、标题、类型、作者、日期',
    'inputSchema' => ['type' => 'object', 'properties' => (object)[], 'required' => []],
    'permission'  => 'read_only',
    'handler'     => 'handle_list_announcements',
]);

registerTool('get_announcement', [
    'name'        => 'get_announcement',
    'description' => '获取单个公告的完整内容（含 Markdown 正文）',
    'inputSchema' => [
        'type'       => 'object',
        'properties' => ['id' => ['type' => 'string', 'description' => '公告 ID']],
        'required'   => ['id'],
    ],
    'permission'  => 'read_only',
    'handler'     => 'handle_get_announcement',
]);

registerTool('write_announcement', [
    'name'        => 'write_announcement',
    'description' => '创建或更新一条公告。提供 id 为更新，不提供 id 为新建。支持 Markdown 格式内容',
    'inputSchema' => [
        'type'       => 'object',
        'properties' => [
            'id'         => ['type' => 'string', 'description' => '公告 ID（更新时必填，新建时可选）'],
            'title'      => ['type' => 'string', 'description' => '公告标题'],
            'type'       => ['type' => 'string', 'description' => '公告类型: update(版本更新) / event(活动) / notice(通知)'],
            'author'     => ['type' => 'string', 'description' => '作者名称'],
            'created_at' => ['type' => 'string', 'description' => '发布日期 (YYYY-MM-DD)'],
            'summary'    => ['type' => 'string', 'description' => '公告摘要'],
            'content'    => ['type' => 'string', 'description' => '公告正文（Markdown 格式）'],
        ],
        'required'   => ['title', 'type', 'author', 'content'],
    ],
    'permission'  => 'admin_only',
    'handler'     => 'handle_write_announcement',
]);

registerTool('send_notification', [
    'name'        => 'send_notification',
    'description' => '向全站发送系统通知，所有登录用户都会收到',
    'inputSchema' => [
        'type'       => 'object',
        'properties' => [
            'title'   => ['type' => 'string', 'description' => '通知标题'],
            'type'    => ['type' => 'string', 'description' => '通知类型: system(系统) / event(活动) / update(更新)'],
            'content' => ['type' => 'string', 'description' => '通知内容'],
        ],
        'required'   => ['title', 'content'],
    ],
    'permission'  => 'admin_only',
    'handler'     => 'handle_send_notification',
]);

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
    // 1. 从请求头获取（兼容多种服务器环境）
    $token = '';

    // Apache / PHP built-in server
    $headers = getallheaders();
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

    // 2. 从 params 获取（initialize 方法会传 auth_token）
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
    $created = strtotime($session['created_at'] ?? '');
    if ($created === false || (time() - $created) > 86400) {
        return 'guest';
    }

    return ($session['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
}

// ── JSON-RPC 分发入口 ───────────────────────────────────

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

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
            $response = handleToolsList($id);
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
    resolvePermission($params); // 仅触发 token 校验，返回结果不直接使用

    return [
        'jsonrpc' => '2.0',
        'id'      => $id,
        'result'  => [
            'protocolVersion' => '2025-03-26',
            'capabilities'    => [
                'tools' => new stdClass(),
            ],
            'serverInfo' => [
                'name'    => 'mcsm-mcp-server',
                'version' => '1.0.0',
            ],
        ],
    ];
}

/**
 * tools/list — 根据当前请求的权限等级返回可用工具列表
 */
function handleToolsList($id): array
{
    global $TOOL_REGISTRY;

    $permission = resolvePermission();

    $tools = [];
    foreach ($TOOL_REGISTRY as $name => $def) {
        if ($permission === 'admin' || $def['permission'] === 'read_only') {
            $tools[] = [
                'name'        => $def['name'],
                'description' => $def['description'],
                'inputSchema' => $def['inputSchema'],
            ];
        }
    }

    return [
        'jsonrpc' => '2.0',
        'id'      => $id,
        'result'  => ['tools' => $tools],
    ];
}

/**
 * tools/call — 调用指定工具
 */
function handleToolsCall(array $params, $id): array
{
    global $TOOL_REGISTRY;

    $permission = resolvePermission();
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

    // 权限检查
    if ($def['permission'] === 'admin_only' && $permission !== 'admin') {
        return [
            'jsonrpc' => '2.0',
            'error'   => ['code' => -32001, 'message' => '权限不足：需要管理员权限'],
            'id'      => $id,
        ];
    }

    // 校验必填参数
    $schema = $def['inputSchema'];
    $required = $schema['required'] ?? [];
    foreach ($required as $field) {
        if (!isset($args[$field]) || (is_string($args[$field]) && trim($args[$field]) === '')) {
            return [
                'jsonrpc' => '2.0',
                'error'   => ['code' => -32602, 'message' => "缺少必填参数: {$field}"],
                'id'      => $id,
            ];
        }
    }

    // 调用工具 handler
    $handler = $def['handler'];
    if (!function_exists($handler)) {
        return [
            'jsonrpc' => '2.0',
            'error'   => ['code' => -32603, 'message' => "Handler not found: {$handler}"],
            'id'      => $id,
        ];
    }

    try {
        $result = $handler($args);
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
        return [
            'jsonrpc' => '2.0',
            'error'   => ['code' => -32000, 'message' => $e->getMessage()],
            'id'      => $id,
        ];
    }
}

// ── MCSManager API 通用调用工具 ──────────────────────────

/**
 * 调用 MCSManager 面板 API
 *
 * @param string $endpoint  API 路径（如 /api/overview）
 * @param array  $query     GET 参数
 * @param string $method    HTTP 方法
 * @param array  $body      POST/PUT 请求体
 * @return array 解码后的响应数据
 * @throws Exception
 */
function mcsmApiCall(string $endpoint, array $query = [], string $method = 'GET', array $body = []): array
{
    $query['apikey'] = MCSM_API_KEY;

    // MCSM_API_URL 已包含 /api 路径（如 https://mcpanel.eqmemory.cn/mcs/api）
    // 端点如 /api/overview 需去掉前缀 /api 避免路径重复
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
    }

    // 生产环境应启用 SSL 验证
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

    return $data['data'] ?? [];
}
