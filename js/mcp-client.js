/**
 * MCP Client — 浏览器端 MCP 协议客户端
 *
 * 封装 JSON-RPC 2.0 通信，自动处理：
 *  - initialize 握手（带 session token 认证）
 *  - tools/list 工具发现
 *  - tools/call 工具调用
 *  - session 缓存（同一页面无需重复握手）
 *
 * 使用示例：
 *   const mcp = new MCPClient('/mcp/mcp-server.php');
 *   await mcp.connect();
 *   const tools = await mcp.listTools();
 *   const result = await mcp.callTool('get_dashboard', {});
 */
class MCPClient {

    /**
     * @param {string} endpoint  MCP Server URL
     * @param {string} [token]   可选的 session token，默认从 localStorage 取
     */
    constructor(endpoint, token) {
        this.endpoint = endpoint;
        this.token = token || localStorage.getItem('authToken') || '';
        this.connected = false;
        this.requestId = 1;
    }

    /**
     * 连接到 MCP Server（initialize 握手）
     */
    async connect() {
        const result = await this._call('initialize', {
            protocolVersion: '2025-03-26',
            auth_token: this.token,
        });
        this.connected = true;
        this.serverInfo = result.serverInfo || {};
        this.capabilities = result.capabilities || {};
        return result;
    }

    /**
     * 获取可用工具列表
     * @returns {Promise<Array>} 工具定义数组
     */
    async listTools() {
        const result = await this._call('tools/list', {});
        this.tools = result.tools || [];
        return this.tools;
    }

    /**
     * 调用指定工具
     * @param {string} name  工具名称
     * @param {object} args  工具参数
     * @returns {Promise<object>} 工具返回结果
     */
    async callTool(name, args = {}) {
        const result = await this._call('tools/call', {
            name: name,
            arguments: args,
        });

        // 提取文本内容
        if (result.content && Array.isArray(result.content)) {
            const textParts = result.content
                .filter(c => c.type === 'text')
                .map(c => c.text);
            return {
                success: true,
                data: textParts.join('\n'),
                raw: result,
            };
        }

        return {
            success: true,
            data: JSON.stringify(result),
            raw: result,
        };
    }

    /**
     * 底层 JSON-RPC 调用
     */
    async _call(method, params = {}) {
        const id = this.requestId++;

        const response = await fetch(this.endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + this.token,
            },
            body: JSON.stringify({
                jsonrpc: '2.0',
                id: id,
                method: method,
                params: params,
            }),
        });

        const data = await response.json();

        if (data.error) {
            throw new Error(data.error.message || 'MCP 请求失败');
        }

        return data.result || {};
    }
}
