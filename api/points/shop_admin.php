<?php
/**
 * 积分商城 - 管理员 API
 * 商品 CRUD：列表 / 新增 / 编辑 / 删除 / 上下架
 */

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helper.php';
require_once __DIR__ . '/../secure_data.php';
require_once __DIR__ . '/../../includes/auth_helper.php';

// 仅管理员可操作（使用 getCurrentUser，不会自动终止脚本）
$user = AuthHelper::getCurrentUser();
$isAdmin = $user && ($user['role'] ?? '') === 'admin';

if (!$isAdmin) {
    json_response(false, '无权限，需要管理员登录');
}

$action = $_GET['action'] ?? '';
$dir = __DIR__ . '/../../data/shop_items';

if (!is_dir($dir)) mkdir($dir, 0755, true);

// POST 解析
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $json = json_decode($input, true);
    if ($json) {
        $action = $json['action'] ?? $action;
        $_POST = $json;
        // 将 token 注入 $_GET 供 AuthHelper::extractToken 读取（php://input 已被消费）
        if (!empty($json['token'])) {
            $_GET['token'] = $json['token'];
        }
    }
}

switch ($action) {
    case 'list':      adminListProducts($dir); break;
    case 'create':    adminCreateProduct($dir); break;
    case 'update':    adminUpdateProduct($dir); break;
    case 'delete':    adminDeleteProduct($dir); break;
    case 'toggle':    adminToggleProduct($dir); break;
    default:          json_response(false, '无效的操作');
}

function adminListProducts($dir) {
    $products = [];
    foreach (scandir($dir) as $f) {
        $ext = pathinfo($f, PATHINFO_EXTENSION);
        if ($ext !== 'json') continue;
        $p = json_decode(file_get_contents("$dir/$f"), true);
        if ($p) {
            $p['filename'] = $f;
            $products[] = $p;
        }
    }
    json_response(true, '', ['products' => $products]);
}

function adminCreateProduct($dir) {
    $id = $_POST['id'] ?? '';
    if (!$id || !preg_match('/^[a-zA-Z0-9_-]+$/', $id)) {
        json_response(false, '商品 ID 只能包含字母、数字、下划线、连字符');
    }
    $file = "$dir/$id.json";
    if (file_exists($file)) json_response(false, '商品 ID 已存在');

    $product = [
        'id' => $id,
        'name' => $_POST['name'] ?? '未命名商品',
        'description' => $_POST['description'] ?? '',
        'price' => (int)($_POST['price'] ?? 0),
        'stock' => (int)($_POST['stock'] ?? -1),
        'status' => $_POST['status'] ?? 'on_sale',
        'level_requirement' => (int)($_POST['level_requirement'] ?? 0),
        'style' => $_POST['style'] ?? 'fa-gift',
        'category' => $_POST['category'] ?? 'hot',
        'sold' => 0,
        'effect' => null,
    ];

    if (file_put_contents($file, json_encode($product, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))) {
        json_response(true, '商品创建成功', ['product' => $product]);
    } else {
        json_response(false, '写入文件失败');
    }
}

function adminUpdateProduct($dir) {
    $id = $_POST['id'] ?? '';
    if (!$id) json_response(false, '商品 ID 不能为空');
    $file = "$dir/$id.json";
    if (!file_exists($file)) json_response(false, '商品不存在');

    $product = json_decode(file_get_contents($file), true);
    if (!$product) json_response(false, '商品数据解析失败');

    // 更新字段（仅提供值的才更新）
    foreach (['name', 'description', 'style', 'category', 'status'] as $k) {
        if (isset($_POST[$k])) $product[$k] = $_POST[$k];
    }
    foreach (['price', 'stock', 'level_requirement', 'sold'] as $k) {
        if (isset($_POST[$k])) $product[$k] = (int)$_POST[$k];
    }
    // effect 字段不在此处设置，需手动编辑 JSON 文件

    if (file_put_contents($file, json_encode($product, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))) {
        json_response(true, '商品更新成功', ['product' => $product]);
    } else {
        json_response(false, '写入文件失败');
    }
}

function adminDeleteProduct($dir) {
    $id = $_POST['id'] ?? '';
    if (!$id) json_response(false, '商品 ID 不能为空');
    $file = "$dir/$id.json";
    if (!file_exists($file)) json_response(false, '商品不存在');
    if (unlink($file)) {
        json_response(true, '商品已删除');
    } else {
        json_response(false, '删除失败');
    }
}

function adminToggleProduct($dir) {
    $id = $_POST['id'] ?? '';
    if (!$id) json_response(false, '商品 ID 不能为空');
    $file = "$dir/$id.json";
    if (!file_exists($file)) json_response(false, '商品不存在');

    $product = json_decode(file_get_contents($file), true);
    $product['status'] = ($product['status'] ?? 'on_sale') === 'on_sale' ? 'off_sale' : 'on_sale';

    if (file_put_contents($file, json_encode($product, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))) {
        json_response(true, '状态已切换', ['product' => $product]);
    } else {
        json_response(false, '写入文件失败');
    }
}
