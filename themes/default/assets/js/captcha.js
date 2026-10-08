/**
 * 验证码组件
 * 提供统一的验证码功能，支持点击刷新、实时验证、多实例共存和动态创建
 * 
 * 全局实例注册表：Captcha._instances  Map<containerElement, CaptchaInstance>
 * 外部可通过 Captcha.getInstance(containerElement) 获取实例
 */
class Captcha {
    constructor(container, options = {}) {
        // 支持直接传递DOM元素或选择器字符串
        if (typeof container === 'string') {
            this.container = document.querySelector(container);
        } else {
            this.container = container;
        }
        if (!this.container) {
            console.error('验证码容器不存在');
            return;
        }

        // 从容器的data属性获取配置
        const containerConfig = {
            width: this.container.getAttribute('data-captcha-width'),
            height: this.container.getAttribute('data-captcha-height'),
            fontSize: this.container.getAttribute('data-captcha-font-size')
        };

        // 生成唯一 ID 前缀，支持同一页面多个验证码实例共存
        this.instanceId = 'captcha_' + Math.random().toString(36).substr(2, 9);

        this.options = Object.assign({
            captchaUrl: '/index.php?c=Captcha',
            verifyUrl: '/index.php?c=Captcha&m=verify',
            inputId: this.instanceId + '_code',
            imageId: this.instanceId + '_image',
            refreshId: this.instanceId + '_refresh',
            messageId: this.instanceId + '_message',
            autoRefresh: false,
            onVerifySuccess: null,
            onVerifyError: null,
            width: containerConfig.width ? parseInt(containerConfig.width) : 120,
            height: containerConfig.height ? parseInt(containerConfig.height) : 40,
            fontSize: containerConfig.fontSize ? parseInt(containerConfig.fontSize) : 20
        }, options);

        // 防重复验证标志：验证码被使用/提交后，忽略后续的实时验证请求结果
        this._isConsumed = false;
        // 当前待处理的验证请求（用于在使用时取消）
        this._pendingRequest = null;

        // 注册到全局实例表（供外部通过容器元素查找）
        Captcha._instances = Captcha._instances || new Map();
        Captcha._instances.set(this.container, this);

        this.init();
    }

    init() {
        // 创建验证码HTML结构
        this.createHtml();

        // 绑定事件
        this.bindEvents();

        // 初始化验证码图片
        this.refresh();
    }

    createHtml() {
        const html = `
            <div class="captcha-container">
                <div class="captcha-input-group">
                    <div class="captcha-input-wrapper">
                        <input type="text" id="${this.options.inputId}" name="captcha_code" placeholder="请输入验证码" class="captcha-input" required autocomplete="off">
                        <span id="${this.options.messageId}" class="captcha-status-icon"></span>
                    </div>
                    <div class="captcha-image-container">
                        <img id="${this.options.imageId}" src="" alt="验证码" class="captcha-image" title="点击刷新验证码">
                    </div>
                </div>
            </div>
        `;

        this.container.innerHTML = html;
    }

    bindEvents() {
        // 刷新验证码事件（点击图片刷新）
        const captchaImg = document.getElementById(this.options.imageId);
        const captchaInput = document.getElementById(this.options.inputId);

        if (captchaImg) {
            captchaImg.addEventListener('click', () => this.refresh());
        }

        // 实时验证事件
        if (captchaInput) {
            let debounceTimer;
            captchaInput.addEventListener('input', (e) => {
                const code = e.target.value.trim();

                // 清除之前的定时器
                clearTimeout(debounceTimer);

                // 只有当输入长度大于等于4时才进行验证
                if (code.length >= 4) {
                    // 添加防抖，避免频繁请求
                    debounceTimer = setTimeout(() => {
                        this.verify(code);
                    }, 500);
                } else if (code.length === 0) {
                    // 清空消息
                    this.clearMessage();
                }
            });
        }
    }

    refresh() {
        const captchaImg = document.getElementById(this.options.imageId);
        if (captchaImg) {
            // 添加随机参数以防止缓存（自动检测已有 ? 则用 & 连接）
            const timestamp = new Date().getTime();
            const sep = this.options.captchaUrl.includes('?') ? '&' : '?';
            captchaImg.src = `${this.options.captchaUrl}${sep}t=${timestamp}`;

            // 动态设置验证码图片尺寸
            captchaImg.style.width = `${this.options.width}px`;
            captchaImg.style.height = `${this.options.height}px`;

            // 清空输入框和消息，并重置已使用标志（刷新后生成新验证码）
            const captchaInput = document.getElementById(this.options.inputId);
            if (captchaInput) {
                captchaInput.value = '';
            }
            this.clearMessage();
            this._isConsumed = false;
        }
    }

    /**
     * 标记验证码已被使用（表单提交成功后调用）
     * 调用后将忽略后续的实时验证请求结果，防止提交成功后仍显示验证码错误
     */
    markAsConsumed() {
        this._isConsumed = true;
        this.clearMessage();
    }

