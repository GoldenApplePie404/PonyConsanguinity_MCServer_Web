// 组件加载器
// 获取当前页面的基础路径
function getBasePath() {
    const path = window.location.pathname;
    if (path.includes('/pages/')) {
        return '../';
    }
    return '';
}

const basePath = getBasePath();

const components = {
    // 侧边栏音乐播放器
    sidebarPlayer: {
        selector: '#app-sidebar-player',
        template: basePath + 'components/sidebar-player.html?v=3.0',
        callback: () => {
            initSidebarPlayer();
        }
    },
    // 回到顶部按钮
    backToTop: {
        selector: '#app-back-to-top',
        template: basePath + 'components/back-to-top.html?v=1.0',
        callback: () => {
            initBackToTop();
        }
    },
    // 导航栏
    navbar: {
        selector: '#app-navbar',
        template: basePath + 'components/navbar.html?v=1.0',
        callback: () => {
            if (typeof window.initNavbar === 'function') {
                window.initNavbar();
            } else {
                // 如果initNavbar函数还不存在，等待一段时间后重试
                setTimeout(() => {
                    if (typeof window.initNavbar === 'function') {
                        window.initNavbar();
                    } else {
                        console.warn('initNavbar函数未找到，导航栏滚动效果可能无法正常工作');
                    }
                }, 100);
            }
        }
    },
    // 页脚
    footer: {
        selector: '#app-footer',
        template: basePath + 'components/footer.html?v=1.0',
        callback: () => {
        }
    }
};

// 加载组件
function loadComponent(config) {
    const container = document.querySelector(config.selector);

    if (!container) {
        console.warn(`组件容器 ${config.selector} 未找到`);
        return;
    }

    fetch(config.template)
        .then(response => {
            if (!response.ok) {
                throw new Error(`加载组件失败: ${response.status}`);
            }
            return response.text();
        })
        .then(html => {
            // 替换BASE_PATH占位符
            html = html.replace(/\{\{BASE_PATH\}\}/g, basePath);

            container.innerHTML = html;

            // 使用双重延迟确保DOM完全更新
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    if (config.callback && typeof config.callback === 'function') {
                        config.callback();
                    }
                });
            });
        })
        .catch(error => {
            console.error('组件加载错误:', error);
        });
}

// 初始化所有组件
function initComponents() {
    const body = document.body;
    const componentsToLoad = body.getAttribute('data-components');

    if (componentsToLoad) {
        const componentNames = componentsToLoad.split(',');

        componentNames.forEach(name => {
            const componentName = name.trim();
            if (components[componentName]) {
                loadComponent(components[componentName]);
            }
        });
    }
}

// 页面加载完成后初始化组件
function safeInitComponents() {
    // 确保DOM完全加载
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initComponents);
    } else {
        // 等待一段时间，确保所有脚本都已加载
        setTimeout(initComponents, 200);
    }
}

// 安全初始化组件
safeInitComponents();
