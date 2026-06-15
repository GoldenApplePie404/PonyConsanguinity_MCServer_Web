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
            'usage' => round(($data['system']['memUsage'] ?? 0) * 100, 1) . '%',
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
            'cpu'       => round(($node['system']['cpuUsage'] ?? 0) * 100, 1) . '%',
            'memory'    => [
                'total' => formatBytes($node['system']['totalmem'] ?? 0),
                'free'  => formatBytes($node['system']['freemem'] ?? 0),
                'usage' => round(($node['system']['memUsage'] ?? 0) * 100, 1) . '%',
            ],
            'instances' => [
                'running' => $node['instance']['running'] ?? 0,
                'total'   => $node['instance']['total'] ?? 0,
            ],
        ];
    }

    return json_encode(['nodes' => $nodes], JSON_UNESCAPED_UNICODE);
}

// ── 格式化工具 ───────────────────────────────────────────

function formatBytes(int $bytes): string
{
    if ($bytes === 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 1) . ' ' . $units[(int)$i];
}

function formatUptime(int $seconds): string
{
    if ($seconds <= 0) return '0 秒';
    $days = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    $mins = floor(($seconds % 3600) / 60);

    $parts = [];
    if ($days > 0) $parts[] = "{$days} 天";
    if ($hours > 0) $parts[] = "{$hours} 小时";
    if ($mins > 0) $parts[] = "{$mins} 分钟";
    if (empty($parts)) $parts[] = "{$seconds} 秒";

    return implode(' ', $parts);
}
