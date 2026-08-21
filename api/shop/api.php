<?php
/**
 * 积分商城 API 主接口
 * 处理所有与积分商城和背包相关的请求
 *
 * 安全说明：
 *  - 除商品浏览（get_items / get_item_detail）外，所有操作必须登录
 *  - user_id 仅允许来自会话，禁止客户端伪造
 *  - add_points / reduce_points 仅限管理员
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth_helper.php';

// 引入管理类
require_once __DIR__ . '/PointsManager.php';
require_once __DIR__ . '/ShopManager.php';

header('Content-Type: application/json; charset=utf-8');
set_cors_headers(); // 安全 CORS：仅允许受信任来源，禁止通配符

// 初始化响应
$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

try {
    // 获取请求方法
    $method = $_SERVER['REQUEST_METHOD'];

    // 获取动作
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    // 公开操作：商品浏览（无需登录）
    $publicActions = ['get_items', 'get_item_detail'];

    // 初始化管理器
    $pointsManager = new PointsManager();
    $shopManager = new ShopManager();

    // 非公开操作：必须登录，且 user_id 仅取自会话（禁止伪造）
    if (!in_array($action, $publicActions)) {
        $session = AuthHelper::requireLogin();
        $selfUserId = $session['user_id'] ?? null;

        // 积分增减为管理员特权
        if (in_array($action, ['add_points', 'reduce_points'])) {
            AuthHelper::requireAdmin();
            // 管理员可对指定目标用户操作
            $userId = $_POST['user_id'] ?? $selfUserId;
        } else {
            $userId = $selfUserId;
        }

        if (!$userId) {
            throw new Exception('会话缺少用户标识，请重新登录');
        }
    }

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
