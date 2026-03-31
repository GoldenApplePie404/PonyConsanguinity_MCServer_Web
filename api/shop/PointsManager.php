<?php
/**
 * 积分管理类
 * 负责用户积分的查找、增加、减少等操作
 */

class PointsManager {
    private $db;
    
    /**
     * 构造函数
     */
    public function __construct() {
        try {
            $this->db = new PDO(
                'sqlite:' . __DIR__ . '/../../data/shop.db',
                null,
                null,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $this->initDatabase();
        } catch (PDOException $e) {
            error_log('PointsManager DB Error: ' . $e->getMessage());
            throw new Exception('数据库初始化失败');
        }
    }
    
    /**
     * 初始化数据库表
     */
    private function initDatabase() {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS user_points (
                user_id TEXT PRIMARY KEY,
                points INTEGER DEFAULT 0,
                level INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS user_inventory (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id TEXT NOT NULL,
                item_id TEXT NOT NULL,
                item_name TEXT NOT NULL,
                item_type TEXT DEFAULT 'default',
                item_icon TEXT DEFAULT '',
                quantity INTEGER DEFAULT 1,
                obtained_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(user_id, item_id)
            )
        ");
        
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS exchange_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id TEXT NOT NULL,
                item_id TEXT NOT NULL,
                item_name TEXT NOT NULL,
                points_cost INTEGER NOT NULL,
                quantity INTEGER DEFAULT 1,
                exchanged_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }
    
    /**
     * 获取用户积分
     * @param string $userId 用户 ID
     * @return array ['points' => 积分，'level' => 等级]
     */
    public function getPoints($userId) {
        try {
            $stmt = $this->db->prepare('SELECT points, level FROM user_points WHERE user_id = ?');
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                return [
                    'success' => true,
                    'points' => (int)$result['points'],
                    'level' => (int)$result['level']
                ];
            }
            
            // 用户不存在，创建记录
            $this->createUser($userId);
            return [
                'success' => true,
                'points' => 0,
                'level' => 0
            ];
        } catch (Exception $e) {
            error_log('Get points error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => '获取积分失败'
            ];
        }
    }
    
    /**
     * 增加用户积分
     * @param string $userId 用户 ID
     * @param int $amount 增加的积分数
     * @return array
     */
    public function addPoints($userId, $amount) {
        if ($amount <= 0) {
            return [
                'success' => false,
                'error' => '增加的积分必须大于 0'
            ];
        }
        
        try {
            $stmt = $this->db->prepare('
                INSERT INTO user_points (user_id, points, updated_at) 
                VALUES (?, ?, CURRENT_TIMESTAMP)
                ON CONFLICT(user_id) DO UPDATE SET 
                    points = points + ?,
                    updated_at = CURRENT_TIMESTAMP
            ');
            $stmt->execute([$userId, $amount, $amount]);
            
            $newPoints = $this->getPoints($userId);
            
            return [
                'success' => true,
                'message' => "成功增加 {$amount} 积分",
                'points' => $newPoints['points'],
                'level' => $newPoints['level']
            ];
        } catch (Exception $e) {
            error_log('Add points error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => '增加积分失败'
            ];
        }
    }
    
    /**
     * 减少用户积分
     * @param string $userId 用户 ID
     * @param int $amount 减少的积分数
     * @return array
     */
    public function reducePoints($userId, $amount) {
        if ($amount <= 0) {
            return [
                'success' => false,
                'error' => '减少的积分必须大于 0'
            ];
        }
        
        try {
            // 检查积分是否足够
            $currentPoints = $this->getPoints($userId);
            if (!$currentPoints['success'] || $currentPoints['points'] < $amount) {
                return [
                    'success' => false,
                    'error' => '积分不足'
                ];
            }
            
            $stmt = $this->db->prepare('
                UPDATE user_points 
                SET points = points - ?, updated_at = CURRENT_TIMESTAMP 
                WHERE user_id = ?
            ');
            $stmt->execute([$amount, $userId]);
            
            $newPoints = $this->getPoints($userId);
            
            return [
                'success' => true,
                'message' => "成功扣除 {$amount} 积分",
                'points' => $newPoints['points'],
                'level' => $newPoints['level']
            ];
        } catch (Exception $e) {
            error_log('Reduce points error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => '扣除积分失败'
            ];
        }
    }
    
    /**
     * 设置用户等级
     * @param string $userId 用户 ID
     * @param int $level 等级
     * @return array
     */
    public function setLevel($userId, $level) {
        if ($level < 0) {
            return [
                'success' => false,
                'error' => '等级不能为负数'
            ];
        }
        
        try {
            $stmt = $this->db->prepare('
                INSERT INTO user_points (user_id, level, updated_at) 
                VALUES (?, ?, CURRENT_TIMESTAMP)
                ON CONFLICT(user_id) DO UPDATE SET 
                    level = ?,
                    updated_at = CURRENT_TIMESTAMP
            ');
            $stmt->execute([$userId, $level, $level]);
            
            return [
                'success' => true,
                'message' => "等级已设置为 {$level}",
                'level' => $level
            ];
        } catch (Exception $e) {
            error_log('Set level error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => '设置等级失败'
            ];
        }
    }
    
    /**
     * 创建用户记录
     * @param string $userId 用户 ID
     */
    private function createUser($userId) {
        $stmt = $this->db->prepare('
            INSERT OR IGNORE INTO user_points (user_id, points, level) 
            VALUES (?, 0, 0)
        ');
        $stmt->execute([$userId]);
    }
    
    /**
     * 获取用户背包物品
     * @param string $userId 用户 ID
     * @return array
     */
    public function getInventory($userId) {
        try {
            $stmt = $this->db->prepare('
                SELECT item_id, item_name, item_type, item_icon, quantity, obtained_at 
                FROM user_inventory 
                WHERE user_id = ? 
                ORDER BY obtained_at DESC
            ');
            $stmt->execute([$userId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'items' => $items
            ];
        } catch (Exception $e) {
            error_log('Get inventory error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => '获取背包失败'
            ];
        }
    }
    
    /**
     * 添加物品到背包
     * @param string $userId 用户 ID
     * @param string $itemId 物品 ID
     * @param string $itemName 物品名称
     * @param string $itemType 物品类型
     * @param string $itemIcon 物品图标
     * @param int $quantity 数量
     * @return array
     */
    public function addItemToInventory($userId, $itemId, $itemName, $itemType = 'default', $itemIcon = '', $quantity = 1) {
        try {
            $stmt = $this->db->prepare('
                INSERT INTO user_inventory (user_id, item_id, item_name, item_type, item_icon, quantity, obtained_at)
                VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                ON CONFLICT(user_id, item_id) DO UPDATE SET
                    quantity = quantity + ?
            ');
            $stmt->execute([$userId, $itemId, $itemName, $itemType, $itemIcon, $quantity, $quantity]);
            
            return [
                'success' => true,
                'message' => "成功获得 {$itemName} x{$quantity}"
            ];
        } catch (Exception $e) {
            error_log('Add item error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => '添加物品失败'
            ];
        }
    }
    
    /**
     * 减少背包物品
     * @param string $userId 用户 ID
     * @param string $itemId 物品 ID
     * @param int $quantity 数量
     * @return array
     */
    public function reduceItemFromInventory($userId, $itemId, $quantity = 1) {
        try {
            // 检查物品是否足够
            $stmt = $this->db->prepare('SELECT quantity FROM user_inventory WHERE user_id = ? AND item_id = ?');
            $stmt->execute([$userId, $itemId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result || $result['quantity'] < $quantity) {
                return [
                    'success' => false,
                    'error' => '物品数量不足'
                ];
            }
            
            if ($result['quantity'] == $quantity) {
                // 删除物品
                $stmt = $this->db->prepare('DELETE FROM user_inventory WHERE user_id = ? AND item_id = ?');
                $stmt->execute([$userId, $itemId]);
            } else {
                // 减少数量
                $stmt = $this->db->prepare('UPDATE user_inventory SET quantity = quantity - ? WHERE user_id = ? AND item_id = ?');
                $stmt->execute([$quantity, $userId, $itemId]);
            }
            
            return [
                'success' => true,
                'message' => "成功使用 {$itemId} x{$quantity}"
            ];
        } catch (Exception $e) {
            error_log('Reduce item error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => '使用物品失败'
            ];
        }
    }
    
    /**
     * 记录兑换日志
     * @param string $userId 用户 ID
     * @param string $itemId 物品 ID
     * @param string $itemName 物品名称
     * @param int $pointsCost 花费积分
     * @param int $quantity 数量
     */
    public function logExchange($userId, $itemId, $itemName, $pointsCost, $quantity = 1) {
        try {
            $stmt = $this->db->prepare('
                INSERT INTO exchange_logs (user_id, item_id, item_name, points_cost, quantity)
                VALUES (?, ?, ?, ?, ?)
            ');
            $stmt->execute([$userId, $itemId, $itemName, $pointsCost, $quantity]);
        } catch (Exception $e) {
            error_log('Log exchange error: ' . $e->getMessage());
        }
    }
}
