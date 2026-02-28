// 全局变量 - 使用 window 对象避免重复声明
if (!window.hasOwnProperty('isLoggedIn')) {
    window.isLoggedIn = false;
    window.currentUser = null;
}

/**
 * main.js v3.5
 * - 支持论坛、状态、日志三个页面的独立Banner样式
 * - 优化hidePageLoader函数，修复子页面内容不显示的问题
 * - 同时支持多种banner类的动画触发
 */

// 移动端菜单切换
function toggleMobileMenu() {
    const navMenu = document.querySelector('.nav-menu');
    navMenu.classList.toggle('active');
}

// 格式化数字
function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

// 模拟实时更新在线人数
function updateOnlinePlayers() {
    const onlineElement = document.getElementById('online-players');
    if (onlineElement) {
        const current = parseInt(onlineElement.textContent);
        const change = Math.floor(Math.random() * 5) - 2; // -2 到 +2
        const newValue = Math.max(50, Math.min(200, current + change));
        onlineElement.textContent = newValue;
    }
}

// 每5秒更新一次在线人数
setInterval(updateOnlinePlayers, 5000);

// 检查登录状态
function checkLoginStatus() {
    const loginBtn = document.querySelector('.btn-login');
    if (loginBtn && window.isLoggedIn && window.currentUser) {
        loginBtn.textContent = window.currentUser.username;
        loginBtn.onclick = () => window.location.href = 'pages/profile.html';
    }
}

