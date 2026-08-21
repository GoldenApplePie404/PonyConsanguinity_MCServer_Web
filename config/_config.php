// 注：这是一个配置文件的示例，同时附上了详细的说明。
// 填写好配置参数后，记得将其修改成config.php（_config.php ——> config.php）

<?php
if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}

// ==================== 数据库配置 ====================
define('DB_HOST', 'xxx.xxx.xxx.xxx'); // 数据库主机地址
define('DB_PORT', 3306);              // 数据库端口（MySQL 默认 3306）
define('DB_NAME', 'database_name');  // 数据库名称
define('DB_USER', 'database_user');  // 数据库用户名
define('DB_PASS', 'database_password');  // 数据库密码

// ==================== MCSManager API 配置 ====================
define('MCSM_API_URL', 'https://xxx.xxxxxx.com/api');   // MCSManager API 地址
define('MCSM_API_KEY', 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxx');  // MCSManager API Key

// ==================== 状态页「子服一览」显示配置 ====================
// 键：MCSManager 面板中的实例原始名；值：对外展示名。
// 只展示此数组中列出的实例，展示顺序 = 数组顺序。
// 扩充方法：新增一行「'实例原始名' => '展示名',」即可，其余无需改动。
define('MC_DISPLAY_INSTANCES', [
    '[1.21.1NeoForge]ModServer' => '炉边茶社',
    '[PC]万驹同源BungeeCord'     => 'BC服',
    '[PC]生存服'                 => '生存服',
    '[PC]创造服'                 => '创造服',
    '[PC]登录服'                 => '登录服',
]);

// 子服「核心」显示配置：实例原始名 => 核心名称（MCSM 面板无核心字段，此处自定义）。
// 与 MC_DISPLAY_INSTANCES 键一致；未列出的实例核心显示「—」。
// 扩充：新增一行「'实例原始名' => '核心名',」即可。
define('MC_INSTANCE_CORES', [
    '[1.21.1NeoForge]ModServer' => 'NeoForge',
    '[PC]万驹同源BungeeCord'     => 'BungeeCord',
    '[PC]生存服'                 => 'Paper',
    '[PC]创造服'                 => 'Paper',
    '[PC]登录服'                 => 'Paper',
]);

// 子服「版本」显示配置：实例原始名 => 版本名（硬编码映射，不读 MCSM 的 version）。
// 与 MC_DISPLAY_INSTANCES 键一致；未列出的实例显示默认「1.12.x~1.21.1」。
// 扩充：新增一行「'实例原始名' => '版本',」即可。
define('MC_INSTANCE_VERSIONS', [
    '[1.21.1NeoForge]ModServer' => '1.21.1',
]);

// ==================== 服务器状态 API 配置 ====================
// 服务器状态 API 地址(公共示例：https://mcpc.goldenapplepie.xyz/mcstatus/api)
define('MCSTATUS_API_URL', 'https://xxxx.xxxxxx.com/mcstatus/api');
define('MC_SERVER_IP', 'xxx.xxxxxxx.xxx');  // Minecraft 服务器地址
define('MC_SERVER_PORT', 25565);          // Minecraft 服务器端口

// ==================== 服务器状态缓存配置 ====================
define('MCSTATUS_CACHE_FILE', dirname(__DIR__) . '/data/mcstatus_cache.json');
define('MCSTATUS_CACHE_TIME', 60);
define('MCSTATUS_MAX_RETRIES', 2);

// ==================== MCP 远程 Service Key 配置 ====================
// 远程 MCP 客户端通过 Authorization: Bearer <key>
// 连接 mcp/remote.php 时的鉴权密钥表。
// 格式：SHA256 hash (hex 64 字符) => 角色 (admin / user)
// 前端绝不暴露此哈希表；客户端仅持有明文 Key。
// 新增 Key：生成明文 Key → SHA256 → 将 hash→role 加入此数组。
define('MCP_SERVICE_KEYS', [
    // Admin 密钥（MCP开发/管理用）
    'fgnmjhkioiwerfghjdfgsdhisuyghuidsghsudihpuggadhipsugahduip' => 'admin',   // 注：当前仅为示例key（无法使用）
    // 后续添加新 Key 示例：
    // hash('sha256', 'your-new-plaintext-key') => 'user',
]);

// ==================== HTTPS 配置 ====================
define('IS_HTTPS', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443));

