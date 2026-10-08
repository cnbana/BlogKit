class SidebarManager {
    constructor() {
        this.sidebar = document.querySelector('.sidebar');
        this.mainContent = document.querySelector('.main-content');
        this.sidebarMenu = this.sidebar?.querySelector('.sidebar-menu');
        this.sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        this.sidebarTheme = localStorage.getItem('sidebarTheme') || 'auto';
        this.expandedMenus = JSON.parse(localStorage.getItem('expandedMenus') || '[]');
        this.sidebarScrollTop = parseInt(localStorage.getItem('sidebarScrollTop') || '0');
        this.currentFocusedIndex = -1;
        this.menuLinks = [];
        this.systemThemeMediaQuery = null;
        this.currentPopupMenu = null;
        this.popupTimeout = null;
        this._hoveredMenuItem = null;
        this._hoveringPopup = false;
        
        this.themeIcons = {
            dark: '<svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>',
            light: '<svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>',
            auto: '<svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line><circle cx="12" cy="10" r="2"></circle><path d="M12 7v6"></path><path d="M9 10h6"></path></svg>'
        };
        
        this.init();
    }

    init() {
        this.restoreState();
        this.restoreExpandedMenus();
        this.bindEvents();
        this.updateThemeIcon();
        this.restoreScrollPosition();
        this.watchSystemTheme();
    }

    restoreState() {
        // 先清理初始化类，防止样式冲突
        // （theme-auto-init 仅用于首帧主题图标选中，图标随后被 updateThemeIcon
        //   覆盖为单一图标，此处一并清理避免残留类影响后续渲染）
        document.documentElement.classList.remove('sidebar-collapsed-init', 'theme-dark-init', 'theme-light-init', 'theme-auto-init');
        document.body.classList.remove('sidebar-collapsed-init', 'theme-dark-init', 'theme-light-init', 'theme-auto-init');

        // 恢复状态
        if (this.sidebarCollapsed) {
            this.sidebar.classList.add('collapsed');
            this.mainContent.classList.add('sidebar-collapsed');
        }

        this.applyTheme();
    }
    
    /**
     * 监听系统主题变化
     */
    watchSystemTheme() {
        if (window.matchMedia) {
            this.systemThemeMediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
            this.systemThemeMediaQuery.addEventListener('change', () => {
                if (this.sidebarTheme === 'auto') {
                    this.applyTheme();
                }
            });
        }
    }
    
    /**
     * 获取实际要使用的主题
     * @returns {string} 'light' 或 'dark'
     */
    getActualTheme() {
        if (this.sidebarTheme === 'auto') {
            return this.getSystemTheme();
        }
        return this.sidebarTheme;
    }
    
    /**
     * 获取系统主题
     * @returns {string} 'light' 或 'dark'
     */
    getSystemTheme() {
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return 'dark';
        }
        return 'light';
    }
    
    /**
     * 应用主题到页面
     */
    applyTheme() {
        const actualTheme = this.getActualTheme();
        if (actualTheme === 'dark') {
            this.sidebar.classList.add('dark-theme');
            document.body.classList.add('dark-theme');
        } else {
            this.sidebar.classList.remove('dark-theme');
            document.body.classList.remove('dark-theme');
        }
    }

    restoreExpandedMenus() {
        this.expandedMenus.forEach(menuId => {
            const menuItem = document.querySelector(`[data-menu-id="${menuId}"]`);
            if (menuItem) {
                const subMenu = menuItem.querySelector('.sub-menu');
                const toggle = menuItem.querySelector('a');
                if (subMenu && toggle) {
                    subMenu.classList.add('show');
                    toggle.classList.add('active');
                }
            }
        });
    }

    getVisibleLinks() {
        return Array.from(this.menuLinks).filter(link => {
            if (link.offsetParent === null) return false;
            const menuItem = link.closest('.menu-item');
            // 检查所有祖先 sub-menu 是否展开（支持三级嵌套）
            let current = menuItem;
            while (current) {
                const parentSubMenu = current.closest('.sub-menu');
                if (!parentSubMenu) break;
                if (!parentSubMenu.classList.contains('show')) return false;
                current = parentSubMenu.closest('.menu-item');
            }
            return true;
        });
    }

    bindEvents() {
        // 初始化折叠态弹出菜单
        this.initCollapsedSubmenuPopup();
        
        const toggleBtn = document.querySelector('.sidebar-toggle');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => this.toggleSidebar());
        }

        const themeToggleBtn = document.getElementById('themeToggleBtn');
        if (themeToggleBtn) {
            themeToggleBtn.addEventListener('click', () => this.toggleTheme());
        }

        const menuToggles = document.querySelectorAll('.menu-item.has-children > a');
        menuToggles.forEach(toggle => {
            toggle.addEventListener('click', (e) => {
                e.preventDefault();
                this.toggleSubmenu(toggle);
            });
        });

        if (this.sidebarMenu) {
            this.sidebarMenu.addEventListener('scroll', () => {
                localStorage.setItem('sidebarScrollTop', this.sidebarMenu.scrollTop.toString());
            });
        }

        this.menuLinks = Array.from(document.querySelectorAll('.menu-item a[href]'));
        this.menuLinks.forEach(link => {
            link.setAttribute('tabindex', '0');
            if (link.getAttribute('href') !== 'javascript:void(0);') {
                link.addEventListener('click', () => {
                    if (this.sidebarMenu) {
                        localStorage.setItem('sidebarScrollTop', this.sidebarMenu.scrollTop.toString());
                    }
                });
            }
        });

        document.addEventListener('keydown', (e) => this.handleGlobalKeyboard(e));

        const shortcutHintBtn = document.getElementById('shortcutHintBtn');
        if (shortcutHintBtn) {
            shortcutHintBtn.addEventListener('click', () => this.toggleShortcutModal());
        }

        const shortcutModalClose = document.getElementById('shortcutModalClose');
        if (shortcutModalClose) {
            shortcutModalClose.addEventListener('click', () => this.closeShortcutModal());
        }

        const shortcutModal = document.getElementById('shortcutModal');
        if (shortcutModal) {
            shortcutModal.addEventListener('click', (e) => {
                if (e.target === shortcutModal) {
                    this.closeShortcutModal();
                }
            });
        }
    }

    navigateMenu(direction) {
        const visibleLinks = this.getVisibleLinks();

        if (visibleLinks.length === 0) return;

        let newIndex;
        
        if (this.currentFocusedIndex === -1) {
            newIndex = 0;
        } else {
            const currentLink = this.getCurrentFocusedLink();
            if (!currentLink) {
                newIndex = 0;
            } else {
                const currentVisibleIndex = visibleLinks.indexOf(currentLink);
                if (currentVisibleIndex === -1) {
                    newIndex = 0;
                } else {
                    newIndex = currentVisibleIndex + direction;
                }
            }
        }

        if (newIndex < 0) {
            newIndex = visibleLinks.length - 1;
        } else if (newIndex >= visibleLinks.length) {
            newIndex = 0;
        }

        this.currentFocusedIndex = this.menuLinks.indexOf(visibleLinks[newIndex]);
        visibleLinks[newIndex].focus();

        visibleLinks[newIndex].scrollIntoView({
            block: 'nearest',
            behavior: 'smooth'
        });
    }

    getCurrentFocusedLink() {
        if (this.currentFocusedIndex >= 0 && this.currentFocusedIndex < this.menuLinks.length) {
            return this.menuLinks[this.currentFocusedIndex];
        }
        return null;
    }

    handleGlobalKeyboard(e) {
        // 检查当前焦点是否在编辑器或输入框中，如果是则跳过侧边栏键盘导航
        // （含自定义下拉 .ui-select：其面板有自己的方向键/Enter 导航，
        //   若不豁免，方向键会被侧边栏抢走、Enter 会误触发侧边栏链接跳转）
        const $focused = $(e.target);
        if ($focused.is('textarea, input, [contenteditable="true"], .note-editor, .CodeMirror, .tox-edit-area, .cke, .ui-select-menu, .ui-select-trigger')) {
            return;
        }

        // 检查父元素是否是编辑器容器
        if ($focused.closest('textarea, [contenteditable="true"], .note-editor, .CodeMirror, .tox-edit-area, .cke, .ui-select-menu').length > 0) {
            return;
        }
        
        // 检查命令面板是否打开，如果打开则跳过侧边栏键盘导航
        const commandPalette = document.getElementById('commandPalette');
        if (commandPalette && commandPalette.classList.contains('active')) {
            return;
        }

        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'd') {
            e.preventDefault();
            window.location.href = 'admin.php?action=dashboard';
        } else if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
        } else if (e.key === 'Escape') {
            const shortcutModal = document.getElementById('shortcutModal');
            if (shortcutModal && shortcutModal.classList.contains('show')) {
                this.closeShortcutModal();
            }
        } else if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === '/') {
            e.preventDefault();
            this.toggleShortcutModal();
        } else if (e.key === 'ArrowDown' || e.key === 'ArrowUp' || e.key === 'ArrowRight' || e.key === 'ArrowLeft' || e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            
            const visibleLinks = this.getVisibleLinks();

            if (visibleLinks.length === 0) return;

            if (this.currentFocusedIndex === -1) {
                this.currentFocusedIndex = this.menuLinks.indexOf(visibleLinks[0]);
                visibleLinks[0].focus();
                return;
            }

            const currentLink = this.getCurrentFocusedLink();
            if (!currentLink) return;

            if (e.key === 'ArrowDown') {
                this.navigateMenu(1);
            } else if (e.key === 'ArrowUp') {
                this.navigateMenu(-1);
            } else if (e.key === 'ArrowRight') {
                const menuItem = currentLink.closest('.menu-item');
                if (menuItem.classList.contains('has-children')) {
                    const subMenu = menuItem.querySelector('.sub-menu');
                    if (!subMenu.classList.contains('show')) {
                        this.toggleSubmenu(currentLink);
                    } else {
                        const firstChildLink = subMenu.querySelector('.menu-item a');
                        if (firstChildLink) {
                            firstChildLink.focus();
                            this.currentFocusedIndex = this.menuLinks.indexOf(firstChildLink);
                        }
                    }
                }
            } else if (e.key === 'ArrowLeft') {
                const menuItem = currentLink.closest('.menu-item');
                if (menuItem.classList.contains('has-children')) {
                    const subMenu = menuItem.querySelector('.sub-menu');
                    if (subMenu.classList.contains('show')) {
                        this.toggleSubmenu(currentLink);
                        return;
                    }
                }
                const parentSubMenu = menuItem.closest('.sub-menu');
                if (parentSubMenu) {
                    const parentToggle = parentSubMenu.closest('.menu-item').querySelector('a');
                    if (parentToggle) {
                        this.toggleSubmenu(parentToggle);
                        parentToggle.focus();
                        this.currentFocusedIndex = this.menuLinks.indexOf(parentToggle);
                    }
                }
            } else if (e.key === 'Enter' || e.key === ' ') {
                if (currentLink.getAttribute('href') === 'javascript:void(0);') {
                    this.toggleSubmenu(currentLink);
                } else {
                    currentLink.click();
                }
            }
        }
    }

    toggleShortcutModal() {
        const shortcutModal = document.getElementById('shortcutModal');
        if (shortcutModal) {
            shortcutModal.classList.toggle('show');
        }
    }

    closeShortcutModal() {
        const shortcutModal = document.getElementById('shortcutModal');
        if (shortcutModal) {
            shortcutModal.classList.remove('show');
        }
    }

    toggleSidebar() {
        // 切换前先关闭弹出菜单
        this.hideCollapsedSubmenu();
        
        this.sidebarCollapsed = !this.sidebarCollapsed;
        this.sidebar.classList.toggle('collapsed');
        this.mainContent.classList.toggle('sidebar-collapsed');
        localStorage.setItem('sidebarCollapsed', this.sidebarCollapsed);
    }

    toggleTheme() {
        // 在三种模式之间循环切换: auto → light → dark → auto
        if (this.sidebarTheme === 'auto') {
            this.sidebarTheme = 'light';
        } else if (this.sidebarTheme === 'light') {
            this.sidebarTheme = 'dark';
        } else {
            this.sidebarTheme = 'auto';
        }
        
        // 应用新主题
        this.applyTheme();
        localStorage.setItem('sidebarTheme', this.sidebarTheme);
        this.updateThemeIcon();
    }

    updateThemeIcon() {
        const themeIcon = document.getElementById('themeIcon');
        if (themeIcon) {
            themeIcon.innerHTML = this.themeIcons[this.sidebarTheme];
        }
    }

    toggleSubmenu(toggle) {
        const menuItem = toggle.closest('.menu-item');
        const subMenu = menuItem.querySelector('.sub-menu');
        const menuId = menuItem.dataset.menuId;

        if (subMenu) {
            const isExpanding = !subMenu.classList.contains('show');
            toggle.classList.toggle('active');

            // 双向平滑高度动画（菜单组展开/收起均有过渡）：
            // 展开先加 .show 再做 0 → h 过渡；收起保持 .show 先做 h → 0 过渡，
            // 动画结束后才移除 .show 隐藏（提前移除会直接 display:none 无动画）。
            // 服务端渲染的 .show 初始展开不走本动画，页面刷新/跳转时不重播。
            if (isExpanding) {
                subMenu.classList.add('show');
                animateSubmenu(subMenu, true);
                if (menuId && !this.expandedMenus.includes(menuId)) {
                    this.expandedMenus.push(menuId);
                }
            } else {
                animateSubmenu(subMenu, false);
                if (menuId) {
                    this.expandedMenus = this.expandedMenus.filter(id => id !== menuId);
                }
            }
            localStorage.setItem('expandedMenus', JSON.stringify(this.expandedMenus));
        }
    }

    restoreScrollPosition() {
        if (this.sidebarMenu && this.sidebarScrollTop > 0) {
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    this.sidebarMenu.scrollTop = this.sidebarScrollTop;
                });
            });
        }
    }

    /**
     * 初始化折叠态弹出子菜单功能
     */
    initCollapsedSubmenuPopup() {
        const menuItemsWithChildren = document.querySelectorAll('.sidebar .menu-item.has-children');
        
        menuItemsWithChildren.forEach(menuItem => {
            const toggle = menuItem.querySelector('a');
            const subMenu = menuItem.querySelector('.sub-menu');
            
            if (!toggle || !subMenu) return;
            
            // 鼠标悬停时显示弹出菜单
            toggle.addEventListener('mouseenter', (e) => {
                if (this.sidebar.classList.contains('collapsed')) {
                    this._hoveredMenuItem = menuItem;
                    this.showCollapsedSubmenu(menuItem, subMenu);
                }
            });
            
            // 鼠标离开时延迟隐藏
            menuItem.addEventListener('mouseleave', () => {
                this._hoveredMenuItem = null;
                this.delayedHideCollapsedSubmenu();
            });
        });
    }

    /**
     * 显示折叠态弹出子菜单
     */
    showCollapsedSubmenu(menuItem, subMenu) {
        // 清除隐藏计时器
        if (this.popupTimeout) {
            clearTimeout(this.popupTimeout);
            this.popupTimeout = null;
        }

        // 如果已经是这个菜单，只需要确保它显示着
        if (this.currentPopupMenu && this.currentPopupMenu._parentMenuItem === menuItem) {
            this.currentPopupMenu.classList.add('active');
            return;
        }

        // 如果有旧的弹出菜单，直接移除（不等待动画）
        if (this.currentPopupMenu) {
            this.currentPopupMenu.remove();
            this.currentPopupMenu = null;
        }

        // 创建新的弹出菜单
        const popup = document.createElement('div');
        popup.className = 'collapsed-submenu-popup';
        popup._parentMenuItem = menuItem;
        
        // 复制子菜单内容
        popup.innerHTML = subMenu.innerHTML;
        
        // 清理：移除内部的嵌套子菜单和箭头
        const innerSubMenus = popup.querySelectorAll('.sub-menu');
        innerSubMenus.forEach(innerSub => innerSub.remove());
        
        const arrows = popup.querySelectorAll('.menu-arrow');
        arrows.forEach(arrow => arrow.remove());
        
        // 移除 has-children 类
        const hasChildrenItems = popup.querySelectorAll('.has-children');
        hasChildrenItems.forEach(item => item.classList.remove('has-children'));
        
        // 绑定点击事件
        popup.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                this.hideCollapsedSubmenu();
            });
        });

        // 鼠标事件
        popup.addEventListener('mouseenter', () => {
            this._hoveringPopup = true;
            if (this.popupTimeout) {
                clearTimeout(this.popupTimeout);
                this.popupTimeout = null;
            }
        });
        
        popup.addEventListener('mouseleave', () => {
            this._hoveringPopup = false;
            this.delayedHideCollapsedSubmenu();
        });

        // 添加到 DOM
        document.body.appendChild(popup);
        this.currentPopupMenu = popup;
        
        // 计算位置
        const rect = menuItem.getBoundingClientRect();
        popup.style.left = (rect.right + 5) + 'px';
        popup.style.top = rect.top + 'px';
        
        // 确保不超出视口底部
        if (rect.top + popup.offsetHeight > window.innerHeight) {
            popup.style.top = (window.innerHeight - popup.offsetHeight - 20) + 'px';
        }

        // 立即显示
        popup.classList.add('active');
    }

    /**
     * 延迟隐藏弹出菜单（防抖）
     */
    delayedHideCollapsedSubmenu() {
        if (this.popupTimeout) {
            clearTimeout(this.popupTimeout);
        }
        this.popupTimeout = setTimeout(() => {
            // 只有当鼠标既不在菜单项上，也不在弹出菜单上时，才真正隐藏
            if (!this._hoveredMenuItem && !this._hoveringPopup) {
                this.hideCollapsedSubmenu();
            }
        }, 200);
    }

    /**
     * 隐藏折叠态弹出子菜单
     */
    hideCollapsedSubmenu() {
        if (this.popupTimeout) {
            clearTimeout(this.popupTimeout);
            this.popupTimeout = null;
        }
        
        if (this.currentPopupMenu) {
            this.currentPopupMenu.classList.remove('active');
            
            // 等待动画结束后再移除
            setTimeout(() => {
                if (this.currentPopupMenu && this.currentPopupMenu.parentNode) {
                    this.currentPopupMenu.parentNode.removeChild(this.currentPopupMenu);
                }
                this.currentPopupMenu = null;
            }, 150);
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.sidebarManager = new SidebarManager();
    
    initMobileMenu();
});

