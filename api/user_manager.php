<?php
// user_manager.php
// 用户管理API

if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}

require_once 'config.php';
require_once 'helper.php';
require_once 'secure_data.php';
require_once 'UserManager.php';
require_once '../includes/auth_helper.php';

// 设置 CORS 和安全头
set_cors_headers();
set_security_headers();

// 只允许 GET 请求
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, '只允许 GET 请求', null, 405);
}

// 验证用户身份
if (!AuthHelper::validateToken()) {
    json_response(false, '未提供认证令牌', null, 401);
}

// 检查是否为管理员
$username = AuthHelper::getUsernameFromToken();
$userManager = new UserManager();
$user = $userManager->getUser($username);

if (!$user || $user['role'] !== 'admin') {
    json_response(false, '权限不足', null, 403);
}

// 获取请求参数
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'list':
        // 获取用户列表
        try {
            $users = $userManager->getAllUsers();
            
            // 格式化用户数据
            $formattedUsers = array_map(function($user) {
                return [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'points' => isset($user['points']) ? $user['points'] : 0,
                    'registered_at' => isset($user['created_at']) ? $user['created_at'] : '',
                    'last_login' => isset($user['last_login']) ? $user['last_login'] : '',
                    'email_verified' => isset($user['email_verified']) && $user['email_verified'] === true
                ];
            }, $users);
            
            json_response(true, '获取用户列表成功', [
                'users' => $formattedUsers,
                'total' => count($formattedUsers)
            ]);
        } catch (Exception $e) {
            json_response(false, '获取用户列表失败: ' . $e->getMessage(), null, 500);
        }
        break;
        
    case 'get':
        // 获取单个用户信息
        $userId = isset($_GET['id']) ? $_GET['id'] : '';
        if (!$userId) {
            json_response(false, '缺少用户ID', null, 400);
        }
        
        try {
            $user = $userManager->getUserById($userId);
            if (!$user) {
                json_response(false, '用户不存在', null, 404);
            }
            
            json_response(true, '获取用户信息成功', $user);
        } catch (Exception $e) {
            json_response(false, '获取用户信息失败: ' . $e->getMessage(), null, 500);
        }
        break;
        
    default:
        json_response(false, '无效的操作', null, 400);
        break;
}
?>