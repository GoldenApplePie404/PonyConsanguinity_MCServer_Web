<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/helper.php';
require_once dirname(__DIR__) . '/secure_data.php';
require_once dirname(__DIR__) . '/UserManager.php';
require_once dirname(__DIR__) . '/../includes/auth_helper.php';
require_once 'ImageManager.php';

set_cors_headers();
set_security_headers();

if (!AuthHelper::validateToken()) {
    json_response(false, '未提供认证令牌', null, 401);
}

$username = AuthHelper::getUsernameFromToken();
$userManager = new UserManager();
$user = $userManager->getUser($username);

if (!$user || $user['role'] !== 'admin') {
    json_response(false, '权限不足', null, 403);
}

$imageManager = new ImageManager();

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'list':
        $images = $imageManager->getAllImages(1000, 0);
        $stats = $imageManager->getStatistics();
        
        json_response(true, '获取图片列表成功', [
            'images' => $images,
            'total' => count($images),
            'stats' => $stats
        ]);
        break;
        
    case 'get':
        $imageId = isset($_GET['id']) ? $_GET['id'] : '';
        if (!$imageId) {
            json_response(false, '缺少图片ID', null, 400);
        }
        
        $image = $imageManager->getImage($imageId);
        if (!$image) {
            json_response(false, '图片不存在', null, 404);
        }
        
        json_response(true, '获取图片信息成功', $image);
        break;
        
    case 'delete':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(false, '只允许 POST 请求', null, 405);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $imageId = isset($input['id']) ? $input['id'] : '';
        
        if (!$imageId) {
            json_response(false, '缺少图片ID', null, 400);
        }
        
        $image = $imageManager->getImage($imageId);
        if (!$image) {
            json_response(false, '图片不存在', null, 404);
        }
        
        $images = json_decode(file_get_contents(dirname(dirname(__DIR__)) . '/data/images.json'), true);
        unset($images[$imageId]);
        file_put_contents(dirname(dirname(__DIR__)) . '/data/images.json', json_encode($images, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        $uploadDir = dirname(dirname(__DIR__)) . '/assets/img/text_img';
        $filePath = $uploadDir . '/' . $image['filename'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        json_response(true, '删除成功');
        break;
        
    case 'upload':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(false, '只允许 POST 请求', null, 405);
        }
        
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            json_response(false, '没有文件被上传', null, 400);
        }
        
        $result = $imageManager->uploadImage(
            $_FILES['image'],
            $user['id'],
            $user['username'],
            'text_img'
        );
        
        if ($result['success']) {
            json_response(true, '上传成功', $result['data']);
        } else {
            json_response(false, $result['message'], $result['errors'] ?? null, 400);
        }
        break;
        
    default:
        json_response(false, '无效的操作', null, 400);
        break;
}
?>
