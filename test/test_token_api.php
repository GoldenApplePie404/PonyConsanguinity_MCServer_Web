<?php
// 测试数据访问令牌的API端点

// 包含secure_data.php
require_once '../api/secure_data.php';

// 设置响应头
header('Content-Type: application/json');

// 测试需要令牌的操作
function testProtectedOperation() {
    // 验证数据访问权限（需要令牌）
    verifyDataAccess(true);
    
    // 如果验证通过，返回成功信息
    echo json_encode([
        'success' => true,
        'message' => '成功访问受保护的资源',
        'token_valid' => true
    ]);
}

// 测试不需要令牌的操作
function testPublicOperation() {
    // 验证数据访问权限（不需要令牌）
    verifyDataAccess(false);
    
    // 如果验证通过，返回成功信息
    echo json_encode([
        'success' => true,
        'message' => '成功访问公开资源',
        'token_valid' => true
    ]);
}

// 获取操作类型
$action = $_GET['action'] ?? 'protected';

// 执行相应的操作
if ($action === 'public') {
    testPublicOperation();
} else {
    testProtectedOperation();
}