<?php
/**
 * 爱发电订单自动更新脚本（Web版）
 * 通过浏览器访问触发订单处理
 */

// 设置响应头
header('Content-Type: text/html; charset=utf-8');

// 加载配置
$config = require __DIR__ . '/config.php';

// 检查是否启用自动更新
if (!isset($config['auto_cron']['enabled']) || !$config['auto_cron']['enabled']) {
    echo json_encode([
        'success' => false,
        'message' => '自动更新未启用',
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 加载必要的模块
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/api/afdian_api.php';
require_once __DIR__ . '/process_orders.php';

// 加载统一日志模块
try {
    require_once dirname(__DIR__) . '/../includes/logger.php';
} catch (Exception $e) {
    // 如果无法加载统一日志模块，使用备用日志函数
}

// 定义备用日志函数
function logAutoUpdate($message, $level = 'info') {
    $logFile = dirname(dirname(__DIR__)) . '/logs/auto_update_' . date('Ymd') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message\n";
    
    if (!file_exists(dirname($logFile))) {
        mkdir(dirname($logFile), 0755, true);
    }
    
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// 主执行函数
function executeAutoUpdate($config) {
    $startTime = microtime(true);
    
    if (function_exists('log_info')) {
        log_info('开始执行自动订单更新', 'aifadian_auto');
    } else {
        logAutoUpdate('开始执行自动订单更新');
    }
    
    $result = [
        'success' => false,
        'message' => '',
        'data' => [
            'processed_count' => 0,
            'updated_players' => [],
            'errors' => []
        ]
    ];
    
    try {
        $orderUpdateMode = isset($config['order_update_mode']) ? strtolower($config['order_update_mode']) : 'api';
        
        if ($orderUpdateMode === 'api' || $orderUpdateMode === 'all') {
            if (function_exists('log_info')) {
                log_info('当前模式: ' . $orderUpdateMode . '，执行订单处理', 'aifadian_auto');
            } else {
                logAutoUpdate('当前模式: ' . $orderUpdateMode . '，执行订单处理');
            }
            
            $db = getDatabase();
            $afdianAPI = new AfdianAPI($config['user_id'], $config['api_token']);
            $processor = new OrderProcessor($db, $afdianAPI, $config);
            $processResult = $processor->processOrders();
            
            $result['success'] = $processResult['success'];
            $result['message'] = $processResult['message'];
            $result['data'] = $processResult['data'];
        } else {
            if (function_exists('log_info')) {
                log_info('当前模式: ' . $orderUpdateMode . '，跳过自动更新', 'aifadian_auto');
            } else {
                logAutoUpdate('当前模式: ' . $orderUpdateMode . '，跳过自动更新');
            }
            $result['success'] = true;
            $result['message'] = '当前模式下不执行自动更新';
        }
    } catch (Exception $e) {
        $errorMessage = '执行过程中发生错误: ' . $e->getMessage();
        if (function_exists('log_error')) {
            log_error($errorMessage, 'aifadian_auto');
        } else {
            logAutoUpdate($errorMessage, 'error');
        }
        $result['message'] = $errorMessage;
        $result['data']['errors'][] = $errorMessage;
    }
    
    $executionTime = microtime(true) - $startTime;
    
    if (function_exists('log_info')) {
        log_info('执行完成，处理订单数: ' . $result['data']['processed_count'] . ', 错误数: ' . count($result['data']['errors']) . ', 执行时间: ' . round($executionTime, 2) . '秒', 'aifadian_auto');
    } else {
        logAutoUpdate('执行完成，处理订单数: ' . $result['data']['processed_count'] . ', 错误数: ' . count($result['data']['errors']) . ', 执行时间: ' . round($executionTime, 2) . '秒');
    }
    
    $result['execution_time'] = round($executionTime, 2);
    $result['timestamp'] = time();
    $result['mode'] = isset($config['order_update_mode']) ? $config['order_update_mode'] : 'API';
    
    return $result;
}

// 执行自动更新
$result = executeAutoUpdate($config);

// 返回JSON响应
echo json_encode($result, JSON_UNESCAPED_UNICODE);

// 如果是JSONP请求，支持跨域
if (isset($_GET['callback'])) {
    echo $_GET['callback'] . '(' . json_encode($result, JSON_UNESCAPED_UNICODE) . ')';
}
?>