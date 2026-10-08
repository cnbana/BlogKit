<?php
/**
 * BlogKit - 轻量开源博客系统 (Lightweight Open-Source Blogging System)
 *
 * 版权所有 (C) 2026 石林波 (Bana)，保留所有权利。
 * Copyright (C) 2026 Shi Linbo (Bana). All rights reserved.
 *
 * 项目主页：https://www.blogkit.cn
 * 源码仓库：https://github.com/cnbana/BlogKit （主仓库）
 *           https://gitee.com/slinbo/blogkit （镜像仓库）
 * 社区反馈：https://www.blogkit.cn/community
 *
 * 本程序为自由软件，依据 GNU General Public License v3.0 (GPLv3) 授权发布：
 * 您可依据协议自由使用、修改与再分发，但依据 GPLv3 第 4 条，
 * 分发时须保留本版权声明与许可声明，并随附协议全文；
 * 本程序不提供任何担保。协议全文：https://www.gnu.org/licenses/gpl-3.0.html
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */


class Plugin {
    private static $instance = null;
    private static $is_initializing = false;
    private static $current_instance = null;
    private $hooks = [];
    private $plugins = [];
    /** @var array 插件清单缓存（slug => plugin.json 解析结果） */
    private $manifests = [];

    /**
     * 侧栏只渲染核心菜单（bk_permission 种子数据 + 授权过滤）：
     * 插件不进侧边栏，其管理页统一从「插件管理 → 插件详情 → 配置」进入；
     * 插件对侧栏/授权树的唯一扩展通道是挂载点（Plugin::slot）埋点。
     */

    /**
     * 构造函数
     */
    private function __construct() {
        // 确保PLUGINS_PATH常量已定义
        if (!defined('PLUGINS_PATH')) {
            define('PLUGINS_PATH', ROOT_PATH . '/plugins');
        }
        // 设置当前实例引用
        self::$current_instance = $this;
        // 设置初始化标志
        self::$is_initializing = true;
        // 加载插件
        $this->loadPlugins();
        // 清除初始化标志
        self::$is_initializing = false;
    }
    
    /**
     * 验证插件文件路径
     * @param string $path 文件路径
     * @param string $slug 插件标识
     * @return bool 路径是否安全
     */
    private function validatePluginPath($path, $slug) {
        // 规范化路径
        $realPath = realpath($path);
        $pluginDir = realpath(PLUGINS_PATH . '/' . $slug);
        
        // 确保路径存在且在插件目录内
        if (!$realPath || !$pluginDir) {
            return false;
        }
        
        // 检查路径是否在插件目录内
        return strpos($realPath, $pluginDir) === 0;
    }
    
