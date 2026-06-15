<?php
/**
 * AuthHelper — 统一认证与授权管理类
 *
 * 职责：
 *  - 统一 token 提取（请求头 / GET / POST 三种来源）
 *  - 验证会话有效性（含过期自动清理）
 *  - 提供简便的 requireLogin / requireAdmin 终止式接口
 *  - 提供旧版兼容方法（validateToken / getUsernameFromToken / getCurrentUser）
 *
 * 安全性：
 *  - 会话过期自动清理，防止 token 囤积
 *  - 单次请求内缓存会话文件，避免重复 I/O
 *  - 统一错误响应格式，避免信息泄露
 *
 * 性能：
 *  - 静态缓存：同一请求多次认证调用只读一次文件
 *  - 惰性加载：只在实际校验时才读取会话文件
 */

if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}

require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/helper.php';
require_once __DIR__ . '/../api/secure_data.php';

class AuthHelper
{
    // ── 配置常量 ──────────────────────────────────────────────
    
    /** 会话过期时间（秒），默认 24 小时 */
    const SESSION_TTL = 86400;

    /** 同一用户最大允许会话数 */
    const MAX_SESSIONS = 10;

    /** 登录锁定阈值（连续失败次数） */
    const MAX_LOGIN_ATTEMPTS = 5;

    /** 登录锁定时间（分钟） */
    const LOGIN_LOCK_MINUTES = 15;

    // ── 请求级缓存 ────────────────────────────────────────────

    /** @var array|null 当前请求已提取的 token */
    private static $cachedToken = null;

    /** @var bool 是否已经尝试提取过 token */
    private static $tokenExtracted = false;

    /** @var array|null 当前请求已加载的会话数据（含清理后） */
    private static $cachedSessions = null;

    /** @var array|null 当前请求已验证的会话记录 */
    private static $cachedSession = null;

    /** @var bool 是否已验证过会话 */
    private static $sessionValidated = false;

    // ═══════════════════════════════════════════════════════════
    //  公开接口
    // ═══════════════════════════════════════════════════════════

    // ── 认证（terminate） ──────────────────────────────────────

    /**
     * 要求登录：未登录则终止并返回 401
     *
     * @return array 当前会话数据（含 username、role、user_id 等）
     */
    public static function requireLogin(): array
    {
        $session = self::getSession();
        if ($session === null) {
            self::jsonAuthError('请先登录', 401);
        }
        return $session;
    }

    /**
     * 要求管理员权限：非 admin 则终止并返回 403
     *
     * @return array 当前会话数据
     */
    public static function requireAdmin(): array
    {
        $session = self::requireLogin();
        if (($session['role'] ?? 'user') !== 'admin') {
            self::jsonAuthError('权限不足，需要管理员权限', 403);
        }
        return $session;
    }

    // ── 认证（return） ────────────────────────────────────────

    /**
     * 获取当前会话数据（验证 token + 过期检查）
     *
     * @return array|null 会话数据（含 username、role），未登录返回 null
     */
    public static function getSession(): ?array
    {
        if (self::$sessionValidated) {
            return self::$cachedSession;
        }

        $token = self::extractToken();
        if ($token === null) {
            self::$sessionValidated = true;
            self::$cachedSession = null;
            return null;
        }

        $sessions = self::loadSessions();

        // token 不存在
        if (!isset($sessions[$token])) {
            self::$sessionValidated = true;
            self::$cachedSession = null;
            return null;
        }

        $session = $sessions[$token];

        // 检查会话过期
        if (self::isSessionExpired($session)) {
            // 删除过期会话
            unset($sessions[$token]);
            self::saveSessions($sessions);
            // 刷新缓存
            self::$cachedSessions = $sessions;
            self::$sessionValidated = true;
            self::$cachedSession = null;
            return null;
        }

        self::$sessionValidated = true;
        self::$cachedSession = $session;
        return $session;
    }

    /**
     * 检查当前请求是否已登录
     *
     * @return bool
     */
    public static function isLoggedIn(): bool
    {
        return self::getSession() !== null;
    }

    /**
     * 检查当前用户是否为管理员
     *
     * @return bool
     */
    public static function isAdmin(): bool
    {
        $session = self::getSession();
        return $session !== null && ($session['role'] ?? 'user') === 'admin';
    }

