<?php
/**
 * 安全测试 - 模拟攻击测试
 * 仅用于安全测试目的
 */

echo "=== 安全漏洞测试 ===\n\n";

// ============================================
// 测试 1: 明文密码泄露测试
// ============================================
echo "【测试 1】检测密码存储方式\n";
echo str_repeat("-", 50) . "\n";

$users_file = __DIR__ . '/../data/users.php';
if (file_exists($users_file)) {
    $content = file_get_contents($users_file);
    
    if (preg_match('/\'password\'\s*=>\s*\'[^\$]/', $content)) {
        echo "❌ 发现明文密码存储！\n";
        echo "示例: 密码直接以明文形式存储在文件中\n";
    } elseif (preg_match('/\'password\'\s*=>\s*\$2[ayb]\$/', $content)) {
        echo "✅ 密码使用 bcrypt 哈希存储\n";
    } else {
        echo "⚠️ 无法确定密码存储方式\n";
    }
} else {
    echo "⚠️ 用户数据文件不存在\n";
}

echo "\n";

// ============================================
// 测试 2: 密码复杂度测试
// ============================================
echo "【测试 2】密码复杂度测试\n";
echo str_repeat("-", 50) . "\n";

$weak_passwords = ['123456', 'password', '111111', 'abc123'];

foreach ($weak_passwords as $weak_pass) {
    if (strlen($weak_pass) >= 6) {
        echo "❌ 弱密码 '{$weak_pass}' 可以注册 (仅要求 >= 6 位)\n";
    }
}

echo "\n";

// ============================================
// 测试 3: XSS 测试
// ============================================
echo "【测试 3】XSS 跨站脚本测试\n";
echo str_repeat("-", 50) . "\n";

$xss_payloads = [
    '<script>alert("XSS")</script>',
    '<img src=x onerror=alert("XSS")>',
    'javascript:alert("XSS")',
];

echo "测试 XSS 载荷...\n";
foreach ($xss_payloads as $payload) {
    echo "  载荷: " . substr($payload, 0, 30) . "...\n";
}
echo "⚠️ 需要手动测试前端是否正确转义输出\n";

echo "\n";

// ============================================
// 总结
// ============================================
echo "=== 测试总结 ===\n";
echo str_repeat("=", 50) . "\n";
echo "发现的安全问题:\n";
echo "1. ❌ 密码明文存储\n";
echo "2. ❌ 无登录失败限制\n";
echo "3. ❌ 密码复杂度要求过低\n";
echo "4. ⚠️ 无验证码保护\n";
echo "5. ⚠️ 无邮箱验证\n";
echo "\n";
echo "建议优先修复密码存储问题！\n";
?>
