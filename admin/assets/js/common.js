// UI/UX 增强功能

// ===== 统一确认/提示弹窗 =====
// 结构：遮罩 > 弹窗（标题栏 + 右上×、圆形状态图标 + 消息、右下取消/确定）
// 样式见 components.css 的 .modal-overlay/.modal 体系（含暗色主题与 .show 动画）。
// showConfirmDialog 返回 Promise：确定 resolve(true)，取消/×/遮罩 resolve(false)。
function showConfirmDialog(message, options) {
    const opts = options || {};
    const type = opts.type || 'warning'; // danger（红×，删除类）| warning（橙!，默认）| info（蓝i）
    const title = opts.title || (type === 'danger' ? '删除确认' : '确认操作');
    const confirmText = opts.confirmText || '确定';
    const cancelText = opts.cancelText !== undefined ? opts.cancelText : '取消';

    // 状态图标（SVG 圆形徽标，与 showToast 图标同一套绘制风格）
    const typeColor = type === 'danger' ? '#dc3545' : (type === 'info' ? '#17a2b8' : '#e0a800');
    let iconInner;
    if (type === 'danger') {
        iconInner = '<line x1="9" y1="9" x2="15" y2="15"></line><line x1="15" y1="9" x2="9" y2="15"></line>';
    } else if (type === 'info') {
        iconInner = '<line x1="12" y1="11" x2="12" y2="16.5"></line><line x1="12" y1="7.5" x2="12" y2="7.6"></line>';
    } else {
        iconInner = '<line x1="12" y1="7" x2="12" y2="13"></line><line x1="12" y1="16.5" x2="12" y2="16.6"></line>';
    }
    const icon = '<svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round">' +
        '<circle cx="12" cy="12" r="10" fill="' + typeColor + '" stroke="none"></circle>' + iconInner + '</svg>';

    // 消息文本 HTML 转义（options.allowHtml 为 true 时跳过，供富文本提示使用）
    const safeMessage = opts.allowHtml
        ? String(message)
        : String(message)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;')
            // \n 换行转为 <br>，支持多行说明文案
            .replace(/\n/g, '<br>');

    return new Promise((resolve) => {
        // 遮罩层：复用 components.css 的 .modal-overlay（.show 淡入）
        const overlay = document.createElement('div');
        overlay.className = 'modal-overlay';
        overlay.innerHTML =
            '<div class="confirm-dialog" role="dialog" aria-modal="true">' +
            '  <div class="modal-header">' +
            '    <h3 class="modal-title"></h3>' +
            '    <button type="button" class="modal-close" aria-label="关闭">&times;</button>' +
            '  </div>' +
            '  <div class="modal-body">' +
            '    <div class="confirm-dialog-row">' +
            '      <span class="confirm-dialog-icon">' + icon + '</span>' +
            '      <span class="confirm-dialog-message">' + safeMessage + '</span>' +
            '    </div>' +
            '  </div>' +
            '  <div class="modal-footer">' +
            (cancelText !== '' ? '<button type="button" class="btn btn-secondary confirm-cancel"></button>' : '') +
            '    <button type="button" class="btn btn-primary confirm-ok"></button>' +
            '  </div>' +
            '</div>';
        overlay.querySelector('.modal-title').textContent = title;
        overlay.querySelector('.confirm-ok').textContent = confirmText;
        const cancelBtn = overlay.querySelector('.confirm-cancel');
        if (cancelBtn) {
            cancelBtn.textContent = cancelText;
        }

        // 关闭：resolve(false)；确定：resolve(true)。一次性绑定后移除 DOM
        const close = (result) => {
            resolve(result);
            overlay.classList.remove('show');
            // 等 .show 过渡播完再移除（与 CSS 0.3s 对应）
            setTimeout(() => overlay.remove(), 300);
        };
        overlay.querySelector('.modal-close').addEventListener('click', () => close(false));
        overlay.querySelector('.confirm-ok').addEventListener('click', () => close(true));
        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => close(false));
        }
        // 点击遮罩空白处 = 取消
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                close(false);
            }
        });

        document.body.appendChild(overlay);
        // 强制 reflow 后加 .show 触发淡入（同 showToast：不依赖 rAF，避免后台标签页下弹窗不可见）
        void overlay.offsetWidth;
        overlay.classList.add('show');
        // 确定按钮聚焦，回车直接确认、Esc 可通过遮罩点击或×关闭
        overlay.querySelector('.confirm-ok').focus();
    });
}

// 自定义提示弹窗（仅一个「确定」按钮，基于统一确认弹窗实现）
function showAlertDialog(message, title) {
    return showConfirmDialog(message, {
        title: title || '提示',
        type: 'info',
        cancelText: ''
    });
}

// ===== 底部动作面板（Action Sheet）=====
// 结构：遮罩 > 底部滑出面板（可选标题组 + 动作分组 + 独立取消组）。
// 交互：点遮罩/取消关闭；动作点击后自动关闭；Esc 等价取消。
// 返回 Promise：点击动作 resolve 该动作对象；取消/遮罩/Esc resolve(null)。
// 动作项两种形态（二选一）：
//   { label, href, target }  链接型——关闭面板后跳转/新窗口打开；
//   { label, onClick }       回调型——关闭面板后执行回调；
//   可选 danger: true 标记危险动作（红色文字，CSS 上独立分组展示）。
// 典型用途：移动端表格行操作（initDropdowns 的窄屏桥接把行内下拉
// 菜单实时转换为动作面板，原菜单项的 confirmLink 确认链完整保留）。
// 样式见 components.css 的 .as-backdrop/.as-sheet 组件区。
function showActionSheet(options) {
    const opts = options || {};
    const actions = opts.actions || [];
    const cancelText = opts.cancelText !== undefined ? opts.cancelText : '取消';

    return new Promise((resolve) => {
        const overlay = document.createElement('div');
        overlay.className = 'as-backdrop';
        // 文本统一走 textContent 回填（防注入）；结构只搭骨架。
        // 标题与全部动作（含危险项）同处一张卡片，
        // 仅「取消」独立成卡——不按危险/普通拆分多卡
        overlay.innerHTML =
            '<div class="as-sheet" role="dialog" aria-modal="true">' +
            '  <div class="as-scroll">' +
            '    <div class="as-group as-main-group">' +
            (opts.title || opts.message ?
            '      <div class="as-title-group"><div class="as-title"></div>' +
            (opts.message ? '<div class="as-message"></div>' : '') + '</div>' : '') +
            '    </div>' +
            '    <div class="as-group as-cancel-group"><button type="button" class="as-item as-cancel"></button></div>' +
            '  </div>' +
            '</div>';

        if (opts.title) overlay.querySelector('.as-title').textContent = opts.title;
        if (opts.message) overlay.querySelector('.as-message').textContent = opts.message;
        const cancelBtn = overlay.querySelector('.as-cancel');
        if (cancelBtn) cancelBtn.textContent = cancelText;

        // 动作项填充：全部进同一张主卡片（含 danger 红字项）
        const mainGroup = overlay.querySelector('.as-main-group');
        actions.forEach(function(action) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'as-item' + (action.danger ? ' as-item-danger' : '');
            btn.textContent = action.label;
            if (action.disabled) {
                btn.disabled = true;
            } else {
                btn.addEventListener('click', function() {
                    close(action);
                    // 延迟到面板滑出后再执行动作：href 跳转/新窗口/回调
                    // （原 confirmLink 确认链在此时触发，确认弹窗不被滑出中的面板遮挡）
                    setTimeout(function() {
                        if (action.onClick) {
                            action.onClick(action);
                        } else if (action.href) {
                            showPageLoading();
                            if (action.target === '_blank') {
                                window.open(action.href, '_blank');
                            } else {
                                window.location.href = action.href;
                            }
                        }
                    }, 180);
                });
            }
            mainGroup.appendChild(btn);
        });

        // 关闭：resolve 结果；一次性绑定后延迟移除 DOM（等滑出过渡播完）
        let closed = false;
        function close(result) {
            if (closed) return;
            closed = true;
            resolve(result);
            document.removeEventListener('keydown', onKeydown);
            overlay.classList.remove('show');
            // 与 CSS .as-sheet 滑出过渡 0.3s 对应
            setTimeout(() => overlay.remove(), 300);
        }
        function onKeydown(e) {
            if (e.key === 'Escape') close(null);
        }
        overlay.addEventListener('click', (e) => {
            // 点遮罩 = 取消；点面板内部不冒泡关闭
            if (e.target === overlay) close(null);
        });
        if (cancelBtn) cancelBtn.addEventListener('click', () => close(null));
        document.addEventListener('keydown', onKeydown);

        document.body.appendChild(overlay);
        // 强制 reflow 后加 .show 触发滑入（同 showConfirmDialog：不依赖 rAF）
        void overlay.offsetWidth;
        overlay.classList.add('show');
    });
}

/* ============================================================
   半屏面板（Sheet Modal，showSheetModal 通用 API）
   用途：窄屏承载表单类内容（列筛选、批量操作等）——底部滑出半屏
   卡片：拖拽指示条 + 标题栏 + 可滚动内容区。桌面端同样贴底，
   限宽 560px 居中。层级 99998（与 Action
   Sheet 同层、低于确认弹窗 99999：面板内触发的确认框永远在面板之上）。
   交互：点遮罩 / × / Esc / 下滑拖拽（grabber 与标题栏为拖拽区，
   超过 80px 松手关闭、不足回弹）均可关闭；打开期间锁定页面滚动。
   参数：{ title, content(string|Node), onClose }——content 传 Node
   时原位移入内容区（事件委托与既有监听不受影响），适合「临时搬家」
   场景（如列筛选面板从表头移入面板、关闭时归位）。
   返回：{ close(), el }——close() 程序化关闭；el 为遮罩根节点，
   关闭前可从其中取回动态内容。样式见 components.css 的 .sheet-* 区。
   ============================================================ */
function showSheetModal(options) {
    const opts = options || {};
    const onClose = typeof opts.onClose === 'function' ? opts.onClose : null;
    let closed = false;

    // 文本统一走 textContent 回填（防注入）；content 为 string 时才用 innerHTML
    const backdrop = document.createElement('div');
    backdrop.className = 'sheet-backdrop';
    backdrop.innerHTML =
        '<div class="sheet-panel" role="dialog" aria-modal="true">' +
        '  <div class="sheet-grabber" aria-hidden="true"></div>' +
        '  <div class="sheet-header">' +
        '    <div class="sheet-title"></div>' +
        '    <button type="button" class="sheet-close" aria-label="关闭">' +
        '      <svg viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"></path></svg>' +
        '    </button>' +
        '  </div>' +
        '  <div class="sheet-body"></div>' +
        '</div>';

    if (opts.title) backdrop.querySelector('.sheet-title').textContent = opts.title;
    const sheetBody = backdrop.querySelector('.sheet-body');
    if (typeof opts.content === 'string') {
        sheetBody.innerHTML = opts.content;
    } else if (opts.content instanceof Node) {
        sheetBody.appendChild(opts.content);
    }

    // 关闭：解除滚动锁定与键盘监听，等滑出过渡播完再移除 DOM 并回调 onClose
    function close() {
        if (closed) return;
        closed = true;
        backdrop.classList.remove('show');
        document.body.style.overflow = prevOverflow;
        document.removeEventListener('keydown', onKey);
        // 与 CSS .sheet-panel 滑出过渡 0.26s 对应
        setTimeout(function() {
            if (backdrop.parentNode) backdrop.parentNode.removeChild(backdrop);
            if (onClose) onClose();
        }, 260);
    }
    function onKey(e) {
        if (e.key === 'Escape') close();
    }

    document.body.appendChild(backdrop);
    // 锁定页面滚动（后台页面滚动容器是 window）
    const prevOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    document.addEventListener('keydown', onKey);
    backdrop.addEventListener('click', function(e) {
        // 点遮罩关闭；点面板内部不冒泡关闭
        if (e.target === backdrop) close();
    });
    backdrop.querySelector('.sheet-close').addEventListener('click', close);

    // 强制 reflow 后加 .show 触发滑入（与 Action Sheet 同款机制）
    void backdrop.offsetWidth;
    backdrop.classList.add('show');

    // 下滑拖拽关闭：grabber 与标题栏为拖拽热区，
    // 按住向下拖面板实时跟随（inline transform），超过 80px 松手关闭、
    // 不足回弹（清空 inline transform 恢复 .show 的定位）。
    // pointer 事件统一鼠标/触屏；setPointerCapture 保证移出热区仍持续跟踪
    const panel = backdrop.querySelector('.sheet-panel');
    const closeBtn = backdrop.querySelector('.sheet-close');
    [backdrop.querySelector('.sheet-grabber'), backdrop.querySelector('.sheet-header')].forEach(function(zone) {
        if (!zone) return;
        let dragStartY = 0;
        let dragging = false;
        zone.addEventListener('pointerdown', function(e) {
            if (closed || e.target.closest('.sheet-close')) return;
            dragging = true;
            dragStartY = e.clientY;
            panel.style.transition = 'none';
            zone.setPointerCapture(e.pointerId);
        });
        zone.addEventListener('pointermove', function(e) {
            if (!dragging) return;
            panel.style.transform = 'translateY(' + Math.max(0, e.clientY - dragStartY) + 'px)';
        });
        ['pointerup', 'pointercancel'].forEach(function(evt) {
            zone.addEventListener(evt, function(e) {
                if (!dragging) return;
                dragging = false;
                panel.style.transition = '';
                const dy = Math.max(0, e.clientY - dragStartY);
                panel.style.transform = '';
                if (dy > 80) close();
            });
        });
    });
    // × 按钮在拖拽热区内，pointerdown 已排除；click 关闭兜底（键盘触发）
    closeBtn.addEventListener('click', close);

    return { close: close, el: backdrop };
}

