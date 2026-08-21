<?php
/**
 * MCP Tools — 实例管理
 *
 * 提供实例列表、详情、日志、启停控制等工具 handler。
 * 读操作为 read_only（普通用户可用），写操作为 admin_only。
 */

/**
 * 批量并行获取运行中实例的详情，补充 processInfo 等实时数据。
 *
 * @param array $instances 以任意键为索引的实例数组，每项需包含 instanceUuid 与 daemonId
 * @return array 与原键对应的详情数组（processInfo 等字段）
 */
function fetchInstanceDetailsParallel(array $instances): array
{
    $details = [];
    $handles = [];
    $multi = curl_multi_init();
    if (!$multi) {
        return $details;
    }

    foreach ($instances as $idx => $inst) {
        $uuid = $inst['instanceUuid'] ?? '';
        $daemonId = $inst['daemonId'] ?? '';
        if (empty($uuid) || empty($daemonId)) {
            continue;
        }

        $url = rtrim(MCSM_API_URL, '/') . '/instance';
        $url .= '?' . http_build_query([
            'apikey'   => MCSM_API_KEY,
            'uuid'     => $uuid,
            'daemonId' => $daemonId,
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json; charset=utf-8',
                'X-Requested-With: XMLHttpRequest',
            ],
        ]);
        curl_multi_add_handle($multi, $ch);
        $handles[$idx] = $ch;
    }

    if (empty($handles)) {
        curl_multi_close($multi);
        return $details;
    }

    $running = null;
    do {
        curl_multi_exec($multi, $running);
        if ($running > 0) {
            curl_multi_select($multi, 0.05);
        }
    } while ($running > 0);

    foreach ($handles as $idx => $ch) {
        $response = curl_multi_getcontent($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $data = json_decode($response, true);
        if ($httpCode === 200 && is_array($data) && ($data['status'] ?? 0) === 200) {
            $details[$idx] = $data['data'] ?? [];
        }
        curl_multi_remove_handle($multi, $ch);
        curl_close($ch);
    }

    curl_multi_close($multi);
    return $details;
}

/**
 * list_instances — 实例列表
 */
