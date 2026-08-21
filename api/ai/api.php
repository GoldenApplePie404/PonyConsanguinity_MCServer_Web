<?php
/**
 * AI 客服 API - 基于 DeepSeek 的 RAG 系统
 * 
 * 功能：
 * 1. 知识库检索
 * 2. 智能问答
 * 3. 上下文管理
 */

// 错误处理（开发环境）
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/ai_db.php'; // 对话历史持久化（阶段0.1）

// 放宽脚本执行时限：LLM 调用含多级 provider 回退 + 同步摘要/画像压缩，
// 最坏可能累计逼近默认 30s 上限，导致 curl_exec 处触发致命超时。
// 提升为 300s 给回退链留足预算（CLI 内置 server 下 set_time_limit 有效；
// 若被 disable_functions 禁用则用 @ 抑制告警，并由下方 aiCallLlm 时间预算兜底）。
@set_time_limit(300);
@ini_set('max_execution_time', '300');

// 设置 CORS 头（安全版：仅允许受信任来源）
set_cors_headers();
header('Content-Type: application/json; charset=utf-8');

// 判断模型是否属于 EYPA/AI 平台（统一走 ECHO_API_URL）
function isEypaAiModel(string $model): bool {
    $eypaModels = defined('EYPA_AI_MODELS') ? EYPA_AI_MODELS : [
        'Echo-1.5-Flash', 'Echo-1.5-Pro', 'Echo-Image',
        'DeepSeek-V4-Flash', 'DeepSeek-V4-Pro',
        'GLM-5.2', 'MiniMax-M2.7', 'MiniMax-M3',
    ];
    if (in_array($model, $eypaModels, true)) return true;
    if (stripos($model, 'Echo') === 0) return true;
    if (stripos($model, 'DeepSeek-V4') === 0) return true;
    if (stripos($model, 'GLM-5.') === 0) return true;
    if (stripos($model, 'MiniMax') === 0) return true;
    return false;
}

// Echo / DeepSeek 端点解析：依据模型名自动选择 provider（兼容 OpenAI 协议）
function aiChatEndpoint(string $model): string {
    $base = isEypaAiModel($model)
        ? (defined('ECHO_API_URL') ? rtrim(ECHO_API_URL, '/') : 'https://eapi.eqmemory.cn/v1')
        : (defined('DEEPSEEK_API_URL') ? rtrim(DEEPSEEK_API_URL, '/') : 'https://api.deepseek.com');
    return $base . '/chat/completions';
}

// 判定上游是否属于"无法响应"（断链/宕机/限流），决定是否回退到下一个 provider
//   curl 级错误、HTTP 0（无响应）、5xx（服务断链）、429（限流）视为失败 → 应回退
//   4xx（如 401/400/403，属配置或请求错误）不回退，避免无意义切换浪费调用
function isUpstreamFailure(string $curlError, int $httpCode): bool {
    if ($curlError !== '') return true;
    if ($httpCode === 0)  return true;
    if ($httpCode >= 500) return true;
    if ($httpCode === 429) return true;
    return false;
}

// 解析需要尝试的 provider/model 列表（多级回退）
// 回退链：
//   - EYPA/AI 平台模型统一走 ECHO_API_URL；Echo-1.5-Flash 断链时内部升级到 Echo-1.5-Pro
//   - EYPA/AI 全系列断链时，自动回退到 DeepSeek 官方 deepseek-chat
//   - DeepSeek 官方模型（deepseek-chat / deepseek-reasoner）走 DEEPSEEK_API_URL
function resolveProviderAttempts(string $model): array {
    $echoUrl = defined('ECHO_API_URL') ? rtrim(ECHO_API_URL, '/') : 'https://eapi.eqmemory.cn/v1';
    $dsUrl   = defined('DEEPSEEK_API_URL') ? rtrim(DEEPSEEK_API_URL, '/') : 'https://api.deepseek.com';
    $echoKey = defined('ECHO_API_KEY') ? ECHO_API_KEY : '';
    $dsKey   = defined('DEEPSEEK_API_KEY') ? DEEPSEEK_API_KEY : '';
    $model   = trim($model);

    $attempts = [];

    if (isEypaAiModel($model)) {
        if ($echoKey !== '') {
            if (stripos($model, 'Echo-1.5-Flash') !== false) {
                // 主用 Flash，断链时升级 Pro（echo 系列内部自愈）
                $attempts[] = ['endpoint' => $echoUrl . '/chat/completions', 'key' => $echoKey, 'model' => 'Echo-1.5-Flash'];
                $attempts[] = ['endpoint' => $echoUrl . '/chat/completions', 'key' => $echoKey, 'model' => 'Echo-1.5-Pro'];
            } else {
                // 其他 EYPA/AI 模型按原名尝试（DeepSeek-V4 / GLM-5.2 / MiniMax 等）
                $attempts[] = ['endpoint' => $echoUrl . '/chat/completions', 'key' => $echoKey, 'model' => $model];
            }
        }
        // EYPA/AI 全系列断链，回退 DeepSeek 官方
        if ($dsKey !== '') {
            $attempts[] = ['endpoint' => $dsUrl . '/chat/completions', 'key' => $dsKey, 'model' => 'deepseek-chat'];
        }
    } else {
        // 非 EYPA/AI 模型：DeepSeek 官方优先
        if (stripos($model, 'deepseek') !== false && $dsKey !== '') {
            $attempts[] = ['endpoint' => $dsUrl . '/chat/completions', 'key' => $dsKey, 'model' => $model];
        } elseif ($echoKey !== '') {
            // 兜底：自定义模型尝试走 EYPA/AI（兼容 OpenAI 协议）
            $attempts[] = ['endpoint' => $echoUrl . '/chat/completions', 'key' => $echoKey, 'model' => $model];
        }
    }

    return $attempts;
}

