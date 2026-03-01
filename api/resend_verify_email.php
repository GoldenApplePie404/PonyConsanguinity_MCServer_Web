<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 自定义错误处理
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    echo json_encode(['success' => false, 'message' => "PHP错误: $errstr in $errfile on line $errline"]);
    exit();
});

set_exception_handler(function($e) {
    echo json_encode(['success' => false, 'message' => '异常: ' . $e->getMessage()]);
    exit();
});

try {
    require_once 'config.php';
    require_once 'helper.php';
    require_once 'secure_data.php';
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => '加载文件失败: ' . $e->getMessage()]);
    exit();
}

// 获取 token
$headers = getallheaders();
$token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : '';

if (!$token) {
    echo json_encode(['success' => false, 'message' => '未提供认证令牌']);
    exit();
}

// 读取会话数据
$sessions = secureReadData(SESSIONS_FILE);

if (!isset($sessions[$token])) {
    echo json_encode(['success' => false, 'message' => '令牌无效或已过期']);
    exit();
}

$session = $sessions[$token];
$username = $session['username'];

// 读取用户数据
$users = secureReadData(USERS_FILE);

if (!isset($users[$username])) {
    echo json_encode(['success' => false, 'message' => '用户不存在']);
    exit();
}

$user = $users[$username];

// 检查是否已验证
if (!empty($user['email_verified'])) {
    echo json_encode(['success' => false, 'message' => '邮箱已验证，无需重复验证']);
    exit();
}

// 检查邮箱是否存在
if (empty($user['email'])) {
    echo json_encode(['success' => false, 'message' => '用户未设置邮箱']);
    exit();
}

// 检查发送频率限制（60秒内只能发送一次）
if (!empty($user['verify_sent_at'])) {
    $lastSent = strtotime($user['verify_sent_at']);
    $now = time();
    if (($now - $lastSent) < 60) {
        $waitSeconds = 60 - ($now - $lastSent);
        echo json_encode(['success' => false, 'message' => "请等待 {$waitSeconds} 秒后再发送"]);
        exit();
    }
}

// 生成验证令牌
$verifyToken = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

// 更新用户数据
$users[$username]['verify_token'] = $verifyToken;
$users[$username]['verify_expires'] = $expiresAt;
$users[$username]['verify_sent_at'] = date('Y-m-d H:i:s');
$users[$username]['verify_resend_count'] = ($users[$username]['verify_resend_count'] ?? 0) + 1;

if (!secureWriteData(USERS_FILE, $users)) {
    echo json_encode(['success' => false, 'message' => '保存验证令牌失败']);
    exit();
}

// 生成验证链接
$verifyLink = SITE_URL . '/pages/verify.html?token=' . $verifyToken;

// 环境检测：本地开发环境 vs 生产环境
$isLocalhost = isset($_SERVER['HTTP_HOST']) && (
    strpos($_SERVER['HTTP_HOST'], 'localhost') !== false ||
    strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false
);

if ($isLocalhost) {
    // 本地开发环境：返回验证链接，方便测试
    echo json_encode([
        'success' => true,
        'message' => '开发模式：请使用以下链接手动验证',
        'verify_link' => $verifyLink,
        'email' => $user['email']
    ]);
} else {
    // 生产环境：使用 PHPMailer 发送邮件
    try {
        require_once __DIR__ . '/../includes/mail_helper.php';
        $mailHelper = MailHelper::getInstance();
        $result = $mailHelper->sendVerificationEmail($user['email'], $user['username'], $verifyLink);
        
        if ($result['success']) {
            echo json_encode(['success' => true, 'message' => '验证邮件已发送，请检查您的邮箱（包括垃圾箱）']);
        } else {
            echo json_encode(['success' => false, 'message' => '邮件发送失败：' . $result['message']]);
        }
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => '邮件发送异常：' . $e->getMessage()]);
    }
}
?>
