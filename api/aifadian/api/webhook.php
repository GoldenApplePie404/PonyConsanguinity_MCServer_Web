<?php
/**
 * 爱发电Webhook接收接口
 * 用于接收爱发电的订单推送通知
 */

// 加载配置
$config = require '../config.php';

// 公钥
$publicKey = "-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAwwdaCg1Bt+UKZKs0R54y
lYnuANma49IpgoOwNmk3a0rhg/PQuhUJ0EOZSowIC44l0K3+fqGns3Ygi4AfmEfS
4EKbdk1ahSxu7Zkp2rHMt+R9GarQFQkwSS/5x1dYiHNVMiR8oIXDgjmvxuNes2Cr
8fw9dEF0xNBKdkKgG2qAawcN1nZrdyaKWtPVT9m2Hl0ddOO9thZmVLFOb9NVzgYf
jEgI+KWX6aY19Ka/ghv/L4t1IXmz9pctablN5S0CRWpJW3Cn0k6zSXgjVdKm4uN7
jRlgSRaf/Ind46vMCm3N2sgwxu/g3bnooW+db0iLo13zzuvyn727Q3UDQ0MmZcEW
MQIDAQAB
-----END PUBLIC KEY-----";

// 验证签名
function verifySign($signStr, $sign) {
    global $publicKey;
    $key = openssl_get_publickey($publicKey);
    return openssl_verify($signStr, base64_decode($sign), $key, 'SHA256');
}

// 定义备用日志函数（在统一日志模块加载失败时使用）
function logToFile($message, $level = 'info') {
    $logFile = dirname(dirname(dirname(__DIR__))) . '/logs/webhook_' . date('Ymd') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message\n";
    
    // 确保日志目录存在
    if (!file_exists(dirname($logFile))) {
        mkdir(dirname($logFile), 0755, true);
    }
    
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// 加载统一日志模块
try {
    require_once dirname(dirname(dirname(__DIR__))) . '/includes/logger.php';
} catch (Exception $e) {
    // 如果无法加载统一日志模块，使用上面定义的备用日志函数
}

// 加载数据库连接模块
try {
    require_once __DIR__ . '/../db.php';
} catch (Exception $e) {
    logToFile('无法加载数据库连接模块: ' . $e->getMessage(), 'error');
    throw new Exception('Database connection module not found');
}

// 处理订单
function processOrder($order, $config) {
    require_once __DIR__ . '/../OrderHandler.php';
    
    $db = getDatabase();
    $handler = new OrderHandler($db, $config);
    
    return $handler->processOrder($order, 'webhook');
}

// 主逻辑
try {
    // 记录webhook被调用
    logToFile("=== Webhook被调用 ===", 'info');
    logToFile("请求方法: " . $_SERVER['REQUEST_METHOD'], 'info');
    logToFile("请求URI: " . $_SERVER['REQUEST_URI'], 'info');
    
    // 接收POST数据
    $postData = file_get_contents('php://input');
    
    $data = json_decode($postData, true);
    
    if (!$data) {
        logToFile("JSON解析失败", 'error');
        throw new Exception('Invalid JSON data');
    }
    
    // 检查数据格式
    if (!isset($data['data']) || !isset($data['data']['order'])) {
        logToFile("数据格式错误，缺少data或order字段", 'error');
        throw new Exception('Invalid data format');
    }
    
    $order = $data['data']['order'];
    $outTradeNo = $order['out_trade_no'];
    
    logToFile("订单号: {$outTradeNo}", 'info');
    
    // 检查订单更新模式
    $orderUpdateMode = isset($config['order_update_mode']) ? strtolower($config['order_update_mode']) : 'API';
    logToFile("订单更新模式: {$orderUpdateMode}", 'info');
    
    // 只有在webhook模式或all模式下才处理Webhook
    if ($orderUpdateMode === 'webhook' || $orderUpdateMode === 'all') {
        if (function_exists('log_info')) {
            log_info("当前模式: {$orderUpdateMode}，处理Webhook通知", 'aifadian_webhook');
        } else {
            logToFile("当前模式: {$orderUpdateMode}，处理Webhook通知", 'info');
        }
        
        // 验证签名
        if ($config['webhook']['verify_sign']) {
            logToFile("开始验证签名", 'info');
            $signStr = $order['out_trade_no'] . $order['user_id'] . $order['plan_id'] . $order['total_amount'];
            
            // 签名在data级别
            $receivedSign = isset($data['data']['sign']) ? $data['data']['sign'] : '';
            
            if (!verifySign($signStr, $receivedSign)) {
                logToFile("签名验证失败", 'error');
                throw new Exception('Signature verification failed');
            }
            
            if (function_exists('log_info')) {
                log_info("签名验证成功: {$outTradeNo}", 'aifadian_webhook');
            } else {
                logToFile("签名验证成功: {$outTradeNo}", 'info');
            }
        } else {
            logToFile("签名验证已禁用", 'info');
        }
        
        // 检查订单状态
        $orderStatus = $order['status'];
        logToFile("订单状态: {$orderStatus}", 'info');
        
        if ($data['data']['type'] == 'order' && $orderStatus == 2) {
            // 订单状态为交易成功
            logToFile("订单状态为交易成功，开始处理订单", 'info');
            
            try {
                // 处理订单（充值）
                $result = processOrder($order, $config);
                
                logToFile("订单处理成功: {$outTradeNo}", 'success');
                
                // 返回成功响应
                header('Content-Type: application/json; charset=utf-8');
                header('Cache-Control: no-cache');
                http_response_code(200);
                echo '{"ec":200,"em":""}';
                
            } catch (Exception $e) {
                // 记录错误
                logToFile('处理订单失败: ' . $e->getMessage(), 'error');
                
                throw $e;
            }
            
        } else {
            // 订单状态不是交易成功
            if (function_exists('log_info')) {
                log_info("订单状态未处理: {$outTradeNo}, 状态: {$orderStatus}", 'aifadian_webhook');
            } else {
                logToFile("订单状态未处理: {$outTradeNo}, 状态: {$orderStatus}", 'info');
            }
            
            // 返回成功响应
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-cache');
            http_response_code(200);
            echo '{"ec":200,"em":""}';
        }
        
    } else {
        // API模式下忽略Webhook
        logToFile("当前模式: {$orderUpdateMode}，忽略Webhook通知", 'info');
        
        // 返回成功响应
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache');
        http_response_code(200);
        echo '{"ec":200,"em":""}';
    }
    
} catch (Exception $e) {
    // 记录错误
    logToFile("=== 发生异常 ===", 'error');
    logToFile("异常消息: " . $e->getMessage(), 'error');
    
    if (function_exists('log_error')) {
        log_error('Error: ' . $e->getMessage(), 'aifadian_webhook');
    } else {
        logToFile('Error: ' . $e->getMessage(), 'error');
    }
    
    // 返回错误响应
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache');
    http_response_code(400);
    $errorMsg = addslashes($e->getMessage());
    echo '{"ec":400,"em":"' . $errorMsg . '"}';
}
?>