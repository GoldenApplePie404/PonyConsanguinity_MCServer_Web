<?php
if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}

// ==================== 数据库配置 ====================
define('DB_HOST', 'your_database_host');
define('DB_PORT', 3306);
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_username');
define('DB_PASS', 'your_database_password');

// ==================== MCSManager API 配置 ====================
define('MCSM_API_URL', 'https://your-mcsm-panel.com/mcs/api');
define('MCSM_API_KEY', 'your_mcsm_api_key');

// ==================== 服务器状态 API 配置 ====================
define('MCSTATUS_API_URL', 'http://your-mcstatus-api.com/api');
define('MC_SERVER_IP', 'your.minecraft.server.com');
define('MC_SERVER_PORT', 25565);

// ==================== 服务器状态缓存配置 ====================
define('MCSTATUS_CACHE_FILE', dirname(__DIR__) . '/data/mcstatus_cache.json');
define('MCSTATUS_CACHE_TIME', 60);
define('MCSTATUS_MAX_RETRIES', 2);

// ==================== HTTPS 配置 ====================
define('IS_HTTPS', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443));

// ==================== CORS 配置 ====================
define('CORS_ALLOW_ORIGIN', '*');
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

// ==================== 爱发电 API 配置 ====================
define('AFDIAN_USER_ID', 'your_afdian_user_id');
define('AFDIAN_API_TOKEN', 'your_afdian_api_token');

// ==================== 爱发电订单更新模式配置 ====================
// 可选值: 'api' (纯API模式), 'webhook' (仅webhook模式), 'all' (webhook为主，API为辅)
define('AFDIAN_ORDER_UPDATE_MODE', 'all');

// ==================== 爱发电自动定时任务配置 ====================
define('AFDIAN_CRON_ENABLED', true);
define('AFDIAN_CRON_INTERVAL', 120);
define('AFDIAN_CRON_MAX_TIME', 300);
define('AFDIAN_CRON_LOG_LEVEL', 'info');

// ==================== 爱发电方案/商品ID配置 ====================
define('AFDIAN_PLAN_GOLDEN_TICKET', 'your_golden_ticket_plan_id');
define('AFDIAN_PLAN_VIP_MONTH', 'your_vip_month_plan_id');
define('AFDIAN_PLAN_VIP_YEAR', 'your_vip_year_plan_id');

// ==================== 爱发电 Webhook 配置 ====================
define('AFDIAN_WEBHOOK_VERIFY_SIGN', true);

// ==================== 爱发电日志配置 ====================
define('AFDIAN_LOG_DIR', dirname(__DIR__) . '/logs');
define('AFDIAN_LOG_LEVEL', 'info');
define('AFDIAN_LOG_MODULE', 'aifadian');

// ==================== 邮件 SMTP 配置 ====================
// 是否启用邮箱验证功能
define('EMAIL_VERIFICATION_ENABLED', true);

// SMTP 服务器配置
define('SMTP_HOST', 'smtp.qq.com');           // SMTP服务器地址
define('SMTP_PORT', 465);                      // SMTP端口（SSL: 465, TLS: 587, 非加密: 25）
define('SMTP_USERNAME', 'your-email@qq.com');  // 发件人邮箱
define('SMTP_PASSWORD', 'your-auth-code');     // 邮箱授权码（不是登录密码）
define('SMTP_ENCRYPTION', 'ssl');              // 加密方式：ssl、tls 或空字符串（非加密）
define('SMTP_AUTH', true);                     // 是否启用SMTP认证

// 发件人信息
define('MAIL_FROM_EMAIL', 'your-email@qq.com');
define('MAIL_FROM_NAME', '万驹同源服务器');

// 验证邮件配置
define('VERIFY_TOKEN_EXPIRY', 86400);          // 验证令牌有效期（秒）默认24小时
define('VERIFY_RESEND_INTERVAL', 600);         // 重新发送间隔（秒）默认10分钟
define('VERIFY_MAX_RESEND', 3);                // 每小时最大重发次数

// ==================== 辅助函数 ====================

