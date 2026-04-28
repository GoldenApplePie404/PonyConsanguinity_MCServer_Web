<?php
/**
 * 积分管理类
 * 负责用户积分的查找、增加、减少等操作
 * 用户数据来源于 data/users.php
 */

class PointsManager {
    private $usersFile;
    private $usersData = [];

    /**
     * 构造函数
     */
    public function __construct() {
        $this->usersFile = __DIR__ . '/../../data/users.php';
        $this->loadUsersData();
    }

    /**
     * 加载用户数据
     */
    private function loadUsersData() {
        if (file_exists($this->usersFile)) {
            // 定义 ACCESS_ALLOWED 常量以允许加载（如果未定义）
            if (!defined('ACCESS_ALLOWED')) {
                define('ACCESS_ALLOWED', true);
            }
            $this->usersData = include $this->usersFile;
        } else {
            $this->usersData = [];
        }
    }

    /**
     * 保存用户数据回 users.php
     */
    private function saveUsersData() {
        $content = "<?php\nif (!defined('ACCESS_ALLOWED')) {\n    header('HTTP/1.1 403 Forbidden');\n    exit;\n}\n\nreturn " . var_export($this->usersData, true) . ";\n?>";
        file_put_contents($this->usersFile, $content);
    }

    /**
     * 通过用户名查找用户 ID
     * @param string $username 用户名
     * @return string|null 用户 ID
     */
    public function findUserIdByUsername($username) {
        foreach ($this->usersData as $userData) {
            if ($userData['username'] === $username) {
                return $userData['id'] ?? null;
            }
        }
        return null;
    }

    /**
     * 获取用户积分
     * @param string $userId 用户 ID
     * @return int 用户积分
     */
    public function getUserPoints($userId) {
        foreach ($this->usersData as $userData) {
            if ($userData['id'] === $userId) {
                return $userData['points'] ?? 0;
            }
        }
        return 0;
    }

    /**
     * 获取用户等级（根据经验值计算）
     * @param string $userId 用户 ID
     * @return int 用户等级
     */
    public function getUserLevel($userId) {
        foreach ($this->usersData as $userData) {
            if ($userData['id'] === $userId) {
                $experience = $userData['experience'] ?? 0;
                // 根据经验值计算等级：每 100 经验升一级
                return floor($experience / 100);
            }
        }
        return 0;
    }

    /**
     * 获取用户经验值
     * @param string $userId 用户 ID
     * @return int 用户经验值
     */
    public function getUserExperience($userId) {
        foreach ($this->usersData as $userData) {
            if ($userData['id'] === $userId) {
                return $userData['experience'] ?? 0;
            }
        }
        return 0;
    }

    /**
     * 获取用户名
     * @param string $userId 用户 ID
     * @return string|null 用户名
     */
    public function getUserName($userId) {
        foreach ($this->usersData as $userData) {
            if ($userData['id'] === $userId) {
                return $userData['username'] ?? null;
            }
        }
        return null;
    }

    /**
     * 增加积分
     * @param string $userId 用户 ID
     * @param int $amount 增加的积分数
     * @return bool 是否成功
     */
    public function addPoints($userId, $amount) {
        if ($amount <= 0) {
            return false;
        }

        foreach ($this->usersData as $username => &$userData) {
            if ($userData['id'] === $userId) {
                if (!isset($userData['points'])) {
                    $userData['points'] = 0;
                }
                $userData['points'] += $amount;
                $this->saveUsersData();
                return true;
            }
        }
        
        return false; // 未找到用户
    }

    /**
     * 减少积分
     * @param string $userId 用户 ID
     * @param int $amount 减少的积分数
     * @return bool 是否成功
     */
    public function reducePoints($userId, $amount) {
        if ($amount <= 0) {
            return false;
        }

        foreach ($this->usersData as $username => &$userData) {
            if ($userData['id'] === $userId) {
                $currentPoints = $userData['points'] ?? 0;
                if ($currentPoints < $amount) {
                    return false; // 积分不足
                }
                $userData['points'] -= $amount;
                $this->saveUsersData();
                return true;
            }
        }
        
        return false; // 未找到用户
    }

    /**
     * 检查用户积分是否足够
     * @param string $userId 用户 ID
     * @param int $amount 需要的积分数
     * @return bool 是否足够
     */
    public function checkPoints($userId, $amount) {
        $currentPoints = $this->getUserPoints($userId);
        return $currentPoints >= $amount;
    }

    /**
     * 检查用户等级是否满足要求
     * @param string $userId 用户 ID
     * @param int $requiredLevel 需要的等级
     * @return bool 是否满足
     */
    public function checkLevel($userId, $requiredLevel) {
        $userLevel = $this->getUserLevel($userId);
        return $userLevel >= $requiredLevel;
    }

    /**
     * 增加经验值
     * @param string $userId 用户 ID
     * @param int $exp 增加的经验值
     * @return array 结果（包含升级信息）
     */
    public function addExperience($userId, $exp) {
        if ($exp <= 0) {
            return ['success' => false, 'message' => '经验值必须大于 0'];
        }

        foreach ($this->usersData as $username => &$userData) {
            if ($userData['id'] === $userId) {
                if (!isset($userData['experience'])) {
                    $userData['experience'] = 0;
                }
                
                $oldExperience = $userData['experience'];
                $userData['experience'] += $exp;
                
                // 计算等级变化
                $oldLevel = floor($oldExperience / 100);
                $newLevel = floor($userData['experience'] / 100);
                
                $leveledUp = $newLevel > $oldLevel;
                
                $this->saveUsersData();

                return [
                    'success' => true,
                    'experience' => $userData['experience'],
                    'level' => $newLevel,
                    'leveled_up' => $leveledUp,
                    'old_level' => $oldLevel,
                    'new_level' => $newLevel
                ];
            }
        }
        
        return ['success' => false, 'message' => '用户不存在'];
    }

    /**
     * 获取用户完整信息
     * @param string $userId 用户 ID
     * @return array 用户信息
     */
    public function getUserInfo($userId) {
        foreach ($this->usersData as $userData) {
            if ($userData['id'] === $userId) {
                return [
                    'points' => $userData['points'] ?? 0,
                    'level' => $this->getUserLevel($userId),
                    'experience' => $userData['experience'] ?? 0,
                    'username' => $userData['username'] ?? null
                ];
            }
        }
        
        return [
            'points' => 0,
            'level' => 0,
            'experience' => 0,
            'username' => null
        ];
    }
}
