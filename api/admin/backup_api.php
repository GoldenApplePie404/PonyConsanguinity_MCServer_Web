<?php
/**
 * 数据备份 API
 * 提供数据备份和下载功能
 */

// 增加执行时间和内存限制
set_time_limit(300); // 5 分钟
ini_set('memory_limit', '512M');

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

// 创建 ZIP 文件
function createZip($sourceDir, $zipPath, $files = null) {
    if (!class_exists('ZipArchive')) {
        throw new Exception('服务器不支持 ZIP 压缩功能');
    }
    
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new Exception('无法创建 ZIP 文件');
    }
    
    // 统一路径格式并规范化
    $sourceDir = rtrim(realpath($sourceDir), '/\\');
    
    error_log('createZip - Source Dir: ' . $sourceDir);
    error_log('createZip - ZIP Path: ' . $zipPath);
    
    if ($files === null) {
        // 添加整个目录（排除 backups 文件夹和 .gitkeep 文件）
        if (!is_dir($sourceDir)) {
            error_log('createZip - Source dir does not exist: ' . $sourceDir);
            $zip->close();
            return false;
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        $fileCount = 0;
        foreach ($iterator as $file) {
            // 跳过 backups 目录
            if ($file->isDir()) {
                // 检查当前目录或父目录是否是 backups
                $pathParts = explode(DIRECTORY_SEPARATOR, $file->getPathname());
                if (in_array('backups', $pathParts)) {
                    continue;
                }
            }
            
            if ($file->isFile() && $file->getFilename() !== '.gitkeep') {
                $filePath = $file->getPathname();
                
                // 计算相对路径：去掉 sourceDir 前缀和目录分隔符
                $relativePath = str_replace($sourceDir . DIRECTORY_SEPARATOR, '', $filePath);
                
                // 统一使用正向斜杠
                $relativePath = str_replace('\\', '/', $relativePath);
                
                // 再次检查是否包含 backups
                if (strpos($relativePath, 'backups/') === 0) {
                    continue;
                }
                
                error_log('Adding file: ' . $relativePath);
                
                if ($zip->addFile($filePath, $relativePath)) {
                    $fileCount++;
                }
            }
        }
        
        error_log('createZip - Total files added: ' . $fileCount);
    } else {
        // 添加指定文件
        foreach ($files as $file) {
            $filePath = $sourceDir . '/' . $file;
            if (file_exists($filePath)) {
                $zip->addFile($filePath, $file);
            }
        }
    }
    
    $zip->close();
    return true;
}

try {
    // 检查权限
    AuthHelper::requireAdmin();
    
    // 获取操作类型
    $action = $_GET['action'] ?? '';
    
    // 处理下载请求
    if ($action === 'download' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $type = $input['type'] ?? 'all';
        
        $dataDir = realpath(__DIR__ . '/../../data');
        $backupDir = realpath(__DIR__ . '/../../data/backups');
        
        // 创建备份目录
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
            $backupDir = realpath(__DIR__ . '/../../data/backups');
        }
        
        // 生成备份文件名
        $timestamp = date('Ymd_His');
        $zipFilename = "backup_{$type}_{$timestamp}.zip";
        $zipPath = $backupDir . '/' . $zipFilename;
        
        error_log('Backup - Data Dir: ' . $dataDir);
        error_log('Backup - Backup Dir: ' . $backupDir);
        error_log('Backup - ZIP Path: ' . $zipPath);
        
        // 备份所有数据
        createZip($dataDir, $zipPath);
        
        // 检查 ZIP 文件是否创建成功
        if (!file_exists($zipPath)) {
            throw new Exception('备份文件创建失败');
        }
        
        // 返回文件信息供前端下载
        echo json_encode([
            'success' => true,
            'file' => $zipFilename
        ]);
        exit();
    }
    
    // 处理文件下载（GET 请求）
    if ($action === 'download' && isset($_GET['file'])) {
        // 验证 token
        AuthHelper::requireAdmin();
        
        $filename = $_GET['file'];
        $backupDir = __DIR__ . '/../../data/backups';
        $filepath = $backupDir . '/' . $filename;
        
        if (!file_exists($filepath)) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => '文件不存在'
            ]);
            exit();
        }
        
        // 设置下载头
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit();
    }
    
    // 只处理 POST 请求
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => '仅支持 POST 请求'
        ]);
        exit();
    }
    
    // 获取请求数据
    $input = json_decode(file_get_contents('php://input'), true);
    $type = $input['type'] ?? 'all';
    
    $dataDir = __DIR__ . '/../../data';
    $backupDir = __DIR__ . '/../../data/backups';
    
    // 创建备份目录
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    // 生成备份文件名
    $timestamp = date('Ymd_His');
    $zipFilename = "backup_{$type}_{$timestamp}.zip";
    $zipPath = $backupDir . '/' . $zipFilename;
    
    // 根据类型确定要备份的文件
    $filesToBackup = [];
    
    switch ($type) {
        case 'all':
            // 备份所有数据
            createZip($dataDir, $zipPath);
            break;
            
        case 'content':
            // 备份内容数据
            $contentFiles = [];
            
            // 帖子内容
            if (is_dir($dataDir . '/content')) {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($dataDir . '/content', RecursiveDirectoryIterator::SKIP_DOTS)
                );
                foreach ($iterator as $file) {
                    if ($file->isFile() && $file->getExtension() === 'md') {
                        $relativePath = str_replace($dataDir . '/', '', $file->getPathname());
                        $contentFiles[] = $relativePath;
                    }
                }
            }
            
            // 回复数据
            if (is_dir($dataDir . '/replies')) {
                $iterator = new DirectoryIterator($dataDir . '/replies');
                foreach ($iterator as $file) {
                    if ($file->isFile() && $file->getExtension() === 'json') {
                        $contentFiles[] = 'replies/' . $file->getFilename();
                    }
                }
            }
            
            if (empty($contentFiles)) {
                throw new Exception('没有找到要备份的内容数据');
            }
            
            createZip($dataDir, $zipPath, $contentFiles);
            break;
            
        case 'users':
            // 备份用户数据
            $userFiles = [];
            
            // 用户数据
            if (file_exists($dataDir . '/users.php')) {
                $userFiles[] = 'users.php';
            }
            
            // 用户通知
            if (is_dir($dataDir . '/user_notifications')) {
                $iterator = new DirectoryIterator($dataDir . '/user_notifications');
                foreach ($iterator as $file) {
                    if ($file->isFile() && $file->getExtension() === 'json') {
                        $userFiles[] = 'user_notifications/' . $file->getFilename();
                    }
                }
            }
            
            // 会话数据
            if (file_exists($dataDir . '/sessions.php')) {
                $userFiles[] = 'sessions.php';
            }
            
            if (empty($userFiles)) {
                throw new Exception('没有找到要备份的用户数据');
            }
            
            createZip($dataDir, $zipPath, $userFiles);
            break;
            
        default:
            throw new Exception('不支持的备份类型');
    }
    
    // 检查 ZIP 文件是否创建成功
    if (!file_exists($zipPath)) {
        throw new Exception('备份文件创建失败');
    }
    
    // 发送文件
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipFilename . '"');
    header('Content-Length: ' . filesize($zipPath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    // 输出文件内容
    readfile($zipPath);
    
    // 可选：删除临时备份文件（如果需要保留备份，可以注释掉）
    // unlink($zipPath);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '备份失败：' . $e->getMessage()
    ]);
}
