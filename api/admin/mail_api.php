<?php
/**
 * 邮件发送 API（后台管理用）
 * 基于 MailHelper(SMTP) 实现单发/群发，支持 HTML 内容
 * 鉴权：站点管理员 AuthHelper::requireAdmin()
 */

if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helper.php';
require_once __DIR__ . '/../secure_data.php';
require_once __DIR__ . '/../../includes/auth_helper.php';
require_once __DIR__ . '/../../includes/mail_helper.php';

header('Content-Type: application/json; charset=utf-8');
set_cors_headers();
set_security_headers();

// 只允许 GET 或 POST 请求
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
    json_response(false, '不支持的请求方法', null, 405);
}

// 管理员鉴权（统一走 AuthHelper）
AuthHelper::requireAdmin();

// 解析请求参数
$input = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
}
$action = $input['action'] ?? ($_GET['action'] ?? '');

switch ($action) {
    case 'send':
        // 收件人：支持字符串（逗号分隔）或数组
        $toRaw = $input['to'] ?? '';
        $subject = trim((string)($input['subject'] ?? ''));
        $html = (string)($input['html'] ?? '');
        $altBody = (string)($input['altBody'] ?? '');

        // 解析收件人列表
        $toList = [];
        if (is_array($toRaw)) {
            $toList = $toRaw;
        } else {
            $toList = array_map('trim', explode(',', (string)$toRaw));
        }
        $toList = array_values(array_filter($toList, function ($e) { return $e !== ''; }));

        // 参数校验
        if (empty($toList)) {
            json_response(false, '请至少填写一个收件人邮箱', null, 400);
        }
        if ($subject === '') {
            json_response(false, '邮件主题不能为空', null, 400);
        }
        if ($html === '') {
            json_response(false, '邮件内容不能为空', null, 400);
        }
        if (count($toList) > 200) {
            json_response(false, '单次发送收件人过多（最多 200 个）', null, 400);
        }

        // 校验每个邮箱格式
        foreach ($toList as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                json_response(false, "邮箱格式无效: {$email}", null, 400);
            }
        }

        // 去重（保留顺序）
        $toList = array_values(array_unique($toList));

        $mailer = MailHelper::getInstance();
        if (!$mailer->isEnabled()) {
            json_response(false, '邮件功能未启用（EMAIL_VERIFICATION_ENABLED 为 false）', null, 400);
        }

        // 逐封发送，互不影响
        $results = [];
        $ok = 0;
        $fail = 0;
        foreach ($toList as $email) {
            $r = $mailer->send($email, $subject, $html, $altBody);
            $results[] = [
                'to' => $email,
                'success' => $r['success'],
                'message' => $r['message']
            ];
            if ($r['success']) {
                $ok++;
            } else {
                $fail++;
            }
        }

        $allOk = $fail === 0;
        json_response(
            $allOk,
            "发送完成：成功 {$ok} 封，失败 {$fail} 封",
            [
                'results' => $results,
                'success_count' => $ok,
                'fail_count' => $fail
            ]
        );
        break;

    case 'test':
        // 快速自测：发送测试邮件到当前管理员账号邮箱
        $me = AuthHelper::getCurrentUser();
        $email = $me['email'] ?? '';
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_response(false, '当前账号未绑定有效邮箱，无法自测', null, 400);
        }
        $r = MailHelper::getInstance()->send(
            $email,
            '【万驹同源】后台邮件功能测试',
            '<div style="padding:24px;font-family:sans-serif;color:#333;"><h2 style="color:#2e6fdb;margin-top:0;">邮件功能测试 ✅</h2><p>这是一封来自管理后台邮件发送功能的测试邮件。</p><p>如果你的邮箱收到此邮件，说明 SMTP 配置与后台发送链路均正常。</p><p style="color:#999;font-size:12px;">此邮件由系统自动发送，请勿回复。</p></div>',
            '这是一封来自管理后台邮件发送功能的测试邮件。'
        );
        json_response($r['success'], $r['message'], $r);
        break;

    default:
        json_response(false, '无效的操作', null, 400);
        break;
}
?>
