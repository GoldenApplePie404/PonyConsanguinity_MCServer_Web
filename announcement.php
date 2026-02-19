<?php
/**
 * 公告管理API
 * 处理公告的创建、读取、更新和删除操作
 */

require_once 'config.php';
require_once 'helper.php';

// 允许跨域请求
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 处理OPTIONS请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 检查用户是否已登录（可选，根据实际需求调整）
/*
session_start();
if (!isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '未授权访问']);
    exit;
}
*/

// 公告数据文件路径
$announcementsFile = '../data/announcements.json';
// 公告内容目录
$contentDir = '../data/content/announcements/';

// 确保内容目录存在
if (!is_dir($contentDir)) {
    mkdir($contentDir, 0755, true);
}

// 确保公告数据文件存在
if (!file_exists($announcementsFile)) {
    $defaultData = ['announcements' => []];
    file_put_contents($announcementsFile, json_encode($defaultData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// 读取公告数据
function readAnnouncements() {
    global $announcementsFile;
    $data = json_decode(file_get_contents($announcementsFile), true);
    // 确保返回的数据结构正确
    if (!is_array($data)) {
        return ['announcements' => []];
    }
    
    // 检查 announcements 是否为数组，如果不是，转换为数组
    if (!isset($data['announcements'])) {
        $data['announcements'] = [];
    } elseif (!is_array($data['announcements'])) {
        // 如果是对象，转换为数组
        $announcementsArray = [];
        foreach ($data['announcements'] as $item) {
            if (is_array($item)) {
                $announcementsArray[] = $item;
            }
        }
        $data['announcements'] = $announcementsArray;
    } else {
        // 确保是索引数组（移除数字键）
        $data['announcements'] = array_values($data['announcements']);
    }
    
    return $data;
}

// 保存公告数据
function saveAnnouncements($data) {
    global $announcementsFile;
    // 确保 announcements 是数组格式
    if (isset($data['announcements']) && is_array($data['announcements'])) {
        // 转换为纯数组（移除数字键，使用索引数组）
        $announcementsArray = array_values($data['announcements']);
        $data['announcements'] = $announcementsArray;
    }
    return file_put_contents($announcementsFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// 保存公告内容文件
function saveContentFile($id, $content) {
    global $contentDir;
    $filePath = $contentDir . $id . '.md';
    return file_put_contents($filePath, $content);
}

// 删除公告内容文件
function deleteContentFile($id) {
    global $contentDir;
    $filePath = $contentDir . $id . '.md';
    if (file_exists($filePath)) {
        return unlink($filePath);
    }
    return true;
}

// 处理不同的请求方法
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // 获取公告列表或单个公告
        $id = isset($_GET['id']) ? $_GET['id'] : null;
        $announcements = readAnnouncements();
        
        if ($id) {
            // 获取单个公告
            $announcement = null;
            foreach ($announcements['announcements'] as $item) {
                if ($item['id'] === $id) {
                    $announcement = $item;
                    break;
                }
            }
            
            if ($announcement) {
                // 读取公告内容
                $contentFile = $contentDir . $announcement['content_file'];
                if (file_exists($contentFile)) {
                    $announcement['content'] = file_get_contents($contentFile);
                }
                
                echo json_encode(['success' => true, 'data' => $announcement]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => '公告不存在']);
            }
        } else {
            // 获取公告列表
            echo json_encode(['success' => true, 'data' => $announcements]);
        }
        break;
        
    case 'POST':
        // 创建新公告
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['id'], $data['title'], $data['type'], $data['author'], $data['created_at'], $data['content'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => '缺少必要参数']);
            break;
        }
        
        $announcements = readAnnouncements();
        
        // 检查ID是否已存在
        foreach ($announcements['announcements'] as $item) {
            if ($item['id'] === $data['id']) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => '公告ID已存在']);
                break 2;
            }
        }
        
        // 创建新公告
        $newAnnouncement = [
            'id' => $data['id'],
            'title' => $data['title'],
            'type' => $data['type'],
            'author' => $data['author'],
            'created_at' => $data['created_at'],
            'updated_at' => $data['created_at'],
            'views' => 0,
            'content_file' => $data['id'] . '.md',
            'summary' => isset($data['summary']) ? $data['summary'] : ''
        ];
        
        // 添加到数据
        array_unshift($announcements['announcements'], $newAnnouncement);
        
        // 保存数据
        if (saveAnnouncements($announcements)) {
            // 保存内容文件
            if (saveContentFile($data['id'], $data['content'])) {
                echo json_encode(['success' => true, 'message' => '公告创建成功', 'data' => $newAnnouncement]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => '保存公告内容失败']);
            }
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => '保存公告数据失败']);
        }
        break;
        
    case 'PUT':
        // 更新公告
        $id = isset($_GET['id']) ? $_GET['id'] : null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => '缺少公告ID']);
            break;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['title'], $data['type'], $data['author'], $data['created_at'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => '缺少必要参数']);
            break;
        }
        
        $announcements = readAnnouncements();
        $found = false;
        
        foreach ($announcements['announcements'] as &$item) {
            if ($item['id'] === $id) {
                // 更新公告
                $item['title'] = $data['title'];
                $item['type'] = $data['type'];
                $item['author'] = $data['author'];
                $item['created_at'] = $data['created_at'];
                $item['updated_at'] = date('Y-m-d');
                $item['summary'] = isset($data['summary']) ? $data['summary'] : '';
                
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => '公告不存在']);
            break;
        }
        
        // 保存数据
        if (saveAnnouncements($announcements)) {
            // 保存内容文件（如果提供了内容）
            if (isset($data['content'])) {
                if (saveContentFile($id, $data['content'])) {
                    echo json_encode(['success' => true, 'message' => '公告更新成功']);
                } else {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => '保存公告内容失败']);
                }
            } else {
                echo json_encode(['success' => true, 'message' => '公告更新成功']);
            }
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => '保存公告数据失败']);
        }
        break;
        
    case 'DELETE':
        // 删除公告
        $id = isset($_GET['id']) ? $_GET['id'] : null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => '缺少公告ID']);
            break;
        }
        
        $announcements = readAnnouncements();
        $originalCount = count($announcements['announcements']);
        
        // 过滤掉要删除的公告
        $announcements['announcements'] = array_filter($announcements['announcements'], function($item) use ($id) {
            return $item['id'] !== $id;
        });
        
        if (count($announcements['announcements']) === $originalCount) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => '公告不存在']);
            break;
        }
        
        // 保存数据
        if (saveAnnouncements($announcements)) {
            // 删除内容文件
            if (deleteContentFile($id)) {
                echo json_encode(['success' => true, 'message' => '公告删除成功']);
            } else {
                // 即使删除文件失败，也返回成功，因为数据已经更新
                echo json_encode(['success' => true, 'message' => '公告删除成功，但删除内容文件时出错']);
            }
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => '保存公告数据失败']);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => '不支持的请求方法']);
        break;
}
?>