<?php
/**
 * 爱发电订单定时更新脚本
 */

// 设置错误处理
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 加载必要的文件
try {
    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/api/afdian_api.php';
    require_once __DIR__ . '/process_orders.php';
} catch (Exception $e) {
    echo "加载文件失败: " . $e->getMessage() . "\n";
    exit(1);
}

// 定义备用日志函数（在统一日志模块加载失败时使用）
function logMessage($message, $level = 'info') {
    $logFile = dirname(dirname(__DIR__)) . '/logs/cron_update_' . date('Ymd') . '.log';
    if (!is_dir(dirname($logFile))) {
        mkdir(dirname($logFile), 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] {$message}\n";
    
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    echo $logEntry;
}

function logError($message) {
    $logFile = dirname(dirname(__DIR__)) . '/logs/cron_error_' . date('Ymd') . '.log';
    if (!is_dir(dirname($logFile))) {
        mkdir(dirname($logFile), 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $errorEntry = "[{$timestamp}] ERROR: {$message}\n";
    
    file_put_contents($logFile, $errorEntry, FILE_APPEND);
    echo $errorEntry;
}

// 加载统一日志模块
try {
    require_once dirname(__DIR__) . '/../includes/logger.php';
} catch (Exception $e) {
    // 如果无法加载统一日志模块，使用上面定义的备用日志函数
}

// 主执行函数
function main() {
    // 使用统一日志函数
    if (function_exists('log_info')) {
        log_info('开始执行爱发电订单定时更新', 'aifadian_cron');
    } else {
        logMessage('开始执行爱发电订单定时更新');
    }
    
    $startTime = microtime(true);
    $result = array(
        'success' => false,
        'message' => '',
        'data' => array(
            'processed_count' => 0,
            'updated_players' => array(),
            'errors' => array()
        )
    );
    
    try {
        // 初始化配置
        $config = require_once __DIR__ . '/config.php';
        
        // 检查订单更新模式
        $orderUpdateMode = isset($config['order_update_mode']) ? strtolower($config['order_update_mode']) : 'API';
        
        // 只有在API模式或all模式下才执行定时任务
        if ($orderUpdateMode === 'api' || $orderUpdateMode === 'all') {
            if (function_exists('log_info')) {
                log_info('当前模式: ' . $orderUpdateMode . '，执行订单处理', 'aifadian_cron');
            } else {
                logMessage('当前模式: ' . $orderUpdateMode . '，执行订单处理');
            }
            
            // 初始化数据库
            $db = getDatabase();
            
            // 初始化爱发电API
            $afdianAPI = new AfdianAPI($config['user_id'], $config['api_token']);
            
            // 初始化订单处理器
            $processor = new OrderProcessor($db, $afdianAPI, $config);
            
            // 处理订单
            $processResult = $processor->processOrders();
            
            if ($processResult['success']) {
                $result['success'] = true;
                $result['message'] = $processResult['message'];
                $result['data'] = $processResult['data'];
            } else {
                $result['message'] = $processResult['message'];
                $result['data'] = $processResult['data'];
            }
            
        } else {
            // webhook模式下不执行定时任务
            if (function_exists('log_info')) {
                log_info('当前模式: ' . $orderUpdateMode . '，跳过定时任务执行', 'aifadian_cron');
            } else {
                logMessage('当前模式: ' . $orderUpdateMode . '，跳过定时任务执行');
            }
            $result['success'] = true;
            $result['message'] = '当前模式下不执行定时任务';
        }
        
    } catch (Exception $e) {
        $errorMessage = '执行过程中发生错误: ' . $e->getMessage();
        if (function_exists('log_error')) {
            log_error($errorMessage, 'aifadian_cron');
        } else {
            logError($errorMessage);
        }
        $result['message'] = $errorMessage;
        $result['data']['errors'][] = $errorMessage;
    }
    
    $executionTime = microtime(true) - $startTime;
    
    if (function_exists('log_info')) {
        log_info('执行完成，处理订单数: ' . $result['data']['processed_count'] . ', 错误数: ' . count($result['data']['errors']) . ', 执行时间: ' . round($executionTime, 2) . '秒', 'aifadian_cron');
    } else {
        logMessage('执行完成，处理订单数: ' . $result['data']['processed_count'] . ', 错误数: ' . count($result['data']['errors']) . ', 执行时间: ' . round($executionTime, 2) . '秒');
    }
    
    // 保存执行结果
    try {
        $resultFile = __DIR__ . '/../data/aifadian/update_result.json';
        if (!is_dir(dirname($resultFile))) {
            mkdir(dirname($resultFile), 0755, true);
        }
        
        $result['execution_time'] = round($executionTime, 2);
        $result['timestamp'] = time();
        $result['mode'] = isset($config['order_update_mode']) ? $config['order_update_mode'] : 'API';
        
        file_put_contents($resultFile, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    } catch (Exception $e) {
        if (function_exists('log_error')) {
            log_error('保存执行结果失败: ' . $e->getMessage(), 'aifadian_cron');
        } else {
            logError('保存执行结果失败: ' . $e->getMessage());
        }
    }
    
    if (function_exists('log_info')) {
        log_info('定时更新执行结束', 'aifadian_cron');
        log_info('--------------------------------------------------------------------------------', 'aifadian_cron');
    } else {
        logMessage('定时更新执行结束');
        logMessage('--------------------------------------------------------------------------------');
    }
    
    return $result;
}

// 执行主函数
if (php_sapi_name() === 'cli') {
    // CLI模式下直接执行
    main();
} else {
    // Web模式下返回JSON
    header('Content-Type: application/json; charset=utf-8');
    $result = main();
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
}
?>