<?php
/**
 * 积分商城 API 主接口
 * 处理所有与积分商城和背包相关的请求
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 处理 OPTIONS 预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 引入管理类
require_once __DIR__ . '/PointsManager.php';
require_once __DIR__ . '/ShopManager.php';

// 初始化响应
$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

try {
    // 获取请求方法
    $method = $_SERVER['REQUEST_METHOD'];
    
    // 获取用户 ID（从 session 或请求参数）
    $userId = $_GET['user_id'] ?? $_POST['user_id'] ?? null;
    
    // 如果没有用户 ID，尝试从 session 获取
    if (!$userId) {
        session_start();
        $userId = $_SESSION['user_id'] ?? null;
    }
    
    // 获取动作
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    // 初始化管理器
    $pointsManager = new PointsManager();
    $shopManager = new ShopManager();
    
    // 路由处理
    switch ($action) {
        // ========== 积分相关 ==========
        case 'get_points':
            // 获取用户积分
            if (!$userId) {
                throw new Exception('未登录');
            }
            $result = $pointsManager->getPoints($userId);
            $response = $result;
            break;
            
        case 'add_points':
            // 增加积分（需要管理员权限）
            if (!$userId) {
                throw new Exception('未登录');
            }
            $amount = intval($_POST['amount'] ?? 0);
            if ($amount <= 0) {
                throw new Exception('积分数量必须大于 0');
            }
            $result = $pointsManager->addPoints($userId, $amount);
            $response = $result;
            break;
            
        case 'reduce_points':
            // 减少积分
            if (!$userId) {
                throw new Exception('未登录');
            }
            $amount = intval($_POST['amount'] ?? 0);
            if ($amount <= 0) {
                throw new Exception('积分数量必须大于 0');
            }
            $result = $pointsManager->reducePoints($userId, $amount);
            $response = $result;
            break;
            
        // ========== 商品相关 ==========
        case 'get_items':
            // 获取商品列表
            $category = $_GET['category'] ?? 'all';
            $items = $shopManager->getAllItems($category, true);
            $response = [
                'success' => true,
                'data' => [
                    'items' => $items,
                    'categories' => $shopManager->getCategories()
                ]
            ];
            break;
            
        case 'get_item_detail':
            // 获取商品详情
            $itemId = $_GET['item_id'] ?? '';
            if (!$itemId) {
                throw new Exception('请指定商品 ID');
            }
            $item = $shopManager->getItem($itemId);
            if (!$item) {
                throw new Exception('商品不存在');
            }
            $response = [
                'success' => true,
                'data' => ['item' => $item]
            ];
            break;
            
        // ========== 兑换相关 ==========
        case 'exchange':
            // 兑换商品
            if (!$userId) {
                throw new Exception('未登录');
            }
            
            $itemId = $_POST['item_id'] ?? '';
            $quantity = intval($_POST['quantity'] ?? 1);
            
            if (!$itemId) {
                throw new Exception('请指定商品 ID');
            }
            
            if ($quantity <= 0) {
                throw new Exception('数量必须大于 0');
            }
            
            // 获取商品信息
            $item = $shopManager->getItem($itemId);
            if (!$item) {
                throw new Exception('商品不存在');
            }
            
            // 获取用户信息
            $userPoints = $pointsManager->getPoints($userId);
            if (!$userPoints['success']) {
                throw new Exception('获取用户信息失败');
            }
            
            // 检查购买资格
            $eligibility = $shopManager->checkPurchaseEligibility($item, $userPoints['level']);
            if (!$eligibility['can_purchase']) {
                throw new Exception($eligibility['reason']);
            }
            
            // 检查积分是否足够
            $totalCost = $item['price'] * $quantity;
            if ($userPoints['points'] < $totalCost) {
                throw new Exception('积分不足');
            }
            
            // 扣除积分
            $reduceResult = $pointsManager->reducePoints($userId, $totalCost);
            if (!$reduceResult['success']) {
                throw new Exception($reduceResult['error'] ?? '扣除积分失败');
            }
            
            // 添加物品到背包
            $addResult = $pointsManager->addItemToInventory(
                $userId,
                $item['item_id'],
                $item['item_name'],
                $item['category'] ?? 'default',
                $item['icon'] ?? '',
                $quantity
            );
            
            if (!$addResult['success']) {
                // 如果添加物品失败，退回积分
                $pointsManager->addPoints($userId, $totalCost);
                throw new Exception($addResult['error'] ?? '添加物品失败');
            }
            
            // 记录兑换日志
            $pointsManager->logExchange($userId, $item['item_id'], $item['item_name'], $item['price'], $quantity);
            
            // 减少库存
            $shopManager->reduceStock($itemId, $quantity);
            
            $response = [
                'success' => true,
                'message' => "成功兑换 {$item['item_name']} x{$quantity}",
                'data' => [
                    'item' => $item,
                    'quantity' => $quantity,
                    'cost' => $totalCost,
                    'remaining_points' => $reduceResult['points']
                ]
            ];
            break;
            
        // ========== 背包相关 ==========
        case 'get_inventory':
            // 获取用户背包
            if (!$userId) {
                throw new Exception('未登录');
            }
            $result = $pointsManager->getInventory($userId);
            $response = $result;
            break;
            
        case 'use_item':
            // 使用物品
            if (!$userId) {
                throw new Exception('未登录');
            }
            $itemId = $_POST['item_id'] ?? '';
            $quantity = intval($_POST['quantity'] ?? 1);
            
            if (!$itemId) {
                throw new Exception('请指定物品 ID');
            }
            
            $result = $pointsManager->reduceItemFromInventory($userId, $itemId, $quantity);
            $response = $result;
            break;
            
        default:
            throw new Exception('无效的请求');
    }
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => $e->getMessage(),
        'data' => null
    ];
    http_response_code(400);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
