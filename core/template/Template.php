<?php
/**
 * BlogKit - 轻量开源博客系统 (Lightweight Open-Source Blogging System)
 *
 * 版权所有 (C) 2026 石林波 (Bana)，保留所有权利。
 * Copyright (C) 2026 Shi Linbo (Bana). All rights reserved.
 *
 * 项目主页：https://www.blogkit.cn
 * 源码仓库：https://github.com/Bana/blogkit （主仓库）
 *           https://gitee.com/Bana/blogkit （镜像仓库）
 * 社区反馈：https://www.blogkit.cn/community
 *
 * 本程序为自由软件，依据 GNU General Public License v3.0 (GPLv3) 授权发布：
 * 您可依据协议自由使用、修改与再分发，但依据 GPLv3 第 4 条，
 * 分发时须保留本版权声明与许可声明，并随附协议全文；
 * 本程序不提供任何担保。协议全文：https://www.gnu.org/licenses/gpl-3.0.html
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */


/**
 * 模板引擎类（核心协调器）
 * 
 * 职责：模板变量管理、站点/主题配置加载、组件编排
 * 解析委托给：TemplateParser、TemplateInheritance、TemplateCondition、TemplateUrl、TemplateOutput
 * 
 * @package BlogKit
 * @since 2.0.0
 */
class Template {
    private static $instance = null;
    // ========== 核心属性 ==========
    private $templatePath;
    private $data = [];
    private $includePaths = [];
    private $siteConfig = [];
    private $pagination = null;
    private $breadcrumb = [];
    private $currentLoopItem = null;
    private $currentLoopData = [];
    private $currentLoopKey = null;          // 当前循环的键（如年份、月份）
    private $currentLoopIndex = 0;           // 当前循环的索引（0, 1, 2...）
    private $blocks = [];
    private $functions = [];
    private $macros = [];
    private $debugMode = false;
    private $errorLog = [];
    private $currentTemplateName = '';
    private $currentLang = 'zh-CN';
    private $currentTheme = 'default';
    private $themeSettings = [];

    // ========== 子组件 ==========
    
    /** @var TemplateFilter */
    private $filter;
    
    /** @var TemplateCompiler */
    private $compiler;
    
    /** @var TemplateInheritance */
    private $inheritance;
    
    /** @var TemplateParser */
    private $parser;
    
    /** @var TemplateCondition */
    private $condition;
    
    /** @var TemplateUrl */
    private $urlBuilder;
    
    /** @var TemplateOutput */
    private $outputHelper;

    // ========================================================================
    //  构造函数 & 初始化
    // ========================================================================

    /**
     * 构造函数
     * @param string $templatePath 模板路径
     * @param array $siteConfig 站点配置
     */
    public function __construct($templatePath = '', $siteConfig = []) {
        Config::init();

        // 安全说明：已彻底移除 ?theme_preview= 前台预览参数支持。
        // 原实现允许任何访客通过 URL 参数未登录切换全站主题，存在滥用风险。

        if (empty($templatePath)) {
            $this->currentTheme = Config::get('default_theme', 'default');
            $templatePath = ROOT_PATH . '/themes/' . $this->currentTheme . '/templates/';
            
            // 向后兼容：如果没有 templates/ 子目录，回退到主题根目录
            if (!is_dir($templatePath)) {
                $templatePath = ROOT_PATH . '/themes/' . $this->currentTheme . '/';
            }
        } else {
            $pathParts = explode('/', rtrim($templatePath, '/'));
            $this->currentTheme = end($pathParts);
        }
        
        $this->templatePath = rtrim($templatePath, '/') . '/';
        $this->includePaths[] = $this->templatePath;
        $this->includePaths[] = $this->templatePath . 'common/';
        
        // 初始化所有子组件
        $this->filter = new TemplateFilter();
        // 模板缓存跟随后台"缓存功能开关"：cache_enabled 关闭时模板缓存也关闭
        $globalCacheEnabled = Config::get('cache_enabled', '0') == '1'; // 兜底默认关闭，与 install.sql 保持一致
        $cacheEnabled = $globalCacheEnabled && Config::get('template_cache.enabled', true);
        $this->compiler = new TemplateCompiler($cacheEnabled);
        $this->inheritance = new TemplateInheritance($this);
        $this->parser = new TemplateParser($this);
        $this->condition = new TemplateCondition($this);
        $this->urlBuilder = new TemplateUrl($this);
        $this->outputHelper = new TemplateOutput($this);
        
        // 加载配置
        $this->loadSiteConfig();
        $this->loadThemeSettings();
        $this->loadExtensions();
        
        // 通知相关
        if (isset($_SESSION['user'])) {
            require_once APP_PATH . '/Models/NotificationModel.php';
            $notificationModel = new NotificationModel();
            $this->assign('total_notifications', $notificationModel->getUnreadCount($_SESSION['user']['id']));
        } else {
            $this->assign('total_notifications', 0);
        }
        
        // CSRF Token：仅在后台 security_csrf_protection = 1 时生成并注入
        $csrfEnabled = class_exists('Security') && method_exists('Security', 'getSecurityConfig')
            ? (bool)Security::getSecurityConfig('csrf_protection', 1)
            : true;
        if ($csrfEnabled) {
            if (!isset($_SESSION['csrf_token']) || empty($_SESSION['csrf_token'])) {
                if (class_exists('Security')) {
                    Security::generateCsrfToken();
                } else {
                    // 兜底：直接生成一个随机 token
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    $_SESSION['csrf_token_time'] = time();
                }
            }
            $this->assign('csrf_token', $_SESSION['csrf_token']);
        } else {
            // 开关关闭时：清除现有 token，并向模板赋值空字符串，避免残留导致前端误判
            unset($_SESSION['csrf_token']);
            unset($_SESSION['csrf_token_time']);
            $this->assign('csrf_token', '');
        }
        
        // 注入 CSP nonce，供模板内联脚本使用
        if (isset($_SESSION['csp_nonce'])) {
            $this->assign('csp_nonce', $_SESSION['csp_nonce']);
        }
    }
    
