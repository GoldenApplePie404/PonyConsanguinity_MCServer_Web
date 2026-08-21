<?php
/**
 * AI Core — 网站与管理 APP 共用的 LLM 调用 + RAG 检索内核
 * ============================================================
 * 本文件只定义"纯函数 + 类"，不含任何端点逻辑（不读 php://input、不 echo、不 exit），
 * 因此可被 api/ai/api.php（站内客服/管理端点）与 api/ai/app_agent.php（Godot APP 大脑）
 * 共同 require，保证两套 AI 走完全一致的 provider 回退链与知识库检索逻辑。
 *
 * 依赖：config.php 提供的 ECHO_API_URL / ECHO_API_KEY / DEEPSEEK_API_URL / DEEPSEEK_API_KEY 常量。
 */

// Echo / DeepSeek 端点解析：依据模型名自动选择 provider（兼容 OpenAI 协议）
function aiChatEndpoint(string $model): string {
    $isEcho = stripos($model, 'Echo') === 0;
    if ($isEcho) {
        $base = defined('ECHO_API_URL') ? rtrim(ECHO_API_URL, '/') : 'https://eapi.eqmemory.cn/v1';
    } else {
        $base = defined('DEEPSEEK_API_URL') ? rtrim(DEEPSEEK_API_URL, '/') : 'https://api.deepseek.com';
    }
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

// 解析需要尝试的 provider/model 列表（三级回退）
// 回退链：Echo-1.5-Flash -> Echo-1.5-Pro -> DeepSeek
//   - 主模型为 Flash 时，先 Flash；Flash 断链则升级到 Pro（echo 系列内部自愈）
//   - 主模型为 Pro 时，先 Pro（不再降级到 Flash）
//   - 整个 echo 系列都无法响应（断链）时，自动回退到 DeepSeek
function resolveProviderAttempts(string $model): array {
    $echoUrl = defined('ECHO_API_URL') ? rtrim(ECHO_API_URL, '/') : 'https://eapi.eqmemory.cn/v1';
    $dsUrl   = defined('DEEPSEEK_API_URL') ? rtrim(DEEPSEEK_API_URL, '/') : 'https://api.deepseek.com';
    $echoKey = defined('ECHO_API_KEY') ? ECHO_API_KEY : '';
    $dsKey   = defined('DEEPSEEK_API_KEY') ? DEEPSEEK_API_KEY : '';
    $model   = trim($model);

    $attempts = [];
    $isEcho = stripos($model, 'Echo') === 0 || stripos($model, 'echo') === 0;

    if ($isEcho) {
        if ($echoKey !== '') {
            if (stripos($model, 'Flash') !== false) {
                // 主用 Flash，断链时升级 Pro
                $attempts[] = ['endpoint' => $echoUrl . '/chat/completions', 'key' => $echoKey, 'model' => 'Echo-1.5-Flash'];
                $attempts[] = ['endpoint' => $echoUrl . '/chat/completions', 'key' => $echoKey, 'model' => 'Echo-1.5-Pro'];
            } elseif (stripos($model, 'Pro') !== false) {
                // 主用 Pro，不再降级到 Flash
                $attempts[] = ['endpoint' => $echoUrl . '/chat/completions', 'key' => $echoKey, 'model' => 'Echo-1.5-Pro'];
            } else {
                // 其他 Echo 模型，原样尝试
                $attempts[] = ['endpoint' => $echoUrl . '/chat/completions', 'key' => $echoKey, 'model' => $model];
            }
        }
        // echo 全系列断链，回退 DeepSeek
        if ($dsKey !== '') {
            $attempts[] = ['endpoint' => $dsUrl . '/chat/completions', 'key' => $dsKey, 'model' => 'deepseek-chat'];
        }
    } else {
        // 非 Echo（如 DeepSeek）：原样尝试
        if (stripos($model, 'deepseek') !== false && $dsKey !== '') {
            $attempts[] = ['endpoint' => $dsUrl . '/chat/completions', 'key' => $dsKey, 'model' => $model];
        } elseif ($echoKey !== '') {
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
    $ad = ['model' => $model, 'messages' => $messages, 'temperature' => $temperature, 'max_tokens' => $maxTokens, 'stream' => false];
    if ($model === 'deepseek-reasoner') unset($ad['temperature']);
    $attempts = resolveProviderAttempts($model);
    $lastErr = '所有 provider 均不可用';
    foreach ($attempts as $att) {
        $a = $ad;
        $a['model'] = $att['model'];
        $ch = curl_init($att['endpoint']);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($a),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . $att['key']],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
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
            $this->systemPrompt = "你是一个 Minecraft 服务器管理助手，请根据工具执行结果回答。";
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
