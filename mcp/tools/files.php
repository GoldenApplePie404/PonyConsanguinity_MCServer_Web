<?php
/**
 * MCP Tools — 实例文件管理
 *
 * 封装 MCSManager 文件管理 API，提供实例内文件的浏览、读取、写入、删除。
 *
 * 注意（2026-07-14 实测）：本部署的守护进程对文件 API 的写/读访问被限制
 * （写入返回 "Illegal access path"，读取返回 500）。list 可正常返回 200（但部分实例根目录 items 为空）。
 * 因此这些工具在「文件管理器 API 写访问已开启」的守护进程上才能完整工作；
 * 当前部署若未开启，写/读会返回清晰的报错而非崩溃，属预期降级行为。
 *
 * MCSManager 文件 API 参数约定（已探测确认）：
 *  - 列表：GET  /api/files/list?daemonId=&uuid=&target=<相对路径>&page=&page_size=
 *  - 读取：GET  /api/files?daemonId=&uuid=&target=<相对文件路径>
 *  - 写入：PUT  /api/files?daemonId=&uuid=   body: {"target":<路径>,"text":<内容>}
 *  - 删除：DELETE /api/files?daemonId=&uuid=  body: {"targets":[<路径>, ...]}
 *  target 一律使用相对于实例工作目录的相对路径（如 ./eula.txt 或 config/server.properties）。
 */

/**
 * list_instance_files — 浏览实例目录
 */
