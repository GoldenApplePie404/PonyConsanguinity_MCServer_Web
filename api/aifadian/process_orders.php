<?php
// 订单处理脚本
// 集成爱发电API，获取订单数据，处理订单并更新玩家点数

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/api/afdian_api.php';

class OrderProcessor {
    private $db;
    private $afdianAPI;
    private $config;
    
    public function __construct($db, $afdianAPI, $config) {
        $this->db = $db;
        $this->afdianAPI = $afdianAPI;
        $this->config = $config;
        
        // 创建必要的表结构
        $this->db->createTables();
    }
    
    // 处理订单（完整版）
    public function processOrders() {
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
            // 加载统一日志模块
            if (!function_exists('log_info')) {
                try {
                    require_once dirname(dirname(__DIR__)) . '/includes/logger.php';
                } catch (Exception $e) {
                    // 忽略加载错误
                }
            }
            
            if (function_exists('log_info')) {
                log_info('开始处理订单', 'aifadian_process');
            } else {
                echo "开始处理订单\n";
            }
            
            // 1. 从爱发电API获取订单列表
            if (function_exists('log_info')) {
                log_info('从爱发电API获取订单数据', 'aifadian_process');
            } else {
                echo "从爱发电API获取订单数据\n";
            }
            $apiResult = $this->afdianAPI->queryOrder(null, 1, 100);
            
            if ($apiResult['ec'] != 200) {
                throw new Exception('获取订单失败: ' . $apiResult['em']);
            }
            
            $orders = $apiResult['data']['list'] ?? array();
            if (function_exists('log_info')) {
                log_info('获取到订单数量: ' . count($orders), 'aifadian_process');
            } else {
                echo "获取到订单数量: " . count($orders) . "\n";
            }
            
            // 2. 筛选并处理黄金券订单
            $processedCount = 0;
            $updatedPlayers = array();
            $errors = array();
            $goldenMatched = 0;

            require_once __DIR__ . '/OrderHandler.php';

            $goldenTicketPlanId = $this->config['plan_ids']['golden_ticket'];
            $handler = new OrderHandler($this->db, $this->config);

            foreach ($orders as $rawOrder) {
                try {
                    // 兼容 query-order 不同返回结构（部分版本将订单包在 order 子键下）
                    $order = $rawOrder['order'] ?? $rawOrder;

                    // 仅处理「已支付」订单（与 webhook 口径对齐：status==2 代表交易成功）
                    // 避免把未支付 / 退款订单误充值
                    if (!isset($order['status']) || (int)$order['status'] !== 2) {
                        continue;
                    }

                    // 检查是否是黄金券订单
                    if (isset($order['plan_id']) && $order['plan_id'] == $goldenTicketPlanId) {
                        $goldenMatched++;
                        $result = $handler->processOrder($order, 'api');

                        if ($result['success']) {
                            if ($result['data']['status'] == 'completed') {
                                $processedCount++;
                                $updatedPlayers[] = array(
                                    'username' => $result['data']['player_username'] ?? '',
                                    'uuid' => $result['data']['player_uuid'] ?? '',
                                    'old_points' => $result['data']['old_points'] ?? 0,
                                    'new_points' => $result['data']['new_points'] ?? 0,
                                    'added_points' => $result['data']['points_added'] ?? 0
                                );
                            }
                        } else {
                            $errorMsg = '处理订单失败 (' . ($order['out_trade_no'] ?? '') . '): ' . $result['message'];
                            if (function_exists('log_error')) {
                                log_error($errorMsg, 'aifadian_process');
                            } else {
                                echo $errorMsg . "\n";
                            }
                            $errors[] = $errorMsg;
                        }
                    }
                } catch (Exception $e) {
                    $outTradeNo = $order['out_trade_no'] ?? 'unknown';
                    $errorMsg = '处理订单失败 (' . $outTradeNo . '): ' . $e->getMessage();
                    if (function_exists('log_error')) {
                        log_error($errorMsg, 'aifadian_process');
                    } else {
                        echo $errorMsg . "\n";
                    }
                    $errors[] = $errorMsg;
                }
            }

            // 监控：拉到订单但黄金券命中为 0 → 预警 query-order 返回结构可能漂移
            if (count($orders) > 0 && $goldenMatched === 0) {
                $warnMsg = 'query-order 返回 ' . count($orders) . ' 笔订单，但黄金券命中为 0（plan_id=' . $goldenTicketPlanId . '），请检查爱发电 query-order 返回结构是否变更';
                if (function_exists('log_info')) {
                    log_info($warnMsg, 'aifadian_process');
                } else {
                    echo $warnMsg . "\n";
                }
            }
            
            $result['success'] = true;
            $result['message'] = '订单处理完成';
            $result['data']['processed_count'] = $processedCount;
            $result['data']['updated_players'] = $updatedPlayers;
            $result['data']['errors'] = $errors;
            
            if (function_exists('log_info')) {
                log_info('订单处理完成，成功处理: ' . $processedCount . ', 错误: ' . count($errors), 'aifadian_process');
            } else {
                echo "订单处理完成，成功处理: " . $processedCount . ", 错误: " . count($errors) . "\n";
            }
            
        } catch (Exception $e) {
            $errorMsg = '处理订单过程中发生错误: ' . $e->getMessage();
            if (function_exists('log_error')) {
                log_error($errorMsg, 'aifadian_process');
            } else {
                echo $errorMsg . "\n";
            }
            $result['message'] = $errorMsg;
            $result['data']['errors'][] = $errorMsg;
        }
        
        return $result;
    }
    
    // 获取订单处理状态
    public function getOrderStatus() {
        $result = array(
            'success' => false,
            'message' => '',
            'data' => array(
                'stats' => array(),
                'recent_orders' => array()
            )
        );
        
        try {
            // 获取订单状态统计
            $stats = $this->db->getOrderStatusStats();
            
            // 获取最近的订单
            $recentOrders = $this->db->getAfdianOrders(10);
            
            $result['success'] = true;
            $result['message'] = '获取订单状态成功';
            $result['data']['stats'] = $stats;
            $result['data']['recent_orders'] = $recentOrders;
            
        } catch (Exception $e) {
            $result['message'] = '获取订单状态失败: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    // 获取玩家点数
    public function getPlayerPoints($username) {
        $result = array(
            'success' => false,
            'message' => '',
            'data' => array(
                'username' => $username,
                'points' => 0,
                'uuid' => null
            )
        );
        
        try {
            // 检查玩家是否存在
            if (!$this->db->playerExists($username)) {
                throw new Exception('玩家不存在');
            }
            
            // 获取玩家UUID
            $playerUUID = $this->db->getPlayerUUID($username);
            if (!$playerUUID) {
                throw new Exception('无法获取玩家UUID');
            }
            
            // 获取玩家点数
            $points = $this->db->getPlayerPoints($playerUUID);
            
            $result['success'] = true;
            $result['message'] = '获取玩家点数成功';
            $result['data']['username'] = $username;
            $result['data']['points'] = $points;
            $result['data']['uuid'] = $playerUUID;
            
        } catch (Exception $e) {
            $result['message'] = '获取玩家点数失败: ' . $e->getMessage();
        }
        
        return $result;
    }
}
?>