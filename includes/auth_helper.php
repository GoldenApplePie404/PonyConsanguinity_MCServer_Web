<?php
/**
 * 认证辅助类
 * 提供token验证和用户信息获取功能
 */

if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}

require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/helper.php';
require_once __DIR__ . '/../api/secure_data.php';

class AuthHelper {
    
    /**
     * 验证token是否有效
     * @return bool
     */
    public static function validateToken() {
        $headers = getallheaders();
        $token = null;
        
        // 从Authorization头获取token
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                $token = $matches[1];
            }
        }
        
        // 从请求参数获取token
        if (!$token && isset($_GET['token'])) {
            $token = $_GET['token'];
        }
        
        // 从POST数据获取token
        if (!$token) {
            $input = json_decode(file_get_contents('php://input'), true);
            if (isset($input['token'])) {
                $token = $input['token'];
            }
        }
        
        if (!$token) {
            return false;
        }
        
        // 验证token
        $sessions = secureReadData(SESSIONS_FILE);
        
        if (!isset($sessions[$token])) {
            return false;
        }
        
        $session = $sessions[$token];
        
        // 检查是否过期
        if (isset($session['expires_at']) && $session['expires_at'] < time()) {
            return false;
        }
        
        // 如果没有过期时间，默认24小时过期
        if (!isset($session['expires_at']) && isset($session['created_at'])) {
            $createdTime = strtotime($session['created_at']);
            if ($createdTime + 86400 < time()) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * 从token获取用户名
     * @return string|null
     */
    public static function getUsernameFromToken() {
        $headers = getallheaders();
        $token = null;
        
        // 从Authorization头获取token
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                $token = $matches[1];
            }
        }
        
        // 从请求参数获取token
        if (!$token && isset($_GET['token'])) {
            $token = $_GET['token'];
        }
        
        // 从POST数据获取token
        if (!$token) {
            $input = json_decode(file_get_contents('php://input'), true);
            if (isset($input['token'])) {
                $token = $input['token'];
            }
        }
        
        if (!$token) {
            return null;
        }
        
        // 获取会话信息
        $sessions = secureReadData(SESSIONS_FILE);
        
        if (!isset($sessions[$token])) {
            return null;
        }
        
        $session = $sessions[$token];
        
        // 检查是否过期
        if (isset($session['expires_at']) && $session['expires_at'] < time()) {
            return null;
        }
        
        // 如果没有过期时间，默认24小时过期
        if (!isset($session['expires_at']) && isset($session['created_at'])) {
            $createdTime = strtotime($session['created_at']);
            if ($createdTime + 86400 < time()) {
                return null;
            }
        }
        
        return $session['username'] ?? null;
    }
    
    /**
     * 获取当前登录用户信息
     * @return array|null
     */
    public static function getCurrentUser() {
        $username = self::getUsernameFromToken();
        
        if (!$username) {
            return null;
        }
        
        $users = secureReadData(USERS_FILE);
        
        if (!isset($users[$username])) {
            return null;
        }
        
        return $users[$username];
    }
}
?>
