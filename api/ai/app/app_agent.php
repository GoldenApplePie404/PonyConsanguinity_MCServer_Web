<?php
/**
 * app_agent.php — Godot 控制 APP「管理 AI 助手」大脑（服务端闭环版）
 * ====================================================================
 * 这是网站 admin AI 助手的一比一复刻（仅 AI 助手 / 管理功能）。
 *
 * 遵循网站"分脑"架构：
 *   - 本端点 = LLM 大脑 + 人格 prompt + RAG + 工具清单注入（不执行工具）
 *   - 返回文本中可能包含 TOOL_CALL:{"name":...,"arguments":{...}} 标记
 *   - 真正的工具执行 + 多轮循环在 Godot 客户端（ai_agent_client.gd）完成：
 *     客户端解析标记 → 调 mcp/remote.php 的 tools/call → 结果回灌本端点
 *
 * 鉴权：复用 config.php 的 MCP_SERVICE_KEYS（SHA256 → 角色）。仅 admin 可访问。
 *       强制 admin 人格，忽略任何明文 persona 参数（APP 端只复刻管理助手）。
 *
 * 请求（POST application/json）：
 * {
 *   "message": "停止生存服",
 *   "history": [ {"role":"user","content":"..."}, {"role":"assistant","content":"..."} ],
 *   "stream": false
 * }
 * 头：Authorization: Bearer <service_key>
 *
 * 响应（200）：
 * {
 *   "success": true,
 *   "message": "好的，我来停止...\nTOOL_CALL:{...}",
 *   "usage": {...}, "provider": "..."
 * }
 */

error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// PHP 7.x 兼容
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }
}

// ── 路径 ──
$appRoot     = __DIR__;                    // api/ai/app
$aiDir       = dirname($appRoot);           // api/ai
$projectRoot = dirname(dirname($aiDir));     // 项目根（api/ai/app → api/ai → api → 项目根）

// ── 引导 ──
define('ACCESS_ALLOWED', true);
require_once $projectRoot . '/config/config.php';
// 共享工具基底（提供 getToolsForRole，并自注册 mcp/tools/*.php）
require_once $projectRoot . '/mcp/toolbase.php';
// 共用 LLM + RAG 内核（与站内 api.php 完全一致）
require_once $aiDir . '/ai_core.php';

// ── 鉴权：Service Key → 角色（复用同套 SHA256 映射） ──
function appResolveRole(): string {
    if (!defined('MCP_SERVICE_KEYS')) {
        return 'guest';
    }
    $keyMap = MCP_SERVICE_KEYS;
    if (!is_array($keyMap) || empty($keyMap)) {
        return 'guest';
    }

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

    $hash = hash('sha256', $token);
    return $keyMap[$hash] ?? 'guest';
}

// ── JSON 响应辅助 ──
function app_json(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ── 前置：仅接受 POST JSON ──
header('Content-Type: application/json; charset=utf-8');
set_cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    app_json(['success' => false, 'error' => 'Method not allowed'], 405);
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    app_json(['success' => false, 'error' => 'Invalid JSON body'], 400);
}

// ── 鉴权检查 ──
$role = appResolveRole();
if ($role !== 'admin') {
    app_json([
        'success' => false,
        'error'   => '未授权',
        'message' => '需要 admin 角色的 Service Key（APP 仅复刻管理 AI 助手）',
    ], 401);
}

// ── 取参数 ──
$message = trim(strval($input['message'] ?? ''));
$history = is_array($input['history'] ?? []) ? $input['history'] : [];
if ($message === '' && empty($history)) {
    app_json(['success' => false, 'error' => '消息为空'], 400);
}

// ── 构建大脑：强制 admin 人格（复制包 prompt.md / knowledge_base.md） ──
$kbFile     = $appRoot . '/knowledge_base.md';
$promptFile = $appRoot . '/prompt.md';

$kb = new KnowledgeBase($kbFile);
$pb = new PromptBuilder($promptFile);

// RAG 检索（message 为空时回退到 history 中最后一条 user 内容）
$ragQuery = $message;
if ($ragQuery === '') {
    for ($i = count($history) - 1; $i >= 0; $i--) {
        if (($history[$i]['role'] ?? '') === 'user' && trim(strval($history[$i]['content'] ?? '')) !== '') {
            $ragQuery = trim(strval($history[$i]['content']));
            break;
        }
    }
}
$knowledge = $kb->getFormattedKnowledge($ragQuery);
$systemPrompt = $pb->buildPrompt($knowledge, $ragQuery);

// 注入 MCP 工具清单（admin 全量，直接复用共享基底，无需客户端传递）
$tools = getToolsForRole('admin');
if (!empty($tools)) {
    $toolList = '';
    foreach ($tools as $t) {
        $toolList .= '- ' . $t['name'] . ': ' . $t['description'] . "\n";
    }
    if ($toolList !== '') {
        $systemPrompt .= "\n\n【MCP 工具】\n你可以使用以下 MCP 工具来回答用户：\n" . $toolList . "\n";
        $systemPrompt .= "工具调用格式：在回复中单独一行写 TOOL_CALL:{\"name\":\"工具名\",\"arguments\":{...}}\n";
        $systemPrompt .= "工具执行后系统会把结果告诉你，你再根据结果回答用户。\n";
    }
}

// ── 组装上下文（客户端维护 history，本端点无状态） ──
$context = [
    ['role' => 'system', 'content' => $systemPrompt],
];
foreach ($history as $h) {
    if (isset($h['role'], $h['content']) && in_array($h['role'], ['user', 'assistant', 'system'], true)) {
        $context[] = ['role' => $h['role'], 'content' => strval($h['content'])];
    }
}
// 若 message 与 history 末条 user 内容相同（客户端已写入 history），则不重复追加，避免上下文重复
$lastContent = '';
if (!empty($history)) {
    $last = end($history);
    $lastContent = strval($last['content'] ?? '');
}
if ($message !== '' && $message !== $lastContent) {
    $context[] = ['role' => 'user', 'content' => $message];
}

// ── 调用 LLM（provider 回退链复用 ai_core.php） ──
$model       = 'deepseek-chat';
$temperature = 0.7;
$maxTokens   = 4096;

$resp = aiCallLlm($context, $model, '', $temperature, $maxTokens);
if (!$resp['ok']) {
    app_json([
        'success' => false,
        'error'   => 'AI 请求失败',
        'message' => $resp['error'],
    ], 502);
}

app_json([
    'success'   => true,
    'message'   => $resp['text'],
    'usage'     => $resp['usage'] ?? null,
    'provider'  => $resp['provider'],
]);
