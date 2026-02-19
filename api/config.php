<?php
// MCSManager API 配置
define('MCSM_API_URL', 'https://xxx.xxxxx.xx/api'); // MCSManager API 地址
define('MCSM_API_KEY', 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'); // API 密钥

// 服务器状态 API 配置
define('MCSTATUS_API_URL', 'http://mcstatus.goldenapplepie.xyz/api'); // 服务器状态 API 地址
define('MC_SERVER_IP', 'xx.xxxxx.xx'); // 服务器 IP 地址
define('MC_SERVER_PORT', 25565); // 服务器端口

// 确保数据目录存在
if (!file_exists(dirname(__DIR__) . '/data')) {
    mkdir(dirname(__DIR__) . '/data', 0755, true);
}

// 定义访问常量
define('ACCESS_ALLOWED', true);

// 会话配置
define('MAX_SESSIONS', 10);

// 数据文件路径配置
define('USERS_FILE', dirname(__DIR__) . '/data/users.php');
define('SESSIONS_FILE', dirname(__DIR__) . '/data/sessions.php');
define('POSTS_FILE', dirname(__DIR__) . '/data/posts.php');
define('NOTIFICATIONS_FILE', dirname(__DIR__) . '/data/notifications.php');
define('CONTENT_DIR', dirname(__DIR__) . '/data/content');
define('REPLIES_DIR', dirname(__DIR__) . '/data/replies');

// 确保content目录存在
if (!file_exists(CONTENT_DIR)) {
    mkdir(CONTENT_DIR, 0755, true);
}

// 确保replies目录存在
if (!file_exists(REPLIES_DIR)) {
    mkdir(REPLIES_DIR, 0755, true);
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
?>