    private function loadExtensions() {
        if (file_exists(CORE_PATH . '/Template/TemplateExtension.php')) {
            require_once CORE_PATH . '/Template/TemplateExtension.php';
            TemplateExtension::registerAll($this);
        }
        if (file_exists(CORE_PATH . '/lib/Security.php')) {
            require_once CORE_PATH . '/lib/Security.php';
        }
    }
    
    private function loadSiteConfig() {
        $siteUrl = Config::get('site.url', '');
        if (empty($siteUrl)) {
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $siteUrl = "$protocol://$host";
        }
        
        $this->siteConfig['url'] = $siteUrl;
        $this->siteConfig['name'] = Config::get('site_name', 'My Blog');
        $this->siteConfig['description'] = Config::get('site_description', 'A simple blog');
        $this->siteConfig['charset'] = Config::get('site.charset', 'UTF-8');
        $this->siteConfig['logo'] = Config::get('site_logo', '');
        $this->siteConfig['logo_display_mode'] = Config::get('logo_display_mode', 'auto');
        
        $rewriteEnabled = Config::get('rewrite_enabled', 0);
        $rssRule = Config::get('rewrite_rss', 'rss');
        $this->siteConfig['rss_url'] = $rewriteEnabled 
            ? $siteUrl . '/' . $rssRule 
            : $siteUrl . '/index.php/' . $rssRule;
        
        $this->siteConfig['comment_enabled'] = Config::get('comment_enabled', 1);
        $this->siteConfig['like_enabled'] = Config::get('like_enabled', 1);
        $this->siteConfig['follow_enabled'] = Config::get('follow_enabled', 1);
        $this->siteConfig['read_history_enabled'] = Config::get('read_history_enabled', 1);
        $this->siteConfig['comment_moderation'] = Config::get('comment_moderation', 0);
        $this->siteConfig['message_enabled'] = Config::get('message_enabled', 1);
        $this->siteConfig['message_duration'] = Config::get('message_duration', 3);
        $this->siteConfig['user_registration'] = Config::get('user_registration', 1);
        $this->siteConfig['profile_avatar_enabled'] = Config::get('profile_avatar_enabled', 1);
        $this->siteConfig['favorite_enabled'] = Config::get('favorite_enabled', 1);
        
        $this->assign('config', Config::getAll());
        // 确保 config 中关键功能开关有合理的默认值（即使数据库中未设置，也使用默认值）
        // 例如：follow_enabled 若未在数据库中设置，模板中 {if config.follow_enabled} 会判断为 false
        // 这里统一从 siteConfig 中合并带默认值的配置项，保证模板判断的一致性
        $configData = $this->data['config'];
        if (!isset($configData['follow_enabled'])) {
            $configData['follow_enabled'] = $this->siteConfig['follow_enabled'];
        }
        if (!isset($configData['message_enabled'])) {
            $configData['message_enabled'] = $this->siteConfig['message_enabled'];
        }
        if (!isset($configData['like_enabled'])) {
            $configData['like_enabled'] = $this->siteConfig['like_enabled'];
        }
        if (!isset($configData['favorite_enabled'])) {
            $configData['favorite_enabled'] = $this->siteConfig['favorite_enabled'];
        }
        if (!isset($configData['comment_enabled'])) {
            $configData['comment_enabled'] = $this->siteConfig['comment_enabled'];
        }
        $this->assign('config', $configData);
        $this->assign('user_registration', (bool)Config::get('user_registration', 1));
        $this->assign('captcha_enabled', (bool)Config::get('captcha_enabled', 0));
        $this->assign('captcha_login_enabled', (bool)Config::get('captcha_login_enabled', 1));
        $this->assign('captcha_register_enabled', (bool)Config::get('captcha_register_enabled', 1));
        $this->assign('captcha_comment_enabled', (bool)Config::get('captcha_comment_enabled', 1));
        $this->assign('captcha_forgot_password_enabled', (bool)Config::get('captcha_forgot_password_enabled', 1));
        $this->assign('captcha_reset_enabled', (bool)Config::get('captcha_reset_enabled', 1));
        $this->assign('captcha_admin_login_enabled', (bool)Config::get('captcha_admin_login_enabled', 0));
        $this->assign('captcha_width', Config::get('captcha_width', 120));
        $this->assign('captcha_height', Config::get('captcha_height', 40));
        $this->assign('captcha_font_size', Config::get('captcha_font_size', 20));
        
        $this->loadCurrentLang();
    }
    