    verify(code) {
        if (!code) {
            this.showMessage('请输入验证码', 'error');
            return false;
        }

        // 如果验证码已被使用（已在表单中提交过），则不进行实时验证
        if (this._isConsumed) {
            return;
        }

        // 发送验证请求
        const request = fetch(this.options.verifyUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `code=${encodeURIComponent(code)}`
        })
        .then(response => response.json())
        .then(data => {
            // 如果在请求发送后验证码已被使用，忽略结果
            if (this._isConsumed) {
                return;
            }
            if (data.success) {
                this.showMessage('验证码正确', 'success');
                if (this.options.onVerifySuccess) {
                    this.options.onVerifySuccess();
                }
            } else {
                this.showMessage('验证码错误', 'error');
                if (this.options.onVerifyError) {
                    this.options.onVerifyError();
                }
                // 验证码错误时自动刷新（带延迟提示）
                setTimeout(() => {
                    this.refresh();
                }, 800);
            }
        })
        .catch(error => {
            // 如果已被使用，静默忽略网络错误
            if (this._isConsumed) {
                return;
            }
            console.error('验证码验证失败:', error);
            this.showMessage('验证失败，请重试', 'error');
        });

        // 保存待处理请求标记（用于在使用时取消视觉效果）
        this._pendingRequest = request;
    }

    showMessage(message, type = 'info') {
        const messageEl = document.getElementById(this.options.messageId);
        const captchaInput = document.getElementById(this.options.inputId);
        if (messageEl) {
            messageEl.innerHTML = '';
            messageEl.className = `captcha-status-icon captcha-status-${type}`;
            if (type === 'success') {
                messageEl.innerHTML = '<svg viewBox="0 0 24 24" width="20" height="20"><circle cx="12" cy="12" r="10" fill="#28a745"/><path d="M8 12l3 3 5-5" stroke="#fff" stroke-width="2" fill="none"/></svg>';
            } else if (type === 'error') {
                messageEl.innerHTML = '<svg viewBox="0 0 24 24" width="20" height="20"><circle cx="12" cy="12" r="10" fill="#dc3545"/><path d="M8 8l8 8M16 8l-8 8" stroke="#fff" stroke-width="2"/></svg>';
            }
        }
        // 更新输入框边框颜色
        if (captchaInput) {
            captchaInput.classList.remove('captcha-input-success', 'captcha-input-error');
            if (type === 'success') {
                captchaInput.classList.add('captcha-input-success');
            } else if (type === 'error') {
                captchaInput.classList.add('captcha-input-error');
            }
        }
    }

    clearMessage() {
        const messageEl = document.getElementById(this.options.messageId);
        const captchaInput = document.getElementById(this.options.inputId);
        if (messageEl) {
            messageEl.innerHTML = '';
            messageEl.className = 'captcha-status-icon';
        }
        if (captchaInput) {
            captchaInput.classList.remove('captcha-input-success', 'captcha-input-error');
        }
    }

    getValue() {
        const captchaInput = document.getElementById(this.options.inputId);
        return captchaInput ? captchaInput.value : '';
    }

    setValue(value) {
        const captchaInput = document.getElementById(this.options.inputId);
        if (captchaInput) {
            captchaInput.value = value;
        }
    }

    focus() {
        const captchaInput = document.getElementById(this.options.inputId);
        if (captchaInput) {
            captchaInput.focus();
        }
    }

    validate() {
        const value = this.getValue();
        if (!value) {
            this.showMessage('请输入验证码', 'error');
            return false;
        }

        // 简单的客户端验证
        if (value.length < 4 || value.length > 6) {
            this.showMessage('验证码长度错误', 'error');
            return false;
        }

        return true;
    }

    /**
     * 手动初始化指定容器内的验证码组件
     * @param {string|HTMLElement} selector - 容器选择器或DOM元素
     * @returns {Captcha|null} 验证码实例
     */
    static init(selector) {
        let container;
        if (typeof selector === 'string') {
            container = document.querySelector(selector);
        } else {
            container = selector;
        }
        if (!container) {
            return null;
        }
        return new Captcha(container);
    }

    /**
     * 手动初始化页面上所有未初始化的验证码组件
     * @param {HTMLElement} [root=document] - 扫描的根元素
     * @returns {Captcha[]} 新创建的验证码实例数组
     */
    static initAll(root = document) {
        const instances = [];
        const containers = root.querySelectorAll('.captcha-wrapper:not([data-captcha-initialized])');
        containers.forEach(container => {
            container.setAttribute('data-captcha-initialized', 'true');
            const instance = new Captcha(container);
            instances.push(instance);
        });
        return instances;
    }

    /**
     * 根据容器元素获取验证码实例
     * @param {HTMLElement|string} container - 容器元素或选择器
     * @returns {Captcha|null} 验证码实例
     */
    static getInstance(container) {
        if (!Captcha._instances) return null;
        let el;
        if (typeof container === 'string') {
            el = document.querySelector(container);
        } else {
            el = container;
        }
        if (!el) return null;
        return Captcha._instances.get(el) || null;
    }

    /**
     * 查找指定父元素内部的所有验证码实例
     * @param {HTMLElement} parent - 父元素
     * @returns {Captcha[]} 验证码实例数组
     */
    static getInstancesIn(parent) {
        if (!Captcha._instances || !parent) return [];
        const results = [];
        Captcha._instances.forEach((instance, container) => {
            if (parent.contains(container)) {
                results.push(instance);
            }
        });
        return results;
    }
}

// 自动初始化页面上的验证码组件
document.addEventListener('DOMContentLoaded', () => {
    Captcha.initAll();
});