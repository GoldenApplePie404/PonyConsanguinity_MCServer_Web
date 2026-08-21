<?php
/**
 * CaptchaHelper — 自建滑块验证核心库
 *
 * 防机器原理（后端强制参与）：
 *  1. create(): 生成一次性挑战 challenge_id + 随机目标位置 target_pct
 *  2. 前端用户拖动滑块到缺口，记录轨迹 trail（[{x,y,t},...]）
 *  3. verify(): 校验位置误差 + 人类轨迹特征（时长/点数/速度波动）→ 签发一次性 captcha_token
 *  4. 业务接口（注册/登录/重置密码等）消费 captcha_token，校验后立即销毁
 *
 * 数据存储：data/captchas.php（与 users.php 相同的 PHP 文件格式）
 *   - challenges: challenge_id => [target_pct, created_at, used]
 *   - tokens:     captcha_token => [created_at, used]
 *
 * 安全要点：
 *  - 挑战/令牌均一次性使用（used 标记 + 过期清理）
 *  - 过期时间短（10 分钟）
 *  - 防枚举：错误信息不区分具体失败原因
 */

if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}

require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/helper.php';
require_once __DIR__ . '/../api/secure_data.php';

class CaptchaHelper
{
    // ── 配置常量 ──────────────────────────────────────────────

    /** 挑战/令牌有效期（秒），默认 10 分钟 */
    const TTL = 600;

    /** 滑块位置误差阈值（百分比），目标 ±3% 内视为命中 */
    const POSITION_TOLERANCE = 3.0;

    /** 目标位置随机范围：35% ~ 85%（避开太靠边，防止直接猜） */
    const TARGET_MIN = 35;
    const TARGET_MAX = 85;

    /** 轨迹最少点数（防"一步到位"） */
    const TRAIL_MIN_POINTS = 5;

    /** 人类拖动最短时长（毫秒），低于此视为脚本模拟 */
    const TRAIL_MIN_DURATION_MS = 400;

    /** 轨迹起点最大位置（百分比），人类从左侧开始拖动 */
    const TRAIL_START_MAX_PCT = 25;

    /** 存储文件名 */
    const STORE_FILE = '/../data/captchas.php';

    // ── 公开接口 ──────────────────────────────────────────────

    /**
     * 生成滑块挑战
     *
     * @return array ['challenge_id' => string, 'target_pct' => float]
     */
    public static function createChallenge(): array
    {
        $store = self::loadStore();

        // 顺手清理：过期挑战 / 已使用挑战（防文件无限积累）
        // 每次创建新挑战时顺带做一次，避免单独定时任务
        self::purge($store);

        // 随机目标位置（保留 1 位小数）
        $targetPct = round(
            mt_rand((int)(self::TARGET_MIN * 10), (int)(self::TARGET_MAX * 10)) / 10,
            1
        );

        $challengeId = bin2hex(random_bytes(16));

        $store['challenges'][$challengeId] = [
            'target_pct' => $targetPct,
            'created_at' => time(),
            'used'       => false,
        ];

        self::saveStore($store);

        return [
            'challenge_id' => $challengeId,
            'target_pct'   => $targetPct,
        ];
    }

    /**
     * 校验滑块结果
     *
     * @param string $challengeId 挑战 ID
     * @param float  $xPct        用户最终滑块位置（百分比）
     * @param array  $trail       轨迹 [{x,y,t},...]（x 为滑块像素位置，t 为毫秒时间戳）
     * @return array ['success'=>bool, 'message'=>string, 'captcha_token'=>?string]
     */
    public static function verifyChallenge(string $challengeId, $xPct, array $trail): array
    {
        $store = self::loadStore();
        $fail = function ($msg) {
            return ['success' => false, 'message' => $msg, 'captcha_token' => null];
        };

        // 挑战是否存在
        if (!isset($store['challenges'][$challengeId])) {
            return $fail('验证已过期，请重试');
        }

        $challenge = $store['challenges'][$challengeId];

        // 是否过期
        if (time() - $challenge['created_at'] > self::TTL) {
            unset($store['challenges'][$challengeId]);
            self::saveStore($store);
            return $fail('验证已过期，请重试');
        }

        // 是否已使用（一次性）
        if (!empty($challenge['used'])) {
            return $fail('验证已使用，请重试');
        }

        // 1. 位置校验
        $xPct = (float)$xPct;
        if (abs($xPct - (float)$challenge['target_pct']) > self::POSITION_TOLERANCE) {
            // 失败也标记 used，防止暴力尝试同一挑战
            $store['challenges'][$challengeId]['used'] = true;
            self::saveStore($store);
            return $fail('滑块位置不准确，请重试');
        }

        // 2. 轨迹人类特征校验
        if (!self::isHumanLikeTrail($trail)) {
            $store['challenges'][$challengeId]['used'] = true;
            self::saveStore($store);
            return $fail('验证失败，请重试');
        }

        // 3. 通过：标记挑战已使用，签发一次性令牌
        $store['challenges'][$challengeId]['used'] = true;

        $captchaToken = bin2hex(random_bytes(24));
        $store['tokens'][$captchaToken] = [
            'created_at' => time(),
            'used'       => false,
        ];

        self::saveStore($store);

        return [
            'success'       => true,
            'message'       => '验证通过',
            'captcha_token' => $captchaToken,
        ];
    }

