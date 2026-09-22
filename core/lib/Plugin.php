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


class Plugin {
    private static $instance = null;
    private static $is_initializing = false;
    private static $current_instance = null;
    private $hooks = [];
    private $plugins = [];
    
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
        $form .= '.config-section-title {';
        $form .= '    margin-top: 20px;';
        $form .= '    margin-bottom: 15px;';
        $form .= '    padding-bottom: 8px;';
        $form .= '    border-bottom: 2px solid #e0e0e0;';
        $form .= '    color: #333;';
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
        $form .= '    color: #555;';
        $form .= '    text-decoration: none;';
        $form .= '    border: 1px solid #ddd;';
        $form .= '    border-bottom: none;';
        $form .= '    border-radius: 5px;';
        $form .= '    background: linear-gradient(to bottom, #f8f9fa, #e9ecef);';
        $form .= '}';
        $form .= '.nav-link:hover {';
        $form .= '    background: #007AFF;';
        $form .= '    color: white;';
        $form .= '}';
        $form .= '.nav-link.active {';
        $form .= '    background: #007AFF;';
        $form .= '    color: white;';
        $form .= '    border-color: #007AFF;';
        $form .= '}';
        $form .= '.tab-content {';
        $form .= '    display: none;';
        $form .= '}';
        $form .= '.tab-content.active {';
        $form .= '    display: block;';
        $form .= '}';
        $form .= '.config-import-export {';
        $form .= '    background: linear-gradient(to right, #f8f9fa, #e9ecef);';
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
        $form .= '    color: #666;';
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
}

// 加载插件基础类（从 Plugin.php 拆分，减少单文件行数）
require_once __DIR__ . '/PluginBase.php';
