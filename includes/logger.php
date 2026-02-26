<?php
/**
 * 统一日志管理模块
 * 提供统一的日志记录功能
 */

class Logger {
    private static $instance = null;
    private $logDir;
    private $logLevel;
    
    private function __construct($logDir = null, $logLevel = 'info') {
        // 如果未指定日志目录，使用项目根目录下的logs文件夹
        if ($logDir === null) {
            $logDir = dirname(__DIR__) . '/logs';
        }
        
        $this->logDir = $logDir;
        $this->logLevel = $logLevel;
        
        // 确保日志目录存在
        $this->ensureDirectoryExists($logDir);
    }
    
    /**
     * 获取日志器实例
     */
    public static function getInstance($logDir = null, $logLevel = 'info') {
        if (self::$instance === null) {
            self::$instance = new self($logDir, $logLevel);
        }
        return self::$instance;
    }
    
    /**
     * 确保目录存在
     */
    private function ensureDirectoryExists($directory) {
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }
    
    /**
     * 记录调试日志
     */
    public function debug($message, $module = 'general') {
        if ($this->shouldLog('debug')) {
            $this->log($message, 'debug', $module);
        }
    }
    
    /**
     * 记录信息日志
     */
    public function info($message, $module = 'general') {
        if ($this->shouldLog('info')) {
            $this->log($message, 'info', $module);
        }
    }
    
    /**
     * 记录错误日志
     */
    public function error($message, $module = 'general') {
        if ($this->shouldLog('error')) {
            $this->log($message, 'error', $module);
        }
    }
    
    /**
     * 记录警告日志
     */
    public function warning($message, $module = 'general') {
        if ($this->shouldLog('warning')) {
            $this->log($message, 'warning', $module);
        }
    }
    
    /**
     * 记录日志
     */
    private function log($message, $level, $module) {
        $logFile = $this->logDir . '/' . $module . '_' . date('Ymd') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message\n";
        
        // 确保模块子目录存在
        $this->ensureDirectoryExists(dirname($logFile));
        
        // 写入日志文件
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        
        // 同时输出到控制台
        if (php_sapi_name() === 'cli') {
            echo $logMessage;
        }
    }
    
    /**
     * 检查是否应该记录该级别的日志
     */
    private function shouldLog($level) {
        $levels = array(
            'debug' => 0,
            'info' => 1,
            'warning' => 2,
            'error' => 3
        );
        
        $currentLevel = isset($levels[$this->logLevel]) ? $levels[$this->logLevel] : 1;
        $targetLevel = isset($levels[$level]) ? $levels[$level] : 1;
        
        return $targetLevel >= $currentLevel;
    }
    
    /**
     * 获取日志目录
     */
    public function getLogDir() {
        return $this->logDir;
    }
    
    /**
     * 设置日志级别
     */
    public function setLogLevel($logLevel) {
        $this->logLevel = $logLevel;
    }
}

// 全局日志函数
function log_debug($message, $module = 'general') {
    Logger::getInstance()->debug($message, $module);
}

function log_info($message, $module = 'general') {
    Logger::getInstance()->info($message, $module);
}

function log_warning($message, $module = 'general') {
    Logger::getInstance()->warning($message, $module);
}

function log_error($message, $module = 'general') {
    Logger::getInstance()->error($message, $module);
}

function log_to_file($message, $level = 'info', $module = 'general') {
    switch (strtolower($level)) {
        case 'debug':
            log_debug($message, $module);
            break;
        case 'info':
            log_info($message, $module);
            break;
        case 'warning':
            log_warning($message, $module);
            break;
        case 'error':
            log_error($message, $module);
            break;
        default:
            log_info($message, $module);
            break;
    }
}
?>