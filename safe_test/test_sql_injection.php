<?php
/**
 * SQL注入测试工具
 * 测试API端点是否存在SQL注入漏洞
 */

echo "=== SQL注入漏洞测试 ===\n\n";

$api_base = "http://localhost/api";

// SQL注入载荷列表
$payloads = [
    // 基础注入
    "' OR '1'='1",
    "' OR '1'='1'--",
    "' OR '1'='1'/*",
    "' OR 1=1--",
    "' OR 1=1/*",
    "1' OR '1'='1",
    "admin'--",
    "admin'#",
    
    // 联合查询注入
    "' UNION SELECT NULL--",
    "' UNION SELECT NULL, NULL--",
    "' UNION SELECT NULL, NULL, NULL--",
    "' UNION SELECT username, password FROM users--",
    
    // 布尔盲注
    "' AND 1=1--",
    "' AND 1=2--",
    "' AND '1'='1",
    "' AND '1'='2",
    
    // 时间盲注
    "'; WAITFOR DELAY '0:0:5'--",
    "'; SLEEP(5)--",
    "' AND SLEEP(5)--",
    
    // 堆叠查询
    "'; DROP TABLE users--",
    "'; INSERT INTO users VALUES ('hacker', 'password')--",
    
    // XSS组合
    "'\"><script>alert('XSS')</script>",
    "'\"><img src=x onerror=alert('XSS')>",
];

// 测试端点
$endpoints = [
    ['url' => '/login.php', 'method' => 'POST', 'params' => ['username', 'password']],
    ['url' => '/register.php', 'method' => 'POST', 'params' => ['username', 'password', 'email']],
    ['url' => '/user_info.php', 'method' => 'GET', 'params' => ['username']],
];

$vulnerabilities = [];

foreach ($endpoints as $endpoint) {
    echo "测试端点: {$endpoint['url']}\n";
    echo str_repeat("-", 50) . "\n";
    
    foreach ($endpoint['params'] as $param) {
        echo "  参数: {$param}\n";
        
        foreach (array_slice($payloads, 0, 5) as $payload) { // 只测试前5个
            $test_data = [];
            foreach ($endpoint['params'] as $p) {
                $test_data[$p] = ($p === $param) ? $payload : 'test';
            }
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $api_base . $endpoint['url']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            
            if ($endpoint['method'] === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($test_data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            } else {
                curl_setopt($ch, CURLOPT_URL, $api_base . $endpoint['url'] . '?' . http_build_query($test_data));
            }
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            // 检测异常响应
            $is_vulnerable = false;
            
            // 检查SQL错误信息泄露
            if (preg_match('/(sql|syntax|mysql|query|database|sqlite)/i', $response)) {
                $is_vulnerable = true;
                $reason = "SQL错误信息泄露";
            }
            
            // 检查异常HTTP状态码
            if ($http_code >= 500) {
                $is_vulnerable = true;
                $reason = "服务器错误 (HTTP {$http_code})";
            }
            
            if ($is_vulnerable) {
                $vulnerabilities[] = [
                    'endpoint' => $endpoint['url'],
                    'param' => $param,
                    'payload' => $payload,
                    'reason' => $reason ?? '未知'
                ];
                
                echo "    ⚠️ 可能存在漏洞!\n";
                echo "      载荷: " . substr($payload, 0, 30) . "\n";
                echo "      原因: {$reason}\n";
            }
        }
    }
    
    echo "\n";
}

// 总结
echo str_repeat("=", 50) . "\n";
echo "测试总结\n";
echo str_repeat("=", 50) . "\n";

if (empty($vulnerabilities)) {
    echo "✅ 未发现明显的SQL注入漏洞\n";
    echo "\n注意: 项目使用文件存储而非数据库，因此不存在传统SQL注入风险\n";
} else {
    echo "⚠️ 发现 " . count($vulnerabilities) . " 个可疑漏洞:\n\n";
    foreach ($vulnerabilities as $vuln) {
        echo "- 端点: {$vuln['endpoint']}\n";
        echo "  参数: {$vuln['param']}\n";
        echo "  载荷: {$vuln['payload']}\n";
        echo "  原因: {$vuln['reason']}\n\n";
    }
}

echo "\n建议:\n";
echo "1. 使用预处理语句（如果使用数据库）\n";
echo "2. 对所有用户输入进行过滤\n";
echo "3. 使用参数化查询\n";
echo "4. 限制错误信息显示\n";
?>
