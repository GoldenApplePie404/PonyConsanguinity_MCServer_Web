<?php
// 测试 PHP 扩展是否加载
$extensions = [
    'openssl' => 'OpenSSL (邮件发送必需）',
    'mbstring' => 'Multibyte String (多字节字符串处理）',
    'json' => 'JSON (JSON 处理）',
    'session' => 'Session (会话管理）',
    'pdo' => 'PDO (数据库连接）'
];

echo "<h2>PHP 扩展检查</h2>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>扩展名</th><th>状态</th><th>说明</th></tr>";

$allLoaded = true;
foreach ($extensions as $ext => $desc) {
    $loaded = extension_loaded($ext);
    $status = $loaded ? '<span style="color: green;">✓ 已加载</span>' : '<span style="color: red;">✗ 未加载</span>';
    echo "<tr><td>$ext</td><td>$status</td><td>$desc</td></tr>";
    if (!$loaded) {
        $allLoaded = false;
    }
}

echo "</table>";

if ($allLoaded) {
    echo "<p style='color: green; font-weight: bold;'>所有必需的扩展都已加载！</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>部分扩展未加载，请启用后重启 Apache</p>";
}

echo "<h3>PHP 版本信息</h3>";
echo "<p>PHP 版本: " . PHP_VERSION . "</p>";
echo "<p>配置文件位置: " . php_ini_loaded_file() . "</p>";
?>