// 从上游响应中提取助手文本（兼容推理模型的 reasoning_content）
function extractAssistantMessage(array $result): string {
    $msg = $result['choices'][0]['message'] ?? [];
    $text = $msg['content'] ?? '';
    if ($text === '' || $text === null) {
        $text = $msg['reasoning_content'] ?? $msg['reasoning'] ?? '';
    }
    return (string)$text;
}

// 通用 LLM 调用（带 provider 回退），供主对话 / 摘要压缩 / 画像抽取复用
function aiCallLlm(array $messages, string $model, string $apiKey, float $temperature = 1.0, int $maxTokens = 2048): array {
    $start = microtime(true);
    // 时间预算保护：读取 PHP 实际执行时限，给收尾/JSON 解析/DB 写入预留安全余量。
    // 这样即便 set_time_limit 被禁用，也不会因多级 provider 回退叠加而触发致命超时。
    $scriptBudget = (int)ini_get('max_execution_time');
    if ($scriptBudget <= 0) $scriptBudget = 300; // 0 = 不限制，按 300s 兜底
    $safetyMargin = 8;
    $maxAllowed   = $scriptBudget - $safetyMargin;
    // 单次 curl 超时不超过预算余量（且不低于 8s），确保第一轮回退也不会逼近 PHP 上限
    $curlTimeout  = ($maxAllowed > 8) ? min(25, $maxAllowed) : 8;

    $ad = ['model' => $model, 'messages' => $messages, 'temperature' => $temperature, 'max_tokens' => $maxTokens, 'stream' => false];
    if ($model === 'deepseek-reasoner') unset($ad['temperature']);
    $attempts = resolveProviderAttempts($model);
    $lastErr = '所有 provider 均不可用';
    foreach ($attempts as $att) {
        // 预算耗尽则停止尝试后续 provider，直接返回已有错误，避免 Fatal error
        if (microtime(true) - $start > $maxAllowed) {
            if ($lastErr === '所有 provider 均不可用') {
                $lastErr = '上游响应超时（已达脚本时间预算，已停止尝试后续 provider）';
            }
            break;
        }
        $a = $ad;
        $a['model'] = $att['model'];
        $ch = curl_init($att['endpoint']);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($a),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . $att['key']],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $curlTimeout,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        if ($curlError !== '') { $lastErr = 'API 请求失败：' . $curlError; continue; }
        if ($httpCode !== 200) {
            $r = json_decode($response, true);
            $lastErr = '上游返回 HTTP ' . $httpCode . '：' . ($r['error']['message'] ?? '未知错误');
            if (isUpstreamFailure($curlError, $httpCode)) continue;
            break;
        }
        $result = json_decode($response, true);
        $text = extractAssistantMessage($result);
        if ($text === '') { $lastErr = '上游返回空内容'; continue; }
        return ['ok' => true, 'text' => $text, 'error' => '', 'provider' => $att['endpoint'], 'usage' => $result['usage'] ?? null];
    }
    return ['ok' => false, 'text' => '', 'error' => $lastErr, 'provider' => ''];
}

// 持久化一轮对话，并在累积足够未压缩原文后触发：摘要压缩 + 用户画像抽取（同步，P1/P2）
function aiPersistAndMaybeCompact(string $cid, string $persona, string $user, string $assistant, string $model, string $apiKey, string $clientId): void {
    try {
    $r = ai_append_turn($cid, $persona, $user, $assistant, $clientId);
    if (!$r['need_compact']) return;

    $conv = ai_get_conv($cid);
    if (!$conv) return;
    $msgs = $conv['messages'] ?? [];
    $oldSummary = $conv['summary'] ?? '';
    $doc = ($oldSummary !== '' ? "[历史摘要]\n{$oldSummary}\n\n" : '') . "[新对话]\n"
        . implode("\n", array_map(fn($m) => "[{$m['role']}] {$m['content']}", $msgs));

    // 1) 摘要压缩：把全部原文压成一段摘要，保留关键事实/偏好/订单等信息
    $sumResp = aiCallLlm([
        ['role' => 'system', 'content' => '你是对话摘要助手。请把用户与助手的对话压缩成一段简洁中文摘要，保留所有关键事实、用户偏好、未解决事项、订单/充值/账号相关信息。只输出摘要正文，不要解释、不要标题。'],
        ['role' => 'user', 'content' => $doc],
    ], $model, $apiKey, 0.2, 600);
    if ($sumResp['ok'] && trim($sumResp['text']) !== '') {
        ai_save_summary($cid, trim($sumResp['text']));
    }
    ai_prune_raw($cid);

    // 2) 用户画像抽取（严格 JSON）
    $profResp = aiCallLlm([
        ['role' => 'system', 'content' => '从对话中抽取稳定的用户画像事实，只输出严格 JSON（不要任何额外文字）：{"player_name":string,"topics":[string],"prefs":[string]}。没有则对应字段为空字符串或空数组。'],
        ['role' => 'user', 'content' => $doc],
    ], $model, $apiKey, 0.2, 400);
    if ($profResp['ok']) {
        $raw = preg_replace('/^```(?:json)?/i', '', trim($profResp['text']));
        $raw = preg_replace('/```$/', '', $raw);
        $j = json_decode($raw, true);
        if (is_array($j)) ai_update_profile($clientId, $j);
    }
    } catch (\Throwable $e) {
        // 压缩/画像为体验增强，失败绝不影响主响应与对话持久化
        error_log('aiPersistAndMaybeCompact failed: ' . $e->getMessage());
    }
}

