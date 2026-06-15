<?php
/**
 * 积分商城 API — 重构版
 * 仅保留 get_products / buy_product 两项核心操作
 * 用户数据直接读写 data/users.php，不再依赖 PointsManager
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

// ─── 请求解析 ───────────────────────────────────────
$action = $_GET['action'] ?? '';
$username = 'guest';

// POST 请求从 JSON body 读取参数，并提取 token 给 AuthHelper
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $json = json_decode($input, true);
    if ($json) {
        $action = $json['action'] ?? $action;
        $_POST = $json;
        if (!empty($json['token'])) {
            $_GET['token'] = $json['token'];
        }
    }
}

// 需要登录的操作才校验身份
$needLogin = ['buy_product'];
if (in_array($action, $needLogin)) {
    try {
        $session = AuthHelper::requireLogin();
        $username = $session['username'];
    } catch (Exception $e) {
        json_response(false, '请先登录');
    }
} else {
    // 浏览商品：可选登录
    $session = AuthHelper::getSession();
    $username = $session ? $session['username'] : 'guest';
}

// ─── 路由 ──────────────────────────────────────────
switch ($action) {
    case 'get_products': getProducts($username); break;
    case 'buy_product':  buyProduct($username);  break;
    default: json_response(false, '无效的操作');
}

// ─── 函数 ──────────────────────────────────────────

/**
 * 获取商品列表（从 data/shop_items/ 目录读取 .php / .json 文件）
 */
function getProducts($username) {
    $dir = __DIR__ . '/../../data/shop_items';
    $products = [];

    if (!is_dir($dir)) {
        json_response(true, '', [ 'products' => [] ]);
        return;
    }

    $files = scandir($dir);

    // 获取用户等级（用于过滤等级限制）
    $userLevel = 0;
    if ($username !== 'guest') {
        $users = secureReadData(USERS_FILE);
        $exp = $users[$username]['experience'] ?? 0;
        $userLevel = floor($exp / 100);
    }

    foreach ($files as $file) {
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        if (!in_array($ext, ['php', 'json'])) continue;

        $product = loadProduct($dir . '/' . $file, $ext);
        if (!$product) continue;
        if (($product['status'] ?? '') !== 'on_sale') continue;
        if (($product['level_requirement'] ?? 0) > $userLevel) continue;

        $products[] = $product;
    }

    json_response(true, '', [ 'products' => $products ]);
}

/**
 * 购买商品
 */
function buyProduct($username) {
    $productId = $_POST['product_id'] ?? '';
    if (!$productId) json_response(false, '商品 ID 不能为空');

    $product = loadProductById($productId);
    if (!$product) json_response(false, '商品不存在');
    if (($product['status'] ?? '') !== 'on_sale') json_response(false, '商品已下架');

    // 等级检查
    $users = secureReadData(USERS_FILE);
    if (!isset($users[$username])) json_response(false, '用户不存在');
    $userExp = $users[$username]['experience'] ?? 0;
    $userLevel = floor($userExp / 100);
    if (($product['level_requirement'] ?? 0) > $userLevel) {
        json_response(false, '等级不足，需要 ' . $product['level_requirement'] . ' 级');
    }

    // 库存检查
    if (isset($product['stock']) && $product['stock'] !== -1 && $product['stock'] <= 0) {
        json_response(false, '商品已售罄');
    }

    // 积分检查
    $price = (int)($product['price'] ?? 0);
    $currentPoints = (int)($users[$username]['points'] ?? 0);
    if ($currentPoints < $price) {
        json_response(false, "积分不足，需要 {$price} 积分，当前只有 {$currentPoints} 积分");
    }

    // ─── 扣积分 ──────────────────────────────────
    $users[$username]['points'] = $currentPoints - $price;

    // ─── 执行效果 ──────────────────────────────────
    $effect = $product['effect'] ?? null;
    $resp = [ 'success' => true, 'product_name' => $product['name'], 'product_id' => $product['id'], 'price' => $price ];

    if ($effect) {
        switch ($effect['type']) {
            case 'experience':
                $users[$username]['experience'] = ($users[$username]['experience'] ?? 0) + (int)$effect['value'];
                $newExp = $users[$username]['experience'];
                $resp['message'] = '购买成功！获得 ' . $effect['value'] . ' 点经验值';
                $resp['experience'] = $newExp;
                $resp['level'] = floor($newExp / 100);
                $resp['effect'] = $effect;
                break;

            case 'points':
                $users[$username]['points'] += (int)$effect['value'];
                $resp['message'] = '购买成功！获得 ' . $effect['value'] . ' 积分';
                $resp['effect'] = $effect;
                break;

            case 'buff':
                $resp['message'] = '激活成功！效果持续中';
                $resp['effect'] = $effect;
                $resp['buff_id'] = $effect['buff_id'] ?? null;
                $resp['duration'] = $effect['duration'] ?? 0;
                break;

            default:
                $resp['message'] = '购买成功！';
        }
    } else {
        $resp['message'] = '购买成功！';
    }

    $resp['remaining_points'] = $users[$username]['points'];

    // 扣除库存
    if (isset($product['stock']) && $product['stock'] !== -1) {
        $product['stock']--;
    }

    secureWriteData(USERS_FILE, $users);
    json_response(true, $resp['message'], $resp);
}

