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
    if (file_exists($file)) {
        return include $file;
    }
    return [];
}

function secureWriteData($file, $data, $requireToken = false) {
    verifyDataAccess($requireToken);
    $dir = dirname($file);
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
    // 对于 PHP 文件，使用 include 格式写入
    $content = "<?php\n";
    $content .= "if (!defined('ACCESS_ALLOWED')) {\n";
    $content .= "    header('HTTP/1.1 403 Forbidden');\n";
    $content .= "    exit;\n";
    $content .= "}\n\n";
    $content .= "return " . var_export($data, true) . ";\n";
    $content .= "?>";
    return file_put_contents($file, $content) !== false;
}
?>