// 显示消息提示
function showMessage(message, type = 'info') {
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${type}`;
    messageDiv.textContent = message;

    // 使用fixed定位，直接弹出在页面中央
    messageDiv.style.position = 'fixed';
    messageDiv.style.top = '50%';
    messageDiv.style.left = '50%';
    messageDiv.style.transform = 'translate(-50%, -50%)';
    messageDiv.style.zIndex = '10000';
    messageDiv.style.boxShadow = '0 4px 20px rgba(0, 0, 0, 0.3)';
    messageDiv.style.animation = 'messagePopup 0.3s ease-out';

    document.body.appendChild(messageDiv);

    setTimeout(() => {
        messageDiv.style.animation = 'messageFadeOut 0.3s ease-out';
        setTimeout(() => {
            messageDiv.remove();
        }, 300);
    }, 3000);
}

// 显示Toast提示
function showToast(message, type = 'success') {
    // 移除已存在的toast
    const existingToast = document.querySelector('.toast');
    if (existingToast) {
        existingToast.remove();
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    
    const icon = type === 'success' ? '✅' : 'X';
    toast.innerHTML = `
        <span class="toast-icon">${icon}</span>
        <span class="toast-message">${message}</span>
    `;

    document.body.appendChild(toast);

    // 添加动画类
    requestAnimationFrame(() => {
        toast.classList.add('show');
    });

    // 3秒后移除
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => {
            if (toast.parentNode) {
                toast.remove();
            }
        }, 300);
    }, 3000);
}

// 复制服务器地址
function copyServerAddress() {
    const serverAddress = 'mc.eqmemory.cn';
    const copyBtn = document.getElementById('copyBtn');
    
    if (!copyBtn) {
        console.error('复制按钮未找到');
        return;
    }
    
    const copyText = copyBtn.querySelector('.copy-text');
    if (!copyText) {
        console.error('复制文本元素未找到');
        return;
    }

    // 使用现代的 Clipboard API
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(serverAddress).then(() => {
            showToast('服务器地址已复制到剪贴板！', 'success');
            showCopySuccess(copyBtn, copyText);
        }).catch(err => {
            console.error('Clipboard API 失败:', err);
            // 如果现代API失败，使用fallback方法
            fallbackCopyText(serverAddress, copyBtn, copyText);
        });
    } else {
        // 使用fallback方法
        fallbackCopyText(serverAddress, copyBtn, copyText);
    }
}

// Fallback复制方法
function fallbackCopyText(text, btn, textElement) {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.left = '-9999px';
    textArea.style.top = '0';
    document.body.appendChild(textArea);
    textArea.select();

    try {
        const successful = document.execCommand('copy');
        if (successful) {
            showToast('服务器地址已复制到剪贴板！', 'success');
            showCopySuccess(btn, textElement);
        } else {
            showToast('复制失败，请手动复制', 'error');
            textElement.textContent = '复制失败';
        }
    } catch (err) {
        console.error('复制失败:', err);
        showToast('复制失败，请手动复制', 'error');
        textElement.textContent = '复制失败';
    }

    document.body.removeChild(textArea);
}

// 显示复制成功
function showCopySuccess(btn, textElement) {
    btn.classList.add('copied');
    textElement.textContent = '已复制';

    setTimeout(() => {
        btn.classList.remove('copied');
        textElement.textContent = '复制地址';
    }, 2000);
}

// 模态框控制
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('show');
        setTimeout(() => {
            modal.classList.add('active');
        }, 10);
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        modal.classList.add('hide');
        setTimeout(() => {
            modal.classList.remove('show', 'hide');
        }, 300);
    }
}

// 关闭模态框点击背景
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal')) {
        const modalId = e.target.id;
        closeModal(modalId);
    }
});

// 标签页切换
function switchTab(tabId) {
    // 移除所有active类
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
    });

    // 添加active到当前标签
    document.querySelector(`[data-tab="${tabId}"]`).classList.add('active');
    document.getElementById(tabId).classList.add('active');
}

// 模拟API请求
async function mockApiCall(url, method = 'GET', data = null) {
    // 模拟网络延迟
    await new Promise(resolve => setTimeout(resolve, 1000));

    // 模拟响应
    if (url.includes('/api/status')) {
        return {
            success: true,
            data: {
                online: true,
                players: 128,
                maxPlayers: 200,
                version: '1.21.3',
                tps: 19.8,
                latency: 45
            }
        };
    }

    if (url.includes('/api/login') && method === 'POST') {
        if (data.username === 'admin' && data.password === 'admin123') {
            return {
                success: true,
                data: {
                    token: 'mock_token_12345',
                    user: {
                        id: 1,
                        username: 'admin',
                        role: 'admin'
                    }
                }
            };
        }
        return {
            success: false,
            message: '用户名或密码错误'
        };
    }

    if (url.includes('/api/register') && method === 'POST') {
        return {
            success: true,
            message: '注册成功，请登录'
        };
    }

    return {
        success: false,
        message: 'API未实现'
    };
}

// 页面加载完成后执行
document.addEventListener('DOMContentLoaded', () => {
    // 检查登录状态
    const storedUser = localStorage.getItem('currentUser');
    if (storedUser && storedUser !== 'undefined' && storedUser !== 'null') {
        try {
            currentUser = JSON.parse(storedUser);
            isLoggedIn = true;
            checkLoginStatus();
        } catch (e) {
            console.error('解析用户数据失败:', e);
            localStorage.removeItem('currentUser');
        }
    }

    // 如果是特定页面，执行特定逻辑
    const currentPage = window.location.pathname;

    if (currentPage.includes('status.html')) {
        initStatusPage();
    }

    if (currentPage.includes('payment.html')) {
        initPaymentPage();
    }

    if (currentPage.includes('forum.html')) {
        initForumPage();
    }

    // 初始化弹幕功能（仅主页）
    if (currentPage.endsWith('index.html') || currentPage.endsWith('/')) {
        initDanmaku();
    }

    // 初始化滚动触发动画
    initScrollAnimations();

    // 隐藏页面加载动画
    hidePageLoader();
});

// 初始化滚动触发动画
function initScrollAnimations() {
    // 使用IntersectionObserver检测元素是否进入视口
    const observerOptions = {
        root: null,
        rootMargin: '0px',
        threshold: 0.1 // 元素出现10%时触发
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                // 动画播放后可以停止观察
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    // 观察所有带有动画类的元素
    const animatedElements = document.querySelectorAll('.fade-in, .slide-up, .scale-in');
    animatedElements.forEach(element => {
        observer.observe(element);
    });
}

// 隐藏页面加载动画
function hidePageLoader() {
    const pageLoader = document.getElementById('pageLoader');
    const heroContent = document.querySelector('.hero-content');
    const subpageBannerContent = document.querySelector('.subpage-banner-content');
    const forumBannerContent = document.querySelector('.forum-banner-content');
    const statusBannerContent = document.querySelector('.status-banner-content');
    const logsBannerContent = document.querySelector('.logs-banner-content');

    // 检查是否是首页（更健壮的判断）
    const pathname = window.location.pathname;
    const isHomePage = pathname.endsWith('index.html') ||
                       pathname.endsWith('/') ||
                       pathname === '/' ||
                       pathname === '' ||
                       pathname.includes('/index.html');

    // 如果不是首页，立即显示所有内容
    if (!isHomePage) {
        // 立即触发所有子页面Banner内容显示（无延迟）
        const allBannerContents = [
            subpageBannerContent,
            forumBannerContent,
            statusBannerContent,
            logsBannerContent
        ];

        allBannerContents.forEach(banner => {
            if (banner) {
                banner.classList.add('animate-in');
                banner.style.transition = 'opacity 0.3s ease-out, transform 0.3s ease-out';
            }
        });

        // 立即显示所有带有动画类的元素
        const animatedElements = document.querySelectorAll('.fade-in, .slide-up, .scale-in');
        animatedElements.forEach(element => {
            element.classList.add('visible');
        });

        // 隐藏页面加载器（如果存在）
        if (pageLoader) {
            pageLoader.style.display = 'none';
            pageLoader.classList.add('hidden');
        }
        return;
    }

    // 首页显示加载动画2秒后淡出
    setTimeout(() => {
        // 先淡出
        pageLoader.classList.add('fade-out');

        // 触发Hero区域动画
        if (heroContent) {
            heroContent.classList.add('animate-in');
        }

        // 淡出完成后完全移除
        setTimeout(() => {
            pageLoader.style.display = 'none';
            pageLoader.classList.add('hidden');
        }, 500);
    }, 2000);
}

// 初始化状态页
function initStatusPage() {
    const statusContainer = document.getElementById('server-status');
    if (statusContainer) {
        statusContainer.innerHTML = '<div class="loading"></div> 数据加载中...';

        mockApiCall('/api/status').then(response => {
            if (response.success) {
                const data = response.data;
                statusContainer.innerHTML = `
                    <div class="status-header">
                        <div class="status-badge ${data.online ? 'online' : 'offline'}">
                            ${data.online ? '在线' : '离线'}
                        </div>
                        <div class="server-info">
                            <span>版本: ${data.version}</span>
                            <span>TPS: ${data.tps}</span>
                            <span>延迟: ${data.latency}ms</span>
                        </div>
                    </div>
                    <div class="status-players">
                        <span class="stat-value">${data.players}</span>
                        <span class="stat-label">/ ${data.maxPlayers} 玩家在线</span>
                    </div>
                `;
            } else {
                statusContainer.innerHTML = '<div class="message error">无法获取服务器状态</div>';
            }
        });
    }
}

// 初始化充值页
function initPaymentPage() {
    const paymentMethods = document.querySelectorAll('.payment-method');
    paymentMethods.forEach(method => {
        method.addEventListener('click', () => {
            paymentMethods.forEach(m => m.classList.remove('selected'));
            method.classList.add('selected');
        });
    });
}

// 初始化论坛页
function initForumPage() {
    // 论坛标签页切换
    const tabs = document.querySelectorAll('.forum-tab');
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            // 加载对应版块的内容
            loadForumPosts(tab.dataset.forum);
        });
    });
}

// 加载论坛帖子
function loadForumPosts(forumId) {
    const postsContainer = document.getElementById('forum-posts');
    if (postsContainer) {
        postsContainer.innerHTML = '<div class="loading"></div> 加载中...';

        // 模拟加载延迟
        setTimeout(() => {
            const posts = [
                {
                    id: 1,
                    title: '新人报到，求组队',
                    author: '新手玩家',
                    replies: 15,
                    views: 256,
                    time: '2小时前'
                },
                {
                    id: 2,
                    title: '分享我的城堡建造过程',
                    author: '建筑师小明',
                    replies: 32,
                    views: 512,
                    time: '5小时前'
                },
                {
                    id: 3,
                    title: '建议增加更多活动',
                    author: '老玩家',
                    replies: 8,
                    views: 128,
                    time: '1天前'
                }
            ];

            let html = '';
            posts.forEach(post => {
                html += `
                    <div class="forum-post">
                        <div class="post-title">
                            <a href="pages/post-detail.html?id=${post.id}">${post.title}</a>
                        </div>
                        <div class="post-meta">
                            <span>作者: ${post.author}</span>
                            <span>回复: ${post.replies}</span>
                            <span>浏览: ${post.views}</span>
                            <span>${post.time}</span>
                        </div>
                    </div>
                `;
            });

            postsContainer.innerHTML = html;
        }, 500);
    }
}

// 创建新帖子
function createPost(title, content, forumId) {
    // 这里应该发送到后端API
    console.log('创建帖子:', { title, content, forumId });
    showMessage('帖子发布成功', 'success');
}

// 提交回复
function submitReply(postId, content) {
    // 这里应该发送到后端API
    console.log('提交回复:', { postId, content });
    showMessage('回复成功', 'success');
}

// 处理支付
function processPayment(method, packageId) {
    // 这里应该调用支付接口
    console.log('处理支付:', { method, packageId });
    showMessage('正在跳转到支付页面...', 'info');
}

// 导出函数供其他页面使用
window.toggleMobileMenu = toggleMobileMenu;
window.switchTab = switchTab;
window.openModal = openModal;
window.closeModal = closeModal;
window.copyServerAddress = copyServerAddress;
window.createPost = createPost;
window.submitReply = submitReply;
window.processPayment = processPayment;

// 初始化代码块复制功能
function initCodeBlockCopy() {
    const codeBlocks = document.querySelectorAll('.post-content pre, .reply-content pre');
    
    codeBlocks.forEach(block => {
        // 检查是否已经添加了复制按钮
        if (!block.querySelector('.copy-btn')) {
            // 创建复制按钮
            const copyBtn = document.createElement('button');
            copyBtn.className = 'copy-btn';
            copyBtn.innerHTML = '<i class="fa-regular fa-copy"></i>';
            
            // 添加到代码块
            block.appendChild(copyBtn);
            
            // 添加点击事件
            copyBtn.addEventListener('click', () => {
                const codeElement = block.querySelector('code');
                if (codeElement) {
                    const codeText = codeElement.textContent;
                    
                    // 使用现代的 Clipboard API
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(codeText).then(() => {
                            showCodeCopySuccess(copyBtn);
                        }).catch(err => {
                            console.error('Clipboard API 失败:', err);
                            // 如果现代API失败，使用fallback方法
                            fallbackCopyCode(codeText, copyBtn);
                        });
                    } else {
                        // 使用fallback方法
                        fallbackCopyCode(codeText, copyBtn);
                    }
                }
            });
        }
    });
}

// Fallback复制代码方法
function fallbackCopyCode(text, btn) {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.left = '-9999px';
    textArea.style.top = '0';
    document.body.appendChild(textArea);
    textArea.select();

    try {
        const successful = document.execCommand('copy');
        if (successful) {
            showCodeCopySuccess(btn);
        } else {
            console.error('复制失败');
        }
    } catch (err) {
        console.error('复制失败:', err);
    }

    document.body.removeChild(textArea);
}

// 显示复制代码成功
function showCodeCopySuccess(btn) {
    btn.classList.add('copied');
    btn.innerHTML = '<i class="fa-solid fa-check"></i>';

    setTimeout(() => {
        btn.classList.remove('copied');
        btn.innerHTML = '<i class="fa-regular fa-copy"></i>';
    }, 2000);
}

// 弹幕功能
function initDanmaku() {
    const container = document.getElementById('danmakuContainer');
    if (!container) return;

    // 弹幕配置
    const config = {
        // 开关控制：true = 开启，false = 关闭
        enabled: false,
        // 弹幕文本列表
        messages: [
            '欢迎来到万驹同源服务器！',
            '服务器地址：mc.eqmemory.cn',
            '推荐版本：1.20.1',
            '快来加入我们的QQ群：569208814',
            '生存游戏等你来挑战！',
            '自由创造，发挥你的想象力',
            '太空服测试中，敬请期待',
            'RPG服即将上线',
            '小游戏服开发中',
            '优质网络，稳定运行',
            '双路志强，超强性能',
            '安全稳定，放心游玩',
            '公益服务器，完全免费',
            '友好社区，和谐氛围',
            '万驹同源欢迎你！'
        ],
        // 弹幕颜色列表
        colors: ['#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7', '#DDA0DD', '#98D8C8', '#F7DC6F'],
        // 弹幕生成间隔（毫秒）
        interval: 1500,
        // 初始弹幕数量
        initialCount: 5,
        // 启动延迟（毫秒）
        startDelay: 5500
    };

    // 如果弹幕被禁用，直接返回
    if (!config.enabled) {
        container.classList.add('hidden');
        return;
    }

    let danmakuInterval;

    function createDanmaku() {
        const containerHeight = container.offsetHeight;
        const containerWidth = container.offsetWidth;
        
        const text = config.messages[Math.floor(Math.random() * config.messages.length)];
        const danmaku = document.createElement('div');
        danmaku.className = 'danmaku-item';
        danmaku.textContent = text;

        const topPosition = Math.random() * (containerHeight - 50);
        const duration = 8 + Math.random() * 8;
        const fontSize = 14 + Math.random() * 4;
        const color = config.colors[Math.floor(Math.random() * config.colors.length)];

        danmaku.style.top = `${topPosition}px`;
        danmaku.style.animationDuration = `${duration}s`;
        danmaku.style.fontSize = `${fontSize}px`;
        danmaku.style.color = color;

        container.appendChild(danmaku);

        danmaku.addEventListener('animationend', () => {
            danmaku.remove();
        });
    }

    function startDanmaku() {
        if (danmakuInterval) return;
        
        for (let i = 0; i < config.initialCount; i++) {
            setTimeout(createDanmaku, i * 300);
        }
        
        danmakuInterval = setInterval(createDanmaku, config.interval);
    }

    // 启动弹幕
    setTimeout(startDanmaku, config.startDelay);

    window.addEventListener('resize', () => {
        const containerWidth = container.offsetWidth;
    });
}

// 页面加载完成后初始化
document.addEventListener('DOMContentLoaded', function() {
    // 初始化状态页（如果存在）
    if (typeof initStatusPage === 'function') {
        initStatusPage();
    }
    
    // 初始化代码块复制功能
    initCodeBlockCopy();
});
