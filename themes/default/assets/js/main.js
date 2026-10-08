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

    // 初始化密码显隐眼睛按钮（登录/注册/重置密码/个人设置页通用）
    initPasswordToggles();
});

// ===== 密码输入框显隐切换（眼睛图标）=====
// 兼容两种既有结构：.password-container > input + .toggle-password 按钮，
// 以及 .input-with-toggle > input + .toggle-visibility[data-target] 按钮。
// 图标为统一 eye / eye-invisible SVG（viewBox 64 64 896 896）。
function initPasswordToggles() {
    var EYE_ICON = '<svg viewBox="64 64 896 896" focusable="false" data-icon="eye" width="1em" height="1em" fill="currentColor" aria-hidden="true"><path d="M942.2 486.2C847.4 286.5 704.1 186 512 186c-192.2 0-335.4 100.5-430.2 300.3a60.3 60.3 0 000 51.5C176.6 737.5 319.9 838 512 838c192.2 0 335.4-100.5 430.2-300.3 7.7-16.2 7.7-35 0-51.5zM512 766c-161.3 0-279.4-81.8-362.7-254C232.6 339.8 350.7 258 512 258c161.3 0 279.4 81.8 362.7 254C791.5 684.2 673.4 766 512 766zm-4-430c-97.2 0-176 78.8-176 176s78.8 176 176 176 176-78.8 176-176-78.8-176-176-176zm0 288c-61.9 0-112-50.1-112-112s50.1-112 112-112 112 50.1 112 112-50.1 112-112 112z"></path></svg>';
    var EYE_INVISIBLE_ICON = '<svg viewBox="64 64 896 896" focusable="false" data-icon="eye-invisible" width="1em" height="1em" fill="currentColor" aria-hidden="true"><path d="M942.2 486.2Q889.47 375.11 816.7 305l-50.88 50.88C807.31 395.53 843.45 447.4 874.7 512 791.5 684.2 673.4 766 512 766q-72.67 0-133.87-22.38L323 798.75Q408 838 512 838q288.3 0 430.2-300.3a60.29 60.29 0 000-51.5zm-63.57-320.64L836 122.88a8 8 0 00-11.32 0L715.31 232.2Q624.86 186 512 186q-288.3 0-430.2 300.3a60.3 60.3 0 000 51.5q56.69 119.4 136.5 191.41L112.48 835a8 8 0 000 11.31L155.17 889a8 8 0 0011.31 0l712.15-712.12a8 8 0 000-11.32zM149.3 512C232.6 339.8 350.7 258 512 258c54.54 0 104.13 9.36 149.12 28.39l-70.3 70.3a176 176 0 00-238.13 238.13l-83.42 83.42C223.1 637.49 183.3 582.28 149.3 512zm246.7 0a112.11 112.11 0 01146.2-106.69L401.31 546.2A112 112 0 01396 512z"></path><path d="M508 624c-3.46 0-6.87-.16-10.25-.47l-52.82 52.82a176.09 176.09 0 00227.42-227.42l-52.82 52.82c.31 3.38.47 6.79.47 10.25a111.94 111.94 0 01-112 112z"></path></svg>';

    document.querySelectorAll('.toggle-password, .toggle-visibility').forEach(function(btn) {
        // 防止重复绑定
        if (btn.dataset.passwordToggleBound) {
            return;
        }
        btn.dataset.passwordToggleBound = '1';

        // 定位目标输入框：优先 data-target 指定 id，否则取同容器内的密码输入框
        var targetId = btn.getAttribute('data-target');
        var input = targetId
            ? document.getElementById(targetId)
            : (btn.closest('.password-container, .input-with-toggle, .form-group') || document).querySelector('input');
        if (!input) {
            return;
        }

        btn.addEventListener('click', function() {
            var showPassword = input.type === 'password';
            input.type = showPassword ? 'text' : 'password';
            btn.innerHTML = showPassword ? EYE_INVISIBLE_ICON : EYE_ICON;
            btn.title = showPassword ? '隐藏密码' : '显示密码';
            btn.setAttribute('aria-label', btn.title);
            // 切换后保持输入焦点不丢失
            input.focus();
        });

        // 初始 aria/title 统一为「显示密码」（图标已由模板内嵌 eye SVG）
        btn.title = '显示密码';
        btn.setAttribute('aria-label', '显示密码');
    });
}

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
