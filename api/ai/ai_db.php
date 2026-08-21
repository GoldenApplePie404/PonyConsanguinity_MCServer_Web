<?php
/**
 * AI 对话历史持久化（轻量级文件存储，云端为唯一真相源）
 * ============================================================
 * 设计取舍：
 * - 复用本站 data/ 目录，与 api/message.php 同一存储风格（.php 数组文件，
 *   直接 include 返回数组，外部无法窥探内容）。
 * - 不引入 MySQL 依赖，部署零新增表，契合项目"本站数据走 data/ 文件"的惯例。
 * - 对话历史属于"本站数据"，与 MC 服务器数据库（PlayerPoints / 爱发电订单）
 *   隔离，避免把玩家聊天混入游戏库。
 *
 * 存储结构（data/ai_conversations.php）：
 *   return [
 *     'version'     => 2,
 *     'conversations' => [
 *       'conv_xxx' => [
 *         'id'           => 'conv_xxx',
 *         'title'        => '对话 2',
 *         'persona'      => 'customer',
 *         'client_id'    => 'cli_abc',   // 匿名客户端标识（无登录也能跨对话认人）
 *         'summary'      => '...',        // 长期压缩记忆（A 分层）
 *         'messages'     => [ [...], ... ],// 仅保留最近 AI_KEEP_RAW 条原文（防膨胀）
 *         'created_at'   => 1720000000,
 *         'updated_at'   => 1720000060,
 *         'turn_count'   => 42,           // 累计轮数（含已压缩掉的）
 *         'unsummarized' => 12,           // 自上次压缩以来累积的未压缩原文条数
 *       ],
 *     ],
 *     'profiles' => [                     // C：结构化用户画像（跨对话，按 client_id）
 *       'cli_abc' => [
 *         'player_name' => 'GAP',
 *         'topics'      => ['充值','订单'],
 *         'prefs'       => ['简洁','中文'],
 *         'updated_at'  => 1720000060,
 *       ],
 *     ],
 *   ];
 *
 * 向后兼容：旧版扁平结构（直接 cid => conv）会被 ai_conv_normalize 自动包装为
 * conversations 并补齐缺失字段，无需迁移脚本。
 */

require_once __DIR__ . '/../../config/config.php';

// 热文件保留的最近原文条数（= 12 轮），超过则仅保留最近这些用于喂模型
define('AI_KEEP_RAW', 24);
// 累积多少条未压缩原文后触发一次摘要压缩（= 6 轮）
define('AI_COMPACT_THRESHOLD', 12);

/**
 * 对话历史文件路径
 */
function ai_conv_file(): string {
    return __DIR__ . '/../../data/ai_conversations.php';
}

/**
 * 读取全部数据（返回带 conversations/profiles 的标准结构）
 */
function ai_conv_load_all(): array {
    $f = ai_conv_file();
    if (!file_exists($f)) {
        return ['version' => 2, 'conversations' => [], 'profiles' => []];
    }
    $data = @include $f;
    if (!is_array($data)) {
        return ['version' => 2, 'conversations' => [], 'profiles' => []];
    }
    return ai_conv_normalize($data);
}

/**
 * 兼容旧结构（扁平 cid => conv）并补齐所有字段，保证后续逻辑无需判空
 */
function ai_conv_normalize(array $data): array {
    if (!isset($data['conversations']) || !is_array($data['conversations'])) {
        $conv = [];
        foreach ($data as $k => $v) {
            if (is_array($v) && (isset($v['messages']) || isset($v['persona']) || isset($v['title']))) {
                $v['id'] = $v['id'] ?? $k;
                $conv[$k] = $v;
            }
        }
        $data = ['conversations' => $conv];
    }
    $data['version'] = 2;
    if (!isset($data['profiles']) || !is_array($data['profiles'])) {
        $data['profiles'] = [];
    }
    foreach ($data['conversations'] as &$c) {
        $c['id']          = $c['id']          ?? '';
        $c['title']       = $c['title']       ?? '对话';
        $c['persona']     = $c['persona']     ?? 'customer';
        $c['client_id']   = $c['client_id']   ?? '';
        $c['summary']     = $c['summary']     ?? '';
        $c['messages']    = $c['messages']    ?? [];
        $c['created_at']  = $c['created_at']  ?? time();
        $c['updated_at']  = $c['updated_at']  ?? time();
        $c['turn_count']  = $c['turn_count']  ?? intval(count($c['messages']) / 2);
        $c['unsummarized'] = $c['unsummarized'] ?? 0;
    }
    unset($c);
    return $data;
}

/**
 * 原子写入全部数据（先写临时文件再 rename，避免并发写损坏）
 */
function ai_conv_save_all(array $all): bool {
    $f = ai_conv_file();
    $dir = dirname($f);
    if (!is_writable($dir)) {
        return false;
    }
    $all = ai_conv_normalize($all);
    $tmp = $f . '.tmp';
    $code = "<?php\n// AI 对话历史（自动生成，请勿手动编辑）\nreturn " . var_export($all, true) . ";\n";
    if (file_put_contents($tmp, $code) === false) {
        return false;
    }
    // 写入目标可能因杀毒软件/并发而瞬时锁住，重试几次避免偶发访问拒绝
    for ($i = 0; $i < 3; $i++) {
        if (rename($tmp, $f)) {
            return true;
        }
        usleep(50000);
    }
    // 最后一次仍失败则清理临时文件
    if (file_exists($tmp)) @unlink($tmp);
    return false;
}

