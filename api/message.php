<?php
/**
 * 消息系统 API
 * 支持私聊 + 群聊
 * 
 * GET 请求:
 *   action=list          → 获取当前用户的会话列表
 *   action=history&conv_id=X → 获取会话消息
 *   action=unread        → 获取未读总数
 *   action=search&q=X    → 搜索用户
 * 
 * POST 请求:
 *   action=send          → 发送消息 { conv_id, content }
 *   action=start_private → 发起私聊 { to }
 *   action=create_group  → 创建群聊 { name, participants[] }
 *   action=mark_read     → 标记已读 { conv_id }
 *   action=add_member    → 添加群成员 { conv_id, username }
 *   action=leave_group   → 退出群聊 { conv_id }
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helper.php';
require_once __DIR__ . '/secure_data.php';
require_once __DIR__ . '/../includes/auth_helper.php';

set_cors_headers();
set_security_headers();
header('Content-Type: application/json; charset=utf-8');

// ── 初始化消息文件 ──
function initMessagesFile() {
    if (!file_exists(MESSAGES_FILE)) {
        write_json(MESSAGES_FILE, ['conversations' => [], 'messages' => []]);
    }
}

// ── 获取当前用户 ──
function getMessengerUser() {
    $user = AuthHelper::getCurrentUser();
    if (!$user) json_response(false, '请先登录', null, 401);
    return $user['username'] ?? '';
}

// ── 获取或创建私聊会话ID ──
function privateConvId($userA, $userB) {
    $parts = [$userA, $userB];
    sort($parts);
    return 'p_' . implode('_', $parts);
}

// ── 路由 ──
initMessagesFile();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    $username = getMessengerUser();

    if ($action === 'list') {
        // 获取会话列表
        $data = read_json(MESSAGES_FILE);
        $convs = $data['conversations'] ?? [];
        $list = [];
        foreach ($convs as $cid => $conv) {
            if (in_array($username, $conv['participants'] ?? [])) {
                $item = [
                    'id' => $cid,
                    'type' => $conv['type'],
                    'name' => $conv['name'] ?? '',
                    'participants' => $conv['participants'],
                    'last_message' => $conv['last_message'] ?? null,
                    'last_time' => $conv['last_time'] ?? '',
                ];
                // 私聊显示对方名字
                if ($conv['type'] === 'private') {
                    $other = array_values(array_filter($conv['participants'], fn($p) => $p !== $username));
                    $item['name'] = $other[0] ?? '未知用户';
                }
                $item['unread'] = $conv['unread'][$username] ?? 0;
                $list[] = $item;
            }
        }
        // 按最后时间排序
        usort($list, fn($a, $b) => strcmp($b['last_time'] ?? '', $a['last_time'] ?? ''));
        json_response(true, 'ok', ['conversations' => $list]);

    } elseif ($action === 'history') {
        // 获取会话消息
        $convId = $_GET['conv_id'] ?? '';
        if (!$convId) json_response(false, '缺少会话ID');
        $data = read_json(MESSAGES_FILE);
        $conv = $data['conversations'][$convId] ?? null;
        if (!$conv) json_response(false, '会话不存在');
        if (!in_array($username, $conv['participants'])) json_response(false, '无权查看', null, 403);

        $msgs = $data['messages'][$convId] ?? [];
        // 标记对方消息为已读
        if (isset($data['conversations'][$convId]['unread'][$username])) {
            $data['conversations'][$convId]['unread'][$username] = 0;
            write_json(MESSAGES_FILE, $data);
        }
        json_response(true, 'ok', ['messages' => $msgs, 'conversation' => $conv]);

    } elseif ($action === 'unread') {
        $data = read_json(MESSAGES_FILE);
        $total = 0;
        foreach ($data['conversations'] ?? [] as $cid => $conv) {
            if (in_array($username, $conv['participants'] ?? [])) {
                $total += $conv['unread'][$username] ?? 0;
            }
        }
        json_response(true, 'ok', ['total' => $total]);

    } elseif ($action === 'search') {
        $q = trim($_GET['q'] ?? '');
        if (!$q) json_response(true, 'ok', ['users' => []]);
        $users = secureReadData(USERS_FILE);
        $results = [];
        foreach ($users as $name => $info) {
            if ($name === $username) continue;
            if (stripos($name, $q) !== false || stripos($info['username'] ?? '', $q) !== false) {
                $results[] = ['username' => $name, 'nickname' => $info['eypa_nickname'] ?? $name];
            }
        }
        json_response(true, 'ok', ['users' => array_slice($results, 0, 10)]);

    } else {
        json_response(false, '未知操作');
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    $username = getMessengerUser();

    if ($action === 'send') {
        $convId = $input['conv_id'] ?? '';
        $content = trim($input['content'] ?? '');
        if (!$convId || !$content) json_response(false, '缺少参数');
        if (mb_strlen($content) > 2000) json_response(false, '消息过长');

        $data = read_json(MESSAGES_FILE);
        $conv = &$data['conversations'][$convId];
        if (!$conv) json_response(false, '会话不存在');
        if (!in_array($username, $conv['participants'])) json_response(false, '无权发言', null, 403);

        // 系统消息检查
        $msgType = 'text';
        if (strpos($content, '/system ') === 0 && in_array('admin', $conv['roles'] ?? [])) {
            $content = substr($content, 8);
            $msgType = 'system';
        }

        $msg = [
            'id' => time() . '_' . rand(1000, 9999),
            'from' => $username,
            'content' => $content,
            'time' => date('Y-m-d H:i:s'),
            'type' => $msgType
        ];

        $data['messages'][$convId][] = $msg;
        $conv['last_message'] = ['text' => $content, 'from' => $username];
        $conv['last_time'] = $msg['time'];

        // 未读计数（发给其他人）
        foreach ($conv['participants'] as $p) {
            if ($p !== $username) {
                $conv['unread'][$p] = ($conv['unread'][$p] ?? 0) + 1;
            }
        }

        write_json(MESSAGES_FILE, $data);
        json_response(true, '发送成功', ['message' => $msg]);

    } elseif ($action === 'start_private') {
        $to = $input['to'] ?? '';
        if (!$to || $to === $username) json_response(false, '无效的用户');
        
        $users = secureReadData(USERS_FILE);
        if (!isset($users[$to])) json_response(false, '用户不存在');

        $convId = privateConvId($username, $to);
        $data = read_json(MESSAGES_FILE);

        if (!isset($data['conversations'][$convId])) {
            $data['conversations'][$convId] = [
                'type' => 'private',
                'participants' => [$username, $to],
                'created_at' => date('Y-m-d H:i:s'),
                'last_message' => null,
                'last_time' => '',
                'unread' => [$username => 0, $to => 0]
            ];
            // 系统消息
            $sysMsg = [
                'id' => time() . '_sys',
                'from' => '',
                'content' => '会话已创建',
                'time' => date('Y-m-d H:i:s'),
                'type' => 'system'
            ];
            $data['messages'][$convId] = [$sysMsg];
            write_json(MESSAGES_FILE, $data);
        }

        json_response(true, 'ok', ['conv_id' => $convId]);

    } elseif ($action === 'create_group') {
        $name = trim($input['name'] ?? '');
        $participants = $input['participants'] ?? [];
        if (!$name) json_response(false, '请输入群名');
        if (count($participants) < 1) json_response(false, '至少需要 1 个成员');

        // 去重 + 包含自己
        $participants[] = $username;
        $participants = array_unique($participants);

        $users = secureReadData(USERS_FILE);
        foreach ($participants as $p) {
            if (!isset($users[$p])) json_response(false, "用户 $p 不存在");
        }

        $convId = 'g_' . time() . '_' . rand(1000, 9999);
        $data = read_json(MESSAGES_FILE);
        $unread = [];
        foreach ($participants as $p) $unread[$p] = 0;

        $data['conversations'][$convId] = [
            'type' => 'group',
            'name' => $name,
            'participants' => $participants,
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $username,
            'last_message' => null,
            'last_time' => '',
            'unread' => $unread
        ];

        $sysMsg = [
            'id' => time() . '_sys',
            'from' => '',
            'content' => "群「$name」已创建",
            'time' => date('Y-m-d H:i:s'),
            'type' => 'system'
        ];
        $data['messages'][$convId] = [$sysMsg];
        write_json(MESSAGES_FILE, $data);

        json_response(true, 'ok', ['conv_id' => $convId]);

    } elseif ($action === 'mark_read') {
        $convId = $input['conv_id'] ?? '';
        if (!$convId) json_response(false, '缺少会话ID');
        $data = read_json(MESSAGES_FILE);
        if (isset($data['conversations'][$convId]['unread'][$username])) {
            $data['conversations'][$convId]['unread'][$username] = 0;
            write_json(MESSAGES_FILE, $data);
        }
        json_response(true, 'ok');

    } elseif ($action === 'add_member') {
        $convId = $input['conv_id'] ?? '';
        $newMember = $input['username'] ?? '';
        if (!$convId || !$newMember) json_response(false, '缺少参数');
        
        $data = read_json(MESSAGES_FILE);
        $conv = &$data['conversations'][$convId];
        if (!$conv) json_response(false, '会话不存在');
        if ($conv['type'] !== 'group') json_response(false, '仅群聊可添加成员');
        if (!in_array($username, $conv['participants'])) json_response(false, '你不是群成员');
        if (in_array($newMember, $conv['participants'])) json_response(false, '已是群成员');

        $users = secureReadData(USERS_FILE);
        if (!isset($users[$newMember])) json_response(false, '用户不存在');

        $conv['participants'][] = $newMember;
        $conv['unread'][$newMember] = 0;

        $sysMsg = [
            'id' => time() . '_sys',
            'from' => '',
            'content' => "$username 邀请了 $newMember 加入群聊",
            'time' => date('Y-m-d H:i:s'),
            'type' => 'system'
        ];
        $data['messages'][$convId][] = $sysMsg;
        write_json(MESSAGES_FILE, $data);
        json_response(true, 'ok');

    } elseif ($action === 'leave_group') {
        $convId = $input['conv_id'] ?? '';
        if (!$convId) json_response(false, '缺少会话ID');
        $data = read_json(MESSAGES_FILE);
        $conv = &$data['conversations'][$convId];
        if (!$conv) json_response(false, '会话不存在');
        if ($conv['type'] !== 'group') json_response(false, '仅群聊可退出');
        
        $conv['participants'] = array_values(array_filter($conv['participants'], fn($p) => $p !== $username));
        unset($conv['unread'][$username]);

        $sysMsg = [
            'id' => time() . '_sys',
            'from' => '',
            'content' => "$username 退出了群聊",
            'time' => date('Y-m-d H:i:s'),
            'type' => 'system'
        ];
        $data['messages'][$convId][] = $sysMsg;
        write_json(MESSAGES_FILE, $data);
        json_response(true, 'ok');

    } elseif ($action === 'admin_list') {
        // 管理员查看所有会话
        $user = AuthHelper::getCurrentUser();
        if (!$user || ($user['role'] ?? 'user') !== 'admin') json_response(false, '权限不足');
        $data = read_json(MESSAGES_FILE);
        $convs = $data['conversations'] ?? [];
        $list = [];
        foreach ($convs as $cid => $conv) {
            $list[] = [
                'id' => $cid,
                'type' => $conv['type'],
                'name' => $conv['name'] ?? implode(',', $conv['participants']),
                'participants' => $conv['participants'],
                'msg_count' => count($data['messages'][$cid] ?? []),
                'last_time' => $conv['last_time'] ?? '',
            ];
        }
        usort($list, fn($a, $b) => strcmp($b['last_time'] ?? '', $a['last_time'] ?? ''));
        json_response(true, 'ok', ['conversations' => $list]);

    } elseif ($action === 'admin_messages') {
        // 管理员查看指定会话消息
        $user = AuthHelper::getCurrentUser();
        if (!$user || ($user['role'] ?? 'user') !== 'admin') json_response(false, '权限不足');
        $convId = $input['conv_id'] ?? '';
        if (!$convId) json_response(false, '缺少会话ID');
        $data = read_json(MESSAGES_FILE);
        $conv = $data['conversations'][$convId] ?? null;
        if (!$conv) json_response(false, '会话不存在');
        json_response(true, 'ok', ['messages' => $data['messages'][$convId] ?? [], 'conversation' => $conv]);

    } elseif ($action === 'admin_delete_messages') {
        // 管理员删除消息
        $user = AuthHelper::getCurrentUser();
        if (!$user || ($user['role'] ?? 'user') !== 'admin') json_response(false, '权限不足');
        $convId = $input['conv_id'] ?? '';
        $msgIds = $input['message_ids'] ?? [];
        $deleteAll = $input['delete_all'] ?? false;
        if (!$convId) json_response(false, '缺少会话ID');
        $data = read_json(MESSAGES_FILE);
        if (!isset($data['messages'][$convId])) json_response(false, '会话不存在');
        if ($deleteAll) {
            $data['messages'][$convId] = [];
            $data['conversations'][$convId]['last_message'] = null;
            $data['conversations'][$convId]['last_time'] = '';
        } elseif (!empty($msgIds)) {
            $data['messages'][$convId] = array_values(array_filter($data['messages'][$convId], fn($m) => !in_array($m['id'], $msgIds)));
        } else {
            json_response(false, '请指定要删除的消息');
        }
        write_json(MESSAGES_FILE, $data);
        json_response(true, 'ok');

    } elseif ($action === 'admin_mute') {
        // 管理员禁言用户
        $user = AuthHelper::getCurrentUser();
        if (!$user || ($user['role'] ?? 'user') !== 'admin') json_response(false, '权限不足');
        $targetUser = $input['username'] ?? '';
        $hours = (int)($input['hours'] ?? 24);
        if (!$targetUser) json_response(false, '缺少用户名');
        $users = secureReadData(USERS_FILE);
        if (!isset($users[$targetUser])) json_response(false, '用户不存在');
        $users[$targetUser]['chat_muted_until'] = date('Y-m-d H:i:s', time() + $hours * 3600);
        secureWriteData(USERS_FILE, $users);
        json_response(true, "用户 $targetUser 已被禁言 $hours 小时");

    } elseif ($action === 'admin_unmute') {
        // 管理员解除禁言
        $user = AuthHelper::getCurrentUser();
        if (!$user || ($user['role'] ?? 'user') !== 'admin') json_response(false, '权限不足');
        $targetUser = $input['username'] ?? '';
        if (!$targetUser) json_response(false, '缺少用户名');
        $users = secureReadData(USERS_FILE);
        if (!isset($users[$targetUser])) json_response(false, '用户不存在');
        unset($users[$targetUser]['chat_muted_until']);
        secureWriteData(USERS_FILE, $users);
        json_response(true, "用户 $targetUser 已解除禁言");

    } elseif ($action === 'admin_muted_list') {
        // 查看被禁言用户列表
        $user = AuthHelper::getCurrentUser();
        if (!$user || ($user['role'] ?? 'user') !== 'admin') json_response(false, '权限不足');
        $users = secureReadData(USERS_FILE);
        $muted = [];
        foreach ($users as $name => $info) {
            if (!empty($info['chat_muted_until'])) {
                $until = strtotime($info['chat_muted_until']);
                if ($until > time()) {
                    $muted[] = ['username' => $name, 'until' => $info['chat_muted_until']];
                } else {
                    unset($users[$name]['chat_muted_until']);
                }
            }
        }
        secureWriteData(USERS_FILE, $users);
        json_response(true, 'ok', ['muted' => $muted]);

    } else {
        json_response(false, '未知操作');
    }
}

echo json_encode(['success' => false, 'message' => '不支持的请求方法']);
