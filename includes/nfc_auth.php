<?php
/**
 * NFC 设备授权登录 —— 凭证校验模块（自包含，零外部依赖） ————这是一个测试版模块，未正式启用
 *
 * 职责：
 *   - 按 device_id 查找绑定用户
 *   - 校验 NFC 设备令牌（bcrypt + 5 分钟短时效 / 一次性用即废）
 *   - 生成 / 标记设备令牌
 *   - 统一调试日志（data/nfc_debug.log）
 *
 * 被以下文件调用：
 *   - api/nfc/device_login.php
 *   - api/nfc/migrate_add_device_fields.php
 *
 * 设计约束：
 *   - 严禁修改 api/login.php / includes/auth_helper.php / data/users.php 现有字段结构
 *   - 本模块只做「设备凭证 → 用户」的映射与校验，登录/会话一律交给 AuthHelper
 *   - 不引入 Composer 依赖，仅用 PHP 内置 random_bytes / password_hash
 */

if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}

// 自包含加载底层依赖（require_once 保证不会重复加载）
require_once __DIR__ . '/../api/config.php';

// ── NFC 模块自包含配置（不污染全局 config.php）─────────────
if (!defined('NFC_TOKEN_TTL')) {
    /**
     * 令牌短时有效窗口（秒）。默认 5 分钟。
     * 超过该窗口的令牌直接判为过期（过期清）。
     */
    define('NFC_TOKEN_TTL', 3600);  // 本地测 Flow② 临时放宽到 1h；生产部署前改回 300
}

if (!defined('NFC_TOKEN_ONETIME')) {
    /**
     * true  = 用即废（一次性）：令牌成功使用一次后标记 device_token_used，
     *         再次使用直接拒绝，需重新下发（见 migrate 脚本）。安全性最高。
     * false = 5 分钟窗口内可复用（过期清）：更适合物理持有的设备反复碰一下。
     * 两种模式均满足需求，默认 false 以便本地反复调试；生产可改为 true。
     */
    define('NFC_TOKEN_ONETIME', false);
}

if (!defined('NFC_TOKEN_BYTES')) {
    /** 令牌熵长度（字节），最终为 2×字节数的十六进制串 */
    define('NFC_TOKEN_BYTES', 32);
}

// 调试日志路径：root/data/nfc_debug.log
if (!defined('NFC_DEBUG_LOG')) {
    define('NFC_DEBUG_LOG', __DIR__ . '/../data/nfc_debug.log');
}

/**
 * 写调试日志（追加，带时间戳）。
 * 文件不可写时静默跳过，绝不影响主业务流程。
 *
 * @param string $step 步骤标识
 * @param mixed  $data 附加数据
 */
function nfc_debug_log($step, $data = null)
{
    $ts   = date('Y-m-d H:i:s');
    $line = "[{$ts}] [NFC] step={$step}";
    if ($data !== null) {
        $line .= ' ' . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    $line .= PHP_EOL;
    @file_put_contents(NFC_DEBUG_LOG, $line, FILE_APPEND | LOCK_EX);
}

/**
 * 按 device_id 查找绑定用户。
 *
 * @param string $dev 设备标识，如 DEV001
 * @return array|null 命中返回 ['username'=>..., 'user'=>...]，否则 null
 */
function nfc_find_user_by_device($dev)
{
    nfc_debug_log('find_user_start', ['device_id' => $dev]);

    if ($dev === '' || $dev === null) {
        nfc_debug_log('find_user_result', ['found' => false, 'reason' => 'device_id 为空']);
        return null;
    }

    $users = secureReadData(USERS_FILE);
    foreach ($users as $username => $u) {
        if (isset($u['device_id']) && (string)$u['device_id'] === (string)$dev) {
            nfc_debug_log('find_user_result', [
                'found'           => true,
                'username'        => $username,
                'has_token_hash'  => !empty($u['device_token_hash']),
                'token_created_at'=> $u['device_token_created_at'] ?? null,
                'token_used'      => !empty($u['device_token_used']),
            ]);
            return ['username' => $username, 'user' => $u];
        }
    }

    nfc_debug_log('find_user_result', ['found' => false, 'reason' => '无匹配 device_id']);
    return null;
}

/**
 * 校验 NFC 设备令牌。
 *
 * 校验顺序：已绑定令牌 → 令牌非空 → bcrypt 匹配 → 一次性未使用 → 未过期。
 *
 * @param array  $user 用户记录（含 device_* 字段）
 * @param string $tok  明文令牌（来自 URL/POST）
 * @return array ['ok'=>bool, 'reason'=>string]
 */
function nfc_verify_token(array $user, $tok)
{
    nfc_debug_log('verify_token_start', [
        'username' => $user['username'] ?? '?',
        'tok_len'  => strlen((string)$tok),
    ]);

    if (empty($user['device_token_hash'])) {
        nfc_debug_log('verify_token_result', ['ok' => false, 'reason' => '未绑定设备令牌']);
        return ['ok' => false, 'reason' => '未绑定设备令牌'];
    }

    if (!is_string($tok) || $tok === '') {
        nfc_debug_log('verify_token_result', ['ok' => false, 'reason' => '令牌为空']);
        return ['ok' => false, 'reason' => '令牌为空'];
    }

    if (!password_verify($tok, $user['device_token_hash'])) {
        nfc_debug_log('verify_token_result', ['ok' => false, 'reason' => '令牌不匹配']);
        return ['ok' => false, 'reason' => '令牌不匹配'];
    }

    if (NFC_TOKEN_ONETIME && !empty($user['device_token_used'])) {
        nfc_debug_log('verify_token_result', ['ok' => false, 'reason' => '令牌已使用（一次性用即废）']);
        return ['ok' => false, 'reason' => '令牌已使用（用即废）'];
    }

    $created = (int)($user['device_token_created_at'] ?? 0);
    $age     = $created > 0 ? (time() - $created) : PHP_INT_MAX;
    if ($created <= 0 || $age > NFC_TOKEN_TTL) {
        nfc_debug_log('verify_token_result', [
            'ok'      => false,
            'reason'  => '令牌已过期',
            'age_sec' => $age,
            'ttl'     => NFC_TOKEN_TTL,
        ]);
        return ['ok' => false, 'reason' => '令牌已过期'];
    }

    nfc_debug_log('verify_token_result', ['ok' => true, 'age_sec' => $age]);
    return ['ok' => true, 'reason' => 'ok'];
}

/**
 * 生成设备令牌对：明文（写 NDEF 用）+ bcrypt 哈希（存 users.php 用）。
 *
 * @return array ['token'=>string, 'hash'=>string]
 */
function nfc_generate_token_pair()
{
    $plain = bin2hex(random_bytes(NFC_TOKEN_BYTES));
    $hash  = password_hash($plain, PASSWORD_BCRYPT);
    return ['token' => $plain, 'hash' => $hash];
}

/**
 * 一次性模式下，登录成功后标记令牌已使用（用即废）。
 *
 * @param string $username
 * @return bool
 */
function nfc_mark_token_used($username)
{
    $users = secureReadData(USERS_FILE);
    if (!isset($users[$username])) {
        nfc_debug_log('mark_used', ['username' => $username, 'ok' => false, 'reason' => '用户不存在']);
        return false;
    }
    $users[$username]['device_token_used'] = true;
    $ok = secureWriteData(USERS_FILE, $users);
    nfc_debug_log('mark_used', ['username' => $username, 'ok' => (bool)$ok]);
    return (bool)$ok;
}
