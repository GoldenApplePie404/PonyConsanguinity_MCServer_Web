/**
 * MCSManager API 配置和调用模块
 * 基于 MCSManager 10.x API 文档
 */

// MCSManager API 配置
const MCSM_CONFIG = {
    // MCSManager 面板地址 - 请替换为您的实际地址
    panelUrl: 'https://mcpanel.eqmemory.cn/mcs/',
    // API Key - 请替换为您的实际 API Key
    apiKey: '1c02a955c9314814ae1fc0b8419c41fb',
    // 是否启用调试模式
    debug: true
};

// 日志输出函数
function log(...args) {
    if (MCSM_CONFIG.debug) {
        console.log('[MCSM API]', ...args);
    }
}

// 错误处理函数
function handleError(error) {
    console.error('[MCSM API Error]', error);
    throw error;
}

/**
 * 构建 API 请求 URL
 * @param {string} endpoint - API 端点
 * @param {Object} params - 查询参数
 * @returns {string} 完整的 API URL
 */
function buildApiUrl(endpoint, params = {}) {
    const url = new URL(`${MCSM_CONFIG.panelUrl}${endpoint}`);
    url.searchParams.append('apikey', MCSM_CONFIG.apiKey);
    
    Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null) {
            url.searchParams.append(key, value);
        }
    });
    
    return url.toString();
}

/**
 * 通用 API 请求函数
 * @param {string} endpoint - API 端点
 * @param {Object} options - 请求选项
 * @returns {Promise<Object>} API 响应数据
 */
async function apiRequest(endpoint, options = {}) {
    const defaultOptions = {
        headers: {
            'Content-Type': 'application/json; charset=utf-8',
            'X-Requested-With': 'XMLHttpRequest'
        },
        mode: 'cors'
    };

    const requestOptions = { ...defaultOptions, ...options };

    try {
        log('发起请求:', endpoint, requestOptions);
        
        const response = await fetch(buildApiUrl(endpoint), requestOptions);
        const data = await response.json();

        log('收到响应:', data);

        // 检查响应状态
        if (data.status !== 200) {
            throw new Error(`API 请求失败: 状态码 ${data.status}`);
        }

        return data;
    } catch (error) {
        handleError(error);
    }
}

// ============ Dashboard API ============

/**
 * 获取面板概览数据
 * 包含版本、系统信息、节点列表、实例统计等
 * @returns {Promise<Object>} 概览数据
 */
async function getOverview() {
    return apiRequest('/api/overview');
}

// ============ Daemon API ============

/**
 * 获取守护进程列表
 * 注意：数据包含在 /api/overview 的 remote 字段中
 * @returns {Promise<Array>} 守护进程列表
 */
async function getDaemonList() {
    const overview = await getOverview();
    return overview.data.remote || [];
}

// ============ Instance API ============

/**
 * 获取所有实例列表
 * @param {string} daemonId - 守护进程 ID
 * @param {number} page - 页码
 * @param {number} pageSize - 每页数量
 * @returns {Promise<Object>} 实例列表数据
 */
async function getInstanceList(daemonId, page = 1, pageSize = 20) {
    return apiRequest('/api/service/remote_service_instances', {
        params: { daemonId, page, page_size: pageSize, status: -1 }
    });
}

/**
 * 获取实例详情
 * @param {string} uuid - 实例 ID
 * @param {string} daemonId - 守护进程 ID
 * @returns {Promise<Object>} 实例详情
 */
async function getInstanceDetail(uuid, daemonId) {
    return apiRequest('/api/instance', {
        params: { uuid, daemonId }
    });
}

// ============ 数据处理工具函数 ============

/**
 * 格式化字节大小
 * @param {number} bytes - 字节数
 * @returns {string} 格式化后的字符串
 */
function formatBytes(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

/**
 * 格式化百分比
 * @param {number} value - 数值（0-1）
 * @returns {string} 百分比字符串
 */
function formatPercent(value) {
    return Math.round(value * 100) + '%';
}

/**
 * 格式化运行时间
 * @param {number} seconds - 秒数
 * @returns {string} 格式化后的时间字符串
 */
function formatUptime(seconds) {
    const days = Math.floor(seconds / 86400);
    const hours = Math.floor((seconds % 86400) / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);

    if (days > 0) {
        return `${days}天 ${hours}小时`;
    } else if (hours > 0) {
        return `${hours}小时 ${minutes}分钟`;
    } else {
        return `${minutes}分钟`;
    }
}

/**
 * 获取实例状态文本
 * @param {number} status - 状态值
 * @returns {string} 状态文本
 */
function getInstanceStatusText(status) {
    const statusMap = {
        '-1': '忙碌',
        '0': '已停止',
        '1': '正在停止',
        '2': '正在启动',
        '3': '运行中'
    };
    return statusMap[status.toString()] || '未知';
}

/**
 * 获取实例状态样式类
 * @param {number} status - 状态值
 * @returns {string} CSS 类名
 */
function getInstanceStatusClass(status) {
    const classMap = {
        '-1': 'status-busy',
        '0': 'status-offline',
        '1': 'status-stopping',
        '2': 'status-starting',
        '3': 'status-online'
    };
    return classMap[status.toString()] || 'status-unknown';
}

/**
 * 统计所有节点的实例总数和运行数
 * @param {Array} daemons - 守护进程列表
 * @returns {Object} 统计数据
 */
function getDaemonStatistics(daemons) {
    let totalInstances = 0;
    let runningInstances = 0;
    let availableDaemons = 0;

    daemons.forEach(daemon => {
        if (daemon.instance) {
            totalInstances += daemon.instance.total || 0;
            runningInstances += daemon.instance.running || 0;
        }
        if (daemon.available) {
            availableDaemons++;
        }
    });

    return {
        totalDaemons: daemons.length,
        availableDaemons,
        totalInstances,
        runningInstances,
        stoppedInstances: totalInstances - runningInstances
    };
}

// 导出配置和函数（如果使用模块化）
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        MCSM_CONFIG,
        getOverview,
        getDaemonList,
        getInstanceList,
        getInstanceDetail,
        formatBytes,
        formatPercent,
        formatUptime,
        getInstanceStatusText,
        getInstanceStatusClass,
        getDaemonStatistics
    };
}
