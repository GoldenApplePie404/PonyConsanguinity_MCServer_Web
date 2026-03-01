<?php
/**
 * 权限提升测试工具
 * 测试是否存在越权访问漏洞
 */

echo "=== 权限提升漏洞测试 ===\n\n";

$api_base = "http://localhost/api";

// 模拟用户令牌（需要实际获取）
$normal_user_token = "test_normal_token";
$admin_token = "test_admin_token";

// 测试场景
$tests = [
    [
        'name' => '垂直越权 - 普通用户访问管理功能',
        'description' => '普通用户尝试访问管理员功能',
        'endpoints' => [
            '/delete_account.php',
            '/announcement.php',
            '/send_notification.php',
        ]
    ],
    [
        'name' => '水平越权 - 访问其他用户数据',
        'description' => '用户A尝试访问用户B的数据',
        'endpoints' => [
            '/user_info.php?username=other_user',
            '/post.php?action=delete&id=other_post',
        ]
    ],
    [
        'name' => '未授权访问',
        'description' => '无令牌访问需要认证的接口',
        'endpoints' => [
            '/user_info.php',
            '/notification.php',
            '/forum.php',
        ]
    ],
];

$vulnerabilities = [];

foreach ($tests as $test) {
    echo "【{$test['name']}】\n";
    echo "描述: {$test['description']}\n";
    echo str_repeat("-", 50) . "\n";
    
    foreach ($test['endpoints'] as $endpoint) {
        // 测试无认证访问
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_base . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "  测试: {$endpoint}\n";
        echo "    HTTP状态: {$http_code}\n";
        
        // 检查是否返回了敏感数据
        if ($http_code == 200) {
            $data = json_decode($response, true);
            if ($data && isset($data['success']) && $data['success']) {
                $vulnerabilities[] = [
                    'test' => $test['name'],
                    'endpoint' => $endpoint,
                    'issue' => '未授权访问成功',
                    'http_code' => $http_code
                ];
                echo "    ❌ 漏洞! 未授权访问成功\n";
            } else {
                echo "    ✓ 需要认证\n";
            }
        } elseif ($http_code == 401 || $http_code == 403) {
            echo "    ✓ 正确拒绝访问\n";
        } else {
            echo "    ⚠️ 异常响应\n";
        }
        
        echo "\n";
    }
}

// 测试角色伪造
echo "【角色伪造测试】\n";
echo str_repeat("-", 50) . "\n";

echo "尝试修改用户角色...\n";

// 模拟请求修改角色
$role_test_data = [
    'action' => 'update_role',
    'username' => 'test_user',
    'role' => 'admin'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_base . '/user_info.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($role_test_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code == 200) {
    echo "  ❌ 严重漏洞! 可以修改用户角色\n";
    $vulnerabilities[] = [
        'test' => '角色伪造',
        'endpoint' => '/user_info.php',
        'issue' => '可以修改用户角色为管理员',
        'http_code' => $http_code
    ];
} else {
    echo "  ✓ 无法修改用户角色\n";
}

// 测试会话固定
echo "\n【会话安全测试】\n";
echo str_repeat("-", 50) . "\n";

echo "检查会话令牌强度...\n";

// 模拟获取令牌
$tokens = [
    'simple_token_123',
    '123456',
    'admin',
    bin2hex(random_bytes(32)), // 强令牌示例
];

foreach ($tokens as $token) {
    $strength = '弱';
    if (strlen($token) >= 32 && ctype_xdigit($token)) {
        $strength = '强';
    } elseif (strlen($token) >= 16) {
        $strength = '中';
    }
    
    echo "  令牌: " . substr($token, 0, 20) . "... - 强度: {$strength}\n";
}

// 总结
echo "\n" . str_repeat("=", 50) . "\n";
echo "测试总结\n";
echo str_repeat("=", 50) . "\n";

if (empty($vulnerabilities)) {
    echo "✅ 未发现权限提升漏洞\n";
} else {
    echo "⚠️ 发现 " . count($vulnerabilities) . " 个权限漏洞:\n\n";
    foreach ($vulnerabilities as $vuln) {
        echo "- 测试: {$vuln['test']}\n";
        echo "  端点: {$vuln['endpoint']}\n";
        echo "  问题: {$vuln['issue']}\n\n";
    }
}

echo "\n防护建议:\n";
echo "1. 每个请求都验证用户身份和权限\n";
echo "2. 使用强随机令牌 (至少32字节)\n";
echo "3. 实施最小权限原则\n";
echo "4. 记录敏感操作日志\n";
echo "5. 关键操作需要二次验证\n";
?>
