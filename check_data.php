<?php
/**
 * 数据完整性检查脚本
 * 用于检查和修复数据文件的完整性
 * 
 * 注意：此功能已暂时禁用（且过时）
 */

require_once 'config.php';
require_once 'helper.php';

// 检查结果
$results = [
    'errors' => [],
    'warnings' => [],
    'successes' => []
];

/*
// 检查目录结构
function checkDirectories() {
    global $results;
    
    $directories = [
        dirname(__DIR__) . '/data',
        CONTENT_DIR,
        REPLIES_DIR
    ];
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            // 创建缺失的目录
            if (mkdir($dir, 0755, true)) {
                $results['successes'][] = "创建目录: $dir";
            } else {
                $results['errors'][] = "无法创建目录: $dir";
            }
        } else {
            $results['successes'][] = "目录存在: $dir";
        }
    }
}

// 检查文件完整性
function checkFiles() {
    global $results;
    
    $files = [
        'users' => USERS_FILE,
        'sessions' => SESSIONS_FILE,
        'posts' => POSTS_FILE
    ];
    
    foreach ($files as $name => $file) {
        if (!file_exists($file)) {
            // 创建缺失的文件
            $defaultContent = [];
            
            switch ($name) {
                case 'users':
                case 'sessions':
                    $defaultContent = [];
                    break;
                case 'posts':
                    $defaultContent = ['posts' => []];
                    break;
            }
            
            if (file_put_contents($file, json_encode($defaultContent, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))) {
                $results['successes'][] = "创建文件: $file";
            } else {
                $results['errors'][] = "无法创建文件: $file";
            }
        } else {
            // 检查文件格式是否正确
            $content = file_get_contents($file);
            $data = json_decode($content, true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                $results['successes'][] = "文件格式正确: $file";
            } else {
                // 尝试修复文件
                $defaultContent = [];
                
                switch ($name) {
                    case 'users':
                    case 'sessions':
                        $defaultContent = [];
                        break;
                    case 'posts':
                        $defaultContent = ['posts' => []];
                        break;
                }
                
                if (file_put_contents($file, json_encode($defaultContent, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))) {
                    $results['warnings'][] = "修复文件格式: $file";
                } else {
                    $results['errors'][] = "文件格式错误且无法修复: $file";
                }
            }
        }
    }
}

// 检查用户数据完整性
function checkUserData() {
    global $results;
    
    $users = read_json(USERS_FILE);
    
    if (empty($users)) {
        $results['warnings'][] = '用户数据为空，建议运行初始化脚本';
    } else {
        // 检查是否有管理员账号
        $hasAdmin = false;
        foreach ($users as $user) {
            if (isset($user['role']) && $user['role'] === 'admin') {
                $hasAdmin = true;
                break;
            }
        }
        
        if (!$hasAdmin) {
            $results['warnings'][] = '缺少管理员账号，建议运行初始化脚本';
        } else {
            $results['successes'][] = '用户数据完整，包含管理员账号';
        }
    }
}

// 检查帖子数据完整性
function checkPostData() {
    global $results;
    
    $posts = read_json(POSTS_FILE);
    $postArray = $posts['posts'] ?? [];
    
    if (empty($postArray)) {
        $results['warnings'][] = '帖子数据为空，建议运行初始化脚本';
    } else {
        $results['successes'][] = "发现 " . count($postArray) . " 个帖子";
        
        // 检查每个帖子的内容文件是否存在
        $missingFiles = 0;
        
        foreach ($postArray as $post) {
            if (isset($post['content_file'])) {
                $contentFile = CONTENT_DIR . '/' . $post['content_file'];
                if (!file_exists($contentFile)) {
                    $missingFiles++;
                }
            }
        }
        
        if ($missingFiles > 0) {
            $results['warnings'][] = "缺少 $missingFiles 个帖子内容文件";
        } else {
            $results['successes'][] = '所有帖子内容文件存在';
        }
        
        // 检查每个帖子的回复文件是否存在
        $missingReplies = 0;
        
        foreach ($postArray as $post) {
            $postId = $post['id'];
            $repliesFile = REPLIES_DIR . "/${postId}.json";
            if (!file_exists($repliesFile)) {
                $missingReplies++;
            }
        }
        
        if ($missingReplies > 0) {
            $results['warnings'][] = "缺少 $missingReplies 个帖子回复文件";
        } else {
            $results['successes'][] = '所有帖子回复文件存在';
        }
    }
}
*/

// 主检查函数
function checkDataIntegrity() {
    global $results;
    
    echo "数据完整性检查功能已暂时禁用\n";
    echo "====================\n";
    echo "如果需要重新启用，请移除 api/check_data.php 中的注释\n";
    echo "====================\n";
    
    // 输出结果
    echo "检查结果:\n";
    echo "成功: " . count($results['successes']) . "\n";
    echo "警告: " . count($results['warnings']) . "\n";
    echo "错误: " . count($results['errors']) . "\n";
    echo "\n";
    echo "⚠️  功能已通过注释暂时禁用\n";
    
    return true;
}

// 运行检查
$success = checkDataIntegrity();

// 输出HTML响应（如果通过浏览器访问）
if (!empty($_SERVER['HTTP_HOST'])) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>';
    echo '<html lang="zh-CN">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>数据完整性检查</title>';
    echo '<style>';
    echo 'body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }';
    echo '.container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }';
    echo 'h1 { color: #333; }';
    echo 'pre { background: #f8f8f8; padding: 15px; border-radius: 4px; white-space: pre-wrap; }';
    echo '.success { color: #4CAF50; }';
    echo '.warning { color: #ff9800; }';
    echo '.error { color: #f44336; }';
    echo '.status { padding: 10px; border-radius: 4px; margin-top: 20px; }';
    echo '.status-success { background-color: #d4edda; color: #155724; }';
    echo '.status-error { background-color: #f8d7da; color: #721c24; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    echo '<div class="container">';
    echo '<h1>数据完整性检查结果</h1>';
    echo '<pre>';
    // 重新运行检查并捕获输出
    ob_start();
    checkDataIntegrity();
    $output = ob_get_clean();
    echo htmlspecialchars($output);
    echo '</pre>';
    echo '<div class="status status-warning">';
    echo '⚠️  数据完整性检查功能已暂时禁用';
    echo '</div>';
    echo '</div>';
    echo '</body>';
    echo '</html>';
}
?>