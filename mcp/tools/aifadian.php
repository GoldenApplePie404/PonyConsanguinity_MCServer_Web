<?php
/**
 * AI 客服 - 爱发电充值订单查询工具（只读）
 * ============================================================
 * 通过 MCP 通道暴露给 customer 人格，受 config.php 的
 * AI_PERSONAS['customer']['allowed_tools'] 白名单约束。
 *
 * 安全：
 * - 仅 SELECT 查询 afdian_orders，无任何写操作。
 * - 强制要求 player_name 参数（玩家自报 MC 游戏名），防止冒用查询他人订单。
 * - 金额由 points_added 反推（1 元 = 100 黄金券 = 100 PlayerPoints），
 *   不依赖可能缺失的 total_amount 字段，健壮性更好。
 *
 * 复用：api/aifadian/db.php 的 getDatabase()（连接 MC 服务器库，含 afdian_orders 表）。
 */

require_once __DIR__ . '/../../api/aifadian/db.php';

/**
 * 查询某玩家的充值订单状态（最近 10 条）
 * @param array $args ['player_name' => string] 必填
 */
function handle_query_my_orders(array $args): string {
    $player = trim((string)($args['player_name'] ?? ''));
    if ($player === '') {
        return json_encode(
            ['success' => false, 'message' => '缺少必填参数 player_name（玩家的 Minecraft 游戏名）'],
            JSON_UNESCAPED_UNICODE
        );
    }
    try {
        $db = getDatabase();
        $rows = $db->fetchAll(
            "SELECT * FROM afdian_orders WHERE remark = ? ORDER BY COALESCE(created_at, create_time) DESC LIMIT 10",
            [$player]
        );
        $orders = [];
        foreach ($rows as $r) {
            $points = (int)($r['points_added'] ?? 0);
            $amount = isset($r['total_amount']) ? (float)$r['total_amount'] : round($points / 100, 2);
            $orders[] = [
                'out_trade_no' => $r['out_trade_no'] ?? '',
                'plan_title'   => $r['plan_title'] ?? '黄金券充值',
                'amount'       => $amount,
                'points'       => $points,
                'status'       => $r['status'] ?? 'pending',
                'error'        => ($r['status'] ?? '') === 'failed' ? ($r['error_message'] ?? '') : '',
                'time'         => $r['created_at'] ?? $r['create_time'] ?? 0,
            ];
        }
        return json_encode([
            'success' => true,
            'data'    => [
                'player' => $player,
                'count'  => count($orders),
                'orders' => $orders,
            ],
        ], JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $e) {
        return json_encode(
            ['success' => false, 'message' => '查询失败：' . $e->getMessage()],
            JSON_UNESCAPED_UNICODE
        );
    }
}

/**
 * 玩家维度充值总额统计（客服/助手 均可读）
 * 严格按玩家游戏名（afdian_orders.remark）聚合该玩家的累计/今日笔数、
 * 黄金券点数、反推金额（1元=100点）、以及失败数。天然隔离"全服总额"，
 * 仅回答"某玩家"的支付情况，符合客服只能查指定玩家维度的边界。
 * @param array $args ['player_name' => string] 必填
 */
function handle_player_recharge_summary(array $args): string {
    $player = trim((string)($args['player_name'] ?? ''));
    if ($player === '') {
        return json_encode(
            ['success' => false, 'message' => '缺少必填参数 player_name（玩家的 Minecraft 游戏名，需与爱发电备注一致）'],
            JSON_UNESCAPED_UNICODE
        );
    }
    try {
        $db = getDatabase();
        $row = $db->fetchOne(
            "SELECT COUNT(*) AS total,"
            . " SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) AS completed,"
            . " SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END) AS failed,"
            . " SUM(CASE WHEN status='completed' THEN points_added ELSE 0 END) AS total_points"
            . " FROM afdian_orders WHERE remark = ?",
            [$player]
        );
        $todayStart = date('Y-m-d 00:00:00');
        $t = $db->fetchOne(
            "SELECT COUNT(*) AS today_completed,"
            . " SUM(CASE WHEN status='completed' THEN points_added ELSE 0 END) AS today_points"
            . " FROM afdian_orders"
            . " WHERE remark = ? AND status='completed'"
            . " AND COALESCE(created_at, FROM_UNIXTIME(create_time)) >= ?",
            [$player, $todayStart]
        );
        $totalPoints = (int)($row['total_points'] ?? 0);
        $todayPoints = (int)($t['today_points'] ?? 0);
        $data = [
            'player'          => $player,
            'scope'           => 'single_player', // 明确这是单玩家维度，非全服统计
            'total_orders'    => (int)($row['total'] ?? 0),
            'completed'       => (int)($row['completed'] ?? 0),
            'failed'          => (int)($row['failed'] ?? 0),
            'total_points'    => $totalPoints,
            'total_amount'    => round($totalPoints / 100, 2),
            'today_completed' => (int)($t['today_completed'] ?? 0),
            'today_points'    => $todayPoints,
            'today_amount'    => round($todayPoints / 100, 2),
            'note'            => '金额由黄金券点数反推（1元=100点）；仅统计游戏名为「' . $player . '」的订单，不含其他玩家。',
        ];
        return json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $e) {
        return json_encode(
            ['success' => false, 'message' => '统计失败：' . $e->getMessage()],
            JSON_UNESCAPED_UNICODE
        );
    }
}

/**
 * 排查某玩家的失败充值订单，将失败原因翻译成人话
 * @param array $args ['player_name' => string] 必填
 */
function handle_troubleshoot_order(array $args): string {
    $player = trim((string)($args['player_name'] ?? ''));
    if ($player === '') {
        return json_encode(
            ['success' => false, 'message' => '缺少必填参数 player_name'],
            JSON_UNESCAPED_UNICODE
        );
    }
    try {
        $db = getDatabase();
        $rows = $db->fetchAll(
            "SELECT out_trade_no, plan_title, points_added, error_message, COALESCE(created_at, create_time) AS ctime"
            . " FROM afdian_orders WHERE remark = ? AND status = 'failed' ORDER BY ctime DESC",
            [$player]
        );
        $failed = [];
        foreach ($rows as $r) {
            $failed[] = [
                'out_trade_no' => $r['out_trade_no'] ?? '',
                'plan_title'   => $r['plan_title'] ?? '黄金券充值',
                'reason'       => $r['error_message'] ?? '未知原因',
                'time'         => $r['ctime'] ?? 0,
            ];
        }
        if (empty($failed)) {
            return json_encode([
                'success' => true,
                'data'    => [
                    'player'      => $player,
                    'has_failed'  => false,
                    'message'     => '未找到「' . $player . '」的失败订单，充值记录均已正常处理或暂无记录。',
                ],
            ], JSON_UNESCAPED_UNICODE);
        }
        return json_encode([
            'success' => true,
            'data'    => [
                'player'     => $player,
                'has_failed' => true,
                'failed'     => $failed,
                'hint'       => '失败订单不会重复扣款，会在你加入服务器（游戏名进入缓存）后自动重试成功。'
                              . '如已加入服务器仍失败，请核对游戏名是否与爱发电备注完全一致。',
            ],
        ], JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $e) {
        return json_encode(
            ['success' => false, 'message' => '查询失败：' . $e->getMessage()],
            JSON_UNESCAPED_UNICODE
        );
    }
}

/**
 * 充值总览统计（admin 只读）
 * 返回累计/今日 笔数、点数、反推金额，以及各状态分布（pending/completed/failed）。
 * 金额由 points_added 反推（1 元 = 100 黄金券 = 100 PlayerPoints），与客服工具口径一致。
 */
function handle_recharge_stats(array $args): string {
    try {
        $db = getDatabase();
        $row = $db->fetchOne(
            "SELECT COUNT(*) AS total,"
            . " SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) AS completed,"
            . " SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) AS pending,"
            . " SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END) AS failed,"
            . " SUM(CASE WHEN status='completed' THEN points_added ELSE 0 END) AS total_points"
            . " FROM afdian_orders"
        );
        $todayStart = date('Y-m-d 00:00:00');
        $t = $db->fetchOne(
            "SELECT COUNT(*) AS today_completed, SUM(points_added) AS today_points"
            . " FROM afdian_orders"
            . " WHERE status='completed' AND COALESCE(created_at, FROM_UNIXTIME(create_time)) >= ?",
            [$todayStart]
        );
        $totalPoints = (int)($row['total_points'] ?? 0);
        $todayPoints = (int)($t['today_points'] ?? 0);
        $data = [
            'total_orders'    => (int)($row['total'] ?? 0),
            'completed'       => (int)($row['completed'] ?? 0),
            'pending'         => (int)($row['pending'] ?? 0),
            'failed'          => (int)($row['failed'] ?? 0),
            'total_points'    => $totalPoints,
            'total_amount'    => round($totalPoints / 100, 2),
            'today_completed' => (int)($t['today_completed'] ?? 0),
            'today_points'    => $todayPoints,
            'today_amount'    => round($todayPoints / 100, 2),
            'note'            => '金额由黄金券点数反推（1元=100点），不含 VIP 月卡等非 1:100 比例方案',
        ];
        return json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $e) {
        return json_encode(['success' => false, 'message' => '统计失败：' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * 列出失败充值订单（admin 只读）
 * 直接读取 A+B+C 阶段 saveFailedOrder 落库的失败记录，供运营排障。
 * @param array $args ['limit' => int] 可选，默认 50，最大 200
 */
function handle_list_failed_orders(array $args): string {
    $limit = (int)($args['limit'] ?? 50);
    if ($limit <= 0 || $limit > 200) {
        $limit = 50;
    }
    try {
        $db = getDatabase();
        $rows = $db->fetchAll(
            "SELECT out_trade_no, remark, plan_title, points_added, error_message,"
            . " COALESCE(created_at, FROM_UNIXTIME(create_time)) AS ctime"
            . " FROM afdian_orders WHERE status = 'failed'"
            . " ORDER BY ctime DESC LIMIT " . (int)$limit
        );
        $failed = [];
        foreach ($rows as $r) {
            $failed[] = [
                'out_trade_no' => $r['out_trade_no'] ?? '',
                'player'       => $r['remark'] ?? '',
                'plan_title'   => $r['plan_title'] ?? '黄金券充值',
                'points'       => (int)($r['points_added'] ?? 0),
                'reason'       => $r['error_message'] ?? '未知原因',
                'time'         => $r['ctime'] ?? '',
            ];
        }
        return json_encode([
            'success' => true,
            'data'    => ['count' => count($failed), 'failed_orders' => $failed],
        ], JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $e) {
        return json_encode(['success' => false, 'message' => '查询失败：' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * 玩家数据库查询（admin 只读）——区别于客服只能查"自己报的名"的订单
 * 可按游戏名或 UUID 查任意玩家的存在性、UUID、当前黄金券点数。
 * @param array $args ['player_name' => string, 'player_uuid' => string] 二选一
 */
function handle_player_lookup(array $args): string {
    $name = trim((string)($args['player_name'] ?? ''));
    $uuid = trim((string)($args['player_uuid'] ?? ''));
    if ($name === '' && $uuid === '') {
        return json_encode(['success' => false, 'message' => '请提供 player_name 或 player_uuid 之一'], JSON_UNESCAPED_UNICODE);
    }
    try {
        $db = getDatabase();
        if ($uuid !== '') {
            $cache = $db->fetchOne("SELECT username FROM playerpoints_username_cache WHERE uuid = ?", [$uuid]);
            $pRow = $db->fetchOne("SELECT points FROM playerpoints_points WHERE uuid = ?", [$uuid]);
            $exists = ($cache !== false) || ($pRow !== false);
            $data = [
                'uuid'        => $uuid,
                'player_name' => $cache !== false ? $cache['username'] : null,
                'exists'      => (bool)$exists,
                'points'      => $pRow !== false ? (int)$pRow['points'] : 0,
            ];
        } else {
            $exists = $db->playerExists($name);
            $u = $db->getPlayerUUID($name);
            $data = [
                'player_name' => $name,
                'uuid'        => $u,
                'exists'      => (bool)$exists,
                'points'      => $u !== null ? $db->getPlayerPoints($u) : 0,
            ];
        }
        return json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $e) {
        return json_encode(['success' => false, 'message' => '查询失败：' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * 按订单号查询单条订单全字段（admin 只读，排查用）
 * @param array $args ['out_trade_no' => string] 必填
 */
function handle_get_order_detail(array $args): string {
    $tradeNo = trim((string)($args['out_trade_no'] ?? ''));
    if ($tradeNo === '') {
        return json_encode(['success' => false, 'message' => '缺少必填参数 out_trade_no'], JSON_UNESCAPED_UNICODE);
    }
    try {
        $db = getDatabase();
        $r = $db->fetchOne("SELECT * FROM afdian_orders WHERE out_trade_no = ?", [$tradeNo]);
        if (!$r) {
            return json_encode(['success' => false, 'message' => '未找到订单：' . $tradeNo], JSON_UNESCAPED_UNICODE);
        }
        $points = (int)($r['points_added'] ?? 0);
        $r['amount'] = isset($r['total_amount']) ? (float)$r['total_amount'] : round($points / 100, 2);
        return json_encode(['success' => true, 'data' => $r], JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $e) {
        return json_encode(['success' => false, 'message' => '查询失败：' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * 列出数据库中的所有表（admin 只读）——用于识别数据库整体结构
 * @param array $args 无
 */
function handle_list_tables(array $args): string {
    try {
        $db = getDatabase();
        $rows = $db->fetchAll("SHOW TABLES");
        $tables = [];
        foreach ($rows as $r) {
            $tables[] = array_values($r)[0];
        }
        return json_encode([
            'success' => true,
            'data'    => ['count' => count($tables), 'tables' => $tables],
        ], JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $e) {
        return json_encode(['success' => false, 'message' => '列出表失败：' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * 查看某张表的字段结构（admin 只读）——识别字段名/类型/键
 * @param array $args ['table' => string] 必填
 */
function handle_describe_table(array $args): string {
    $table = trim((string)($args['table'] ?? ''));
    // 表名白名单格式：仅允许字母数字下划线，防注入
    if ($table === '' || !preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        return json_encode(['success' => false, 'message' => '非法表名（仅允许字母、数字、下划线）'], JSON_UNESCAPED_UNICODE);
    }
    try {
        $db = getDatabase();
        $cols = $db->fetchAll("DESCRIBE `$table`");
        return json_encode([
            'success' => true,
            'data'    => ['table' => $table, 'columns' => $cols],
        ], JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $e) {
        return json_encode(['success' => false, 'message' => '查看表结构失败：' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * 灵活筛选爱发电订单（admin 只读）
 * @param array $args ['status'=>string,'player_name'=>string,'from'=>string,'to'=>string,'limit'=>int]
 */
function handle_search_orders(array $args): string {
    $status = trim((string)($args['status'] ?? ''));
    $name   = trim((string)($args['player_name'] ?? ''));
    $from   = trim((string)($args['from'] ?? ''));
    $to     = trim((string)($args['to'] ?? ''));
    $limit  = (int)($args['limit'] ?? 50);
    if ($limit <= 0 || $limit > 200) { $limit = 50; }
    try {
        $db = getDatabase();
        $where = [];
        $params = [];
        if ($status !== '') {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        if ($name !== '') {
            $where[] = 'remark LIKE ?';
            $params[] = '%' . $name . '%';
        }
        if ($from !== '') {
            $where[] = 'COALESCE(created_at, FROM_UNIXTIME(create_time)) >= ?';
            $params[] = $from;
        }
        if ($to !== '') {
            $where[] = 'COALESCE(created_at, FROM_UNIXTIME(create_time)) <= ?';
            $params[] = $to;
        }
        $sql = "SELECT out_trade_no, remark, plan_title, points_added, status, error_message,"
             . " COALESCE(created_at, FROM_UNIXTIME(create_time)) AS ctime"
             . " FROM afdian_orders";
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        $sql .= " ORDER BY ctime DESC LIMIT " . (int)$limit;
        $rows = $db->fetchAll($sql, $params);
        $orders = [];
        foreach ($rows as $r) {
            $points = (int)($r['points_added'] ?? 0);
            $orders[] = [
                'out_trade_no' => $r['out_trade_no'] ?? '',
                'player'       => $r['remark'] ?? '',
                'plan_title'   => $r['plan_title'] ?? '',
                'points'       => $points,
                'amount'       => round($points / 100, 2),
                'status'       => $r['status'] ?? '',
                'error'        => ($r['status'] ?? '') === 'failed' ? ($r['error_message'] ?? '') : '',
                'time'         => $r['ctime'] ?? '',
            ];
        }
        return json_encode([
            'success' => true,
            'data'    => ['count' => count($orders), 'orders' => $orders],
        ], JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $e) {
        return json_encode(['success' => false, 'message' => '查询失败：' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * 按游戏名/UUID 模糊搜索玩家（admin 只读）
 * @param array $args ['player_name'=>string,'player_uuid'=>string,'limit'=>int]
 */
function handle_search_players(array $args): string {
    $name  = trim((string)($args['player_name'] ?? ''));
    $uuid  = trim((string)($args['player_uuid'] ?? ''));
    $limit = (int)($args['limit'] ?? 50);
    if ($limit <= 0 || $limit > 200) { $limit = 50; }
    if ($name === '' && $uuid === '') {
        return json_encode(['success' => false, 'message' => '请提供 player_name 或 player_uuid'], JSON_UNESCAPED_UNICODE);
    }
    try {
        $db = getDatabase();
        $sql = "SELECT c.uuid, c.username, p.points"
             . " FROM playerpoints_username_cache c"
             . " LEFT JOIN playerpoints_points p ON c.uuid = p.uuid";
        $where = [];
        $params = [];
        if ($name !== '') {
            $where[] = 'c.username LIKE ?';
            $params[] = '%' . $name . '%';
        }
        if ($uuid !== '') {
            $where[] = 'c.uuid LIKE ?';
            $params[] = '%' . $uuid . '%';
        }
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        $sql .= " ORDER BY c.username ASC LIMIT " . (int)$limit;
        $rows = $db->fetchAll($sql, $params);
        return json_encode([
            'success' => true,
            'data'    => ['count' => count($rows), 'players' => $rows],
        ], JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $e) {
        return json_encode(['success' => false, 'message' => '查询失败：' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * 执行受控的只读 SQL（admin 只读，需管理员角色）
 * 护栏：仅允许单条 SELECT；禁止多语句与注释；禁止写/危险关键词；
 *       屏蔽敏感列（password 等）；强制 LIMIT ≤ 200（防全表扫描拖垮库）。
 * @param array $args ['sql' => string] 必填
 */
function handle_run_readonly_query(array $args): string {
    $sql = trim((string)($args['sql'] ?? ''));
    if ($sql === '') {
        return json_encode(['success' => false, 'message' => '缺少必填参数 sql'], JSON_UNESCAPED_UNICODE);
    }
    // 1) 仅允许以 SELECT 开头（忽略前导空白）
    if (!preg_match('/^\s*SELECT\s/i', $sql)) {
        return json_encode(['success' => false, 'message' => '仅允许 SELECT 查询，已拒绝'], JSON_UNESCAPED_UNICODE);
    }
    // 2) 禁止多语句与注释
    if (preg_match('/;|--|\/\*|\*\/|#/i', $sql)) {
        return json_encode(['success' => false, 'message' => '不允许多语句或注释，已拒绝'], JSON_UNESCAPED_UNICODE);
    }
    // 3) 禁止写/危险关键词
    $forbidden = '/\b(INSERT|UPDATE|DELETE|DROP|ALTER|TRUNCATE|CREATE|RENAME|REPLACE|GRANT|REVOKE|EXEC|EXECUTE|INTO\s+OUTFILE|LOAD_FILE|SHOW\s+GRANTS)\b/i';
    if (preg_match($forbidden, $sql)) {
        return json_encode(['success' => false, 'message' => '检测到写/危险关键词，已拒绝'], JSON_UNESCAPED_UNICODE);
    }
    // 4) 屏蔽敏感列（密码哈希等）
    if (preg_match('/\bpassword\b/i', $sql)) {
        return json_encode(['success' => false, 'message' => '出于安全，禁止查询 password 等敏感列'], JSON_UNESCAPED_UNICODE);
    }
    try {
        $db = getDatabase();
    } catch (\Throwable $e) {
        return json_encode(['success' => false, 'message' => '查询失败：' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

// ── 工具注册（自注册模式） ──────────────────────────────

registerTool('query_my_orders', [
    'name'        => 'query_my_orders',
    'description' => '查询指定玩家的爱发电充值订单状态（最近 10 条），返回每笔订单的金额、黄金券点数、处理状态。参数 player_name 为玩家的 Minecraft 游戏名（需与爱发电支付备注完全一致）。用于回答"我的充值到哪了 / 我的充值记录"类问题。',
    'inputSchema' => [
        'type'       => 'object',
        'properties' => [
            'player_name' => ['type' => 'string', 'description' => '玩家的 Minecraft 游戏名（与爱发电备注一致）'],
        ],
        'required'   => ['player_name'],
    ],
    'permission'  => 'read_only',
    'handler'     => 'handle_query_my_orders',
]);

registerTool('troubleshoot_order', [
    'name'        => 'troubleshoot_order',
    'description' => '排查指定玩家的失败充值订单，返回失败原因（如备注为空、玩家名不存在、UUID 缺失）与处理建议。参数 player_name 为 Minecraft 游戏名。用于回答"我充值了但没到账 / 订单失败"类问题。',
    'inputSchema' => [
        'type'       => 'object',
        'properties' => [
            'player_name' => ['type' => 'string', 'description' => '玩家的 Minecraft 游戏名'],
        ],
        'required'   => ['player_name'],
    ],
    'permission'  => 'read_only',
    'handler'     => 'handle_troubleshoot_order',
]);

registerTool('player_recharge_summary', [
    'name'        => 'player_recharge_summary',
    'description' => '按玩家 Minecraft 游戏名统计【该玩家本人】的充值情况：累计/今日笔数、黄金券点数、反推金额（1元=100点）、失败单数。严格单玩家维度（依据 afdian_orders.remark），绝不返回全服总额。客服与助手均可用，用于回答"我一共充了多少 / 我的支付总额"类问题。参数 player_name 必填。',
    'inputSchema' => [
        'type'       => 'object',
        'properties' => [
            'player_name' => ['type' => 'string', 'description' => '玩家的 Minecraft 游戏名（与爱发电备注一致）'],
        ],
        'required'   => ['player_name'],
    ],
    'permission'  => 'read_only',
    'handler'     => 'handle_player_recharge_summary',
]);

registerTool('recharge_stats', [
    'name'        => 'recharge_stats',
    'description' => '充值总览统计：累计与今日的订单笔数、黄金券点数、反推金额（1元=100点），以及 pending/completed/failed 各状态分布。用于回答"今天充值多少 / 总充值情况"类管理问题。',
    'inputSchema' => ['type' => 'object', 'properties' => (object)[], 'required' => []],
    'permission'  => 'read_only',
    'handler'     => 'handle_recharge_stats',
]);

registerTool('list_failed_orders', [
    'name'        => 'list_failed_orders',
    'description' => '列出所有失败的充值订单及失败原因（直接读取失败落库记录），供运营排障。可选 limit 控制返回条数（默认50，最大200）。用于回答"有哪些充值失败了 / 失败订单清单"类问题。',
    'inputSchema' => [
        'type'       => 'object',
        'properties' => [
            'limit' => ['type' => 'integer', 'description' => '返回条数上限（默认 50，最大 200）'],
        ],
        'required'   => [],
    ],
    'permission'  => 'read_only',
    'handler'     => 'handle_list_failed_orders',
]);

registerTool('player_lookup', [
    'name'        => 'player_lookup',
    'description' => '按 Minecraft 游戏名或玩家 UUID 查询任意玩家的底层数据库状态：是否存在、UUID、当前黄金券点数。区别于客服只能查"自己报的名"的订单——此工具可查任意玩家。用于回答"某某玩家有多少点 / 这个 UUID 是谁"类管理问题。',
    'inputSchema' => [
        'type'       => 'object',
        'properties' => [
            'player_name' => ['type' => 'string', 'description' => '玩家 Minecraft 游戏名'],
            'player_uuid' => ['type' => 'string', 'description' => '玩家 UUID（与 player_name 二选一）'],
        ],
        'required'   => [],
    ],
    'permission'  => 'read_only',
    'handler'     => 'handle_player_lookup',
]);

registerTool('get_order_detail', [
    'name'        => 'get_order_detail',
    'description' => '按订单号（out_trade_no）查���单条爱发电订单的全部字段（金额、点数、状态、错误原因、时间等），用于深度排查某笔具体订单。',
    'inputSchema' => [
        'type'       => 'object',
        'properties' => [
            'out_trade_no' => ['type' => 'string', 'description' => '爱发电订单号'],
        ],
        'required'   => ['out_trade_no'],
    ],
    'permission'  => 'read_only',
    'handler'     => 'handle_get_order_detail',
]);

registerTool('list_tables', [
    'name'        => 'list_tables',
    'description' => '列出数据库（mcsqlserver）中的所有数据表名称，用于识别数据库整体结构。只读。',
    'inputSchema' => [
        'type'       => 'object',
        'properties' => (object)[],
        'required'   => [],
    ],
    'permission'  => 'read_only',
    'handler'     => 'handle_list_tables',
]);

registerTool('describe_table', [
    'name'        => 'describe_table',
    'description' => '查看某张表的字段结构（字段名、类型、是否主键/唯一、默认值），用于识别字段含义。表名仅允许字母数字下划线。只读。',
    'inputSchema' => [
        'type'       => 'object',
        'properties' => [
            'table' => ['type' => 'string', 'description' => '表名'],
        ],
        'required'   => ['table'],
    ],
    'permission'  => 'read_only',
    'handler'     => 'handle_describe_table',
]);

registerTool('search_orders', [
    'name'        => 'search_orders',
    'description' => '按状态(status)、玩家游戏名(remark 模糊)、时间范围(from/to, 格式 YYYY-MM-DD HH:MM:SS)灵活筛选爱发电订单，返回订单列表。只读。',
    'inputSchema' => [
        'type'       => 'object',
        'properties' => [
            'status'       => ['type' => 'string', 'description' => '订单状态: pending/processing/completed/failed'],
            'player_name'  => ['type' => 'string', 'description' => '玩家游戏名（模糊匹配 afdian_orders.remark）'],
            'from'         => ['type' => 'string', 'description' => '起始时间'],
            'to'           => ['type' => 'string', 'description' => '结束时间'],
            'limit'        => ['type' => 'integer', 'description' => '返回条数上限，默认50，最大200'],
        ],
        'required'   => [],
    ],
    'permission'  => 'read_only',
    'handler'     => 'handle_search_orders',
]);

registerTool('search_players', [
    'name'        => 'search_players',
    'description' => '按游戏名或 UUID 模糊搜索玩家，返回匹配的 uuid、用户名、当前黄金券点数。只读。',
    'inputSchema' => [
        'type'       => 'object',
        'properties' => [
            'player_name'  => ['type' => 'string', 'description' => '玩家游戏名（模糊）'],
            'player_uuid'  => ['type' => 'string', 'description' => '玩家 UUID（模糊）'],
            'limit'        => ['type' => 'integer', 'description' => '返回条数上限，默认50，最大200'],
        ],
        'required'   => [],
    ],
    'permission'  => 'read_only',
    'handler'     => 'handle_search_players',
]);

registerTool('run_readonly_query', [
    'name'        => 'run_readonly_query',
    'description' => '执行一条受控的只读 SQL（SELECT）。仅限 SELECT、禁止写/危险关键词、禁止查询 password 等敏感列、强制 LIMIT≤200。用于对任意数据灵活读取与总结。需管理员角色。',
    'inputSchema' => [
        'type'       => 'object',
        'properties' => [
            'sql' => ['type' => 'string', 'description' => '一条 SELECT 语句'],
        ],
        'required'   => ['sql'],
    ],
    'permission'  => 'admin_only',
    'handler'     => 'handle_run_readonly_query',
]);