/**
 * 子菜单双向高度动画（展开 0 → h / 收起 h → 0，两个方向均有平滑过渡）
 *
 * 实现：scrollHeight 测量目标高度 + 内联 height/opacity 过渡；
 * 结束后清空内联样式交还文档流（收起时移除 .show 隐藏）。
 * 中断保护：再次切换时先清理上一轮未完成的监听与内联样式，避免样式残留。
 * 无障碍：prefers-reduced-motion 用户跳过动画（收起时立即移除 .show）。
 *
 * @param {HTMLElement} subMenu    .sub-menu 节点
 * @param {boolean}     isExpanding true=展开（调用前已加 .show）；false=收起（结束后移除 .show）
 */
function animateSubmenu(subMenu, isExpanding) {
    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var speed = (getComputedStyle(subMenu).getPropertyValue('--transition-speed') || '0.2s').trim();

    // 中断保护：清理上一轮未完成的动画状态（监听 + 内联样式）
    if (subMenu._animEnd) {
        subMenu.removeEventListener('transitionend', subMenu._animEnd);
        subMenu._animEnd = null;
    }
    subMenu.style.height = '';
    subMenu.style.overflow = '';
    subMenu.style.opacity = '';
    subMenu.style.transition = '';

    if (reduced) {
        if (!isExpanding) subMenu.classList.remove('show');
        return;
    }

    var onEnd = function(e) {
        if (e.propertyName !== 'height') return; // 只认 height，忽略 opacity 等并行过渡
        subMenu.removeEventListener('transitionend', onEnd);
        subMenu._animEnd = null;
        subMenu.style.height = '';
        subMenu.style.overflow = '';
        subMenu.style.opacity = '';
        subMenu.style.transition = '';
        if (!isExpanding) subMenu.classList.remove('show');
    };
    subMenu._animEnd = onEnd;

    if (isExpanding) {
        // 展开起始态：0 高度 + 透明，强制 reflow 后过渡到目标高度
        subMenu.style.overflow = 'hidden';
        subMenu.style.height = '0px';
        subMenu.style.opacity = '0';
        void subMenu.offsetHeight;
        subMenu.style.transition = 'height ' + speed + ' ease, opacity ' + speed + ' ease';
        subMenu.style.height = subMenu.scrollHeight + 'px';
        subMenu.style.opacity = '1';
    } else {
        // 收起起始态：固定当前内容高度，强制 reflow 后过渡到 0
        subMenu.style.overflow = 'hidden';
        subMenu.style.height = subMenu.scrollHeight + 'px';
        void subMenu.offsetHeight;
        subMenu.style.transition = 'height ' + speed + ' ease, opacity ' + speed + ' ease';
        subMenu.style.height = '0px';
        subMenu.style.opacity = '0';
    }
    subMenu.addEventListener('transitionend', onEnd);
}

