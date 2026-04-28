<?php
/**
 * AI 客服技能引擎
 * 提供服务器状态查询、玩家信息查询等技能
 */

require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json; charset=utf-8');

// 允许跨域
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

/**
 * 技能引擎类
 */
class SkillEngine {
    private $skills = [];
    private $mcServerHost;
    private $mcServerPort;
    
    public function __construct() {
        // 服务器地址从 config.php 读取或默认值
        $this->mcServerHost = defined('MC_SERVER_IP') ? MC_SERVER_IP : 'mc.eqmemory.cn';
        $this->mcServerPort = defined('MC_SERVER_PORT') ? MC_SERVER_PORT : 25565;
        
        // 注册技能
        $this->registerSkills();
    }
    
    /**
     * 注册所有可用技能
     */
    private function registerSkills() {
        $this->skills['server_status'] = [$this, 'getServerStatus'];
        $this->skills['server_version'] = [$this, 'getServerVersion'];
        $this->skills['player_count'] = [$this, 'getPlayerCount'];
    }
    
    /**
     * 执行技能
     */
    public function execute($skillName, $params = []) {
        if (!isset($this->skills[$skillName])) {
            return [
                'success' => false,
                'message' => "技能 '{$skillName}' 不存在",
                'skill' => $skillName
            ];
        }
        
        try {
            $result = call_user_func($this->skills[$skillName], $params);
            return $result;
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => "技能执行失败：" . $e->getMessage(),
                'skill' => $skillName
            ];
        }
    }
    
    /**
     * 技能：获取服务器状态
     */
    private function getServerStatus($params) {
        $status = $this->queryMCServer();
        
        if ($status['online']) {
            return [
                'success' => true,
                'skill' => 'server_status',
                'data' => $status,
                'message' => "服务器当前在线，玩家数量：{$status['players']['online']}/{$status['players']['max']}"
            ];
        } else {
            return [
                'success' => false,
                'skill' => 'server_status',
                'message' => '服务器当前离线或无法连接',
                'data' => $status  // 包含调试信息
            ];
        }
    }
    
    /**
     * 技能：获取玩家数量
     */
    private function getPlayerCount($params) {
        $status = $this->queryMCServer();
        
        if ($status['online']) {
            $count = $status['players']['online'];
            $max = $status['players']['max'];
            $percentage = round(($count / $max) * 100, 1);
            
            return [
                'success' => true,
                'skill' => 'player_count',
                'data' => [
                    'online' => $count,
                    'max' => $max,
                    'percentage' => $percentage
                ],
                'message' => "服务器当前有 {$count} 名玩家在线喵，最大容量 {$max} 人（使用率 {$percentage}%）喵~"
            ];
        } else {
            return [
                'success' => false,
                'skill' => 'player_count',
                'message' => '无法获取玩家数量，服务器可能离线'
            ];
        }
    }
    
    /**
     * 技能：获取服务器版本
     */
    private function getServerVersion($params) {
        $status = $this->queryMCServer();
        
        if ($status['online'] && isset($status['version']['name'])) {
            return [
                'success' => true,
                'skill' => 'server_version',
                'data' => [
                    'version' => $status['version']['name'],
                    'protocol' => $status['version']['protocol'] ?? 'unknown'
                ],
                'message' => "服务器版本：{$status['version']['name']}"
            ];
        } else {
            return [
                'success' => false,
                'skill' => 'server_version',
                'message' => '无法获取服务器版本'
            ];
        }
    }
    
    /**
     * 查询 Minecraft 服务器状态
     * 直接调用 mcstatus.php API
     */
    private function queryMCServer() {
        $debugInfo = [];
        
        // 第一层：通过 HTTP 请求调用 mcstatus.php
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'mcbgp.eqmemory.cn';
        $apiUrl = $protocol . '://' . $host . '/api/mcstatus.php';
        $debugInfo['layer1_url'] = $apiUrl;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        $debugInfo['layer1_http_code'] = $httpCode;
        $debugInfo['layer1_error'] = $curlError;
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            $debugInfo['layer1_response'] = $data;
            
            if ($data && isset($data['online'])) {
                $result = [
                    'online' => $data['online'],
                    'players' => [
                        'online' => $data['players']['online'] ?? 0,
                        'max' => $data['players']['max'] ?? 0,
                        'list' => $data['players']['list'] ?? []
                    ],
                    'version' => [
                        'name' => $data['version']['name'] ?? 'Unknown',
                        'protocol' => $data['version']['protocol'] ?? 0
                    ],
                    'motd' => $data['motd'] ?? '',
                    'hostname' => $this->mcServerHost,
                    'port' => $this->mcServerPort,
                    'debug' => $debugInfo
                ];
                return $result;
            }
        }
        
        // 第二层：使用外部 API 查询
        $debugInfo['layer1_failed'] = true;
        return $this->queryExternalAPI($debugInfo);
    }
    
    /**
     * 使用外部 API 查询服务器状态（后备方案）
     */
    private function queryExternalAPI($debugInfo = []) {
        $apiUrl = MCSTATUS_API_URL . "?ip=" . urlencode($this->mcServerHost) . "&port=" . $this->mcServerPort;
        $debugInfo['layer2_url'] = $apiUrl;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // 跟随重定向
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5); // 最多 5 次重定向
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        $debugInfo['layer2_http_code'] = $httpCode;
        $debugInfo['layer2_error'] = $curlError;
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            $debugInfo['layer2_response'] = $data;
            
            // API 返回格式：{"code": 200, "data": {...}, "queryTime": ...}
            if ($data && isset($data['code']) && $data['code'] === 200 && isset($data['data'])) {
                $serverData = $data['data'];
                return [
                    'online' => true,
                    'players' => [
                        'online' => $serverData['players']['online'] ?? 0,
                        'max' => $serverData['players']['max'] ?? 0,
                        'list' => $serverData['players']['list'] ?? []
                    ],
                    'version' => [
                        'name' => $serverData['version'] ?? 'Unknown',
                        'protocol' => $serverData['protocol'] ?? 0
                    ],
                    'motd' => $serverData['motd'] ?? '',
                    'hostname' => $this->mcServerHost,
                    'port' => $this->mcServerPort,
                    'debug' => $debugInfo
                ];
            }
        }
        
        // 第三层：Socket 直连
        $debugInfo['layer2_failed'] = true;
        $socketResult = $this->queryServerDirectly();
        $socketResult['debug'] = $debugInfo;
        return $socketResult;
    }
    
    /**
     * 直接使用 PHP socket 查询服务器状态
     */
    private function queryServerDirectly() {
        $socket = @fsockopen($this->mcServerHost, $this->mcServerPort, $errno, $errstr, 3);
        
        if ($socket) {
            fclose($socket);
            return [
                'online' => true,
                'players' => [
                    'online' => 0,
                    'max' => 0,
                    'list' => []
                ],
                'version' => [
                    'name' => 'Unknown',
                    'protocol' => 0
                ],
                'motd' => '',
                'hostname' => $this->mcServerHost,
                'port' => $this->mcServerPort
            ];
        }
        
        return ['online' => false];
    }
    
    /**
     * 获取可用技能列表
     */
    public function getAvailableSkills() {
        return [
            [
                'name' => 'server_status',
                'displayName' => '服务器状态查询',
                'description' => '查询服务器在线状态、玩家数量等信息',
                'examples' => ['服务器状态如何？', '现在有多少人在线？', '服务器开了吗？']
            ],
            [
                'name' => 'server_version',
                'displayName' => '服务器版本查询',
                'description' => '查询服务器版本信息',
                'examples' => ['服务器是什么版本？', '支持哪些版本？']
            ],
            [
                'name' => 'player_count',
                'displayName' => '玩家数量查询',
                'description' => '查询当前在线玩家数量',
                'examples' => ['多少人在线？', '服务器容量是多少？']
            ]
        ];
    }
}

// 处理请求
$engine = new SkillEngine();

// 获取请求数据
$input = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? ($input['action'] ?? 'execute');

if ($action === 'list') {
    // 返回可用技能列表
    echo json_encode([
        'success' => true,
        'skills' => $engine->getAvailableSkills()
    ]);
} else {
    // 执行技能
    $skillName = $_GET['skill'] ?? ($input['skill'] ?? '');
    $params = $_GET['params'] ?? ($input['params'] ?? []);
    
    if (empty($skillName)) {
        echo json_encode([
            'success' => false,
            'message' => '请指定技能名称'
        ]);
        exit;
    }
    
    $result = $engine->execute($skillName, $params);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
}
?>
