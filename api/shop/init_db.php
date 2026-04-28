<?php
/**
 * 积分商城数据库初始化脚本
 * 运行此脚本会创建所需的数据库和表
 */

echo "=== 积分商城数据库初始化 ===\n\n";

$dbPath = __DIR__ . '/../data/shop.db';

// 检查 data 目录是否存在
$dataDir = dirname($dbPath);
if (!is_dir($dataDir)) {
    echo "创建数据目录：{$dataDir}\n";
    mkdir($dataDir, 0755, true);
}

try {
    // 创建数据库连接
    $db = new PDO(
        'sqlite:' . $dbPath,
        null,
        null,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "✓ 数据库连接成功\n\n";
    
    // 创建用户积分表
    $db->exec("
        CREATE TABLE IF NOT EXISTS user_points (
            user_id TEXT PRIMARY KEY,
            points INTEGER DEFAULT 0,
            level INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✓ 创建用户积分表 (user_points)\n";
    
    // 创建用户背包表
    $db->exec("
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
    echo "✓ 创建用户背包表 (user_inventory)\n";
    
    // 创建兑换日志表
    $db->exec("
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
    echo "✓ 创建兑换日志表 (exchange_logs)\n";
    
    // 创建索引
    $db->exec("
        CREATE INDEX IF NOT EXISTS idx_inventory_user ON user_inventory(user_id)
    ");
    echo "✓ 创建索引\n";
    
    echo "\n=== 初始化完成！===\n";
    echo "数据库文件位置：{$dbPath}\n";
    
} catch (PDOException $e) {
    echo "\n❌ 错误：" . $e->getMessage() . "\n";
    exit(1);
}
