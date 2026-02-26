// 导航栏功能

// 初始化导航栏
function initNavbar() {
    // 自动高亮当前页面
    highlightCurrentPage();
    // 更新登录按钮状态
    updateLoginButton();
    // 初始化滚动效果
    initNavbarScrollEffect();
}

// 高亮当前页面的导航链接
function highlightCurrentPage() {
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('.nav-link');

    navLinks.forEach(link => {
        const href = link.getAttribute('href');

        // 移除active类
        link.classList.remove('active');

        // 判断是否是当前页面
        if (href === currentPath) {
            link.classList.add('active');
        }
        // 特殊处理首页（在根目录时）
        else if (currentPath === '/' || currentPath.endsWith('/index.html')) {
            if (href === 'index.html' || href === './index.html' || href === '../index.html') {
                link.classList.add('active');
            }
        }
    });
}

// 更新登录按钮状态
function updateLoginButton() {
    const loginBtn = document.querySelector('.btn-login');
    if (!loginBtn) return;

    // 检查是否有登录信息
    const currentUser = getCurrentUser();

    if (currentUser) {
        // 已登录状态：显示个人中心
        loginBtn.textContent = currentUser.username;
        loginBtn.setAttribute('href', '/pages/profile.html');
        loginBtn.onclick = (e) => {
            e.preventDefault();
            window.location.href = '/pages/profile.html';
        };
    } else {
        // 未登录状态：显示登录
        loginBtn.textContent = '登录';
        loginBtn.setAttribute('href', '/pages/login.html');
        loginBtn.onclick = null;
    }
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

// 移动端菜单切换
function toggleMobileMenu() {
    const navMenu = document.querySelector('.nav-menu');
    if (navMenu) {
        navMenu.classList.toggle('active');
    }
}

// 暴露函数到全局作用域
window.initNavbar = initNavbar;
window.highlightCurrentPage = highlightCurrentPage;
window.updateLoginButton = updateLoginButton;
window.toggleMobileMenu = toggleMobileMenu;
window.initNavbarScrollEffect = initNavbarScrollEffect;

// 确保在页面加载时更新登录按钮状态
window.addEventListener('DOMContentLoaded', () => {
    updateLoginButton();
    highlightCurrentPage();
});

// 初始化导航栏滚动效果
function initNavbarScrollEffect() {
    const navbar = document.querySelector('.navbar');
    if (!navbar) {
        return;
    }
    
    // 初始状态
    navbar.style.backgroundColor = 'rgba(111, 126, 189, 0.85)';
    navbar.style.backdropFilter = 'blur(2px)';
    
    // 监听滚动事件
    window.addEventListener('scroll', () => {
        const scrollTop = window.scrollY || document.documentElement.scrollTop;
        // 滚动时改变背景
        if (scrollTop > 100) {
            navbar.style.backgroundColor = 'rgba(111, 127, 189, 0.85)';
            navbar.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.15)';
            
        } else {
            navbar.style.backgroundColor = 'rgba(132, 145, 203, 0.3)';
            navbar.style.boxShadow = 'var(--shadow)';
        }
    }, { passive: true });
    
    // 初始化时检查滚动位置
    const initialScrollTop = window.scrollY || document.documentElement.scrollTop;
    if (initialScrollTop > 100) {
        navbar.style.backgroundColor = 'rgba(76, 91, 158, 0.85)';
        navbar.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.15)';
    }
}
