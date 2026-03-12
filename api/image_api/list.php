<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/helper.php';
require_once dirname(__DIR__) . '/secure_data.php';
require_once dirname(__DIR__) . '/UserManager.php';
require_once dirname(__DIR__) . '/../includes/auth_helper.php';
require_once 'ImageManager.php';

// 设置 CORS 和安全头
set_cors_headers();
set_security_headers();

// 验证用户身份
if (!AuthHelper::validateToken()) {
    json_response(false, '未提供认证令牌', null, 401);
}

$username = AuthHelper::getUsernameFromToken();
$userManager = new UserManager();
$user = $userManager->getUser($username);

if (!$user) {
    json_response(false, '用户不存在', null, 401);
}

// 初始化图片管理器
$imageManager = new ImageManager();

// 获取请求参数
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

// 获取图片列表
$images = $imageManager->getUserImages($user['id']);

// 分页处理
$total = count($images);
$images = array_slice($images, $offset, $limit);

// 返回结果
json_response(true, '获取成功', [
    'images' => $images,
    'total' => $total,
    'limit' => $limit,
    'offset' => $offset
]);
?>