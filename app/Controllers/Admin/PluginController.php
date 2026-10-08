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


class PluginController {
    public function index() {
        // 扫描新插件
        Plugin::getInstance()->scanPlugins();

        // 获取数据库中的插件列表（只包含已安装的，排除已卸载的）
        $db = Database::getInstance();
        $installed_plugins = $db->fetchAll("SELECT * FROM {$db->table('plugin')} WHERE status != -1 ORDER BY name ASC");

        // ── 统一插件列表：已安装（启用/禁用）+ 未安装 合并为单一列表，
        // 每项带 state 状态标记（enabled/disabled/uninstalled），模板据此渲染
        // 状态徽章与对应操作列（单表 + Tab 筛选布局，替代原三分区表格） ──
        $plugins = array();

        foreach ($installed_plugins as $plugin) {
            // 检查插件是否有配置项
            $plugin_json_path = PLUGINS_PATH . '/' . $plugin['slug'] . '/plugin.json';
            $has_settings = false;

            if (file_exists($plugin_json_path)) {
                $plugin_json = json_decode(file_get_contents($plugin_json_path), true);
                if (isset($plugin_json['default_settings']) && !empty($plugin_json['default_settings'])) {
                    $has_settings = true;
                } elseif (!empty($plugin_json['admin_routes']['plugin.' . $plugin['slug'] . '.settings'])) {
                    // 插件通过 admin_routes 提供自绘设置页（如图片优化的 plugin.{slug}.settings 路由），
                    // 同样视为可配置，列表页据此展示“配置”入口
                    $has_settings = true;
                } elseif (!empty($plugin_json['settings_page'])) {
                    // manifest 声明 settings_page=true：插件提供管理配置页（页面入口
                    // 统一从插件详情/列表的“配置”按钮进入，不进侧边栏）
                    $has_settings = true;
                }
                // 添加作者信息
                if (isset($plugin_json['author'])) {
                    $plugin['author'] = $plugin_json['author'];
                }
                // 补充插件描述（plugin 表无该字段，供列表副文本展示；缺省为空串）
                if (isset($plugin_json['description'])) {
                    $plugin['description'] = $plugin_json['description'];
                }
            }
            if (!isset($plugin['description'])) {
                $plugin['description'] = '';
            }

            $plugin['has_settings'] = $has_settings;
            $plugin['state'] = ($plugin['status'] == 1) ? 'enabled' : 'disabled';
            $plugins[] = $plugin;
        }

        // 获取服务器目录中的插件（未安装：目录存在但数据库无记录或状态为 -1）
        if (is_dir(PLUGINS_PATH)) {
            $plugin_dirs = glob(PLUGINS_PATH . '/*', GLOB_ONLYDIR);

            foreach ($plugin_dirs as $plugin_dir) {
                $slug = basename($plugin_dir);

                // 检查是否已安装（未被卸载）
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
                            $plugin_info['state'] = 'uninstalled';
                            $plugin_info['has_settings'] = false;
                            $plugin_info['description'] = isset($plugin_info['description']) ? $plugin_info['description'] : '';
                            $plugins[] = $plugin_info;
                        }
                    }
                }
            }
        }

        // ── 统计条数据（始终按全量统计，不随 Tab/搜索变化） ──
        $pluginStats = [
            'total'       => count($plugins),
            'enabled'     => 0,
            'disabled'    => 0,
            'uninstalled' => 0,
        ];
        foreach ($plugins as $plugin) {
            $pluginStats[$plugin['state']]++;
        }

        // ── Tab 筛选（status：'' 全部 / enabled / disabled / uninstalled）──
        $statusFilter = isset($_GET['status']) ? (string)$_GET['status'] : '';
        if (!in_array($statusFilter, ['enabled', 'disabled', 'uninstalled'], true)) {
            $statusFilter = ''; // 非法值回退「全部」
        }

        // ── 关键词搜索（kw：名称/描述/作者/标识 模糊匹配，与主题/市场页同口径）──
        $kw = isset($_GET['kw']) ? trim((string)$_GET['kw']) : '';
        if ($statusFilter !== '' || $kw !== '') {
            $filteredPlugins = [];
            foreach ($plugins as $plugin) {
                // Tab 状态筛选
                if ($statusFilter !== '' && $plugin['state'] !== $statusFilter) {
                    continue;
                }
                // 关键词过滤
                if ($kw !== '') {
                    $haystack = (string)$plugin['name'] . ' ' . (string)$plugin['description'] . ' '
                        . (string)($plugin['author'] ?? '') . ' ' . (string)$plugin['slug'];
                    if (mb_stripos($haystack, $kw) === false) {
                        continue;
                    }
                }
                $filteredPlugins[] = $plugin;
            }
            $plugins = $filteredPlugins;
        }

        // ── 分页（服务端切页：插件列表为本地全量数据，此处按页切片）──
        // 每页条数走白名单（与 ThemeController / MarketController 等分页页面同口径），
        // 翻页/条数参数经公共分页组件回传（kw、status 随分页链接透传）
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $allowedLimits = [10, 20, 50, 100];
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        if (!in_array($limit, $allowedLimits, true)) {
            $limit = 20;
        }
        $total = count($plugins);
        $totalPages = max(1, (int)ceil($total / $limit));
        if ($page > $totalPages) {
            $page = $totalPages; // 越界页码（如筛选后条数变少）归位到末页
        }
        // 当前页切片：模板表格仅渲染本页数据
        $pagedPlugins = array_slice($plugins, ($page - 1) * $limit, $limit);

        // 分页链接透传的筛选参数（组件会跳过空值）
        $paginationFilters = [
            'kw'     => $kw,
            'status' => $statusFilter,
        ];

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
    
    /**
     * CSRF 校验：状态变更类动作（启用/禁用/安装/卸载）统一要求
     * POST 表单提交并携带有效令牌（不再接受 GET 链接触发，防跨站请求伪造）
     */
    private function requireCsrfToken() {
        $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
        if (!Security::validateCsrfToken($token)) {
            $this->showMessage('安全校验失败，请刷新页面后重试', 'admin.php?action=plugin');
        }
    }

    public function enable() {
        // 取参：仅接受 POST 表单提交（GET 链接已全部改造为 POST+CSRF）
        $slug = isset($_POST['slug']) ? $_POST['slug'] : '';
        if (!$slug) {
            // 参数错误
            $this->showMessage('参数错误', 'admin.php?action=plugin');
            return;
        }
        $this->requireCsrfToken();

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

            // 侧栏菜单为运行时合并渲染（随启用状态自动出现），无需任何注册操作

            if ($result) {
                $this->showMessage('插件启用成功', 'admin.php?action=plugin');
            } else {
                $this->showMessage('插件启用失败', 'admin.php?action=plugin');
            }
        }
    }
    
    public function disable() {
        // 取参：仅接受 POST 表单提交（GET 链接已全部改造为 POST+CSRF）
        $slug = isset($_POST['slug']) ? $_POST['slug'] : '';
        if (!$slug) {
            // 参数错误
            $this->showMessage('参数错误', 'admin.php?action=plugin');
            return;
        }
        $this->requireCsrfToken();

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

            // 侧栏菜单为运行时合并渲染（停用后插件不再参与合并，菜单自动消失），
            // 无需任何菜单隐藏操作

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
        // 取参：仅接受 POST 表单提交（GET 链接已全部改造为 POST+CSRF）
        $slug = isset($_POST['slug']) ? $_POST['slug'] : '';
        if (!$slug) {
            // 参数错误
            $this->showMessage('参数错误', 'admin.php?action=plugin');
            return;
        }
        $this->requireCsrfToken();

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
        // 取参：仅接受 POST 表单提交（GET 链接已全部改造为 POST+CSRF）
        $slug = isset($_POST['slug']) ? $_POST['slug'] : '';
        if (!$slug) {
            // 参数错误
            $this->showMessage('参数错误', 'admin.php?action=plugin');
            return;
        }
        $this->requireCsrfToken();

        // 记录插件卸载日志
        Log::init();
        Log::info('卸载插件', 'plugin', ['slug' => $slug]);

        // 检查是否需要保留数据
        $keepData = false;

        // 1. 优先检查表单参数（卸载确认弹窗的选择结果）
        if (isset($_POST['keepdata'])) {
            $keepData = $_POST['keepdata'] === '1';
        } elseif (isset($_POST['keep_data'])) {
            $keepData = $_POST['keep_data'] === '1';
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
            } elseif (!empty($pluginInfo['admin_routes']['plugin.' . $slug . '.settings'])) {
                // 插件通过 admin_routes 提供自绘设置页（如图片优化）：
                // 标记为可配置，并在下方统一转投到插件自己的设置页
                $has_settings = true;
                $settingsRoute = 'plugin.' . $slug . '.settings';
            } elseif (!empty($pluginInfo['settings_page'])) {
                // manifest 声明 settings_page=true：插件提供自建管理页
                //（admin_routes 已注册 plugin.{slug}.index 等页面路由），
                // 配置入口转投到插件管理首页
                $has_settings = true;
                $settingsRoute = 'plugin.' . $slug . '.index';
            }
        }
        
        // 自绘设置页插件：配置入口直接转投到插件自己的设置页
        if ($has_settings && !empty($settingsRoute)) {
            header('Location: admin.php?action=' . $settingsRoute);
            exit;
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

        // 目录安全校验：slug 仅允许字母数字下划线连字符，防目录穿越
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $slug) || !is_dir(PLUGINS_PATH . '/' . $slug)) {
            $this->showMessage('插件不存在', 'admin.php?action=plugin');
            return;
        }

        // 获取插件信息（未安装插件无数据库记录或状态为 -1，同样支持查看详情：
        // 详情数据以 plugin.json 为准，数据库记录仅补充安装时间等运行时字段）
        $db = Database::getInstance();
        $plugin = $db->fetch("SELECT * FROM {$db->table('plugin')} WHERE slug = ?", array($slug));

        // 获取插件详细信息
        $pluginInfoFile = PLUGINS_PATH . '/' . $slug . '/plugin.json';
        $pluginInfo = array();
        $has_settings = false;
        if (file_exists($pluginInfoFile)) {
            $pluginInfo = json_decode(file_get_contents($pluginInfoFile), true);
            if (isset($pluginInfo['default_settings']) && !empty($pluginInfo['default_settings'])) {
                $has_settings = true;
            } elseif (!empty($pluginInfo['admin_routes']['plugin.' . $slug . '.settings'])) {
                // 插件通过 admin_routes 提供自绘设置页（如图片优化）：详情页展示“配置”入口，
                // 点击后由 config() 统一转投到插件自己的设置页
                $has_settings = true;
            } elseif (!empty($pluginInfo['settings_page'])) {
                // manifest 声明 settings_page=true：插件提供管理配置页，
                // 详情页据此渲染“配置插件”按钮（点击后由 config() 转投插件设置页）
                $has_settings = true;
            }
        }

        // 未安装（数据库无记录或状态 -1）：以 plugin.json 合成基础信息继续渲染，
        // 模板中的"配置/禁用/卸载"等操作均以 status==1 为前提，未安装态自动隐藏
        if (!$plugin || $plugin['status'] == -1) {
            $plugin = array(
                'id'            => 0,
                'slug'          => $slug,
                'name'          => isset($pluginInfo['name']) ? $pluginInfo['name'] : $slug,
                'version'       => isset($pluginInfo['version']) ? $pluginInfo['version'] : '',
                'author'        => isset($pluginInfo['author']) ? $pluginInfo['author'] : '',
                'description'   => isset($pluginInfo['description']) ? $pluginInfo['description'] : '',
                'status'        => -1,
                'installed_at'  => 0,
                'created_at'    => 0,
            );
        }
        
        // 添加has_settings到plugin数组
        $plugin['has_settings'] = $has_settings;

        // ── 插件页面访问授权（ACL）数据准备 ──
        // 仅「已安装 + 注册了后台路由」的插件展示授权区块：无后台页面的插件
        // 没有可控制访问的入口，展示区块只会造成困惑
        $adminRoutes = isset($pluginInfo['admin_routes']) && is_array($pluginInfo['admin_routes'])
            ? $pluginInfo['admin_routes'] : [];
        $show_acl = ($plugin['status'] != -1) && !empty($adminRoutes);
        $aclRoles = array();      // 可配置的角色列表（排除超级管理员）
        $aclRoleIds = null;       // 当前生效授权名单（null = 未配置，缺省放行）
        if ($show_acl) {
            if (!class_exists('RoleModel')) {
                require_once APP_PATH . '/Models/RoleModel.php';
            }
            // 取全部角色（数量级极小，一次取全量，不做分页）
            $rolesResult = RoleModel::getAllRoles(1, 999);
            foreach ($rolesResult['data'] as $roleItem) {
                // 超级管理员（角色 1）访问不受 ACL 约束，不在授权界面出现
                if ((int)$roleItem['id'] === 1) {
                    continue;
                }
                $aclRoles[] = $roleItem;
            }
            $aclRoleIds = Plugin::getAdminAcl($slug);
        }

        // 显示插件详情页
        include ADMIN_PATH . '/templates/plugin_detail.html';
    }

    /**
     * 保存插件页面访问授权（详情页「访问授权」区块提交入口）
     * POST + CSRF：勾选的角色写入授权名单，全部不勾 = 恢复缺省放行
     * （移除该插件的授权条目）。校验由 AuthMiddleware 在访问插件页面时执行。
     */
    public function saveAcl() {
        // 取参：仅接受 POST 表单提交
        $slug = isset($_POST['slug']) ? $_POST['slug'] : '';
        if (!$slug) {
            $this->showMessage('参数错误', 'admin.php?action=plugin');
            return;
        }
        $this->requireCsrfToken();

        // slug 白名单校验（与 detail/delete 同口径，防目录穿越类注入）
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $slug)) {
            $this->showMessage('非法的插件标识', 'admin.php?action=plugin');
            return;
        }

        // 收集勾选的角色 ID（未勾选时 $_POST['roles'] 不存在 = 恢复缺省放行）
        $roleIds = isset($_POST['roles']) && is_array($_POST['roles']) ? $_POST['roles'] : [];

        // 记录授权变更日志（名单转字符串便于检索）
        Log::init();
        Log::info('保存插件页面访问授权', 'plugin', [
            'slug'  => $slug,
            'roles' => $roleIds === [] ? '缺省放行' : implode(',', $roleIds),
        ]);

        $result = Plugin::saveAdminAcl($slug, $roleIds);
        $redirect = 'admin.php?action=plugin&sub=detail&slug=' . urlencode($slug);
        if ($result) {
            $this->showMessage('插件访问授权保存成功', $redirect);
        } else {
            $this->showMessage('插件访问授权保存失败', $redirect);
        }
    }

    /**
     * 删除未安装的插件目录
     * 仅允许删除「未安装」状态的插件（数据库无记录或状态为 -1），
     * 已安装/启用中的插件必须先卸载，防止误删运行中的插件。
     * 安全防护：POST + CSRF + slug 白名单校验 + realpath 目录前缀校验。
     */
    public function delete() {
        // 取参：仅接受 POST 表单提交
        $slug = isset($_POST['slug']) ? $_POST['slug'] : '';
        if (!$slug) {
            $this->showMessage('参数错误', 'admin.php?action=plugin');
            return;
        }
        $this->requireCsrfToken();

        // slug 白名单校验 + 目录必须真实存在于插件根目录之下（防目录穿越）
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $slug)) {
            $this->showMessage('非法的插件标识', 'admin.php?action=plugin');
            return;
        }
        $pluginDir = PLUGINS_PATH . '/' . $slug;
        $realPluginDir = realpath($pluginDir);
        if ($realPluginDir === false || strpos($realPluginDir, realpath(PLUGINS_PATH) . DIRECTORY_SEPARATOR) !== 0) {
            $this->showMessage('插件目录不存在', 'admin.php?action=plugin');
            return;
        }

        // 仅允许删除未安装的插件（数据库无记录，或状态为 -1 已卸载）
        $db = Database::getInstance();
        $plugin = $db->fetch("SELECT * FROM {$db->table('plugin')} WHERE slug = ?", array($slug));
        if ($plugin && $plugin['status'] != -1) {
            $this->showMessage('该插件已安装，请先卸载后再删除', 'admin.php?action=plugin');
            return;
        }

        // 记录删除日志
        Log::init();
        Log::info('删除插件目录', 'plugin', ['slug' => $slug, 'path' => $realPluginDir]);

        // 递归删除插件目录
        if ($this->deleteDirectory($realPluginDir)) {
            $this->showMessage('插件目录删除成功', 'admin.php?action=plugin');
        } else {
            $this->showMessage('插件目录删除失败，请检查目录权限', 'admin.php?action=plugin');
        }
    }

    /**
     * 递归删除目录及其全部内容
     * 供 delete() 调用，仅处理已通过安全校验的插件目录
     */
    private function deleteDirectory($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        $items = array_diff(scandir($dir), array('.', '..'));
        foreach ($items as $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path) && !is_link($path)) {
                // 子目录：递归删除
                $this->deleteDirectory($path);
            } else {
                // 文件或符号链接：直接删除
                @unlink($path);
            }
        }
        return @rmdir($dir);
    }

    // 出于安全考虑，已移除上传插件功能：避免通过 ZIP 上传任意 PHP 文件造成代码执行风险
    // 插件部署方式：直接将插件目录放置到 /plugins 目录下，系统会自动扫描识别

    private function showMessage($message, $redirect = '') {
        // 统一闪存 + 重定向（core/lib/functions.php）：无中间提示页，
        // 回到目标页后由 flash_render 以统一 Toast 弹出；消息类型按语义自动判断
        $success = (mb_strpos($message, '成功') !== false);
        respondFlash($success, $message, $redirect !== '' ? $redirect : null);
    }
}