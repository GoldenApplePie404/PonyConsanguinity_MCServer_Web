<?php
/**
 * XSS跨站脚本攻击测试工具
 * 测试是否存在XSS漏洞
 */

echo "=== XSS跨站脚本攻击测试 ===\n\n";

$api_base = "http://localhost/api";

// XSS载荷列表
$xss_payloads = [
    // 基础XSS
    "<script>alert('XSS')</script>",
    "<img src=x onerror=alert('XSS')>",
    "<svg onload=alert('XSS')>",
    "<body onload=alert('XSS')>",
    "<iframe src='javascript:alert(\"XSS\")'>",
    
    // 绕过过滤
    "<ScRiPt>alert('XSS')</sCrIpT>",
    "<img src=x onerror=&#97;&#108;&#101;&#114;&#116;&#40;&#39;&#88;&#83;&#83;&#39;&#41;>",
    "<img src=x onerror=eval(atob('YWxlcnQoJ1hTUycp'))>",
    
    // 事件处理器
    "<div onmouseover='alert(\"XSS\")'>hover me</div>",
    "<input onfocus=alert('XSS') autofocus>",
    "<marquee onstart=alert('XSS')>",
    
    // JavaScript伪协议
    "javascript:alert('XSS')",
    "data:text/html,<script>alert('XSS')</script>",
    
    // HTML5 XSS
    "<video><source onerror='alert(\"XSS\")'>",
    "<audio src=x onerror=alert('XSS')>",
    "<details open ontoggle=alert('XSS')>",
    
    // 编码绕过
    "%3Cscript%3Ealert('XSS')%3C/script%3E",
    "&#60;script&#62;alert('XSS')&#60;/script&#62;",
    
    // 模板注入
    "{{constructor.constructor('alert(1)')()}}",
    "${alert('XSS')}",
];

// 测试输入点
$test_points = [
    ['name' => '用户名注册', 'url' => '/register.php', 'method' => 'POST', 'field' => 'username'],
    ['name' => '邮箱注册', 'url' => '/register.php', 'method' => 'POST', 'field' => 'email'],
    ['name' => '帖子标题', 'url' => '/forum.php', 'method' => 'POST', 'field' => 'title'],
    ['name' => '帖子内容', 'url' => '/forum.php', 'method' => 'POST', 'field' => 'content'],
    ['name' => '回复内容', 'url' => '/forum.php', 'method' => 'POST', 'field' => 'reply'],
];

$vulnerabilities = [];

echo "开始测试...\n\n";

foreach ($test_points as $point) {
    echo "【{$point['name']}】\n";
    echo str_repeat("-", 40) . "\n";
    
    // 随机选择几个载荷测试
    $test_payloads = array_rand(array_flip($xss_payloads), 3);
    
    foreach ($test_payloads as $payload) {
        $test_data = [
            $point['field'] => $xss_payloads[$payload],
            'password' => 'test123456',
            'username' => 'test_' . time(),
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_base . $point['url']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($test_data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // 检查响应中是否包含未转义的XSS载荷
        if (strpos($response, $xss_payloads[$payload]) !== false) {
            $vulnerabilities[] = [
                'point' => $point['name'],
                'payload' => $xss_payloads[$payload],
                'response' => substr($response, 0, 100)
            ];
            
            echo "  ⚠️ 发现XSS漏洞!\n";
            echo "    载荷: " . substr($xss_payloads[$payload], 0, 40) . "\n";
        } else {
            echo "  ✓ 载荷已过滤: " . substr($xss_payloads[$payload], 0, 30) . "...\n";
        }
    }
    
    echo "\n";
}

// 测试存储型XSS - 检查数据文件
echo "【存储型XSS检查】\n";
echo str_repeat("-", 40) . "\n";

$data_files = [
    '../data/users.php',
    '../data/posts.json',
    '../data/replies/',
];

foreach ($data_files as $file) {
    $full_path = __DIR__ . '/' . $file;
    if (file_exists($full_path)) {
        $content = file_get_contents($full_path);
        
        // 检查是否包含未转义的HTML标签
        if (preg_match('/<script|onerror=|onload=|javascript:/i', $content)) {
            echo "  ⚠️ 在 {$file} 中发现可疑内容\n";
        } else {
            echo "  ✓ {$file} 安全\n";
        }
    }
}

// 总结
echo "\n" . str_repeat("=", 50) . "\n";
echo "测试总结\n";
echo str_repeat("=", 50) . "\n";

if (empty($vulnerabilities)) {
    echo "✅ 未发现明显的XSS漏洞\n";
} else {
    echo "⚠️ 发现 " . count($vulnerabilities) . " 个XSS漏洞:\n\n";
    foreach ($vulnerabilities as $vuln) {
        echo "- 位置: {$vuln['point']}\n";
        echo "  载荷: {$vuln['payload']}\n\n";
    }
}

echo "\n防护建议:\n";
echo "1. 使用 htmlspecialchars() 转义输出\n";
echo "2. 使用 Content-Security-Policy 头部\n";
echo "3. 设置 HttpOnly 和 Secure Cookie 标志\n";
echo "4. 输入验证和白名单过滤\n";
echo "5. 使用模板引擎自动转义\n";
?>
