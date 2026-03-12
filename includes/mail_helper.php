<?php
/**
 * 邮件发送辅助类
 * 基于 PHPMailer 实现 SMTP 邮件发送
 */

require_once __DIR__ . '/PHPMailer.php';
require_once __DIR__ . '/SMTP.php';
require_once __DIR__ . '/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class MailHelper {
    private static $instance = null;
    private $mailer;
    private $enabled;
    
    private function __construct() {
        $this->enabled = defined('EMAIL_VERIFICATION_ENABLED') ? EMAIL_VERIFICATION_ENABLED : false;
        
        if ($this->enabled) {
            $this->initMailer();
        }
    }
    
    /**
     * 获取单例实例
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 初始化 PHPMailer
     */
    private function initMailer() {
        $this->mailer = new PHPMailer(true);
        
        // 服务器设置
        $this->mailer->isSMTP();
        $this->mailer->Host = SMTP_HOST;
        $this->mailer->SMTPAuth = SMTP_AUTH;
        $this->mailer->Username = SMTP_USERNAME;
        $this->mailer->Password = SMTP_PASSWORD;
        $this->mailer->Port = SMTP_PORT;
        
        // 设置加密方式（如果为空则不加密）
        if (!empty(SMTP_ENCRYPTION)) {
            $this->mailer->SMTPSecure = SMTP_ENCRYPTION;
        } else {
            $this->mailer->SMTPSecure = false;
            $this->mailer->SMTPAutoTLS = false;
        }
        
        // 编码设置
        $this->mailer->CharSet = 'UTF-8';
        $this->mailer->Encoding = 'base64';
        
        // 发件人
        $this->mailer->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        
        // 调试模式（开发时启用）
        // $this->mailer->SMTPDebug = SMTP::DEBUG_SERVER;
    }
    
    /**
     * 发送邮件
     * 
     * @param string $to 收件人邮箱
     * @param string $subject 主题
     * @param string $body 邮件内容（HTML）
     * @param string $altBody 纯文本内容（可选）
     * @return array 发送结果
     */
    public function send($to, $subject, $body, $altBody = '') {
        if (!$this->enabled) {
            return [
                'success' => false,
                'message' => '邮件功能未启用'
            ];
        }
        
        try {
            // 清除之前的收件人
            $this->mailer->clearAddresses();
            
            // 设置收件人
            $this->mailer->addAddress($to);
            
            // 设置邮件内容
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;
            $this->mailer->AltBody = $altBody ?: strip_tags($body);
            
            // 发送
            $this->mailer->send();
            
            return [
                'success' => true,
                'message' => '邮件发送成功',
                'to' => $to
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => '邮件发送失败: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 发送纯文本邮件
     */
    public function sendText($to, $subject, $body) {
        if (!$this->enabled) {
            return [
                'success' => false,
                'message' => '邮件功能未启用'
            ];
        }
        
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($to);
            $this->mailer->isHTML(false);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;
            
            $this->mailer->send();
            
            return [
                'success' => true,
                'message' => '邮件发送成功',
                'to' => $to
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => '邮件发送失败: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 发送邮箱验证邮件
     * 
     * @param string $to 收件人邮箱
     * @param string $username 用户名
     * @param string $verifyUrl 验证链接
     * @return array 发送结果
     */
    public function sendVerificationEmail($to, $username, $verifyUrl) {
        $subject = '请验证您的邮箱 - ' . MAIL_FROM_NAME;
        $body = $this->generateVerifyEmailHtml($username, $verifyUrl);
        $altBody = $this->generateVerifyEmailText($username, $verifyUrl);
        
        return $this->send($to, $subject, $body, $altBody);
    }
    
    /**
     * 生成验证邮件 HTML 内容
     */
    private function generateVerifyEmailHtml($username, $verifyUrl) {
        $siteName = MAIL_FROM_NAME;
        $expiryHours = VERIFY_TOKEN_EXPIRY / 3600;
        
        // 读取邮件模板
        $templatePath = __DIR__ . '/email_templates/verify_email.html';
        
        if (file_exists($templatePath)) {
            $template = file_get_contents($templatePath);
            
            // 替换模板变量
            $replacements = [
                '{site_name}' => $siteName,
                '{username}' => $username,
                '{verify_url}' => $verifyUrl,
                '{expiry_hours}' => $expiryHours
            ];
            
            $html = str_replace(array_keys($replacements), array_values($replacements), $template);
            
            return $html;
        } else {
            // 如果模板文件不存在，返回错误信息
            return '<div style="padding: 20px; color: red;">邮件模板文件不存在: ' . $templatePath . '</div>';
        }
    }
    
    /**
     * 生成验证邮件纯文本内容
     */
    private function generateVerifyEmailText($username, $verifyUrl) {
        $siteName = MAIL_FROM_NAME;
        $expiryHours = VERIFY_TOKEN_EXPIRY / 3600;
        
        return <<<TEXT
您好，{$username}

感谢您注册 {$siteName}！

请点击以下链接验证您的邮箱地址：
{$verifyUrl}

此链接将在 {$expiryHours} 小时后过期。

如果您没有注册 {$siteName}，请忽略此邮件。

此邮件由系统自动发送，请勿回复。
TEXT;
    }
    
    /**
     * 检查邮件功能是否可用
     */
    public function isEnabled() {
        return $this->enabled;
    }
    
    /**
     * 测试邮件配置
     */
    public function testConfig() {
        if (!$this->enabled) {
            return [
                'success' => false,
                'message' => '邮件功能未启用'
            ];
        }
        
        $required = [
            'SMTP_HOST' => SMTP_HOST,
            'SMTP_PORT' => SMTP_PORT,
            'SMTP_USERNAME' => SMTP_USERNAME,
            'SMTP_PASSWORD' => SMTP_PASSWORD,
            'MAIL_FROM_EMAIL' => MAIL_FROM_EMAIL
        ];
        
        $missing = [];
        foreach ($required as $key => $value) {
            if (empty($value) || $value === 'your_email@qq.com' || $value === 'your_auth_code') {
                $missing[] = $key;
            }
        }
        
        if (!empty($missing)) {
            return [
                'success' => false,
                'message' => '以下配置项未设置: ' . implode(', ', $missing)
            ];
        }
        
        return [
            'success' => true,
            'message' => '邮件配置检查通过'
        ];
    }
}

/**
 * 便捷函数
 */
function mail_helper() {
    return MailHelper::getInstance();
}
?>
