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

// 检查是否有文件上传
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    json_response(false, '没有文件被上传', null, 400);
}

// 初始化图片管理器
$imageManager = new ImageManager();

// 上传图片
$result = $imageManager->uploadImage(
    $_FILES['image'],
    $user['id'],
    $user['username'],
    'text_img'
);

// 返回结果
if ($result['success']) {
    json_response(true, '上传成功', $result['data']);
} else {
    json_response(false, $result['message'], $result['errors'] ?? null, 400);
}
?>