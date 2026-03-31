<?php
/**
 * 积分商城 API
 * 处理商品列表、购买等操作
 * 购买后物品立即生效，无背包系统
 */

// 关闭 HTML 错误输出，确保只返回 JSON
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/PointsManager.php';

$action = $_GET['action'] ?? '';
$userId = $_GET['user_id'] ?? $_POST['user_id'] ?? 'guest';

// 如果是 POST 请求且 Content-Type 包含 application/json，需要手动解析
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $input = file_get_contents('php://input');
        $jsonData = json_decode($input, true);
        if ($jsonData) {
            $action = $jsonData['action'] ?? $action;
            $userId = $jsonData['user_id'] ?? $userId;
            $_POST = $jsonData;
        }
    }
}

$pointsManager = new PointsManager();

switch ($action) {
    case 'get_products':
        getProducts($userId, $pointsManager);
        break;
    
    case 'get_product':
        $productId = $_GET['product_id'] ?? '';
        getProduct($productId, $userId, $pointsManager);
        break;
    
    case 'buy_product':
        $productId = $_POST['product_id'] ?? '';
        buyProduct($userId, $productId, $pointsManager);
        break;
    
    default:
        echo json_encode([
            'success' => false,
            'message' => '无效的操作'
        ], JSON_UNESCAPED_UNICODE);
}

/**
 * 获取商品列表
 */