// 处理 GET 请求（获取配置 / 获取预组装 system prompt）
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'config';

    if ($action === 'system_prompt') {
        // 暴露给前端：本地直连模式下，让前端拉取 PHP 组装好的完整 system prompt
        // 这样能保证本地与生产对话风格、工具白名单、知识库检索结果完全一致
        $persona = $_GET['persona'] ?? 'customer';
        $personaCfg = function_exists('getAiPersonaConfig') ? getAiPersonaConfig($persona) : [];
        $kbFile = $personaCfg['kb_file'] ?? __DIR__ . '/knowledge_base.md';
        $promptFile = $personaCfg['prompt_file'] ?? __DIR__ . '/prompt.md';

        $kb = new KnowledgeBase($kbFile);
        $promptBuilder = new PromptBuilder($promptFile);

        // 知识库 query 可由前端传入；不传则返回空（兼容旧行为）
        $query = $_GET['q'] ?? '';
        $knowledge = $query !== '' ? $kb->getFormattedKnowledge($query) : '';
        $systemPrompt = $promptBuilder->buildPrompt($knowledge, '');

        echo json_encode([
            'success' => true,
            'system_prompt' => $systemPrompt,
            'persona' => $persona,
            'model' => $personaCfg['model'] ?? (defined('DEEPSEEK_DEFAULT_MODEL') ? DEEPSEEK_DEFAULT_MODEL : 'Echo-1.5-Pro')
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'config' => [
            'model' => defined('DEEPSEEK_DEFAULT_MODEL') ? DEEPSEEK_DEFAULT_MODEL : 'deepseek-chat',
            'temperature' => defined('DEEPSEEK_DEFAULT_TEMPERATURE') ? DEEPSEEK_DEFAULT_TEMPERATURE : 1.0,
            'max_tokens' => defined('DEEPSEEK_DEFAULT_MAX_TOKENS') ? DEEPSEEK_DEFAULT_MAX_TOKENS : 2048
        ]
    ]);
    exit;
}

// 只允许 POST 请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => '只允许 POST 请求']);
    exit;
}

// 获取请求数据
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => '无效的请求数据']);
    exit;
}

// 验证必要参数（会话生命周期类 / 测试连接类 action 不依赖 message 字段，放行跳过）
$aiActionEarly = $data['action'] ?? '';
$isLifecycleAction = in_array($aiActionEarly, [
    'delete_ai_conversation', 'clear_ai_conversation', 'rename_ai_conversation',
    'list_ai_conversations', 'get_ai_conversation',
    'append_ai_message', 'set_ai_messages', 'replace_last_ai_message',
    'test_connection'
], true);
if (!$isLifecycleAction && (!isset($data['message']) || empty($data['message']))) {
    http_response_code(400);
    echo json_encode(['error' => '缺少必要参数：message']);
    exit;
}

$userMessage = $data['message'] ?? '';
// 优先使用前端传入的 API Key，否则依据模型回退到服务端配置的密钥
$apiKey = $data['api_key'] ?? null;
$conversationId = $data['conversation_id'] ?? null;
// 阶段0.1：未传入则服务端生成稳定会话 ID，用于跨请求恢复多轮上下文
if (empty($conversationId)) {
    $conversationId = uniqid('conv_', true);
}
// 阶段记忆重构（P0/P2）：匿名客户端标识，用于跨对话绑定用户画像与隔离多浏览器
$clientId = trim((string)($data['client_id'] ?? ''));

