<?php
/**
 * 用户积分 API
 * 处理用户积分查询、增减等操作
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
    case 'get_user_info':
        getUserInfo($userId, $pointsManager);
        break;
    
    case 'add_points':
        $amount = intval($_POST['amount'] ?? 0);
        addPoints($userId, $amount, $pointsManager);
        break;
    
    case 'reduce_points':
        $amount = intval($_POST['amount'] ?? 0);
        reducePoints($userId, $amount, $pointsManager);
        break;
    
    case 'add_experience':
        $exp = intval($_POST['experience'] ?? 0);
        addExperience($userId, $exp, $pointsManager);
        break;
    
    default:
        echo json_encode([
            'success' => false,
            'message' => '无效的操作'
        ], JSON_UNESCAPED_UNICODE);
}

/**
 * 获取用户信息
 */
function getUserInfo($userId, $pointsManager) {
    $userInfo = $pointsManager->getUserInfo($userId);
    
    echo json_encode([
        'success' => true,
        'user_id' => $userId,
        'points' => $userInfo['points'],
        'level' => $userInfo['level'],
        'experience' => $userInfo['experience']
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * 增加积分
 */
function addPoints($userId, $amount, $pointsManager) {
    if ($amount <= 0) {
        echo json_encode([
            'success' => false,
            'message' => '增加的积分必须大于 0'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $pointsManager->addPoints($userId, $amount);
    $newPoints = $pointsManager->getUserPoints($userId);
    
    echo json_encode([
        'success' => true,
        'message' => '积分增加成功',
        'amount' => $amount,
        'new_points' => $newPoints
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * 减少积分
 */
function reducePoints($userId, $amount, $pointsManager) {
    if ($amount <= 0) {
        echo json_encode([
            'success' => false,
            'message' => '减少的积分必须大于 0'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $currentPoints = $pointsManager->getUserPoints($userId);
    if ($currentPoints < $amount) {
        echo json_encode([
            'success' => false,
            'message' => '积分不足',
            'current_points' => $currentPoints,
            'required' => $amount
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $pointsManager->reducePoints($userId, $amount);
    $newPoints = $pointsManager->getUserPoints($userId);
    
    echo json_encode([
        'success' => true,
        'message' => '积分减少成功',
        'amount' => $amount,
        'new_points' => $newPoints
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * 增加经验值
 */
function addExperience($userId, $exp, $pointsManager) {
    if ($exp <= 0) {
        echo json_encode([
            'success' => false,
            'message' => '增加的经验值必须大于 0'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $result = $pointsManager->addExperience($userId, $exp);
    
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
}
