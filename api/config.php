<?php
// 引入统一配置文件
require_once dirname(__DIR__) . '/config/config.php';

// 引入辅助函数
require_once 'helper.php';

// 引入数据安全处理
require_once 'secure_data.php';

// ==================== 配置验证 ====================

// 验证必需的配置常量是否已定义
$required_constants = [
    'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS',
    'MCSM_API_URL', 'MCSM_API_KEY',
    'MCSTATUS_API_URL', 'MC_SERVER_IP', 'MC_SERVER_PORT',
    'MCSTATUS_CACHE_FILE', 'MCSTATUS_CACHE_TIME', 'MCSTATUS_MAX_RETRIES',
    'IS_HTTPS',
    'CORS_ALLOW_ORIGIN', 'CORS_ALLOW_METHODS', 'CORS_ALLOW_HEADERS',
    'MAX_SESSIONS',
    'USERS_FILE', 'SESSIONS_FILE', 'POSTS_FILE', 'NOTIFICATIONS_FILE',
    'CONTENT_DIR', 'REPLIES_DIR',
    'AFDIAN_USER_ID', 'AFDIAN_API_TOKEN',
    'AFDIAN_ORDER_UPDATE_MODE',
    'AFDIAN_CRON_ENABLED', 'AFDIAN_CRON_INTERVAL', 'AFDIAN_CRON_MAX_TIME', 'AFDIAN_CRON_LOG_LEVEL',
    'AFDIAN_PLAN_GOLDEN_TICKET', 'AFDIAN_PLAN_VIP_MONTH', 'AFDIAN_PLAN_VIP_YEAR',
    'AFDIAN_WEBHOOK_VERIFY_SIGN',
    'AFDIAN_LOG_DIR', 'AFDIAN_LOG_LEVEL', 'AFDIAN_LOG_MODULE'
];

foreach ($required_constants as $constant) {
    if (!defined($constant)) {
        error_log("必需的配置常量 {$constant} 未定义");
    }
}

// ==================== 配置完整性检查 ====================

// 检查数据目录是否可写
$required_dirs = [
    dirname(__DIR__) . '/data',
    CONTENT_DIR,
    REPLIES_DIR,
    AFDIAN_LOG_DIR
];

foreach ($required_dirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

// 检查缓存文件目录是否可写
$cacheDir = dirname(MCSTATUS_CACHE_FILE);
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}
?>