function handle_list_instance_files(array $args): string
{
    $target   = $args['path'] ?? './';
    $page     = max(1, (int)($args['page'] ?? 1));
    $pageSize = max(1, min(200, (int)($args['page_size'] ?? 50)));

    $data = mcsmApiCall('/api/files/list', [
        'daemonId'  => $args['daemonId'],
        'uuid'      => $args['uuid'],
        'target'    => $target,
        'page'      => $page,
        'page_size' => $pageSize,
    ]);

    // data 结构：{ items:[{name,size,time,mode,type,...}], page, pageSize, total, absolutePath }
    $items = $data['items'] ?? [];
    $files = [];
    foreach ($items as $it) {
        $files[] = [
            'name' => $it['name'] ?? '',
            'type' => $it['type'] ?? ($it['isDirectory'] ? 'directory' : 'file'),
            'size' => isset($it['size']) ? formatBytes((int)$it['size']) : null,
            'time' => $it['time'] ?? null,
            'mode' => $it['mode'] ?? null,
        ];
    }

    return json_encode([
        'path'         => $target,
        'absolutePath' => $data['absolutePath'] ?? null,
        'files'        => $files,
        'total'        => $data['total'] ?? count($files),
        'page'         => $data['page'] ?? $page,
        'pageSize'     => $data['pageSize'] ?? $pageSize,
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * read_instance_file — 读取实例文件内容
 */
function handle_read_instance_file(array $args): string
{
    $target = $args['path'] ?? '';
    if ($target === '') {
        throw new \Exception('缺少参数: path（相对文件路径，如 ./eula.txt）');
    }

    // MCSM 读取返回文件原文（data 字段为字符串）；某些部署返回 500（文件管理器读访问受限）
    $data = mcsmApiCall('/api/files', [
        'daemonId' => $args['daemonId'],
        'uuid'     => $args['uuid'],
        'target'   => $target,
    ]);

    // $data 可能是字符串（文件内容）或 {raw:...}
    $content = is_array($data) ? ($data['raw'] ?? reset($data)) : (string)$data;
    if (!is_string($content)) {
        $content = json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    $maxLen = 8000;
    $truncated = mb_strlen($content, 'UTF-8') > $maxLen;
    if ($truncated) {
        $content = mb_substr($content, 0, $maxLen, 'UTF-8');
    }

    return json_encode([
        'path'      => $target,
        'content'   => $content,
        'truncated' => $truncated,
        'note'      => $truncated ? '内容超过 8000 字符已截断，仅展示前部分。' : null,
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * write_instance_file — 写入/覆盖实例文件
 *
 * 破坏性操作，需前端确认（requiresConfirm）。
 */
function handle_write_instance_file(array $args): string
{
    $target  = $args['path'] ?? '';
    $content = $args['content'] ?? '';
    if ($target === '') {
        throw new \Exception('缺少参数: path（相对文件路径，如 ./eula.txt）');
    }

    // PUT：daemonId/uuid 走 query，target/text 走 body
    $data = mcsmApiCall('/api/files', [
        'daemonId' => $args['daemonId'],
        'uuid'     => $args['uuid'],
    ], 'PUT', [
        'target' => $target,
        'text'   => $content,
    ]);

    return json_encode([
        'success'   => true,
        'path'      => $target,
        'bytes'     => mb_strlen($content, 'UTF-8'),
        'message'   => '文件已写入（若返回 Illegal access path 说明该守护进程未开放文件写访问）',
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * delete_instance_file — 删除实例文件
 *
 * 破坏性操作，需前端确认（requiresConfirm）。
 */
function handle_delete_instance_file(array $args): string
{
    $target = $args['path'] ?? '';
    if ($target === '') {
        throw new \Exception('缺少参数: path（相对文件路径，如 ./old.log）');
    }

    // DELETE：daemonId/uuid 走 query，targets（数组）走 body
    $data = mcsmApiCall('/api/files', [
        'daemonId' => $args['daemonId'],
        'uuid'     => $args['uuid'],
    ], 'DELETE', [
        'targets' => [$target],
    ]);

    return json_encode([
        'success' => true,
        'path'    => $target,
        'message' => '删除请求已发送',
    ], JSON_UNESCAPED_UNICODE);
}

// ── 工具注册（自注册模式） ──────────────────────────────

registerTool('list_instance_files', [
    'name'        => 'list_instance_files',
    'description' => '浏览指定实例目录下的文件列表（名称、类型、大小、修改时间）。path 为相对实例工作目录的路径，默认 ./（根目录）。只读，安全。',
    'inputSchema' => [
        'type'       => 'object',
        'properties' => [
            'uuid'      => ['type' => 'string', 'description' => '实例 UUID'],
            'daemonId'  => ['type' => 'string', 'description' => '实例所在节点 UUID'],
            'path'      => ['type' => 'string', 'description' => '相对目录路径，如 ./ 或 ./plugins（默认 ./）'],
            'page'      => ['type' => 'integer', 'description' => '页码（默认 1）'],
            'page_size' => ['type' => 'integer', 'description' => '每页数量（默认 50）'],
        ],
        'required'   => ['uuid', 'daemonId'],
    ],
    'permission'  => 'read_only',
    'handler'     => 'handle_list_instance_files',
]);

registerTool('read_instance_file', [
    'name'        => 'read_instance_file',
    'description' => '读取实例内的文本文件内容（如 eula.txt、server.properties、config.yml）。path 为相对路径，如 ./eula.txt。内容超过 8000 字符会截断。只读，安全。',
    'inputSchema' => [
        'type'       => 'object',
        'properties' => [
            'uuid'     => ['type' => 'string', 'description' => '实例 UUID'],
            'daemonId' => ['type' => 'string', 'description' => '实例所在节点 UUID'],
            'path'     => ['type' => 'string', 'description' => '相对文件路径，如 ./eula.txt 或 ./server.properties'],
        ],
        'required'   => ['uuid', 'daemonId', 'path'],
    ],
    'permission'  => 'read_only',
    'handler'     => 'handle_read_instance_file',
]);

registerTool('write_instance_file', [
    'name'        => 'write_instance_file',
    'description' => '写入或覆盖实例内的文本文件（如修改 eula.txt 为 eula=true、改 server.properties 配置）。path 为相对路径，content 为完整文件内容。会覆盖原文件。破坏性操作，需守护进程开启文件写访问。',
    'inputSchema' => [
        'type'       => 'object',
        'properties' => [
            'uuid'     => ['type' => 'string', 'description' => '实例 UUID'],
            'daemonId' => ['type' => 'string', 'description' => '实例所在节点 UUID'],
            'path'     => ['type' => 'string', 'description' => '相对文件路径，如 ./eula.txt'],
            'content'  => ['type' => 'string', 'description' => '要写入的完整文件内容'],
            'confirm'  => ['type' => 'boolean', 'description' => '破坏性操作确认：必须为 true 才会执行'],
        ],
        'required'   => ['uuid', 'daemonId', 'path', 'content', 'confirm'],
    ],
    'permission'     => 'admin_only',
    'requiresConfirm' => true,
    'handler'        => 'handle_write_instance_file',
]);

registerTool('delete_instance_file', [
    'name'        => 'delete_instance_file',
    'description' => '删除实例内的文件。path 为相对文件路径。破坏性操作，需守护进程开启文件写访问。',
    'inputSchema' => [
        'type'       => 'object',
        'properties' => [
            'uuid'     => ['type' => 'string', 'description' => '实例 UUID'],
            'daemonId' => ['type' => 'string', 'description' => '实例所在节点 UUID'],
            'path'     => ['type' => 'string', 'description' => '相对文件路径，如 ./old.log'],
            'confirm'  => ['type' => 'boolean', 'description' => '破坏性操作确认：必须为 true 才会执行'],
        ],
        'required'   => ['uuid', 'daemonId', 'path', 'confirm'],
    ],
    'permission'     => 'admin_only',
    'requiresConfirm' => true,
    'handler'        => 'handle_delete_instance_file',
]);
