<?php
// 测试数据访问令牌功能

// 包含secure_data.php
require_once 'api/secure_data.php';

// 测试函数
function testScenario($name, $token, $expectedResult) {
    echo "=== $name ===\n";
    echo "预期结果：$expectedResult\n";
    
    // 清除之前的令牌
    unset($_GET['token']);
    unset($_SERVER['HTTP_X_DATA_TOKEN']);
    
    // 设置令牌
    if ($token !== null) {
        $_GET['token'] = $token;
    }
    
    // 捕获输出
    ob_start();
    
    // 尝试调用验证函数
    $success = false;
    try {
        verifyDataAccess(true);
        $success = true;
    } catch (Exception $e) {
        // 忽略异常
    }
    
    $output = ob_get_clean();
    
    // 检查结果
    if ($success && strpos($output, '403 Forbidden') === false) {
        echo "实际结果：成功访问\n";
    } else {
        echo "实际结果：访问被拒绝 (403 Forbidden)\n";
    }
    
    echo "\n";
}

// 测试场景

echo "=== 数据访问令牌功能测试 ===\n\n";

// 测试场景1：不提供令牌
testScenario("测试场景1：不提供令牌", null, "访问被拒绝");

// 测试场景2：提供正确的令牌
testScenario("测试场景2：提供正确的令牌", '8f42a73e6b9f4c8d9e2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e', "成功访问");

// 测试场景3：提供错误的令牌
testScenario("测试场景3：提供错误的令牌", 'wrong-token-12345', "访问被拒绝");

// 测试场景4：通过HTTP头提供令牌
echo "=== 测试场景4：通过HTTP头提供令牌 ===\n";
echo "预期结果：成功访问\n";

// 清除之前的令牌
unset($_GET['token']);
unset($_SERVER['HTTP_X_DATA_TOKEN']);

// 通过HTTP头设置令牌
$_SERVER['HTTP_X_DATA_TOKEN'] = '8f42a73e6b9f4c8d9e2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e';

// 捕获输出
ob_start();

$success = false;
try {
    verifyDataAccess(true);
    $success = true;
} catch (Exception $e) {
    // 忽略异常
}

$output = ob_get_clean();

if ($success && strpos($output, '403 Forbidden') === false) {
    echo "实际结果：成功访问\n";
} else {
    echo "实际结果：访问被拒绝 (403 Forbidden)\n";
}

echo "\n";

// 测试场景5：不需要令牌的访问
echo "=== 测试场景5：不需要令牌的访问 ===\n";
echo "预期结果：成功访问\n";

// 清除所有令牌
unset($_GET['token']);
unset($_SERVER['HTTP_X_DATA_TOKEN']);

$success = false;
try {
    verifyDataAccess(false);
    $success = true;
} catch (Exception $e) {
    // 忽略异常
}

if ($success) {
    echo "实际结果：成功访问\n";
} else {
    echo "实际结果：访问被拒绝\n";
}

echo "\n=== 测试完成 ===\n";