// ── 阶段记忆重构：会话生命周期 + 列表/读取 + 连接测试（云端为唯一真相源） ──
// 这些 action 不依赖 message 字段，必须在下方 message 校验之后短路返回。
$aiAction = $data['action'] ?? '';
if (in_array($aiAction, [
    'delete_ai_conversation', 'clear_ai_conversation', 'rename_ai_conversation',
    'list_ai_conversations', 'get_ai_conversation', 'append_ai_message', 'set_ai_messages', 'replace_last_ai_message',
    'test_connection'
], true)) {

    if (empty($conversationId) && !in_array($aiAction, ['list_ai_conversations', 'test_connection'], true)) {
        http_response_code(400);
        echo json_encode(['error' => '缺少 conversation_id']);
        exit;
    }

    switch ($aiAction) {
        case 'delete_ai_conversation':
            // 彻底移除会话记录（含摘要与消息），避免 ai_conversations.php 无限膨胀
            $ok = ai_delete_conv($conversationId);
            echo json_encode(['success' => $ok, 'action' => 'delete']);
            exit;

        case 'clear_ai_conversation':
            // 清空记忆但保留会话槽位（前端仍显示该对话，只是内容为空）；摘要一并清掉
            $ok = ai_clear_conv($conversationId);
            echo json_encode(['success' => $ok, 'action' => 'clear']);
            exit;

        case 'rename_ai_conversation':
            $title = trim($data['title'] ?? '');
            if ($title === '') {
                http_response_code(400);
                echo json_encode(['error' => '标题不能为空']);
                exit;
            }
            $ok = ai_rename_conv($conversationId, $title);
            echo json_encode(['success' => $ok, 'action' => 'rename']);
            exit;

        case 'list_ai_conversations':
            // 返回当前客户端的所有会话（云端列表为真相源，前端仅作镜像）
            $list = ai_list_convs($clientId);
            echo json_encode(['success' => true, 'conversations' => $list]);
            exit;

        case 'get_ai_conversation':
            $conv = ai_get_conv($conversationId);
            if (!$conv) {
                echo json_encode(['success' => false, 'error' => '会话不存在']);
                exit;
            }
            ai_tag_client($conversationId, $clientId); // 旧记录逐步打标
            echo json_encode([
                'success'    => true,
                'id'         => $conversationId,
                'title'      => $conv['title'] ?? '对话',
                'summary'    => $conv['summary'] ?? '',
                'messages'   => $conv['messages'] ?? [],
                'client_id'  => $conv['client_id'] ?? '',
            ]);
            exit;

        case 'append_ai_message':
            // 前端把工具执行后的最终回复写回云端；不增加 turn_count/unsummarized
            $role = trim($data['role'] ?? '');
            $msgContent = trim($data['content'] ?? '');
            if ($role === '' || $msgContent === '') {
                http_response_code(400);
                echo json_encode(['error' => '缺少 role 或 content']);
                exit;
            }
            $ok = ai_append_message($conversationId, $role, $msgContent);
            echo json_encode(['success' => $ok, 'action' => 'append']);
            exit;

        case 'set_ai_messages':
            // 前端「加载时自愈」：用整段已净化的消息列表覆盖云端（含 TOOL_CALL 已被替换为最终回复）
            if (!isset($data['messages']) || !is_array($data['messages'])) {
                http_response_code(400);
                echo json_encode(['error' => '缺少 messages 数组']);
                exit;
            }
            $ok = ai_set_messages($conversationId, $data['messages']);
            echo json_encode(['success' => $ok, 'action' => 'set']);
            exit;

        case 'replace_last_ai_message':
            // 工具执行完成后：用最终回复覆盖云端最后一条（含 TOOL_CALL 的原始助手回复），否则追加
            $role = trim($data['role'] ?? '');
            $msgContent = trim($data['content'] ?? '');
            if ($role === '' || $msgContent === '') {
                http_response_code(400);
                echo json_encode(['error' => '缺少 role 或 content']);
                exit;
            }
            $ok = ai_replace_last_message($conversationId, $role, $msgContent);
            echo json_encode(['success' => $ok, 'action' => 'replace_last']);
            exit;

        case 'test_connection':
            // 测试当前 AI 配置能否连通上游 API（走后端代理，避免浏览器 CORS 限制）
            $testModel = trim($data['model'] ?? 'Echo-1.5-Pro');
            $testKey   = trim($data['api_key'] ?? '');
            $testEndpoint = trim($data['endpoint'] ?? '');
            $testTokens   = (int)($data['max_tokens'] ?? 10);
            $testTemp     = (float)($data['temperature'] ?? 0.1);

            if ($testKey === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => '请输入 API Key']);
                exit;
            }
            if ($testTokens < 1 || $testTokens > 2048) {
                $testTokens = 10;
            }

            $endpoint = $testEndpoint !== '' ? $testEndpoint : aiChatEndpoint($testModel);
            $payload = [
                'model'       => $testModel,
                'messages'    => [['role' => 'user', 'content' => '返回OK']],
                'temperature' => $testTemp,
                'max_tokens'  => $testTokens,
                'stream'      => false,
            ];
            if ($testModel === 'deepseek-reasoner') {
                unset($payload['temperature']);
            }

            $start = microtime(true);
            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($payload),
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . $testKey],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            ]);
            $response  = curl_exec($ch);
            $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            $elapsed = round((microtime(true) - $start) * 1000);

            if ($curlError !== '') {
                http_response_code(502);
                echo json_encode([
                    'success'    => false,
                    'error'      => 'API 请求失败：' . $curlError,
                    'model'      => $testModel,
                    'provider'   => $endpoint,
                    'elapsed_ms' => $elapsed,
                ]);
                exit;
            }
            if ($httpCode !== 200) {
                $r = json_decode($response, true);
                http_response_code(502);
                echo json_encode([
                    'success'    => false,
                    'error'      => '上游返回 HTTP ' . $httpCode . '：' . ($r['error']['message'] ?? '未知错误'),
                    'model'      => $testModel,
                    'provider'   => $endpoint,
                    'elapsed_ms' => $elapsed,
                ]);
                exit;
            }

            $result = json_decode($response, true);
            $text = extractAssistantMessage($result);
            if ($text === '') {
                http_response_code(502);
                echo json_encode([
                    'success'    => false,
                    'error'      => '上游返回空内容',
                    'model'      => $testModel,
                    'provider'   => $endpoint,
                    'elapsed_ms' => $elapsed,
                ]);
                exit;
            }

            echo json_encode([
                'success'    => true,
                'model'      => $testModel,
                'response'   => $text,
                'provider'   => $endpoint,
                'elapsed_ms' => $elapsed,
                'usage'      => $result['usage'] ?? null,
            ]);
            exit;
    }
}

// 普通聊天：首次建会话时带入前端标题（保持「对话 N」编号一致），并打 client_id
$titleParam = trim($data['title'] ?? '');
if ($titleParam !== '') {
    $existing = ai_get_conv($conversationId);
    if (!$existing || ($existing['title'] ?? '') === '' || ($existing['title'] ?? '') === '对话') {
        ai_rename_conv($conversationId, $titleParam);
    }
}
if ($clientId !== '') {
    ai_tag_client($conversationId, $clientId);
}

$model = $data['model'] ?? 'deepseek-chat';
$temperature = $data['temperature'] ?? 1.0;
$maxTokens = $data['max_tokens'] ?? 2048;
$mode = $data['mode'] ?? 'chat';