function initMobileMenu() {
    const mobileMenuToggle = document.createElement('button');
    mobileMenuToggle.className = 'mobile-menu-toggle';
    mobileMenuToggle.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>`;
    mobileMenuToggle.addEventListener('click', toggleMobileSidebar);

    const sidebarOverlay = document.createElement('div');
    sidebarOverlay.className = 'sidebar-overlay';
    sidebarOverlay.addEventListener('click', closeMobileSidebar);

    // 汉堡按钮挂载到头部栏内（CSS 定位为头部左侧、与标题同一行），
    // 不再悬浮于页面左上角；头部不存在时兜底挂载到 body
    const header = document.querySelector('.header');
    if (header) {
        header.appendChild(mobileMenuToggle);
    } else {
        document.body.appendChild(mobileMenuToggle);
    }
    document.body.appendChild(sidebarOverlay);
}

function toggleMobileSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    
    if (sidebar) {
        sidebar.classList.toggle('open');
        overlay.classList.toggle('show');
        
        if (sidebar.classList.contains('open')) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
    }
}

function closeMobileSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');

    if (sidebar) {
        sidebar.classList.remove('open');
        // 遮罩元素可能不存在（如个别页面未渲染 .sidebar-overlay），需判空避免 resize 时抛错
        if (overlay) {
            overlay.classList.remove('show');
        }
        document.body.style.overflow = '';
    }
}

function closeSidebarOnResize() {
    if (window.innerWidth <= 768) {
        closeMobileSidebar();
    }
}

window.addEventListener('resize', closeSidebarOnResize);
