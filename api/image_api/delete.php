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

// 只允许 POST 请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, '只允许 POST 请求', null, 405);
}

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

// 获取请求数据
$data = get_post_data();
$imageId = $data['image_id'] ?? '';

if (empty($imageId)) {
    json_response(false, '图片ID不能为空', null, 400);
}

// 初始化图片管理器
$imageManager = new ImageManager();

// 删除图片
$result = $imageManager->deleteImage($imageId, $user['id']);

// 返回结果
if ($result['success']) {
    json_response(true, $result['message']);
} else {
    json_response(false, $result['message'], null, 400);
}
?>