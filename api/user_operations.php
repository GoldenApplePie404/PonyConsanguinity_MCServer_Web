<?php
// user_operations.php
// 统一的用户操作API

if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}

require_once 'config.php';
require_once 'helper.php';
require_once 'secure_data.php';
require_once 'UserManager.php';

header('Content-Type: application/json; charset=utf-8');

session_start();
require_once '../includes/auth_helper.php';

// 验证登录
if (!AuthHelper::validateToken()) {
    echo json_encode([
        'success' => false,
        'message' => '请先登录'
    ]);
    exit;
}

$username = AuthHelper::getUsernameFromToken();
$manager = new UserManager();
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'add_points':
        $points = intval($_POST['points'] ?? 0);
        if ($points > 0) {
            $success = $manager->addPoints($username, $points);
            if ($success) {
                $user = $manager->getUser($username);
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'points' => $user['points'],
                        'experience' => $user['experience']
                    ]
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => '添加积分失败'
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => '积分必须大于0'
            ]);
        }
        break;
        
    case 'remove_points':
        $points = intval($_POST['points'] ?? 0);
        if ($points > 0) {
            $success = $manager->removePoints($username, $points);
            if ($success) {
                $user = $manager->getUser($username);
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'points' => $user['points'],
                        'experience' => $user['experience']
                    ]
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => '扣除积分失败'
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => '积分必须大于0'
            ]);
        }
        break;
        
    case 'add_experience':
        $experience = intval($_POST['experience'] ?? 0);
        if ($experience > 0) {
            $success = $manager->addExperience($username, $experience);
            if ($success) {
                $user = $manager->getUser($username);
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'points' => $user['points'],
                        'experience' => $user['experience']
                    ]
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => '添加经验值失败'
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => '经验值必须大于0'
            ]);
        }
        break;
        
    case 'remove_experience':
        $experience = intval($_POST['experience'] ?? 0);
        if ($experience > 0) {
            $success = $manager->removeExperience($username, $experience);
            if ($success) {
                $user = $manager->getUser($username);
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'points' => $user['points'],
                        'experience' => $user['experience']
                    ]
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => '扣除经验值失败'
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => '经验值必须大于0'
            ]);
        }
        break;
        
    case 'get_user':
        $user = $manager->getUser($username);
        if ($user) {
            echo json_encode([
                'success' => true,
                'data' => [
                    'points' => $user['points'],
                    'experience' => $user['experience']
                ]
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => '用户不存在'
            ]);
        }
        break;
        
    default:
        echo json_encode([
            'success' => false,
            'message' => '无效的操作'
        ]);
        break;
}
?>