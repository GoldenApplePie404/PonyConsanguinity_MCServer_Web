<?php
// 引入统一配置文件
require_once dirname(__DIR__) . '/../config/config.php';

// 数据库连接和配置模块

class Database {
    private $conn;
    private $config;
    
    // 构造函数
    public function __construct() {
        $this->config = get_db_config();
        $this->connect();
    }
    
    // 连接数据库
    private function connect() {
        try {
            $dsn = "mysql:host={$this->config['hostname']};port={$this->config['port']};dbname={$this->config['database']};charset=utf8mb4";
            $this->conn = new PDO($dsn, $this->config['username'], $this->config['password']);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception('数据库连接失败: ' . $e->getMessage());
        }
    }
    
    // 获取数据库连接
    public function getConnection() {
        return $this->conn;
    }
    
    // 开始事务
    public function beginTransaction() {
        return $this->conn->beginTransaction();
    }
    
    // 提交事务
    public function commit() {
        return $this->conn->commit();
    }
    
    // 回滚事务
    public function rollback() {
        return $this->conn->rollback();
    }
    
    // 执行查询
    public function query($sql, $params = []) {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new Exception('查询执行失败: ' . $e->getMessage());
        }
    }
    
    // 执行SQL语句（INSERT, UPDATE, DELETE）
    public function exec($sql, $params = []) {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw new Exception('SQL执行失败: ' . $e->getMessage());
        }
    }
    
    // 获取单条记录
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    // 获取所有记录
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    // 插入数据
    public function insert($table, $data) {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        
        $sql = "INSERT INTO `{$table}` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $placeholders) . ")";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute(array_values($data));
            return $this->conn->lastInsertId();
        } catch (PDOException $e) {
            throw new Exception('插入数据失败: ' . $e->getMessage());
        }
    }
    
    // 更新数据
    public function update($table, $data, $where, $whereParams = []) {
        $set = [];
        foreach (array_keys($data) as $column) {
            $set[] = "`{$column}` = ?";
        }
        
        $sql = "UPDATE `{$table}` SET " . implode(', ', $set) . " WHERE {$where}";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute(array_merge(array_values($data), $whereParams));
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw new Exception('更新数据失败: ' . $e->getMessage());
        }
    }
    
    // 删除数据
    public function delete($table, $where, $whereParams = []) {
        $sql = "DELETE FROM `{$table}` WHERE {$where}";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($whereParams);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw new Exception('删除数据失败: ' . $e->getMessage());
        }
    }
    
    // 检查记录是否存在
    public function exists($table, $where, $whereParams = []) {
        $sql = "SELECT COUNT(*) as count FROM `{$table}` WHERE {$where}";
        $result = $this->fetchOne($sql, $whereParams);
        return $result['count'] > 0;
    }
    
    // 获取玩家点数
    public function getPlayerPoints($uuid) {
        $sql = "SELECT points FROM playerpoints_points WHERE uuid = ?";
        $result = $this->fetchOne($sql, [$uuid]);
        return $result ? $result['points'] : 0;
    }
    
    /**
     * 原子递增玩家点数（防并发丢点数）
     * 由数据库在行锁内完成 read-modify-write，避免「先读后算再写」的竞态：
     * 同一玩家的两笔不同订单并发处理时，旧实现会互相覆盖导致丢点数。
     * @param string $uuid  玩家UUID
     * @param int    $delta 增量点数
     */
    public function updatePlayerPoints($uuid, $delta) {
        return $this->exec("UPDATE playerpoints_points SET points = points + ? WHERE uuid = ?", [$delta, $uuid]);
    }
    
    // 根据玩家名获取UUID
    public function getPlayerUUID($username) {
        $sql = "SELECT uuid FROM playerpoints_username_cache WHERE username = ?";
        $result = $this->fetchOne($sql, [$username]);
        return $result ? $result['uuid'] : null;
    }
    
    // 检查玩家是否存在
    public function playerExists($username) {
        return $this->exists('playerpoints_username_cache', 'username = ?', [$username]);
    }
    
    // 检查订单是否已处理
    public function isOrderProcessed($orderId) {
        return $this->exists('processed_orders', 'order_id = ?', [$orderId]);
    }
    
    // 标记订单为已处理
    public function markOrderProcessed($orderId) {
        try {
            return $this->insert('processed_orders', ['order_id' => $orderId]);
        } catch (Exception $e) {
            // 检查是否是唯一约束冲突（订单已存在）
            if (strpos($e->getMessage(), 'Duplicate entry') !== false || 
                strpos($e->getMessage(), '1062') !== false) {
                // 订单已被其他进程处理，返回false
                return false;
            }
            // 其他错误，重新抛出
            throw $e;
        }
    }
    
    // 保存爱发电订单
    public function saveAfdianOrder($orderData) {
        // 检查订单是否已存在
        if ($this->exists('afdian_orders', 'out_trade_no = ?', [$orderData['out_trade_no']])) {
            // 更新现有订单
            return $this->update('afdian_orders', $orderData, 'out_trade_no = ?', [$orderData['out_trade_no']]);
        } else {
            // 插入新订单
            return $this->insert('afdian_orders', $orderData);
        }
    }
    
    // 获取爱发电订单列表
    public function getAfdianOrders($limit = 50) {
        $sql = "SELECT * FROM afdian_orders ORDER BY created_at DESC LIMIT " . intval($limit);
        return $this->fetchAll($sql);
    }
    
    // 获取订单状态统计
    public function getOrderStatusStats() {
        $sql = "SELECT 
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                    COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed,
                    COUNT(*) as total
                 FROM afdian_orders";
        return $this->fetchOne($sql);
    }
    
    // 创建必要的表结构
    public function createTables() {
        // 创建已处理订单表
        $sql = "CREATE TABLE IF NOT EXISTS `processed_orders` (
            `id` INT PRIMARY KEY AUTO_INCREMENT,
            `order_id` VARCHAR(255) UNIQUE NOT NULL COMMENT '订单号',
            `processed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '处理时间'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->exec($sql);
        
        // 创建爱发电订单表
        $sql = "CREATE TABLE IF NOT EXISTS `afdian_orders` (
            `id` INT PRIMARY KEY AUTO_INCREMENT,
            `out_trade_no` VARCHAR(255) UNIQUE NOT NULL COMMENT '订单号',
            `remark` VARCHAR(255) COMMENT '玩家名称',
            `create_time` INT COMMENT '订单创建时间戳',
            `plan_title` VARCHAR(255) COMMENT '商品名称',
            `plan_id` VARCHAR(255) COMMENT '商品ID',
            `sku_count` INT DEFAULT 1 COMMENT '购买数量',
            `points_added` INT COMMENT '增加的点数',
            `player_uuid` VARCHAR(255) COMMENT '玩家UUID',
            `player_username` VARCHAR(255) COMMENT '玩家用户名',
            `status` ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending' COMMENT '处理状态',
            `error_message` TEXT COMMENT '错误信息（如果有）',
            `processed_at` TIMESTAMP NULL COMMENT '处理时间',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '记录创建时间'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->exec($sql);
    }
    
    // 关闭数据库连接
    public function close() {
        $this->conn = null;
    }
}

// 获取数据库实例
function getDatabase() {
    static $db = null;
    if ($db === null) {
        $db = new Database();
    }
    return $db;
}

/**
 * 爱发电接口共享密钥鉴权（防滥用）
 * 仅当 AFDIAN_API_SECRET 已配置（非空）时才强制校验；
 * 校验来源：请求头 X-Afdian-Key 或 POST/GET 参数 secret。
 * 校验失败直接返回 403 并终止脚本。
 * 说明：该密钥会出现在前端 JS 中，仅阻挡"盲打"式滥用，无法防住查看源码的定向攻击；
 *       若需更强防护，请在此基础上追加 IP 白名单或速率限制。
 */
function afdian_require_auth() {
    if (!defined('AFDIAN_API_SECRET') || AFDIAN_API_SECRET === '') {
        return; // 未配置密钥 → 不强制鉴权（开发/调试环境）
    }
    $secret = (string) AFDIAN_API_SECRET;
    $provided = '';
    if (isset($_SERVER['HTTP_X_AFDIAN_KEY'])) {
        $provided = (string) $_SERVER['HTTP_X_AFDIAN_KEY'];
    } elseif (isset($_POST['secret'])) {
        $provided = (string) $_POST['secret'];
    } elseif (isset($_GET['secret'])) {
        $provided = (string) $_GET['secret'];
    }
    if (!hash_equals($secret, $provided)) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(403);
        }
        echo json_encode(['ec' => 403, 'em' => 'Forbidden: invalid or missing API secret'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
?>