    /**
     * 获取当前用户的角色字符串
     *
     * @return string 'admin'、'user' 或 'guest'（未登录）
     */
    public static function getRole(): string
    {
        $session = self::getSession();
        if ($session === null) {
            return 'guest';
        }
        return $session['role'] ?? 'user';
    }

    /**
     * 获取当前登录用户的完整信息（从 users.php 读取）
     *
     * @return array|null 未登录或用户不存在返回 null
     */
    public static function getCurrentUser(): ?array
    {
        $session = self::getSession();
        if ($session === null) {
            return null;
        }

        $username = $session['username'] ?? '';
        if (empty($username)) {
            return null;
        }

        $users = secureReadData(USERS_FILE);
        return $users[$username] ?? null;
    }

    // ═══════════════════════════════════════════════════════════
    //  旧版兼容方法（保持签名不变，内部调用新方法）
    // ═══════════════════════════════════════════════════════════

    /**
     * 旧版：验证 token 是否有效
     *
     * @return bool
     */
    public static function validateToken(): bool
    {
        return self::getSession() !== null;
    }

    /**
     * 旧版：从 token 获取用户名
     *
     * @return string|null
     */
    public static function getUsernameFromToken(): ?string
    {
        $session = self::getSession();
        return $session['username'] ?? null;
    }

    // ═══════════════════════════════════════════════════════════
    //  工具方法
    // ═══════════════════════════════════════════════════════════

    /**
     * 生成一个新的会话 token（UUID v4）
     *
     * @return string
     */
    public static function generateToken(): string
    {
        $data = random_bytes(32);

        // 设置版本位（UUID v4）
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        // 设置变体位
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * 创建新会话并写入会话文件
     *
     * @param array $user 用户数据（必须含 id、username、role）
     * @return string 生成的 token
     */
    public static function createSession(array $user): string
    {
        $sessions = secureReadData(SESSIONS_FILE);
        $token = self::generateToken();

        $sessions[$token] = [
            'user_id'    => $user['id'] ?? '',
            'username'   => $user['username'] ?? '',
            'role'       => $user['role'] ?? 'user',
            'created_at' => date('Y-m-d H:i:s'),
        ];

        // 限制会话数量（FIFO 淘汰）
        $userTokens = [];
        foreach ($sessions as $tok => $sess) {
            if (($sess['username'] ?? '') === ($user['username'] ?? '')) {
                $userTokens[$tok] = $sess['created_at'] ?? '';
            }
        }
        while (count($userTokens) >= self::MAX_SESSIONS) {
            asort($userTokens);
            $oldestToken = key($userTokens);
            unset($sessions[$oldestToken], $userTokens[$oldestToken]);
        }

        secureWriteData(SESSIONS_FILE, $sessions);

        // 清除请求级缓存，确保后续读取能拿到新会话
        self::clearCache();

        return $token;
    }

    /**
     * 删除指定会话（登出）
     *
     * @param string|null $token 要删除的 token，null 则使用当前请求的 token
     * @return bool
     */
    public static function destroySession(?string $token = null): bool
    {
        if ($token === null) {
            $token = self::extractToken();
        }
        if ($token === null) {
            return false;
        }

        $sessions = secureReadData(SESSIONS_FILE);
        if (!isset($sessions[$token])) {
            return false;
        }

        unset($sessions[$token]);
        secureWriteData(SESSIONS_FILE, $sessions);

        // 清除缓存
        self::clearCache();

        return true;
    }

    /**
     * 检查用户是否被锁定（登录失败次数过多）
     *
     * @param array $user 用户数据
     * @return array ['locked' => bool, 'remaining_minutes' => int]
     */
    public static function isUserLocked(array $user): array
    {
        $lockUntil = $user['lock_until'] ?? null;
        if ($lockUntil === null) {
            return ['locked' => false, 'remaining_minutes' => 0];
        }

        $lockTime = strtotime($lockUntil);
        if ($lockTime === false) {
            return ['locked' => false, 'remaining_minutes' => 0];
        }

        $remaining = $lockTime - time();
        if ($remaining <= 0) {
            return ['locked' => false, 'remaining_minutes' => 0];
        }

        return [
            'locked'            => true,
            'remaining_minutes' => (int)ceil($remaining / 60),
        ];
    }

    /**
     * 记录登录失败（自增 login_attempts，超过阈值则锁定）
     *
     * @param string $username 用户名
     * @return array 更新后的用户数据
     */
    public static function recordLoginFailure(string $username): array
    {
        $users = secureReadData(USERS_FILE);
        if (!isset($users[$username])) {
            return [];
        }

        $users[$username]['login_attempts'] = ($users[$username]['login_attempts'] ?? 0) + 1;

        if ($users[$username]['login_attempts'] >= self::MAX_LOGIN_ATTEMPTS) {
            $users[$username]['lock_until'] = date(
                'Y-m-d H:i:s',
                time() + self::LOGIN_LOCK_MINUTES * 60
            );
        }

        secureWriteData(USERS_FILE, $users);
        return $users[$username];
    }

    /**
     * 重置登录失败次数
     *
     * @param string $username 用户名
     */
    public static function resetLoginAttempts(string $username): void
    {
        $users = secureReadData(USERS_FILE);
        if (isset($users[$username])) {
            $users[$username]['login_attempts'] = 0;
            $users[$username]['lock_until'] = null;
            secureWriteData(USERS_FILE, $users);
        }
    }

    // ═══════════════════════════════════════════════════════════
    //  内部方法
    // ═══════════════════════════════════════════════════════════

    /**
     * 从请求中提取 token（统一三种来源）
     *
     * 优先级：Authorization 请求头 > GET 参数 > POST body
     *
     * @return string|null
     */
    private static function extractToken(): ?string
    {
        if (self::$tokenExtracted) {
            return self::$cachedToken;
        }

        $token = null;

        // 1. Authorization 请求头
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            if (preg_match('/^Bearer\s+(.+)$/i', $headers['Authorization'], $matches)) {
                $token = trim($matches[1]);
            }
        }

        // 2. GET 参数
        if ($token === null && isset($_GET['token'])) {
            $token = trim($_GET['token']);
        }

        // 3. POST body
        if ($token === null) {
            $input = json_decode(file_get_contents('php://input'), true);
            if (is_array($input) && isset($input['token'])) {
                $token = trim($input['token']);
            }
        }

        self::$cachedToken = ($token !== '' && $token !== null) ? $token : null;
        self::$tokenExtracted = true;

        return self::$cachedToken;
    }