// ==================== 网站URL配置 ====================
$protocol = IS_HTTPS ? 'https://' : 'http://';
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost:8000';
define('SITE_URL', $protocol . $host);

// ==================== 马国记忆 OAuth 配置 ====================
// 是否启用马国记忆OAuth登录
//define('EYPA_OAUTH_ENABLED', true);

// 马国记忆平台配置
// 注意：EYPA 平台使用自定义 REST 前缀 eu-json（非 WordPress 标准 wp-json）。
// wp-json/eu-connect/... 返回 404，eu-json/eu-connect/... 正常（已于 2026-07-12 通过 curl 实测验证）。
//define('EYPA_API_ENDPOINT', 'https://xxxx.xxxxxxx.xxx/xxxx/v1/user-profile');    // 用户信息接口地址（暂不外露）
//define('EYPA_AUTH_URL', 'https://xxxxx.xx/eu-authorize');  // 授权页面地址 （暂不外露）

// 回调地址（自动根据当前域名生成）
$eypa_protocol = IS_HTTPS ? 'https' : 'http';
$eypa_host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
define('EYPA_REDIRECT_URI', $eypa_protocol . '://' . $eypa_host . '/api/eypa/callback.php');

// OAuth调试模式（开发时开启，生产环境关闭）
define('EYPA_OAUTH_DEBUG', true);

// ==================== CORS 配置 ====================
// 环境感知白名单：生产环境（HTTPS + 正式域名）仅放行生产域名；
// 本地开发环境自动追加本机调试入口，保证「本地联调可用」且「线上部署不暴露本地端口」。
// 如需新增可信前端域名，在下方 $corsAllowed 基础数组里追加即可。
$corsAllowed = ['https://mcpc.goldenapplepie.xyz'];

// 本地环境识别：非 HTTPS，或 host 为本机地址（localhost / 127.0.0.1，任意端口）
$requestHost   = $_SERVER['HTTP_HOST'] ?? '';
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
$isLocalhost   = (strpos($requestHost, 'localhost') !== false) || (strpos($requestHost, '127.0.0.1') !== false);
$isPlainHttp   = !IS_HTTPS;

if ($isPlainHttp || $isLocalhost) {
    // 固定常用本地入口
    $corsAllowed[] = 'http://localhost:8000';
    $corsAllowed[] = 'http://127.0.0.1:8000';
    // 兜底：放行当前请求所用的本机 origin（兼容自定义端口，例如 8080）
    if ($requestOrigin !== '' && (strpos($requestOrigin, 'http://localhost') === 0 || strpos($requestOrigin, 'http://127.0.0.1') === 0)) {
        $corsAllowed[] = $requestOrigin;
    }
}

define('CORS_ALLOW_ORIGIN', implode(',', array_unique($corsAllowed)));
define('CORS_ALLOW_METHODS', 'GET, POST, PUT, DELETE, OPTIONS');
define('CORS_ALLOW_HEADERS', 'Content-Type, Authorization, X-Requested-With');

// ==================== 会话配置 ====================
define('MAX_SESSIONS', 10);

// ==================== 数据文件路径配置 ====================
define('USERS_FILE', dirname(__DIR__) . '/data/users.php');
define('SESSIONS_FILE', dirname(__DIR__) . '/data/sessions.php');
define('POSTS_FILE', dirname(__DIR__) . '/data/posts.php');
define('NOTIFICATIONS_FILE', dirname(__DIR__) . '/data/notifications.php');
define('CONTENT_DIR', dirname(__DIR__) . '/data/content');
define('REPLIES_DIR', dirname(__DIR__) . '/data/replies');
define('MESSAGES_FILE', dirname(__DIR__) . '/data/messages.json');

// ==================== 点赞/收藏数据文件配置 ====================
define('LIKES_FILE', dirname(__DIR__) . '/data/likes.json');
define('BOOKMARKS_FILE', dirname(__DIR__) . '/data/bookmarks.json');

