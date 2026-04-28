<?php
// get_profile.php
// 获取用户资料信息

if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}

require_once 'config.php';
require_once 'helper.php';
require_once 'secure_data.php';
require_once 'UserManager.php';

header('Content-Type: application/json; charset=utf-8');

session_start();
require_once '../includes/auth_helper.php';

// 验证登录
if (!AuthHelper::validateToken()) {
    echo json_encode([
        'success' => false,
        'message' => '请先登录'
    ]);
    exit;
}

$username = AuthHelper::getUsernameFromToken();
$manager = new UserManager();

// 获取用户信息
$user = $manager->getUser($username);

if ($user) {
    // 获取背包和BUFF信息
    $inventory = $manager->getInventory($username);
    $buffs = $manager->getBuffs($username);
    
    // 物品名称映射
    $itemNames = [
        'resign_card' => '补签卡'
    ];
    
    // BUFF名称映射
    $buffNames = [
        'double_exp' => '双倍经验',
        'points_boost' => '积分加成'
    ];
    
    // 格式化背包数据
    $formattedInventory = [];
    foreach ($inventory as $itemType => $count) {
        $formattedInventory[] = [
            'type' => $itemType,
            'name' => $itemNames[$itemType] ?? $itemType,
            'count' => $count
        ];
    }
    
    // 格式化BUFF数据
    $formattedBuffs = [];
    foreach ($buffs as $buffType => $buff) {
        $formattedBuffs[] = [
            'type' => $buffType,
            'name' => $buffNames[$buffType] ?? $buffType,
            'end_time' => $buff['end_time']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'username' => $user['username'],
            'email' => $user['email'] ?? '',
            'role' => $user['role'] ?? 'user',
            'points' => $user['points'] ?? 0,
            'experience' => $user['experience'] ?? 0,
            'email_verified' => $user['email_verified'] ?? false,
            'created_at' => $user['created_at'] ?? '',
            'last_login' => $user['last_login'] ?? '',
            'inventory' => $formattedInventory,
            'buffs' => $formattedBuffs
        ]
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => '用户不存在'
    ]);
}
?>