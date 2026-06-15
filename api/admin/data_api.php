<?php
/**
 * 数据管理 API
 * 提供数据概览、文件列表、文件查看等功能
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 引入配置文件
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helper.php';
require_once __DIR__ . '/../../includes/auth_helper.php';

// ---- 站点管理员认证 ----
// 所有受保护的路由都通过 AuthHelper::requireAdmin() 统一鉴权

// 获取文件夹大小
function getDirectorySize($path) {
    $size = 0;
    if (!is_dir($path)) return 0;
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $size += $file->getSize();
        }
    }
    
    return $size;
}

// 获取文件数量
function getFileCount($path, $pattern = '*') {
    if (!is_dir($path)) return 0;
    return count(glob($path . '/' . $pattern));
}

// 获取目录中所有文件
function getDirectoryFiles($path) {
    $files = [];
    if (!is_dir($path)) return $files;
    
    $iterator = new DirectoryIterator($path);
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getFilename() !== '.gitkeep') {
            $files[] = [
                'name' => $file->getFilename(),
                'size' => $file->getSize(),
                'modified' => $file->getMTime(),
                'path' => $file->getPathname()
            ];
        }
    }
    
    // 按修改时间排序（最新的在前）
    usort($files, function($a, $b) {
        return $b['modified'] - $a['modified'];
    });
    
    return $files;
}

// 获取操作
$action = $_GET['action'] ?? '';

// 调试：输出错误信息
error_reporting(E_ALL);
ini_set('display_errors', 0); // 不显示在页面上，只记录到日志
ini_set('log_errors', 1);

try {
    // 检查管理员权限
    AuthHelper::requireAdmin();
    
    // 定义 ACCESS_ALLOWED 以便读取数据文件
    if (!defined('ACCESS_ALLOWED')) {
        define('ACCESS_ALLOWED', true);
    }
    
    switch ($action) {
        case 'overview':
            // 数据概览
            $dataDir = __DIR__ . '/../../data';
            
            $overview = [
                'posts' => getFileCount($dataDir . '/content', '*.md'),
                'replies' => getFileCount($dataDir . '/replies', '*.json'),
                'users' => 0,
                'images' => 0,
                'announcements' => getFileCount($dataDir . '/content/announcements', '*.md'),
                'notifications' => 0
            ];
            
            // 统计用户数据
            if (file_exists($dataDir . '/users.php')) {
                define('ACCESS_ALLOWED', true);
                $usersArray = include $dataDir . '/users.php';
                if (is_array($usersArray)) {
                    $overview['users'] = count($usersArray);
                }
            }
            
            // 统计图片数据
            if (file_exists($dataDir . '/images.json')) {
                $imagesData = json_decode(file_get_contents($dataDir . '/images.json'), true);
                if (is_array($imagesData)) {
                    $overview['images'] = isset($imagesData['images']) ? count($imagesData['images']) : count($imagesData);
                }
            }
            
            // 统计通知数据
            if (file_exists($dataDir . '/notifications.json')) {
                $notificationsData = json_decode(file_get_contents($dataDir . '/notifications.json'), true);
                $overview['notifications'] = count($notificationsData['notifications'] ?? []);
            }
            
            echo json_encode([
                'success' => true,
                'data' => $overview
            ]);
            break;
            
        case 'categories':
            // 数据分类详情 - 返回帖子、回复、用户、图片等详细数据
            $dataDir = __DIR__ . '/../../data';
            
            $result = [
                'posts' => [],
                'replies' => [],
                'users' => [],
                'images' => []
            ];
            
            // 获取帖子数据
            if (file_exists($dataDir . '/posts.php')) {
                define('ACCESS_ALLOWED', true);
                $postsArray = include $dataDir . '/posts.php';
                if (is_array($postsArray) && !empty($postsArray['posts'])) {
                    foreach ($postsArray['posts'] as $post) {
                        // 读取帖子内容
                        $content = '';
                        if (!empty($post['content_file'])) {
                            $contentFile = $dataDir . '/content/' . $post['content_file'];
                            if (file_exists($contentFile)) {
                                $content = file_get_contents($contentFile);
                            }
                        }
                        
                        $result['posts'][] = [
                            'id' => $post['id'] ?? '-',
                            'title' => $post['title'] ?? '无标题',
                            'author' => $post['author'] ?? '匿名',
                            'date' => $post['created_at'] ?? date('Y-m-d H:i:s'),
                            'content' => mb_substr($content, 0, 100) . '...'
                        ];
                    }
                }
            }
            
            // 获取回复数据
            if (is_dir($dataDir . '/replies')) {
                $replyFiles = glob($dataDir . '/replies/*.json');
                foreach ($replyFiles as $file) {
                    $content = file_get_contents($file);
                    $replyData = json_decode($content, true);
                    if (!empty($replyData['replies'])) {
                        foreach ($replyData['replies'] as $reply) {
                            $result['replies'][] = [
                                'id' => $reply['id'] ?? basename($file, '.json'),
                                'content' => $reply['content'] ?? '-',
                                'author' => $reply['author'] ?? '匿名',
                                'date' => $reply['created_at'] ?? date('Y-m-d H:i:s', time())
                            ];
                        }
                    }
                }
            }
            
            // 获取用户数据
            if (file_exists($dataDir . '/users.php')) {
                define('ACCESS_ALLOWED', true);
                $usersArray = include $dataDir . '/users.php';
                if (is_array($usersArray)) {
                    foreach ($usersArray as $user) {
                        $result['users'][] = [
                            'id' => $user['id'] ?? '-',
                            'username' => $user['username'] ?? '未知',
                            'email' => $user['email'] ?? '-',
                            'role' => $user['role'] ?? 'user'
                        ];
                    }
                }
            }
            
            // 获取图片数据
            if (file_exists($dataDir . '/images.json')) {
                $imagesData = json_decode(file_get_contents($dataDir . '/images.json'), true);
                if (!empty($imagesData['images'])) {
                    foreach ($imagesData['images'] as $img) {
                        $result['images'][] = [
                            'name' => $img['name'] ?? '-',
                            'size' => $img['size'] ?? '-',
                            'type' => $img['type'] ?? '-',
                            'path' => $img['path'] ?? '../data/images/' . ($img['name'] ?? '')
                        ];
                    }
                }
            }
            
            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
            break;
            
        case 'files':
            // 获取目录文件列表
            $dir = $_GET['dir'] ?? '';
            $dataDir = __DIR__ . '/../../data';
            
            $allowedDirs = ['content', 'replies', 'announcements', 'user_notifications', 'shop_items', 'content/announcements'];
            
            if (!in_array($dir, $allowedDirs)) {
                echo json_encode([
                    'success' => false,
                    'message' => '无效的目录'
                ]);
                exit();
            }
            
            $targetPath = $dataDir . '/' . $dir;
            
            if (!is_dir($targetPath)) {
                echo json_encode([
                    'success' => false,
                    'message' => '目录不存在'
                ]);
                exit();
            }
            
            $files = getDirectoryFiles($targetPath);
            
            echo json_encode([
                'success' => true,
                'data' => $files
            ]);
            break;
            
        case 'view':
            // 查看文件内容
            $path = $_GET['path'] ?? '';
            $dataDir = __DIR__ . '/../../data';
            
            // 安全检查：防止目录遍历攻击
            $realPath = realpath($dataDir . '/' . $path);
            if ($realPath === false || strpos($realPath, realpath($dataDir)) !== 0) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => '非法路径'
                ]);
                exit();
            }
            
            if (!file_exists($realPath)) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => '文件不存在'
                ]);
                exit();
            }
            
            $content = file_get_contents($realPath);
            $extension = pathinfo($realPath, PATHINFO_EXTENSION);
            
            // 根据文件类型设置不同的响应
            if (in_array($extension, ['json'])) {
                header('Content-Type: application/json; charset=utf-8');
            } elseif (in_array($extension, ['md', 'txt'])) {
                header('Content-Type: text/plain; charset=utf-8');
            } elseif ($extension === 'php') {
                // PHP 文件显示源代码
                highlight_string($content);
                exit();
            }
            
            echo $content;
            break;
            
        case 'download':
            // 下载文件
            $path = $_GET['path'] ?? '';
            $dataDir = __DIR__ . '/../../data';
            
            // 安全检查
            $realPath = realpath($dataDir . '/' . $path);
            if ($realPath === false || strpos($realPath, realpath($dataDir)) !== 0) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => '非法路径'
                ]);
                exit();
            }
            
            if (!file_exists($realPath)) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => '文件不存在'
                ]);
                exit();
            }
            
            $filename = basename($realPath);
            
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($realPath));
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: no-cache');
            
            readfile($realPath);
            break;
            
        case 'post':
            // 获取单个帖子详情
            $postId = $_GET['id'] ?? '';
            $dataDir = __DIR__ . '/../../data';
            
            if (file_exists($dataDir . '/posts.php')) {
                define('ACCESS_ALLOWED', true);
                $postsArray = include $dataDir . '/posts.php';
                
                if (is_array($postsArray)) {
                    foreach ($postsArray['posts'] as $post) {
                        if (($post['id'] ?? '') == $postId) {
                            // 读取完整内容
                            $content = '';
                            if (!empty($post['content_file'])) {
                                $contentFile = $dataDir . '/content/' . $post['content_file'];
                                if (file_exists($contentFile)) {
                                    $content = file_get_contents($contentFile);
                                }
                            }
                            
                            $post['content'] = $content;
                            
                            echo json_encode([
                                'success' => true,
                                'data' => $post
                            ]);
                            exit();
                        }
                    }
                }
            }
            
            echo json_encode([
                'success' => false,
                'message' => '帖子不存在'
            ]);
            break;
            
        case 'reply':
            // 获取单个回复详情
            $replyId = $_GET['id'] ?? '';
            $dataDir = __DIR__ . '/../../data';
            
            // 遍历所有回复文件查找匹配的 ID
            $replyFiles = glob($dataDir . '/replies/*.json');
            foreach ($replyFiles as $file) {
                $content = file_get_contents($file);
                $replyData = json_decode($content, true);
                
                if (!empty($replyData['replies'])) {
                    foreach ($replyData['replies'] as $reply) {
                        if (($reply['id'] ?? '') == $replyId) {
                            echo json_encode([
                                'success' => true,
                                'data' => $reply
                            ]);
                            exit();
                        }
                    }
                }
            }
            
            echo json_encode([
                'success' => false,
                'message' => '回复不存在'
            ]);
            break;
            
        case 'user':
            // 获取单个用户详情
            $userId = $_GET['id'] ?? '';
            $dataDir = __DIR__ . '/../../data';
            
            if (file_exists($dataDir . '/users.php')) {
                define('ACCESS_ALLOWED', true);
                $usersArray = include $dataDir . '/users.php';
                
                if (is_array($usersArray)) {
                    foreach ($usersArray as $user) {
                        if (($user['id'] ?? '') == $userId) {
                            echo json_encode([
                                'success' => true,
                                'data' => $user
                            ]);
                            exit();
                        }
                    }
                }
            }
            
            echo json_encode([
                'success' => false,
                'message' => '用户不存在'
            ]);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => '无效的操作'
            ]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '服务器错误：' . $e->getMessage()
    ]);
}
