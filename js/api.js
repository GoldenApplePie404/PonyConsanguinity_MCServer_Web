/**
 * API 客户端
 * 用于与 PHP 后端 API 服务器通信
 */

// 使用统一配置文件
if (typeof API_CONFIG === 'undefined') {
    console.warn('配置文件未加载，使用默认配置');
    window.API_CONFIG = {
        baseUrl: '/api',
        fullUrl: window.location.origin
    };
    window.APP_CONFIG = {
        apiTimeout: 3000
    };
}

// API 基础地址
const API_BASE_URL = API_CONFIG.baseUrl;

// 是否使用 mock 模式（当 API 不可用时自动启用）
let USE_MOCK_MODE = false;

// 检测 API 可用性
async function checkApiAvailability() {
    try {
        const response = await fetch(`${API_BASE_URL}/health.php`, {
            method: 'GET',
            signal: AbortSignal.timeout(APP_CONFIG.apiTimeout)
        });
        USE_MOCK_MODE = !response.ok;
        console.log('API 可用性检测:', USE_MOCK_MODE ? '使用 mock 模式' : '使用真实 API');
    } catch (error) {
        console.warn('API 不可用，启用 mock 模式', error);
        USE_MOCK_MODE = true;
    }
}

// 强制使用 mock 模式
USE_MOCK_MODE = false;
console.log('使用真实 API 模式，因为系统中已安装 PHP');


// 初始化时检查 API 可用性
if (!USE_MOCK_MODE) {
    checkApiAvailability();
} else {
    console.log('在 mock 模式下跳过 API 可用性检查');
}

// 获取存储的 token
function getToken() {
    return localStorage.getItem('authToken');
}

// 设置 token
function setToken(token) {
    localStorage.setItem('authToken', token);
}

// 清除 token
function clearToken() {
    localStorage.removeItem('authToken');
}

// 获取当前用户
function getCurrentUser() {
    const user = localStorage.getItem('currentUser');
    if (user && user !== 'undefined' && user !== 'null') {
        try {
            return JSON.parse(user);
        } catch (e) {
            console.error('解析用户数据失败:', e);
            localStorage.removeItem('currentUser');
            return null;
        }
    }
    return null;
}

// 设置当前用户
function setCurrentUser(user) {
    localStorage.setItem('currentUser', JSON.stringify(user));
}

// 清除当前用户
function clearCurrentUser() {
    localStorage.removeItem('currentUser');
}

// 通用 API 请求函数
async function apiRequest(url, method = 'GET', data = null, requireAuth = false) {
    // Mock 模式：返回模拟数据
    if (USE_MOCK_MODE) {
        console.log('使用 Mock 模式处理请求:', url, method);
        return new Promise((resolve) => {
            setTimeout(() => {
                const mockResponse = getMockResponse(url, method, data);
                console.log('Mock 响应:', mockResponse);
                resolve(mockResponse);
            }, 300);
        });
    }

    console.log('使用真实 API 模式处理请求:', url, method);
    const headers = {
        'Content-Type': 'application/json'
    };

    // 如果需要认证，添加 token
    if (requireAuth) {
        const token = getToken();
        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }
    }

    const config = {
        method: method,
        headers: headers
    };

    if (data && (method === 'POST' || method === 'PUT')) {
        config.body = JSON.stringify(data);
    }

    try {
        const response = await fetch(`${API_BASE_URL}${url}`, config);
        console.log('API 响应状态:', response.status);
        
        // 检查响应是否为 JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            console.error('API 响应不是 JSON:', await response.text());
            return {
                success: false,
                message: '服务器返回了非 JSON 响应'
            };
        }
        
        const result = await response.json();
        console.log('API 响应结果:', result);

        if (!result.success) {
            return {
                success: false,
                message: result.message || '请求失败',
                status: response.status
            };
        }

        return result;
    } catch (error) {
        console.error('API 请求错误:', error);
        return {
            success: false,
            message: '网络错误，请检查连接'
        };
    }
}