// ── 单会话读写 ──────────────────────────────────────
function ai_get_conv(string $cid): ?array {
    $all = ai_conv_load_all();
    return $all['conversations'][$cid] ?? null;
}

function ai_put_conv(string $cid, array $conv): bool {
    $all = ai_conv_load_all();
    $conv['id'] = $cid;
    $all['conversations'][$cid] = array_merge($all['conversations'][$cid] ?? [], $conv);
    return ai_conv_save_all($all);
}

// 旧记录（无 client_id）在首次访问时打上当前客户端标识（单用户本地部署逐步迁移）
function ai_tag_client(string $cid, string $clientId): void {
    if ($clientId === '') return;
    $conv = ai_get_conv($cid);
    if ($conv && ($conv['client_id'] ?? '') === '') {
        ai_put_conv($cid, ['client_id' => $clientId, 'updated_at' => time()]);
    }
}

function ai_save_summary(string $cid, string $summary): bool {
    return ai_put_conv($cid, ['summary' => $summary, 'updated_at' => time()]);
}

function ai_clear_conv(string $cid): bool {
    return ai_put_conv($cid, [
        'messages'     => [],
        'summary'      => '',
        'turn_count'   => 0,
        'unsummarized' => 0,
        'updated_at'   => time(),
    ]);
}

function ai_rename_conv(string $cid, string $title): bool {
    return ai_put_conv($cid, ['title' => $title, 'updated_at' => time()]);
}

function ai_delete_conv(string $cid): bool {
    $all = ai_conv_load_all();
    unset($all['conversations'][$cid]);
    return ai_conv_save_all($all);
}

// 列表（按 client_id 隔离；旧记录未打标也展示给当前客户端）
function ai_list_convs(string $clientId): array {
    $all = ai_conv_load_all();
    $out = [];
    foreach ($all['conversations'] as $cid => $c) {
        if ($clientId !== '' && !empty($c['client_id']) && $c['client_id'] !== $clientId) {
            continue;
        }
        $msgs = $c['messages'];
        $preview = '';
        if (!empty($msgs)) {
            $last = end($msgs);
            $preview = mb_substr(($last['content'] ?? ''), 0, 40);
        }
        $out[] = [
            'id'         => $cid,
            'title'      => $c['title'] ?: '对话',
            'preview'    => $preview,
            'updated_at' => $c['updated_at'],
            'msg_count'  => count($msgs),
            'summary'    => $c['summary'] ?? '',
        ];
    }
    usort($out, fn($a, $b) => $b['updated_at'] <=> $a['updated_at']);
    return $out;
}

function ai_get_conv_messages(string $cid): array {
    $conv = ai_get_conv($cid);
    return $conv ? ($conv['messages'] ?? []) : [];
}

/**
 * 追加一轮对话；返回是否达到压缩阈值（need_compact）
 * 热文件只保留最近 AI_KEEP_RAW 条原文，防止文件无限增长。
 */
function ai_append_turn(string $cid, string $persona, string $user, string $assistant, string $clientId = ''): array {
    if ($user === '' && $assistant === '') {
        return ['need_compact' => false];
    }
    $all = ai_conv_load_all();
    if (!isset($all['conversations'][$cid])) {
        $all['conversations'][$cid] = [
            'id'          => $cid,
            'title'       => '对话',
            'persona'     => $persona,
            'client_id'   => $clientId,
            'summary'     => '',
            'messages'    => [],
            'created_at'  => time(),
            'updated_at'  => time(),
            'turn_count'  => 0,
            'unsummarized'=> 0,
        ];
    }
    $conv =& $all['conversations'][$cid];
    if ($user !== '')    $conv['messages'][] = ['role' => 'user', 'content' => $user];
    if ($assistant !== '') $conv['messages'][] = ['role' => 'assistant', 'content' => $assistant];
    // 热文件只保留最近 AI_KEEP_RAW 条原文
    if (count($conv['messages']) > AI_KEEP_RAW) {
        $conv['messages'] = array_slice($conv['messages'], -AI_KEEP_RAW);
    }
    $conv['turn_count'] = ($conv['turn_count'] ?? 0) + (($user !== '' && $assistant !== '') ? 1 : 0);
    $conv['unsummarized'] = ($conv['unsummarized'] ?? 0) + (($user !== '' ? 1 : 0) + ($assistant !== '' ? 1 : 0));
    $conv['updated_at'] = time();
    $needCompact = $conv['unsummarized'] >= AI_COMPACT_THRESHOLD;
    ai_conv_save_all($all);
    return ['need_compact' => $needCompact];
}

/**
 * 追加单条消息（用于前端把工具执行后的最终回复写回云端）。
 * 不增加 turn_count/unsummarized，仅更新消息列表与 updated_at。
 */
