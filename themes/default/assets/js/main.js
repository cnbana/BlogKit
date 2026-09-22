// BlogKit 主脚本文件

// ===========================================
// 主题切换系统 (Dark Mode)
// 支持三模式：auto(跟随系统) / light(浅色) / dark(深色)
// ===========================================
(function initThemeSystem() {
    const THEME_KEY = 'blogkit_theme';
    const THEME_ATTR = 'data-theme';
    const STORAGE_KEY = 'blogkit_theme_mode'; // 用户主动选择的模式
    
    const themeToggle = document.getElementById('theme-toggle');
    if (!themeToggle) return;
    
    const iconDark = document.getElementById('theme-icon-dark');
    const iconLight = document.getElementById('theme-icon-light');
    const iconAuto = document.getElementById('theme-icon-auto');
    
    // 获取系统主题偏好
    function getSystemTheme() {
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }
    
    // 获取当前有效主题
    function getEffectiveTheme() {
        var savedMode = localStorage.getItem(STORAGE_KEY);
        if (savedMode === 'dark') return 'dark';
        if (savedMode === 'light') return 'light';
        return getSystemTheme();
    }
    
    // 设置主题
    function setTheme(theme) {
        if (theme === 'dark') {
            document.documentElement.setAttribute(THEME_ATTR, 'dark');
        } else {
            document.documentElement.removeAttribute(THEME_ATTR);
        }
    }
    
    // 更新切换按钮图标
    function updateToggleIcon() {
        var savedMode = localStorage.getItem(STORAGE_KEY) || 'auto';
        
        // 隐藏所有图标
        if (iconDark) iconDark.style.display = 'none';
        if (iconLight) iconLight.style.display = 'none';
        if (iconAuto) iconAuto.style.display = 'none';
        
        // 显示对应图标
        if (savedMode === 'dark' && iconLight) {
            iconLight.style.display = 'block';
        } else if (savedMode === 'light' && iconDark) {
            iconDark.style.display = 'block';
        } else if (iconAuto) {
            iconAuto.style.display = 'block';
        }
    }
    
    // 切换主题模式: auto -> light -> dark -> auto
    function toggleTheme() {
        var savedMode = localStorage.getItem(STORAGE_KEY) || 'auto';
        var nextMode;
        
        if (savedMode === 'auto') {
            nextMode = 'light';
        } else if (savedMode === 'light') {
            nextMode = 'dark';
        } else {
            nextMode = 'auto';
        }
        
        localStorage.setItem(STORAGE_KEY, nextMode);
        
        // 添加旋转动画
        themeToggle.classList.add('rotating');
        setTimeout(function() {
            themeToggle.classList.remove('rotating');
        }, 500);
        
        applyTheme();
        updateToggleIcon();
    }
    
    // 应用主题
    function applyTheme() {
        var savedMode = localStorage.getItem(STORAGE_KEY) || 'auto';
        if (savedMode === 'dark') {
            setTheme('dark');
        } else if (savedMode === 'light') {
            setTheme('light');
        } else {
            setTheme(getSystemTheme());
        }
    }
    
    // 应用主题（先于任何渲染，防止闪烁）
    applyTheme();
    updateToggleIcon();
    
    // 绑定切换按钮
    themeToggle.addEventListener('click', toggleTheme);
    
    // 监听系统主题变化
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
        var savedMode = localStorage.getItem(STORAGE_KEY);
        if (!savedMode || savedMode === 'auto') {
            setTheme(e.matches ? 'dark' : 'light');
            updateToggleIcon();
        }
    });
})();

// 页面加载完成后执行
document.addEventListener('DOMContentLoaded', function() {
    // 导航菜单高亮
    highlightNavigation();
    
    // 平滑滚动
    smoothScroll();
    
    // 初始化回到顶部按钮
    initBackToTop();
});

// 导航菜单高亮
function highlightNavigation() {
    const currentUrl = window.location.pathname;
    const navItems = document.querySelectorAll('nav ul.navbar-nav.mr-auto li.nav-item');
    
    // 移除所有nav-item的active类
    navItems.forEach(item => {
        item.classList.remove('active');
    });
    
    navItems.forEach(item => {
        const link = item.querySelector('a.nav-link');
        const linkUrl = new URL(link.href).pathname;
        
        if (linkUrl === currentUrl) {
            item.classList.add('active');
        }
        
        // 特别处理首页：如果当前是根路径，给首页添加active类
        if (currentUrl === '/' && linkUrl === '/') {
            item.classList.add('active');
        }
    });
}

// 平滑滚动
function smoothScroll() {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;
            
            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                targetElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
}



// 搜索功能
function searchArticles() {
    const searchInput = document.getElementById('searchInput');
    const searchTerm = searchInput.value.toLowerCase();
    const articles = document.querySelectorAll('article');
    
    articles.forEach(article => {
        const title = article.querySelector('h4').textContent.toLowerCase();
        const content = article.querySelector('p').textContent.toLowerCase();
        
        if (title.includes(searchTerm) || content.includes(searchTerm)) {
            article.style.display = 'block';
        } else {
            article.style.display = 'none';
        }
    });
}

// 页面加载进度
window.addEventListener('load', function() {
    const preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.style.display = 'none';
    }
});

// 回到顶部按钮功能
function initBackToTop() {
    const backToTopButton = document.getElementById('back-to-top');
    if (!backToTopButton) return;
    
    // 滚动监听
    window.addEventListener('scroll', function() {
        if (window.pageYOffset > 300) {
            backToTopButton.classList.add('show');
        } else {
            backToTopButton.classList.remove('show');
        }
    });
    
    // 点击事件
    backToTopButton.addEventListener('click', function() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
}

// 图片懒加载函数 (可重用)
window.initLazyLoad = function() {
    if (!('IntersectionObserver' in window)) {
        // 不支持 IntersectionObserver 则直接加载所有图片
        document.querySelectorAll('img[data-src]').forEach(function(img) {
            img.src = img.getAttribute('data-src');
            img.removeAttribute('data-src');
            img.classList.add('lazy-loaded');
            // 处理 <picture> 中的 <source> 标签
            var picture = img.closest('picture');
            if (picture) {
                picture.querySelectorAll('source[data-srcset]').forEach(function(source) {
                    source.srcset = source.getAttribute('data-srcset');
                    source.removeAttribute('data-srcset');
                });
            }
        });
        return;
    }
    
    var observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                var img = entry.target;
                var src = img.getAttribute('data-src');
                if (src) {
                    img.src = src;
                    img.removeAttribute('data-src');
                    img.classList.add('lazy-loaded');
                }
                // 处理 <picture> 中的 <source data-srcset>
                var picture = img.closest('picture');
                if (picture) {
                    picture.querySelectorAll('source[data-srcset]').forEach(function(source) {
                        source.srcset = source.getAttribute('data-srcset');
                        source.removeAttribute('data-srcset');
                    });
                }
                // 处理 img 自身的 data-srcset
                var srcset = img.getAttribute('data-srcset');
                if (srcset) {
                    img.srcset = srcset;
                    img.removeAttribute('data-srcset');
                }
                observer.unobserve(img);
            }
        });
    }, {
        rootMargin: '100px 0px', // 提前100px开始加载
        threshold: 0.01
    });
    
    // 观察所有带 data-src 的图片
    document.querySelectorAll('img[data-src]').forEach(function(img) {
        observer.observe(img);
    });
};

// 页面加载时初始化懒加载
document.addEventListener('DOMContentLoaded', window.initLazyLoad);