function getProducts($userId, $pointsManager) {
    $shopItemsDir = __DIR__ . '/../../data/shop_items';
    $products = [];
    
    if (!is_dir($shopItemsDir)) {
        echo json_encode([
            'success' => true,
            'products' => []
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $files = scandir($shopItemsDir);
    
    foreach ($files as $file) {
        // 支持 .php 和 .json 格式
        $extension = pathinfo($file, PATHINFO_EXTENSION);
        if ($extension === 'php' || $extension === 'json') {
            $filePath = $shopItemsDir . '/' . $file;
            
            // 加载物品数据
            $product = loadProduct($filePath, $extension);
            
            if ($product) {
                $userLevel = $pointsManager->getUserLevel($userId);
                $requiredLevel = $product['level_requirement'] ?? 0;
                
                if ($product['status'] === 'on_sale' && $userLevel >= $requiredLevel) {
                    $products[] = $product;
                }
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'products' => $products
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * 加载物品数据（支持 PHP 和 JSON）
 */
function loadProduct($filePath, $extension) {
    if ($extension === 'php') {
        // PHP 文件：读取文件内容并提取 $item 数组
        $content = file_get_contents($filePath);
        
        // 查找 $item = [...] 部分
        if (preg_match('/\$item\s*=\s*(\[.*?\]);/s', $content, $matches)) {
            $itemStr = $matches[1];
            // 将 PHP 数组语法转换为可评估的形式
            // 替换 null 为 NULL（eval 需要）
            $itemStr = str_replace('null', 'NULL', $itemStr);
            
            try {
                // 使用 eval 解析数组（在受控环境下是安全的）
                eval('$item = ' . $itemStr . ';');
                return $item;
            } catch (Exception $e) {
                return null;
            }
        }
        return null;
    } else {
        // JSON 文件直接解析
        $content = file_get_contents($filePath);
        return json_decode($content, true);
    }
}

/**
 * 获取单个商品详情
 */
function getProduct($productId, $userId, $pointsManager) {
    $product = loadProductById($productId);
    
    if (!$product) {
        echo json_encode([
            'success' => false,
            'message' => '商品不存在'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $userLevel = $pointsManager->getUserLevel($userId);
    $requiredLevel = $product['level_requirement'] ?? 0;
    
    if ($userLevel < $requiredLevel) {
        echo json_encode([
            'success' => false,
            'message' => '等级不足，需要达到 ' . $requiredLevel . ' 级'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'product' => $product
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * 根据 ID 加载物品
 */
function loadProductById($productId) {
    $shopItemsDir = __DIR__ . '/../../data/shop_items';
    
    // 先尝试 PHP 文件
    $phpFile = $shopItemsDir . '/' . $productId . '.php';
    if (file_exists($phpFile)) {
        // 对于 PHP 文件，直接读取 $item 变量而不 include
        // 通过解析 PHP 文件获取 $item 数组
        $content = file_get_contents($phpFile);
        // 使用简单的方法：查找 $item = [...] 部分
        if (preg_match('/\$item\s*=\s*(\[.*?\]);/s', $content, $matches)) {
            // 将 PHP 数组字符串转换为实际数组（简单处理）
            $itemStr = $matches[1];
            // 替换 PHP 常量
            $itemStr = str_replace('null', 'NULL', $itemStr);
            // 使用 eval 来解析（在受控环境下安全）
            try {
                eval('$item = ' . $itemStr . ';');
                return $item;
            } catch (Exception $e) {
                return null;
            }
        }
        return null;
    }
    
    // 再尝试 JSON 文件
    $jsonFile = $shopItemsDir . '/' . $productId . '.json';
    if (file_exists($jsonFile)) {
        $content = file_get_contents($jsonFile);
        return json_decode($content, true);
    }
    
    return null;
}

/**
 * 购买商品 - 购买后立即生效
 */
function buyProduct($userId, $productId, $pointsManager) {
    if (empty($productId)) {
        echo json_encode([
            'success' => false,
            'message' => '商品 ID 不能为空'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // 加载物品
    $product = loadProductById($productId);
    
    if (!$product) {
        echo json_encode([
            'success' => false,
            'message' => '商品不存在'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // 检查商品状态
    if ($product['status'] !== 'on_sale') {
        echo json_encode([
            'success' => false,
            'message' => '商品已下架'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // 检查等级限制
    $userLevel = $pointsManager->getUserLevel($userId);
    $requiredLevel = $product['level_requirement'] ?? 0;
    if ($userLevel < $requiredLevel) {
        echo json_encode([
            'success' => false,
            'message' => '等级不足，需要达到 ' . $requiredLevel . ' 级'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // 检查库存
    if (isset($product['stock']) && $product['stock'] !== -1 && $product['stock'] <= 0) {
        echo json_encode([
            'success' => false,
            'message' => '商品已售罄'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // 检查积分是否足够
    $price = $product['price'];
    if (!$pointsManager->checkPoints($userId, $price)) {
        $currentPoints = $pointsManager->getUserPoints($userId);
        echo json_encode([
            'success' => false,
            'message' => '积分不足，需要 ' . $price . ' 积分，当前只有 ' . $currentPoints . ' 积分'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // 检查物品是否有 PHP 文件（支持 buyAndUse API）
    $shopItemsDir = __DIR__ . '/../../data/shop_items';
    $phpFile = $shopItemsDir . '/' . $productId . '.php';
    
    if (file_exists($phpFile)) {
        // 使用 PHP 文件的 buyAndUse 函数
        global $context, $item;
        $context = 'shop';
        include $phpFile;
        
        if (function_exists('buyAndUse')) {
            $result = buyAndUse($userId, $pointsManager);
            
            // 减少库存（如果是有限库存且购买成功）
            if ($result['success'] && isset($product['stock']) && $product['stock'] !== -1) {
                $product['stock'] -= 1;
                $extension = pathinfo($phpFile, PATHINFO_EXTENSION);
                if ($extension === 'json') {
                    file_put_contents($phpFile, json_encode($product, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                }
            }
            
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            return;
        }
    }
    
    // 默认购买逻辑（如果没有 buyAndUse 函数）
    // 扣除积分
    $pointsManager->reducePoints($userId, $price);
    
    // 根据效果类型执行操作
    $effect = $product['effect'] ?? null;
    
    if ($effect) {
        switch ($effect['type']) {
            case 'experience':
                $result = $pointsManager->addExperience($userId, $effect['value']);
                $response = [
                    'success' => true,
                    'message' => '购买成功！获得 ' . $effect['value'] . ' 点经验值',
                    'product_name' => $product['name'],
                    'product_id' => $product['id'],
                    'price' => $price,
                    'remaining_points' => $pointsManager->getUserPoints($userId),
                    'experience' => $result['experience'],
                    'level' => $result['level'],
                    'leveled_up' => $result['leveled_up'] ?? false,
                    'effect' => $effect
                ];
                break;
            
            case 'points':
                $pointsManager->addPoints($userId, $effect['value']);
                $response = [
                    'success' => true,
                    'message' => '购买成功！获得 ' . $effect['value'] . ' 积分',
                    'product_name' => $product['name'],
                    'product_id' => $product['id'],
                    'price' => $price,
                    'remaining_points' => $pointsManager->getUserPoints($userId),
                    'effect' => $effect
                ];
                break;
            
            case 'buff':
                $response = [
                    'success' => true,
                    'message' => '激活成功！效果持续中',
                    'product_name' => $product['name'],
                    'product_id' => $product['id'],
                    'price' => $price,
                    'remaining_points' => $pointsManager->getUserPoints($userId),
                    'effect' => $effect,
                    'buff_id' => $effect['buff_id'] ?? null,
                    'duration' => $effect['duration'] ?? 0
                ];
                break;
            
            default:
                $response = [
                    'success' => true,
                    'message' => '购买成功！',
                    'product_name' => $product['name'],
                    'remaining_points' => $pointsManager->getUserPoints($userId)
                ];
                break;
        }
    } else {
        $response = [
            'success' => true,
            'message' => '购买成功！',
            'product_name' => $product['name'],
            'remaining_points' => $pointsManager->getUserPoints($userId)
        ];
    }
    
    // 减少库存
    if (isset($product['stock']) && $product['stock'] !== -1) {
        $product['stock'] -= 1;
        $jsonFile = $shopItemsDir . '/' . $productId . '.json';
        if (file_exists($jsonFile)) {
            file_put_contents($jsonFile, json_encode($product, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}
