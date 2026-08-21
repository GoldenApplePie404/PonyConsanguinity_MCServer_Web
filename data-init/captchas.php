<?php
if (!defined('ACCESS_ALLOWED')) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

// 滑块验证码运行时数据（CaptchaHelper 使用）
// 结构：challenges（验证挑战）/ tokens（一次性验证令牌）
// 仅存随机挑战与一次性令牌，无用户隐私；10 分钟过期自动清理
return [
    'challenges' => [],
    'tokens' => [],
];
