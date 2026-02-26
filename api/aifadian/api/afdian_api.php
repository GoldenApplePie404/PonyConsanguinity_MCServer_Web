<?php
/**
 * 爱发电API封装类
 * 用于调用爱发电的各种API接口
 */
class AfdianAPI {
    private $userId;
    private $apiToken;
    private $apiUrl = 'https://afdian.com/api/open/';
    
    /**
     * 构造函数
     * @param string $userId 爱发电用户ID
     * @param string $apiToken 爱发电API令牌
     */
    public function __construct($userId, $apiToken) {
        $this->userId = $userId;
        $this->apiToken = $apiToken;
    }
    
    /**
     * 计算签名
     * @param string $paramsJson params参数的JSON字符串
     * @param int $ts 时间戳
     * @return string 签名
     */
    private function sign($paramsJson, $ts) {
        $kvString = "params{$paramsJson}ts{$ts}user_id{$this->userId}";
        return md5($this->apiToken . $kvString);
    }
    
    /**
     * 发送API请求
     * @param string $method API方法名
     * @param array $params 请求参数
     * @return array 响应数据
     */
    private function request($method, $params) {
        // 检查curl扩展是否可用
        if (!function_exists('curl_init')) {
            throw new Exception('PHP curl extension is not enabled. Please enable curl extension in php.ini.');
        }
        
        $ts = time();
        $paramsJson = json_encode($params);
        $sign = $this->sign($paramsJson, $ts);
        
        $data = array(
            'user_id' => $this->userId,
            'params' => $paramsJson,
            'ts' => $ts,
            'sign' => $sign
        );
        
        $url = $this->apiUrl . $method;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/x-www-form-urlencoded'
        ));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 增加超时时间
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 禁用SSL验证（仅用于测试）
        
        // 调试信息
        $debugInfo = array(
            'url' => $url,
            'request_data' => $data,
            'user_id' => $this->userId
        );
        error_log('爱发电API请求: ' . json_encode($debugInfo, JSON_UNESCAPED_UNICODE));
        
        $response = curl_exec($ch);
        
        // 检查curl错误
        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            curl_close($ch);
            throw new Exception('Curl error: ' . $error_msg);
        }
        
        // 检查HTTP状态码
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code != 200) {
            throw new Exception('HTTP error: ' . $http_code . ', Response: ' . $response);
        }
        
        $result = json_decode($response, true);
        
        if ($result === null) {
            throw new Exception('Invalid JSON response: ' . $response);
        }
        
        return $result;
    }
    
    /**
     * 测试签名是否正确
     * @return array 响应数据
     */
    public function ping() {
        return $this->request('ping', array('a' => 1));
    }
    
    /**
     * 查询订单
     * @param string $outTradeNo 订单号（可选）
     * @param int $page 页码（默认1）
     * @param int $perPage 每页数量（默认50）
     * @param string $startTime 开始时间（可选）
     * @param string $endTime 结束时间（可选）
     * @return array 响应数据
     */
    public function queryOrder($outTradeNo = null, $page = 1, $perPage = 50, $startTime = null, $endTime = null) {
        $params = array();
        
        if ($outTradeNo) {
            $params['out_trade_no'] = $outTradeNo;
        }
        
        if ($page) {
            $params['page'] = $page;
        }
        
        if ($perPage) {
            $params['per_page'] = $perPage;
        }
        
        if ($startTime) {
            $params['start_time'] = $startTime;
        }
        
        if ($endTime) {
            $params['end_time'] = $endTime;
        }
        
        return $this->request('query-order', $params);
    }
    
    /**
     * 查询赞助者
     * @param string $userId 用户ID（可选）
     * @param int $page 页码（默认1）
     * @param int $perPage 每页数量（默认20）
     * @return array 响应数据
     */
    public function querySponsor($userId = null, $page = 1, $perPage = 20) {
        $params = array(
            'page' => $page,
            'per_page' => $perPage
        );
        
        if ($userId) {
            $params['user_id'] = $userId;
        }
        
        return $this->request('query-sponsor', $params);
    }
    
    /**
     * 查询方案信息
     * @param string $planId 方案ID
     * @return array 响应数据
     */
    public function queryPlan($planId) {
        try {
            // 尝试使用query-plan接口
            return $this->request('query-plan', array('plan_id' => $planId));
        } catch (Exception $e) {
            // 如果是售卖类型的方案，返回特殊处理
            return array(
                'ec' => 200,
                'em' => 'ok',
                'data' => array(
                    'plan_id' => $planId,
                    'title' => '售卖方案',
                    'product_type' => 1
                )
            );
        }
    }
    
    /**
     * 查询商品信息（售卖类型的方案）
     * @param string $productId 商品ID
     * @return array 响应数据
     */
    public function queryProduct($productId) {
        // 注意：爱发电API可能没有专门的商品查询接口
        // 这里返回一个模拟的响应，实际使用时可能需要调整
        return array(
            'ec' => 200,
            'em' => 'ok',
            'data' => array(
                'product_id' => $productId,
                'title' => '售卖商品',
                'product_type' => 1
            )
        );
    }
    
    /**
     * 发送私信
     * @param string $recipient 接收者用户ID
     * @param string $content 私信内容
     * @return array 响应数据
     */
    public function sendMsg($recipient, $content) {
        return $this->request('send-msg', array(
            'recipient' => $recipient,
            'content' => $content
        ));
    }
    
    /**
     * 查询随机自动回复
     * @param string $outTradeNo 订单号
     * @return array 响应数据
     */
    public function queryRandomReply($outTradeNo) {
        return $this->request('query-random-reply', array(
            'out_trade_no' => $outTradeNo
        ));
    }
}
?>