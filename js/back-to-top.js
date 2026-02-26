// 回到顶部功能
let isScrollListenerAdded = false;

// 初始化回到顶部组件
function initBackToTop() {
    const backToTopBtn = document.getElementById('back-to-top');
    if (!backToTopBtn) return;

    // 默认隐藏
    backToTopBtn.classList.add('hidden');

    // 如果还没有添加滚动监听器，则添加
    if (!isScrollListenerAdded) {
        window.addEventListener('scroll', handleScroll);
        isScrollListenerAdded = true;
    }

    // 初始检查
    handleScroll();
}

// 处理滚动事件
function handleScroll() {
    const backToTopBtn = document.getElementById('back-to-top');
    if (!backToTopBtn) return;

    // 滚动超过300px时显示按钮
    if (window.scrollY > 300) {
        backToTopBtn.classList.remove('hidden');
        backToTopBtn.classList.add('visible');
    } else {
        backToTopBtn.classList.remove('visible');
        backToTopBtn.classList.add('hidden');
    }
}

// 滚动到顶部
function scrollToTop() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}
