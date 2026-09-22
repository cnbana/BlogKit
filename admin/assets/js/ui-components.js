/**
 * BlogKit Admin UI Components
 * 统一的UI组件封装，提供Toast、Modal、Button等常用组件
 */

// 使用IIFE封装，避免全局污染
(function(window, document, undefined) {
    
    'use strict';
    
    /**
     * Toast 消息提示组件
     */
    class Toast {
        /**
         * 显示Toast提示
         * @param {string} message - 提示消息
         * @param {string} type - 提示类型：success/error/warning/info
         * @param {number} duration - 显示时长（毫秒）
         */
        static show(message, type = 'info', duration = 3000) {
            // 移除已存在的toast
            this.removeExisting();
            
            // 创建toast元素
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            
            // 添加图标
            const icons = {
                success: '✓',
                error: '✕',
                warning: '⚠',
                info: 'ℹ'
            };
            
            toast.innerHTML = `
                <div class="toast-container">
                    <span class="toast-icon">${icons[type] || icons.info}</span>
                    <span class="toast-message">${message}</span>
                </div>
            `;
            
            // 添加到页面
            document.body.appendChild(toast);
            
            // 添加显示动画
            setTimeout(() => toast.classList.add('show'), 10);
            
            // 自动移除
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, duration);
        }
        
        /**
         * 显示成功提示
         */
        static success(message, duration = 3000) {
            this.show(message, 'success', duration);
        }
        
        /**
         * 显示错误提示
         */
        static error(message, duration = 4000) {
            this.show(message, 'error', duration);
        }
        
        /**
         * 显示警告提示
         */
        static warning(message, duration = 3500) {
            this.show(message, 'warning', duration);
        }
        
        /**
         * 显示信息提示
         */
        static info(message, duration = 3000) {
            this.show(message, 'info', duration);
        }
        
        /**
         * 移除已存在的toast
         */
        static removeExisting() {
            const existing = document.querySelector('.toast');
            if (existing) existing.remove();
        }
    }
    
    /**
     * Modal 模态框组件
     */
    class Modal {
        /**
         * 构造函数
         * @param {object} options - 配置选项
         */
        constructor(options = {}) {
            this.options = Object.assign({
                title: '',
                content: '',
                showClose: true,
                showFooter: true,
                confirmText: '确定',
                cancelText: '取消',
                onConfirm: null,
                onCancel: null,
                size: 'md' // sm/md/lg
            }, options);
            
            this.modal = null;
            this.overlay = null;
        }
        
        /**
         * 显示模态框
         */
        show() {
            // 创建遮罩层
            this.overlay = document.createElement('div');
            this.overlay.className = 'modal-overlay';
            
            // 创建模态框
            this.modal = document.createElement('div');
            this.modal.className = `modal modal-${this.options.size}`;
            
            // 构建内容
            this.modal.innerHTML = `
                <div class="modal-header">
                    <h3 class="modal-title">${this.options.title}</h3>
                    ${this.options.showClose ? '<button class="modal-close" onclick="this.closest(\'.modal\').parentElement.remove();">&times;</button>' : ''}
                </div>
                <div class="modal-body">${this.options.content}</div>
                ${this.options.showFooter ? `
                <div class="modal-footer">
                    <button class="btn btn-secondary" onclick="this.closest('.modal').parentElement.remove();">${this.options.cancelText}</button>
                    <button class="btn btn-primary" onclick="this.closest('.modal').parentElement.remove();">${this.options.confirmText}</button>
                </div>
                ` : ''}
            `;
            
            // 添加到页面
            this.overlay.appendChild(this.modal);
            document.body.appendChild(this.overlay);
            
            // 添加事件监听
            this.bindEvents();
            
            // 显示动画
            setTimeout(() => {
                this.overlay.classList.add('show');
                this.modal.classList.add('show');
            }, 10);
        }
        
        /**
         * 隐藏模态框
         */
        hide() {
            if (this.overlay && this.modal) {
                this.overlay.classList.remove('show');
                this.modal.classList.remove('show');
                
                setTimeout(() => {
                    this.overlay.remove();
                    this.modal = null;
                    this.overlay = null;
                }, 300);
            }
        }
        
        /**
         * 绑定事件
         */
        bindEvents() {
            // 点击遮罩层关闭
            this.overlay.addEventListener('click', (e) => {
                if (e.target === this.overlay) {
                    this.hide();
                    if (this.options.onCancel) this.options.onCancel();
                }
            });
            
            // 确认按钮
            const confirmBtn = this.modal.querySelector('.btn-primary');
            if (confirmBtn && this.options.onConfirm) {
                confirmBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.hide();
                    this.options.onConfirm();
                });
            }
            
            // 取消按钮
            const cancelBtn = this.modal.querySelector('.btn-secondary');
            if (cancelBtn && this.options.onCancel) {
                cancelBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.hide();
                    this.options.onCancel();
                });
            }
        }
        
        /**
         * 更新内容
         * @param {string} content - 新内容
         */
        updateContent(content) {
            if (this.modal) {
                const body = this.modal.querySelector('.modal-body');
                if (body) body.innerHTML = content;
            }
        }
        
        /**
         * 静态方法：快速显示模态框
         */
        static alert(message, title = '提示') {
            new Modal({
                title,
                content: `<p>${message}</p>`,
                showFooter: true,
                confirmText: '确定',
                cancelText: null
            }).show();
        }
        
        /**
         * 静态方法：快速显示确认框
         */
        static confirm(message, options = {}) {
            return new Promise((resolve) => {
                new Modal({
                    title: options.title || '确认',
                    content: `<p>${message}</p>`,
                    confirmText: options.confirmText || '确定',
                    cancelText: options.cancelText || '取消',
                    onConfirm: () => resolve(true),
                    onCancel: () => resolve(false)
                }).show();
            });
        }
    }
    
    /**
     * Button 按钮组件
     */
    class Button {
        /**
         * 设置按钮加载状态
         * @param {HTMLElement|string} button - 按钮元素或ID
         * @param {boolean} isLoading - 是否加载中
         * @param {string} loadingText - 加载时的文字
         */
        static loading(button, isLoading = true, loadingText = '加载中...') {
            const btn = typeof button === 'string' ? document.getElementById(button) : button;
            if (!btn) return;
            
            if (isLoading) {
                btn.classList.add('loading');
                btn.disabled = true;
                btn.dataset.originalText = btn.innerHTML;
                btn.innerHTML = `<span class="btn-spinner"></span>${loadingText}`;
            } else {
                btn.classList.remove('loading');
                btn.disabled = false;
                if (btn.dataset.originalText) {
                    btn.innerHTML = btn.dataset.originalText;
                    delete btn.dataset.originalText;
                }
            }
        }
        
        /**
         * 禁用按钮
         */
        static disable(button) {
            const btn = typeof button === 'string' ? document.getElementById(button) : button;
            if (btn) {
                btn.disabled = true;
                btn.classList.add('disabled');
            }
        }
        
        /**
         * 启用按钮
         */
        static enable(button) {
            const btn = typeof button === 'string' ? document.getElementById(button) : button;
            if (btn) {
                btn.disabled = false;
                btn.classList.remove('disabled');
            }
        }
    }
    
    /**
     * Form 表单验证组件
     */
    class FormValidator {
        /**
         * 验证表单
         * @param {HTMLFormElement|string} form - 表单元素或ID
         * @param {object} rules - 验证规则
         * @returns {boolean}
         */
        static validate(form, rules = {}) {
            const formElement = typeof form === 'string' ? document.getElementById(form) : form;
            if (!formElement) return true;
            
            let isValid = true;
            const inputs = formElement.querySelectorAll('[required], [data-validate]');
            
            inputs.forEach(input => {
                const name = input.name;
                const value = input.value.trim();
                const fieldRules = rules[name] || {};
                
                // 清除之前的错误
                this.removeError(input);
                
                // 非空验证
                if (input.hasAttribute('required') && !value) {
                    this.showError(input, fieldRules.requiredMessage || '此字段为必填项');
                    isValid = false;
                    return;
                }
                
                // 自定义验证规则
                if (fieldRules.validate && typeof fieldRules.validate === 'function') {
                    const result = fieldRules.validate(value);
                    if (result !== true) {
                        this.showError(input, result || fieldRules.message || '验证失败');
                        isValid = false;
                    }
                }
                
                // 邮箱验证
                if (fieldRules.type === 'email' && value && !this.isEmail(value)) {
                    this.showError(input, fieldRules.message || '请输入有效的邮箱地址');
                    isValid = false;
                }
                
                // 手机号验证
                if (fieldRules.type === 'phone' && value && !this.isPhone(value)) {
                    this.showError(input, fieldRules.message || '请输入有效的手机号码');
                    isValid = false;
                }
                
                // URL验证
                if (fieldRules.type === 'url' && value && !this.isUrl(value)) {
                    this.showError(input, fieldRules.message || '请输入有效的URL地址');
                    isValid = false;
                }
                
                // 最小长度验证
                if (fieldRules.minLength && value.length < fieldRules.minLength) {
                    this.showError(input, fieldRules.message || `最少需要${fieldRules.minLength}个字符`);
                    isValid = false;
                }
                
                // 最大长度验证
                if (fieldRules.maxLength && value.length > fieldRules.maxLength) {
                    this.showError(input, fieldRules.message || `最多允许${fieldRules.maxLength}个字符`);
                    isValid = false;
                }
                
                // 正则验证
                if (fieldRules.pattern && value) {
                    const regex = typeof fieldRules.pattern === 'string' ? new RegExp(fieldRules.pattern) : fieldRules.pattern;
                    if (!regex.test(value)) {
                        this.showError(input, fieldRules.message || '格式不正确');
                        isValid = false;
                    }
                }
            });
            
            return isValid;
        }
        
        /**
         * 显示错误
         */
        static showError(input, message) {
            input.classList.add('error');
            
            // 创建错误提示元素
            let errorElement = input.nextElementSibling;
            if (!errorElement || !errorElement.classList.contains('form-error')) {
                errorElement = document.createElement('div');
                errorElement.className = 'form-error';
                input.parentNode.insertBefore(errorElement, input.nextSibling);
            }
            errorElement.textContent = message;
            errorElement.style.display = 'block';
            
            // 添加输入事件移除错误
            input.addEventListener('input', () => this.removeError(input), { once: true });
        }
        
        /**
         * 移除错误
         */
        static removeError(input) {
            input.classList.remove('error');
            const errorElement = input.nextElementSibling;
            if (errorElement && errorElement.classList.contains('form-error')) {
                errorElement.style.display = 'none';
            }
        }
        
        /**
         * 邮箱格式验证
         */
        static isEmail(value) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
        }
        
        /**
         * 手机号格式验证
         */
        static isPhone(value) {
            return /^1[3-9]\d{9}$/.test(value);
        }
        
        /**
         * URL格式验证
         */
        static isUrl(value) {
            try {
                new URL(value);
                return true;
            } catch {
                return false;
            }
        }
    }
    
    /**
     * Loading 加载状态组件
     */
    class Loading {
        /**
         * 显示页面加载状态
         */
        static show() {
            // 移除已存在的loading
            this.hide();
            
            const loading = document.createElement('div');
            loading.className = 'page-loading';
            loading.innerHTML = `
                <div class="loading-spinner"></div>
                <span class="loading-text">加载中...</span>
            `;
            document.body.appendChild(loading);
        }
        
        /**
         * 隐藏页面加载状态
         */
        static hide() {
            const loading = document.querySelector('.page-loading');
            if (loading) loading.remove();
        }
        
        /**
         * 显示元素加载状态
         * @param {HTMLElement|string} element - 元素或ID
         */
        static showElement(element) {
            const el = typeof element === 'string' ? document.getElementById(element) : element;
            if (!el) return;
            
            el.classList.add('loading');
            el.innerHTML = '<div class="element-spinner"></div>';
        }
        
        /**
         * 隐藏元素加载状态
         * @param {HTMLElement|string} element - 元素或ID
         */
        static hideElement(element) {
            const el = typeof element === 'string' ? document.getElementById(element) : element;
            if (!el) return;
            
            el.classList.remove('loading');
        }
    }
    
    /**
     * 统一导出到全局
     */
    window.BlogKitUI = {
        Toast,
        Modal,
        Button,
        FormValidator,
        Loading
    };
    
})(window, document);