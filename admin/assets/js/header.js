/**
 * Header 功能模块
 * 包含：全局命令面板、通知中心、用户菜单
 */

$(document).ready(function() {
    initCommandPalette();
    initNotificationCenter();
    initUserMenu();
    initNotificationPolling();
});

/**
 * 全局命令面板
 */
function initCommandPalette() {
    var $toggle = $('#commandPaletteToggle');
    var $palette = $('#commandPalette');
    var $input = $('#commandInput');
    var $results = $('#commandResults');
    var searchTimeout = null;
    
    // 点击按钮打开
    $toggle.click(function(e) {
        e.stopPropagation();
        $palette.toggleClass('active');
        if ($palette.hasClass('active')) {
            $input.focus();
        }
    });
    
    // Ctrl+K 快捷键
    $(document).keydown(function(e) {
        // 如果当前焦点在编辑器或输入框中，忽略 Ctrl+K 快捷键
        var $focused = $(e.target);
        if ($focused.is('textarea, input, [contenteditable="true"], .note-editor, .CodeMirror, .tox-edit-area, .cke')) {
            return;
        }
        
        // 检查父元素是否是编辑器容器
        if ($focused.closest('textarea, [contenteditable="true"], .note-editor, .CodeMirror, .tox-edit-area, .cke').length > 0) {
            return;
        }
        
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            $palette.toggleClass('active');
            if ($palette.hasClass('active')) {
                $input.focus();
            }
        }
        
        // ESC 关闭面板
        if (e.key === 'Escape') {
            $palette.removeClass('active');
        }
    });
    
    // 点击外部关闭
    $(document).click(function(e) {
        if (!$(e.target).closest('.command-palette').length && 
            !$(e.target).closest('.command-palette-toggle').length) {
            $palette.removeClass('active');
        }
    });
    
    // 搜索输入（带防抖）
    $input.keyup(function(e) {
        // 忽略方向键和回车键
        if (['ArrowUp', 'ArrowDown', 'Enter', 'Tab'].includes(e.key)) {
            return;
        }
        
        var query = $(this).val().trim();
        
        // 清除之前的定时器
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }
        
        if (query.length > 0) {
            // 200ms 防抖延迟
            searchTimeout = setTimeout(function() {
                searchCommands(query);
            }, 200);
        } else {
            $results.html('');
        }
    });
    
    // 键盘导航：上下键选择，回车键确认
    $input.keydown(function(e) {
        var $items = $results.find('.command-palette-item');
        
        if ($items.length === 0) {
            return;
        }
        
        var $selected = $items.filter('.selected');
        
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            var next = $selected.length ? $selected.next('.command-palette-item') : $items.first();
            $selected.removeClass('selected');
            next.addClass('selected');
            // 滚动到可见区域
            next[0]?.scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            var prev = $selected.length ? $selected.prev('.command-palette-item') : $items.last();
            $selected.removeClass('selected');
            prev.addClass('selected');
            // 滚动到可见区域
            prev[0]?.scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'Enter' && $selected.length) {
            e.preventDefault();
            window.location.href = $selected.data('url');
        }
    });
}

/**
 * 搜索命令
 */
function searchCommands(query) {
    var $results = $('#commandResults');
    var keywords = query.toLowerCase();
    
    // 显示加载状态
    $results.html('<div class="command-palette-loading">搜索中...</div>');
    
    // 调用后端API搜索
    $.get('admin.php?action=search', { query: query, limit: 8 }, function(res) {
        if (res.success && res.data) {
            renderSearchResults(res.data);
        } else {
            $results.html('<div class="command-palette-empty">未找到匹配结果</div>');
        }
    }, 'json').fail(function() {
        // 后端不可用时，使用本地命令列表
        renderLocalCommands(keywords);
    });
}

/**
 * 渲染搜索结果（分类展示）
 */
