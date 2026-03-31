<?php
/**
 * Session 管理 API
 * 用于通过 session token 获取用户真实信息
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

$action = $_GET['action'] ?? '';
$token = $_GET['token'] ?? $_POST['token'] ?? '';

// 如果是 POST 请求且 Content-Type 包含 application/json，需要手动解析
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $input = file_get_contents('php://input');
        $jsonData = json_decode($input, true);
        if ($jsonData) {
            $action = $jsonData['action'] ?? $action;
            $token = $jsonData['token'] ?? $token;
            $_POST = $jsonData;
        }
    }
}

switch ($action) {
    case 'get_user':
        getUserByToken($token);
        break;
    
    case 'validate':
        validateToken($token);
        break;
    
    default:
        echo json_encode([
            'success' => false,
            'message' => '无效的操作'
        ], JSON_UNESCAPED_UNICODE);
}

/**
 * 通过 session token 获取用户信息
 */
function getUserByToken($token) {
    if (empty($token)) {
        echo json_encode([
            'success' => false,
            'message' => 'Token 不能为空'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $sessionsFile = __DIR__ . '/../../data/sessions.php';
    
    if (!file_exists($sessionsFile)) {
        echo json_encode([
            'success' => false,
            'message' => 'Session 文件不存在'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // 定义 ACCESS_ALLOWED 常量以允许加载
    if (!defined('ACCESS_ALLOWED')) {
        define('ACCESS_ALLOWED', true);
    }
    
    $sessions = include $sessionsFile;
    
    if (!isset($sessions[$token])) {
        echo json_encode([
            'success' => false,
            'message' => '无效的 Session'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $session = $sessions[$token];
    
    // 检查 session 是否过期
    if (isset($session['expires_at']) && $session['expires_at'] < time()) {
        echo json_encode([
            'success' => false,
            'message' => 'Session 已过期'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'user_id' => $session['user_id'],
        'username' => $session['username'],
        'role' => $session['role'] ?? 'user',
        'expires_at' => $session['expires_at'] ?? null
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * 验证 token 是否有效
 */
function validateToken($token) {
    if (empty($token)) {
        echo json_encode([
            'success' => false,
            'message' => 'Token 不能为空'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $sessionsFile = __DIR__ . '/../../data/sessions.php';
    
    if (!file_exists($sessionsFile)) {
        echo json_encode([
            'success' => false,
            'message' => 'Session 文件不存在'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // 定义 ACCESS_ALLOWED 常量以允许加载
    if (!defined('ACCESS_ALLOWED')) {
        define('ACCESS_ALLOWED', true);
    }
    
    $sessions = include $sessionsFile;
    
    if (!isset($sessions[$token])) {
        echo json_encode([
            'success' => false,
            'message' => '无效的 Session'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $session = $sessions[$token];
    
    // 检查 session 是否过期
    if (isset($session['expires_at']) && $session['expires_at'] < time()) {
        echo json_encode([
            'success' => false,
            'message' => 'Session 已过期'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Token 有效'
    ], JSON_UNESCAPED_UNICODE);
}
?>
