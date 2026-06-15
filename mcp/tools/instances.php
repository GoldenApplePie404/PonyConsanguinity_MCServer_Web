<?php
/**
 * MCP Tools — 实例管理
 *
 * 提供实例列表、详情、日志、启停控制等工具 handler。
 * 读操作为 read_only（普通用户可用），写操作为 admin_only。
 */

/**
 * list_instances — 实例列表
 */
function handle_list_instances(array $args): string
{
    $daemonId = $args['daemonId'] ?? '';
    $page     = max(1, (int)($args['page'] ?? 1));
    $pageSize = max(1, min(100, (int)($args['page_size'] ?? 50)));

    $query = [
        'page'      => $page,
        'page_size' => $pageSize,
        'status'    => '',
    ];
    if (!empty($daemonId)) {
        $query['daemonId'] = $daemonId;
    }

    $data = mcsmApiCall('/api/service/remote_service_instances', $query);

    $instances = [];
    foreach (($data['data'] ?? []) as $inst) {
        $info = $inst['info'] ?? [];
        $config = $inst['config'] ?? [];
        $process = $inst['processInfo'] ?? [];

        $statusMap = [
            -1 => '忙碌',
            0  => '停止',
            1  => '停止中',
            2  => '启动中',
            3  => '运行中',
        ];

        $instances[] = [
            'uuid'           => $inst['instanceUuid'] ?? '',
            'name'           => $config['nickname'] ?? '未命名',
            'status'         => $statusMap[$inst['status'] ?? 0] ?? '未知',
            'status_code'    => $inst['status'] ?? 0,
            'players'        => [
                'current' => $info['currentPlayers'] ?? -1,
                'max'     => $info['maxPlayers'] ?? -1,
            ],
            'version'        => $info['version'] ?? '',
            'start_command'  => $config['startCommand'] ?? '',
            'type'           => $config['type'] ?? 'universal',
            'process'        => [
                'cpu'    => $process['cpu'] ?? 0,
                'memory' => formatBytes($process['memory'] ?? 0),
            ],
            'started_count'  => $inst['started'] ?? 0,
            'space'          => formatBytes($inst['space'] ?? 0),
            'auto_start'     => $config['eventTask']['autoStart'] ?? false,
            'auto_restart'   => $config['eventTask']['autoRestart'] ?? false,
        ];
    }

    return json_encode([
        'instances' => $instances,
        'page'      => $data['page'] ?? $page,
        'pageSize'  => $data['pageSize'] ?? $pageSize,
        'maxPage'   => $data['maxPage'] ?? 1,
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
            'cpu'      => $process['cpu'] ?? 0,
            'memory'   => formatBytes($process['memory'] ?? 0),
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

    return json_encode([
        'log'      => $data ?? '',
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