function handle_list_instances(array $args): string
{
    $daemonId = $args['daemonId'] ?? '';
    $page     = max(1, (int)($args['page'] ?? 1));
    $pageSize = max(1, min(100, (int)($args['page_size'] ?? 50)));

    $statusMap = [
        -1 => '忙碌',
        0  => '停止',
        1  => '停止中',
        2  => '启动中',
        3  => '运行中',
    ];

    $collected = [];

    if (!empty($daemonId)) {
        // 指定节点：直接请求该节点实例
        $query = [
            'page'      => $page,
            'page_size' => $pageSize,
            'status'    => '',
            'daemonId'  => $daemonId,
        ];
        $data = mcsmApiCall('/api/service/remote_service_instances', $query);
        foreach (($data['data'] ?? []) as $inst) {
            $inst['daemonId'] = $daemonId;
            $collected[] = $inst;
        }
    } else {
        // 未指定节点：从 overview 获取所有 daemon，逐节点拉取并合并
        // （MCSManager 10.x 的 remote_service_instances 强制要求 daemonId）
        $overview = mcsmApiCall('/api/overview');
        $remotes  = $overview['remote'] ?? [];
        foreach ($remotes as $node) {
            $did = $node['uuid'] ?? '';
            if ($did === '') {
                continue;
            }
            $query = [
                'page'      => 1,
                'page_size' => 100,
                'status'    => '',
                'daemonId'  => $did,
            ];
            $data = mcsmApiCall('/api/service/remote_service_instances', $query);
            foreach (($data['data'] ?? []) as $inst) {
                $inst['daemonId'] = $did;
                $collected[] = $inst;
            }
        }
    }

    // 列表接口的 status 可能滞后（daemon 重启/状态未同步），对所有实例补充详情接口的实时状态
    $allIdx = [];
    foreach ($collected as $idx => $inst) {
        $allIdx[$idx] = $inst;
    }
    $details = fetchInstanceDetailsParallel($allIdx);
    foreach ($details as $idx => $detail) {
        if (isset($detail['status'])) {
            $collected[$idx]['status'] = $detail['status'];
        }
        if (!empty($detail['processInfo']) && is_array($detail['processInfo'])) {
            $collected[$idx]['processInfo'] = $detail['processInfo'];
        }
        if (!empty($detail['info']) && is_array($detail['info'])) {
            $collected[$idx]['info'] = $detail['info'];
        }
    }

    $total = count($collected);
    $slice = array_slice($collected, ($page - 1) * $pageSize, $pageSize);

    $instances = [];
    foreach ($slice as $inst) {
        $info    = $inst['info'] ?? [];
        $config  = $inst['config'] ?? [];
        $process = $inst['processInfo'] ?? [];

        // 运行判定：详情接口 status=3 视为运行中；即便 status 滞后，只要进程存活（pid>0）也视为运行中
        $rawStatus = (int) ($inst['status'] ?? 0);
        $pid       = (int) ($process['pid'] ?? 0);
        if ($rawStatus !== 3 && $pid > 0) {
            $rawStatus = 3;
        }

        $instances[] = [
            'uuid'           => $inst['instanceUuid'] ?? '',
            'daemonId'       => $inst['daemonId'] ?? $daemonId,
            'name'           => $config['nickname'] ?? '未命名',
            'status'         => $statusMap[$rawStatus] ?? '未知',
            'status_code'    => $rawStatus,
            'players'        => [
                'current' => $info['currentPlayers'] ?? -1,
                'max'     => $info['maxPlayers'] ?? -1,
            ],
            'version'        => $info['version'] ?? '',
            'start_command'  => $config['startCommand'] ?? '',
            'type'           => $config['type'] ?? 'universal',
        'process'        => [
            'cpu'    => normalizeCpu($process['cpu'] ?? null),
            'memory' => isset($process['memory']) ? formatBytes($process['memory']) : null,
        ],
            'started_count'  => $inst['started'] ?? 0,
            'space'          => formatBytes($inst['space'] ?? 0),
            'auto_start'     => $config['eventTask']['autoStart'] ?? false,
            'auto_restart'   => $config['eventTask']['autoRestart'] ?? false,
        ];
    }

    return json_encode([
        'instances' => $instances,
        'page'      => $page,
        'pageSize'  => $pageSize,
        'maxPage'   => max(1, ceil($total / $pageSize)),
        'total'     => $total,
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * get_instance_detail — 单个实例详情
 */
function handle_get_instance_detail(array $args): string
{
    $data = mcsmApiCall('/api/instance', [
        'uuid'     => $args['uuid'],
        'daemonId' => $args['daemonId'],
    ]);

    $config  = $data['config'] ?? [];
    $info    = $data['info'] ?? [];
    $process = $data['processInfo'] ?? [];

    $statusMap = [
        -1 => '忙碌',
        0  => '停止',
        1  => '停止中',
        2  => '启动中',
        3  => '运行中',
    ];

    $result = [
        'uuid'          => $data['instanceUuid'] ?? '',
        'name'          => $config['nickname'] ?? '未命名',
        'status'        => $statusMap[$data['status'] ?? 0] ?? '未知',
        'status_code'   => $data['status'] ?? 0,
        'started_count' => $data['started'] ?? 0,
        'space'         => formatBytes($data['space'] ?? 0),
        'config'        => [
            'start_command' => $config['startCommand'] ?? '',
            'stop_command'  => $config['stopCommand'] ?? '',
            'cwd'           => $config['cwd'] ?? '',
            'type'          => $config['type'] ?? 'universal',
            'file_code'     => $config['fileCode'] ?? 'utf-8',
            'process_type'  => $config['processType'] ?? 'native',
            'auto_start'    => $config['eventTask']['autoStart'] ?? false,
            'auto_restart'  => $config['eventTask']['autoRestart'] ?? false,
            'end_time'      => $config['endTime'] ?? 0,
            'created_at'    => $config['createDatetime'] ?? 0,
            'last_start'    => $config['lastDatetime'] ?? 0,
        ],
        'players' => [
            'current' => $info['currentPlayers'] ?? -1,
            'max'     => $info['maxPlayers'] ?? -1,
        ],
        'version' => $info['version'] ?? '',
        'process' => [
            'cpu'      => normalizeCpu($process['cpu'] ?? null),
            'memory'   => isset($process['memory']) ? formatBytes($process['memory']) : null,
            'pid'      => $process['pid'] ?? 0,
            'elapsed'  => $process['elapsed'] ?? 0,
        ],
    ];

    return json_encode($result, JSON_UNESCAPED_UNICODE);
}

/**
 * get_instance_log — 获取实例日志
 */
function handle_get_instance_log(array $args): string
{
    $size = max(1, min(2048, (int)($args['size'] ?? 100)));

    $data = mcsmApiCall('/api/protected_instance/outputlog', [
        'uuid'     => $args['uuid'],
        'daemonId' => $args['daemonId'],
        'size'     => $size,
    ]);

    // 该端点的 data 字段是 raw 字符串（mcsmApiCall 已自动包装为 ['raw' => $string]）
    $log = is_array($data) ? ($data['raw'] ?? '') : (string)$data;

    return json_encode([
        'log'      => $log,
        'size_kb'  => $size,
    ], JSON_UNESCAPED_UNICODE);
}

// ── 管理员操作 ───────────────────────────────────────────

/**
 * start_instance — 启动实例
 */
function handle_start_instance(array $args): string
{
    $data = mcsmApiCall('/api/protected_instance/open', [
        'uuid'     => $args['uuid'],
        'daemonId' => $args['daemonId'],
    ]);

    return json_encode([
        'success'      => true,
        'instanceUuid' => $data['instanceUuid'] ?? $args['uuid'],
        'message'      => '实例启动命令已发送',
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * stop_instance — 停止实例
 */
function handle_stop_instance(array $args): string
{
    $data = mcsmApiCall('/api/protected_instance/stop', [
        'uuid'     => $args['uuid'],
        'daemonId' => $args['daemonId'],
    ]);

    return json_encode([
        'success'      => true,
        'instanceUuid' => $data['instanceUuid'] ?? $args['uuid'],
        'message'      => '实例停止命令已发送',
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * restart_instance — 重启实例
 */
function handle_restart_instance(array $args): string
{
    $data = mcsmApiCall('/api/protected_instance/restart', [
        'uuid'     => $args['uuid'],
        'daemonId' => $args['daemonId'],
    ]);

    return json_encode([
        'success'      => true,
        'instanceUuid' => $data['instanceUuid'] ?? $args['uuid'],
        'message'      => '实例重启命令已发送',
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * send_command — 发送控制台命令
 */
function handle_send_command(array $args): string
{
    $data = mcsmApiCall('/api/protected_instance/command', [
        'uuid'     => $args['uuid'],
        'daemonId' => $args['daemonId'],
        'command'  => $args['command'],
    ]);

    return json_encode([
        'success'      => true,
        'instanceUuid' => $data['instanceUuid'] ?? $args['uuid'],
        'command'      => $args['command'],
        'message'      => '命令已发送到服务器控制台',
    ], JSON_UNESCAPED_UNICODE);
}

// ── 高级管理（破坏性，需确认） ──────────────────────────

/**
 * update_instance_config — 修改实例配置（如内存、自动启停）
 *
 * 破坏性操作，需前端确认（requiresConfirm）。
 * 传入 config 对象（仅包含要修改的字段），会自动与当前配置合并后整体写回。
 */
function handle_update_instance_config(array $args): string
{
    $overrides = $args['config'] ?? [];
    if (!is_array($overrides) || empty($overrides)) {
        throw new \Exception('缺少参数: config（要修改的配置字段对象，如 {"eventTask":{"autoRestart":true}}）');
    }

    // 1) 拉取当前实例完整配置
    $current = mcsmApiCall('/api/instance', [
        'uuid'     => $args['uuid'],
        'daemonId' => $args['daemonId'],
    ]);
    $config = $current['config'] ?? [];

    // 2) 浅合并覆盖，嵌套对象（eventTask 等）做深度合并，避免误清其他字段
    foreach ($overrides as $k => $v) {
        if (is_array($v) && isset($config[$k]) && is_array($config[$k])) {
            $config[$k] = array_merge($config[$k], $v);
        } else {
            $config[$k] = $v;
        }
    }

    // 3) 整体写回
    $data = mcsmApiCall('/api/instance', [
        'uuid'     => $args['uuid'],
        'daemonId' => $args['daemonId'],
    ], 'PUT', $config);

    return json_encode([
        'success'      => true,
        'instanceUuid' => $data['instanceUuid'] ?? $args['uuid'],
        'applied'      => $overrides,
        'message'      => '实例配置已更新（重启实例后生效）',
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * kill_instance — 强制结束实例进程（卡死时应急）
 *
 * 破坏性操作，需前端确认（requiresConfirm）。
 */
function handle_kill_instance(array $args): string
{
    $data = mcsmApiCall('/api/protected_instance/kill', [
        'uuid'     => $args['uuid'],
        'daemonId' => $args['daemonId'],
    ]);

    return json_encode([
        'success'      => true,
        'instanceUuid' => $data['instanceUuid'] ?? $args['uuid'],
        'message'      => '实例进程已发送强制结束信号',
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * batch_instances — 批量启停/重启多个实例
 *
 * 破坏性操作，需前端确认（requiresConfirm）。
 * action: start | stop | restart；uuids: 实例 UUID 数组；daemonId 可选（不传则自动从实例列表匹配）。
 */
function handle_batch_instances(array $args): string
{
    $action = $args['action'] ?? '';
    $uuids  = $args['uuids'] ?? [];
    if (!in_array($action, ['start', 'stop', 'restart'], true)) {
        throw new \Exception('参数 action 必须为 start / stop / restart 之一');
    }
    if (!is_array($uuids) || empty($uuids)) {
        throw new \Exception('缺少参数: uuids（实例 UUID 数组）');
    }

    $endpointMap = [
        'start'   => '/api/protected_instance/open',
        'stop'    => '/api/protected_instance/stop',
        'restart' => '/api/protected_instance/restart',
    ];
    $endpoint = $endpointMap[$action];

    // 构建 uuid -> daemonId 映射（未提供 daemonId 时）
    $uuidMap = [];
    if (!empty($args['daemonId'])) {
        foreach ($uuids as $u) {
            $uuidMap[$u] = $args['daemonId'];
        }
    } else {
        $listJson = handle_list_instances(['page' => 1, 'page_size' => 200]);
        $list = json_decode($listJson, true);
        $known = [];
        foreach (($list['instances'] ?? []) as $inst) {
            $known[$inst['uuid']] = $inst['daemonId'];
        }
        foreach ($uuids as $u) {
            if (isset($known[$u])) {
                $uuidMap[$u] = $known[$u];
            }
        }
    }

    $ok = [];
    $failed = [];
    foreach ($uuids as $u) {
        if (empty($uuidMap[$u])) {
            $failed[] = ['uuid' => $u, 'error' => '找不到该实例的节点(daemonId)，跳过'];
            continue;
        }
        try {
            mcsmApiCall($endpoint, [
                'uuid'     => $u,
                'daemonId' => $uuidMap[$u],
            ]);
            $ok[] = $u;
        } catch (\Throwable $e) {
            $failed[] = ['uuid' => $u, 'error' => $e->getMessage()];
        }
    }

    return json_encode([
        'success'     => count($failed) === 0,
        'action'      => $action,
        'succeeded'   => $ok,
        'failed'      => $failed,
        'message'     => '批量' . $action . ' 完成：成功 ' . count($ok) . ' 个，失败 ' . count($failed) . ' 个',
    ], JSON_UNESCAPED_UNICODE);
}

// ── 工具注册（自注册模式） ──────────────────────────────

registerTool('list_instances', [
    'name'        => 'list_instances',
    'description' => '获取指定节点下所有游戏服务器实例列表，包含实例名称、UUID、运行状态、当前玩家数等',
    'inputSchema' => [
        'type'       => 'object',
        'properties' => [
            'daemonId'   => ['type' => 'string', 'description' => '节点 UUID（可选，不传则返回所有节点的实例）'],
            'page'       => ['type' => 'integer', 'description' => '页码（默认 1）'],
            'page_size'  => ['type' => 'integer', 'description' => '每页数量（默认 50）'],
        ],
    ],
    'permission'  => 'read_only',
    'handler'     => 'handle_list_instances',
]);

registerTool('get_instance_detail', [
    'name'        => 'get_instance_detail',
    'description' => '获取单个实例的详细信息，包括配置、运行状态、进程资源占用（CPU/内存）、启动次数等',
    'inputSchema' => [
        'type'       => 'object',
        'properties' => [
            'uuid'     => ['type' => 'string', 'description' => '实例 UUID'],
            'daemonId' => ['type' => 'string', 'description' => '实例所在节点 UUID'],
        ],
        'required'   => ['uuid', 'daemonId'],
    ],
    'permission'  => 'read_only',
    'handler'     => 'handle_get_instance_detail',
]);

registerTool('get_instance_log', [
    'name'        => 'get_instance_log',
    'description' => '获取实例最近的控制台输出日志，用于排查问题或查看服务器启动情况',
    'inputSchema' => [
        'type'       => 'object',
        'properties' => [
            'uuid'     => ['type' => 'string', 'description' => '实例 UUID'],
            'daemonId' => ['type' => 'string', 'description' => '实例所在节点 UUID'],
            'size'     => ['type' => 'integer', 'description' => '获取的日志大小（KB），默认 100，范围 1~2048'],
        ],
        'required'   => ['uuid', 'daemonId'],
    ],
    'permission'  => 'read_only',
    'handler'     => 'handle_get_instance_log',
]);

registerTool('start_instance', [
    'name'        => 'start_instance',
    'description' => '启动指定的游戏服务器实例',
    'inputSchema' => [
        'type'       => 'object',
        'properties' => [
            'uuid'     => ['type' => 'string', 'description' => '实例 UUID'],
            'daemonId' => ['type' => 'string', 'description' => '实例所在节点 UUID'],
        ],
        'required'   => ['uuid', 'daemonId'],
    ],
    'permission'  => 'admin_only',
    'handler'     => 'handle_start_instance',
]);

registerTool('stop_instance', [
    'name'        => 'stop_instance',
    'description' => '停止指定的游戏服务器实例',
    'inputSchema' => [
        'type'       => 'object',
        'properties' => [
            'uuid'     => ['type' => 'string', 'description' => '实例 UUID'],
            'daemonId' => ['type' => 'string', 'description' => '实例所在节点 UUID'],
        ],
        'required'   => ['uuid', 'daemonId'],
    ],
    'permission'  => 'admin_only',
    'handler'     => 'handle_stop_instance',
]);

registerTool('restart_instance', [
    'name'        => 'restart_instance',
    'description' => '重启指定的游戏服务器实例',
    'inputSchema' => [
        'type'       => 'object',
        'properties' => [
            'uuid'     => ['type' => 'string', 'description' => '实例 UUID'],
            'daemonId' => ['type' => 'string', 'description' => '实例所在节点 UUID'],
        ],
        'required'   => ['uuid', 'daemonId'],
    ],
    'permission'  => 'admin_only',
    'handler'     => 'handle_restart_instance',
]);

registerTool('send_command', [
    'name'        => 'send_command',
    'description' => '向指定游戏服务器的【控制台】发送命令。注意：命令通过服务器控制台执行，不需要、也不要加前导斜杠"/"（例如天气指令写 weather clear 而非 /weather clear，广播写 say 你好 而非 /say 你好）。请先通过 list_instances 按名称匹配目标实例取得 uuid 与 daemonId。',
    'inputSchema' => [
        'type'       => 'object',
        'properties' => [
            'uuid'     => ['type' => 'string', 'description' => '实例 UUID'],
            'daemonId' => ['type' => 'string', 'description' => '实例所在节点 UUID'],
            'command'  => ['type' => 'string', 'description' => '要发送的命令内容'],
        ],
        'required'   => ['uuid', 'daemonId', 'command'],
    ],
    'permission'  => 'admin_only',
    'handler'     => 'handle_send_command',
]);

registerTool('update_instance_config', [
    'name'        => 'update_instance_config',
    'description' => '修改指定实例的配置（如内存分配、自动启动/重启、启动命令等）。只需传入要修改的字段。示例：将生存服设为自动重启 {"config":{"eventTask":{"autoRestart":true}}}；改内存需在 start_command 中调整 -Xmx 参数。修改后需重启实例生效。这是破坏性操作。',
    'inputSchema' => [
        'type'       => 'object',
        'properties' => [
            'uuid'     => ['type' => 'string', 'description' => '实例 UUID'],
            'daemonId' => ['type' => 'string', 'description' => '实例所在节点 UUID'],
            'config'   => ['type' => 'object', 'description' => '要修改的配置字段（仅写要改的，如 {"eventTask":{"autoRestart":true}} 或 {"nickname":"新名字"}）'],
            'confirm'  => ['type' => 'boolean', 'description' => '破坏性操作确认：必须为 true 才会执行'],
        ],
        'required'   => ['uuid', 'daemonId', 'config', 'confirm'],
    ],
    'permission'     => 'admin_only',
    'requiresConfirm' => true,
    'handler'        => 'handle_update_instance_config',
]);

registerTool('kill_instance', [
    'name'        => 'kill_instance',
    'description' => '强制结束指定实例的进程（用于服务器卡死、停止命令无响应的应急场景）。实例会被立即杀掉，不会正常保存。这是破坏性操作。',
    'inputSchema' => [
        'type'       => 'object',
        'properties' => [
            'uuid'     => ['type' => 'string', 'description' => '实例 UUID'],
            'daemonId' => ['type' => 'string', 'description' => '实例所在节点 UUID'],
            'confirm'  => ['type' => 'boolean', 'description' => '破坏性操作确认：必须为 true 才会执行'],
        ],
        'required'   => ['uuid', 'daemonId', 'confirm'],
    ],
    'permission'     => 'admin_only',
    'requiresConfirm' => true,
    'handler'        => 'handle_kill_instance',
]);

registerTool('batch_instances', [
    'name'        => 'batch_instances',
    'description' => '批量对多个实例执行启动/停止/重启。action 为 start/stop/restart；uuids 为实例 UUID 数组；daemonId 可选（不传则自动匹配节点）。例如"把全部实例重启"。这是破坏性操作。',
    'inputSchema' => [
        'type'       => 'object',
        'properties' => [
            'action'   => ['type' => 'string', 'description' => '操作类型: start / stop / restart'],
            'uuids'    => ['type' => 'array', 'description' => '实例 UUID 数组', 'items' => ['type' => 'string']],
            'daemonId' => ['type' => 'string', 'description' => '节点 UUID（可选，不传则按 uuid 自动匹配）'],
            'confirm'  => ['type' => 'boolean', 'description' => '破坏性操作确认：必须为 true 才会执行'],
        ],
        'required'   => ['action', 'uuids', 'confirm'],
    ],
    'permission'     => 'admin_only',
    'requiresConfirm' => true,
    'handler'        => 'handle_batch_instances',
]);