function renderSearchResults(data) {
    var $results = $('#commandResults');
    var html = '';
    var hasResults = false;
    
    // 定义分类顺序和图标
    var categories = [
        { key: 'commands', label: '快捷操作', icon: 'settings' },
        { key: 'articles', label: '文章', icon: 'file-text' },
        { key: 'users', label: '用户', icon: 'users' },
        { key: 'comments', label: '评论', icon: 'message-square' },
        { key: 'categories', label: '分类', icon: 'folder' },
        { key: 'tags', label: '标签', icon: 'tag' },
        { key: 'configs', label: '配置项', icon: 'settings' },
    ];
    
    categories.forEach(function(cat) {
        if (data[cat.key] && data[cat.key].items && data[cat.key].items.length > 0) {
            hasResults = true;
            html += '<div class="command-palette-group">';
            html += '<div class="command-palette-group-header">';
            html += '<svg class="command-palette-group-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">';
            html += getIconPath(cat.icon);
            html += '</svg>';
            html += '<span>' + cat.label + '</span>';
            html += '</div>';
            
            data[cat.key].items.forEach(function(item) {
                html += '<div class="command-palette-item" data-url="' + item.url + '">';
                html += '<svg class="command-palette-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">';
                html += getIconPath(cat.icon);
                html += '</svg>';
                html += '<div>';
                html += '<div class="command-palette-item-title">' + escapeHtml(item.title) + '</div>';
                html += '<div class="command-palette-item-subtitle">' + escapeHtml(item.subtitle) + '</div>';
                html += '</div>';
                html += '<span class="command-palette-item-action">跳转</span>';
                html += '</div>';
            });
            
            html += '</div>';
        }
    });
    
    if (!hasResults) {
        html = '<div class="command-palette-empty">未找到匹配结果</div>';
    }
    
    $results.html(html);
    
    // 点击跳转
    $results.find('.command-palette-item').click(function() {
        window.location.href = $(this).data('url');
    });
}

/**
 * 渲染本地命令（备用）
 */
function renderLocalCommands(keywords) {
    var $results = $('#commandResults');
    
    var commands = [
        { type: 'article', title: '文章管理', subtitle: '查看和管理文章', url: 'admin.php?action=article', icon: 'file-text' },
        { type: 'article', title: '添加文章', subtitle: '创建新文章', url: 'admin.php?action=article&sub=add', icon: 'file-text' },
        { type: 'comment', title: '评论管理', subtitle: '审核和管理评论', url: 'admin.php?action=comment', icon: 'message-square' },
        { type: 'user', title: '用户管理', subtitle: '管理用户账户', url: 'admin.php?action=user', icon: 'users' },
        { type: 'config', title: '系统设置', subtitle: '配置系统参数', url: 'admin.php?action=config', icon: 'settings' },
        { type: 'theme', title: '主题管理', subtitle: '管理网站主题', url: 'admin.php?action=theme', icon: 'palette' },
        { type: 'plugin', title: '插件管理', subtitle: '管理插件扩展', url: 'admin.php?action=plugin', icon: 'extension' },
        { type: 'media', title: '媒体库', subtitle: '管理上传文件', url: 'admin.php?action=media', icon: 'folder' },
        { type: 'category', title: '分类管理', subtitle: '管理文章分类', url: 'admin.php?action=category', icon: 'folder' },
        { type: 'tag', title: '标签管理', subtitle: '管理文章标签', url: 'admin.php?action=tag', icon: 'tag' },
        { type: 'page', title: '页面管理', subtitle: '管理独立页面', url: 'admin.php?action=page', icon: 'file-text' },
        { type: 'backup', title: '数据备份', subtitle: '备份和恢复数据', url: 'admin.php?action=backup', icon: 'server' },
        { type: 'log', title: '操作日志', subtitle: '查看系统日志', url: 'admin.php?action=log', icon: 'file-text-o' },
        { type: 'dashboard', title: '仪表盘', subtitle: '查看数据概览', url: 'admin.php?action=dashboard', icon: 'dashboard' },
    ];
    
    var filtered = commands.filter(function(cmd) {
        return cmd.title.toLowerCase().includes(keywords) || 
               cmd.subtitle.toLowerCase().includes(keywords);
    });
    
    var html = '';
    if (filtered.length > 0) {
        html += '<div class="command-palette-group">';
        html += '<div class="command-palette-group-header">';
        html += '<svg class="command-palette-group-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">';
        html += getIconPath('settings');
        html += '</svg>';
        html += '<span>快捷操作</span>';
        html += '</div>';
        
        filtered.forEach(function(cmd) {
            html += '<div class="command-palette-item" data-url="' + cmd.url + '">';
            html += '<svg class="command-palette-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">';
            html += getIconPath(cmd.icon);
            html += '</svg>';
            html += '<div>';
            html += '<div class="command-palette-item-title">' + cmd.title + '</div>';
            html += '<div class="command-palette-item-subtitle">' + cmd.subtitle + '</div>';
            html += '</div>';
            html += '<span class="command-palette-item-action">跳转</span>';
            html += '</div>';
        });
        
        html += '</div>';
    } else {
        html = '<div class="command-palette-empty">未找到匹配结果</div>';
    }
    
    $results.html(html);
    
    // 点击跳转
    $results.find('.command-palette-item').click(function() {
        window.location.href = $(this).data('url');
    });
}