    private function loadThemeSettings() {
        $this->themeSettings = [];
        // 从主题根目录（而非 templates/ 子目录）读取 theme.json
        // 因为 theme.json 位于 /themes/{theme_name}/theme.json，而不是 /themes/{theme_name}/templates/theme.json
        $themeRootPath = rtrim(dirname($this->templatePath), '/') . '/theme.json';
        $themePath = file_exists($themeRootPath) ? $themeRootPath : ($this->templatePath . 'theme.json');
        $defaultSettings = [];
        
        if (file_exists($themePath)) {
            $jsonContent = json_decode(file_get_contents($themePath), true);
            if ($jsonContent && isset($jsonContent['settings'])) {
                foreach ($jsonContent['settings'] as $setting) {
                    $defaultSettings[$setting['key']] = $setting['default'] ?? '';
                }
            }
        }
        
        $db = Database::getInstance();
        $savedSettings = [];
        $themeInfo = $db->fetch("SELECT settings FROM {$db->table('theme')} WHERE slug = ?", [$this->currentTheme]);
        
        if ($themeInfo && !empty($themeInfo['settings'])) {
            $decodedSettings = json_decode($themeInfo['settings'], true);
            // 只有当解码后的数据不为空时才使用数据库中的设置
            if (is_array($decodedSettings) && !empty($decodedSettings)) {
                $savedSettings = $decodedSettings;
            }
        }
        
        // 如果数据库中没有有效的设置，尝试从旧格式读取
        if (empty($savedSettings)) {
            $prefix = "theme_{$this->currentTheme}_";
            $results = $db->fetchAll("SELECT name, value FROM {$db->table('config')} WHERE name LIKE ?", [$prefix . '%']);
            foreach ($results as $result) {
                $key = str_replace($prefix, '', $result['name']);
                $savedSettings[$key] = $result['value'];
            }
        }
        
        // 合并默认设置和保存的设置，保存的设置优先级更高
        $this->themeSettings = array_merge($defaultSettings, $savedSettings);
        $this->assign('theme_settings', $this->themeSettings);
        foreach ($this->themeSettings as $key => $value) {
            $this->assign("theme_{$key}", $value);
        }
    }
    
    public function getThemeSetting($key, $default = '') {
        return $this->themeSettings[$key] ?? $default;
    }
    
    /**
     * 加载当前语言（多语言已从核心移除，默认使用 'zh-CN'）
     * —— 若启用多语言插件，插件可通过 Template::getInstance()->setCurrentLang('xx') 注入实际语言值
     */
    private function loadCurrentLang() {
        $this->currentLang = 'zh-CN';
        $this->assign('current_lang', $this->currentLang);
        // 兼容：如果插件重新定义了 Lang 类，则读取它提供的 supported_langs
        if (class_exists('Lang') && method_exists('Lang', 'getSupportedLangs')) {
            $this->assign('supported_langs', Lang::getSupportedLangs());
        } else {
            $this->assign('supported_langs', ['zh-CN' => ['name' => '简体中文', 'native' => '简体中文', 'flag' => '🇨🇳']]);
        }
    }

    // ========================================================================
    //  模板变量管理
    // ========================================================================

