<?php
/**
 * 爱发电API接口
 * 统一的API调用接口，处理所有爱发电相关的请求
 */

// 设置响应头
header('Content-Type: application/json; charset=utf-8');

// 加载配置
$config = require __DIR__ . '/../config.php';

// 加载必要的模块
require_once __DIR__ . '/afdian_api.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../process_orders.php';

// 加载统一日志模块
try {
    require_once dirname(__DIR__) . '/../includes/logger.php';
} catch (Exception $e) {
    // 如果无法加载统一日志模块，使用备用日志函数
}

// 定义备用日志函数
function logApi($message, $level = 'info') {
    $logFile = dirname(dirname(__DIR__)) . '/logs/api_' . date('Ymd') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message\n";
    
    if (!file_exists(dirname($logFile))) {
        mkdir(dirname($logFile), 0755, true);
    }
    
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// 检查请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ec' => 405, 'em' => 'Method Not Allowed']);
    exit;
}

// 获取请求数据
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['ec' => 400, 'em' => 'Invalid JSON data']);
    exit;
}

$action = isset($data['action']) ? $data['action'] : '';

if (function_exists('log_info')) {
    log_info("API请求: action={$action}", 'aifadian_api');
} else {
    logApi("API请求: action={$action}", 'info');
}

try {
    $result = null;
    
    switch ($action) {
        case 'ping':
            $result = handlePing($config);
            break;
            
        case 'query_order':
            $result = handleQueryOrder($config, $data);
            break;
            
        case 'get_orders':
            $result = handleGetOrders($config, $data);
            break;
            
        case 'query_plan':
            $result = handleQueryPlan($config, $data);
            break;
            
        case 'process_orders':
            $result = handleProcessOrders($config);
            break;
            
        case 'get_order_status':
            $result = handleGetOrderStatus($config);
            break;
            
        case 'get_player_points':
            $result = handleGetPlayerPoints($config, $data);
            break;
            
        case 'get_processed_orders':
            $result = handleGetProcessedOrders($config);
            break;
            
        case 'get_statistics':
            $result = handleGetStatistics($config);
            break;
            
        default:
            $result = [
                'ec' => 400,
                'em' => 'Unknown action: ' . $action,
                'data' => null
            ];
    }
    
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    $errorMessage = 'API错误: ' . $e->getMessage();
    
    if (function_exists('log_error')) {
        log_error($errorMessage, 'aifadian_api');
    } else {
        logApi($errorMessage, 'error');
    }
    
    echo json_encode([
        'ec' => 500,
        'em' => $errorMessage,
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * 处理Ping请求
 */
function handlePing($config) {
    $afdianAPI = new AfdianAPI($config['user_id'], $config['api_token']);
    $result = $afdianAPI->ping();
    
    if ($result['ec'] == 200) {
        return [
            'ec' => 200,
            'em' => 'Ping成功',
            'data' => $result['data']
        ];
    } else {
        return [
            'ec' => $result['ec'],
            'em' => $result['em'],
            'data' => $result['data']
        ];
    }
}

/**
 * 处理查询订单请求
 */
function handleQueryOrder($config, $data) {
    $outTradeNo = isset($data['out_trade_no']) ? $data['out_trade_no'] : '';
    
    if (empty($outTradeNo)) {
        return [
            'ec' => 400,
            'em' => '订单号不能为空',
            'data' => null
        ];
    }
    
    $afdianAPI = new AfdianAPI($config['user_id'], $config['api_token']);
    $result = $afdianAPI->queryOrder($outTradeNo);
    
    if ($result['ec'] == 200) {
        return [
            'ec' => 200,
            'em' => '查询成功',
            'data' => $result['data']
        ];
    } else {
        return [
            'ec' => $result['ec'],
            'em' => $result['em'],
            'data' => $result['data']
        ];
    }
}

/**
 * 处理获取订单列表请求
 */
function handleGetOrders($config, $data) {
    $page = isset($data['page']) ? intval($data['page']) : 1;
    $perPage = isset($data['per_page']) ? intval($data['per_page']) : 20;
    
    $afdianAPI = new AfdianAPI($config['user_id'], $config['api_token']);
    $result = $afdianAPI->queryOrder(null, $page, $perPage);
    
    if ($result['ec'] == 200) {
        return [
            'ec' => 200,
            'em' => '获取成功',
            'data' => $result['data']
        ];
    } else {
        return [
            'ec' => $result['ec'],
            'em' => $result['em'],
            'data' => $result['data']
        ];
    }
}

/**
 * 处理查询方案请求
 */
function handleQueryPlan($config, $data) {
    $planId = isset($data['plan_id']) ? $data['plan_id'] : '';
    
    if (empty($planId)) {
        return [
            'ec' => 400,
            'em' => '方案ID不能为空',
            'data' => null
        ];
    }
    
    $afdianAPI = new AfdianAPI($config['user_id'], $config['api_token']);
    $result = $afdianAPI->queryPlan($planId);
    
    return [
        'ec' => 200,
        'em' => '查询成功',
        'data' => $result['data']
    ];
}

/**
 * 处理订单处理请求
 */
function handleProcessOrders($config) {
    $orderUpdateMode = isset($config['order_update_mode']) ? strtolower($config['order_update_mode']) : 'api';
    
    if ($orderUpdateMode === 'api' || $orderUpdateMode === 'all') {
        if (function_exists('log_info')) {
            log_info('当前模式: ' . $orderUpdateMode . '，执行订单处理', 'aifadian_api');
        } else {
            logApi('当前模式: ' . $orderUpdateMode . '，执行订单处理', 'info');
        }
        
        $db = getDatabase();
        $afdianAPI = new AfdianAPI($config['user_id'], $config['api_token']);
        $processor = new OrderProcessor($db, $afdianAPI, $config);
        $result = $processor->processOrders();
        
        return [
            'ec' => $result['success'] ? 200 : 500,
            'em' => $result['message'],
            'data' => $result['data'],
            'mode' => $config['order_update_mode']
        ];
    } else {
        if (function_exists('log_info')) {
            log_info('当前模式: ' . $orderUpdateMode . '，跳过订单处理', 'aifadian_api');
        } else {
            logApi('当前模式: ' . $orderUpdateMode . '，跳过订单处理', 'info');
        }
        
        return [
            'ec' => 200,
            'em' => '当前模式下不执行订单处理',
            'data' => [
                'processed_count' => 0,
                'updated_players' => [],
                'errors' => []
            ],
            'mode' => $config['order_update_mode']
        ];
    }
}

/**
 * 处理获取订单状态请求
 */
function handleGetOrderStatus($config) {
    $db = getDatabase();
    $afdianAPI = new AfdianAPI($config['user_id'], $config['api_token']);
    $processor = new OrderProcessor($db, $afdianAPI, $config);
    $result = $processor->getOrderStatus();
    
    return [
        'ec' => $result['success'] ? 200 : 500,
        'em' => $result['message'],
        'data' => $result['data']
    ];
}

/**
 * 处理获取玩家点数请求
 */
function handleGetPlayerPoints($config, $data) {
    $username = isset($data['username']) ? $data['username'] : '';
    
    if (empty($username)) {
        return [
            'ec' => 400,
            'em' => '玩家名称不能为空',
            'data' => null
        ];
    }
    
    $db = getDatabase();
    $afdianAPI = new AfdianAPI($config['user_id'], $config['api_token']);
    $processor = new OrderProcessor($db, $afdianAPI, $config);
    $result = $processor->getPlayerPoints($username);
    
    return [
        'ec' => $result['success'] ? 200 : 500,
        'em' => $result['message'],
        'data' => $result['data']
    ];
}

/**
 * 处理获取已处理订单请求
 */
function handleGetProcessedOrders($config) {
    $db = getDatabase();
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    
    $orders = $db->getAfdianOrders($limit, $offset);
    
    return [
        'ec' => 200,
        'em' => '获取成功',
        'data' => [
            'orders' => $orders,
            'count' => count($orders)
        ]
    ];
}

/**
 * 处理获取统计信息请求
 */
function handleGetStatistics($config) {
    $db = getDatabase();
    
    $stats = [
        'total_orders' => 0,
        'completed_orders' => 0,
        'pending_orders' => 0,
        'failed_orders' => 0,
        'total_points' => 0,
        'total_amount' => 0
    ];
    
    try {
        $result = $db->fetchAll("SELECT status, COUNT(*) as count FROM afdian_orders GROUP BY status");
        foreach ($result as $row) {
            $status = $row['status'];
            $count = $row['count'];
            
            $stats['total_orders'] += $count;
            
            switch ($status) {
                case 'completed':
                    $stats['completed_orders'] = $count;
                    break;
                case 'pending':
                    $stats['pending_orders'] = $count;
                    break;
                case 'failed':
                    $stats['failed_orders'] = $count;
                    break;
            }
        }
        
        $result = $db->fetchOne("SELECT SUM(points_added) as total_points, SUM(amount) as total_amount FROM afdian_orders WHERE status = 'completed'");
        if ($result) {
            $stats['total_points'] = $result['total_points'] ? intval($result['total_points']) : 0;
            $stats['total_amount'] = $result['total_amount'] ? floatval($result['total_amount']) : 0;
        }
    } catch (Exception $e) {
        if (function_exists('log_error')) {
            log_error('获取统计信息失败: ' . $e->getMessage(), 'aifadian_api');
        } else {
            logApi('获取统计信息失败: ' . $e->getMessage(), 'error');
        }
    }
    
    return [
        'ec' => 200,
        'em' => '获取成功',
        'data' => $stats
    ];
}
?>