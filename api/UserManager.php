<?php
// UserManager.php
// 用户管理类，封装用户相关操作

if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}

require_once 'config.php';
require_once 'secure_data.php';

class UserManager {
    private $usersFile;
    private $users;
    
    /**
     * 构造函数
     * @param string $usersFile 用户数据文件路径
     */
    public function __construct($usersFile = USERS_FILE) {
        $this->usersFile = $usersFile;
        $this->loadUsers();
    }
    
    /**
     * 加载用户数据
     */
    private function loadUsers() {
        $this->users = secureReadData($this->usersFile);
    }
    
    /**
     * 保存用户数据
     */
    private function saveUsers() {
        return secureWriteData($this->usersFile, $this->users);
    }
    
    /**
     * 获取用户信息
     * @param string $username 用户名
     * @return array|null 用户信息
     */
    public function getUser($username) {
        return isset($this->users[$username]) ? $this->users[$username] : null;
    }
    
    /**
     * 增加积分
     * @param string $username 用户名
     * @param int $points 增加的积分
     * @return bool 是否成功
     */
    public function addPoints($username, $points) {
        if (isset($this->users[$username])) {
            $currentPoints = isset($this->users[$username]['points']) ? intval($this->users[$username]['points']) : 0;
            $this->users[$username]['points'] = $currentPoints + $points;
            return $this->saveUsers();
        }
        return false;
    }
    
    /**
     * 减少积分
     * @param string $username 用户名
     * @param int $points 减少的积分
     * @return bool 是否成功
     */
    public function removePoints($username, $points) {
        if (isset($this->users[$username])) {
            $currentPoints = isset($this->users[$username]['points']) ? intval($this->users[$username]['points']) : 0;
            $this->users[$username]['points'] = max(0, $currentPoints - $points);
            return $this->saveUsers();
        }
        return false;
    }
    
    /**
     * 增加经验值
     * @param string $username 用户名
     * @param int $experience 增加的经验值
     * @return bool 是否成功
     */
    public function addExperience($username, $experience) {
        if (isset($this->users[$username])) {
            $currentExp = isset($this->users[$username]['experience']) ? intval($this->users[$username]['experience']) : 0;
            $this->users[$username]['experience'] = $currentExp + $experience;
            return $this->saveUsers();
        }
        return false;
    }
    
    /**
     * 减少经验值
     * @param string $username 用户名
     * @param int $experience 减少的经验值
     * @return bool 是否成功
     */
    public function removeExperience($username, $experience) {
        if (isset($this->users[$username])) {
            $currentExp = isset($this->users[$username]['experience']) ? intval($this->users[$username]['experience']) : 0;
            $this->users[$username]['experience'] = max(0, $currentExp - $experience);
            return $this->saveUsers();
        }
        return false;
    }
    
    /**
     * 检查用户是否已签到
     * @param string $username 用户名
     * @return bool 是否已签到
     */
    public function hasCheckedIn($username) {
        if (isset($this->users[$username])) {
            $lastCheckin = isset($this->users[$username]['check_t']) ? $this->users[$username]['check_t'] : '';
            $today = date('Y-m-d');
            return $lastCheckin == $today;
        }
        return false;
    }
    
    /**
     * 更新签到状态
     * @param string $username 用户名
     * @return bool 是否成功
     */
    public function updateCheckinStatus($username) {
        if (isset($this->users[$username])) {
            $this->users[$username]['check_t'] = date('Y-m-d');
            return $this->saveUsers();
        }
        return false;
    }
    
    /**
     * 执行签到
     * @param string $username 用户名
     * @param int $rewardPoints 奖励积分
     * @param int $rewardExperience 奖励经验值
     * @return array 签到结果
     */
    public function checkin($username, $rewardPoints = 10, $rewardExperience = 15) {
        if (!$this->hasCheckedIn($username)) {
            $this->addPoints($username, $rewardPoints);
            $this->addExperience($username, $rewardExperience);
            $this->updateCheckinStatus($username);
            $user = $this->getUser($username);
            return [
                'success' => true,
                'points' => $user['points'],
                'experience' => $user['experience'],
                'reward' => $rewardPoints,
                'reward_experience' => $rewardExperience
            ];
        }
        return ['success' => false, 'message' => '今日已签到'];
    }
    
    /**
     * 获取用户签到状态
     * @param string $username 用户名
     * @return array 签到状态
     */
    public function getCheckinStatus($username) {
        $user = $this->getUser($username);
        if ($user) {
            $hasCheckedIn = $this->hasCheckedIn($username);
            $lastCheckin = isset($user['check_t']) ? $user['check_t'] : '';
            $points = isset($user['points']) ? intval($user['points']) : 0;
            $experience = isset($user['experience']) ? intval($user['experience']) : 0;
            $emailVerified = isset($user['email_verified']) && $user['email_verified'] === true;
            
            return [
                'success' => true,
                'points' => $points,
                'experience' => $experience,
                'has_checked_in' => $hasCheckedIn,
                'last_checkin' => $lastCheckin,
                'email_verified' => $emailVerified
            ];
        }
        return ['success' => false, 'message' => '用户不存在'];
    }
    
