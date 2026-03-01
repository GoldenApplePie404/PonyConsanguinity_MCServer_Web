<?php
/**
 * 密码迁移脚本
 * 将明文密码转换为 bcrypt 哈希
 */

define('ACCESS_ALLOWED', true);

$users_file = __DIR__ . '/../data/users.php';

echo "=== 密码迁移脚本 ===\n\n";

if (!file_exists($users_file)) {
    echo "❌ 用户数据文件不存在\n";
    exit(1);
}

$users = include $users_file;

if (empty($users)) {
    echo "没有用户数据需要迁移\n";
    exit(0);
}

$migrated = 0;
$skipped = 0;

foreach ($users as $username => &$user) {
    $old_password = $user['password'];
    
    // 检查是否已经是 bcrypt 哈希 (以 $2y$ 开头)
    if (substr($old_password, 0, 4) === '$2y$') {
        echo "跳过 {$username} - 已是哈希密码\n";
        $skipped++;
        continue;
    }
    
    // 转换为 bcrypt 哈希
    $user['password'] = password_hash($old_password, PASSWORD_DEFAULT);
    
    // 添加新字段
    if (!isset($user['login_attempts'])) {
        $user['login_attempts'] = 0;
    }
    if (!isset($user['lock_until'])) {
        $user['lock_until'] = null;
    }
    
    echo "✓ 迁移 {$username}\n";
    echo "  原密码: {$old_password}\n";
    echo "  新哈希: " . substr($user['password'], 0, 30) . "...\n\n";
    
    $migrated++;
}

// 保存数据
if ($migrated > 0) {
    $content = "<?php\n";
    $content .= "if (!defined('ACCESS_ALLOWED')) {\n";
    $content .= "    header('HTTP/1.1 403 Forbidden');\n";
    $content .= "    exit;\n";
    $content .= "}\n\n";
    $content .= "return " . var_export($users, true) . ";\n";
    $content .= "?>";
    
    if (file_put_contents($users_file, $content)) {
        echo str_repeat("=", 50) . "\n";
        echo "迁移完成！\n";
        echo "- 已迁移: {$migrated} 个用户\n";
        echo "- 已跳过: {$skipped} 个用户\n";
        echo "\n⚠️ 请立即删除此脚本文件！\n";
    } else {
        echo "❌ 保存失败！\n";
        exit(1);
    }
} else {
    echo "没有需要迁移的用户\n";
}
?>
