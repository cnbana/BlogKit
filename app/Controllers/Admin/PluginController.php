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


class PluginController {
    public function index() {
        // 扫描新插件
        Plugin::getInstance()->scanPlugins();
        
        // 获取数据库中的插件列表（只包含已安装的，排除已卸载的）
        $db = Database::getInstance();
        $installed_plugins = $db->fetchAll("SELECT * FROM {$db->table('plugin')} WHERE status != -1 ORDER BY name ASC");
        
        // 分类插件并检查是否有配置项
        $enabled_plugins = array();
        $disabled_plugins = array();
        
        foreach ($installed_plugins as $plugin) {
            // 检查插件是否有配置项
            $plugin_json_path = PLUGINS_PATH . '/' . $plugin['slug'] . '/plugin.json';
            $has_settings = false;
            
            if (file_exists($plugin_json_path)) {
                $plugin_json = json_decode(file_get_contents($plugin_json_path), true);
                if (isset($plugin_json['default_settings']) && !empty($plugin_json['default_settings'])) {
                    $has_settings = true;
                }
                // 添加作者信息
                if (isset($plugin_json['author'])) {
                    $plugin['author'] = $plugin_json['author'];
                }
            }
            
            $plugin['has_settings'] = $has_settings;
            
            if ($plugin['status'] == 1) {
                $enabled_plugins[] = $plugin;
            } elseif ($plugin['status'] == 0) {
                $disabled_plugins[] = $plugin;
            }
        }
        
        // 获取服务器目录中的插件
        $server_plugins = array();
        if (is_dir(PLUGINS_PATH)) {
            $plugin_dirs = glob(PLUGINS_PATH . '/*', GLOB_ONLYDIR);
            
            foreach ($plugin_dirs as $plugin_dir) {
                $slug = basename($plugin_dir);
                
                // 检查是否已安装（未被卸载）
                $installed = false;
                $existing_plugin = $db->fetch("SELECT * FROM {$db->table('plugin')} WHERE slug = ?", array($slug));
                
                // 如果插件不在数据库中，或者状态为-1（已卸载），则视为未安装
                if (!$existing_plugin || $existing_plugin['status'] == -1) {
                    // 读取plugin.json
                    $json_path = $plugin_dir . '/plugin.json';
                    if (file_exists($json_path)) {
                        $json_content = file_get_contents($json_path);
                        $plugin_info = json_decode($json_content, true);
                        if ($plugin_info) {
                            $plugin_info['slug'] = $slug;
                            $server_plugins[] = $plugin_info;
                        }
                    }
                }
            }
        }
        
        // 显示插件列表
        include ADMIN_PATH . '/templates/plugin.html';
    }
    
    /**
     * 处理子操作
     */
    public function handleSubAction() {
        // 触发插件控制器钩子，让插件可以处理自己的请求
        Plugin::getInstance()->doHook('plugin_admin_controller');
        
        // 如果插件没有处理请求，则继续默认处理
        if (isset($_GET['sub'])) {
            $subAction = $_GET['sub'];
            if (method_exists($this, $subAction)) {
                $this->$subAction();
            } else {
                // 子操作不存在
                $this->showMessage('操作不存在', 'admin.php?action=plugin');
            }
        } else {
            $this->index();
        }
    }
    
    public function enable() {
        $slug = $_GET['slug'];
        if (!$slug) {
            // 参数错误
            $this->showMessage('参数错误', 'admin.php?action=plugin');
            return;
        }
        
        // 获取插件信息
        $db = Database::getInstance();
        $plugin = $db->fetch("SELECT * FROM {$db->table('plugin')} WHERE slug = ?", array($slug));
        
        // 记录插件启用日志
        Log::init();
        Log::info('启用插件', 'plugin', ['slug' => $slug, 'name' => $plugin['name'] ?? $slug]);
        
        if ($plugin) {
            // 加载插件并调用启用方法
            $pluginFile = PLUGINS_PATH . '/' . $plugin['slug'] . '/plugin.php';
            if (file_exists($pluginFile)) {
                // 将slug转换为PascalCase格式
                $parts = explode('_', $plugin['slug']);
                $pascalCaseSlug = implode('', array_map('ucfirst', $parts));
                $className = $pascalCaseSlug . 'Plugin';
                
                // 兼容没有Plugin后缀的类名
                if (!class_exists($className)) {
                    $className = $pascalCaseSlug;
                }
                
                if (class_exists($className)) {
                    $pluginInstance = new $className($plugin);
                    if (method_exists($pluginInstance, 'activate')) {
                        $pluginInstance->activate();
                    }
                }
            }
            
            // 更新数据库状态
            $result = $db->update('plugin', array('status' => 1), array('slug' => $slug));
            
            if ($result) {
                $this->showMessage('插件启用成功', 'admin.php?action=plugin');
            } else {
                $this->showMessage('插件启用失败', 'admin.php?action=plugin');
            }
        }
    }
    
