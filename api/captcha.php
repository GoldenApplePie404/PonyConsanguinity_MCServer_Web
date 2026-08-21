<?php
/**
 * 滑块验证 API
 *
 * action=create  → 生成挑战，返回 challenge_id + target_pct（目标位置百分比）
 * action=verify  → 校验滑块结果（位置 + 轨迹），通过则签发一次性 captcha_token
 *
 * 前端流程：
 *   1. POST captcha.php {action:'create'}            → {challenge_id, target_pct}
 *   2. 用户拖滑块到缺口，记录轨迹
 *   3. POST captcha.php {action:'verify', challenge_id, x_pct, trail} → {captcha_token}
 *   4. 业务请求携带 captcha_token，后端消费校验
 */
require_once 'config.php';
require_once 'helper.php';
require_once 'secure_data.php';
require_once __DIR__ . '/../includes/captcha_helper.php';

// 设置 CORS 和安全头
set_cors_headers();
set_security_headers();

// 只允许 POST 请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, '只允许 POST 请求', null, 405);
}

$data = get_post_data();
$action = $data['action'] ?? '';

switch ($action) {
    case 'create':
        $challenge = CaptchaHelper::createChallenge();
        json_response(true, '挑战已生成', $challenge, 200);
        break;

    case 'verify':
        $challengeId = trim($data['challenge_id'] ?? '');
        $xPct = $data['x_pct'] ?? null;
        $trail = $data['trail'] ?? [];

        if (empty($challengeId) || $xPct === null) {
            json_response(false, '缺少验证参数', null, 400);
        }

        if (!is_array($trail)) {
            $trail = [];
        }

        // 限制轨迹长度，防止超大请求体
        $trail = array_slice($trail, 0, 200);

        $result = CaptchaHelper::verifyChallenge($challengeId, $xPct, $trail);
        if ($result['success']) {
            json_response(true, '验证通过', ['captcha_token' => $result['captcha_token']], 200);
        } else {
            json_response(false, $result['message'], null, 400);
        }
        break;

    default:
        json_response(false, '未知操作', null, 400);
}
?>