    /**
     * 分配模板变量
     */
    public function assign($key, $value) {
        $this->data[$key] = $value;
        if ($key === 'pagination' && is_object($value)) {
            $this->pagination = $value;
        }
        if ($key === 'breadcrumb' && is_array($value)) {
            $this->breadcrumb = $value;
        }
        return $this;
    }

    /**
     * 临时设置数据变量（用于宏调用等场景）
     */
    public function dataSet($key, $value) {
        $this->data[$key] = $value;
    }

    /**
     * 恢复数据状态（用于宏调用等场景）
     */
    public function dataRestore($originalData) {
        $this->data = $originalData;
    }

    /**
     * 获取嵌套变量值
     */
    public function getDataValue($key) {
        if (!isset($this->data[$key])) {
            if (strpos($key, '.') !== false) {
                $keys = explode('.', $key);
                $value = $this->data;
                foreach ($keys as $k) {
                    if (is_array($value)) {
                        if (!isset($value[$k])) return null;
                        $value = $value[$k];
                    } elseif (is_object($value)) {
                        if (!property_exists($value, $k)) return null;
                        $value = $value->$k;
                    } else {
                        return null;
                    }
                }
                return $value;
            }
            return null;
        }
        return $this->data[$key];
    }

    /**
     * 安全转义值
     */
    public function escapeValue($value) {
        if (is_string($value)) {
            return htmlspecialchars($value);
        } elseif (is_array($value)) {
            return 'Array';
        } elseif (is_object($value)) {
            return get_class($value);
        } elseif (is_numeric($value)) {
            return (string)$value;
        } elseif (is_bool($value)) {
            return $value ? 'true' : 'false';
        } elseif ($value === null) {
            return '';
        }
        return (string)$value;
    }

    // ========================================================================
    //  渲染入口
    // ========================================================================

    public function render($templateName) {
        $content = $this->loadTemplate($templateName);
        $content = $this->parser->parse($content);
        
        // 主题资产随主题存放，URL 无需重写，由 index.php 资产处理器直接 serve
        return $content;
    }
    
    public function display($templateName) {
        echo $this->render($templateName);
    }

    // ========================================================================
    //  模板加载（供子组件调用）
    // ========================================================================

    /**
     * 加载模板文件
     * @param string $templateName 模板文件名（不含.html扩展名）
     * @param bool $skipParse 是否跳过解析（用于加载父模板）
     * @return string 模板内容
     */
    public function loadTemplate($templateName, $skipParse = false) {
        $originalName = $templateName;
        $cleanName = str_replace('.html', '', $templateName);
        
        $searchPatterns = [];
        if (strpos($cleanName, '/') !== false) {
            $searchPatterns[] = $cleanName;
            $searchPatterns[] = $cleanName . '.html';
        } else {
            $searchPatterns[] = $cleanName;
            $searchPatterns[] = $cleanName . '.html';
            $searchPatterns[] = 'common/' . $cleanName;
            $searchPatterns[] = 'common/' . $cleanName . '.html';
        }
        
        foreach ($this->includePaths as $path) {
            foreach ($searchPatterns as $pattern) {
                $file = $path . $pattern;
                if (file_exists($file)) {
                    if ($this->compiler->isEnabled() && $this->compiler->isCompiled($file) && !$skipParse) {
                        return $this->compiler->getCompiled($file);
                    }
                    
                    $content = file_get_contents($file);
                    
                    if ($skipParse) {
                        return $content;
                    }
                    
                    $content = $this->inheritance->parseInheritance($content, $file);
                    $content = $this->inheritance->parseBlocks($content);
                    
                    if ($this->compiler->isEnabled()) {
                        $this->compiler->saveCompiled($file, $content);
                    }
                    
                    return $content;
                }
            }
        }
        
        return "Template file '{$originalName}' not found. (Searched patterns: " . implode(', ', $searchPatterns) . ")";
    }

    // ========================================================================
    //  子组件访问器
    // ========================================================================

    /** @return TemplateFilter */
    public function filter() { return $this->filter; }
    
    /** @return TemplateCompiler */
    public function compiler() { return $this->compiler; }
    
    /** @return TemplateInheritance */
    public function inheritance() { return $this->inheritance; }
    
    /** @return TemplateParser */
    public function parser() { return $this->parser; }
    
    /** @return TemplateCondition */
    public function condition() { return $this->condition; }
    
    /** @return TemplateUrl */
    public function url() { return $this->urlBuilder; }
    
    /** @return TemplateOutput */
    public function output() { return $this->outputHelper; }

    // ========================================================================
    //  公开数据访问器（供子组件使用）
    // ========================================================================