// 链接确认辅助：替代内联 onclick="return confirm(...)"。
// 用法：<a href="..." onclick="return confirmLink(this, '确定删除吗？', { title: '删除确认', type: 'danger' })">
// 阻止默认跳转，确认后自行前往 el.href；取消则无动作
function confirmLink(el, message, options) {
    showConfirmDialog(message, options).then((ok) => {
        if (ok && el && el.href) {
            // 导航前补等待反馈（慢动作链接：清缓存/备份下载等）
            showPageLoading();
            markSubmitterLoading(el);
            window.location.href = el.href;
        }
    });
    return false;
}

// 表单确认辅助：替代 onsubmit="return confirm(...)"。
// 用法：<form onsubmit="return confirmForm(this, '确定提交吗？', { beforeSubmit: fn })">
// 确认后可选执行 beforeSubmit 回调（如禁用提交按钮）再提交表单；
// form.submit() 不触发 onsubmit，无递归风险
function confirmForm(form, message, options) {
    const opts = options || {};
    showConfirmDialog(message, opts).then((ok) => {
        if (ok && form) {
            if (typeof opts.beforeSubmit === 'function') {
                opts.beforeSubmit(form);
            }
            // 确认后提交不触发 submit 事件，补全页遮罩 + 按钮等待态
            afterConfirmSubmit(form, null);
            form.submit();
        }
    });
    return false;
}

// 提交按钮确认辅助：替代按钮内联 onclick="return confirm(...)"。
// 用法：<button type="submit" onclick="return confirmSubmit(this, '确定提交吗？')">
// 确认后向上找所属表单提交（submit 按钮被 return false 阻止默认后由回调接管）
function confirmSubmit(btn, message, options) {
    showConfirmDialog(message, options).then((ok) => {
        if (ok && btn) {
            const form = btn.closest('form');
            if (form) {
                // 确认后提交不触发 submit 事件，补全页遮罩 + 按钮等待态
                afterConfirmSubmit(form, btn);
                form.submit();
            }
        }
    });
    return false;
}

// 模态框显示
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
    }
}

// 模态框隐藏
function hideModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
    }
}

// 点击模态框外部关闭模态框
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}