// ─── 商品加载辅助 ─────────────────────────────────

function loadProduct($path, $ext) {
    if ($ext === 'php') {
        return loadProductPhp($path);
    }
    return json_decode(file_get_contents($path), true);
}

/**
 * 兼容旧 .php 商品文件（优先使用 .json）
 * 手动匹配 $item = [ ... ] 中的数组，支持嵌套
 */
function loadProductPhp($path) {
    $content = file_get_contents($path);
    if (!$content) return null;

    $pos = strpos($content, '$item');
    if ($pos === false) return null;
    $braceStart = strpos($content, '[', $pos);
    if ($braceStart === false) return null;

    $depth = 0;
    $inSingle = $inDouble = $inLine = $inBlock = false;
    $len = strlen($content);

    for ($i = $braceStart; $i < $len; $i++) {
        $ch = $content[$i];
        $prev = $i > 0 ? $content[$i - 1] : '';

        if (!$inSingle && !$inDouble && !$inLine && !$inBlock) {
            if ($ch === '/' && $i+1 < $len && $content[$i+1] === '/') { $inLine = true; continue; }
            if ($ch === '/' && $i+1 < $len && $content[$i+1] === '*') { $inBlock = true; $i++; continue; }
        }
        if ($inLine && $ch === "\n") { $inLine = false; continue; }
        if ($inBlock && $ch === '*' && $i+1 < $len && $content[$i+1] === '/') { $inBlock = false; $i++; continue; }
        if ($inLine || $inBlock) continue;

        if ($ch === "'" && !$inDouble && $prev !== '\\') { $inSingle = !$inSingle; continue; }
        if ($ch === '"' && !$inSingle && $prev !== '\\') { $inDouble = !$inDouble; continue; }
        if ($inSingle || $inDouble) { if ($ch === '\\') $i++; continue; }

        if ($ch === '[') { $depth++; }
        elseif ($ch === ']') {
            $depth--;
            if ($depth === 0) {
                try {
                    eval('$item = ' . substr($content, $braceStart, $i - $braceStart + 1) . ';');
                    return $item ?? null;
                } catch (Throwable $e) { return null; }
            }
        }
    }
    return null;
}

function loadProductById($id) {
    $dir = __DIR__ . '/../../data/shop_items';
    foreach (['php', 'json'] as $ext) {
        $f = $dir . '/' . $id . '.' . $ext;
        if (file_exists($f)) return loadProduct($f, $ext);
    }
    return null;
}