    /**
     * 获取单例实例
     * @return Plugin
     */
    public static function getInstance() {
        if (self::$instance === null) {
            // 如果已经在初始化过程中，返回当前正在初始化的实例
            if (self::$is_initializing) {
                return self::$current_instance;
            }
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 加载插件
     */
    private function loadPlugins() {
        // 从数据库获取激活的插件
        $db = Database::getInstance();
        $plugins = $db->fetchAll("SELECT * FROM {$db->table('plugin')} WHERE status = 1");
        
        foreach ($plugins as $plugin) {
            $this->loadPlugin($plugin);
        }
    }
    
    /**
     * 加载单个插件
     * @param array $plugin 插件信息
     */
    private function loadPlugin($plugin) {
        // 确保$plugin是数组类型，避免致命错误
        if (!is_array($plugin) || !isset($plugin['slug'])) {
            return;
        }
        
        $pluginFile = PLUGINS_PATH . '/' . $plugin['slug'] . '/plugin.php';
        
        if (file_exists($pluginFile)) {
            // 验证路径安全性
            if (!$this->validatePluginPath($pluginFile, $plugin['slug'])) {
                return;
            }
            
            require_once $pluginFile;
            // 将slug转换为PascalCase格式
            $parts = explode('_', $plugin['slug']);
            $pascalCaseSlug = implode('', array_map('ucfirst', $parts));
            $className = $pascalCaseSlug . 'Plugin';
            
            // 兼容没有Plugin后缀的类名
            if (!class_exists($className)) {
                $className = $pascalCaseSlug;
            }
            
            if (class_exists($className)) {
                $this->plugins[$plugin['slug']] = new $className($plugin);
            }
        }
    }
    
    /**
     * 扫描插件目录，发现新插件
     * @return array 发现的新插件列表
     */
    public function scanPlugins() {
        $foundPlugins = [];
        
        // 确保插件目录存在
        if (!is_dir(PLUGINS_PATH)) {
            mkdir(PLUGINS_PATH, 0755, true);
            return $foundPlugins;
        }
        
        // 遍历插件目录
        $pluginDirs = glob(PLUGINS_PATH . '/*', GLOB_ONLYDIR);
        
        foreach ($pluginDirs as $pluginDir) {
            $pluginInfoFile = $pluginDir . '/plugin.json';
            
            // 检查是否存在插件信息文件
            if (file_exists($pluginInfoFile)) {
                $pluginInfo = json_decode(file_get_contents($pluginInfoFile), true);
                
                if ($pluginInfo) {
                    // 验证插件信息的完整性
                    if (isset($pluginInfo['name'], $pluginInfo['slug'], $pluginInfo['version'], $pluginInfo['description'], $pluginInfo['author'])) {
                        $slug = $pluginInfo['slug'];
                        $foundPlugins[$slug] = $pluginInfo;
                        
                        // 检查插件是否已在数据库中注册
                        $db = Database::getInstance();
                        $existing = $db->fetch("SELECT * FROM {$db->table('plugin')} WHERE slug = ?", array($slug));
                        
                        if ($existing && $existing['status'] == -1) {
                            // 插件已被卸载，不要重新注册
                            continue;
                        }
                        // 移除自动注册逻辑，让新插件保持未安装状态
                        // 新插件会在PluginController中显示为"未安装的插件"
                    }
                }
            }
        }
        
        return $foundPlugins;
    }
    
    /**
     * 注册插件到数据库
     * @param array $pluginInfo 插件信息
     */
    private function registerPlugin($pluginInfo) {
        // 确保$pluginInfo是数组类型，避免致命错误
        if (!is_array($pluginInfo)) {
            return;
        }
        
        $db = Database::getInstance();
        
        $pluginData = array(
            'name' => isset($pluginInfo['name']) ? $pluginInfo['name'] : '',
            'slug' => isset($pluginInfo['slug']) ? $pluginInfo['slug'] : '',
            'description' => isset($pluginInfo['description']) ? $pluginInfo['description'] : '',
            'version' => isset($pluginInfo['version']) ? $pluginInfo['version'] : '',
            'status' => 0, // 默认禁用状态
            'settings' => json_encode(isset($pluginInfo['default_settings']) ? $pluginInfo['default_settings'] : array()),
            'created_at' => time(),
            'installed_at' => time()
        );
        
        $db->insert('plugin', $pluginData);
    }
    
    /**
     * 安装插件
     * @param string $slug 插件标识
     * @return bool 是否安装成功
     */
    public function installPlugin($slug) {
        // 依赖检测
        if (!$this->checkDependencies($slug)) {
            return false;
        }
        
        // 版本兼容性检查
        if (!$this->checkVersionCompatibility($slug)) {
            return false;
        }
        
        // 获取插件信息
        $db = Database::getInstance();
        $plugin = $db->fetch("SELECT * FROM {$db->table('plugin')} WHERE slug = ?", array($slug));
        
        if (!$plugin) {
            // 插件不在数据库中，尝试从文件读取信息并注册
            $pluginInfoFile = PLUGINS_PATH . '/' . $slug . '/plugin.json';
            if (file_exists($pluginInfoFile)) {
                $pluginInfo = json_decode(file_get_contents($pluginInfoFile), true);
                if ($pluginInfo && isset($pluginInfo['name'], $pluginInfo['slug'], $pluginInfo['version'], $pluginInfo['description'], $pluginInfo['author'])) {
                    // 注册插件
                    $this->registerPlugin($pluginInfo);
                    // 重新获取插件信息
                    $plugin = $db->fetch("SELECT * FROM {$db->table('plugin')} WHERE slug = ?", array($pluginInfo['slug']));
                }
            }
            
            if (!$plugin) {
                // 插件注册失败
                return false;
            }
        } else if ($plugin['status'] == -1) {
            // 插件已卸载，更新状态为已安装并更新安装时间
            $db->update('plugin', array('status' => 0, 'installed_at' => time()), array('slug' => $slug));
            // 更新插件信息
            $plugin['status'] = 0;
            $plugin['installed_at'] = time();
        }
        
        // 加载插件主文件
        $pluginFile = PLUGINS_PATH . '/' . $slug . '/plugin.php';
        if (!file_exists($pluginFile)) {
            return false;
        }
        
        // 验证路径安全性
        if (!$this->validatePluginPath($pluginFile, $slug)) {
            return false;
        }
        
        require_once $pluginFile;
        // 将slug转换为PascalCase格式
        $parts = explode('_', $slug);
        $pascalCaseSlug = implode('', array_map('ucfirst', $parts));
        $pluginClass = $pascalCaseSlug . 'Plugin';
        
        // 兼容没有Plugin后缀的类名
        if (!class_exists($pluginClass)) {
            $pluginClass = $pascalCaseSlug;
        }
        
        if (!class_exists($pluginClass)) {
            return false;
        }
        
        // 实例化插件并调用安装方法
        $pluginInstance = new $pluginClass($plugin);
        if (method_exists($pluginInstance, 'install')) {
            $pluginInstance->install();
        }

        // 注意：安装阶段不注册后台菜单——侧边栏只渲染核心菜单（bk_permission
        // 种子数据），插件管理页统一从插件详情页的「配置」入口进入，
        // 插件生命周期对 bk_permission 表零写入。

        return true;
    }
    
    /**
     * 卸载插件
     * @param string $slug 插件标识
     * @param bool $keepData 是否保留插件数据
     * @return bool 是否卸载成功
     */
    public function uninstallPlugin($slug, $keepData = false) {
        // 获取插件信息
        $db = Database::getInstance();
        $plugin = $db->fetch("SELECT * FROM {$db->table('plugin')} WHERE slug = ?", array($slug));
        
        if (!$plugin) {
            return false;
        }
        
        // 加载插件类
        $pluginFile = PLUGINS_PATH . '/' . $slug . '/plugin.php';
        if (file_exists($pluginFile)) {
            // 验证路径安全性
            if (!$this->validatePluginPath($pluginFile, $slug)) {
                return false;
            }
            
            require_once $pluginFile;
            // 将slug转换为PascalCase格式
            $parts = explode('_', $slug);
            $pascalCaseSlug = implode('', array_map('ucfirst', $parts));
            $className = $pascalCaseSlug . 'Plugin';
            
            // 兼容没有Plugin后缀的类名
            if (!class_exists($className)) {
                $className = $pascalCaseSlug;
            }
            
            if (class_exists($className)) {
                // 实例化插件并调用卸载方法
                $pluginInstance = new $className($plugin);
                if (method_exists($pluginInstance, 'uninstall')) {
                    // 传递是否保留数据的参数给插件的卸载方法
                    $pluginInstance->uninstall($keepData);
                }
            }
        }
        
        // 更新插件状态为-1（已卸载），而不是直接删除
        // 这样可以避免scanPlugins方法重新将其添加回来
        $db->update('plugin', array('status' => -1), array('slug' => $slug));

        // 同步清除该插件的页面授权名单（ACL），防止同 slug 重装时静默继承旧授权
        self::removeAdminAcl($slug);

        return true;
    }
    
    /**
     * 注册钩子
     * @param string $hook 钩子名称
     * @param callable $callback 回调函数
     * @param int $priority 优先级，数值越小优先级越高
     */
    public function addHook($hook, callable $callback, $priority = 10) {
        if (!isset($this->hooks[$hook])) {
            $this->hooks[$hook] = [];
        }
        
        $this->hooks[$hook][$priority][] = $callback;
        
        // 按优先级排序
        ksort($this->hooks[$hook]);
    }
    
    /**
     * 触发钩子
     * @param string $hook 钩子名称
     * @param mixed $args 传递给回调函数的参数
     * @return string 处理后的结果，确保返回字符串
     */
    public function doHook($hook, $args = null) {
        $result = '';
        if (isset($this->hooks[$hook])) {
            foreach ($this->hooks[$hook] as $priority => $callbacks) {
                foreach ($callbacks as $callback) {
                    $callback_result = call_user_func($callback, $args);
                    
                    // 处理不同类型的返回值
                    if (is_string($callback_result)) {
                        // 字符串直接拼接
                        $result .= $callback_result;
                    } elseif (is_array($callback_result)) {
                        // 数组转换为字符串（兼容旧插件）
                        $result .= implode('', array_filter($callback_result, 'is_string'));
                    } elseif (is_object($callback_result) && method_exists($callback_result, '__toString')) {
                        // 对象转换为字符串
                        $result .= (string) $callback_result;
                    }
                    // 其他类型（数字、布尔值等）忽略
                }
            }
        }
        
        return $result;
    }
    
    /**
     * 获取所有插件
     * @return array
     */
    public function getPlugins() {
        return $this->plugins;
    }
    
    /**
     * 获取单个插件
     * @param string $slug 插件标识
     * @return mixed
     */
    public function getPlugin($slug) {
        return isset($this->plugins[$slug]) ? $this->plugins[$slug] : null;
    }
    
    /**
     * 获取所有已激活插件
     * @return array
     */
    public function getActivePlugins() {
        return $this->plugins;
    }
    
    /**
     * 插件间通信：调用其他插件的方法
     * @param string $slug 目标插件标识
     * @param string $method 方法名
     * @param array $args 参数数组
     * @return mixed 方法调用结果
     */
    public function callPluginMethod($slug, $method, $args = []) {
        $plugin = $this->getPlugin($slug);
        if ($plugin && method_exists($plugin, $method)) {
            return call_user_func_array([$plugin, $method], $args);
        }
        return null;
    }
    
    /**
     * 插件间通信：触发插件特定事件
     * @param string $slug 目标插件标识
     * @param string $event 事件名称
     * @param mixed $data 事件数据
     * @return mixed 事件处理结果
     */
    public function triggerPluginEvent($slug, $event, $data = null) {
        $plugin = $this->getPlugin($slug);
        if ($plugin) {
            $eventMethod = 'on' . ucfirst($event);
            if (method_exists($plugin, $eventMethod)) {
                return call_user_func([$plugin, $eventMethod], $data);
            }
        }
        return null;
    }
    
    /**
     * 获取指定钩子的所有回调
     * @param string $hook 钩子名称
     * @return array 钩子回调数组
     */
    public function getHooks($hook) {
        return isset($this->hooks[$hook]) ? $this->hooks[$hook] : [];
    }

    // ==================== 插件清单与页面能力 ====================

    /**
     * 获取插件清单（plugin.json 解析结果，带内存缓存）
     * @param string $slug 插件标识
     * @return array 清单数组（文件不存在或解析失败返回空数组）
     */
    public function getManifest($slug) {
        if (array_key_exists($slug, $this->manifests)) {
            return $this->manifests[$slug];
        }

        $manifest = [];
        $manifestFile = PLUGINS_PATH . '/' . $slug . '/plugin.json';
        if (is_file($manifestFile)) {
            $decoded = json_decode((string)file_get_contents($manifestFile), true);
            if (is_array($decoded)) {
                $manifest = $decoded;
            }
        }

        $this->manifests[$slug] = $manifest;
        return $manifest;
    }

    /**
     * 检查插件后台路由是否可访问（仅校验，不执行路由）
     *
     * 插件后台页面的唯一访问门禁（AuthMiddleware 调用）：侧栏不渲染
     * 插件菜单、bk_permission 表不存插件菜单行，故页面访问控制采用
     * 静态放行规则——插件处于启用状态（$this->plugins 仅含 status=1）
     * 且目标页面已在 plugin.json 的 admin_routes 注册时放行，否则拒绝。
     * 与 dispatchAdminRoute 的前置校验保持一致，但不加载控制器文件，
     * 避免权限检查阶段产生类加载等副作用。
     *
     * @param string $action 后台 action（plugin.{slug}.{page}）
     * @return bool 路由是否可访问
     */
    public function hasAdminRoute($action) {
        $segments = explode('.', $action);
        if (count($segments) !== 3) {
            return false;
        }
        $slug = $segments[1];

        // 仅已激活插件可响应（与 dispatchAdminRoute 的激活校验一致）
        if (!isset($this->plugins[$slug])) {
            return false;
        }

        // 路由必须已在 plugin.json 的 admin_routes 中注册
        $manifest = $this->getManifest($slug);
        $routes = isset($manifest['admin_routes']) && is_array($manifest['admin_routes']) ? $manifest['admin_routes'] : [];

        return isset($routes[$action]);
    }

    /**
     * 分发插件后台页面请求（admin.php 入口调用）
     *
     * action 形如 plugin.{slug}.{page}，仅当插件处于激活状态
     * （Plugin 只加载 status=1 的插件）且 plugin.json 的 admin_routes
     * 中注册了该页面时才生效；未命中返回 false，由入口继续抛出 404。
     * 权限校验由 AuthMiddleware 在分发前完成（静态放行规则：
     * 插件启用 + 路由已注册，见 hasAdminRoute）。
     *
     * @param string $action 后台 action（plugin.{slug}.{page}）
     * @return bool 是否命中并执行了插件路由
     */
    public function dispatchAdminRoute($action) {
        $segments = explode('.', $action);
        if (count($segments) !== 3) {
            return false;
        }
        $slug = $segments[1];

        // 仅已激活插件可响应（停用即失效）
        if (!isset($this->plugins[$slug])) {
            return false;
        }

        $manifest = $this->getManifest($slug);
        $routes = isset($manifest['admin_routes']) && is_array($manifest['admin_routes']) ? $manifest['admin_routes'] : [];
        if (!isset($routes[$action])) {
            return false;
        }

        // 处理器格式：'Admin/XxxController.php@method'（相对插件根目录）
        $handler = $routes[$action];
        if (!is_string($handler) || strpos($handler, '@') === false) {
            return false;
        }
        list($relativeFile, $methodName) = explode('@', $handler, 2);

        $handlerFile = PLUGINS_PATH . '/' . $slug . '/' . ltrim($relativeFile, '/');
        // 路径安全校验：控制器文件必须位于该插件目录内
        if (!$this->validatePluginPath($handlerFile, $slug) || !is_file($handlerFile)) {
            return false;
        }

        require_once $handlerFile;
        $controllerClass = basename($relativeFile, '.php');
        if (!class_exists($controllerClass)) {
            return false;
        }

        $controller = new $controllerClass();
        if (!method_exists($controller, $methodName)) {
            return false;
        }

        call_user_func([$controller, $methodName]);
        return true;
    }

    // ==================== 插件页面角色级访问授权（ACL） ====================
    // 授权名单存储于 bk_config 表 plugin_admin_acl 键（JSON 对象）：
    //   {"slug": [roleId, ...], ...}
    // 语义规则：
    //   - 键不存在 / 无该插件条目 / 条目为空数组 → 所有可登录后台的角色放行
    //     （缺省放行保证向后兼容：未做任何配置的站点行为与旧版完全一致）；
    //   - 条目为非空数组 → 仅名单内角色可访问该插件的后台页面；
    //   - 超级管理员（角色 1）无条件放行，且名单中永不写入角色 1。

    /** @var string|null 插件 ACL 原始 JSON（请求内缓存，避免同一请求重复查库） */
    private static $admin_acl_raw = null;
    /** @var bool 上述缓存是否已初始化（null 是合法的查询结果，需独立标记） */
    private static $admin_acl_loaded = false;

    /**
     * 读取插件 ACL 原始数据（首次调用查库，后续读请求内缓存）
     * @return array 解析后的 ACL 映射（slug => 角色ID数组），无配置返回空数组
     */
    private static function getAdminAclMap() {
        if (!self::$admin_acl_loaded) {
            self::$admin_acl_loaded = true;
            $model = new ConfigModel();
            $raw = $model->getConfigByName('plugin_admin_acl');
            $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
            self::$admin_acl_raw = is_array($decoded) ? $decoded : [];
        }
        return self::$admin_acl_raw;
    }

    /**
     * 校验指定角色是否可访问指定插件的后台页面
     * 由 AuthMiddleware 在插件后台路由（plugin.{slug}.{page}）分发前调用；
     * 校验顺序：超级管理员无条件放行 → 缺省放行 → 名单精确匹配。
     *
     * @param string $slug 插件标识
     * @param int $roleId 访问者角色 ID
     * @return bool 是否允许访问
     */
    public static function checkAdminAcl($slug, $roleId) {
        // 超级管理员（角色 1）不受授权名单约束
        if ((int)$roleId === 1) {
            return true;
        }

        $acl = self::getAdminAclMap();
        // 未配置该插件的授权名单 → 缺省放行（向后兼容）
        if (!isset($acl[$slug]) || !is_array($acl[$slug]) || empty($acl[$slug])) {
            return true;
        }

        return in_array((int)$roleId, array_map('intval', $acl[$slug]), true);
    }

    /**
     * 保存插件后台页面的角色授权名单
     * 由 PluginController 详情页「访问授权」保存链调用；保存后清除
     * 请求内 ACL 缓存，确保同请求内的后续校验读到最新名单。
     *
     * @param string $slug 插件标识
     * @param array $roleIds 允许访问的角色 ID 列表（自动过滤角色 1 与非法值）
     * @return bool 是否保存成功
     */
    public static function saveAdminAcl($slug, $roleIds) {
        // 角色去重 + 取整 + 过滤：角色 1 永不放行入名单（其访问不受 ACL 约束），
        // 非正整数视为脏数据丢弃
        $clean = array();
        foreach ((array)$roleIds as $rid) {
            $rid = (int)$rid;
            if ($rid > 1 && !in_array($rid, $clean, true)) {
                $clean[] = $rid;
            }
        }
        sort($clean);

        $acl = self::getAdminAclMap();
        if (empty($clean)) {
            // 名单为空 = 恢复缺省放行：直接移除该插件条目，保持配置最小化
            unset($acl[$slug]);
        } else {
            $acl[$slug] = $clean;
        }

        $model = new ConfigModel();
        $result = $model->setConfig(
            'plugin_admin_acl',
            json_encode($acl),
            '插件后台页面角色授权名单（JSON：slug => 角色 ID 数组，缺省放行）',
            'array'
        );

        // 同步请求内缓存，保证保存后的即时校验一致
        self::$admin_acl_raw = $acl;
        return $result;
    }

    /**
     * 清除指定插件的授权名单（卸载插件时调用，避免残留孤儿条目：
     * 否则同 slug 插件重装后会静默继承旧授权）
     * @param string $slug 插件标识
     */
    public static function removeAdminAcl($slug) {
        $acl = self::getAdminAclMap();
        if (isset($acl[$slug])) {
            unset($acl[$slug]);
            $model = new ConfigModel();
            $model->setConfig(
                'plugin_admin_acl',
                json_encode($acl),
                '插件后台页面角色授权名单（JSON：slug => 角色 ID 数组，缺省放行）',
                'array'
            );
            self::$admin_acl_raw = $acl;
        }
    }

    /**
     * 获取指定插件当前生效的授权名单
     * 供详情页「访问授权」区块回显：返回 null 表示未配置（缺省放行），
     * 返回数组表示显式名单（仅名单内角色可访问）。
     *
     * @param string $slug 插件标识
     * @return array|null 当前授权角色 ID 数组；未配置时返回 null
     */
    public static function getAdminAcl($slug) {
        $acl = self::getAdminAclMap();
        if (!isset($acl[$slug]) || !is_array($acl[$slug]) || empty($acl[$slug])) {
            return null;
        }
        return array_map('intval', $acl[$slug]);
    }

    /**
     * 匹配插件前台路由（Router 分发前调用，静态方法便于入口直接使用）
     *
     * 按「先注册先得」顺序遍历已激活插件，命中即加载插件控制器并执行；
     * 未命中返回 false，由核心路由继续处理。
     * 路径支持 {param} 占位符（匹配除 / 外的任意字符），参数按出现顺序
     * 传给处理器方法；HTTP 方法为 * 或空时匹配任意方法。
     *
     * @param string $method HTTP 方法（GET/POST/...）
     * @param string $path 归一化后的请求路径（不含首尾斜杠）
     * @return bool 是否命中并执行了插件路由
     */
    public static function matchFrontRoute($method, $path) {
        $instance = self::getInstance();

        foreach ($instance->plugins as $slug => $pluginInstance) {
            $manifest = $instance->getManifest($slug);
            $routes = isset($manifest['routes']) && is_array($manifest['routes']) ? $manifest['routes'] : [];

            foreach ($routes as $route) {
                if (!is_array($route) || empty($route['path']) || empty($route['handler'])) {
                    continue;
                }

                // HTTP 方法匹配（* 或空表示任意方法）
                $routeMethod = isset($route['method']) ? strtoupper($route['method']) : '*';
                if ($routeMethod !== '*' && $routeMethod !== strtoupper($method)) {
                    continue;
                }

                // 路径匹配：{param} 占位符 → 除 / 外任意字符
                $pattern = trim($route['path'], '/');
                $pattern = preg_replace('/\{[a-zA-Z_]\w*\}/', '([^/]+)', $pattern);
                $pattern = str_replace('/', '\/', $pattern);
                if (!preg_match('/^' . $pattern . '$/', $path, $matches)) {
                    continue;
                }
                array_shift($matches);

                // 加载并执行插件控制器（处理器格式：'Controllers/XxxController.php@method'）
                list($relativeFile, $methodName) = explode('@', $route['handler'], 2);
                $handlerFile = PLUGINS_PATH . '/' . $slug . '/' . ltrim($relativeFile, '/');
                if (!$instance->validatePluginPath($handlerFile, $slug) || !is_file($handlerFile)) {
                    continue;
                }

                require_once $handlerFile;
                $controllerClass = basename($relativeFile, '.php');
                if (!class_exists($controllerClass)) {
                    continue;
                }

                $controller = new $controllerClass();
                if (!method_exists($controller, $methodName)) {
                    continue;
                }

                call_user_func_array([$controller, $methodName], $matches);
                return true;
            }
        }

        return false;
    }

    /**
     * 渲染插件模板（前台/后台页面输出）
     *
     * 复用核心 Template 引擎，模板根目录指向 plugins/{slug}/templates/，
     * 模板内可使用全部现有标签语法（循环不可嵌套等引擎约束同样适用）。
     *
     * @param string $slug 插件标识
     * @param string $tpl 模板名（相对插件 templates/ 目录，如 index.html）
     * @param array $data 模板变量
     */
    public function view($slug, $tpl, $data = []) {
        $templateDir = PLUGINS_PATH . '/' . $slug . '/templates/';
        if (!is_dir($templateDir) || !is_file($templateDir . $tpl)) {
            ExceptionHandler::handle404();
            return;
        }

        $template = new Template($templateDir);
        foreach ($data as $key => $value) {
            $template->assign($key, $value);
        }
        $template->display($tpl);
    }
    
    /**
     * 添加系统服务访问接口
     * @return array 系统服务列表
     */
    public function getSystemServices() {
        return [
            'database' => Database::getInstance(),
            'config' => Config::getInstance(),
            'log' => Log::getInstance(),
            'cache' => Cache::getInstance(),
            'router' => Router::getInstance(),
            'template' => Template::getInstance(),
            'plugin' => $this
        ];
    }
    
    /**
     * 获取指定系统服务
     * @param string $serviceName 服务名称
     * @return mixed 系统服务实例
     */
    public function getSystemService($serviceName) {
        $services = $this->getSystemServices();
        return isset($services[$serviceName]) ? $services[$serviceName] : null;
    }
    
    /**
     * 检查插件依赖
     * @param string $slug 插件标识
     * @return bool 依赖是否满足
     */
    public function checkDependencies($slug) {
        $pluginInfoFile = PLUGINS_PATH . '/' . $slug . '/plugin.json';
        if (!file_exists($pluginInfoFile)) {
            return false;
        }
        
        $pluginInfo = json_decode(file_get_contents($pluginInfoFile), true);
        if (!isset($pluginInfo['requirements']['dependencies']) || !is_array($pluginInfo['requirements']['dependencies'])) {
            return true;
        }
        
        $dependencies = $pluginInfo['requirements']['dependencies'];
        $db = Database::getInstance();
        
        foreach ($dependencies as $dependency) {
            if (isset($dependency['optional']) && $dependency['optional']) {
                continue;
            }
            
            $depSlug = $dependency['slug'];
            $depVersion = $dependency['version'];
            
            // 检查依赖插件是否已安装
            $depPlugin = $db->fetch("SELECT * FROM {$db->table('plugin')} WHERE slug = ? AND status != -1", array($depSlug));
            if (!$depPlugin) {
                // 依赖插件未安装
                return false;
            }
            
            // 检查版本是否兼容
            if (!$this->checkVersionCompatibility($depSlug, $depVersion)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * 检查版本兼容性
     * @param string $slug 插件标识
     * @param string $requiredVersion 要求的版本（可选）
     * @return bool 版本是否兼容
     */
    public function checkVersionCompatibility($slug, $requiredVersion = null) {
        $pluginInfoFile = PLUGINS_PATH . '/' . $slug . '/plugin.json';
        if (!file_exists($pluginInfoFile)) {
            return false;
        }
        
        $pluginInfo = json_decode(file_get_contents($pluginInfoFile), true);
        
        // 检查系统版本兼容性
        if (isset($pluginInfo['compatible_version'])) {
            // 这里可以添加系统版本检查逻辑
            // 暂时返回true，假设系统版本兼容
        }
        
        // 检查依赖版本兼容性
        if ($requiredVersion) {
            $currentVersion = $pluginInfo['version'];
            // 简单的版本比较（实际项目中可能需要更复杂的语义化版本比较）
            if (version_compare($currentVersion, $requiredVersion, '<')) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * 获取插件配置
     * @param string $slug 插件标识
     * @return array 插件配置
     */
    public function getPluginConfig($slug) {
        $db = Database::getInstance();
        $plugin = $db->fetch("SELECT settings FROM {$db->table('plugin')} WHERE slug = ?", array($slug));
        
        // 从plugin.json获取默认配置结构
        $pluginInfoFile = PLUGINS_PATH . '/' . $slug . '/plugin.json';
        $defaultConfig = array();
        if (file_exists($pluginInfoFile)) {
            $pluginInfo = json_decode(file_get_contents($pluginInfoFile), true);
            if (isset($pluginInfo['default_settings'])) {
                $defaultConfig = $pluginInfo['default_settings'];
            }
        }
        
        // 如果数据库中有配置，则合并到默认配置结构中
        if ($plugin && $plugin['settings']) {
            $savedConfig = json_decode($plugin['settings'], true);
            
            // 合并配置值到默认配置结构
            foreach ($savedConfig as $key => $value) {
                if (isset($defaultConfig[$key]) && is_array($defaultConfig[$key]) && isset($defaultConfig[$key]['type'])) {
                    // 如果是复杂配置格式，只更新value
                    // 检查$value是否是数组，如果是，说明数据库中存储的是完整结构，需要提取其中的value字段
                    if (is_array($value) && isset($value['value'])) {
                        $defaultConfig[$key]['value'] = $value['value'];
                    } else {
                        $defaultConfig[$key]['value'] = $value;
                    }
                } else {
                    // 如果是简单配置格式，直接设置值
                    $defaultConfig[$key] = $value;
                }
            }
        }
        
        return $defaultConfig;
    }
    
    /**
     * 保存插件配置
     * @param string $slug 插件标识
     * @param array $config 插件配置
     * @return bool 是否保存成功
     */
    public function savePluginConfig($slug, $config) {
        $db = Database::getInstance();
        
        // 从plugin.json获取默认配置结构，用于处理复杂配置格式
        $pluginInfoFile = PLUGINS_PATH . '/' . $slug . '/plugin.json';
        $defaultConfig = array();
        if (file_exists($pluginInfoFile)) {
            $pluginInfo = json_decode(file_get_contents($pluginInfoFile), true);
            if (isset($pluginInfo['default_settings'])) {
                $defaultConfig = $pluginInfo['default_settings'];
            }
        }
        
        // 处理配置，只保存值而不是完整结构
        $saveConfig = array();
        
        // 首先处理默认配置中的boolean类型字段
        // 由于HTML checkbox取消勾选时不会提交，需要特殊处理
        foreach ($defaultConfig as $key => $defaultValue) {
            if (is_array($defaultValue) && isset($defaultValue['type']) && $defaultValue['type'] === 'boolean') {
                // 如果表单没有提交这个字段（checkbox取消勾选），设置为false
                if (!isset($config[$key])) {
                    $saveConfig[$key] = false;
                } else {
                    // checkbox提交的值是 '1' 或 'on'
                    $saveConfig[$key] = ($config[$key] === '1' || $config[$key] === 'on' || $config[$key] === true);
                }
            }
        }
        
        // 然后处理所有提交的配置
        foreach ($config as $key => $value) {
            // 如果已经处理过（boolean类型），跳过
            if (isset($saveConfig[$key])) {
                continue;
            }
            
            if (isset($defaultConfig[$key]) && is_array($defaultConfig[$key]) && isset($defaultConfig[$key]['type'])) {
                // 如果是复杂配置格式，只保存值
                $saveConfig[$key] = $value;
            } else {
                // 如果是简单配置格式，直接保存
                $saveConfig[$key] = $value;
            }
        }
        
        $settingsJson = json_encode($saveConfig);
        
        return $db->update('plugin', array('settings' => $settingsJson), array('slug' => $slug));
    }
    
    /**
     * 获取插件配置表单
     * @param string $slug 插件标识
     * @return string 配置表单HTML
     */
    public function getPluginConfigForm($slug) {
        $config = $this->getPluginConfig($slug);
        $pluginInfoFile = PLUGINS_PATH . '/' . $slug . '/plugin.json';
        $pluginInfo = json_decode(file_get_contents($pluginInfoFile), true);
        
        // 检查是否有标签页配置
        $configTabs = isset($pluginInfo['config_tabs']) ? $pluginInfo['config_tabs'] : array();
        $hasTabs = !empty($configTabs);
        
        // 按标签页分组配置项
        $tabbedConfig = array();
        if ($hasTabs) {
            foreach ($configTabs as $tabKey => $tabInfo) {
                $tabbedConfig[$tabKey] = array();
            }
        }
        $defaultConfig = array();
        
        foreach ($config as $key => $value) {
            // 处理复杂配置格式
            $fieldType = 'text';
            $fieldLabel = ucwords(str_replace('_', ' ', $key));
            $fieldValue = $value;
            $fieldOptions = array();
            $fieldAttributes = array();
            $fieldHelp = '';
            $fieldDependency = array();
            $fieldTab = '';
            
            // 检查是否是复杂配置格式
            if (is_array($value) && isset($value['type'])) {
                $fieldType = $value['type'];
                $fieldLabel = isset($value['label']) ? $value['label'] : $fieldLabel;
                $fieldValue = isset($value['value']) ? $value['value'] : '';
                $fieldOptions = isset($value['options']) ? $value['options'] : array();
                $fieldAttributes = isset($value['attributes']) ? $value['attributes'] : array();
                $fieldHelp = isset($value['help']) ? $value['help'] : '';
                $fieldDependency = isset($value['dependency']) ? $value['dependency'] : array();
                $fieldTab = isset($value['tab']) ? $value['tab'] : '';
            }
            
            // 检查依赖关系
            $showField = true;
            if (!empty($fieldDependency) && is_array($fieldDependency)) {
                foreach ($fieldDependency as $depKey => $depValue) {
                    // 获取依赖字段的值
                    $depFieldValue = '';
                    if (isset($config[$depKey])) {
                        if (is_array($config[$depKey]) && isset($config[$depKey]['value'])) {
                            $depFieldValue = $config[$depKey]['value'];
                        } else {
                            $depFieldValue = $config[$depKey];
                        }
                    }
                    
                    // 检查依赖条件
                    if (is_array($depValue)) {
                        // 依赖值是数组，检查当前值是否在数组中
                        if (!in_array($depFieldValue, $depValue)) {
                            $showField = false;
                            break;
                        }
                    } else {
                        // 依赖值是单个值，检查当前值是否等于依赖值
                        if ($depFieldValue != $depValue) {
                            $showField = false;
                            break;
                        }
                    }
                }
            }
            
            // 如果不满足依赖条件，跳过该字段
            if (!$showField) {
                continue;
            }
            
            // 将配置项添加到对应的标签页
            if ($hasTabs && !empty($fieldTab) && isset($tabbedConfig[$fieldTab])) {
                $tabbedConfig[$fieldTab][$key] = $value;
            } else {
                $defaultConfig[$key] = $value;
            }
        }
        
        $form = '<form action="admin.php?action=plugin&sub=saveConfig" method="post" enctype="multipart/form-data">';
        $form .= '<input type="hidden" name="slug" value="' . $slug . '">';
        $form .= '<div class="plugin-config-form">';
        $form .= '<style>';
        // 分区标题/标签页样式：颜色一律引用主题 Token（common.css 定义），
        // 暗色主题下随 body.dark-theme 自动切换，避免硬编码浅色在暗色下刺眼
        $form .= '.config-section-title {';
        $form .= '    margin-top: 20px;';
        $form .= '    margin-bottom: 15px;';
        $form .= '    padding-bottom: 8px;';
        $form .= '    border-bottom: 2px solid var(--border-split);';
        $form .= '    color: var(--text-primary);';
        $form .= '    font-size: 16px;';
        $form .= '    font-weight: 600;';
        $form .= '}';
        $form .= '.nav-tabs {';
        $form .= '    margin-bottom: 20px;';
        $form .= '    display: flex;';
        $form .= '    gap: 5px;';
        $form .= '}';
        $form .= '.nav-item {';
        $form .= '    list-style: none;';
        $form .= '}';
        $form .= '.nav-link {';
        $form .= '    display: block;';
        $form .= '    padding: 12px 20px;';
        $form .= '    color: var(--text-secondary);';
        $form .= '    text-decoration: none;';
        $form .= '    border: 1px solid var(--border-color);';
        $form .= '    border-bottom: none;';
        $form .= '    border-radius: 5px;';
        $form .= '    background: var(--bg-subtle);';
        $form .= '}';
        $form .= '.nav-link:hover {';
        $form .= '    background: var(--primary);';
        $form .= '    color: white;';
        $form .= '}';
        $form .= '.nav-link.active {';
        $form .= '    background: var(--primary);';
        $form .= '    color: white;';
        $form .= '    border-color: var(--primary);';
        $form .= '}';
        $form .= '.tab-content {';
        $form .= '    display: none;';
        $form .= '}';
        $form .= '.tab-content.active {';
        $form .= '    display: block;';
        $form .= '}';
        // 导入/导出配置卡片：底色与描边引用主题 Token（原浅色渐变在暗色下呈白卡）
        $form .= '.config-import-export {';
        $form .= '    background: var(--bg-container);';
        $form .= '    border: 1px solid var(--border-color);';
        $form .= '    padding: 20px;';
        $form .= '    border-radius: 8px;';
        $form .= '    margin-top: 20px;';
        $form .= '}';
        $form .= '.config-import-export .row {';
        $form .= '    display: flex;';
        $form .= '    align-items: center;';
        $form .= '    gap: 20px;';
        $form .= '}';
        $form .= '.config-import-export .btn {';
        $form .= '    min-width: 120px;';
        $form .= '}';
        $form .= '.config-import-export .input-group {';
        $form .= '    display: flex;';
        $form .= '    gap: 10px;';
        $form .= '}';
        $form .= '.config-import-export input[type="file"] {';
        $form .= '    max-width: 300px;';
        $form .= '}';
        $form .= '.form-group {';
        $form .= '    margin-bottom: 15px;';
        $form .= '}';
        $form .= '.form-group label {';
        $form .= '    display: block;';
        $form .= '    margin-bottom: 5px;';
        $form .= '    font-weight: 600;';
        $form .= '}';
        $form .= '.form-control {';
        $form .= '    width: 100%;';
        $form .= '    padding: 8px 12px;';
        $form .= '    border: 1px solid #ddd;';
        $form .= '    border-radius: 4px;';
        $form .= '}';
        $form .= '.checkbox-group, .radio-group {';
        $form .= '    margin-top: 5px;';
        $form .= '}';
        $form .= '.checkbox-inline, .radio-inline {';
        $form .= '    margin-right: 15px;';
        $form .= '}';
        $form .= '.help-block {';
        $form .= '    margin-top: 5px;';
        $form .= '    color: var(--text-secondary);';
        $form .= '    font-size: 14px;';
        $form .= '}';
        $form .= '</style>';
        
        // 添加导入/导出功能（移到顶部）
        $form .= '<div class="form-group config-import-export">';
        $form .= '<div class="row">';
        $form .= '<div class="col-md-12">';
        $form .= '<button type="button" class="btn btn-secondary" id="export-config">导出配置</button>';
        $form .= '<span style="margin: 0 15px;"></span>';
        $form .= '<input type="file" class="form-control" id="import-config" accept=".json" style="display: inline-block; width: auto; vertical-align: middle;">';
        $form .= '<button type="button" class="btn btn-secondary" id="import-config-btn">导入配置</button>';
        $form .= '</div>';
        $form .= '</div>';
        $form .= '</div>';
        
        // 生成标签页导航
        if ($hasTabs) {
            $form .= '<div class="nav-tabs" role="tablist">';
            $firstTab = true;
            foreach ($configTabs as $tabKey => $tabInfo) {
                $active = $firstTab ? 'active' : '';
                $form .= '<a class="nav-link ' . $active . '" data-tab="tab-' . $tabKey . '" href="javascript:void(0);">' . $tabInfo['label'] . '</a>';
                $firstTab = false;
            }
            $form .= '</div>';
        }
        
        // 生成每个标签页的内容
        if ($hasTabs) {
            $firstTab = true;
            foreach ($configTabs as $tabKey => $tabInfo) {
                $active = $firstTab ? 'active' : '';
                $form .= '<div id="tab-' . $tabKey . '" class="tab-content ' . $active . '">';
                
                // 生成该标签页的配置字段
                if (isset($tabbedConfig[$tabKey])) {
                    $this->generateConfigFields($form, $tabbedConfig[$tabKey]);
                }
                
                $form .= '</div>';
                $firstTab = false;
            }
        }
        
        // 生成默认配置字段（没有标签页的）
        if (!$hasTabs || !empty($defaultConfig)) {
            $this->generateConfigFields($form, $defaultConfig);
        }
        
        $form .= '<div class="form-group">';
        $form .= '<button type="submit" class="btn btn-primary">保存配置</button>';
        $form .= '</div>';
        $form .= '</div>';
        $form .= '</form>';
        
        // 添加JavaScript代码
        $downloadFileName = "plugin-config-{$slug}.json";
        $jsCode = '<script>
        // 标签页切换
        $(document).ready(function() {
            // 标签页切换功能
            $(".nav-link").click(function() {
                var tabId = $(this).data("tab");
                
                // 移除所有标签页的active状态
                $(".nav-link").removeClass("active");
                $(".tab-content").removeClass("active");
                
                // 添加active状态到当前标签页
                $(this).addClass("active");
                $("#" + tabId).addClass("active");
            });
            
            // 导出配置
            $("#export-config").click(function() {
                var formData = $("form").serializeArray();
                var config = {};
                
                $.each(formData, function(index, field) {
                    if (field.name.startsWith("config[") && field.name.endsWith("]")) {
                        var key = field.name.replace("config[", "").replace("]", "");
                        if (field.name.includes("[]")) {
                            key = key.replace("[]", "");
                            if (!config[key]) {
                                config[key] = [];
                            }
                            config[key].push(field.value);
                        } else {
                            config[key] = field.value;
                        }
                    }
                });
                
                var configJson = JSON.stringify(config, null, 2);
                var blob = new Blob([configJson], {type: "application/json"});
                var url = URL.createObjectURL(blob);
                var a = document.createElement("a");
                a.href = url;
                a.download = "' . $downloadFileName . '";
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            });
            
            // 导入配置
            $("#import-config-btn").click(function() {
                var fileInput = document.getElementById("import-config");
                if (fileInput.files.length === 0) {
                    alert("请选择要导入的配置文件");
                    return;
                }
                
                var file = fileInput.files[0];
                var reader = new FileReader();
                reader.onload = function(e) {
                    try {
                        var importedConfig = JSON.parse(e.target.result);
                        
                        // 填充表单
                        $.each(importedConfig, function(key, value) {
                            var fieldName = "config[" + key + "]";
                            var field = $("[name=\"" + fieldName + "\"]");
                            
                            if (field.is(":checkbox")) {
                                field.prop("checked", value === "1" || value === true);
                            } else if (field.is(":radio")) {
                                $("[name=\"" + fieldName + "\"][value=\"" + value + "\"]").prop("checked", true);
                            } else if (field.is("select")) {
                                field.val(value);
                            } else {
                                field.val(value);
                            }
                        });
                        
                        alert("配置导入成功");
                    } catch (error) {
                        alert("配置文件格式错误");
                    }
                };
                reader.readAsText(file);
            });
            
            // 客户端验证
            $("form").submit(function(e) {
                var isValid = true;
                var errorMessages = [];
                
                // 验证必填字段
                $("[required]").each(function() {
                    if (!$(this).val()) {
                        isValid = false;
                        errorMessages.push($(this).prev("label").text() + " 不能为空");
                    }
                });
                
                // 验证邮箱格式
                $("input[type=email]").each(function() {
                    var email = $(this).val();
                    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                        isValid = false;
                        errorMessages.push($(this).prev("label").text() + " 格式不正确");
                    }
                });
                
                // 验证URL格式
                $("input[type=url]").each(function() {
                    var url = $(this).val();
                    if (url && !/^https?:\/\/.+/.test(url)) {
                        isValid = false;
                        errorMessages.push($(this).prev("label").text() + " 格式不正确");
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    alert(errorMessages.join("\n"));
                }
            });
        });
        </script>';
        $form .= $jsCode;
        
        return $form;
    }
    
    /**
     * 生成配置字段
     * @param string &$form 表单HTML引用
     * @param array $config 配置项数组
     */
    private function generateConfigFields(&$form, $config) {
        foreach ($config as $key => $value) {
            // 处理特殊分隔线字段
            if ($key === '---' || (is_array($value) && isset($value['type']) && $value['type'] === 'section')) {
                $fieldLabel = '分隔线';
                $fieldDependency = array();
                if (is_array($value) && isset($value['label'])) {
                    $fieldLabel = $value['label'];
                }
                if (is_array($value) && isset($value['dependency'])) {
                    $fieldDependency = $value['dependency'];
                }
                
                // 检查依赖关系
                $showField = true;
                if (!empty($fieldDependency) && is_array($fieldDependency)) {
                    foreach ($fieldDependency as $depKey => $depValue) {
                        // 获取依赖字段的值
                        $depFieldValue = '';
                        if (isset($config[$depKey])) {
                            if (is_array($config[$depKey]) && isset($config[$depKey]['value'])) {
                                $depFieldValue = $config[$depKey]['value'];
                            } else {
                                $depFieldValue = $config[$depKey];
                            }
                        }
                        
                        // 检查依赖条件
                        if (is_array($depValue)) {
                            // 依赖值是数组，检查当前值是否在数组中
                            if (!in_array($depFieldValue, $depValue)) {
                                $showField = false;
                                break;
                            }
                        } else {
                            // 依赖值是单个值，检查当前值是否等于依赖值
                            if ($depFieldValue != $depValue) {
                                $showField = false;
                                break;
                            }
                        }
                    }
                }
                
                // 如果不满足依赖条件，跳过该字段
                if (!$showField) {
                    continue;
                }
                
                $form .= '<div class="form-group">';
                $form .= '<h4 class="config-section-title">' . $fieldLabel . '</h4>';
                $form .= '</div>';
                continue;
            }
            
            // 处理复杂配置格式
            $fieldType = 'text';
            $fieldLabel = ucwords(str_replace('_', ' ', $key));
            $fieldValue = $value;
            $fieldOptions = array();
            $fieldAttributes = array();
            $fieldHelp = '';
            $fieldDependency = array();
            
            // 检查是否是复杂配置格式
            if (is_array($value) && isset($value['type'])) {
                $fieldType = $value['type'];
                $fieldLabel = isset($value['label']) ? $value['label'] : $fieldLabel;
                $fieldValue = isset($value['value']) ? $value['value'] : '';
                $fieldOptions = isset($value['options']) ? $value['options'] : array();
                $fieldAttributes = isset($value['attributes']) ? $value['attributes'] : array();
                $fieldHelp = isset($value['help']) ? $value['help'] : '';
                $fieldDependency = isset($value['dependency']) ? $value['dependency'] : array();
            }
            
            // 检查依赖关系
            $showField = true;
            if (!empty($fieldDependency) && is_array($fieldDependency)) {
                foreach ($fieldDependency as $depKey => $depValue) {
                    // 获取依赖字段的值
                    $depFieldValue = '';
                    if (isset($config[$depKey])) {
                        if (is_array($config[$depKey]) && isset($config[$depKey]['value'])) {
                            $depFieldValue = $config[$depKey]['value'];
                        } else {
                            $depFieldValue = $config[$depKey];
                        }
                    }
                    
                    // 检查依赖条件
                    if (is_array($depValue)) {
                        // 依赖值是数组，检查当前值是否在数组中
                        if (!in_array($depFieldValue, $depValue)) {
                            $showField = false;
                            break;
                        }
                    } else {
                        // 依赖值是单个值，检查当前值是否等于依赖值
                        if ($depFieldValue != $depValue) {
                            $showField = false;
                            break;
                        }
                    }
                }
            }
            
            // 如果不满足依赖条件，跳过该字段
            if (!$showField) {
                continue;
            }
            
            $form .= '<div class="form-group">';
            
            // 输出标签
            $form .= '<label for="' . $key . '">' . $fieldLabel . '</label>';
            
            // 根据类型生成表单字段
            switch ($fieldType) {
                case 'boolean':
                    // 布尔值生成复选框
                    $checked = $fieldValue ? 'checked' : '';
                    $form .= '<input type="checkbox" id="' . $key . '" name="config[' . $key . ']" value="1" ' . $checked . '>';
                    break;
                    
                case 'string':
                case 'text':
                    // 文本类型
                    $safeValue = is_array($fieldValue) ? '' : htmlspecialchars($fieldValue);
                    $placeholder = isset($fieldAttributes['placeholder']) ? ' placeholder="' . $fieldAttributes['placeholder'] . '"' : '';
                    $required = isset($fieldAttributes['required']) ? ' required' : '';
                    if ($fieldType == 'text') {
                        // 文本域
                        $form .= '<textarea id="' . $key . '" name="config[' . $key . ']" class="form-control" rows="4"' . $placeholder . $required . '>' . $safeValue . '</textarea>';
                    } else {
                        // 文本输入框
                        $form .= '<input type="text" id="' . $key . '" name="config[' . $key . ']" value="' . $safeValue . '" class="form-control"' . $placeholder . $required . '>';
                    }
                    break;
                    
                case 'number':
                    // 数字输入框
                    $min = isset($fieldAttributes['min']) ? ' min="' . $fieldAttributes['min'] . '"' : '';
                    $max = isset($fieldAttributes['max']) ? ' max="' . $fieldAttributes['max'] . '"' : '';
                    $step = isset($fieldAttributes['step']) ? ' step="' . $fieldAttributes['step'] . '"' : '';
                    $placeholder = isset($fieldAttributes['placeholder']) ? ' placeholder="' . $fieldAttributes['placeholder'] . '"' : '';
                    $required = isset($fieldAttributes['required']) ? ' required' : '';
                    $safeValue = is_array($fieldValue) ? '' : $fieldValue;
                    $form .= '<input type="number" id="' . $key . '" name="config[' . $key . ']" value="' . $safeValue . '" class="form-control"' . $min . $max . $step . $placeholder . $required . '>';
                    break;
                    
                case 'select':
                    // 下拉选择框
                    $required = isset($fieldAttributes['required']) ? ' required' : '';
                    $form .= '<select id="' . $key . '" name="config[' . $key . ']" class="form-control"' . $required . '>';
                    foreach ($fieldOptions as $optionValue => $optionLabel) {
                        $safeValue = is_array($fieldValue) ? '' : $fieldValue;
                        $selected = $safeValue == $optionValue ? 'selected' : '';
                        $form .= '<option value="' . $optionValue . '" ' . $selected . '>' . $optionLabel . '</option>';
                    }
                    $form .= '</select>';
                    break;
                    
                case 'radio':
                    // 单选按钮组
                    $form .= '<div class="radio-group">';
                    foreach ($fieldOptions as $optionValue => $optionLabel) {
                        $safeValue = is_array($fieldValue) ? '' : $fieldValue;
                        $checked = $safeValue == $optionValue ? 'checked' : '';
                        $form .= '<label class="radio-inline">';
                        $form .= '<input type="radio" name="config[' . $key . ']" value="' . $optionValue . '" ' . $checked . '> ' . $optionLabel;
                        $form .= '</label>';
                    }
                    $form .= '</div>';
                    break;
                    
                case 'checkbox':
                    // 复选框组
                    $form .= '<div class="checkbox-group">';
                    foreach ($fieldOptions as $optionValue => $optionLabel) {
                        $checked = is_array($fieldValue) && in_array($optionValue, $fieldValue) ? 'checked' : '';
                        $form .= '<label class="checkbox-inline">';
                        $form .= '<input type="checkbox" name="config[' . $key . '][]" value="' . $optionValue . '" ' . $checked . '> ' . $optionLabel;
                        $form .= '</label>';
                    }
                    $form .= '</div>';
                    break;
                    
                case 'password':
                    // 密码输入框
                    $safeValue = is_array($fieldValue) ? '' : htmlspecialchars($fieldValue);
                    $required = isset($fieldAttributes['required']) ? ' required' : '';
                    $form .= '<input type="password" id="' . $key . '" name="config[' . $key . ']" value="' . $safeValue . '" class="form-control"' . $required . '>';
                    break;
                    
                case 'email':
                    // 邮箱输入框
                    $safeValue = is_array($fieldValue) ? '' : htmlspecialchars($fieldValue);
                    $required = isset($fieldAttributes['required']) ? ' required' : '';
                    $form .= '<input type="email" id="' . $key . '" name="config[' . $key . ']" value="' . $safeValue . '" class="form-control"' . $required . '>';
                    break;
                    
                case 'url':
                    // URL输入框
                    $safeValue = is_array($fieldValue) ? '' : htmlspecialchars($fieldValue);
                    $required = isset($fieldAttributes['required']) ? ' required' : '';
                    $form .= '<input type="url" id="' . $key . '" name="config[' . $key . ']" value="' . $safeValue . '" class="form-control"' . $required . '>';
                    break;
                    
                case 'date':
                    // 日期选择器
                    $safeValue = is_array($fieldValue) ? '' : $fieldValue;
                    $required = isset($fieldAttributes['required']) ? ' required' : '';
                    $form .= '<input type="date" id="' . $key . '" name="config[' . $key . ']" value="' . $safeValue . '" class="form-control"' . $required . '>';
                    break;
                    
                case 'time':
                    // 时间选择器
                    $safeValue = is_array($fieldValue) ? '' : $fieldValue;
                    $required = isset($fieldAttributes['required']) ? ' required' : '';
                    $form .= '<input type="time" id="' . $key . '" name="config[' . $key . ']" value="' . $safeValue . '" class="form-control"' . $required . '>';
                    break;
                    
                case 'datetime':
                    // 日期时间选择器
                    $safeValue = is_array($fieldValue) ? '' : $fieldValue;
                    $required = isset($fieldAttributes['required']) ? ' required' : '';
                    $form .= '<input type="datetime-local" id="' . $key . '" name="config[' . $key . ']" value="' . $safeValue . '" class="form-control"' . $required . '>';
                    break;
                    
                case 'color':
                    // 颜色选择器
                    $safeValue = is_array($fieldValue) ? '#ffffff' : $fieldValue;
                    $safeValue = empty($safeValue) ? '#ffffff' : $safeValue;
                    $required = isset($fieldAttributes['required']) ? ' required' : '';
                    $form .= '<input type="color" id="' . $key . '" name="config[' . $key . ']" value="' . $safeValue . '" class="form-control"' . $required . '>';
                    break;
                    
                case 'file':
                    // 文件上传
                    $accept = isset($fieldAttributes['accept']) ? ' accept="' . $fieldAttributes['accept'] . '"' : '';
                    $required = isset($fieldAttributes['required']) ? ' required' : '';
                    $form .= '<input type="file" id="' . $key . '" name="config[' . $key . ']" class="form-control"' . $accept . $required . '>';
                    $safeValue = is_array($fieldValue) ? '' : $fieldValue;
                    if (!empty($safeValue)) {
                        $form .= '<p class="help-block">当前文件: ' . $safeValue . '</p>';
                    }
                    break;
                    
                case 'rich_text':
                    // 富文本编辑器
                    $safeValue = is_array($fieldValue) ? '' : htmlspecialchars($fieldValue);
                    $required = isset($fieldAttributes['required']) ? ' required' : '';
                    $form .= '<textarea id="' . $key . '" name="config[' . $key . ']" class="form-control" rows="8"' . $required . '>' . $safeValue . '</textarea>';
                    $form .= '<script>if (typeof CKEDITOR !== "undefined") { CKEDITOR.replace("' . $key . '"); }</script>';
                    break;
                    
                case 'hidden':
                    // 隐藏字段
                    $safeValue = is_array($fieldValue) ? '' : htmlspecialchars($fieldValue);
                    $form .= '<input type="hidden" id="' . $key . '" name="config[' . $key . ']" value="' . $safeValue . '">';
                    break;
                    
                default:
                    // 默认为文本输入框
                    $safeValue = is_array($fieldValue) ? '' : htmlspecialchars($fieldValue);
                    $required = isset($fieldAttributes['required']) ? ' required' : '';
                    $form .= '<input type="text" id="' . $key . '" name="config[' . $key . ']" value="' . $safeValue . '" class="form-control"' . $required . '>';
                    break;
            }
            
            // 显示帮助文本
            if (!empty($fieldHelp)) {
                $form .= '<small class="form-text text-muted">' . $fieldHelp . '</small>';
            }
            
            $form .= '</div>';
        }
    }
    
    /**
     * 静态方法：触发钩子（兼容旧代码）
     * @param string $hook 钩子名称
     * @param mixed $args 传递给回调函数的参数
     * @return string 处理后的结果
     */
    public static function triggerHook($hook, $args = null) {
        return self::getInstance()->doHook($hook, $args);
    }

    /**
     * 后台模板挂载点：在模板的精确位置按注册顺序执行所有订阅回调并输出内容
     *
     * 与 doHook/triggerHook 共用同一套订阅数据（addHook 注册的 $hooks 表）：
     * 订阅方式完全一致（插件在初始化时对挂载点名调用 addHook），区别仅在
     * 执行时机由模板埋点处精确触发、返回内容直接 echo 到输出流，而非由
     * 控制器调用并拼接返回值。订阅面向所有已启用插件（Plugin 单例只加载
     * status=1 的插件），第三方插件与官方插件同等参与。
     *
     * 使用方式：
     *   - 插件订阅：Plugin::getInstance()->addHook('article.title.after', [$this, 'onTitleAfter']);
     *   - 模板埋点：<?php Plugin::slot('article.title.after', ['article' => $article]); ?>
     *
     * 容错约定：单个回调抛出的任何异常（Throwable）都被捕获并记录系统错误
     * 日志，随后继续执行后续订阅回调——第三方插件故障不允许拖垮后台页面渲染；
     * 无订阅者时本方法不产生任何输出（模板处零残留）。
     *
     * @param string $name    挂载点名称（约定以区域为前缀的点分命名，如 admin.head）
     * @param array  $context 透传给每个回调的上下文数据（如当前文章数组、页面标识等）
     * @return void 内容直接输出到响应流，无返回值
     */
    public static function slot(string $name, array $context = []): void {
        $instance = self::getInstance();

        // 无订阅者：直接返回（埋点处输出零字节，页面结构与未埋点时完全一致）
        if (empty($instance->hooks[$name])) {
            return;
        }

        // 外层键为优先级数值（addHook 时已 ksort 升序），内层为同优先级的回调队列，
        // 遍历顺序即「优先级 → 注册顺序」，与 doHook 的执行语义保持一致
        foreach ($instance->hooks[$name] as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                try {
                    $result = call_user_func($callback, $context);

                    // 返回值兼容规则与 doHook 一致：字符串直接输出；
                    // 数组仅拼接其中的字符串元素（兼容旧插件的批量返回）；
                    // 实现 __toString 的对象转字符串后输出；其余类型忽略
                    if (is_string($result)) {
                        echo $result;
                    } elseif (is_array($result)) {
                        echo implode('', array_filter($result, 'is_string'));
                    } elseif (is_object($result) && method_exists($result, '__toString')) {
                        echo (string)$result;
                    }
                } catch (\Throwable $e) {
                    // 异常隔离：记录错误日志后继续后续回调，不中断页面渲染。
                    // Log 类在部分入口按需加载，未加载时退回 PHP 原生错误日志，
                    // 保证本方法在任何加载阶段调用都不产生致命错误
                    $message = '挂载点回调执行异常：slot=' . $name
                        . '，错误：' . $e->getMessage()
                        . '（' . $e->getFile() . ':' . $e->getLine() . '）';
                    if (class_exists('Log')) {
                        Log::error($message, Log::CATEGORY_SYSTEM, ['slot' => $name]);
                    } else {
                        error_log('[BlogKit Plugin] ' . $message);
                    }
                }
            }
        }
    }
}

// 加载插件基础类（从 Plugin.php 拆分，减少单文件行数）
require_once __DIR__ . '/PluginBase.php';
