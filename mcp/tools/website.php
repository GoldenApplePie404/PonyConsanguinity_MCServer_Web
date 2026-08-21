<?php
/**
 * MCP Tools — 网站内容管理工具
 *
 * 将网站自身的 API（公告、通知）封装为 MCP 工具，
 * 供 AI 客服通过自然语言调用。
 */

/**
 * 通用：带 Token 的内部 API 请求
 */
function siteApiFetch(string $url, string $method = 'GET', array $data = []): array
{
    global $projectRoot;
    // Resolve relative URL to absolute
    if (str_starts_with($url, '../') || str_starts_with($url, './')) {
        $url = $projectRoot . '/' . ltrim($url, './');
    }

    $token = '';
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        $token = str_replace('Bearer ', '', $headers['Authorization']);
    } elseif (isset($headers['authorization'])) {
        $token = str_replace('Bearer ', '', $headers['authorization']);
    }
    if (empty($token) && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
    }

    $opts = [
        'http' => [
            'method'  => $method,
            'header'  => "Content-Type: application/json\r\nAuthorization: Bearer {$token}\r\n",
            'timeout' => 10,
            'ignore_errors' => true,
        ],
    ];

    if ($method === 'POST' || $method === 'PUT') {
        $opts['http']['content'] = json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    $context = stream_context_create($opts);
    $result = @file_get_contents($url, false, $context);
    if ($result === false) {
        throw new Exception("API 请求失败: {$url}");
    }
    return json_decode($result, true) ?? ['success' => false, 'message' => '解析响应失败'];
}

// ── 公告列表 ────────────────────────────────

function handle_list_announcements(array $args): string
{
    global $projectRoot;
    $announcementFile = $projectRoot . '/data/announcements.json';

    if (!file_exists($announcementFile)) {
        return json_encode(['success' => true, 'data' => ['announcements' => []]], JSON_UNESCAPED_UNICODE);
    }

    $raw = file_get_contents($announcementFile);
    $data = json_decode($raw, true) ?? ['announcements' => []];
    return json_encode(['success' => true, 'data' => [
        'announcements' => array_map(function($a) {
            return [
                'id'         => $a['id'] ?? '',
                'title'      => $a['title'] ?? '',
                'type'       => $a['type'] ?? '',
                'author'     => $a['author'] ?? '',
                'created_at' => $a['created_at'] ?? '',
                'views'      => $a['views'] ?? 0,
                'summary'    => $a['summary'] ?? '',
            ];
        }, $data['announcements'] ?? [])
    ]], JSON_UNESCAPED_UNICODE);
}

// ── 单个公告详情 ────────────────────────────

function handle_get_announcement(array $args): string
{
    $id = $args['id'] ?? '';
    global $projectRoot;
    $result = siteApiFetch($projectRoot . '/api/announcement.php?id=' . urlencode($id));

    if ($result['success'] ?? false) {
        return json_encode(['success' => true, 'data' => $result['data']], JSON_UNESCAPED_UNICODE);
    }
    return json_encode(['success' => false, 'message' => $result['message'] ?? '公告不存在'], JSON_UNESCAPED_UNICODE);
}

// ── 写入公告（新建/更新） ────────────────────

function handle_write_announcement(array $args): string
{
    $id = $args['id'] ?? '';
    $method = empty($id) ? 'POST' : 'PUT';
    global $projectRoot;
    $url = $projectRoot . '/api/announcement.php';
    if (!empty($id)) {
        $url .= '?id=' . urlencode($id);
    }

    $data = [
        'title'      => $args['title'] ?? '',
        'type'       => $args['type'] ?? 'update',
        'author'     => $args['author'] ?? '管理员',
        'created_at' => $args['created_at'] ?? date('Y-m-d'),
        'summary'    => $args['summary'] ?? '',
        'content'    => $args['content'] ?? '',
    ];
    if (!empty($id)) {
        $data['id'] = $id;
    }

    $result = siteApiFetch($url, $method, $data);
    if ($result['success'] ?? false) {
        $action = empty($id) ? '已创建' : '已更新';
        return json_encode(['success' => true, 'message' => "公告「{$args['title']}」{$action}"], JSON_UNESCAPED_UNICODE);
    }
    return json_encode(['success' => false, 'message' => $result['message'] ?? '操作失败'], JSON_UNESCAPED_UNICODE);
}

// ── 发送通知 ────────────────────────────────

function handle_send_notification(array $args): string
{
    global $projectRoot;
    $url = $projectRoot . '/api/send_notification.php';

    $data = [
        'title'   => $args['title'] ?? '',
        'type'    => $args['type'] ?? 'system',
        'content' => $args['content'] ?? '',
    ];

    $result = siteApiFetch($url, 'POST', $data);
    if ($result['success'] ?? false) {
        return json_encode(['success' => true, 'message' => "通知「{$args['title']}」已发送"], JSON_UNESCAPED_UNICODE);
    }
    return json_encode(['success' => false, 'message' => $result['message'] ?? '发送失败'], JSON_UNESCAPED_UNICODE);
}

// ── 工具注册（自注册模式） ──────────────────────────────

registerTool('list_announcements', [
    'name'        => 'list_announcements',
    'description' => '获取所有公告列表，返回每条公告的 ID、标题、类型、作者、日期',
    'inputSchema' => ['type' => 'object', 'properties' => (object)[], 'required' => []],
    'permission'  => 'read_only',
    'handler'     => 'handle_list_announcements',
]);

registerTool('get_announcement', [
    'name'        => 'get_announcement',
    'description' => '获取单个公告的完整内容（含 Markdown 正文）',
    'inputSchema' => [
        'type'       => 'object',
        'properties' => ['id' => ['type' => 'string', 'description' => '公告 ID']],
        'required'   => ['id'],
    ],
    'permission'  => 'read_only',
    'handler'     => 'handle_get_announcement',
]);

registerTool('write_announcement', [
    'name'        => 'write_announcement',
    'description' => '创建或更新一条公告。提供 id 为更新，不提供 id 为新建。支持 Markdown 格式内容',
    'inputSchema' => [
        'type'       => 'object',
        'properties' => [
            'id'         => ['type' => 'string', 'description' => '公告 ID（更新时必填，新建时可选）'],
            'title'      => ['type' => 'string', 'description' => '公告标题'],
            'type'       => ['type' => 'string', 'description' => '公告类型: update(版本更新) / event(活动) / notice(通知)'],
            'author'     => ['type' => 'string', 'description' => '作者名称'],
            'created_at' => ['type' => 'string', 'description' => '发布日期 (YYYY-MM-DD)'],
            'summary'    => ['type' => 'string', 'description' => '公告摘要'],
            'content'    => ['type' => 'string', 'description' => '公告正文（Markdown 格式）'],
        ],
        'required'   => ['title', 'type', 'author', 'content'],
    ],
    'permission'  => 'admin_only',
    'handler'     => 'handle_write_announcement',
]);

registerTool('send_notification', [
    'name'        => 'send_notification',
    'description' => '向全站发送系统通知，所有登录用户都会收到',
    'inputSchema' => [
        'type'       => 'object',
        'properties' => [
            'title'   => ['type' => 'string', 'description' => '通知标题'],
            'type'    => ['type' => 'string', 'description' => '通知类型: system(系统) / event(活动) / update(更新)'],
            'content' => ['type' => 'string', 'description' => '通知内容'],
        ],
        'required'   => ['title', 'content'],
    ],
    'permission'  => 'admin_only',
    'handler'     => 'handle_send_notification',
]);