// 操作提示功能（Toast）
// 样式统一收拢至 common.css（顶部居中堆叠式），此处负责结构生成与生命周期：
// 入场 .show 下滑到位；离场移除 .show 向上滑出淡出后移除 DOM
function showToast(message, type = 'info', duration = 3000) {
    // 四类型的圆形图标（SVG，与截图样式的 ✓/!/✕/i 徽标对齐）
    const toastIcons = {
        success: '<svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" fill="#28a745" stroke="none"></circle><polyline points="8 12.5 11 15.5 16 9.5"></polyline></svg>',
        error: '<svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round"><circle cx="12" cy="12" r="10" fill="#dc3545" stroke="none"></circle><line x1="9" y1="9" x2="15" y2="15"></line><line x1="15" y1="9" x2="9" y2="15"></line></svg>',
        warning: '<svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round"><circle cx="12" cy="12" r="10" fill="#e0a800" stroke="none"></circle><line x1="12" y1="7" x2="12" y2="13"></line><line x1="12" y1="16.5" x2="12" y2="16.6"></line></svg>',
        info: '<svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round"><circle cx="12" cy="12" r="10" fill="#17a2b8" stroke="none"></circle><line x1="12" y1="11" x2="12" y2="16.5"></line><line x1="12" y1="7.5" x2="12" y2="7.6"></line></svg>'
    };

    // 消息内容做 HTML 转义，防止消息文本中携带的字符破坏结构
    const safeMessage = String(message)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');

    // 堆叠容器：懒创建（首次调用时插入 body），多条提示纵向排列互不顶替
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
    }

    // 创建新的 toast 条目
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <span class="toast-icon">${toastIcons[type] || toastIcons.info}</span>
        <span class="toast-message">${safeMessage}</span>
        <button type="button" class="toast-close" aria-label="关闭">&times;</button>
    `;

    // 关闭按钮：点击立即离场（清除自动关闭定时器，避免重复移除）
    let removeTimer = null;
    const dismiss = () => {
        if (removeTimer) {
            clearTimeout(removeTimer);
            removeTimer = null;
        }
        if (!toast.parentNode) {
            return;
        }
        toast.classList.remove('show');
        // 等离场动画播完再移除 DOM（时长与 CSS transition 0.3s 对应）
        setTimeout(() => toast.remove(), 300);
    };
    toast.querySelector('.toast-close').addEventListener('click', dismiss);

    container.appendChild(toast);

    // 入场：强制 reflow 后立即加 .show 触发下滑过渡。
    // 不用 requestAnimationFrame——后台标签页/渲染节流场景下 rAF 回调不执行，
    // 会导致 .show 永远加不上、提示永远不可见；同步 reflow 可靠且同样保留过渡动画
    void toast.offsetWidth;
    toast.classList.add('show');

    // 自动离场
    removeTimer = setTimeout(dismiss, duration);
}

// 显示页面加载状态
function showPageLoading() {
    // 移除已存在的加载状态
    const existingLoading = document.querySelector('.page-loading');
    if (existingLoading) {
        existingLoading.remove();
    }
    
    // 创建新的加载状态
    const loading = document.createElement('div');
    loading.className = 'page-loading';
    document.body.appendChild(loading);
}

// 隐藏页面加载状态
function hidePageLoading() {
    const loading = document.querySelector('.page-loading');
    if (loading) {
        loading.remove();
    }
}

// ===== 统一提交按钮等待态 =====
// 把触发提交的按钮标记为「处理中…」（spinner + 文案替换）。
// 注意用 pointer-events:none 而非 disabled：disabled 会把按钮从 POST 数据里
// 剔除，依赖按钮 name=value 判定动作的表单会因此丢失参数。
// button 与 input[type=submit] 双形态兼容（input 无法写 innerHTML，改 value）。
function markSubmitterLoading(btn) {
    if (!btn || btn.dataset.loading) {
        return;
    }
    btn.dataset.loading = '1';
    btn.classList.add('is-loading');
    if (btn.tagName === 'INPUT') {
        // input 的 value 会进 POST 数据：带 name 的按钮不改 value，防止把「处理中…」提交给服务端
        if (!btn.name) {
            btn.dataset.originalText = btn.value;
            btn.value = '处理中…';
        }
    } else {
        btn.dataset.originalText = btn.innerHTML;
        btn.innerHTML = '<span class="btn-spin" aria-hidden="true"></span>处理中…';
    }
}

// 确认弹窗后的提交补反馈：showConfirmDialog 确认后走 form.submit()，
// 不触发 submit 事件、enhanceFormSubmission 的全页遮罩不会出现，
// 慢动作（安装/检测更新/备份等）会白屏等待——这里统一补上遮罩 + 按钮等待态
function afterConfirmSubmit(form, submitter) {
    showPageLoading();
    markSubmitterLoading(submitter || (form ? form.querySelector('button[type="submit"], input[type="submit"]') : null));
}

// 设置按钮加载状态
function setButtonLoading(buttonId, isLoading = true) {
    const button = document.getElementById(buttonId);
    if (!button) return;
    
    if (isLoading) {
        button.classList.add('loading');
        button.disabled = true;
        const originalText = button.innerHTML;
        button.dataset.originalText = originalText;
        button.innerHTML = `<span>${originalText}</span>`;
    } else {
        button.classList.remove('loading');
        button.disabled = false;
        if (button.dataset.originalText) {
            button.innerHTML = button.dataset.originalText;
        }
    }
}

// 表单验证 - 非空检查
function validateRequiredFields(formId) {
    const form = document.getElementById(formId);
    if (!form) return true;
    
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('error');
            
            // 添加错误提示
            let errorElement = field.nextElementSibling;
            if (!errorElement || !errorElement.classList.contains('form-feedback')) {
                errorElement = document.createElement('div');
                errorElement.className = 'form-feedback';
                errorElement.textContent = '此字段为必填项';
                field.parentNode.insertBefore(errorElement, field.nextSibling);
            }
            
            isValid = false;
        } else {
            field.classList.remove('error');
            
            // 移除错误提示
            const errorElement = field.nextElementSibling;
            if (errorElement && errorElement.classList.contains('form-feedback')) {
                errorElement.remove();
            }
        }
    });
    
    return isValid;
}

// 增强的表单验证
function enhancedFormValidation(formId) {
    const form = document.getElementById(formId);
    if (!form) return true;
    
    // 先执行基础验证
    if (!validateRequiredFields(formId)) {
        return false;
    }
    
    // 邮箱验证
    const emailFields = form.querySelectorAll('input[type="email"]');
    emailFields.forEach(field => {
        if (field.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(field.value)) {
            field.classList.add('error');
            
            let errorElement = field.nextElementSibling;
            if (!errorElement || !errorElement.classList.contains('form-feedback')) {
                errorElement = document.createElement('div');
                errorElement.className = 'form-feedback';
                errorElement.textContent = '请输入有效的邮箱地址';
                field.parentNode.insertBefore(errorElement, field.nextSibling);
            }
            
            return false;
        }
    });
    
    return true;
}

// ===== 批量选中计数提示（设计规格：批量选中后工具栏显示「已选 N 项」） =====
// 用法：页面工具栏放 <span id="batch-selected-info" class="batch-selected-info"></span>。
// 行复选框识别规则（依次尝试）：
//   1. name="ids[]"（文章/友链等标准批量表单）
//   2. class 含 _checkbox / -checkbox 的复选框（用户/媒体等页面的自定义行选框）
// 本函数自动监听变化并更新计数文案，无选中时隐藏提示
function initBatchSelectedInfo() {
    const info = document.getElementById('batch-selected-info');
    if (!info) return;

    let boxes = document.querySelectorAll('input[name="ids[]"]');
    if (!boxes.length) {
        boxes = document.querySelectorAll(
            'input[type="checkbox"][class*="_checkbox"], input[type="checkbox"][class*="-checkbox"]'
        );
    }
    if (!boxes.length) return;

    const update = function() {
        const count = document.querySelectorAll('input[name="ids[]"]:checked, input[class*="_checkbox"]:checked, input[class*="-checkbox"]:checked').length;
        info.textContent = count > 0 ? '已选 ' + count + ' 项' : '';
        // 无选中时隐藏胶囊徽标（.show 由 components.css .batch-selected-info 定义）
        info.classList.toggle('show', count > 0);
    };

    // 事件委托统一监听复选框变化：各列表页的「表头全选」脚本是直接改写
    // 行复选框的 checked 属性（不触发行复选框的 change 事件），若逐行绑定
    // 监听会漏计全选场景；委托到 document 后，任意复选框（含表头全选框、
    // 行复选框以及 JS 动态渲染的行）发生变化都会重新计数
    document.addEventListener('change', function (e) {
        // 仅响应复选框变化，避免输入框等无关事件触发多余统计
        if (!e.target || e.target.type !== 'checkbox') {
            return;
        }
        update();
    });
    update();
}

// ===== 全局快捷键（设计规格 5.2：⌘/Ctrl+N 新建文章、G L/G C/G D 序列跳转、Esc 关闭弹层） =====
// ⌘/Ctrl+K（命令面板）与 Esc（抽屉/下拉）由 header.js / initDropdowns / openEntityDrawer
// 各自实现，此处不重复绑定，避免同一按键触发两次。
// 输入保护：焦点在输入框 / 文本域 / 富文本编辑器内时不劫持任何按键。
function initKeyboardShortcuts() {
    // G 序列键的待决状态：记录刚按下的 'g' 及时间戳（1 秒内按下配套键才算有效序列）
    let pendingG = null;

    // 判断焦点是否在输入类控件或编辑器中（与 sidebar.js / header.js 的判定保持一致）
    const isInputFocused = (target) => {
        const el = target && target.closest
            ? target.closest('textarea, input, select, [contenteditable="true"], .note-editor, .CodeMirror, .tox-edit-area, .cke')
            : null;
        return !!el;
    };

    document.addEventListener('keydown', function(e) {
        if (isInputFocused(e.target)) return;

        // Esc：关闭当前打开的确认/提示弹层（modal-overlay.show）与编辑器元信息抽屉；
        // 其余弹层（命令面板/实体抽屉/下拉菜单）已有各自的 Esc 处理
        if (e.key === 'Escape') {
            const openOverlay = document.querySelector('.modal-overlay.show');
            if (openOverlay) {
                openOverlay.classList.remove('show');
            }
            const openEditorDrawer = document.querySelector('.editor-drawer.show');
            if (openEditorDrawer && typeof window.closeEditorDrawer === 'function') {
                window.closeEditorDrawer();
            }
            return;
        }

        // ⌘/Ctrl + N：新建文章（后台全局）。浏览器保留键（如 Chrome 新窗口）无法拦截，
        // 能拦截的环境下提供快捷入口，其余环境仍可用命令面板或菜单进入
        if ((e.ctrlKey || e.metaKey) && (e.key === 'n' || e.key === 'N')) {
            e.preventDefault();
            window.location.href = 'admin.php?action=article&sub=add';
            return;
        }

        // G 序列跳转：G L=文章列表 / G C=评论 / G D=仪表盘
        if (pendingG && (Date.now() - pendingG.time) <= 1000) {
            const routes = { l: 'admin.php?action=article', c: 'admin.php?action=comment', d: 'admin.php?action=dashboard' };
            const target = routes[e.key.toLowerCase()];
            pendingG = null;
            if (target) {
                e.preventDefault();
                window.location.href = target;
            }
            return;
        }

        // 记录 'g' 按键，等待 1 秒内的配套键；单独按 g（或 g 后跟其它键）不影响正常操作
        if (e.key === 'g' || e.key === 'G') {
            pendingG = { time: Date.now() };
        }
    });
}

// 批量操作功能
function performBulkAction(action, formId) {
    const form = document.getElementById(formId);
    if (!form) return;
    
    const checkboxes = form.querySelectorAll('input[type="checkbox"][name="ids[]"]:checked');
    if (checkboxes.length === 0) {
        // 提示文案不带感叹号（文案规范：禁止感叹号）
        showToast('请选择至少一条记录', 'warning');
        return;
    }

    if (action === 'delete') {
        // 统一确认弹窗（danger 删除类），确认后提交批量表单；按钮文案 = 具体动作
        showConfirmDialog('确定要删除选中的记录吗？', { type: 'danger', confirmText: '删除' }).then((ok) => {
            if (ok) {
                // 显示加载状态
                showPageLoading();
                form.submit();
            }
        });
    } else {
        // 其他批量操作
        showPageLoading();
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = action;
        form.appendChild(actionInput);
        form.submit();
    }
}

// 禁用按钮防止重复提交
function disableButtonOnSubmit(buttonId) {
    const button = document.getElementById(buttonId);
    if (button) {
        setButtonLoading(buttonId, true);
    }
}

// 搜索功能
function searchTable(inputId, tableId) {
    const input = document.getElementById(inputId);
    const filter = input.value.toUpperCase();
    const table = document.getElementById(tableId);
    const tr = table.getElementsByTagName('tr');
    
    // 跳过表头行
    for (let i = 1; i < tr.length; i++) {
        let found = false;
        const td = tr[i].getElementsByTagName('td');
        
        for (let j = 0; j < td.length; j++) {
            if (td[j]) {
                const txtValue = td[j].textContent || td[j].innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    found = true;
                    break;
                }
            }
        }
        
        tr[i].style.display = found ? '' : 'none';
    }
}

// 平滑滚动到顶部
function scrollToTop() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}

// 响应式菜单切换
function toggleResponsiveMenu() {
    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
        sidebar.classList.toggle('responsive');
    }
}

// 表格行悬停效果增强（已废弃）：
// tables.css 的 tr:hover { background-color: var(--hover-bg) } 已统一承担悬停反馈，
// 此处原先用 JS 内联写入固定色 #f8f9fa，在暗色主题下会把悬停行染成白色，
// 且内联样式优先级高于样式表，CSS Token 无法覆盖，故整体移除。

// 表单提交增强
function enhanceFormSubmission() {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            // 提交已被其他处理器取消时直接退出（如表单 onsubmit 里 confirm() 点了「取消」
            // 或内联 return false）。否则页面不跳转、遮罩又无人清理，会永久卡在加载层。
            if (e.defaultPrevented) {
                return;
            }
            // 跳过带有no-loading类的表单
            if (form.classList.contains('no-loading')) {
                return;
            }
            
            // 表单验证
            if (!enhancedFormValidation(form.id)) {
                e.preventDefault();
                showToast('请检查表单填写是否正确', 'error');
                return;
            }
            
            // 显示加载状态：全页遮罩 + 触发提交的按钮进入「处理中…」等待态
            showPageLoading();
            markSubmitterLoading(e.submitter);
        });
    });
}

// ===== 统一下拉菜单（点击开合交互）=====
// 适用结构：.btn-group / .dropdown 容器内 <button class="dropdown-toggle"> + .dropdown-menu 兄弟节点。
// 行为：点击切换开合、点击容器外部自动关闭、Esc 关闭、点击菜单项后关闭。
// 样式见 components.css 的 .dropdown-menu 统一体系（圆角/阴影/悬停/入场动画）。
function initDropdowns() {
    // 表格容器内的下拉菜单会被 .table-container 的 overflow-x:auto 裁剪
    // （横向滚动容器同时裁剪纵向溢出，行少时菜单超出容器下边界）。
    // 打开时给容器加 .dropdown-open 临时放开裁剪，关闭时还原。
    function syncTableOverflow(menu, show) {
        const tableContainer = menu.closest('.table-container');
        if (tableContainer) {
            tableContainer.classList.toggle('dropdown-open', show);
        }
    }

    // 关闭当前已打开的全部下拉菜单（菜单与容器同步移除 .show）
    // 注意：关闭时不清除 menu-flip-* 翻转类——面板关闭动画期间仍可见，
    // 移除翻转类会让面板跳回默认对齐位（右缘溢出视口）；翻转类在
    // adjustMenuFlip 打开时总会先移除再重算，无需提前清除
    function closeAllDropdowns(exceptMenu) {
        document.querySelectorAll('.dropdown-menu.show').forEach(function(menu) {
            if (menu !== exceptMenu) {
                menu.classList.remove('show');
                const parent = menu.closest('.btn-group, .dropdown');
                if (parent) {
                    parent.classList.remove('show');
                }
                // 关闭时还原表格容器的溢出裁剪
                syncTableOverflow(menu, false);
            }
        });
    }

    // 点击任意位置：处理切换/外部关闭
    document.addEventListener('click', function(e) {
        // ---- 移动端行操作桥接（iOS Action Sheet）----
        // 窄屏下表格行内「操作」下拉（a.dropdown-toggle.table-link）难以点按、
        // 菜单在表格横滚容器里也局促；改为把同容器 .dropdown-menu 的菜单项
        // 实时转换为底部动作面板（showActionSheet）。动作点击透传回原菜单项
        // click()——原内联 onclick 的 confirmLink 确认链完整保留，零模板改动。
        // 仅拦截行操作（.table-link）：批量操作等工具栏下拉保持原生下拉形态。
        const mobileRowToggle = e.target.closest('a.dropdown-toggle.table-link');
        if (mobileRowToggle && window.matchMedia('(max-width: 768px)').matches) {
            e.preventDefault();
            const rowContainer = mobileRowToggle.closest('.btn-group, .dropdown');
            const rowMenu = rowContainer ? rowContainer.querySelector('.dropdown-menu') : null;
            if (rowMenu) {
                const actions = Array.prototype.map.call(rowMenu.querySelectorAll('li > a'), function(link) {
                    return {
                        label: link.textContent.trim(),
                        danger: link.classList.contains('menu-item-danger'),
                        // 透传点击到原菜单项：href 跳转与 onclick 确认链原样执行
                        onClick: function() { link.click(); }
                    };
                });
                showActionSheet({ title: '行操作', actions: actions });
            }
            return;
        }
        const toggle = e.target.closest('.dropdown-toggle');
        if (toggle) {
            // 找同容器内的下拉菜单（toggle 的父级 .btn-group/.dropdown 内）
            const container = toggle.closest('.btn-group, .dropdown');
            const menu = container ? container.querySelector('.dropdown-menu') : null;
            if (menu) {
                e.preventDefault();
                const willShow = !menu.classList.contains('show');
                // 同时只保留一个下拉打开
                closeAllDropdowns(willShow ? menu : null);
                menu.classList.toggle('show', willShow);
                if (willShow) {
                    // 防视口溢出：右缘/下缘放不下时翻转对齐方向（移动端/窄屏适配）
                    adjustMenuFlip(menu);
                } else {
                    clearMenuFlip(menu);
                }
                // 打开时放开表格容器裁剪，避免菜单被 overflow-x:auto 裁掉
                syncTableOverflow(menu, willShow);
                // 容器同步加 .show（兼容 .btn-group.show .dropdown-menu 旧选择器）
                if (container) {
                    container.classList.toggle('show', willShow);
                }
            }
            return;
        }
        // 点击菜单内部（非 toggle）：菜单项点击后关闭本菜单
        // （同样保留翻转类，原因见 closeAllDropdowns 注释）
        const insideMenu = e.target.closest('.dropdown-menu');
        if (insideMenu) {
            insideMenu.classList.remove('show');
            // 菜单项点击后同样还原表格容器裁剪
            syncTableOverflow(insideMenu, false);
            const insideParent = insideMenu.closest('.btn-group, .dropdown');
            if (insideParent) {
                insideParent.classList.remove('show');
            }
            return;
        }
        // 点击容器外部：关闭全部
        closeAllDropdowns(null);
    });

    // Esc 键关闭全部下拉
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAllDropdowns(null);
        }
    });
}

// ===== 表头列筛选面板（点击开合 + 搜索 + 全选 + 重置/确定提交）=====
// 适用结构：th.th-filterable 内 .th-filter-btn（漏斗按钮）+ .col-filter-panel（筛选面板，
// 面板 data-param 记录 URL 参数名，选项 value 由模板按当前 URL 状态预勾选）。
// 行为：点击漏斗开合、点击外部/Esc 关闭；搜索框按选项文字实时过滤；全选行联动可见选项；
// 「确定」将勾选值以逗号分隔写回 URL 参数（全选/全不选 = 移除参数，回到第 1 页）；
// 「重置」清空该筛选参数立即生效。开合动画与表格容器溢出放行机制同 initDropdowns。
function initColumnFilter() {
    // 表格容器 overflow-x:auto 会裁剪纵向溢出，面板打开时临时放开（同下拉菜单处理）
    function syncTableOverflow(panel, show) {
        const tableContainer = panel.closest('.table-container');
        if (tableContainer) {
            tableContainer.classList.toggle('dropdown-open', show);
        }
    }

    // 关闭全部已打开的筛选面板（可指定例外面板）
    function closeAllPanels(exceptPanel) {
        document.querySelectorAll('.col-filter-panel.show').forEach(function(panel) {
            if (panel !== exceptPanel) {
                panel.classList.remove('show');
                syncTableOverflow(panel, false);
            }
        });
    }

    // 按搜索关键字过滤选项（匹配选项文字，隐藏不匹配行；全选行始终显示），
    // 并同步空态提示与「确定」按钮禁用状态（无可见选项 = 无匹配 = 不可确定）
    function filterOptions(panel, keyword) {
        const kw = keyword.trim().toLowerCase();
        let visibleCount = 0;
        panel.querySelectorAll('.cfp-option:not(.cfp-option-all)').forEach(function(option) {
            const label = option.querySelector('.cfp-option-label');
            const match = !kw || (label && label.textContent.toLowerCase().indexOf(kw) !== -1);
            option.style.display = match ? '' : 'none';
            if (match) {
                visibleCount++;
            }
        });
        // 空态提示：无匹配时显示「暂无匹配数据」
        const emptyTip = panel.querySelector('.cfp-empty');
        if (emptyTip) {
            emptyTip.classList.toggle('show', visibleCount === 0);
        }
        // 确定按钮：无可见选项时禁用，避免提交一个无效筛选
        const okBtn = panel.querySelector('.cfp-ok');
        if (okBtn) {
            okBtn.disabled = visibleCount === 0;
        }
    }

    // 收集面板勾选值并写回 URL：全选/全不选视为不筛选（移除参数），其余逗号拼接
    function applyFilter(panel) {
        const param = panel.getAttribute('data-param');
        const values = Array.prototype.map.call(
            panel.querySelectorAll('.cfp-check:checked'),
            function(cb) { return cb.value; }
        );
        const total = panel.querySelectorAll('.cfp-check').length;
        const params = new URLSearchParams(window.location.search);
        params.delete('page'); // 筛选条件变化后回到第 1 页
        if (values.length === 0 || values.length === total) {
            params.delete(param);
        } else {
            params.set(param, values.join(','));
        }
        window.location.search = params.toString();
    }

    // 重置：清空该筛选参数并立即跳转（回到第 1 页）
    function resetFilter(panel) {
        const param = panel.getAttribute('data-param');
        const params = new URLSearchParams(window.location.search);
        params.delete('page');
        params.delete(param);
        window.location.search = params.toString();
    }

    document.addEventListener('click', function(e) {
        // 点击其他下拉触发按钮时同步收起筛选面板（两套浮层互斥）
        if (e.target.closest('.dropdown-toggle')) {
            closeAllPanels(null);
            return;
        }

        // 漏斗按钮：切换开合
        const btn = e.target.closest('.th-filter-btn');
        if (btn) {
            const th = btn.closest('th');
            const panel = th ? th.querySelector('.col-filter-panel') : null;
            if (panel) {
                // 窄屏（≤768px）：侧弹面板拇指不可达，转为底部半屏面板。
                // 面板 DOM 原位移入 sheet 内容区（事件委托是 document 级的，
                // 对 .col-filter-panel 的命中不受位置影响）；关闭时归位表头。
                if (window.matchMedia('(max-width: 768px)').matches) {
                    if (panel._sheet) return; // 已在半屏面板中，防重复打开
                    closeAllPanels(null);
                    // 标题取列名（表头内 .th-filter-label 文本，col_filter.html 生成；
                    // aria-label「筛选XX」兜底），最终回退「列」
                    const label = th.querySelector('.th-filter-label');
                    const colName = label ? label.textContent.trim()
                        : (btn.getAttribute('aria-label') || '').replace(/^筛选/, '') || '列';
                    panel._sheet = showSheetModal({
                        title: '筛选：' + colName,
                        content: panel,
                        onClose: function() {
                            // 归位：面板移回表头并清除打开标记（cfp-ok/cfp-reset
                            // 会整页跳转，无需归位；此处覆盖手动关闭的路径）
                            panel._sheet = null;
                            th.appendChild(panel);
                        }
                    });
                    return;
                }
                const willShow = !panel.classList.contains('show');
                closeAllPanels(willShow ? panel : null);
                panel.classList.toggle('show', willShow);
                syncTableOverflow(panel, willShow);
                if (willShow) {
                    // 每次打开重置搜索关键字，展示完整选项列表
                    const search = panel.querySelector('.cfp-search');
                    if (search) {
                        search.value = '';
                        filterOptions(panel, '');
                    }
                }
            }
            return;
        }

        // 面板内部点击：处理确定/重置；其余点击（搜索、选项）不关闭面板
        const panel = e.target.closest('.col-filter-panel');
        if (panel) {
            if (e.target.classList.contains('cfp-ok')) {
                applyFilter(panel);
            } else if (e.target.classList.contains('cfp-reset')) {
                resetFilter(panel);
            }
            return;
        }

        // 点击面板与漏斗之外：关闭全部
        closeAllPanels(null);
    });

    // Esc 键关闭全部筛选面板
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAllPanels(null);
        }
    });

    // 搜索框输入：实时过滤选项（事件委托绑定到面板内输入）
    document.addEventListener('input', function(e) {
        if (e.target.classList && e.target.classList.contains('cfp-search')) {
            const panel = e.target.closest('.col-filter-panel');
            if (panel) {
                filterOptions(panel, e.target.value);
            }
        }
    });

    // 全选行联动：勾选状态同步到当前可见（未被搜索过滤）的选项
    document.addEventListener('change', function(e) {
        if (e.target.classList && e.target.classList.contains('cfp-select-all')) {
            const panel = e.target.closest('.col-filter-panel');
            if (panel) {
                panel.querySelectorAll('.cfp-option:not(.cfp-option-all)').forEach(function(option) {
                    if (option.style.display !== 'none') {
                        option.querySelector('.cfp-check').checked = e.target.checked;
                    }
                });
            }
        }
        // 选项勾选变化时反向同步全选行（全部可见选项勾选 = 全选选中，否则取消）
        if (e.target.classList && e.target.classList.contains('cfp-check')) {
            const panel = e.target.closest('.col-filter-panel');
            if (panel) {
                const allCheckbox = panel.querySelector('.cfp-select-all');
                if (allCheckbox) {
                    const checks = panel.querySelectorAll('.cfp-check');
                    const checkedCount = panel.querySelectorAll('.cfp-check:checked').length;
                    allCheckbox.checked = checks.length > 0 && checkedCount === checks.length;
                }
            }
        }
    });
}

// ===== 密码输入框显隐切换（眼睛图标，全后台自动生效）=====
// 扫描页面所有 input[type="password"]，外包 .password-input-wrap 容器并
// 在尾部插入眼睛按钮：默认「显示」态用 eye 图标，点击后转明文并切换为
// eye-invisible 图标。SVG 采用统一图标源（viewBox 64 64 896 896）。
// 样式见 auth.css（独立认证页）与 forms.css（后台通用）的 .password-input-wrap。
// 动态内容：@param root 缺省 document；向 DOM 动态插入含密码框的表单后，
// 手动调用 initPasswordVisibility(container) 增量增强（与 initCustomSelects 同款用法）。
function initPasswordVisibility(root) {
    root = root || document;
    const EYE_ICON = '<svg viewBox="64 64 896 896" focusable="false" data-icon="eye" width="1em" height="1em" fill="currentColor" aria-hidden="true"><path d="M942.2 486.2C847.4 286.5 704.1 186 512 186c-192.2 0-335.4 100.5-430.2 300.3a60.3 60.3 0 000 51.5C176.6 737.5 319.9 838 512 838c192.2 0 335.4-100.5 430.2-300.3 7.7-16.2 7.7-35 0-51.5zM512 766c-161.3 0-279.4-81.8-362.7-254C232.6 339.8 350.7 258 512 258c161.3 0 279.4 81.8 362.7 254C791.5 684.2 673.4 766 512 766zm-4-430c-97.2 0-176 78.8-176 176s78.8 176 176 176 176-78.8 176-176-78.8-176-176-176zm0 288c-61.9 0-112-50.1-112-112s50.1-112 112-112 112 50.1 112 112-50.1 112-112 112z"></path></svg>';
    const EYE_INVISIBLE_ICON = '<svg viewBox="64 64 896 896" focusable="false" data-icon="eye-invisible" width="1em" height="1em" fill="currentColor" aria-hidden="true"><path d="M942.2 486.2Q889.47 375.11 816.7 305l-50.88 50.88C807.31 395.53 843.45 447.4 874.7 512 791.5 684.2 673.4 766 512 766q-72.67 0-133.87-22.38L323 798.75Q408 838 512 838q288.3 0 430.2-300.3a60.29 60.29 0 000-51.5zm-63.57-320.64L836 122.88a8 8 0 00-11.32 0L715.31 232.2Q624.86 186 512 186q-288.3 0-430.2 300.3a60.3 60.3 0 000 51.5q56.69 119.4 136.5 191.41L112.48 835a8 8 0 000 11.31L155.17 889a8 8 0 0011.31 0l712.15-712.12a8 8 0 000-11.32zM149.3 512C232.6 339.8 350.7 258 512 258c54.54 0 104.13 9.36 149.12 28.39l-70.3 70.3a176 176 0 00-238.13 238.13l-83.42 83.42C223.1 637.49 183.3 582.28 149.3 512zm246.7 0a112.11 112.11 0 01146.2-106.69L401.31 546.2A112 112 0 01396 512z"></path><path d="M508 624c-3.46 0-6.87-.16-10.25-.47l-52.82 52.82a176.09 176.09 0 00227.42-227.42l-52.82 52.82c.31 3.38.47 6.79.47 10.25a111.94 111.94 0 01-112 112z"></path></svg>';

    root.querySelectorAll('input[type="password"]').forEach(function(input) {
        // 防止重复包裹（二次初始化/动态场景）
        if (input.parentElement && input.parentElement.classList.contains('password-input-wrap')) {
            return;
        }
        // 包裹容器：定位锚点，让眼睛按钮叠在输入框右侧
        const wrap = document.createElement('div');
        wrap.className = 'password-input-wrap';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);

        // 眼睛按钮：type=button 避免触发表单提交
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'password-toggle-btn';
        btn.setAttribute('aria-label', '显示密码');
        btn.title = '显示密码';
        btn.innerHTML = EYE_ICON;
        btn.addEventListener('click', function() {
            const showPassword = input.type === 'password';
            input.type = showPassword ? 'text' : 'password';
            btn.innerHTML = showPassword ? EYE_INVISIBLE_ICON : EYE_ICON;
            btn.title = showPassword ? '隐藏密码' : '显示密码';
            btn.setAttribute('aria-label', btn.title);
            // 切换后保持输入焦点不丢失
            input.focus();
        });
        wrap.appendChild(btn);
    });
}

/* ============================================================
   可清空输入框（textarea + url 输入框的行内「清空输入」按钮）
   原理：自动增强 —— 包裹定位容器并追加行内 × 按钮，控件有值时
   悬停/聚焦浮现，点击一键清空并派发 input + change 事件，
   因此设置页的值快照 dirty 检测（initFormSectionNav）实时联动。
   白名单（仅这两类值得配，克制原则）：
   - textarea：内容长，一键归零比全选删除省心；
   - input[type=url]：长粘贴值，清空重粘比定位改片段快。
   排除（不增强，保持原生）：
   - name="content" 的 textarea：文章/页面/评论/邮件模板正文编辑器，
     超长内容误触清空是灾难，绝不能配一键清空；
   - readonly / disabled 控件：无清空意义。
   说明：file 输入框不在此列——后台全部 file 均为隐藏式
   （.upload-hidden-input，由上传卡片驱动），行内 × 无处安放，
   且已有各自的「取消选择」机制。
   幂等：无全局守卫，靠 .clearable-wrap 包裹检查保证可重复调用——
   全局守卫会阻止带 root 参数的二次调用（动态增强盲区）。
   动态内容：@param root 缺省 document；向 DOM 动态插入含 textarea /
   url 输入框的表单后，手动调用 initClearableInputs(container) 增量增强
   （与 initCustomSelects 同款用法）。
   样式见 forms.css 的 .clearable-wrap 组件区。
   ============================================================ */
const CLEARABLE_X_ICON = '<svg viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"></path></svg>';

function initClearableInputs(root) {
    root = root || document;

    root.querySelectorAll('textarea, input[type="url"]').forEach(function(el) {
        // 正文编辑器排除：清空整篇内容风险过大
        if (el.name === 'content') return;
        // 只读 / 禁用控件无清空意义
        if (el.readOnly || el.disabled) return;
        // 已包裹的防重复处理（动态/二次初始化场景）
        if (el.parentElement && el.parentElement.classList.contains('clearable-wrap')) return;

        // 包裹容器：定位锚点，让 × 按钮叠在控件右上角（textarea）或右侧（url）
        const wrap = document.createElement('div');
        wrap.className = 'clearable-wrap';
        el.parentNode.insertBefore(wrap, el);
        wrap.appendChild(el);

        // 清空按钮：type=button 避免触发表单提交；tabindex=-1 纯鼠标辅助操作
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'clearable-btn';
        btn.setAttribute('aria-label', '清空输入');
        btn.title = '清空输入';
        btn.innerHTML = CLEARABLE_X_ICON;
        btn.addEventListener('click', function(e) {
            // 阻止焦点抢占：清空后焦点交还控件，便于直接重输
            e.preventDefault();
            if (!el.value) return;
            el.value = '';
            // 派发 input + change：联动值快照 dirty 检测（保存栏/红点/chips 实时更新）
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
            el.focus();
        });
        wrap.appendChild(btn);

        // 有值标记：驱动 × 按钮的浮现（悬停/聚焦时才显示，平时零视觉噪音）
        const syncFilled = function() {
            wrap.classList.toggle('is-filled', el.value.trim() !== '');
        };
        el.addEventListener('input', syncFilled);
        syncFilled();
    });
}

/* ============================================================
   步进器（Stepper，input[type=number] 自动增强）
   原理：自动增强 —— 包裹定位容器并在右侧内嵌「− / +」按钮组，
   尊重控件原生 min / max / step 属性，到达边界自动禁用对应按钮；
   步进后派发 input + change 事件，因此设置页的值快照 dirty 检测
   （initFormSectionNav）实时联动；键盘 ↑/↓ 仍走原生行为。
   交互细节：
   - 长按 − / + 连续步进（500ms 起步延迟，之后 120ms/步）；
   - 原生 spin 小箭头在包裹内隐藏（避免双 UI，见 forms.css）。
   排除（不增强，保持原生）：
   - .input-narrow：排序等 100px 窄输入框放不下按钮组；
   - readonly / disabled 控件。
   幂等：无全局守卫，靠 .stepper-wrap 包裹检查保证可重复调用——
   全局守卫会阻止带 root 参数的二次调用（动态增强盲区）。
   动态内容：@param root 缺省 document；向 DOM 动态插入含数字输入框
   的表单后，手动调用 initSteppers(container) 增量增强（与
   initCustomSelects 同款用法）。
   样式见 forms.css 的 .stepper-wrap 组件区。
   ============================================================ */
const STEPPER_MINUS_ICON = '<svg viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14"></path></svg>';
const STEPPER_PLUS_ICON = '<svg viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"></path></svg>';

function initSteppers(root) {
    root = root || document;

    root.querySelectorAll('input[type="number"]').forEach(function(input) {
        // 窄排序框放不下按钮组；只读/禁用无步进意义
        if (input.classList.contains('input-narrow')) return;
        if (input.readOnly || input.disabled) return;
        // 已包裹的防重复处理（动态/二次初始化场景）
        if (input.parentElement && input.parentElement.classList.contains('stepper-wrap')) return;

        // 数值边界：min/max 属性缺省视为无界
        function bounds() {
            return {
                min: input.min === '' ? -Infinity : parseFloat(input.min),
                max: input.max === '' ? Infinity : parseFloat(input.max)
            };
        }

        // 单步进：值 + dir*step，并夹取到 [min, max] 区间；步进结果按 step
        // 的小数位数取整，规避 0.1+0.2 之类的浮点误差（0.30000000000000004）
        function stepBy(dir) {
            const step = parseFloat(input.step);
            const safeStep = (!isNaN(step) && step > 0) ? step : 1;
            const b = bounds();
            const cur = parseFloat(input.value);
            // 空值起步：从最近的有限边界开始（无边界则 0），避免「空 + 1 = NaN」
            let next = isNaN(cur)
                ? (dir > 0 ? (b.min === -Infinity ? 0 : b.min) : (b.max === Infinity ? 0 : b.max))
                : cur + dir * safeStep;
            next = Math.min(b.max, Math.max(b.min, next));
            const decimals = (String(safeStep).split('.')[1] || '').length;
            input.value = next.toFixed(decimals);
            // 派发 input + change：联动值快照 dirty 检测与既有 change 监听
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }

        // 边界禁用同步：到下界禁 −、到上界禁 +（min/max 缺省视为无界）
        function syncDisabled() {
            const cur = parseFloat(input.value);
            const b = bounds();
            minusBtn.disabled = !isNaN(cur) && cur <= b.min;
            plusBtn.disabled = !isNaN(cur) && cur >= b.max;
        }

        // 创建单个步进按钮：单击一步；按住 500ms 后连续步进（120ms/步）。
        // 用 pointer 事件统一鼠标/触屏；长按重复在 pointerdown 启动，抬起即停
        function createBtn(dir, icon, label) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'stepper-btn';
            btn.setAttribute('aria-label', label);
            btn.title = label;
            btn.innerHTML = icon;
            let holdTimer = null;
            let repeatTimer = null;
            const stopHold = function() {
                clearTimeout(holdTimer);
                clearInterval(repeatTimer);
                holdTimer = null;
                repeatTimer = null;
            };
            btn.addEventListener('pointerdown', function(e) {
                if (btn.disabled) return;
                e.preventDefault(); // 防触屏长按选中文字/弹出系统菜单
                stepBy(dir);
                syncDisabled();
                holdTimer = setTimeout(function() {
                    repeatTimer = setInterval(function() {
                        stepBy(dir);
                        syncDisabled();
                    }, 120);
                }, 500);
            });
            ['pointerup', 'pointerleave', 'pointercancel'].forEach(function(evt) {
                btn.addEventListener(evt, stopHold);
            });
            // 键盘可达性：Enter/Space 触发的 click 无 pointer 前置（e.detail=0），
            // 此处补步进；指针点击的 click（detail≥1）已在 pointerdown 步进过，跳过防双步
            btn.addEventListener('click', function(e) {
                if (btn.disabled || e.detail !== 0) return;
                stepBy(dir);
                syncDisabled();
            });
            return btn;
        }

        const minusBtn = createBtn(-1, STEPPER_MINUS_ICON, '减少');
        const plusBtn = createBtn(1, STEPPER_PLUS_ICON, '增加');
        const btnGroup = document.createElement('div');
        btnGroup.className = 'stepper-btns';
        btnGroup.appendChild(minusBtn);
        btnGroup.appendChild(plusBtn);

        // 包裹容器：定位锚点，按钮组内嵌于控件右侧（同 .clearable-wrap 模式）
        const wrap = document.createElement('div');
        wrap.className = 'stepper-wrap';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);
        // 标记类：驱动 forms.css 的高特异性 padding 规则（预留按钮组空间，
        // 压过元素级 8-:not 基线的 0,8,1 特异性，同 .input-narrow 先例）
        input.classList.add('stepper-input');
        wrap.appendChild(btnGroup);

        // 输入时同步边界禁用态（手动输入也可能触界）
        input.addEventListener('input', syncDisabled);
        syncDisabled();
    });
}

/* ============================================================
   自定义下拉选择器
   原理：自动增强 —— 扫描全后台原生 <select>，在原位置生成
   「触发器 + 弹出面板」自定义结构，原生 select 视觉隐藏但保留在
   DOM 中；所有选择实时同步回原生 select 并派发 change 事件，
   因此表单提交、既有的 change 监听（如筛选自动提交）完全兼容。
   交互：点击开合 / 上下箭头移动 / Enter/Space 选中 / Esc 关闭 /
   外点关闭；ARIA listbox 语义由本组件维护。
   说明：动态插入的 select 需手动调用 initCustomSelects(container)；
   multiple/size>1/disabled 的 select 自动跳过，保持原生行为。
   样式见 common.css 的 .ui-select 组件区。
   ============================================================ */
function initCustomSelects(root) {
    root = root || document;
    // 增强全部单选下拉（含初始不可见区域：折叠筛选/弹窗/抽屉内的 select，
    // 容器显示后即以自定义面板呈现，避免出现两套下拉形态）；
    // 已增强的（data-ui-select 标记）防重复处理
    root.querySelectorAll('select:not([multiple]):not([size]):not([disabled]):not([data-ui-select])').forEach(function(select) {
        select.dataset.uiSelect = '1';

        // ---- 包一层定位容器：承接触发器与面板的 absolute 定位 ----
        // 宽度复制（保持增强前后布局一致）必须在包裹/隐藏之前测量：
        // 原生 select 一旦加上 .ui-select-source（absolute + 1px）就脱离布局流，
        // 此时 offsetWidth 已塌缩，复制到的是错误宽度（移动端筛选下拉被压成小方块即此因）
        const nativeWidth = (select.offsetParent && select.offsetWidth > 0) ? select.offsetWidth : 0;

        const wrap = document.createElement('div');
        wrap.className = 'ui-select';
        // 筛选区下拉（.filter-select）标记为紧凑规格：触发器高度对齐
        // 缓存设置页「清理缓存」小按钮（30px），样式见 common.css 的
        // .ui-select--compact 区块
        if (select.classList.contains('filter-select')) {
            wrap.classList.add('ui-select--compact');
        }
        select.parentNode.insertBefore(wrap, select);
        wrap.appendChild(select);
        // 原生 select 转为视觉隐藏的「数据源」（样式见 common.css .ui-select-source）
        select.classList.add('ui-select-source');
        // 回填原生宽度（仅页面加载时可见的 select 可测量；
        // 隐藏容器内的不设置，显示后由内容自适应）
        if (nativeWidth > 0) {
            wrap.style.width = nativeWidth + 'px';
        }

        // ---- 触发器按钮：显示当前选中项文本，type=button 防表单误提交 ----
        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'ui-select-trigger';
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        // 箭头由 CSS mask 绘制（.ui-select-arrow），展开时旋转 180°
        trigger.innerHTML = '<span class="ui-select-text"></span><span class="ui-select-arrow"></span>';
        wrap.appendChild(trigger);

        // ---- 弹出面板：listbox 语义，option 项由原生 options 映射生成 ----
        const menu = document.createElement('ul');
        menu.className = 'ui-select-menu';
        menu.setAttribute('role', 'listbox');
        menu.tabIndex = -1;
        wrap.appendChild(menu);

        // 依据原生 select 的 option/optgroup 构建面板项（含禁用项与分组标签）
        function buildMenu() {
            menu.innerHTML = '';
            Array.prototype.forEach.call(select.children, function(child) {
                if (child.tagName === 'OPTGROUP') {
                    const group = document.createElement('li');
                    group.className = 'ui-select-group-label';
                    group.textContent = child.label;
                    menu.appendChild(group);
                    Array.prototype.forEach.call(child.children, function(opt) {
                        menu.appendChild(buildOption(opt));
                    });
                } else if (child.tagName === 'OPTION') {
                    menu.appendChild(buildOption(child));
                }
            });
        }
        // 单个 option → 面板项；data-value 记录值，禁用项阻断选择。
        // tabIndex=-1 使 li 可被编程聚焦（键盘导航的前提，原生 li 不可聚焦）
        function buildOption(opt) {
            const li = document.createElement('li');
            li.className = 'ui-select-option';
            li.setAttribute('role', 'option');
            li.tabIndex = -1;
            li.dataset.value = opt.value;
            // trim 模板缩进空白：原生 select 显示文本时浏览器自动压缩，
            // 自定义面板/触发器需手动去除首尾空白
            li.textContent = opt.textContent.trim();
            if (opt.disabled) {
                li.classList.add('ui-select-option--disabled');
                li.setAttribute('aria-disabled', 'true');
            }
            return li;
        }

        // ---- 触发器文本与选中态同步（幂等，原生 change/程序赋值均会刷新） ----
        function syncFromSelect() {
            const selected = select.selectedOptions[0];
            trigger.querySelector('.ui-select-text').textContent = selected ? selected.textContent : '';
            menu.querySelectorAll('.ui-select-option').forEach(function(li) {
                const isOn = li.dataset.value === select.value;
                li.classList.toggle('is-selected', isOn);
                if (isOn) {
                    li.setAttribute('aria-selected', 'true');
                } else {
                    li.removeAttribute('aria-selected');
                }
            });
        }
        buildMenu();
        syncFromSelect();
        // 外部（脚本/表单重置等）修改 select 值时同步显示；本组件派发的 change 也会走到这里，幂等无副作用
        select.addEventListener('change', syncFromSelect);

        // ---- 开合控制 ----
        function isOpen() {
            return wrap.classList.contains('is-open');
        }
        function open() {
            // 关闭页面上其他已打开的下拉，保证同屏只有一个面板
            closeAllSelects(wrap);
            wrap.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
            // 防视口溢出：右缘/下缘放不下时翻转对齐方向（移动端/窄屏适配）
            adjustMenuFlip(menu);
            // 打开后将焦点移到当前选中项（或第一个可用项），支持直接键盘操作
            const current = menu.querySelector('.ui-select-option.is-selected:not(.ui-select-option--disabled)') || menu.querySelector('.ui-select-option:not(.ui-select-option--disabled)');
            if (current) {
                current.focus();
            }
        }
        function close() {
            wrap.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
            // 保留翻转类：关闭动画期间移除会让面板跳回默认对齐位（见 adjustMenuFlip 注释）
        }
        // 面板项点击选中：写回原生 select 并派发冒泡 change（兼容既有监听）
        menu.addEventListener('click', function(e) {
            const li = e.target.closest('.ui-select-option');
            if (!li || li.classList.contains('ui-select-option--disabled')) {
                return;
            }
            select.value = li.dataset.value;
            select.dispatchEvent(new Event('change', { bubbles: true }));
            close();
            trigger.focus();
        });
        // 面板键盘导航：上下移动焦点、Enter/Space 选中、Esc/Tab 关闭
        menu.addEventListener('keydown', function(e) {
            const options = Array.prototype.filter.call(menu.querySelectorAll('.ui-select-option'), function(li) {
                return !li.classList.contains('ui-select-option--disabled');
            });
            const index = options.indexOf(document.activeElement);
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (options[index + 1]) {
                    options[index + 1].focus();
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (options[index - 1]) {
                    options[index - 1].focus();
                }
            } else if (e.key === 'Home') {
                e.preventDefault();
                if (options[0]) {
                    options[0].focus();
                }
            } else if (e.key === 'End') {
                e.preventDefault();
                if (options[options.length - 1]) {
                    options[options.length - 1].focus();
                }
            } else if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                if (document.activeElement && document.activeElement.classList.contains('ui-select-option')) {
                    document.activeElement.click();
                }
            } else if (e.key === 'Escape' || e.key === 'Tab') {
                close();
                if (e.key === 'Escape') {
                    trigger.focus();
                }
            }
        });
        // 触发器键盘：下箭头/Enter/Space 打开（Enter/Space 原生 button 语义即点击，此处仅补下箭头）
        trigger.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowDown' && !isOpen()) {
                e.preventDefault();
                open();
            }
        });
        trigger.addEventListener('click', function() {
            if (isOpen()) {
                close();
            } else {
                open();
            }
        });
    });
}

// 弹出面板防视口溢出（移动端/平板/窄屏适配，.ui-select-menu 与 .dropdown-menu 共用）：
// 在面板可见后测量其视口位置——右缘超出时改为右对齐（menu-flip-x），
// 下缘超出且上方空间更大时改为向上弹出（menu-flip-y）。
// 翻转类由 CSS 提供定位规则（common.css / components.css 的 .menu-flip-* 区）。
function adjustMenuFlip(menu) {
    menu.classList.remove('menu-flip-x', 'menu-flip-y');
    const rect = menu.getBoundingClientRect();
    const vw = document.documentElement.clientWidth;
    const vh = document.documentElement.clientHeight;
    const edge = 8; // 视口安全边距：距边缘不足 8px 视为溢出
    if (rect.right > vw - edge) {
        menu.classList.add('menu-flip-x');
    }
    if (rect.bottom > vh - edge) {
        menu.classList.add('menu-flip-y');
    }
}

// 清除面板翻转态（关闭面板时调用，保证下次打开重新按当前位置计算）
function clearMenuFlip(menu) {
    menu.classList.remove('menu-flip-x', 'menu-flip-y');
}

// 关闭页面上所有已展开的自定义下拉（excludeWrap 用于开合切换时跳过自身）
function closeAllSelects(excludeWrap) {
    document.querySelectorAll('.ui-select.is-open').forEach(function(wrap) {
        if (wrap === excludeWrap) {
            return;
        }
        wrap.classList.remove('is-open');
        wrap.querySelector('.ui-select-trigger').setAttribute('aria-expanded', 'false');
        // 保留翻转类：关闭动画期间移除会让面板跳回默认对齐位（见 closeAllDropdowns 注释）
    });
}

// 自定义下拉的全局关闭监听：外点 / 页面滚动 / 窗口尺寸变化时收起面板
// （面板为 absolute 定位随触发器所在文档流，滚动会导致面板与触发器错位，故直接关闭）
function bindGlobalSelectClose() {
    // 防重复绑定守卫：footer.html 的防闪烁内联脚本会提前调用本函数，
    // DOMContentLoaded 回调随后会再调一次；document/window 级监听没有
    // 类似 select 的 data-ui-select 幂等标记，需自行防重，避免重复关闭调用
    if (bindGlobalSelectClose._bound) {
        return;
    }
    bindGlobalSelectClose._bound = true;
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.ui-select')) {
            closeAllSelects();
        }
    });
    window.addEventListener('scroll', closeAllSelects, true);
    window.addEventListener('resize', closeAllSelects);
}

/**
 * Segmented 分段控制器滑块（common.css 的 .seg 组件增强）
 *
 * 为页面上所有 .seg 容器注入一个 .seg-thumb 滑块元素（绝对定位在槽内），
 * 选中块的白底浮起效果由滑块承担并跟随 .seg-active 项滑动（transform 过渡），
 * 分段项自身在 .has-thumb 容器内不再绘制背景（CSS 配合，JS 不可用时优雅降级
 * 回退到 seg-item.seg-active 自带底色）。
 * 监听方式：MutationObserver 观察容器内 class 属性变化（各页面 JS 用
 * classList.toggle 切换 seg-active，无需感知本增强）；窗口缩放时重算位置。
 */
function initSegmentedThumbs() {
    document.querySelectorAll('.seg').forEach(function (seg) {
        var items = seg.querySelectorAll('.seg-item');
        if (items.length === 0 || seg.querySelector('.seg-thumb')) {
            return; // 空分组或已初始化：跳过
        }

        // 创建滑块并插入槽内（容器 .seg 在 CSS 中为 position:relative 定位基准）
        var thumb = document.createElement('span');
        thumb.className = 'seg-thumb';
        seg.classList.add('has-thumb');
        seg.appendChild(thumb);

        // 把滑块平移到指定分段项的位置（transform + width，CSS transition 负责滑动动画）
        function moveTo(item, animate) {
            if (!item) return;
            if (!animate) {
                // 初始定位：临时关闭过渡，避免页面加载时滑块从 0 位置飘过来
                thumb.style.transition = 'none';
            }
            thumb.style.width = item.offsetWidth + 'px';
            thumb.style.transform = 'translateX(' + (item.offsetLeft - 2) + 'px)';
            // offsetLeft 以 .seg 的 padding-box 左缘为基准（含 2px 内边距），平移量扣除同值
            if (!animate) {
                void thumb.offsetWidth; // 强制重排后再恢复过渡
                thumb.style.transition = '';
            }
        }

        // 当前激活项（无 seg-active 时不显示滑块）
        function activeItem() {
            return seg.querySelector('.seg-item.seg-active');
        }

        function refresh(animate) {
            var active = activeItem();
            thumb.style.opacity = active ? '1' : '0';
            if (active) {
                moveTo(active, animate);
            }
        }

        // 观察 seg-active 在分段项之间的切换（各页面 JS classList.toggle 触发）
        var observer = new MutationObserver(function () { refresh(true); });
        observer.observe(seg, { subtree: true, attributes: true, attributeFilter: ['class'] });

        // 窗口缩放时按新布局重新定位（不播放动画，直接归位）
        window.addEventListener('resize', function () { refresh(false); });

        // 首次定位（无动画）
        refresh(false);
    });
}

/* ============================================================
   设置页表单增强：分组锚点导航 + 粘性保存栏
   适用条件：.form-container 内含 ≥2 个 .form-section 的长表单
   （目前仅 config 各设置子页使用 .form-section，其他页面不受影响）。
   解决两个问题：
   1) 设置项多难定位：表单顶部自动生成粘性锚点 Tab（样式见
      components.css 的 .form-nav-bar 区），点击平滑滚动到分组，
      滚动时自动高亮当前所在分组（scrollspy）
   2) 保存按钮在底部：表单内容变更（dirty）且原生保存按钮滚出
      视口时，底部滑入粘性保存栏（红点提示 + 保存按钮，点击等效
      原按钮提交）；Ctrl/Cmd+S 快捷保存
   全部为前端增强，不改变表单结构与提交行为
   ============================================================ */
function initFormSectionNav() {
    document.querySelectorAll('.form-container form').forEach(function (form) {
        // 幂等标记：重复调用直接跳过（动态渲染场景防御）
        if (form.dataset.formNavInit) {
            return;
        }
        const sections = Array.prototype.slice.call(form.querySelectorAll('.form-section'));
        if (sections.length < 2) {
            return; // 非分组长表单（普通编辑页等）：不增强
        }
        form.dataset.formNavInit = '1';

        var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        // ---- 1) 分组锚点 Tab 条（粘性吸附全局头部下方） ----
        const bar = document.createElement('nav');
        bar.className = 'form-nav-bar';
        bar.setAttribute('aria-label', '设置分组导航');
        const navItems = [];
        sections.forEach(function (section, i) {
            const title = section.querySelector('h4');
            if (!title) {
                return; // 无标题的分组不进锚点（锚点必须有可点击的名称）
            }
            const id = section.id || ('cfg-section-' + (i + 1));
            section.id = id;
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'form-nav-item';
            item.textContent = title.textContent.trim();
            item.dataset.targetId = id;
            item.addEventListener('click', function () {
                // 点击后锁定 scrollspy（平滑滚动过程中各组会依次掠过阈值，
                // 不锁定会出现高亮连跳），滚动到位后由 spy 解锁
                lockTarget = id;
                setActive(id);
                section.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'start' });
            });
            bar.appendChild(item);
            navItems.push(item);
        });
        if (navItems.length < 2) {
            return; // 有效锚点不足 2 个：不增强（避免无意义的导航条）
        }
        // 插到表单最前面（表单内第一个可见元素，粘性起点即表单顶部）
        form.insertBefore(bar, form.firstChild);

        // 高亮指定锚点项；横向滚动容器内同步把激活项滚入可视范围
        function setActive(id) {
            navItems.forEach(function (btn) {
                const on = btn.dataset.targetId === id;
                btn.classList.toggle('is-active', on);
                if (on && typeof btn.scrollIntoView === 'function') {
                    btn.scrollIntoView({ block: 'nearest', inline: 'nearest' });
                }
            });
        }

        // ---- 2) scrollspy：滚动时高亮当前所在分组 ----
        let lockTarget = null;
        function spy() {
            // 阈值 = 全局头部 57px + 锚点条实高 + 提前量 40px：
            // 分组标题进入「粘性锚点条下方」即激活；提前量需大于
            // scroll-margin-top（120px）与实际停靠位（57+条高）的差值，
            // 否则锚点点击滚动到位后标题停在阈值外，高亮会滞后一组
            const threshold = 57 + (bar.offsetHeight || 50) + 40;
            let current = null;
            sections.forEach(function (section) {
                if (section.getBoundingClientRect().top <= threshold) {
                    current = section.id;
                }
            });
            // 页面顶部（第一组尚未越过阈值）默认激活第一组
            if (!current) {
                current = sections[0].id;
            }
            // 滚动到底部时强制激活最后一组（末组内容短、永远到不了阈值）
            if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 2) {
                current = sections[sections.length - 1].id;
            }
            if (lockTarget) {
                if (current === lockTarget) {
                    lockTarget = null; // 已滚动到目标组：解锁，交还 spy
                } else {
                    return; // 滚动途中：维持点击时的高亮不跳变
                }
            }
            if (current) {
                setActive(current);
            }
        }
        window.addEventListener('scroll', spy, { passive: true });
        window.addEventListener('resize', spy);
        spy();

        // ---- 3) 粘性保存栏（有真实未保存变更且原生按钮滚出视口时显示） ----
        const submitBtn = form.querySelector('button[type="submit"]');
        if (!submitBtn) {
            return;
        }
        const mainContent = document.querySelector('.main-content');
        if (!mainContent) {
            return;
        }

        // 控件初始值快照：dirty 判定基于「当前值 vs 初始值」对比，而非
        // 「是否发生过输入事件」——改动后又改回原值的控件不算未保存变更。
        // hidden 控件（sub_page/clear_logo 等流程辅助字段）不参与对比与定位
        const controls = Array.prototype.slice.call(form.querySelectorAll('input, select, textarea'))
            .filter(function (el) { return el.type !== 'hidden'; });
        const initialValue = new Map();
        controls.forEach(function (el) {
            initialValue.set(el, controlValue(el));
        });
        // 控件当前值：checkbox/radio 取选中态；其余（含 file 的本机路径回显）取 value
        function controlValue(el) {
            if (el.type === 'checkbox' || el.type === 'radio') {
                return el.checked ? '1' : '0';
            }
            return el.value;
        }

        // 保存栏骨架：三段式布局——状态区（红点 + 文案）| 变更设置项 chips
        // （弹性区，过多时内部滚动）| 保存按钮。chips 必须是保存栏直接子项，
        // 嵌在状态区内会撑爆状态区宽度、把保存按钮挤出视口
        const saveBar = document.createElement('div');
        saveBar.className = 'sticky-save-bar';
        saveBar.innerHTML =
            '<div class="ssb-status"><span class="ssb-dot"></span><span>有未保存更改</span></div>' +
            '<div class="ssb-chips"></div>' +
            '<button type="button" class="btn btn-primary ssb-save">保存设置</button>';
        // 挂到主内容区末尾：fixed 定位由 CSS 控制位置，放 .main-content 内
        // 便于用 .sidebar-collapsed 后代选择器对齐折叠态左缘
        mainContent.appendChild(saveBar);
        const chipsWrap = saveBar.querySelector('.ssb-chips');

        // 为每个控件预建「设置项级」定位 chip：默认隐藏，该控件出现真实值
        // 变更时显示；chip 文本取设置项的 label 名称，点击滚动到具体设置项
        // 并短暂高亮锚定视线。radio 按 name 去重（同组多 radio 共用一个 chip）
        const chips = new Map();
        // chip 去重键：radio 用组名，其余优先 id、再退到 name
        function chipKey(el) {
            return el.type === 'radio' ? 'r:' + el.name : (el.id ? 'i:' + el.id : 'n:' + el.name);
        }
        // 设置项显示名称：优先 label[for=id]，回退到所在 .form-group 的
        // 第一个 label；label 内的必填星号（.form-required）不参与显示
        function controlLabel(el) {
            let label = null;
            if (el.id) {
                label = form.querySelector('label[for="' + (window.CSS && CSS.escape ? CSS.escape(el.id) : el.id) + '"]');
            }
            if (!label) {
                const group = el.closest('.form-group');
                if (group) {
                    label = group.querySelector('label');
                }
            }
            if (label) {
                const clone = label.cloneNode(true);
                Array.prototype.slice.call(clone.querySelectorAll('.form-required')).forEach(function (star) {
                    star.remove();
                });
                const text = clone.textContent.trim();
                if (text) {
                    return text;
                }
            }
            // 无 label 兜底：用控件自身标识
            return el.name || el.id || '未命名设置项';
        }
        controls.forEach(function (el) {
            const key = chipKey(el);
            if (chips.has(key)) {
                return; // radio 同组 / 重复 id：已建过 chip
            }
            // 定位目标：设置项所在 .form-group（滚动 + 高亮都以它为准）
            const target = el.closest('.form-group') || el.closest('.form-section') || el;
            const section = el.closest('.form-section');
            const chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'ssb-chip';
            chip.textContent = controlLabel(el);
            chip.title = chip.textContent;
            chip.addEventListener('click', function () {
                // 同步锚点高亮到设置项所在分组（与锚点 Tab 点击同一套滚动/锁定逻辑）
                if (section) {
                    lockTarget = section.id;
                    setActive(section.id);
                }
                target.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'start' });
                // 短暂高亮目标设置项：滚动结束后帮视线锚定具体是哪一项
                target.classList.add('flash-target');
                setTimeout(function () {
                    target.classList.remove('flash-target');
                }, 1400);
            });
            chip.style.display = 'none';
            chipsWrap.appendChild(chip);
            chips.set(key, chip);
        });

        let submitted = false;  // 提交后保持隐藏（页面即将跳转）
        let btnVisible = true;  // 原生保存按钮是否在视口内
        function syncBar() {
            // 值对比：收集当前值与快照不一致的控件（设置项级）及其所在分组
            const changedSections = new Set();
            const changedKeys = new Set();
            controls.forEach(function (el) {
                if (initialValue.get(el) !== controlValue(el)) {
                    changedKeys.add(chipKey(el));
                    const sec = el.closest('.form-section');
                    if (sec) {
                        changedSections.add(sec.id);
                    }
                }
            });
            // 锚点 Tab 红点：标记含未保存变更的分组（与保存栏 chips 联动）
            navItems.forEach(function (btn) {
                btn.classList.toggle('has-changes', changedSections.has(btn.dataset.targetId));
            });
            // 设置项级 chips：只显示当前值确实变更的控件
            chips.forEach(function (chip, key) {
                chip.style.display = changedKeys.has(key) ? '' : 'none';
            });
            saveBar.classList.toggle('is-visible', !submitted && changedSections.size > 0 && !btnVisible);
        }
        // 任意输入/控件变更触发重算（值对比幂等，改回原值自动恢复干净态）
        form.addEventListener('input', syncBar);
        form.addEventListener('change', syncBar);
        // 提交时强制隐藏（页面即将跳转，避免加载遮罩下残留粘性栏）
        form.addEventListener('submit', function () { submitted = true; syncBar(); });

        // 原生按钮可见性检测：按钮在视口内时不显示粘性栏（避免双按钮）
        if ('IntersectionObserver' in window) {
            new IntersectionObserver(function (entries) {
                btnVisible = entries[0].isIntersecting;
                syncBar();
            }).observe(submitBtn);
        } else {
            btnVisible = false; // 兜底：无法检测时只要有变更就显示
        }

        // 粘性栏保存按钮 = 原生按钮代点（requestSubmit 触发完整提交链路，
        // 含 enhanceFormSubmission 的加载遮罩与按钮等待态）
        function submitForm() {
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit(submitBtn);
            } else {
                submitBtn.click();
            }
        }
        saveBar.querySelector('.ssb-save').addEventListener('click', submitForm);

        // ---- 4) Ctrl/Cmd+S 快捷保存（全局仅绑定一次，提交当前增强表单） ----
        if (!initFormSectionNav._hotkeyBound) {
            initFormSectionNav._hotkeyBound = true;
            document.addEventListener('keydown', function (e) {
                if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S')) {
                    // 仅当焦点不在输入框时接管？输入框内 Ctrl+S 同样应保存，
                    // 浏览器默认「保存网页」对后台无意义，一律拦截提交
                    e.preventDefault();
                    const target = document.querySelector('.form-container form[data-form-nav-init]');
                    if (target && typeof target.requestSubmit === 'function') {
                        const btn = target.querySelector('button[type="submit"]');
                        target.requestSubmit(btn);
                    }
                }
            });
        }
    });
}

// 页面加载完成后执行
window.addEventListener('DOMContentLoaded', function() {
    // 给所有带confirm-delete类的链接添加确认删除功能
    const deleteLinks = document.querySelectorAll('.confirm-delete');
    deleteLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // 阻止默认跳转，确认后自行前往链接地址
            e.preventDefault();
            // 按钮文案 = 具体动作（删除类弹窗统一「删除」）
            showConfirmDialog(this.dataset.message || '确定要删除这条记录吗？', { type: 'danger', confirmText: '删除' }).then((ok) => {
                if (ok) {
                    // 显示加载状态后跳转
                    showPageLoading();
                    window.location.href = this.href;
                }
            });
        });
    });
    
    // 给所有带disable-on-submit类的按钮添加防止重复提交功能
    const submitButtons = document.querySelectorAll('.disable-on-submit');
    submitButtons.forEach(button => {
        button.addEventListener('click', function() {
            setButtonLoading(this.id, true);
        });
    });
    
    // 表格行悬停反馈由 tables.css 的 tr:hover 统一承担（见文件上方废弃说明）
    
    // 增强表单提交
    enhanceFormSubmission();

    // 初始化统一下拉菜单（点击开合/外部关闭/Esc 关闭）
    initDropdowns();

    // 初始化表头列筛选面板（漏斗开合/搜索/全选/重置与确定提交）
    initColumnFilter();

    // 初始化批量选中计数提示（页面存在 #batch-selected-info 与 ids[] 复选框时生效）
    initBatchSelectedInfo();

    // 初始化密码显隐眼睛按钮（全后台 input[type="password"] 自动生效）
    initPasswordVisibility();
    initClearableInputs();

    // 初始化步进器（input[type=number] 自动增强 −/+ 按钮组，
    // 尊重原生 min/max/step，边界自动禁用；见函数上方说明）
    initSteppers();

    // 初始化自定义下拉（全后台原生 select 自动增强为统一面板，见函数上方说明）
    initCustomSelects();
    bindGlobalSelectClose();

    // 初始化全局快捷键（⌘/Ctrl+N 新建文章、G L/G C/G D 序列跳转、Esc 关闭弹层）
    initKeyboardShortcuts();

    // 初始化 Segmented 分段控制器滑块（.seg 容器自动注入滑动选中块）
    initSegmentedThumbs();

    // 初始化设置页表单增强（分组锚点导航 + 粘性保存栏，仅分组长表单生效）
    initFormSectionNav();

    // 检查URL参数中的消息
    const urlParams = new URLSearchParams(window.location.search);
    const message = urlParams.get('message');
    const messageType = urlParams.get('message_type') || 'info';
    
    if (message) {
        showToast(decodeURIComponent(message), messageType);
        // 从URL中移除消息参数
        const newUrl = new URL(window.location.href);
        newUrl.searchParams.delete('message');
        newUrl.searchParams.delete('message_type');
        window.history.replaceState({}, '', newUrl);
    }
    
    // 回到顶部：双击全局头部状态栏触发（原右下角返回顶部悬浮按钮已移除，
    // 头部空白区域双击即可；头部内的输入框/按钮/链接/下拉交互不受影响）
    const globalHeader = document.querySelector('.header');
    if (globalHeader) {
        globalHeader.addEventListener('dblclick', function(e) {
            if (e.target.closest('input, textarea, select, button, a, .command-palette, .user-menu, .dropdown-menu')) {
                return;
            }
            scrollToTop();
        });
    }

    // 头部缓存清理下拉（仅缓存功能开启时渲染，模板按 Config 输出）：
    // 点击垃圾桶图标开合面板，点击面板外部关闭；菜单项提交前经 confirmForm 二次确认
    const cacheToggle = document.getElementById('cacheMenuToggle');
    const cacheDropdown = document.getElementById('cacheMenuDropdown');
    if (cacheToggle && cacheDropdown) {
        cacheToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            cacheDropdown.classList.toggle('show');
        });
        document.addEventListener('click', function(e) {
            if (!cacheDropdown.contains(e.target)) {
                cacheDropdown.classList.remove('show');
            }
        });
    }

    // 缓存设置页「缓存状态」卡片标题右侧的清理下拉（原右列独立清理面板
    // 收纳于此，面板与全局头部清理下拉同构）：点击触发按钮开合面板，
    // 点击面板外部关闭；菜单项提交前经 confirmForm 弹确认框（见 config_cache.html）
    const cacheClearToggle = document.getElementById('cacheClearToggle');
    const cacheClearDropdown = document.getElementById('cacheClearDropdown');
    if (cacheClearToggle && cacheClearDropdown) {
        // 外层容器：开合时同步加/删 .open（components.css 据此高亮触发按钮）
        const cacheClearMenu = cacheClearToggle.closest('.cache-clear-menu');
        cacheClearToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            const isOpen = cacheClearDropdown.classList.toggle('show');
            if (cacheClearMenu) {
                cacheClearMenu.classList.toggle('open', isOpen);
            }
            // aria-expanded 同步开合状态（无障碍语义，纯辅助属性不影响交互）
            cacheClearToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
        document.addEventListener('click', function(e) {
            if (!cacheClearDropdown.contains(e.target)) {
                cacheClearDropdown.classList.remove('show');
                if (cacheClearMenu) {
                    cacheClearMenu.classList.remove('open');
                }
                cacheClearToggle.setAttribute('aria-expanded', 'false');
            }
        });
    }
});

// 保存草稿功能
function saveDraft() {
    const form = document.getElementById('article-form');
    if (!form) {
        showToast('未找到表单', 'error');
        return;
    }
    
    // 检查是否已有 status 输入框
    let statusInput = document.querySelector('input[name="status"]');
    if (!statusInput) {
        // 创建隐藏的 status 输入框
        statusInput = document.createElement('input');
        statusInput.type = 'hidden';
        statusInput.name = 'status';
        form.appendChild(statusInput);
    }
    
    // 设置为草稿状态
    statusInput.value = '0';
    
    // 提交表单
    form.submit();
}

// 预览文章功能
function previewArticle() {
    console.log('previewArticle called');
    const form = document.getElementById('article-form');
    if (!form) {
        showToast('未找到表单', 'error');
        return;
    }
    
    // 获取表单数据
    const title = document.getElementById('title')?.value || '';
    const description = document.getElementById('description')?.value || '';
    const content = document.getElementById('content')?.value || '';
    const categorySelect = document.getElementById('category_id');
    const categoryName = categorySelect?.options[categorySelect?.selectedIndex]?.text || '未分类';
    const authorName = document.querySelector('.user-name')?.textContent || '管理员';
    const publishDate = new Date().toLocaleDateString('zh-CN', { year: 'numeric', month: 'long', day: 'numeric' });
    
    console.log('Form data:', { title, description, content, categoryName });
    
    // 创建预览模态框
    const existingModal = document.getElementById('previewModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // 预览弹窗：统一使用 components.css 的 .modal-overlay > .modal 体系
    // （样式全部由 CSS 类提供，含暗色主题适配，此处不再写内联样式覆盖）
    const modalHtml = `
            <div class="modal-overlay show" id="previewModal" style="overflow: visible !important;">
                <div class="modal modal-lg" role="dialog" aria-modal="true">
                    <div class="modal-header">
                        <h3 class="modal-title">文章预览</h3>
                        <button type="button" class="modal-close" onclick="closePreviewModal()">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="preview-container">
                            <h1 class="preview-title">${escapeHtml(title)}</h1>
                            <div class="preview-meta">
                                <span style="margin-right: 15px; display: inline-block;">分类：${escapeHtml(categoryName)}</span>
                                <span style="margin-right: 15px; display: inline-block;">作者：${escapeHtml(authorName)}</span>
                                <span style="margin-right: 15px; display: inline-block;">发布时间：${publishDate}</span>
                            </div>
                            ${description ? `<div class="preview-description">${escapeHtml(description)}</div>` : ''}
                            <div class="preview-content">${content}</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closePreviewModal()">关闭</button>
                    </div>
                </div>
            </div>
        `;
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    console.log('Modal HTML added to body');
    
    // 检查模态框是否正确创建
    const modal = document.getElementById('previewModal');
    console.log('Modal element:', modal);
    if (modal) {
        console.log('Modal styles:', window.getComputedStyle(modal));
        console.log('Modal children:', modal.children);
    }
    
    // 点击遮罩关闭
    document.getElementById('previewModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closePreviewModal();
        }
    });
    
    // ESC键关闭
    const escHandler = function(e) {
        if (e.key === 'Escape') {
            closePreviewModal();
            document.removeEventListener('keydown', escHandler);
        }
    };
    document.addEventListener('keydown', escHandler);
}

// 关闭预览模态框
function closePreviewModal() {
    const modal = document.getElementById('previewModal');
    if (modal) {
        modal.classList.remove('show');
        setTimeout(() => {
            modal.remove();
        }, 300);
    }
}

// ===== 实体编辑抽屉（列表页通用） =====
// 列表页的「编辑/添加」在右侧抽屉内打开，保留列表上下文（用户编辑抽屉试点验证后的通用化实现）。
// 结构使用 components.css 的 .drawer-overlay > .drawer 体系；抽屉内通过 iframe 加载完整编辑页，
// 复用编辑页已有的表单校验/错误提示/保存逻辑，避免每个实体重复实现一套 AJAX 提交。
// iframe 与后台同源，加载后给内部 body 加 drawer-embed 类（见 common.css），
// 隐藏侧边栏/顶部栏，让表单在抽屉内满宽显示。

// 打开抽屉时的上下文：记录列表页自身的 action，用于识别「编辑页保存后跳回列表」
let entityDrawerContext = null;

// 懒创建单例抽屉 DOM（各列表页共用一份，无需在模板里重复写抽屉结构）
function ensureEntityDrawer() {
    let overlay = document.getElementById('entityEditDrawer');
    if (overlay) return overlay;

    overlay = document.createElement('div');
    overlay.className = 'drawer-overlay';
    overlay.id = 'entityEditDrawer';
    overlay.innerHTML =
        '<div class="drawer drawer-lg" role="dialog" aria-modal="true">' +
        '  <div class="drawer-header">' +
        '    <h3 class="drawer-title"></h3>' +
        '    <button type="button" class="drawer-close" aria-label="关闭">&times;</button>' +
        '  </div>' +
        // drawer-body：iframe 模式下自身不滚动（overflow:hidden），滚动交给 iframe 内部文档，
        // 避免外层与内层同时出现滚动条
        '  <div class="drawer-body" style="padding: 0; position: relative; overflow: hidden;">' +
        // src 初始为空占位，打开时才加载对应编辑页，避免页面初始多一次请求；
        // iframe 绝对定位填满 body（flex 布局下 height:100% 计算有偏差会溢出，改用 inset:0 精确贴合）
        '    <iframe src="about:blank" title="编辑" style="position: absolute; inset: 0; width: 100%; height: 100%; border: none; display: block;"></iframe>' +
        '  </div>' +
        '</div>';
    document.body.appendChild(overlay);

    const frame = overlay.querySelector('iframe');

    // 关闭按钮 / 点击遮罩空白处关闭（仅当点击目标是遮罩本身时触发）
    overlay.querySelector('.drawer-close').addEventListener('click', closeEntityDrawer);
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) {
            closeEntityDrawer();
        }
    });

    // iframe 加载完成：为内部文档加 drawer-embed 嵌入类，并检测保存成功后跳回列表的情况
    frame.addEventListener('load', function() {
        try {
            const innerDoc = this.contentDocument;
            const innerUrl = this.contentWindow.location.href;
            if (!innerDoc || !innerDoc.body || innerUrl.indexOf('about:blank') !== -1) return;
            innerDoc.body.classList.add('drawer-embed');
            // 抽屉内「取消/返回」类链接：仅关闭抽屉，不走「跳回列表」的父页刷新
            // （表单未提交、数据未变，列表无需刷新）；href 保留，中键新窗口仍可用
            innerDoc.addEventListener('click', function(e) {
                const cancelLink = e.target.closest('a.drawer-cancel');
                if (cancelLink) {
                    e.preventDefault();
                    closeEntityDrawer();
                }
            });
            if (!entityDrawerContext) return;
            // 编辑/添加页保存成功后一般会 302 跳回同 action 的列表页（无 edit/add/detail 子操作），
            // 此时关闭抽屉并刷新父页面，让列表展示最新数据
            const innerParams = new URLSearchParams(this.contentWindow.location.search);
            const innerAction = innerParams.get('action');
            const innerSub = innerParams.get('sub');
            // 跳回列表判定：iframe 地址与列表页同 action，且不带 sub/method 子路由参数
            // （列表页 URL 只有 action；抽屉内表单页如 edit/add/detail/import 均带 sub，
            //   method= 型页面如回收站列表则带 method，都不会命中，避免抽屉刚打开就被误关）
            const innerMethod = innerParams.get('method');
            if (innerAction && innerAction === entityDrawerContext.action &&
                !innerSub && !innerMethod) {
                closeEntityDrawer();
                window.location.reload();
            }
        } catch (e) {
            // 跨域等无法访问 iframe 内部时静默忽略（抽屉仍可正常显示与手动关闭）
        }
    });

    // Esc 关闭抽屉（sidebar.js 的 Esc 只处理快捷键弹窗，这里补充抽屉自身的 Esc）
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && overlay.classList.contains('show')) {
            closeEntityDrawer();
        }
    });

    return overlay;
}

// 打开实体编辑抽屉
// @param {string} url 编辑页地址（如 admin.php?action=user&sub=edit&id=1）
// @param {string} title 抽屉标题（如「编辑用户」）
function openEntityDrawer(url, title) {
    const overlay = ensureEntityDrawer();
    // 记录列表页 action，供 iframe 加载回调判断「是否已跳回列表」
    entityDrawerContext = {
        action: new URLSearchParams(window.location.search).get('action') || ''
    };
    overlay.querySelector('.drawer-title').textContent = title || '编辑';
    overlay.querySelector('iframe').src = url;
    overlay.classList.add('show');
}

// 关闭抽屉：移除 .show 后延迟重置 iframe，避免残留旧页面内容与音视频播放
function closeEntityDrawer() {
    const overlay = document.getElementById('entityEditDrawer');
    if (!overlay) return;
    overlay.classList.remove('show');
    setTimeout(() => {
        overlay.querySelector('iframe').src = 'about:blank';
    }, 300);
}

// ==================== 筛选条件折叠 ====================

// 切换筛选区的次要条件显隐（配合 forms.css 的 .filter-extra 机制）
// @param {HTMLElement} btn 「展开筛选/收起筛选」切换按钮（须位于 .filter-row 内）
function toggleFilterExtra(btn) {
    const row = btn.closest('.filter-row');
    if (!row) return;
    const expanded = row.classList.toggle('filter-expanded');
    // 按钮文字与箭头方向跟随状态
    const text = btn.querySelector('.filter-toggle-text');
    if (text) text.textContent = expanded ? '收起筛选' : '展开筛选';
    btn.classList.toggle('open', expanded);
}

// HTML转义
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// 简单的Markdown解析
function parseMarkdown(text) {
    if (!text) return '';
    
    let html = text;
    
    // 换行转br
    html = html.replace(/\n/g, '<br>');
    
    // 标题
    html = html.replace(/^### (.*$)/gm, '<h3>$1</h3>');
    html = html.replace(/^## (.*$)/gm, '<h2>$1</h2>');
    html = html.replace(/^# (.*$)/gm, '<h1>$1</h1>');
    
    // 粗体
    html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    
    // 斜体
    html = html.replace(/\*(.*?)\*/g, '<em>$1</em>');
    
    // 链接
    html = html.replace(/\[([^\]]*)\]\(([^)]*)\)/g, '<a href="$2" target="_blank">$1</a>');
    
    // 图片
    html = html.replace(/!\[([^\]]*)\]\(([^)]*)\)/g, '<img src="$2" alt="$1">');
    
    return html;
}