// ==================== 问卷系统配置 ====================
define('QUESTIONNAIRES_FILE', dirname(__DIR__) . '/data/questionnaires.json');

// ==================== 招募系统配置 ====================
define('RECRUITMENT_DIR', dirname(__DIR__) . '/data/recruitments');

// ==================== 爱发电 API 配置 ====================
define('AFDIAN_USER_ID', 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');   // 爱发电用户ID
define('AFDIAN_API_TOKEN', 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');   // 爱发电API令牌

// ==================== 爱发电订单更新模式配置 ====================
define('AFDIAN_ORDER_UPDATE_MODE', 'all');

// ==================== 爱发电自动定时任务配置 ====================
define('AFDIAN_CRON_ENABLED', true);
define('AFDIAN_CRON_INTERVAL', 120);
define('AFDIAN_CRON_MAX_TIME', 300);
define('AFDIAN_CRON_LOG_LEVEL', 'info');

// ==================== 爱发电方案/商品ID配置 ====================
define('AFDIAN_PLAN_GOLDEN_TICKET', 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');  // 爱发电「黄金券」方案ID
define('AFDIAN_PLAN_VIP_MONTH', 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');  // 爱发电「VIP月卡」方案ID
define('AFDIAN_PLAN_VIP_YEAR', 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');  // 爱发电「VIP年卡」方案ID

// ==================== 爱发电 Webhook 配置 ====================
define('AFDIAN_WEBHOOK_VERIFY_SIGN', true);

// ==================== 爱发电 API 接口共享密钥（防滥用）====================
// 保护 process_orders / auto_update 等会触发昂贵 Afdian API 调用的端点，防止被恶意频繁调用。
// 留空则关闭鉴权（开发/调试用）。前端 payment.html / admin-hub.html 携带相同值（请求头 X-Afdian-Key）。
// 注意：该密钥会出现在前端 JS 中，仅能阻挡"盲打"式滥用，无法防住查看源码的定向攻击；
// 若需更强防护，请在此基础上追加 IP 白名单或速率限制。
define('AFDIAN_API_SECRET', 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');  // 注：当前仅为示例key（无法使用）

// ==================== 爱发电日志配置 ====================
define('AFDIAN_LOG_DIR', dirname(__DIR__) . '/logs');
define('AFDIAN_LOG_LEVEL', 'info');
define('AFDIAN_LOG_MODULE', 'aifadian');

// ==================== 邮件 SMTP 配置 ====================
// 邮箱验证功能配置
// 支持的 SMTP 服务：QQ 邮箱 (smtp.qq.com)、163 邮箱 (smtp.163.com)、Gmail(smtp.gmail.com) 等

// 是否启用邮箱验证功能
define('EMAIL_VERIFICATION_ENABLED', true);

// SMTP 服务器配置
define('SMTP_HOST', 'smtp.qq.com');           // SMTP 服务器地址
define('SMTP_PORT', 465);                      // SMTP 端口（SSL: 465, TLS: 587, 非加密：25）
define('SMTP_USERNAME', 'xxxxxxxxxxxxxxxx@qq.com');  // 发件人邮箱
define('SMTP_PASSWORD', 'xxxxxxxxxxxxxxx');     // 邮箱授权码（不是登录密码）
define('SMTP_ENCRYPTION', 'ssl');              // 加密方式：ssl、tls 或空字符串（非加密）
define('SMTP_AUTH', true);                     // 是否启用 SMTP 认证

// 发件人信息
define('MAIL_FROM_EMAIL', 'xxxxxxxxxxxxxxxx@qq.com');
define('MAIL_FROM_NAME', '万驹同源服务器');

// 验证邮件配置
define('VERIFY_TOKEN_EXPIRY', 86400);          // 验证令牌有效期（秒）默认 24 小时
define('VERIFY_RESEND_INTERVAL', 600);         // 重新发送间隔（秒）默认 10 分钟
define('VERIFY_MAX_RESEND', 3);                // 每小时最大重发次数

// 重置密码配置
define('RESET_TOKEN_EXPIRY', 1800);            // 重置令牌有效期（秒）默认 30 分钟
define('RESET_RESEND_INTERVAL', 120);          // 重置邮件重发间隔（秒）默认 2 分钟

// ==================== AI 客服大模型配置 ====================
// 默认模型已切换为 Echo-1.5-Pro（EYPA/AI 自研 MoE 模型，兼容 OpenAI 协议）。
// 同时保留 DeepSeek 作为可回退的备选 provider。

// ── Echo (EYPA/AI) 配置 ──
//define('ECHO_API_URL', 'https://xx.xxxxxxx.xx/v1'); // Echo 基础域名（兼容 OpenAI 协议）——(暂不外露)
define('ECHO_API_KEY', 'sk-ageaegaegaehheaehaehaheaehaehaehaehg');  // Echo API Key（兼容 OpenAI 协议）——(暂不外露)

// EYPA/AI 平台可用模型（统一通过 ECHO_API_URL 访问，OpenAI 兼容协议）
// 用于前后端识别哪些模型应走 EYPA/AI 端点而非 DeepSeek 官方端点
define('EYPA_AI_MODELS', [
    'Echo-1.5-Flash',
    'Echo-1.5-Pro',
    'Echo-Image',
    'DeepSeek-V4-Flash',
    'DeepSeek-V4-Pro',
    'GLM-5.2',
    'MiniMax-M2.7',
    'MiniMax-M3',
]);

// ── DeepSeek 配置（备选 provider，保留以便回退） ──
define('DEEPSEEK_API_URL', 'https://api.deepseek.com');
define('DEEPSEEK_API_KEY', 'sk-xxxxxxxxxxxxxxxxxxxxxxxx'); // 留空则需要在网页中手动输入

// 默认模型（常量名沿用 DEEPSEEK_DEFAULT_MODEL 以兼容现有引用）：Echo-1.5-Pro 为主（多模态），Flash 为轻量备选
define('DEEPSEEK_DEFAULT_MODEL', 'Echo-1.5-Pro');
define('DEEPSEEK_DEFAULT_TEMPERATURE', 1.0); // 默认随机性：0.0-2.0
define('DEEPSEEK_DEFAULT_MAX_TOKENS', 2048); // 默认最大输出长度

// AI 客服系统配置
define('AI_CUSTOMER_SERVICE_ENABLED', true); // 是否启用 AI 客服
define('AI_KNOWLEDGE_BASE_ENABLED', true); // 是否启用知识库检索（RAG）
define('AI_SHOW_CONFIG_PANEL', false); // 是否在网页中显示配置面板（生产环境建议 false）

// ==================== AI 人格配置（客服 / 管理 完全分离）====================
//
// 设计原则（安全核心）：
// - customer（客服 AI，面向普通玩家）：仅允许只读 MCP 工具，服务端强制白名单，
//   即便管理员调用客服渠道也会被降级为只读，杜绝越权操作服务器。
// - admin（管理 AI，仅限管理后台）：拥有全部 MCP 工具，但写操作仍需管理员角色。
// 两个人格使用各自独立的 prompt 文件与知识库文件，互不干扰。
$GLOBALS['AI_PERSONAS'] = [
    'customer' => [
        'label'         => '客服 AI',
        'model'         => defined('DEEPSEEK_DEFAULT_MODEL') ? DEEPSEEK_DEFAULT_MODEL : 'Echo-1.5-Flash',
        'prompt_file'   => __DIR__ . '/../api/ai/prompt_customer.md',
        'kb_file'       => __DIR__ . '/../api/ai/knowledge_base.md',
        // 服务端强制只读白名单（MCP 工具名）。null 表示全部（仅 admin）。
        'allowed_tools' => [
            'get_dashboard',
            'list_instances',
            'get_instance_detail',
            'list_announcements',
            'get_announcement',
            'query_my_orders',
            'player_recharge_summary',
            'troubleshoot_order',
        ],
        'allow_mcp'     => true,
        'skills'        => ['server_status', 'server_version', 'player_count'],
    ],
    'admin' => [
        'label'         => '管理 AI',
        'model'         => 'Echo-1.5-Pro',
        'prompt_file'   => __DIR__ . '/../api/ai/prompt_admin.md',
        'kb_file'       => __DIR__ . '/../api/ai/knowledge_base_admin.md',
        'allowed_tools' => null, // 全部工具（写操作仍需管理员角色）
        'allow_mcp'     => true,
        'skills'        => ['server_status', 'server_version', 'player_count'],
    ],
];

/**
 * 获取指定人格的配置
 */
function getAiPersonaConfig(string $persona): array
{
    $map = $GLOBALS['AI_PERSONAS'] ?? [];
    return $map[$persona] ?? $map['customer'] ?? [];
}

/**
 * 获取指定人格允许使用的 MCP 工具白名单；返回 null 表示允许全部
 */
function getAiAllowedTools(string $persona): ?array
{
    $cfg = getAiPersonaConfig($persona);
    return $cfg['allowed_tools'] ?? null;
}

// ==================== 辅助函数 ====================

// 设置 CORS 头（安全版：仅反射白名单内的来源，绝不回退到通配符 *）
function set_cors_headers() {
    $allowedOrigins = array_filter(array_map('trim', explode(',', CORS_ALLOW_ORIGIN)));
    $requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';

    // 仅当请求来源在白名单内时才反射该来源；否则回退到生产域名（浏览器会因源不匹配而拦截）
    if ($requestOrigin !== '' && in_array($requestOrigin, $allowedOrigins, true)) {
        header('Access-Control-Allow-Origin: ' . $requestOrigin);
    } else {
        header('Access-Control-Allow-Origin: ' . ($allowedOrigins[0] ?? 'https://mcpc.goldenapplepie.xyz'));
    }

    header('Access-Control-Allow-Methods: ' . CORS_ALLOW_METHODS);
    header('Access-Control-Allow-Headers: ' . CORS_ALLOW_HEADERS);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');

    // 处理 OPTIONS 预检请求
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

// 设置安全头
function set_security_headers() {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    
    if (IS_HTTPS) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// 获取数据库连接配置数组（用于向后兼容）
function get_db_config() {
    return [
        'hostname' => DB_HOST,
        'port' => DB_PORT,
        'database' => DB_NAME,
        'username' => DB_USER,
        'password' => DB_PASS
    ];
}

// 获取爱发电配置数组（用于向后兼容）
function get_afdian_config() {
    return [
        'user_id' => AFDIAN_USER_ID,
        'api_token' => AFDIAN_API_TOKEN,
        'order_update_mode' => AFDIAN_ORDER_UPDATE_MODE,
        'auto_cron' => [
            'enabled' => AFDIAN_CRON_ENABLED,
            'interval' => AFDIAN_CRON_INTERVAL,
            'max_execution_time' => AFDIAN_CRON_MAX_TIME,
            'log_level' => AFDIAN_CRON_LOG_LEVEL
        ],
        'plan_ids' => [
            'golden_ticket' => AFDIAN_PLAN_GOLDEN_TICKET,
            'vip_month' => AFDIAN_PLAN_VIP_MONTH,
            'vip_year' => AFDIAN_PLAN_VIP_YEAR
        ],
        'webhook' => [
            'verify_sign' => AFDIAN_WEBHOOK_VERIFY_SIGN
        ],
        'api_secret' => AFDIAN_API_SECRET,
        'logger' => [
            'log_dir' => AFDIAN_LOG_DIR,
            'log_level' => AFDIAN_LOG_LEVEL,
            'module' => AFDIAN_LOG_MODULE
        ]
    ];
}

// ==================== 初始化函数 ====================

// 加载数据目录自动初始化引擎（由 git 跟踪，详见 includes/data-init.php）
require_once __DIR__ . '/../includes/data-init.php';

// 初始化必要的目录和文件（委托给 tracked 版本的 ensure_data_initialized）
function init_config() {
    static $initialized = false;
    if ($initialized) { return; }

    // data/ 目录及所有子目录/文件，从 data-init/ 模板自动初始化（幂等、不覆盖已有数据）
    ensure_data_initialized();

    // 确保日志目录存在（独立于 data/，不归 data-init 管理）
    if (!file_exists(AFDIAN_LOG_DIR)) {
        mkdir(AFDIAN_LOG_DIR, 0755, true);
    }

    $initialized = true;
}

// 自动初始化（仅在 Web 环境下）
if (php_sapi_name() !== 'cli') {
    init_config();
}
?>
