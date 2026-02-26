/**
 * 统一配置文件
 * 用于管理项目的全局配置，区分测试环境和生产环境
 */

// 环境检测
const ENV = {
    // 检测是否为本地开发环境
    isLocalhost: window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1',
    
    // 检测是否为生产环境
    isProduction: window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1'
};

// API 配置
const API_CONFIG = {
    // API 基础地址
    // 本地开发环境使用 localhost:8000，生产环境使用指定域名
    baseUrl: ENV.isLocalhost ? 'http://localhost:8000/api' : 'https://mcpc.goldenapplepie.xyz/api',
    
    // API 完整地址（用于某些需要完整 URL 的场景）
    fullUrl: ENV.isLocalhost ? 'http://localhost:8000' : 'https://mcpc.goldenapplepie.xyz/api'

};

// 页面路径配置
const PATH_CONFIG = {
    // 数据库查询页面
    dbTest: ENV.isLocalhost ? 'http://localhost:8000/pages/db_test.html' : 'https://mcpc.goldenapplepie.xyz/pages/db_test.html',
    
    // 公告管理页面
    announcementManager: ENV.isLocalhost ? 'https://localhost:8000/tools/announcement-manager.html' : 'http://mcpc.goldenapplepie.xyz/tools/announcement-manager.html',
    
    // 卫星地图服务器地址
    mapServer: ENV.isLocalhost ? 'http://localhost:11823' : 'https://mcpc.goldenapplepie.xyz/map'
};

// 应用配置
const APP_CONFIG = {
    // 应用名称
    appName: '万驹同源',
    
    // API 可用性检测超时时间（毫秒）
    apiTimeout: 3000,
    
    // 是否启用调试模式
    debugMode: ENV.isLocalhost
};

// 导出配置（兼容性）
if (typeof window !== 'undefined') {
    window.ENV = ENV;
    window.API_CONFIG = API_CONFIG;
    window.PATH_CONFIG = PATH_CONFIG;
    window.APP_CONFIG = APP_CONFIG;
    
    // 控制台输出当前环境信息
    console.log('=== 环境配置 ===');
    console.log('当前环境:', ENV.isLocalhost ? '本地开发环境' : '生产环境');
    console.log('API 基础地址:', API_CONFIG.baseUrl);
    console.log('完整 URL:', API_CONFIG.fullUrl);
    console.log('================');
}