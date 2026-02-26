<?php
define('DATA_ACCESS_TOKEN', '8f42a73e6b9f4c8d9e2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e'); // 数据访问令牌

function verifyDataAccess($requireToken = true) {
    if (!$requireToken) {
        return;
    }
    $token = $_GET['token'] ?? $_SERVER['HTTP_X_DATA_TOKEN'] ?? '';
    if ($token !== DATA_ACCESS_TOKEN) {
        header('HTTP/1.1 403 Forbidden');
        exit;
    }
}

function secureReadData($file, $requireToken = false) {
    verifyDataAccess($requireToken);
    
    if (!file_exists($file)) {
        error_log("secureReadData: 文件不存在 - " . $file);
        return [];
    }
    
    if (!is_readable($file)) {
        error_log("secureReadData: 文件不可读 - " . $file);
        return [];
    }
    
    $data = @include $file;
    if ($data === false) {
        error_log("secureReadData: 文件包含失败 - " . $file);
        return [];
    }
    
    return $data;
}

function secureWriteData($file, $data, $requireToken = false) {
    verifyDataAccess($requireToken);
    
    $dir = dirname($file);
    if (!file_exists($dir)) {
        if (!mkdir($dir, 0755, true)) {
            error_log("secureWriteData: 无法创建目录 - " . $dir);
            return false;
        }
    }
    
    if (!is_writable($dir)) {
        error_log("secureWriteData: 目录不可写 - " . $dir);
        return false;
    }
    
    // 对于 PHP 文件，使用 include 格式写入
    $content = "<?php\n";
    $content .= "if (!defined('ACCESS_ALLOWED')) {\n";
    $content .= "    header('HTTP/1.1 403 Forbidden');\n";
    $content .= "    exit;\n";
    $content .= "}\n\n";
    $content .= "return " . var_export($data, true) . ";\n";
    $content .= "?>";
    
    $result = @file_put_contents($file, $content, LOCK_EX);
    if ($result === false) {
        error_log("secureWriteData: 写入文件失败 - " . $file);
        return false;
    }
    
    return true;
}
?>