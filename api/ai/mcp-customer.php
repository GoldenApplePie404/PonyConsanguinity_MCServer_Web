<?php
/**
 * 客服专属 MCP 入口（只读强制）
 * ============================================================
 * 本文件是「客服 AI」专用的 MCP 通道。它通过 MCP_PERSONA_FORCED 在
 * 服务端把人格强制锁定为 customer，使得 mcp-server.php 在 tools/list
 * 与 tools/call 中只放行只读工具白名单（见 config.php 的 AI_PERSONAS）。
 *
 * 安全意义：
 * - 即便管理员调用本入口，也会被降级为只读，绝不暴露 stop/start/restart/
 *   send_command 等写操作。
 * - 普通玩家调用本入口本就只能读，符合「客服仅开放相关只读功能」的诉求。
 *
 * 客服页（kefu.html）的 MCP Client 应指向本文件，而非 mcp/mcp-server.php。
 */

// 在引入 mcp-server.php 之前锁定人格，使其无法被客户端覆盖
define('MCP_PERSONA_FORCED', 'customer');

// 引入共享的 MCP 服务实现（其中包含完整的 JSON-RPC 分发与响应逻辑）
require_once __DIR__ . '/../../mcp/mcp-server.php';
