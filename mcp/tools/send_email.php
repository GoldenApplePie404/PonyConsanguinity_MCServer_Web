<?php
/**
 * MCP Tools — 邮件发送（send_email）
 * ============================================================
 * 复用网站 SMTP 组件（MailHelper）向一个或多个收件人发送 HTML 邮件。
 *
 * 权限：admin_only
 *   - 仅 admin 角色可调用（mcp-server.php 管理渠道 + remote.php 的 admin Service Key）
 *   - 客服渠道（persona=customer）走白名单 getAiAllowedTools('customer')，本工具不在白名单内，
 *     因此客服 AI 既看不到也调不到，天然隔离。
 *
 */

require_once dirname(__DIR__, 2) . '/includes/mail_helper.php';

/**
 * send_email — 通过 SMTP 发送邮件（支持单发 / 群发）
 *
 * @param array $args
 *   - to      : string|string[]  收件人邮箱；支持逗号分隔字符串或多个地址数组
 *   - subject : string           邮件主题（必填）
 *   - body    : string           邮件正文（HTML 格式，必填）
 *   - altBody : string           可选纯文本备用内容；留空自动从 HTML 提取
 * @return string JSON 摘要（success / sent / failed / total / results[]）
 */
function handle_send_email(array $args): string
{
    $mailer = MailHelper::getInstance();
    if (!$mailer->isEnabled()) {
        throw new \Exception('邮件功能未启用（EMAIL_VERIFICATION_ENABLED=false）');
    }

    // ── 解析收件人：字符串（逗号分隔）或数组 ──
    $toRaw = $args['to'] ?? [];
    if (is_string($toRaw)) {
        $toRaw = array_map('trim', explode(',', $toRaw));
    }
    if (!is_array($toRaw) || empty($toRaw)) {
        throw new \Exception('缺少收件人（to）');
    }

    // ── 去重 + 邮箱格式校验 ──
    $recipients = [];
    $MAX = 200;
    foreach ($toRaw as $addr) {
        $addr = trim((string) $addr);
        if ($addr === '') {
            continue;
        }
        if (!filter_var($addr, FILTER_VALIDATE_EMAIL)) {
            throw new \Exception("非法邮箱地址: {$addr}");
        }
        $key = strtolower($addr);
        if (!isset($recipients[$key])) {
            $recipients[$key] = $addr;
        }
    }
    $recipients = array_values($recipients);
    if (empty($recipients)) {
        throw new \Exception('没有有效的收件人邮箱');
    }
    if (count($recipients) > $MAX) {
        throw new \Exception("收件人数量超过上限（{$MAX}）");
    }

    // ── 主题 / 正文校验 ──
    $subject = trim((string) ($args['subject'] ?? ''));
    if ($subject === '') {
        throw new \Exception('缺少邮件主题（subject）');
    }
    $body = $args['body'] ?? '';
    if (!is_string($body) || trim($body) === '') {
        throw new \Exception('缺少邮件正文（body，HTML 格式）');
    }
    $altBody = trim((string) ($args['altBody'] ?? ''));

    // ── 逐收件人发送（互不影响）──
    $results = [];
    $ok   = 0;
    $fail = 0;
    foreach ($recipients as $addr) {
        $r = $mailer->send($addr, $subject, $body, $altBody);
        if (!empty($r['success'])) {
            $ok++;
            $results[] = ['to' => $addr, 'success' => true];
        } else {
            $fail++;
            $results[] = ['to' => $addr, 'success' => false, 'error' => $r['message'] ?? '未知错误'];
        }
    }

    $summary = [
        'success' => $fail === 0,
        'sent'    => $ok,
        'failed'  => $fail,
        'total'   => count($recipients),
        'results' => $results,
    ];
    return json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

// ── 工具注册（自注册模式） ──────────────────────────────
// toolbase.php 通过 glob 自动加载本文件并调用 registerTool。

registerTool('send_email', [
    'name'        => 'send_email',
    'description' => '通过网站 SMTP 组件向一个或多个收件人发送邮件。正文 body 为 HTML 格式，可用于群发公告、活动通知、用户召回等。'
        . '收件人 to 支持逗号分隔的多个地址字符串，或多个地址组成的数组。',
    'inputSchema' => [
        'type'       => 'object',
        'properties' => (object) [
            'to'      => ['type' => 'string', 'description' => '收件人邮箱，支持逗号分隔的多个地址字符串，或多个地址组成的数组'],
            'subject' => ['type' => 'string', 'description' => '邮件主题'],
            'body'    => ['type' => 'string', 'description' => '邮件正文，HTML 格式'],
            'altBody' => ['type' => 'string', 'description' => '可选，纯文本备用内容；留空则自动从 HTML 提取'],
        ],
        'required'   => ['to', 'subject', 'body'],
    ],
    'permission'      => 'admin_only',
    'handler'         => 'handle_send_email',
]);
