// UI/UX 增强功能

// 确认删除功能 - 保持兼容性（返回布尔值）
function confirmDelete(message) {
    return confirm(message || '确定要删除这条记录吗？此操作不可撤销！');
}

// 新的自定义确认弹窗（返回Promise）
function showConfirmDialog(message, options) {
    if (window.BlogKitUI && window.BlogKitUI.Modal) {
        return BlogKitUI.Modal.confirm(message, options || {});
    } else {
        // 降级到原生confirm，返回一个已resolve的Promise
        return Promise.resolve(confirm(message));
    }
}

// 自定义提示弹窗
function showAlertDialog(message, title) {
    if (window.BlogKitUI && window.BlogKitUI.Modal) {
        BlogKitUI.Modal.alert(message, title || '提示');
    } else {
        alert(message);
    }
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
function showToast(message, type = 'info', duration = 3000) {
    // 移除已存在的toast
    const existingToast = document.querySelector('.toast');
    if (existingToast) {
        existingToast.remove();
    }
    
    // 创建新的toast
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    // 添加图标
    let icon = '';
    switch (type) {
        case 'success':
            icon = '✓';
            break;
        case 'error':
            icon = '⚠';
            break;
        case 'warning':
            icon = '⚠';
            break;
        case 'info':
        default:
            icon = 'ℹ';
    }
    
    toast.innerHTML = `
        <span class="toast-icon">${icon}</span>
        <span class="toast-message">${message}</span>
    `;
    
    // 添加到页面
    document.body.appendChild(toast);
    
    // 触发显示动画
    setTimeout(() => {
        toast.classList.add('show');
    }, 10);
    
    // 自动移除
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => {
            toast.remove();
        }, 300);
    }, duration);
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

// 批量操作功能
function performBulkAction(action, formId) {
    const form = document.getElementById(formId);
    if (!form) return;
    
    const checkboxes = form.querySelectorAll('input[type="checkbox"][name="ids[]"]:checked');
    if (checkboxes.length === 0) {
        showToast('请选择至少一条记录！', 'warning');
        return;
    }
    
    if (action === 'delete') {
        if (confirmDelete('确定要删除选中的记录吗？')) {
            // 显示加载状态
            showPageLoading();
            form.submit();
        }
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

// 表格行悬停效果增强
function enhanceTableHover() {
    const tables = document.querySelectorAll('table');
    tables.forEach(table => {
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.backgroundColor = '#f8f9fa';
                this.style.transition = 'background-color 0.2s ease';
            });
            row.addEventListener('mouseleave', function() {
                this.style.backgroundColor = '';
            });
        });
    });
}

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
            
            // 显示加载状态
            showPageLoading();
        });
    });
}

// 页面加载完成后执行
window.addEventListener('DOMContentLoaded', function() {
    // 给所有带confirm-delete类的链接添加确认删除功能
    const deleteLinks = document.querySelectorAll('.confirm-delete');
    deleteLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            if (!confirmDelete(this.dataset.message || '确定要删除这条记录吗？')) {
                e.preventDefault();
            } else {
                // 显示加载状态
                showPageLoading();
            }
        });
    });
    
    // 给所有带disable-on-submit类的按钮添加防止重复提交功能
    const submitButtons = document.querySelectorAll('.disable-on-submit');
    submitButtons.forEach(button => {
        button.addEventListener('click', function() {
            setButtonLoading(this.id, true);
        });
    });
    
    // 增强表格悬停效果
    enhanceTableHover();
    
    // 增强表单提交
    enhanceFormSubmission();
    
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
    
    // 添加回到顶部按钮
    const backToTopButton = document.createElement('button');
    backToTopButton.id = 'back-to-top';
    backToTopButton.className = 'btn btn-primary';
    backToTopButton.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 1000;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
    `;
    backToTopButton.innerHTML = '↑';
    backToTopButton.title = '回到顶部';
    backToTopButton.addEventListener('click', scrollToTop);
    document.body.appendChild(backToTopButton);
    
    // 滚动事件监听
    window.addEventListener('scroll', function() {
        const backToTopBtn = document.getElementById('back-to-top');
        if (window.pageYOffset > 300) {
            backToTopBtn.style.opacity = '1';
            backToTopBtn.style.visibility = 'visible';
        } else {
            backToTopBtn.style.opacity = '0';
            backToTopBtn.style.visibility = 'hidden';
        }
    });
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
    
    const modalHtml = `
            <div class="modal-overlay show" id="previewModal" style="z-index: 999999 !important; overflow: visible !important;">
                <div class="modal modal-lg" style="background: white !important; border-radius: 10px !important; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2) !important; max-width: 95% !important; width: 1200px !important; position: relative !important; z-index: 9999991 !important; display: flex !important; flex-direction: column !important; min-height: 400px !important;">
                    <div class="modal-header" style="display: flex !important; justify-content: space-between !important; align-items: center !important; padding: 15px 20px !important; border-bottom: 1px solid #e9ecef !important; border-radius: 10px 10px 0 0 !important; min-height: 60px !important;">
                        <h3 class="modal-title" style="margin: 0 !important; font-size: 18px !important; font-weight: 600 !important; color: #212529 !important;">文章预览</h3>
                        <button class="modal-close" onclick="closePreviewModal()" style="background: none !important; border: none !important; font-size: 24px !important; cursor: pointer !important; color: #6c757d !important; line-height: 1 !important;">&times;</button>
                    </div>
                    <div class="modal-body" style="padding: 20px !important; max-height: 70vh !important; overflow-y: auto !important; flex: 1 !important; min-height: 300px !important; display: block !important;">
                        <div class="preview-container" style="display: block !important;">
                            <h1 class="preview-title" style="font-size: 28px !important; font-weight: bold !important; margin-bottom: 15px !important; color: #212529 !important; display: block !important;">${escapeHtml(title)}</h1>
                            <div class="preview-meta" style="color: #6c757d !important; font-size: 14px !important; margin-bottom: 20px !important; padding-bottom: 15px !important; border-bottom: 1px solid #e9ecef !important; display: block !important;">
                                <span style="margin-right: 15px !important; display: inline-block !important;">分类：${escapeHtml(categoryName)}</span>
                                <span style="margin-right: 15px !important; display: inline-block !important;">作者：${escapeHtml(authorName)}</span>
                                <span style="margin-right: 15px !important; display: inline-block !important;">发布时间：${publishDate}</span>
                            </div>
                            ${description ? `<div class="preview-description" style="color: #495057 !important; font-size: 16px !important; margin-bottom: 20px !important; padding: 12px !important; background-color: #f8f9fa !important; border-radius: 6px !important; display: block !important;">${escapeHtml(description)}</div>` : ''}
                            <div class="preview-content" style="font-size: 16px !important; line-height: 1.8 !important; color: #333 !important; display: block !important;">${content}</div>
                        </div>
                    </div>
                    <div class="modal-footer" style="display: flex !important; justify-content: flex-end !important; gap: 10px !important; padding: 15px 20px !important; border-top: 1px solid #e9ecef !important; border-radius: 0 0 10px 10px !important; min-height: 50px !important;">
                        <button class="btn btn-default" onclick="closePreviewModal()" style="padding: 8px 16px !important; border: 1px solid #dee2e6 !important; background: #f8f9fa !important; border-radius: 4px !important; cursor: pointer !important; display: inline-block !important;">关闭</button>
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