    /**
     * 加载会话文件（带请求级缓存）
     *
     * @return array
     */
    private static function loadSessions(): array
    {
        if (self::$cachedSessions !== null) {
            return self::$cachedSessions;
        }

        $sessions = secureReadData(SESSIONS_FILE);

        // 自动清理过期会话
        $changed = false;
        foreach ($sessions as $token => $session) {
            if (self::isSessionExpired($session)) {
                unset($sessions[$token]);
                $changed = true;
            }
        }
        if ($changed) {
            secureWriteData(SESSIONS_FILE, $sessions);
        }

        self::$cachedSessions = $sessions;
        return $sessions;
    }

    /**
     * 判断会话是否已过期
     *
     * @param array $session 会话数据
     * @return bool
     */
    private static function isSessionExpired(array $session): bool
    {
        // 优先检查 expires_at 字段
        if (isset($session['expires_at']) && is_numeric($session['expires_at'])) {
            return $session['expires_at'] < time();
        }

        // 回退检查 created_at 字段（默认 SESSION_TTL 过期）
        if (isset($session['created_at'])) {
            $createdTime = strtotime($session['created_at']);
            if ($createdTime !== false) {
                return ($createdTime + self::SESSION_TTL) < time();
            }
        }

        // 无时间信息 → 视为过期（安全优先）
        return true;
    }

    /**
     * 将会话数据写回文件
     *
     * @param array $sessions
     */
    private static function saveSessions(array $sessions): void
    {
        secureWriteData(SESSIONS_FILE, $sessions);
    }

    /**
     * 以标准 JSON 格式输出认证错误并终止
     *
     * @param string $message
     * @param int    $httpCode
     */
    private static function jsonAuthError(string $message, int $httpCode): void
    {
        json_response(false, $message, null, $httpCode);
        exit; // json_response 内部已有 exit，双重保险
    }

    /**
     * 清除请求级缓存（在会话变更后调用）
     */
    private static function clearCache(): void
    {
        self::$cachedToken = null;
        self::$tokenExtracted = false;
        self::$cachedSessions = null;
        self::$cachedSession = null;
        self::$sessionValidated = false;
    }
}