    public function disable() {
        $slug = $_GET['slug'];
        if (!$slug) {
            // 参数错误
            $this->showMessage('参数错误', 'admin.php?action=plugin');
            return;
        }
        
        // 获取插件信息
        $db = Database::getInstance();
        $plugin = $db->fetch("SELECT * FROM {$db->table('plugin')} WHERE slug = ?", array($slug));
        
        // 记录插件禁用日志
        Log::init();
        Log::info('禁用插件', 'plugin', ['slug' => $slug, 'name' => $plugin['name'] ?? $slug]);
        
        if ($plugin) {
            // 加载插件并调用禁用方法
            $pluginFile = PLUGINS_PATH . '/' . $plugin['slug'] . '/plugin.php';
            if (file_exists($pluginFile)) {
                // 将slug转换为PascalCase格式
                $parts = explode('_', $plugin['slug']);
                $pascalCaseSlug = implode('', array_map('ucfirst', $parts));
                $className = $pascalCaseSlug . 'Plugin';
                
                // 兼容没有Plugin后缀的类名
                if (!class_exists($className)) {
                    $className = $pascalCaseSlug;
                }
                
                if (class_exists($className)) {
                    $pluginInstance = new $className($plugin);
                    if (method_exists($pluginInstance, 'deactivate')) {
                        $pluginInstance->deactivate();
                    }
                }
            }
            
            // 更新数据库状态
            $result = $db->update('plugin', array('status' => 0), array('slug' => $slug));
            
            if ($result) {
                $this->showMessage('插件禁用成功', 'admin.php?action=plugin');
            } else {
                $this->showMessage('插件禁用失败', 'admin.php?action=plugin');
            }
        }
    }
    
    /**
     * 安装插件
     */
    public function install() {
        $slug = $_GET['slug'];
        if (!$slug) {
            // 参数错误
            $this->showMessage('参数错误', 'admin.php?action=plugin');
            return;
        }
        
        // 记录插件安装日志
        Log::init();
        Log::info('安装插件', 'plugin', ['slug' => $slug]);
        
        // 调用插件系统的安装方法
        $result = Plugin::getInstance()->installPlugin($slug);
        
        if ($result) {
            $this->showMessage('插件安装成功', 'admin.php?action=plugin');
        } else {
            $this->showMessage('插件安装失败', 'admin.php?action=plugin');
        }
    }
    
    /**
     * 卸载插件
     */
    public function uninstall() {
        $slug = $_GET['slug'];
        if (!$slug) {
            // 参数错误
            $this->showMessage('参数错误', 'admin.php?action=plugin');
            return;
        }
        
        // 记录插件卸载日志
        Log::init();
        Log::info('卸载插件', 'plugin', ['slug' => $slug]);
        
        // 检查是否需要保留数据
        $keepData = false;
        
        // 1. 优先检查URL参数
        if (isset($_GET['keepdata'])) {
            $keepData = $_GET['keepdata'] === '1';
        } elseif (isset($_GET['keep_data'])) {
            $keepData = $_GET['keep_data'] === '1';
        } else {
            // 2. 如果没有URL参数，则从插件设置中获取
            $db = Database::getInstance();
            $plugin = $db->fetch("SELECT * FROM {$db->table('plugin')} WHERE slug = ?", array($slug));
            
            if ($plugin && !empty($plugin['settings'])) {
                $settings = json_decode($plugin['settings'], true);
                if (isset($settings['keep_data_on_uninstall'])) {
                    $keepData = $settings['keep_data_on_uninstall'];
                }
            }
        }
        
        // 调用插件系统的卸载方法
        $result = Plugin::getInstance()->uninstallPlugin($slug, $keepData);
        
        if ($result) {
            $this->showMessage('插件卸载成功', 'admin.php?action=plugin');
        } else {
            $this->showMessage('插件卸载失败', 'admin.php?action=plugin');
        }
    }
    