// 获取 Mock 响应数据
function getMockResponse(url, method, data) {
    // 登录
    if (url.includes('login.php') && method === 'POST') {
        const username = data?.username || 'demo_user';
        // 尝试从users.json读取用户信息
        let userRole = 'user';
        try {
            // 模拟从users.json读取
            if (username === 'admin') {
                userRole = 'admin';
            }
        } catch (error) {
            console.log('无法读取users.json，使用默认角色');
        }
        return {
            success: true,
            message: '登录成功（演示模式）',
            token: 'mock_token_' + Date.now(),
            user: {
                id: 1,
                username: username,
                email: username + '@example.com',
                role: userRole
            }
        };
    }
    
    // 注册
    if (url.includes('register.php') && method === 'POST') {
        return {
            success: true,
            message: '注册成功（演示模式）',
            token: 'mock_token_' + Date.now(),
            user: {
                id: Date.now(),
                username: data?.username || 'new_user',
                email: data?.email || 'new_user@example.com'
            }
        };
    }
    
    // 登出
    if (url.includes('logout.php')) {
        return { success: true, message: '登出成功' };
    }
    
    // 用户信息
    if (url.includes('user_info.php')) {
        const user = getCurrentUser() || { username: 'demo_user', email: 'demo@example.com' };
        return {
            success: true,
            user: user
        };
    }
    
    // 帖子列表
    if (url.includes('posts.php')) {
        return {
            success: true,
            posts: [
                { id: 1, title: '欢迎来到万驹同源', content: '欢迎来到我们的服务器！', author: 'admin', time: '2024-01-20', views: 100 },
                { id: 2, title: '服务器规则', content: '请遵守以下规则...', author: 'admin', time: '2024-01-19', views: 50 }
            ]
        };
    }
    
    // 发帖
    if (url.includes('post.php') && method === 'POST') {
        return {
            success: true,
            message: '发布成功（演示模式）',
            post: {
                id: Date.now(),
                title: data?.title,
                content: data?.content,
                author: getCurrentUser()?.username || 'demo_user',
                time: new Date().toISOString().split('T')[0]
            }
        };
    }
    
    // 服务器状态
    if (url.includes('mcstatus.php')) {
        return {
            success: true,
            status: 'online',
            players: { online: 42, max: 100 },
            version: '1.20.1',
            motd: '欢迎来到万驹同源！'
        };
    }
    
    if (url.includes('system.php')) {
        return {
            success: true,
            cpu: 45,
            memory: 62,
            uptime: '5天12小时'
        };
    }
    
    // 通知列表
    if (url.includes('notification.php') && url.includes('action=list')) {
        return {
            success: true,
            message: '获取成功',
            data: {
                notifications: [
                    {
                        id: 1,
                        title: '欢迎加入服务器',
                        type: 'system',
                        content: '亲爱的玩家，欢迎加入我们的服务器！在这里你可以体验到最棒的游戏乐趣，结交更多朋友。',
                        created_at: '2026-01-30 10:00:00',
                        read: false
                    }
                ]
            }
        };
    }
    
    // 标记通知为已读
    if (url.includes('notification.php') && url.includes('action=mark_read')) {
        return {
            success: true,
            message: '标记成功'
        };
    }
    
    // 默认响应
    return {
        success: true,
        message: '操作成功（演示模式）'
    };
}

// ==================== 用户系统 API ====================

/**
 * 用户注册
 */
async function registerUser(username, email, password) {
    return await apiRequest('/register.php', 'POST', {
        username,
        email,
        password
    });
}

/**
 * 用户登录
 */
async function loginUser(username, password) {
    const response = await apiRequest('/login.php', 'POST', {
        username,
        password
    });

    if (response.success) {
        setToken(response.data.token);
        setCurrentUser(response.data.user);
    }

    return response;
}

/**
 * 用户登出
 */
async function logoutUser() {
    const response = await apiRequest('/logout.php', 'POST', {
        token: getToken()
    });
    clearToken();
    clearCurrentUser();
    return response;
}

/**
 * 获取当前用户信息
 */
async function getUserInfo() {
    return await apiRequest('/user_info.php', 'GET', null, true);
}

/**
 * 检查是否登录
 */
function isLoggedin() {
    return !!getToken();
}

// ==================== 论坛系统 API ====================

/**
 * 获取帖子列表
 */
async function getPosts() {
    return await apiRequest('/posts.php', 'GET');
}

/**
 * 获取单个帖子详情
 */
async function getPost(postId) {
    return await apiRequest(`/post.php?id=${postId}`, 'GET');
}

/**
 * 创建新帖子
 */
async function createPost(title, content, category = '普通讨论') {
    return await apiRequest('/posts.php', 'POST', {
        title,
        content,
        category
    }, true);
}

/**
 * 更新帖子
 */
async function updatePost(postId, title, content) {
    return await apiRequest(`/post.php?id=${postId}`, 'PUT', {
        title,
        content
    }, true);
}

/**
 * 删除帖子
 */
async function deletePostApi(postId) {
    return await apiRequest(`/post.php?id=${postId}`, 'DELETE', null, true);
}

/**
 * 检查是否是帖子作者
 */
function isPostAuthor(postAuthor) {
    const currentUser = getCurrentUser();
    return currentUser && currentUser.username === postAuthor;
}

// ==================== 通知系统 API ====================

/**
 * 获取通知列表
 */
async function getNotifications() {
    return await apiRequest('/notification.php?action=list', 'GET', null, true);
}

/**
 * 标记通知为已读
 */
async function markNotificationAsRead(notificationId) {
    return await apiRequest('/notification.php?action=mark_read', 'POST', {
        notification_id: notificationId
    }, true);
}

// ==================== 账户管理 API ====================

/**
 * 注销账户
 */
async function deleteAccount(password) {
    return await apiRequest('/delete_account.php', 'POST', {
        password: password
    }, true);
}

// 调试信息
console.log('=== api.js expose global functions ===');
console.log('getNotifications defined:', typeof getNotifications !== 'undefined');
console.log('markNotificationAsRead defined:', typeof markNotificationAsRead !== 'undefined');
console.log('apiRequest defined:', typeof apiRequest !== 'undefined');
console.log('============================');

// Expose global functions for HTML pages
if (typeof window !== 'undefined') {
    console.log('=== Expose global functions to window object ===');
    
    // Ensure all functions are properly exposed
    window.registerUser = registerUser;
    window.loginUser = loginUser;
    window.logoutUser = logoutUser;
    window.getUserInfo = getUserInfo;
    window.isLoggedin = isLoggedin;
    window.getToken = getToken;
    window.setToken = setToken;
    window.getCurrentUser = getCurrentUser;
    window.setCurrentUser = setCurrentUser;
    
    // Specifically ensure getNotifications function is exposed
    window.getNotifications = getNotifications;
    console.log('getNotifications exposed:', typeof window.getNotifications !== 'undefined');
    
    window.markNotificationAsRead = markNotificationAsRead;
    console.log('markNotificationAsRead exposed:', typeof window.markNotificationAsRead !== 'undefined');
    
    window.deleteAccount = deleteAccount;
    
    console.log('=== Expose completed ===');
}