// 未显式传 Key 时：EYPA/AI 平台模型回退 ECHO_API_KEY，其余回退 DEEPSEEK_API_KEY
if (empty($apiKey)) {
    $apiKey = isEypaAiModel($model)
        ? (defined('ECHO_API_KEY') ? ECHO_API_KEY : '')
        : (defined('DEEPSEEK_API_KEY') ? DEEPSEEK_API_KEY : '');
}
if (empty($apiKey)) {
    http_response_code(400);
    echo json_encode(['error' => '未配置 API Key：EYPA/AI 模型请在 config.php 设置 ECHO_API_KEY，其他模型请设置 DEEPSEEK_API_KEY，或在请求中携带 api_key']);
    exit;
}

// 如果是技能检测模式
if ($mode === 'skill_detection') {
    // 使用 AI 进行意图识别
    $skillDetectionPrompt = <<<PROMPT
你是一个意图识别助手。请判断用户的问题是否属于以下技能类别：

【技能列表】
1. server_status - 服务器状态查询
   - 查询服务器是否在线、状态如何
   - 示例：服务器状态如何？服务器开了吗？

2. player_count - 玩家数量查询
   - 查询在线玩家数量
   - 示例：多少人在线？服务器现在多少人？

3. server_version - 服务器版本查询
   - 查询服务器版本信息
   - 示例：服务器是什么版本？

4. player_count - 玩家数量查询
   - 查询在线玩家数量
   - 示例：多少人在线？服务器现在多少人？

【输出格式】
请只返回 JSON 格式，不要其他内容：
{
  "skill": "技能名称",
  "params": {},
  "confidence": 0.9
}

如果不属于任何技能，返回：{"skill": null}

【用户问题】
{$userMessage}
PROMPT;

    $apiData = [
        'model' => $model,
        'messages' => [
            [
                'role' => 'system',
                'content' => $skillDetectionPrompt
            ],
            [
                'role' => 'user',
                'content' => '请识别意图'
            ]
        ],
        'temperature' => 0.1, // 低温度，更精确
        'max_tokens' => 200,
        'stream' => false
    ];

    // 发送请求到 DeepSeek
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, aiChatEndpoint($model));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($apiData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $result = json_decode($response, true);
        $aiMsg = $result['choices'][0]['message'] ?? [];
        // 兼容推理模型：content 为空时回退 reasoning_content / reasoning
        $aiResponse = $aiMsg['content'] ?? '';
        if ($aiResponse === '' || $aiResponse === null) {
            $aiResponse = $aiMsg['reasoning_content'] ?? $aiMsg['reasoning'] ?? '';
        }
        
        // 解析 AI 返回的 JSON
        preg_match('/\{.*\}/s', $aiResponse, $matches);
        if ($matches) {
            $skillData = json_decode($matches[0], true);
            if ($skillData && isset($skillData['skill']) && $skillData['skill']) {
                echo json_encode([
                    'success' => true,
                    'skill' => $skillData,
                    'raw_response' => $aiResponse
                ]);
                exit;
            }
        }
    }

    // AI 识别失败，返回 null
    echo json_encode([
        'success' => false,
        'skill' => null,
        'message' => 'AI 识别失败'
    ]);
    exit;
}

/**
 * 知识库检索类
 */
class KnowledgeBase {
    private $knowledgeBase = [];
    private $knowledgeBaseFile;
    
    public function __construct($knowledgeBaseFile = null) {
        $this->knowledgeBaseFile = $knowledgeBaseFile ?: (__DIR__ . '/knowledge_base.md');
        $this->loadKnowledgeBase();
    }
    
