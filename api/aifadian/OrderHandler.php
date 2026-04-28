<?php
/**
 * 统一订单处理器
 * 处理Webhook和API的订单处理逻辑
 */

class OrderHandler {
    private $db;
    private $config;
    
    public function __construct($db, $config) {
        $this->db = $db;
        $this->config = $config;
    }
    
    /**
     * 处理单个订单
     * @param array $orderData 订单数据
     * @param string $source 来源（webhook或api）
     * @return array 处理结果
     */
    public function processOrder($orderData, $source = 'api') {
        $result = array(
            'success' => false,
            'message' => '',
            'data' => array()
        );
        
        try {
            $outTradeNo = $orderData['out_trade_no'] ?? '';
            $remark = $orderData['remark'] ?? '';
            $planId = $orderData['plan_id'] ?? '';
            $amount = floatval($orderData['total_amount'] ?? 0);
            
            // 检查订单号
            if (empty($outTradeNo)) {
                throw new Exception('订单号为空');
            }
            
            // 检查订单是否已处理
            if ($this->db->isOrderProcessed($outTradeNo)) {
                $logMessage = "订单已处理，跳过: {$outTradeNo}";
                if (function_exists('log_info')) {
                    log_info($logMessage, 'aifadian_' . $source);
                }
                return array(
                    'success' => true,
                    'message' => '订单已处理',
                    'data' => array(
                        'out_trade_no' => $outTradeNo,
                        'status' => 'skipped'
                    )
                );
            }
            
            // 检查用户名
            if (empty($remark)) {
                $logMessage = "订单没有玩家名称，跳过: {$outTradeNo}";
                if (function_exists('log_info')) {
                    log_info($logMessage, 'aifadian_' . $source);
                }
                return array(
                    'success' => false,
                    'message' => '订单没有玩家名称',
                    'data' => array(
                        'out_trade_no' => $outTradeNo,
                        'status' => 'failed'
                    )
                );
            }
            
            // 检查玩家是否存在
            if (!$this->db->playerExists($remark)) {
                $logMessage = "玩家不存在，跳过: {$remark}";
                if (function_exists('log_info')) {
                    log_info($logMessage, 'aifadian_' . $source);
                }
                return array(
                    'success' => false,
                    'message' => '玩家不存在: ' . $remark,
                    'data' => array(
                        'out_trade_no' => $outTradeNo,
                        'status' => 'failed'
                    )
                );
            }
            
            // 获取玩家UUID
            $playerUUID = $this->db->getPlayerUUID($remark);
            if (!$playerUUID) {
                $logMessage = "无法获取玩家UUID，跳过: {$remark}";
                if (function_exists('log_info')) {
                    log_info($logMessage, 'aifadian_' . $source);
                }
                return array(
                    'success' => false,
                    'message' => '无法获取玩家UUID: ' . $remark,
                    'data' => array(
                        'out_trade_no' => $outTradeNo,
                        'status' => 'failed'
                    )
                );
            }
            
            // 计算需要增加的点数
            $pointsToAdd = $this->calculatePoints($orderData, $amount);
            
            // 获取当前点数
            $oldPoints = $this->db->getPlayerPoints($playerUUID);
            $newPoints = $oldPoints + $pointsToAdd;
            
            // 开始事务
            $this->db->beginTransaction();
            
            try {
                // 先标记订单为已处理（防止并发）
                $markResult = $this->db->markOrderProcessed($outTradeNo);
                if ($markResult === false) {
                    // 订单已被其他进程处理，回滚事务
                    $this->db->rollback();
                    $logMessage = "订单已被其他进程处理，跳过: {$outTradeNo}";
                    if (function_exists('log_info')) {
                        log_info($logMessage, 'aifadian_' . $source);
                    }
                    return array(
                        'success' => true,
                        'message' => '订单已被其他进程处理',
                        'data' => array(
                            'out_trade_no' => $outTradeNo,
                            'status' => 'skipped'
                        )
                    );
                }
                
                // 更新玩家点数
                $this->db->updatePlayerPoints($playerUUID, $newPoints);
                
                // 保存订单到数据库
                $orderSaveData = array(
                    'out_trade_no' => $outTradeNo,
                    'remark' => $remark,
                    'create_time' => isset($orderData['create_time']) ? $orderData['create_time'] : time(),
                    'plan_title' => $orderData['plan_title'] ?? '黄金券充值',
                    'plan_id' => $planId,
                    'sku_count' => $this->getSkuCount($orderData),
                    'points_added' => $pointsToAdd,
                    'player_uuid' => $playerUUID,
                    'player_username' => $remark,
                    'status' => 'completed',
                    'processed_at' => date('Y-m-d H:i:s')
                );
                
                $this->db->saveAfdianOrder($orderSaveData);
                
                // 提交事务
                $this->db->commit();
                
                $logMessage = "订单处理成功: {$outTradeNo}, 玩家: {$remark}, 点数: {$oldPoints} → {$newPoints}";
                if (function_exists('log_info')) {
                    log_info($logMessage, 'aifadian_' . $source);
                }
                
                return array(
                    'success' => true,
                    'message' => '订单处理成功',
                    'data' => array(
                        'out_trade_no' => $outTradeNo,
                        'player_username' => $remark,
                        'player_uuid' => $playerUUID,
                        'old_points' => $oldPoints,
                        'new_points' => $newPoints,
                        'points_added' => $pointsToAdd,
                        'status' => 'completed'
                    )
                );
                
            } catch (Exception $e) {
                // 回滚事务
                $this->db->rollback();
                throw $e;
            }
            
        } catch (Exception $e) {
            $errorMessage = '处理订单失败: ' . $e->getMessage();
            if (function_exists('log_error')) {
                log_error($errorMessage, 'aifadian_' . $source);
            }
            return array(
                'success' => false,
                'message' => $e->getMessage(),
                'data' => array(
                    'out_trade_no' => $outTradeNo ?? '',
                    'status' => 'error'
                )
            );
        }
    }
    
    /**
     * 计算点数
     * @param array $orderData 订单数据
     * @param float $amount 金额
     * @return int 点数
     */
    private function calculatePoints($orderData, $amount) {
        $planId = $orderData['plan_id'] ?? '';
        
        // 黄金券订单
        if ($planId == $this->config['plan_ids']['golden_ticket']) {
            // 计算方式：1元 = 100黄金券
            return intval($amount * 100);
        }
        
        // 其他订单类型
        return 0;
    }
    
    /**
     * 获取商品数量
     * @param array $orderData 订单数据
     * @return int 数量
     */
    private function getSkuCount($orderData) {
        $skuCount = 1;
        if (isset($orderData['sku_detail']) && is_array($orderData['sku_detail'])) {
            foreach ($orderData['sku_detail'] as $sku) {
                if (isset($sku['count'])) {
                    $skuCount = $sku['count'];
                    break;
                }
            }
        }
        return $skuCount;
    }
}
?>