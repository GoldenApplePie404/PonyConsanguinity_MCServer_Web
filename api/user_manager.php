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

// 只允许 GET 或 POST 请求
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
    json_response(false, '不支持的请求方法', null, 405);
}

// 验证管理员权限
AuthHelper::requireAdmin();
$userManager = new UserManager();

// 获取请求参数（支持 GET 和 POST）
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $action = isset($input['action']) ? $input['action'] : ($_GET['action'] ?? '');
} else {
    $action = isset($_GET['action']) ? $_GET['action'] : '';
}

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
        $userId = isset($_GET['id']) ? $_GET['id'] : (isset($input['id']) ? $input['id'] : '');
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

    case 'set_role':
        // 设置用户角色（管理员可提升/降级其他用户）
        $targetUser = isset($_GET['username']) ? $_GET['username'] : (isset($input['username']) ? $input['username'] : '');
        $newRole = isset($_GET['role']) ? $_GET['role'] : (isset($input['role']) ? $input['role'] : '');
        
        if (!$targetUser || !$newRole) {
            json_response(false, '缺少参数 username 或 role', null, 400);
        }
        if (!in_array($newRole, ['admin', 'user'])) {
            json_response(false, '角色无效，仅支持 admin/user', null, 400);
        }
        
        try {
            $users = secureReadData(USERS_FILE);
            if (!isset($users[$targetUser])) {
                json_response(false, '用户不存在', null, 404);
            }
            // 禁止将自己降级
            $current = AuthHelper::getCurrentUser();
            if ($current && $current['username'] === $targetUser) {
                json_response(false, '不能修改自己的角色', null, 400);
            }
            $users[$targetUser]['role'] = $newRole;
            if (!secureWriteData(USERS_FILE, $users)) {
                json_response(false, '保存失败', null, 500);
            }
            json_response(true, "用户 {$targetUser} 的角色已更新为 {$newRole}");
        } catch (Exception $e) {
            json_response(false, '设置角色失败: ' . $e->getMessage(), null, 500);
        }
        break;
        
    default:
        json_response(false, '无效的操作', null, 400);
        break;
}
?>