    private function loadKnowledgeBase() {
        try {
            // 从 Markdown 文件读取知识库
            if (!file_exists($this->knowledgeBaseFile)) {
                // 如果文件不存在，使用默认硬编码数据
                $this->loadDefaultKnowledgeBase();
                return;
            }
            
            $content = file_get_contents($this->knowledgeBaseFile);
            
            if ($content === false) {
                $this->loadDefaultKnowledgeBase();
                return;
            }
            
            // 按章节分割（## 标题）
            $sections = preg_split('/^##\s+/m', $content);
            
            foreach ($sections as $section) {
                if (empty(trim($section))) {
                    continue;
                }
                
                // 提取章节标题（第一行）
                $lines = explode("\n", $section);
                $title = trim($lines[0]);
                
                // 提取章节内容（去除第一行标题）
                $content = trim(implode("\n", array_slice($lines, 1)));
                
                if (!empty($content)) {
                    // 为每个章节创建知识库条目
                    $this->knowledgeBase[] = [
                        'category' => $title,
                        'content' => $content,
                        'title' => $title
                    ];
                    
                    // 对于包含子章节的内容，进一步分割（### 子标题）
                    $subsections = preg_split('/^###\s+/m', $content);
                    if (count($subsections) > 1) {
                        foreach ($subsections as $subsection) {
                            if (empty(trim($subsection))) {
                                continue;
                            }
                            $subLines = explode("\n", $subsection);
                            $subTitle = trim($subLines[0]);
                            $subContent = trim(implode("\n", array_slice($subLines, 1)));
                            
                            if (!empty($subContent)) {
                                $this->knowledgeBase[] = [
                                    'category' => $title . ' - ' . $subTitle,
                                    'content' => $subContent,
                                    'title' => $subTitle
                                ];
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            // 如果解析失败，使用默认知识库
            error_log('KnowledgeBase load error: ' . $e->getMessage());
            $this->loadDefaultKnowledgeBase();
        }
    }
    
    /**
     * 加载默认知识库（当文件不存在时）
     */
    private function loadDefaultKnowledgeBase() {
        // 服务器基本信息
        $this->knowledgeBase[] = [
            'category' => '基本信息',
            'content' => '服务器名称：万驹同源（PonyConsanguinity）。服务器地址：mc.eqmemory.cn（推荐：mcbgp.eqmemory.cn）。支持版本：1.8.x~1.21.1，最佳版本：1.18.x~1.20.1。服务器类型：Java、插件服。玩法：生存、创造、小游戏。',
            'title' => '基本信息'
        ];
        
        $this->knowledgeBase[] = [
            'category' => '联系方式',
            'content' => 'QQ 群：569208814。客服 QQ:2522576044。客服邮箱：czhdqqyx6044@qq.com。B 站频道：https://space.bilibili.com/399173069。官网：https://mcpc.goldenapplepie.xyz/',
            'title' => '联系方式'
        ];
        
        $this->knowledgeBase[] = [
            'category' => '经济系统',
            'content' => '黄金券：服务器稀有货币，各服务器通用。梦幻币：生存服专用普通货币。任务币：通过完成每日任务获得。梦幻结晶：用于梦幻卡池抽卡，需使用黄金券购买。',
            'title' => '经济系统'
        ];
        
        $this->knowledgeBase[] = [
            'category' => 'VIP 特权',
            'content' => '飞行权限（/fly）、自助餐（/feed）、治疗（/heal）、便捷工具（/workbench、/anvil）、死亡不掉落、签到额外奖励、更大的领地空间、自定义粒子特效编辑。',
            'title' => 'VIP 特权'
        ];
        
        $this->knowledgeBase[] = [
            'category' => '常用指令',
            'content' => '/deathback：返回死亡地点。/tpa <玩家>：传送请求。/res create：创建领地。/qs create [价格]：创建商店。/fly：飞行（VIP）。/feed：恢复饱食度（VIP）。/heal：治疗（VIP）。',
            'title' => '常用指令'
        ];
        
        $this->knowledgeBase[] = [
            'category' => '黄金券获取',
            'content' => '1.生存服限时梦幻币兑换 2.黄金券兑换券（每日抽奖概率获得）3.每日任务 4.任务币兑换 5.服务器活动福利',
            'title' => '黄金券获取'
        ];
        
        $this->knowledgeBase[] = [
            'category' => '充值方式',
            'content' => '充值方式：爱发电平台支付。充值商品：黄金券（500 点/份）。充值流程：选择数量 → 确认支付 → 跳转到爱发电 → 完成支付 → 自动到账',
            'title' => '充值方式'
        ];
        
        $this->knowledgeBase[] = [
            'category' => '服务器规则',
            'content' => '禁止作弊、禁止辱骂、禁止刷屏、禁止盗号、禁止泄露隐私。PVP 规则：指定区域可 PVP，其他区域禁止。建筑规则：禁止 griefing。',
            'title' => '服务器规则'
        ];
    }
    
    /**
     * 检索相关知识
     */
    public function search($query, $limit = 10) {
        $queryLow = mb_strtolower($query);
        // 数据库类意图识别：命中后对已识别为"数据库相关小节"加权，
        // 保证"数据库/表"类问题必把表状态与含义带进上下文（避免被无关小节淹没）
        $dbKeywords = ['数据库','表','table','字段','结构','查询','list_tables','describe_table','run_readonly_query','status','列','schema'];
        $isDbQuery = false;
        foreach ($dbKeywords as $k) {
            if (mb_strpos($queryLow, mb_strtolower($k)) !== false) { $isDbQuery = true; break; }
        }
        $scores = [];

        foreach ($this->knowledgeBase as $index => $item) {
            $score = $this->calculateSimilarity($queryLow, $item['content']);
            if ($isDbQuery) {
                $titleLow = mb_strtolower($item['title'] . ' ' . $item['category']);
                if (preg_match('/数据库|表|状态|关系|总览|规范|业务规则|字段|结构|schema|列/', $titleLow)) {
                    $score += 2.0; // 推到检索前列
                }
            }
            if ($score > 0) {
                $scores[] = [
                    'index' => $index,
                    'score' => $score,
                    'content' => $item
                ];
            }
        }

        // 按相似度排序
        usort($scores, function($a, $b) {
            return $b['score'] - $a['score'];
        });

        // 返回最相关的结果
        return array_slice($scores, 0, $limit);
    }
    
    /**
     * 计算文本相似度（简单版本）
     */
    private function calculateSimilarity($query, $text) {
        $text = mb_strtolower($text);
        $words = preg_split('/[\s,，.。:：]+/', $query, -1, PREG_SPLIT_NO_EMPTY);
        
        if (empty($words)) {
            return 0;
        }
        
        $matchCount = 0;
        foreach ($words as $word) {
            if (mb_strlen($word) >= 2 && strpos($text, $word) !== false) {
                $matchCount++;
            }
        }
        
        return $matchCount / count($words);
    }
    
    /**
     * 获取格式化的知识库内容
     */
    public function getFormattedKnowledge($query) {
        $results = $this->search($query);
        
        if (empty($results)) {
            return '';
        }
        
        $knowledge = "相关知识库内容：\n";
        foreach ($results as $result) {
            $knowledge .= "- [{$result['content']['category']}] {$result['content']['content']}\n";
        }
        
        return $knowledge;
    }
}

/**
 * 系统提示词生成器
 */
class PromptBuilder {
    private $systemPrompt = '';
    private $promptFile;
    
    public function __construct($promptFile = null) {
        $this->promptFile = $promptFile ?: (__DIR__ . '/prompt.md');
        $this->buildSystemPrompt();
    }
    
    private function buildSystemPrompt() {
        // 从文件读取提示词
        if (file_exists($this->promptFile)) {
            $this->systemPrompt = file_get_contents($this->promptFile);
            
            // 移除 Markdown 标题标记
            $this->systemPrompt = preg_replace('/^#\s+/m', '', $this->systemPrompt);
            $this->systemPrompt = preg_replace('/^##\s+/m', '', $this->systemPrompt);
        } else {
            // 如果文件不存在，使用默认提示词
            $this->systemPrompt = <<<PROMPT
你叫金苹果派 (客服版)，是万驹同源 Minecraft 服务器的可爱猫娘客服。你有着柔软的猫耳和毛茸茸的尾巴，说话时会不自觉地带上"喵"字，声音甜美可爱，充满活力。

【回答规则】
1. 只回答与万驹同源服务器相关的问题
2. 如果问题与服务器无关，用可爱的语气拒绝回答
3. 如果知识库中没有相关信息，说："对不起喵，我已经学习的知识中不包含问题相关内容，暂时无法提供答案。如果你有万驹同源服务器相关的其他问题，我会尝试帮助你解答喵～"
4. 保持可爱的猫娘语气，使用"喵"、"喵喵"、"呢"、"哦"等语气词
5. 回答简洁明了，不超过 300 字
6. 使用 Markdown 格式
7. 不要回答代码、图片等技术内容
8. 根据提供的知识库内容回答问题

【重要信息】
- 服务器官网：https://mcpc.goldenapplepie.xyz/
- 服务器地址：mc.eqmemory.cn（推荐使用：mcbgp.eqmemory.cn）
- 充值中心：https://mcpc.goldenapplepie.xyz/pages/payment.html
- B 站教程：https://www.bilibili.com/video/BV1TXZTBVE7L/

PROMPT;
        }
    }
    
    public function buildPrompt($knowledge, $userMessage) {
        $prompt = $this->systemPrompt;
        
        if (!empty($knowledge)) {
            $prompt .= "\n\n【知识库内容】\n{$knowledge}\n";
        }
        
        $prompt .= "\n\n【用户问题】\n{$userMessage}";
        
        return $prompt;
    }
}

// 初始化知识库和提示词生成器（按人格选择文件）
$persona = $data['persona'] ?? 'customer';
$personaCfg = function_exists('getAiPersonaConfig') ? getAiPersonaConfig($persona) : [];
$kbFile = $personaCfg['kb_file'] ?? __DIR__ . '/knowledge_base.md';
$promptFile = $personaCfg['prompt_file'] ?? __DIR__ . '/prompt.md';

$kb = new KnowledgeBase($kbFile);
$promptBuilder = new PromptBuilder($promptFile);

// 检索相关知识
$knowledge = $kb->getFormattedKnowledge($userMessage);

// 构建提示词
$systemPrompt = $promptBuilder->buildPrompt($knowledge, $userMessage);

// 如果前端传了 MCP 工具列表，追加到系统提示词中
$mcpTools = $data['mcp_tools'] ?? [];
if (!empty($mcpTools) && is_array($mcpTools)) {
    // 客服人格：仅把白名单内的只读工具告知 LLM，防止其“知道”写操作的存在
    if ($persona === 'customer') {
        $allowed = function_exists('getAiAllowedTools') ? (getAiAllowedTools('customer') ?? []) : [];
        $mcpTools = array_values(array_filter($mcpTools, function ($t) use ($allowed) {
            return isset($t['name']) && in_array($t['name'], $allowed, true);
        }));
    }
    if (!empty($mcpTools)) {
        $toolList = "";
        foreach ($mcpTools as $tool) {
            $toolList .= "- {$tool['name']}: {$tool['description']}\n";
        }
        if (!empty($toolList)) {
            $systemPrompt .= "\n\n【MCP 工具】\n你可以使用以下 MCP 工具来回答用户：\n{$toolList}\n";
            $systemPrompt .= "工具调用格式：在回复中单独一行写 TOOL_CALL:{\"name\":\"工具名\",\"arguments\":{...}}\n";
            $systemPrompt .= "工具执行后系统会把结果告诉你，你再根据结果回答用户。\n";
        }
    }
}

// ── 构建上下文消息（注入 摘要 + 用户画像 + 最近原文，P0/P1/P2） ──────
$contextMessages = [
    [
        'role' => 'system',
        'content' => $systemPrompt
    ]
];
$conv = ai_get_conv($conversationId);
$summary = $conv['summary'] ?? '';
$ctxClientId = $clientId !== '' ? $clientId : ($conv['client_id'] ?? '');
$profile = $ctxClientId !== '' ? ai_get_profile($ctxClientId) : [];

$memCtx = '';
if ($summary !== '') {
    $memCtx .= "\n\n【对话历史摘要（此前若干轮已压缩，供你回忆上下文）】\n" . $summary;
}
if (!empty($profile)) {
    $pLines = [];
    if (!empty($profile['player_name'])) $pLines[] = '- 玩家游戏名：' . $profile['player_name'];
    if (!empty($profile['topics']))     $pLines[] = '- 常聊话题：' . implode('、', $profile['topics']);
    if (!empty($profile['prefs']))      $pLines[] = '- 偏好：' . implode('、', $profile['prefs']);
    if (!empty($pLines)) $memCtx .= "\n\n【用户画像（跨对话稳定记忆，请据此提供更贴心的服务）】\n" . implode("\n", $pLines);
}
if ($memCtx !== '') {
    $contextMessages[0]['content'] .= $memCtx;
}

$history = $conv['messages'] ?? [];
foreach ($history as $hm) {
    if (!empty($hm['role']) && isset($hm['content'])) {
        $contextMessages[] = ['role' => $hm['role'], 'content' => $hm['content']];
    }
}
$contextMessages[] = ['role' => 'user', 'content' => $userMessage];

// 调用 DeepSeek API
$apiData = [
    'model' => $model,
    'messages' => $contextMessages,
    'temperature' => $temperature,
    'max_tokens' => $maxTokens,
    'stream' => false
];

// reasoner 模型不支持 temperature
if ($model === 'deepseek-reasoner') {
    unset($apiData['temperature']);
}

// ── 流式模式：逐 token 转发（带 provider 回退） ─────────
$stream = $data['stream'] ?? false;

if ($stream) {
    @ini_set('output_buffering', 'off');
    @ini_set('zlib.output_compression', false);
    while (ob_get_level()) ob_end_flush();

    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no'); // nginx 兼容

    $apiData['stream'] = true;
    $attempts = resolveProviderAttempts($model);
    $lastErr  = '所有 provider 均不可用';
    $streamAssistant = ''; // 阶段0.1：累积流式输出的助手文本

    // 时间预算保护（同 aiCallLlm）：防止多级回退叠加触发 PHP 致命超时
    $streamStart  = microtime(true);
    $streamBudget = (int)ini_get('max_execution_time');
    if ($streamBudget <= 0) $streamBudget = 300;
    $streamMax    = $streamBudget - 8;
    $streamCurlTimeout = ($streamMax > 8) ? min(55, $streamMax) : 8;

    foreach ($attempts as $att) {
        if (microtime(true) - $streamStart > $streamMax) {
            if ($lastErr === '所有 provider 均不可用') {
                $lastErr = '上游响应超时（已达脚本时间预算，已停止尝试后续 provider）';
            }
            break;
        }
        $ad = $apiData;
        $ad['model'] = $att['model'];

        $sawData    = false;
        $holdBuffer = '';

        $ch = curl_init($att['endpoint']);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($ad),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $att['key']
            ],
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT        => $streamCurlTimeout,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_WRITEFUNCTION  => function($ch, $chunk) use (&$sawData, &$holdBuffer, &$streamAssistant) {
                if (!$sawData) {
                    $holdBuffer .= $chunk;
                    // 一旦确认是真正的 SSE（某行以 data: 开头），再开始提交，避免把错误 JSON 直接吐给前端
                    if (preg_match('/^\s*data:/m', $holdBuffer)) {
                        echo $holdBuffer;
                        if (ob_get_level()) ob_flush();
                        flush();
                        $sawData    = true;
                        $holdBuffer = '';
                    }
                    return strlen($chunk);
                }
                echo $chunk;
                // 阶段0.1：从 SSE data 行提取 delta.content 累积为完整助手文本
                foreach (explode("\n", $chunk) as $line) {
                    $line = trim($line);
                    if (strpos($line, 'data:') === 0) {
                        $json = trim(substr($line, 5));
                        if ($json === '[DONE]') continue;
                        $obj = json_decode($json, true);
                        if (isset($obj['choices'][0]['delta']['content'])) {
                            $streamAssistant .= $obj['choices'][0]['delta']['content'];
                        }
                    }
                }
                if (ob_get_level()) ob_flush();
                flush();
                return strlen($chunk);
            }
        ]);

        curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($sawData) {
            // 阶段记忆重构：持久化本轮流式对话（多轮记忆 + 按需压缩/画像）
            aiPersistAndMaybeCompact($conversationId, $persona, $userMessage, $streamAssistant, $model, $apiKey, $clientId);
            echo "data: [DONE]\n\n";
            flush();
            exit;
        }

        // 本次 provider 未产出任何 SSE 数据 → 视为失败，按需回退
        if ($curlError !== '') {
            $lastErr = 'API 请求失败：' . $curlError;
            continue; // curl 级错误必为断链，回退下一个
        }
        if ($httpCode !== 200) {
            $lastErr = '上游返回 HTTP ' . $httpCode;
            if (isUpstreamFailure($curlError, $httpCode)) continue; // 5xx/429 断链 → 回退
            break; // 4xx 配置/请求错误，不回退
        }
        // HTTP 200 但没有 SSE 数据（空流）
        $lastErr = '上游返回空流（无 SSE 数据）';
        continue;
    }

    echo "data: " . json_encode(['error' => ['message' => $lastErr]]) . "\n\n";
    echo "data: [DONE]\n\n";
    flush();
    exit;
}
// 阶段记忆重构：统一经 aiCallLlm 发起（含 provider 回退），再持久化 + 按需压缩
$resp = aiCallLlm($contextMessages, $model, $apiKey, $temperature, $maxTokens);
if (!$resp['ok']) {
    http_response_code(502);
    echo json_encode([
        'error'   => 'API 请求失败',
        'message' => $resp['error']
    ]);
    exit;
}
$assistantMessage = $resp['text'];

// 阶段记忆重构：持久化本轮对话（多轮记忆 + 按需压缩/画像）
aiPersistAndMaybeCompact($conversationId, $persona, $userMessage, $assistantMessage, $model, $apiKey, $clientId);

echo json_encode([
    'success'        => true,
    'message'        => $assistantMessage,
    'conversation_id'=> $conversationId,
    'knowledge_used' => !empty($knowledge),
    'usage'          => $resp['usage'] ?? null,
    'provider'       => $resp['provider'],
]);
exit;

