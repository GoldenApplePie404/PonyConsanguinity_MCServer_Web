<?php
// 地图代理脚本
// 解决 HTTPS 页面加载 HTTP iframe 的混合内容问题

require_once 'config.php';

// 设置 CORS 和安全头
set_cors_headers();
set_security_headers();

// 获取请求参数
$mapUrl = $_GET['url'] ?? '';

// 默认地图服务器地址
$defaultMapUrl = 'http://115.231.176.218:11823/';

// 如果没有指定URL，使用默认地址
if (empty($mapUrl)) {
    $mapUrl = $defaultMapUrl;
}

// 验证URL（只允许访问指定的地图服务器）
$allowedHosts = ['115.231.176.218'];
$parsedUrl = parse_url($mapUrl);

if (!$parsedUrl || !isset($parsedUrl['host']) || !in_array($parsedUrl['host'], $allowedHosts)) {
    json_response(false, '不允许的URL', null, 403);
}

// 发起请求
$ch = curl_init($mapUrl);

// 设置 cURL 选项
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

// 设置 User-Agent
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');

// 设置请求头（转发客户端的请求头）
$headers = [];
if (isset($_SERVER['HTTP_ACCEPT'])) {
    $headers[] = 'Accept: ' . $_SERVER['HTTP_ACCEPT'];
}
if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
    $headers[] = 'Accept-Language: ' . $_SERVER['HTTP_ACCEPT_LANGUAGE'];
}
if (!empty($headers)) {
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
}

// 执行请求
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$error = curl_error($ch);

curl_close($ch);

// 检查错误
if ($error) {
    json_response(false, '无法连接到地图服务器: ' . $error, null, 502);
}

// 设置响应头
if ($contentType) {
    header('Content-Type: ' . $contentType);
}

// 设置缓存头（缓存5分钟）
header('Cache-Control: public, max-age=300');

// 返回响应
http_response_code($httpCode);
echo $response;
?>