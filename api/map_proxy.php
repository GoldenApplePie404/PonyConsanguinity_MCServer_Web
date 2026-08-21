<?php
// ⚠️ 已弃用：卫星地图现由 pages/map.html 的 iframe 直连实现，本代理前端零引用，仅保留存档。
// 同时已完成安全加固（协议/端口白名单 + 禁重定向跟随），若后续不再使用可安全删除。

// 地图代理脚本
// 解决 HTTPS 页面加载 HTTP iframe 的混合内容问题

require_once 'config.php';

// 设置 CORS 和安全头
set_cors_headers();
set_security_headers();

// 获取请求参数
$mapUrl = $_GET['url'] ?? '';

// 默认地图服务器地址
$defaultMapUrl = 'https://xxxxxxx.xxxxx.xx/';

// 如果没有指定URL，使用默认地址
if (empty($mapUrl)) {
    $mapUrl = $defaultMapUrl;
}

// 解析 URL 并做白名单校验（加固：2026-08-17）
$allowedHosts = ['xxx.xxx.xxx.xxx', 'xxxxxxx.xxxxx.xx'];
$parsedUrl = parse_url($mapUrl);

if (!$parsedUrl || !isset($parsedUrl['host'])) {
    json_response(false, '不允许的URL', null, 403);
}

// ① 协议白名单：仅允许 http/https（防止 file:// 等协议读取本地文件）
$scheme = strtolower($parsedUrl['scheme'] ?? '');
if (!in_array($scheme, ['http', 'https'])) {
    json_response(false, '不支持的协议', null, 403);
}

// ② host 白名单（大小写不敏感）
if (!in_array(strtolower($parsedUrl['host']), $allowedHosts)) {
    json_response(false, '不允许的URL', null, 403);
}

// ③ 端口白名单：仅 80/443（未显式写端口时按协议默认，防止 :3306 等端口探测）
$port = $parsedUrl['port'] ?? (($scheme === 'https') ? 443 : 80);
if (!in_array($port, [80, 443])) {
    json_response(false, '不允许的端口', null, 403);
}

// 发起请求
$ch = curl_init($mapUrl);

// 设置 cURL 选项
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// ④ 禁止跟随重定向：防止目标 302 跳转到内网/未白名单地址绕过校验
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
// ⑤ 协议双保险：仅允许 http/https
curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
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