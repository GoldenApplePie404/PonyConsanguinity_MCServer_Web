<?php
/**
 * AI 客服 API - 基于 DeepSeek 的 RAG 系统
 * 
 * 功能：
 * 1. 知识库检索
 * 2. 智能问答
 * 3. 上下文管理
 */

require_once __DIR__ . '/../../config/_config.php';

// 设置 CORS 头
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
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

// 验证必要参数
$requiredFields = ['message', 'api_key'];
foreach ($requiredFields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "缺少必要参数：{$field}"]);
        exit;
    }
}

$userMessage = $data['message'];
$apiKey = $data['api_key'];
$conversationId = $data['conversation_id'] ?? null;
$model = $data['model'] ?? 'deepseek-chat';
$temperature = $data['temperature'] ?? 1.0;
$maxTokens = $data['max_tokens'] ?? 2048;
$mode = $data['mode'] ?? 'chat'; 

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

3. player_query - 玩家查询
   - 查询特定玩家是否在线
   - 示例：czhdq 在线吗？查询玩家 Steve

4. server_version - 服务器版本查询
   - 查询服务器版本信息
   - 示例：服务器是什么版本？

5. player_count - 玩家数量查询
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
    curl_setopt($ch, CURLOPT_URL, 'https://api.deepseek.com/chat/completions');
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
        $aiResponse = $result['choices'][0]['message']['content'] ?? '';
        
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
    
    public function __construct() {
        $this->knowledgeBaseFile = __DIR__ . '/knowledge_base.md';
        $this->loadKnowledgeBase();
    }
    
    private function loadKnowledgeBase() {
        // 从 Markdown 文件读取知识库
        if (!file_exists($this->knowledgeBaseFile)) {
            // 如果文件不存在，使用默认硬编码数据
            $this->loadDefaultKnowledgeBase();
            return;
        }
        
        $content = file_get_contents($this->knowledgeBaseFile);
        
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
    public function search($query, $limit = 3) {
        $query = mb_strtolower($query);
        $scores = [];
        
        foreach ($this->knowledgeBase as $index => $item) {
            $score = $this->calculateSimilarity($query, $item['content']);
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
    
    public function __construct() {
        $this->promptFile = __DIR__ . '/prompt.md';
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

// 初始化知识库和提示词生成器
$kb = new KnowledgeBase();
$promptBuilder = new PromptBuilder();

// 检索相关知识
$knowledge = $kb->getFormattedKnowledge($userMessage);

// 构建提示词
$systemPrompt = $promptBuilder->buildPrompt($knowledge, $userMessage);

// 调用 DeepSeek API
$apiData = [
    'model' => $model,
    'messages' => [
        [
            'role' => 'system',
            'content' => $systemPrompt
        ],
        [
            'role' => 'user',
            'content' => $userMessage
        ]
    ],
    'temperature' => $temperature,
    'max_tokens' => $maxTokens,
    'stream' => false
];

// reasoner 模型不支持 temperature
if ($model === 'deepseek-reasoner') {
    unset($apiData['temperature']);
}

// 发送请求到 DeepSeek
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.deepseek.com/chat/completions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($apiData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $apiKey
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

// SSL 证书配置（开发环境）
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 跳过证书验证
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // 跳过主机验证

// 生产环境应该使用正确的证书配置：
// curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
// curl_setopt($ch, CURLOPT_CAINFO, __DIR__ . '/cacert.pem');

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// 处理响应
if ($curlError) {
    http_response_code(500);
    echo json_encode([
        'error' => 'API 请求失败',
        'message' => $curlError
    ]);
    exit;
}

$result = json_decode($response, true);

if ($httpCode !== 200) {
    http_response_code($httpCode);
    echo json_encode([
        'error' => 'API 请求失败',
        'message' => $result['error']['message'] ?? '未知错误',
        'http_code' => $httpCode
    ]);
    exit;
}

// 提取回复内容
$assistantMessage = $result['choices'][0]['message']['content'] ?? '';
$usage = $result['usage'] ?? null;

// 返回响应
echo json_encode([
    'success' => true,
    'message' => $assistantMessage,
    'conversation_id' => $conversationId ?? uniqid('conv_'),
    'knowledge_used' => !empty($knowledge),
    'usage' => $usage
]);
?>