    /**
     * 插件配置
     */
    public function config() {
        $slug = $_GET['slug'];
        if (!$slug) {
            // 参数错误
            $this->showMessage('参数错误', 'admin.php?action=plugin');
            return;
        }
        
        // 获取插件信息
        $db = Database::getInstance();
        $plugin = $db->fetch("SELECT * FROM {$db->table('plugin')} WHERE slug = ?", array($slug));
        if (!$plugin) {
            $this->showMessage('插件不存在', 'admin.php?action=plugin');
            return;
        }
        
        // 检查插件是否有配置项
        $pluginInfoFile = PLUGINS_PATH . '/' . $slug . '/plugin.json';
        $has_settings = false;
        if (file_exists($pluginInfoFile)) {
            $pluginInfo = json_decode(file_get_contents($pluginInfoFile), true);
            if (isset($pluginInfo['default_settings']) && !empty($pluginInfo['default_settings'])) {
                $has_settings = true;
            }
        }
        
        if (!$has_settings) {
            // 插件没有配置项，跳转到插件详情页
            $this->showMessage('该插件没有配置项', 'admin.php?action=plugin&sub=detail&slug=' . $slug);
            return;
        }
        
        // 获取插件配置表单
        $pluginSystem = Plugin::getInstance();
        $configForm = $pluginSystem->getPluginConfigForm($slug);
        
        // 显示配置页面
        include ADMIN_PATH . '/templates/plugin_config.html';
    }
    
    /**
     * 保存插件配置
     */
    public function saveConfig() {
        $slug = $_POST['slug'];
        $config = isset($_POST['config']) ? $_POST['config'] : array();
        
        if (!$slug) {
            // 参数错误
            $this->showMessage('参数错误', 'admin.php?action=plugin');
            return;
        }
        
        // 处理布尔值
        foreach ($config as $key => &$value) {
            if ($value === '1') {
                $value = true;
            } elseif ($value === '0') {
                $value = false;
            }
        }
        
        // 保存配置
        $pluginSystem = Plugin::getInstance();
        $result = $pluginSystem->savePluginConfig($slug, $config);
        
        if ($result) {
            $this->showMessage('配置保存成功', 'admin.php?action=plugin&sub=config&slug=' . $slug);
        } else {
            $this->showMessage('配置保存失败', 'admin.php?action=plugin&sub=config&slug=' . $slug);
        }
    }
    
    /**
     * 插件详情
     */
    public function detail() {
        $slug = $_GET['slug'];
        if (!$slug) {
            // 参数错误
            $this->showMessage('参数错误', 'admin.php?action=plugin');
            return;
        }
        
        // 获取插件信息
        $db = Database::getInstance();
        $plugin = $db->fetch("SELECT * FROM {$db->table('plugin')} WHERE slug = ?", array($slug));
        if (!$plugin) {
            $this->showMessage('插件不存在', 'admin.php?action=plugin');
            return;
        }
        
        // 获取插件详细信息
        $pluginInfoFile = PLUGINS_PATH . '/' . $slug . '/plugin.json';
        $pluginInfo = array();
        $has_settings = false;
        if (file_exists($pluginInfoFile)) {
            $pluginInfo = json_decode(file_get_contents($pluginInfoFile), true);
            if (isset($pluginInfo['default_settings']) && !empty($pluginInfo['default_settings'])) {
                $has_settings = true;
            }
        }
        
        // 添加has_settings到plugin数组
        $plugin['has_settings'] = $has_settings;
        
        // 显示插件详情页
        include ADMIN_PATH . '/templates/plugin_detail.html';
    }
    
    // 出于安全考虑，已移除上传插件功能：避免通过 ZIP 上传任意 PHP 文件造成代码执行风险
    // 插件部署方式：直接将插件目录放置到 /plugins 目录下，系统会自动扫描识别

    private function showMessage($message, $redirect = '') {
        echo "<script>alert('{$message}');";
        if ($redirect) {
            echo "window.location.href = '{$redirect}';";
        }
        echo "</script>";
        exit;
    }
}