<?php
/**
 * 安全日志模块 v2.0
 * 记录登录、注册等安全相关事件 - 纯文本日志
 */

require_once __DIR__ . '/logger.php';

class SecurityLogger {
    private static $instance = null;
    private $logger;
    private $logDir;
    
    private function __construct() {
        $this->logDir = dirname(__DIR__) . '/logs/security';
        $this->logger = Logger::getInstance($this->logDir, 'info');
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 获取客户端IP地址
     */
    public function getClientIP() {
        $ip = '';
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
            $ip = $_SERVER['HTTP_FORWARDED'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        // 处理多个IP的情况（如代理）
        if (strpos($ip, ',') !== false) {
            $ips = explode(',', $ip);
            $ip = trim($ips[0]);
        }
        
        return $ip ?: 'unknown';
    }
    
    /**
     * 获取用户代理信息
     */
    public function getUserAgent() {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    }
    
    /**
     * 解析设备信息
     */
    public function parseDeviceInfo() {
        $ua = $this->getUserAgent();
        
        $device = [
            'browser' => 'Unknown',
            'os' => 'Unknown',
            'device_type' => 'Desktop',
            'is_mobile' => false
        ];
        
        // 检测浏览器
        if (preg_match('/Firefox/i', $ua)) {
            $device['browser'] = 'Firefox';
        } elseif (preg_match('/Edg/i', $ua)) {
            $device['browser'] = 'Edge';
        } elseif (preg_match('/Chrome/i', $ua)) {
            $device['browser'] = 'Chrome';
        } elseif (preg_match('/Safari/i', $ua)) {
            $device['browser'] = 'Safari';
        } elseif (preg_match('/Opera|OPR/i', $ua)) {
            $device['browser'] = 'Opera';
        } elseif (preg_match('/MSIE|Trident/i', $ua)) {
            $device['browser'] = 'Internet Explorer';
        }
        
        // 检测操作系统
        if (preg_match('/Windows/i', $ua)) {
            $device['os'] = 'Windows';
        } elseif (preg_match('/Mac/i', $ua)) {
            $device['os'] = 'macOS';
        } elseif (preg_match('/Linux/i', $ua)) {
            $device['os'] = 'Linux';
        } elseif (preg_match('/Android/i', $ua)) {
            $device['os'] = 'Android';
            $device['device_type'] = 'Mobile';
            $device['is_mobile'] = true;
        } elseif (preg_match('/iPhone|iPad|iPod/i', $ua)) {
            $device['os'] = 'iOS';
            $device['device_type'] = preg_match('/iPad/i', $ua) ? 'Tablet' : 'Mobile';
            $device['is_mobile'] = true;
        }
        
        // 检测移动设备
        if (preg_match('/Mobile|Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i', $ua)) {
            $device['is_mobile'] = true;
            if ($device['device_type'] === 'Desktop') {
                $device['device_type'] = 'Mobile';
            }
        }
        
        return $device;
    }
    
    /**
     * 记录登录成功
     */
    public function logLoginSuccess($username, $additionalData = []) {
        $device = $this->parseDeviceInfo();
        $ip = $this->getClientIP();
        $role = $additionalData['role'] ?? 'user';
        
        $message = sprintf(
            "[登录成功] 用户: %s | 角色: %s | IP: %s | 设备: %s/%s/%s",
            $username,
            $role,
            $ip,
            $device['browser'],
            $device['os'],
            $device['device_type']
        );
        
        $this->logger->info($message, 'auth');
        
        return [
            'event' => 'LOGIN_SUCCESS',
            'username' => $username,
            'ip' => $ip,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * 记录登录失败
     */
    public function logLoginFailure($username, $reason = '密码错误', $additionalData = []) {
        $device = $this->parseDeviceInfo();
        $ip = $this->getClientIP();
        
        $message = sprintf(
            "[登录失败] 用户: %s | IP: %s | 原因: %s | 设备: %s/%s",
            $username,
            $ip,
            $reason,
            $device['browser'],
            $device['os']
        );
        
        $this->logger->warning($message, 'auth');
        
        return [
            'event' => 'LOGIN_FAILURE',
            'username' => $username,
            'ip' => $ip,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * 记录账户锁定
     */
    public function logAccountLocked($username, $reason = '登录失败次数过多', $additionalData = []) {
        $ip = $this->getClientIP();
        
        $message = sprintf(
            "[账户锁定] 用户: %s | IP: %s | 原因: %s",
            $username,
            $ip,
            $reason
        );
        
        $this->logger->error($message, 'auth');
        
        return [
            'event' => 'ACCOUNT_LOCKED',
            'username' => $username,
            'ip' => $ip,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * 记录注册
     */
    public function logRegister($username, $email, $additionalData = []) {
        $device = $this->parseDeviceInfo();
        $ip = $this->getClientIP();
        
        $message = sprintf(
            "[用户注册] 用户: %s | 邮箱: %s | IP: %s | 设备: %s/%s",
            $username,
            $email,
            $ip,
            $device['browser'],
            $device['os']
        );
        
        $this->logger->info($message, 'auth');
        
        return [
            'event' => 'REGISTER',
            'username' => $username,
            'ip' => $ip,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * 记录注销
     */
    public function logLogout($username, $additionalData = []) {
        $ip = $this->getClientIP();
        $device = $this->parseDeviceInfo();
        
        $message = sprintf(
            "[用户注销] 用户: %s | IP: %s | 设备: %s/%s",
            $username,
            $ip,
            $device['browser'],
            $device['os']
        );
        
        $this->logger->info($message, 'auth');
        
        return [
            'event' => 'LOGOUT',
            'username' => $username,
            'ip' => $ip,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * 记录账户删除
     */
    public function logAccountDeletion($username, $additionalData = []) {
        $ip = $this->getClientIP();
        
        $message = sprintf(
            "[账户删除] 用户: %s | IP: %s",
            $username,
            $ip
        );
        
        $this->logger->warning($message, 'auth');
        
        return [
            'event' => 'ACCOUNT_DELETED',
            'username' => $username,
            'ip' => $ip,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * 记录安全告警
     */
    public function logSecurityAlert($username, $alertType, $details = []) {
        $ip = $this->getClientIP();
        $detailsStr = json_encode($details, JSON_UNESCAPED_UNICODE);
        
        $message = sprintf(
            "[安全告警] 类型: %s | 用户: %s | IP: %s | 详情: %s",
            $alertType,
            $username,
            $ip,
            $detailsStr
        );
        
        $this->logger->error($message, 'auth');
        
        return [
            'event' => 'SECURITY_ALERT',
            'alert_type' => $alertType,
            'username' => $username,
            'ip' => $ip,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}

// 便捷函数
function security_log() {
    return SecurityLogger::getInstance();
}
?>