    /**
     * 更新用户信息
     * @param string $username 用户名
     * @param array $userData 用户数据
     * @return bool 是否成功
     */
    public function updateUser($username, $userData) {
        if (isset($this->users[$username])) {
            $this->users[$username] = array_merge($this->users[$username], $userData);
            return $this->saveUsers();
        }
        return false;
    }
    
    /**
     * 获取用户背包
     * @param string $username 用户名
     * @return array 背包物品
     */
    public function getInventory($username) {
        if (isset($this->users[$username])) {
            return isset($this->users[$username]['inventory']) ? $this->users[$username]['inventory'] : [];
        }
        return [];
    }
    
    /**
     * 添加物品到背包
     * @param string $username 用户名
     * @param string $itemType 物品类型
     * @param int $amount 数量
     * @return bool 是否成功
     */
    public function addItem($username, $itemType, $amount = 1) {
        if (isset($this->users[$username])) {
            if (!isset($this->users[$username]['inventory'])) {
                $this->users[$username]['inventory'] = [];
            }
            
            if (!isset($this->users[$username]['inventory'][$itemType])) {
                $this->users[$username]['inventory'][$itemType] = 0;
            }
            
            $this->users[$username]['inventory'][$itemType] += $amount;
            return $this->saveUsers();
        }
        return false;
    }
    
    /**
     * 从背包移除物品
     * @param string $username 用户名
     * @param string $itemType 物品类型
     * @param int $amount 数量
     * @return bool 是否成功
     */
    public function removeItem($username, $itemType, $amount = 1) {
        if (isset($this->users[$username]) && isset($this->users[$username]['inventory'][$itemType])) {
            if ($this->users[$username]['inventory'][$itemType] >= $amount) {
                $this->users[$username]['inventory'][$itemType] -= $amount;
                
                // 如果数量为0，删除该物品
                if ($this->users[$username]['inventory'][$itemType] <= 0) {
                    unset($this->users[$username]['inventory'][$itemType]);
                }
                
                return $this->saveUsers();
            }
        }
        return false;
    }
    
    /**
     * 使用物品
     * @param string $username 用户名
     * @param string $itemType 物品类型
     * @return array 使用结果
     */
    public function useItem($username, $itemType) {
        if (!isset($this->users[$username]) || !isset($this->users[$username]['inventory'][$itemType])) {
            return ['success' => false, 'message' => '物品不存在'];
        }
        
        if ($this->users[$username]['inventory'][$itemType] <= 0) {
            return ['success' => false, 'message' => '物品数量不足'];
        }
        
        // 根据物品类型处理使用效果
        switch ($itemType) {
            case 'resign_card':
                // 补签卡使用逻辑
                $this->removeItem($username, $itemType, 1);
                return [
                    'success' => true,
                    'message' => '补签卡已使用',
                    'data' => [
                        'item_type' => $itemType,
                        'remaining' => $this->getInventory($username)[$itemType] ?? 0
                    ]
                ];
                
            default:
                return ['success' => false, 'message' => '未知的物品类型'];
        }
    }
    
    /**
     * 获取用户BUFF
     * @param string $username 用户名
     * @return array BUFF列表
     */
    public function getBuffs($username) {
        if (isset($this->users[$username])) {
            $buffs = isset($this->users[$username]['buffs']) ? $this->users[$username]['buffs'] : [];
            
            // 过滤掉已过期的BUFF
            $now = date('Y-m-d H:i:s');
            foreach ($buffs as $type => $buff) {
                if ($buff['end_time'] < $now) {
                    unset($buffs[$type]);
                }
            }
            
            return $buffs;
        }
        return [];
    }
    
    /**
     * 添加BUFF
     * @param string $username 用户名
     * @param string $buffType BUFF类型
     * @param string $endTime 结束时间
     * @return bool 是否成功
     */
    public function addBuff($username, $buffType, $endTime) {
        if (isset($this->users[$username])) {
            if (!isset($this->users[$username]['buffs'])) {
                $this->users[$username]['buffs'] = [];
            }
            
            $this->users[$username]['buffs'][$buffType] = [
                'end_time' => $endTime,
                'activated' => false
            ];
            
            return $this->saveUsers();
        }
        return false;
    }
    
    /**
     * 检查用户是否有指定BUFF
     * @param string $username 用户名
     * @param string $buffType BUFF类型
     * @return bool 是否有BUFF
     */
    public function hasBuff($username, $buffType) {
        $buffs = $this->getBuffs($username);
        return isset($buffs[$buffType]);
    }
    
    /**
     * 获取所有用户
     * @return array 用户列表
     */
    public function getAllUsers() {
        $users = [];
        foreach ($this->users as $username => $userData) {
            $users[] = array_merge($userData, ['id' => $userData['id'] ?? $username, 'username' => $username]);
        }
        return $users;
    }
    
    /**
     * 根据ID获取用户
     * @param string $userId 用户ID
     * @return array|null 用户信息
     */
    public function getUserById($userId) {
        foreach ($this->users as $username => $userData) {
            if (isset($userData['id']) && $userData['id'] === $userId) {
                return array_merge($userData, ['username' => $username]);
            }
        }
        return null;
    }
}
?>