// 设置 CORS 头
function set_cors_headers() {
    header('Access-Control-Allow-Origin: ' . CORS_ALLOW_ORIGIN);
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
        'logger' => [
            'log_dir' => AFDIAN_LOG_DIR,
            'log_level' => AFDIAN_LOG_LEVEL,
            'module' => AFDIAN_LOG_MODULE
        ]
    ];
}

// 获取邮箱验证配置数组（用于向后兼容）
function get_email_verification_config() {
    return [
        'enabled' => EMAIL_VERIFICATION_ENABLED,
        'smtp' => [
            'host' => SMTP_HOST,
            'port' => SMTP_PORT,
            'username' => SMTP_USERNAME,
            'password' => SMTP_PASSWORD,
            'encryption' => SMTP_ENCRYPTION,
            'auth' => SMTP_AUTH
        ],
        'from' => [
            'email' => MAIL_FROM_EMAIL,
            'name' => MAIL_FROM_NAME
        ],
        'verification' => [
            'token_expiry' => VERIFY_TOKEN_EXPIRY,
            'resend_interval' => VERIFY_RESEND_INTERVAL,
            'max_resend' => VERIFY_MAX_RESEND
        ]
    ];
}

// ==================== 初始化函数 ====================

// 初始化必要的目录和文件
function init_config() {
    static $initialized = false;
    
    if ($initialized) {
        return;
    }
    
    // 确保数据目录存在
    $dataDir = dirname(__DIR__) . '/data';
    if (!file_exists($dataDir)) {
        mkdir($dataDir, 0755, true);
    }
    
    // 确保content目录存在
    if (!file_exists(CONTENT_DIR)) {
        mkdir(CONTENT_DIR, 0755, true);
    }
    
    // 确保replies目录存在
    if (!file_exists(REPLIES_DIR)) {
        mkdir(REPLIES_DIR, 0755, true);
    }
    
    // 确保日志目录存在
    if (!file_exists(AFDIAN_LOG_DIR)) {
        mkdir(AFDIAN_LOG_DIR, 0755, true);
    }
    
    // 确保帖子文件存在
    if (!file_exists(POSTS_FILE)) {
        $content = "<?php\n";
        $content .= "if (!defined('ACCESS_ALLOWED')) {\n";
        $content .= "    header('HTTP/1.1 403 Forbidden');\n";
        $content .= "    exit;\n";
        $content .= "}\n\n";
        $content .= "return ['posts' => []];\n";
        $content .= "?>";
        file_put_contents(POSTS_FILE, $content);
    }
    
    // 确保用户文件存在
    if (!file_exists(USERS_FILE)) {
        $content = "<?php\n";
        $content .= "if (!defined('ACCESS_ALLOWED')) {\n";
        $content .= "    header('HTTP/1.1 403 Forbidden');\n";
        $content .= "    exit;\n";
        $content .= "}\n\n";
        $content .= "return [];\n";
        $content .= "?>";
        file_put_contents(USERS_FILE, $content);
    }
    
    // 确保会话文件存在
    if (!file_exists(SESSIONS_FILE)) {
        $content = "<?php\n";
        $content .= "if (!defined('ACCESS_ALLOWED')) {\n";
        $content .= "    header('HTTP/1.1 403 Forbidden');\n";
        $content .= "    exit;\n";
        $content .= "}\n\n";
        $content .= "return [];\n";
        $content .= "?>";
        file_put_contents(SESSIONS_FILE, $content);
    }
    
    // 确保通知文件存在
    if (!file_exists(NOTIFICATIONS_FILE)) {
        $content = "<?php\n";
        $content .= "if (!defined('ACCESS_ALLOWED')) {\n";
        $content .= "    header('HTTP/1.1 403 Forbidden');\n";
        $content .= "    exit;\n";
        $content .= "}\n\n";
        $content .= "return [];\n";
        $content .= "?>";
        file_put_contents(NOTIFICATIONS_FILE, $content);
    }
    
    $initialized = true;
}

// 自动初始化（仅在 Web 环境下）
if (php_sapi_name() !== 'cli') {
    init_config();
}
?>