    /**
     * 消费一次性验证令牌（业务接口调用）
     *
     * 校验存在 + 未过期 + 未使用，通过则立即销毁。
     *
     * @param string $token 前端提交的 captcha_token
     * @return bool
     */
    public static function consumeToken(string $token): bool
    {
        $token = trim($token);
        if ($token === '' || strlen($token) !== 48) {
            return false;
        }

        $store = self::loadStore();

        if (!isset($store['tokens'][$token])) {
            return false;
        }

        $tok = $store['tokens'][$token];

        // 过期
        if (time() - $tok['created_at'] > self::TTL) {
            unset($store['tokens'][$token]);
            self::saveStore($store);
            return false;
        }

        // 已使用
        if (!empty($tok['used'])) {
            return false;
        }

        // 一次性：标记已使用并删除
        unset($store['tokens'][$token]);
        self::saveStore($store);

        return true;
    }

    /**
     * 清理过期的挑战与令牌（防文件无限增长）
     * 说明：已使用（used=true）的挑战已无价值，同样清除，避免积累
     *
     * @return void
     */
    public static function cleanup(): void
    {
        $store = self::loadStore();
        $changed = self::purge($store);
        if ($changed) {
            self::saveStore($store);
        }
    }

    /**
     * 原地清理（不读写文件）：
     *  - 过期的挑战 / 令牌（超过 TTL）
     *  - 已使用（used=true）的挑战
     *
     * @param array &$store 存储数组（引用传递，直接修改）
     * @return bool 是否发生了删除
     */
    private static function purge(array &$store): bool
    {
        $changed = false;
        $now = time();

        foreach ($store['challenges'] as $id => $ch) {
            $createdAt = $ch['created_at'] ?? 0;
            $isUsed    = !empty($ch['used']);
            if ($isUsed || ($now - $createdAt > self::TTL)) {
                unset($store['challenges'][$id]);
                $changed = true;
            }
        }

        foreach ($store['tokens'] as $tok => $t) {
            if ($now - ($t['created_at'] ?? 0) > self::TTL) {
                unset($store['tokens'][$tok]);
                $changed = true;
            }
        }

        return $changed;
    }

    // ── 内部方法 ──────────────────────────────────────────────

    /**
     * 轨迹是否呈现人类拖动特征
     *
     * 检测项：
     *  1. 点数 >= 5（防"一步到位"）
     *  2. 总时长 >= 400ms（防瞬间完成）
     *  3. 起点靠近左侧（<=25%）
     *  4. 速度有波动（人类拖动先快后慢/有微调，非匀速直线）
     *  5. 相邻点步长有上限（防程序逐点跳变）
     *
     * @param array $trail 轨迹点 [{x,y,t},...]
     * @return bool
     */
    private static function isHumanLikeTrail(array $trail): bool
    {
        $points = array_values($trail);
        $count = count($points);
        if ($count < self::TRAIL_MIN_POINTS) {
            return false;
        }

        // 提取 x 与 t
        $xs = [];
        $ts = [];
        foreach ($points as $p) {
            $xs[] = (float)($p['x'] ?? 0);
            $ts[] = (int)($p['t'] ?? 0);
        }

        // 时长检测：最后点 - 第一点 >= 400ms
        $duration = $ts[$count - 1] - $ts[0];
        if ($duration < self::TRAIL_MIN_DURATION_MS) {
            return false;
        }

        // 起点检测：起点位置（百分比，按终点归一化）不能太靠右
        $xEnd = $xs[$count - 1];
        if ($xEnd <= 0) {
            return false;
        }
        $startPct = ($xs[0] / $xEnd) * 100;
        if ($startPct > self::TRAIL_START_MAX_PCT) {
            return false;
        }

        // 相邻步长检测：任何单步位移不能超过终点的 40%（防瞬移/跳变）
        for ($i = 1; $i < $count; $i++) {
            $step = abs($xs[$i] - $xs[$i - 1]);
            if ($step > $xEnd * 0.4) {
                return false;
            }
        }

        // 速度波动检测：计算相邻点速度（px/ms），人类拖动有明显起伏
        $speeds = [];
        for ($i = 1; $i < $count; $i++) {
            $dt = $ts[$i] - $ts[$i - 1];
            if ($dt <= 0) {
                continue; // 时间戳重复/倒退的点跳过
            }
            $speeds[] = abs($xs[$i] - $xs[$i - 1]) / $dt;
        }

        if (count($speeds) < 3) {
            return false; // 有效速度点太少
        }

        // 计算标准差；人类轨迹标准差 > 0（非完全匀速）
        $mean = array_sum($speeds) / count($speeds);
        $variance = 0;
        foreach ($speeds as $s) {
            $variance += pow($s - $mean, 2);
        }
        $stdDev = sqrt($variance / count($speeds));

        // 匀速直线模拟（机器人）→ 标准差接近 0
        if ($stdDev < 0.0005) {
            return false;
        }

        return true;
    }

    /**
     * 读取存储文件
     *
     * @return array
     */
    private static function loadStore(): array
    {
        $file = __DIR__ . self::STORE_FILE;
        if (!file_exists($file)) {
            return ['challenges' => [], 'tokens' => []];
        }
        return secureReadData($file);
    }

    /**
     * 写入存储文件
     *
     * @param array $store
     * @return bool
     */
    private static function saveStore(array $store): bool
    {
        return secureWriteData(__DIR__ . self::STORE_FILE, $store);
    }
}

/**
 * 便捷函数
 */
function captcha_helper() {
    return CaptchaHelper::class;
}
?>
