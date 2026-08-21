<?php
/**
 * MCP Tools — 仪表盘 / 系统监控
 *
 * 提供面板概览、节点状态、系统性能等只读工具的 handler。
 */

/**
 * get_dashboard — 面板概览数据
 */
function handle_get_dashboard(array $args): string
{
    $data = mcsmApiCall('/api/overview');

    $result = [
        'version'     => $data['version'] ?? '未知',
        'hostname'    => $data['system']['hostname'] ?? '未知',
        'platform'    => $data['system']['platform'] ?? '未知',
        'cpu'         => round(($data['system']['cpu'] ?? 0) * 100, 1) . '%',
        'memory'      => [
            'total' => formatBytes($data['system']['totalmem'] ?? 0),
            'free'  => formatBytes($data['system']['freemem'] ?? 0),
            'usage' => round(calculateMemUsage($data['system'] ?? []) * 100, 1) . '%',
        ],
        'uptime'      => formatUptime($data['system']['uptime'] ?? 0),
        'node_count'  => [
            'total'     => $data['remoteCount']['total'] ?? 0,
            'available' => $data['remoteCount']['available'] ?? 0,
        ],
        'instances'   => [
            'running' => 0,
            'total'   => 0,
        ],
        'login_stats' => [
            'logined'       => $data['record']['logined'] ?? 0,
            'failed'        => $data['record']['loginFailed'] ?? 0,
            'illegal_access' => $data['record']['illegalAccess'] ?? 0,
        ],
    ];

    // 汇总所有节点的实例数
    $remotes = $data['remote'] ?? [];
    foreach ($remotes as $node) {
        $result['instances']['running'] += $node['instance']['running'] ?? 0;
        $result['instances']['total']   += $node['instance']['total'] ?? 0;
    }

    return json_encode($result, JSON_UNESCAPED_UNICODE);
}

/**
 * get_nodes_status — 所有节点状态详情
 */
function handle_get_nodes_status(array $args): string
{
    $data = mcsmApiCall('/api/overview');
    $remotes = $data['remote'] ?? [];

    $nodes = [];
    foreach ($remotes as $node) {
        $nodes[] = [
            'uuid'      => $node['uuid'] ?? '',
            'ip'        => $node['ip'] ?? '',
            'port'      => $node['port'] ?? 0,
            'available' => $node['available'] ?? false,
            'remarks'   => $node['remarks'] ?? '',
            'version'   => $node['version'] ?? '',
            'platform'  => $node['system']['platform'] ?? '',
            'hostname'  => $node['system']['hostname'] ?? '',
            'uptime'    => formatUptime($node['system']['uptime'] ?? 0),
            'cpu'       => round(($node['system']['cpuUsage'] ?? $node['system']['cpu'] ?? 0) * 100, 1) . '%',
            'memory'    => [
                'total' => formatBytes($node['system']['totalmem'] ?? 0),
                'free'  => formatBytes($node['system']['freemem'] ?? 0),
                'usage' => round(calculateMemUsage($node['system'] ?? []) * 100, 1) . '%',
            ],
            'instances' => [
                'running' => $node['instance']['running'] ?? 0,
                'total'   => $node['instance']['total'] ?? 0,
            ],
        ];
    }

    return json_encode(['nodes' => $nodes], JSON_UNESCAPED_UNICODE);
}

// ── 工具注册（自注册模式） ──────────────────────────────
// 工具函数 define above, register here; loaded automatically by toolbase.php

registerTool('get_dashboard', [
    'name'        => 'get_dashboard',
    'description' => '获取 MCSManager 面板概览数据，包括面板版本、系统信息、CPU/内存使用率、所有节点状态',
    'inputSchema' => ['type' => 'object', 'properties' => (object)[], 'required' => []],
    'permission'  => 'read_only',
    'handler'     => 'handle_get_dashboard',
]);

registerTool('get_nodes_status', [
    'name'        => 'get_nodes_status',
    'description' => '获取所有节点的详细状态，包括每个节点的 CPU 使用率、内存使用率、运行/总实例数、系统信息',
    'inputSchema' => ['type' => 'object', 'properties' => (object)[], 'required' => []],
    'permission'  => 'read_only',
    'handler'     => 'handle_get_nodes_status',
]);
