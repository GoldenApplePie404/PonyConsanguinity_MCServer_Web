<?php
// exchange.php
// 处理积分兑换功能

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
$action = $_POST['action'] ?? '';

// 商品配置
$productConfig = [
    'exp' => [
        'name' => '经验值礼包',
        'price' => 50,
        'reward_type' => 'experience',
        'reward_amount' => 100,
        'description' => '100点经验值'
    ],
    'double_exp' => [
        'name' => '经验双倍卡',
        'price' => 150,
        'reward_type' => 'buff',
        'buff_type' => 'double_exp',
        'duration' => 24,  // 小时
        'description' => '24小时双倍经验'
    ],
    'chest' => [
        'name' => '神秘宝箱',
        'price' => 80,
        'reward_type' => 'random',
        'description' => '随机奖励'
    ],
    'resign' => [
        'name' => '补签卡',
        'price' => 30,
        'reward_type' => 'item',
        'item_type' => 'resign_card',
        'description' => '补签卡 x1'
    ],
    'points_boost' => [
        'name' => '积分加成卡',
        'price' => 120,
        'reward_type' => 'buff',
        'buff_type' => 'points_boost',
        'duration' => 168,  // 7天 = 168小时
        'description' => '7天积分加成(+50%)'
    ]
];

switch ($action) {
    case 'get_products':
        // 获取商品列表
        $products = [];
        foreach ($productConfig as $key => $config) {
            $products[$key] = [
                'name' => $config['name'],
                'price' => $config['price'],
                'description' => $config['description']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'data' => $products
        ]);
        break;
        
    case 'exchange':
        $productType = $_POST['product_type'] ?? '';
        $price = intval($_POST['price'] ?? 0);
        
        // 验证商品类型
        if (!isset($productConfig[$productType])) {
            echo json_encode([
                'success' => false,
                'message' => '未知的商品类型'
            ]);
            exit;
        }
        
        $config = $productConfig[$productType];
        
        // 验证价格
        if ($price !== $config['price']) {
            echo json_encode([
                'success' => false,
                'message' => '价格不匹配'
            ]);
            exit;
        }
        
        // 获取用户信息
        $user = $manager->getUser($username);
        if (!$user) {
            echo json_encode([
                'success' => false,
                'message' => '用户不存在'
            ]);
            exit;
        }
        
        $currentPoints = isset($user['points']) ? intval($user['points']) : 0;
        
        // 检查积分是否足够
        if ($currentPoints < $price) {
            echo json_encode([
                'success' => false,
                'message' => '积分不足'
            ]);
            exit;
        }
        
        // 扣除积分
        $success = $manager->removePoints($username, $price);
        
        if ($success) {
            // 根据商品类型发放奖励
            $reward = '';
            $rewardData = [];
            
            switch ($productType) {
                case 'exp':
                    $expAmount = $config['reward_amount'];
                    $manager->addExperience($username, $expAmount);
                    $reward = $expAmount . ' 经验值';
                    $rewardData = ['type' => 'experience', 'amount' => $expAmount];
                    break;
                    
                case 'double_exp':
                    // 记录双倍经验状态
                    $endTime = date('Y-m-d H:i:s', strtotime('+24 hours'));
                    $manager->addBuff($username, 'double_exp', $endTime);
                    $reward = '24小时双倍经验';
                    $rewardData = ['type' => 'buff', 'buff_type' => 'double_exp', 'end_time' => $endTime];
                    break;
                    
                case 'chest':
                    // 随机奖励
                    $randomReward = generateRandomReward();
                    
                    if ($randomReward['type'] === 'points') {
                        $manager->addPoints($username, $randomReward['amount']);
                    } else {
                        $manager->addExperience($username, $randomReward['amount']);
                    }
                    
                    $reward = $randomReward['name'];
                    $rewardData = $randomReward;
                    break;
                    
                case 'resign':
                    // 补签卡
                    $manager->addItem($username, 'resign_card', 1);
                    $reward = '补签卡 x1';
                    $rewardData = ['type' => 'item', 'item_type' => 'resign_card', 'amount' => 1];
                    break;
                    
                case 'points_boost':
                    // 积分加成卡
                    $endTime = date('Y-m-d H:i:s', strtotime('+7 days'));
                    $manager->addBuff($username, 'points_boost', $endTime);
                    $reward = '7天积分加成(+50%)';
                    $rewardData = ['type' => 'buff', 'buff_type' => 'points_boost', 'end_time' => $endTime];
                    break;
                    
                default:
                    // 未知类型，退还积分
                    $manager->addPoints($username, $price);
                    echo json_encode([
                        'success' => false,
                        'message' => '未知的商品类型'
                    ]);
                    exit;
            }
            
            // 获取更新后的用户信息
            $user = $manager->getUser($username);
            
            echo json_encode([
                'success' => true,
                'message' => '兑换成功',
                'data' => [
                    'points' => $user['points'],
                    'experience' => $user['experience'],
                    'reward' => $reward,
                    'reward_data' => $rewardData
                ]
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => '兑换失败，请稍后重试'
            ]);
        }
        break;
        
    case 'get_inventory':
        // 获取用户背包
        $inventory = $manager->getInventory($username);
        $buffs = $manager->getBuffs($username);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'items' => $inventory,
                'buffs' => $buffs
            ]
        ]);
        break;
        
    case 'use_item':
        // 使用物品
        $itemType = $_POST['item_type'] ?? '';
        
        if (empty($itemType)) {
            echo json_encode([
                'success' => false,
                'message' => '物品类型不能为空'
            ]);
            exit;
        }
        
        $result = $manager->useItem($username, $itemType);
        echo json_encode($result);
        break;
        
    default:
        echo json_encode([
            'success' => false,
            'message' => '无效的操作'
        ]);
        break;
}

// 生成随机奖励
function generateRandomReward() {
    $rewards = [
        ['type' => 'points', 'amount' => 50, 'name' => '50积分', 'weight' => 30],
        ['type' => 'points', 'amount' => 100, 'name' => '100积分', 'weight' => 25],
        ['type' => 'exp', 'amount' => 80, 'name' => '80经验值', 'weight' => 20],
        ['type' => 'points', 'amount' => 200, 'name' => '200积分', 'weight' => 15],
        ['type' => 'exp', 'amount' => 200, 'name' => '200经验值', 'weight' => 10]
    ];
    
    $totalWeight = array_sum(array_column($rewards, 'weight'));
    $random = mt_rand(1, $totalWeight);
    
    $currentWeight = 0;
    foreach ($rewards as $reward) {
        $currentWeight += $reward['weight'];
        if ($random <= $currentWeight) {
            return $reward;
        }
    }
    
    return $rewards[0];
}
?>