    public function getData() { return $this->data; }
    public function getSiteConfig() { return $this->siteConfig; }
    public function getPagination() { return $this->pagination; }
    public function getBreadcrumb() { return $this->breadcrumb; }
    public function getCurrentLoopItem() { return $this->currentLoopItem; }
    public function getCurrentLoopData() { return $this->currentLoopData; }
    public function getCurrentLoopKey() { return $this->currentLoopKey; }
    public function getCurrentLoopIndex() { return $this->currentLoopIndex; }
    public function setCurrentLoopKey($key) { $this->currentLoopKey = $key; }
    public function setCurrentLoopIndex($index) { $this->currentLoopIndex = $index; }
    public function getFunctions() { return $this->functions; }
    public function getMacros() { return $this->macros; }
    public function getCurrentLang() { return $this->currentLang; }
    /**
     * 允许插件动态设置当前语言（多语言插件可调用此接口注入）
     * @param string $lang
     */
    public function setCurrentLang($lang) {
        $this->currentLang = $lang;
        $this->assign('current_lang', $lang);
    }
    public function getCurrentTheme() { return $this->currentTheme; }
    public function getThemeSettings() { return $this->themeSettings; }
    public function getTemplatePath() { return $this->templatePath; }
    public function getIncludePaths() { return $this->includePaths; }
    public function getDebugMode() { return $this->debugMode; }
    public function getErrorLog() { return $this->errorLog; }
    public function getCurrentTemplateName() { return $this->currentTemplateName; }

    // ========================================================================
    //  公开状态修改器（供子组件使用）
    // ========================================================================

    public function setCurrentLoopItem($item) { $this->currentLoopItem = $item; }
    public function setCurrentLoopData($data) { $this->currentLoopData = $data; }
    public function setBlocks($blocks) { $this->blocks = $blocks; }
    public function blocks() { return $this->blocks; }
    public function setDebugMode($debugMode) { $this->debugMode = $debugMode; }
    public function setCurrentTemplateName($name) { $this->currentTemplateName = $name; }
    public function setPagination($pagination) { $this->pagination = $pagination; }
    public function setBreadcrumb($breadcrumb) { $this->breadcrumb = $breadcrumb; }

    // ========================================================================
    //  调试
    // ========================================================================

    public function logError($message, $level = 'error') {
        $this->errorLog[] = [
            'level' => $level,
            'message' => $message,
            'template' => $this->currentTemplateName,
            'time' => date('Y-m-d H:i:s')
        ];
        if ($this->debugMode) {
            error_log("Template Debug [{$level}]: {$message} (Template: {$this->currentTemplateName})");
        }
    }

    public function renderDebugInfo() {
        return $this->outputHelper->renderDebugInfo();
    }

    // ========================================================================
    //  注册方法
    // ========================================================================

    public function registerFunction($name, callable $callback) {
        $this->functions[$name] = $callback;
    }

    public function registerFilter($name, callable $callback) {
        $this->filter->register($name, $callback);
    }

    public function registerMacro($name, $content, $params = []) {
        $this->macros[$name] = ['content' => $content, 'params' => $params];
    }

    // ========================================================================
    //  测试方法（保持向后兼容）
    // ========================================================================

    public function testParse($content) {
        return $this->parser->parse($content);
    }

    public function testExtractBlocks($content) {
        $this->setBlocks([]);
        $this->inheritance->extractBlocks($content);
        return $this->blocks;
    }

    public function testReplaceBlocks($content) {
        return $this->inheritance->replaceBlocks($content);
    }

    // ========================================================================
    //  委托给子组件的公共方法
    // ========================================================================

    /** 生成URL（委托给TemplateUrl） */
    public function generateUrl($type, $item = null, $paramName = '') {
        return $this->urlBuilder->generate($type, $item, $paramName);
    }

    /** 渲染面包屑（委托给TemplateOutput） */
    public function renderBreadcrumb() {
        return $this->outputHelper->renderBreadcrumb();
    }

    /** 渲染分页信息（委托给TemplateOutput） */
    public function renderPaginationInfo() {
        return $this->outputHelper->renderPaginationInfo();
    }

    /** 渲染分页（委托给TemplateOutput） */
    public function renderPagination() {
        return $this->outputHelper->renderPagination();
    }

    /** 清空模板缓存（委托给TemplateCompiler） */
    public function clearCache() {
        return $this->compiler->clear();
    }

    /** CSRF令牌字段 */
    public function getCsrfField() {
        return $this->outputHelper->getCsrfField();
    }

    /** 验证CSRF令牌 */
    public function validateCsrfToken($token) {
        if (class_exists('Security')) {
            return Security::validateCsrfToken($token);
        }
        return false;
    }

    /**
     * 获取单例实例（用于插件系统服务容器）
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