/**
 * HTML转义
 */
function escapeHtml(str) {
    return str.replace(/&/g, '&amp;')
              .replace(/</g, '&lt;')
              .replace(/>/g, '&gt;')
              .replace(/"/g, '&quot;');
}

/**
 * 获取图标路径
 */
function getIconPath(iconName) {
    var icons = {
        'dashboard': '<rect x="3" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="14" width="7" height="7" rx="1"></rect><rect x="3" y="14" width="7" height="7" rx="1"></rect>',
        'users': '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
        'file-text': '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><line x1="10" y1="9" x2="8" y2="9"></line>',
        'file-text-o': '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><line x1="10" y1="9" x2="8" y2="9"></line>',
        'folder': '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>',
        'tag': '<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.83z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line>',
        'message-square': '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>',
        'settings': '<circle cx="12" cy="12" r="3"></circle><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"></path>',
        'palette': '<circle cx="13.5" cy="6.5" r=".5" fill="currentColor"></circle><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"></circle><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"></circle><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"></circle><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.682-.438-1.125C12.773 17.438 12 16.695 12 15.813c0-.95.858-1.722 1.908-1.722h2.184c1.05 0 1.908.772 1.908 1.722 0 .882-.773 1.625-1.713 1.625-.25 0-.45.413-.45.875 0 .25.15.463.375.625.225.162.525.287.825.287.35 0 .65-.15.9-.4 2.5-2.5 4-5.5 4-8.75C22 6.5 17.5 2 12 2z"></path>',
        'extension': '<path d="M12 2L2 7l10 5 10-5-10-5z"></path><path d="M2 17l10 5 10-5"></path><path d="M2 12l10 5 10-5"></path>',
        'server': '<rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect><rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect><line x1="6" y1="6" x2="6.01" y2="6"></line><line x1="6" y1="18" x2="6.01" y2="18"></line>',
    };
    return icons[iconName] || icons['file-text'];
}

/**
 * 通知中心
 */
function initNotificationCenter() {
    var $btn = $('#notificationBtn');
    var $badge = $('#notificationBadge');
    var $center = $('#notificationCenter');
    var $panel = $('#notificationCenterPanel');
    var $mask = $('#notificationCenterMask');
    var $close = $('#notificationCenterClose');
    var $body = $('#notificationCenterBody');
    var $loading = $('#notificationLoading');
    var $empty = $('#notificationEmpty');
    var $list = $('#notificationList');
    var $markAll = $('#notificationMarkAllRead');
    
    // 点击按钮打开
    $btn.click(function(e) {
        e.stopPropagation();
        loadNotifications();
        $center.addClass('active');
    });
    
    // 点击关闭按钮
    $close.click(function() {
        $center.removeClass('active');
    });
    
    // 点击遮罩关闭
    $mask.click(function() {
        $center.removeClass('active');
    });
    
    // 点击外部关闭
    $(document).click(function(e) {
        if (!$(e.target).closest('.notification-center-panel').length && 
            !$(e.target).closest('.notification-btn').length) {
            $center.removeClass('active');
        }
    });
    
    // 全部标为已读
    $markAll.click(function() {
        $.post('admin.php?action=notification&method=markAllAsRead', function(res) {
            if (res.success) {
                $badge.hide();
                $list.find('.notification-item.unread').removeClass('unread');
            }
        }, 'json');
    });
}

/**
 * 加载通知列表
 */
function loadNotifications() {
    var $loading = $('#notificationLoading');
    var $empty = $('#notificationEmpty');
    var $list = $('#notificationList');
    
    $loading.show();
    $empty.hide();
    $list.hide();
    
    $.get('admin.php?action=notification&method=getRecent', function(res) {
        $loading.hide();
        
        if (res.success && res.data.length > 0) {
            renderNotifications(res.data);
            $list.show();
        } else {
            $empty.show();
        }
    }, 'json');
}

/**
 * 渲染通知列表
 */
function renderNotifications(notifications) {
    var $list = $('#notificationList');
    var html = '';
    
    notifications.forEach(function(notif) {
        var unreadClass = notif.read == 0 ? 'unread' : '';
        html += '<div class="notification-item ' + unreadClass + '" data-id="' + notif.id + '" data-url="' + notif.url + '">';
        html += '<div class="notification-item-icon">';
        html += '<svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">';
        html += getNotificationIcon(notif.type);
        html += '</svg>';
        html += '</div>';
        html += '<div class="notification-item-content">';
        html += '<h4 class="notification-item-title">' + htmlEscape(notif.title) + '</h4>';
        html += '<p class="notification-item-text">' + htmlEscape(notif.content) + '</p>';
        html += '<div class="notification-item-time">' + notif.time_ago + '</div>';
        html += '</div>';
        html += '</div>';
    });
    
    $list.html(html);
    
    // 点击通知
    $list.find('.notification-item').click(function() {
        var $item = $(this);
        var id = $item.data('id');
        var url = $item.data('url');
        
        // 标记为已读
        $.post('admin.php?action=notification&method=markAsRead', { id: id }, function(res) {
            if (res.success) {
                $item.removeClass('unread');
                updateNotificationCount();
            }
        }, 'json');
        
        // 跳转
        if (url) {
            window.location.href = url;
        }
    });
}

/**
 * 获取通知类型图标
 */
function getNotificationIcon(type) {
    var icons = {
        'comment_reply': '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>',
        'new_follower': '<path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line>',
        'article_approved': '<polyline points="20 6 9 17 4 12"></polyline>',
        'article_rejected': '<circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12" y2="16"></line>',
        'system_update': '<polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>',
    };
    return icons[type] || '<circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line>';
}

/**
 * HTML转义
 */
function htmlEscape(str) {
    return str.replace(/&/g, '&amp;')
              .replace(/</g, '&lt;')
              .replace(/>/g, '&gt;')
              .replace(/"/g, '&quot;');
}

/**
 * 用户菜单
 */
function initUserMenu() {
    var $toggle = $('#userMenuToggle');
    var $dropdown = $('#userMenuDropdown');
    
    // 点击按钮切换
    $toggle.click(function(e) {
        e.stopPropagation();
        $dropdown.toggleClass('show');
    });
    
    // 点击外部关闭
    $(document).click(function(e) {
        if (!$(e.target).closest('.user-menu').length) {
            $dropdown.removeClass('show');
        }
    });
}

/**
 * 通知轮询
 */
function initNotificationPolling() {
    // 初始加载
    updateNotificationCount();
    
    // 每60秒轮询一次
    setInterval(updateNotificationCount, 60000);
}



/**
 * 更新通知数量
 */
function updateNotificationCount() {
    var $badge = $('#notificationBadge');
    
    $.get('admin.php?action=notification&method=getUnreadCount', function(res) {
        if (res.success) {
            var count = res.data.count;
            if (count > 0) {
                $badge.text(count > 99 ? '99+' : count);
                $badge.show();
            } else {
                $badge.hide();
            }
        }
    }, 'json');
}