function ai_append_message(string $cid, string $role, string $content): bool {
    if ($content === '' || !in_array($role, ['assistant', 'user', 'tool', 'system'], true)) {
        return false;
    }
    $all = ai_conv_load_all();
    if (!isset($all['conversations'][$cid])) return false;
    $conv =& $all['conversations'][$cid];
    $conv['messages'][] = ['role' => $role, 'content' => $content];
    if (count($conv['messages']) > AI_KEEP_RAW) {
        $conv['messages'] = array_slice($conv['messages'], -AI_KEEP_RAW);
    }
    $conv['updated_at'] = time();
    return ai_conv_save_all($all);
}

/**
 * 整段覆盖某会话的消息列表（用于前端「加载时自愈」：把含 TOOL_CALL 的历史消息
 * 替换为工具执行后的最终回复后再写回云端）。
 * 仅更新消息与 updated_at，不改动 turn_count/unsummarized，避免触发重复压缩。
 */
function ai_set_messages(string $cid, array $messages): bool {
    $all = ai_conv_load_all();
    if (!isset($all['conversations'][$cid])) return false;
    $clean = [];
    foreach ($messages as $m) {
        if (!is_array($m)) continue;
        $role = $m['role'] ?? '';
        $content = $m['content'] ?? '';
        if (!in_array($role, ['assistant', 'user', 'tool', 'system'], true)) continue;
        $content = is_string($content) ? $content : (string) $content;
        if ($content === '') continue;
        $clean[] = ['role' => $role, 'content' => $content];
    }
    // 热文件只保留最近 AI_KEEP_RAW 条
    if (count($clean) > AI_KEEP_RAW) {
        $clean = array_slice($clean, -AI_KEEP_RAW);
    }
    $all['conversations'][$cid]['messages'] = $clean;
    $all['conversations'][$cid]['updated_at'] = time();
    return ai_conv_save_all($all);
}

/**
 * 替换最后一条消息（若其为含 TOOL_CALL 的原始助手回复），否则追加。
 * 用于工具调用完成后用「最终回复」覆盖云端那条带 TOOL_CALL 的中间记录，
 * 避免留下原始 JSON 且不与最终回复重复。
 */
function ai_replace_last_message(string $cid, string $role, string $content): bool {
    if ($content === '' || !in_array($role, ['assistant', 'user', 'tool', 'system'], true)) {
        return false;
    }
    $all = ai_conv_load_all();
    if (!isset($all['conversations'][$cid])) return false;
    $conv =& $all['conversations'][$cid];
    $msgs =& $conv['messages'];
    $lastIdx = count($msgs) - 1;
    if ($lastIdx >= 0
        && ($msgs[$lastIdx]['role'] ?? '') === $role
        && strpos($msgs[$lastIdx]['content'] ?? '', 'TOOL_CALL:') !== false) {
        $msgs[$lastIdx]['content'] = $content;
    } else {
        $msgs[] = ['role' => $role, 'content' => $content];
    }
    if (count($msgs) > AI_KEEP_RAW) {
        $msgs = array_slice($msgs, -AI_KEEP_RAW);
    }
    $conv['updated_at'] = time();
    return ai_conv_save_all($all);
}

// 压缩后裁剪热文件（重新对齐到 KEEP_RAW 并重置未压缩计数）
function ai_prune_raw(string $cid): bool {
    $conv = ai_get_conv($cid);
    if (!$conv) return false;
    $msgs = $conv['messages'] ?? [];
    if (count($msgs) > AI_KEEP_RAW) {
        $msgs = array_slice($msgs, -AI_KEEP_RAW);
    }
    return ai_put_conv($cid, ['messages' => $msgs, 'unsummarized' => 0, 'updated_at' => time()]);
}

// ── 用户画像（跨对话，按 client_id）──────────────────
function ai_get_profile(string $clientId): array {
    if ($clientId === '') return [];
    $all = ai_conv_load_all();
    return $all['profiles'][$clientId] ?? [];
}

function ai_update_profile(string $clientId, array $patch): bool {
    if ($clientId === '') return false;
    $all = ai_conv_load_all();
    $cur = $all['profiles'][$clientId] ?? ['player_name' => '', 'topics' => [], 'prefs' => [], 'updated_at' => time()];
    foreach (['player_name', 'topics', 'prefs'] as $k) {
        if (!array_key_exists($k, $patch)) continue;
        if ($k === 'player_name') {
            if (is_string($patch[$k]) && $patch[$k] !== '') {
                $cur['player_name'] = $patch[$k];
            }
        } else {
            if (is_array($patch[$k])) {
                $cur[$k] = array_values(array_unique(array_merge($cur[$k] ?? [], $patch[$k])));
            }
        }
    }
    $cur['updated_at'] = time();
    $all['profiles'][$clientId] = $cur;
    return ai_conv_save_all($all);
}

function ai_delete_profile(string $clientId): bool {
    if ($clientId === '') return false;
    $all = ai_conv_load_all();
    unset($all['profiles'][$clientId]);
    return ai_conv_save_all($all);
}
