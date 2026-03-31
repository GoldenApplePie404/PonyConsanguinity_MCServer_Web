<?php
/**
 * 黄金券兑换
 * 购买后立即获得 100 点经验值
 */

// 物品属性
$item = [
    'id' => 'golden_ticket',
    'name' => '黄金券兑换',
    'description' => '使用后可获得黄金券 x1，可在游戏内获得50黄金券。',
    'price' => 500,
    'stock' => 0, // -1 表示无限库存
    'status' => 'on_sale',
    'level_requirement' => 0,
    'style' => '🎫',
    'category' => 'hot',
    'effect' => [
        'type' => 'points',
        'value' => 50
    ]
];

// 如果在 shop 上下文中被调用，设置返回值
if (isset($context) && $context === 'shop') {
    // 不修改 $item，让它保持原值供 buyAndUse 使用
    return;
}

// 如果直接访问此文件，返回物品信息
if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($item, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 购买并立即使用此物品
 * @param string $userId 用户 ID
 * @param PointsManager $pointsManager 积分管理器
 * @return array 操作结果
 */
function buyAndUse($userId, $pointsManager) {
    global $item;
    
    // 检查积分是否足够
    $currentPoints = $pointsManager->getUserPoints($userId);
    if ($currentPoints < $item['price']) {
        return [
            'success' => false,
            'message' => '积分不足',
            'current_points' => $currentPoints,
            'required' => $item['price']
        ];
    }
    
    // 扣除积分
    $pointsManager->reducePoints($userId, $item['price']);
    
    // 根据效果类型执行操作
    $effect = $item['effect'];
    
    switch ($effect['type']) {
        case 'points':
            // 增加黄金券
            $result = $pointsManager->addPoints($userId, $effect['value']);
            
            return [
                'success' => true,
                'message' => '购买成功！获得 ' . $effect['value'] . ' 个黄金券',
                'product_name' => $item['name'],
                'product_id' => $item['id'],
                'price' => $item['price'],
                'remaining_points' => $pointsManager->getUserPoints($userId),
                'points' => $result['points'],
                'level' => $result['level'],
                'leveled_up' => $result['leveled_up'] ?? false,
                'effect' => $effect
            ];
        
        case 'points':
            // 增加积分（相当于用积分购买更多积分，通常用于促销活动）
            $pointsManager->addPoints($userId, $effect['value']);
            
            return [
                'success' => true,
                'message' => '购买成功！获得 ' . $effect['value'] . ' 积分',
                'product_name' => $item['name'],
                'product_id' => $item['id'],
                'price' => $item['price'],
                'remaining_points' => $pointsManager->getUserPoints($userId),
                'effect' => $effect
            ];
        
        case 'buff':
            // 激活 BUFF（暂时只返回成功，BUFF 系统后续实现）
            return [
                'success' => true,
                'message' => '激活成功！效果持续中',
                'product_name' => $item['name'],
                'product_id' => $item['id'],
                'price' => $item['price'],
                'remaining_points' => $pointsManager->getUserPoints($userId),
                'effect' => $effect,
                'buff_id' => $effect['buff_id'] ?? null,
                'duration' => $effect['duration'] ?? 0
            ];
        
        default:
            return [
                'success' => false,
                'message' => '未知的效果类型'
            ];
    }
}

// 如果在物品上下文中被调用，返回物品对象
return $item;
