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

/**
 * BlogKit 后台统一入口
 * 加载 bootstrap.php 处理公共初始化，然后执行后台专属逻辑。
 */

require_once __DIR__ . '/core/bootstrap.php';

// 后台专属路径常量
define('ADMIN_PATH', ROOT_PATH . '/admin');
define('PLUGINS_PATH', ROOT_PATH . '/plugins');
define('ADMIN_URL', '/admin');
define('ADMIN_ASSETS_URL', '/admin/assets');

// ========================================
// 加载后台 SVG 图标库（admin_svg_icon() / admin_svg_icons()）
// admin.php 是后台唯一统一入口，在路由分发前集中加载一次，
// 所有后台模板（工具栏按钮、列表页、编辑器等）均可直接调用；
// 与 components/sidebar_logic.html、config_nav.html 内的 require_once 幂等不冲突
// ========================================
require_once ADMIN_PATH . '/templates/components/svg_icons.php';

// ========================================
// 加载 IP 白名单类
// ========================================
require_once CORE_PATH . '/lib/IpWhitelist.php';

// ========================================
// IP 白名单验证
// ========================================
$ipWhitelistEnabled = Config::get('admin_ip_whitelist_enabled', 0);
if ($ipWhitelistEnabled) {
    require_once CORE_PATH . '/lib/Log.php';
    Log::init();

    $whitelist      = Config::get('admin_ip_whitelist', []);
    $trustedProxies = Config::get('admin_ip_trusted_proxies', []);

    IpWhitelist::setWhitelist($whitelist);
    IpWhitelist::setTrustedProxies($trustedProxies);

    $realIp    = IpWhitelist::getRealIp();
    $isAllowed = IpWhitelist::validate($realIp);

    $logContext = [
        'ip'         => $realIp,
        'url'        => $_SERVER['REQUEST_URI'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'allowed'    => $isAllowed
    ];

    if ($isAllowed) {
        Log::info('后台访问允许', 'security', $logContext);
    } else {
        Log::warning('后台访问被拦截', 'security', $logContext);
        ExceptionHandler::handle403();
    }
}

// ========================================
// 处理"记住我"自动登录
// ========================================
require_once APP_PATH . '/Controllers/Admin/LoginController.php';
LoginController::validateRememberToken();

// ========================================
// 路由处理
// ========================================
$action = isset($_GET['action']) ? $_GET['action'] : 'dashboard';

// ========================================
// 配置驱动：子路由映射表（替代硬编码 if/else）
// ========================================
// 格式：'父action' => ['sub参数值' => '目标action' | ['子sub参数值' => '目标action']]
const SUB_ROUTE_MAP = [
    'config' => [
        'email' => ['email_template' => 'email_template'],
        // v2.1.0: 向后兼容 — 旧 config&sub=migrate 重定向到新控制器（sub=info 已随系统信息页并入仪表盘而移除）
        'migrate' => 'migrate',
    ],
];

/**
 * 配置驱动解析子路由
 */
function resolveAction(string $action, array $get): string {
    if (!isset(SUB_ROUTE_MAP[$action])) {
        return $action;
    }
    $sub = $get['sub'] ?? '';
    if (!isset(SUB_ROUTE_MAP[$action][$sub])) {
        return $action;
    }
    $target = SUB_ROUTE_MAP[$action][$sub];
    if (is_array($target)) {
        // 二级嵌套：查找对应 key_sub 参数
        $sub2 = $get[$sub . '_sub'] ?? '';
        return $target[$sub2] ?? $action;
    }
    return $target;
}

$action = resolveAction($action, $_GET);

// 检查登录状态（captcha 验证码请求也允许未登录访问）
// 不仅校验 session 是否存在，还通过 RoleModel::userHasAdminAccess
// 二次确认用户当前仍拥有后台访问权限（例如角色被禁用、权限被撤销时，
// 旧 session 也会被踢出）。
$isAdminLoggedIn = false;
if (isset($_SESSION['admin']) && is_array($_SESSION['admin'])) {
    $isAdminLoggedIn = RoleModel::userHasAdminAccess($_SESSION['admin']);
    if (!$isAdminLoggedIn) {
        // 权限失效时立即清理 session，避免同一浏览器反复校验失败
        unset($_SESSION['admin']);
    }
}
if (!$isAdminLoggedIn && strpos($_SERVER['REQUEST_URI'], 'login') === false && strpos($_SERVER['REQUEST_URI'], 'forgot-password') === false && strpos($_SERVER['REQUEST_URI'], 'reset-password') === false && strpos($_SERVER['REQUEST_URI'], 'captcha') === false) {
    header('Location: admin.php?action=login');
    exit;
}

// 加载认证中间件
require_once APP_PATH . '/Middleware/AuthMiddleware.php';

// 后台路由映射表（action → controller + method）
$controllerMap = [
    // 认证
    'login'              => ['controller' => 'Login',        'method' => 'index'],
    'logout'             => ['controller' => 'Login',        'method' => 'logout'],
    'forgot-password'    => ['controller' => 'Login',        'method' => 'forgotPassword'],
    'reset-password'     => ['controller' => 'Login',        'method' => 'resetPassword'],
    'captcha'            => ['controller' => 'Captcha',      'method' => 'index'],
    // 核心功能
    'dashboard'          => ['controller' => 'Dashboard',    'method' => 'index'],
    'article'            => ['controller' => 'Article',      'method' => 'index'],
    'category'           => ['controller' => 'Category',     'method' => 'index'],
    'tag'                => ['controller' => 'Tag',          'method' => 'index'],
    'comment'            => ['controller' => 'Comment',      'method' => 'index'],
    'user'               => ['controller' => 'User',         'method' => 'index'],
    'page'               => ['controller' => 'Page',         'method' => 'index'],
    'media'              => ['controller' => 'Media',        'method' => 'index'],
    'media.delete'       => ['controller' => 'Media',        'method' => 'delete'],
    'media.batch_delete' => ['controller' => 'Media',        'method' => 'batch_delete'],
    'media.rename'       => ['controller' => 'Media',        'method' => 'rename'],
    'media.upload'       => ['controller' => 'Media',        'method' => 'upload'],
    // 系统管理
    'config'             => ['controller' => 'Config',       'method' => 'index'],
    'theme'              => ['controller' => 'Theme',        'method' => 'index'],
    'plugin'             => ['controller' => 'Plugin',       'method' => 'index'],
    // 应用市场（在线安装）：列表/设置/安装由 MarketController 内部按 op 分流
    'market'             => ['controller' => 'Market',       'method' => 'index'],
    // 系统升级：升级页/执行升级由 UpdateController 内部按 op 分流
    'update'             => ['controller' => 'Update',       'method' => 'index'],
    'backup'             => ['controller' => 'Backup',       'method' => 'index'],
    // 权限与角色
    'role'               => ['controller' => 'Role',         'method' => 'index'],
    'permission'         => ['controller' => 'Permission',   'method' => 'index'],
    // 扩展功能
    'email_template'     => ['controller' => 'EmailTemplate','method' => 'index'],
    'friendlink'         => ['controller' => 'Friendlink',   'method' => 'index'],
    // 拆分自 ConfigController（v2.1.0 架构治理）；system/info 信息页已并入仪表盘，对应路由移除
    'migrate'            => ['controller' => 'Migrate',      'method' => 'index'],
    'sitemap_generate'   => ['controller' => 'Sitemap',      'method' => 'generate'],
    // 编辑器上传（受后台登录保护）
    'upload_config'      => ['controller' => 'Upload',       'method' => 'config'],
    'uploadimage'        => ['controller' => 'Upload',       'method' => 'uploadimage'],
    'uploadfile'         => ['controller' => 'Upload',       'method' => 'uploadfile'],
    'uploadvideo'        => ['controller' => 'Upload',       'method' => 'uploadvideo'],
    'uploadscrawl'       => ['controller' => 'Upload',       'method' => 'uploadscrawl'],
    // 全局搜索
    'search'             => ['controller' => 'Search',       'method' => 'index'],
    // 新增独立控制器
    'cache'              => ['controller' => 'Cache',          'method' => 'index'],
    'security'           => ['controller' => 'Security',       'method' => 'index'],
    // 系统设置所有子菜单独立控制器
    // （§4.4/§4.5 页面归并：ip_whitelist 并入 security、register/login 并入 user_settings，
    //   对应路由行与控制器已删除，设置页左侧导航见 admin_nav.html）
    'basic'              => ['controller' => 'BasicSettings',      'method' => 'index'],
    'user_settings'      => ['controller' => 'UserSettings',       'method' => 'index'],
    'comment_settings'   => ['controller' => 'CommentSettings',    'method' => 'index'],
    'search_settings'    => ['controller' => 'SearchSettings',     'method' => 'index'],
    'seo'                => ['controller' => 'SeoSettings',        'method' => 'index'],
    'rewrite'            => ['controller' => 'RewriteSettings',    'method' => 'index'],
    'email'              => ['controller' => 'EmailSettings',      'method' => 'index'],
    'captcha_settings'   => ['controller' => 'CaptchaSettings',    'method' => 'index'],
];

// 登录状态检查（非公开页面）
if (!AuthMiddleware::isPublicAction($action)) {
    AuthMiddleware::requireLogin();

    // 权限检查
    if (!AuthMiddleware::checkPermission($action)) {
        AuthMiddleware::deny();
    }
}

// ========================================
// 插件后台页面分发（action 形如 plugin.{slug}.{page}）
// ========================================
// 命中已激活插件在 plugin.json admin_routes 中注册的页面时，
// 由插件控制器直接接管请求；未命中则继续走 404。
// 权限校验已在上方完成（启用状态 + 路由注册由 AuthMiddleware 的
// hasAdminRoute 判定；角色级访问授权由 Plugin::checkAdminAcl 校验）。
if (strpos($action, 'plugin.') === 0) {
    if (!Plugin::getInstance()->dispatchAdminRoute($action)) {
        ExceptionHandler::handle404();
    }
    exit;
}

// ========================================
// 配置驱动：控制器类名映射
// ========================================
// 格式：$controllerMap 中的 controller名 → 真实类名（不遵循常规命名规则的控制器在此映射）
const CONTROLLER_CLASS_MAP = [
    'Captcha'  => 'AdminCaptchaController',
    'Comment'  => 'AdminCommentController',
];

// ========================================
// 配置驱动：支持子方法调用的控制器白名单
// ========================================
// 配置驱动：支持子方法调用的控制器白名单
// 这些控制器允许通过 sub/method 参数调用除 index() 外的真实方法（如 save/generateSitemap）
// 不在白名单内的控制器，sub/method 值仅作为视图参数由 index() 内部处理
const SUB_METHOD_CONTROLLERS = [
    'Config', 'EmailTemplate',
    'Cache', 'Security',
    'BasicSettings', 'UserSettings', 'CommentSettings', 'SearchSettings',
    'SeoSettings', 'RewriteSettings', 'EmailSettings', 'CaptchaSettings',
    'Captcha'
];

// ========================================
// 操作级权限映射（action + 子方法 → type=2 操作权限码）
// ========================================
// 背景：bk_permission 中的操作权限行（type=2，如 article_add/comment_delete）此前
// 仅作为角色授权树中的展示数据，分发层只校验菜单级权限（action → 菜单码），
// 未校验操作级权限，导致「只授查看菜单、未授操作」的角色也能执行写操作。
// 本表在路由分发时对相关子方法追加操作级校验，原则：
//   1. 只对已存在操作权限行的动作启用拦截，未列入映射的动作维持原状（不发明新码）；
//   2. 校验要求精确持有操作码（不走父权限回退，否则「只授菜单未授操作」
//      会经父权限回退穿透，拦截失效）；角色 1 无条件放行；
//   3. 用户管理（UserController）已有控制器内检，不重复配置，避免双重提示逻辑。
// 插件控制器的子操作经 handleSubAction() 按 sub 参数转发，故按 sub 值查表；
// 卸载/删除插件目录没有专属操作码，按「能安装即可卸载/删除」并入 plugin_install。
const SUB_ACTION_PERMISSION_MAP = [
    'Article' => [
        'add'               => 'article_add',     // 新增文章
        'edit'              => 'article_edit',    // 编辑文章
        'delete'            => 'article_delete',  // 删除（入回收站）
        'permanentlyDelete' => 'article_delete',  // 回收站彻底删除
        'batchDelete'       => 'article_delete',  // 批量删除
        'batchForceDelete'  => 'article_delete',  // 批量彻底删除
    ],
    'Comment' => [
        'toggleStatus'      => 'comment_approve', // 审核状态切换（通过/拒绝）
        'batchUpdateStatus' => 'comment_approve', // 批量审核
        'delete'            => 'comment_delete',  // 删除（入回收站）
        'forceDelete'       => 'comment_delete',  // 回收站彻底删除
        'batchDelete'       => 'comment_delete',  // 批量删除
        'batchForceDelete'  => 'comment_delete',  // 批量彻底删除
    ],
    'Plugin' => [
        'install'           => 'plugin_install',  // 安装插件
        'uninstall'         => 'plugin_install',  // 卸载插件（无专属码，并入安装权限）
        'delete'            => 'plugin_install',  // 删除未安装插件目录（同上）
        'enable'            => 'plugin_enable',   // 启用插件
        'disable'           => 'plugin_disable',  // 停用插件
    ],
];

// ========================================
// 系统设置子页保存权限（config_save 一刀切拆分）
// ========================================
// 检查顺序：角色 1 放行 → 持有通用保存权限 config_save 全子页放行 →
// 持有对应子页菜单码放行 → 否则 403。子页码与 install.sql 设置子菜单种子一一对应。
// 仅拦截保存动作（save 方法）；设置页的查看访问仍走原 action → 'config' 菜单码映射。
const SETTINGS_SAVE_PERMISSION_MAP = [
    'Config'          => 'config',          // 旧版统一保存入口：按 POST sub_page 动态映射子页码
    'BasicSettings'   => 'config_basic',    // 基本设置
    'UserSettings'    => 'config_user',     // 用户设置（含注册/登录设置）
    'CommentSettings' => 'config_comment',  // 评论设置
    'SearchSettings'  => 'config_search',   // 搜索设置
    'SeoSettings'     => 'config_seo',      // SEO 设置
    'RewriteSettings' => 'config_rewrite',  // 伪静态设置
    'EmailSettings'   => 'config_email',    // 邮箱配置
    'CaptchaSettings' => 'config_captcha',  // 验证码设置
    'Security'        => 'config_security', // 安全设置（含 IP 白名单）
    'Cache'           => 'config_cache',    // 缓存设置
];

// 旧版 Config::save 统一入口的 sub_page 标识 → 子页菜单码映射
//（该入口的表单以隐藏域 sub_page 声明当前保存的设置子页）
const CONFIG_SUB_PAGE_PERMISSION_MAP = [
    'basic'    => 'config_basic',
    'user'     => 'config_user',
    'comment'  => 'config_comment',
    'search'   => 'config_search',
    'seo'      => 'config_seo',
    'rewrite'  => 'config_rewrite',
    'email'    => 'config_email',
    'security' => 'config_security',
    'captcha'  => 'config_captcha',
    'cache'    => 'config_cache',
];

/**
 * 操作级权限校验（精确匹配，不走父权限回退）
 * 角色 1 无条件放行（与 RoleModel::checkRolePermission 的兜底语义一致）；
 * 其余角色必须被显式授予对应操作权限码。
 * @param string $permissionCode 操作权限码（type=2）
 * @return bool
 */
function checkOperationPermission($permissionCode) {
    $currentUser = isset($_SESSION['admin']) ? $_SESSION['admin'] : null;
    if (!is_array($currentUser) || empty($currentUser['role'])) {
        return false;
    }
    // 系统管理员无条件放行
    if ((int)$currentUser['role'] === 1) {
        return true;
    }
    // 精确匹配操作权限码（若走父权限回退，「只授菜单未授操作」的角色会被穿透）
    return RoleModel::checkUserPermission($currentUser['id'], $permissionCode);
}

// 路由分发
if (isset($controllerMap[$action])) {
    $controllerName = $controllerMap[$action]['controller'];
    $methodName     = $controllerMap[$action]['method'];

    $controllerFile = APP_PATH . '/Controllers/Admin/' . $controllerName . 'Controller.php';
    if (file_exists($controllerFile)) {
        // 类名映射
        $controllerClass = CONTROLLER_CLASS_MAP[$controllerName] ?? ($controllerName . 'Controller');
        
        if (!class_exists($controllerClass)) {
            require $controllerFile;
        }

        // 处理子方法参数
        if ($controllerName === 'Captcha' && $action === 'captcha' && count($_GET) > 1) {
            if (isset($_GET['verify'])) {
                $methodName = 'verify';
            } else {
                $methodName = 'create';
            }
        } elseif (in_array($controllerName, SUB_METHOD_CONTROLLERS, true)) {
            // 这些控制器的 sub/method 参数优先作为子页面标识（由 index() 内部处理），
            // 仅当 sub/method 值匹配白名单中的真实方法且方法存在时，才作为方法名调用
            // 这样 basic、like、favorite、add、edit 等视图参数不会触发 403
            $actionParam = $_GET['method'] ?? $_GET['sub'] ?? null; // 优先检查 method，再检查 sub
            if ($actionParam && $actionParam !== 'index') {
                if (AuthMiddleware::isMethodAllowed($controllerName, $actionParam)
                    && method_exists($controllerClass, $actionParam)) {
                    $methodName = $actionParam;
                }
                // 否则保持默认 methodName = 'index'，由 index() 内部读取 $_GET['sub'] 或 $_GET['method']
            }
        } else {
            $actionParam = $_GET['sub'] ?? $_GET['method'] ?? null;
            if ($actionParam) {
                if ($controllerName === 'Plugin') {
                    $methodName = 'handleSubAction';
                } else {
                    // 改进：检查方法是否在白名单 AND 是否存在于控制器类中
                    // 只有同时满足这两个条件才认为是真实的方法调用
                    // 否则，sub/method 参数仅作为视图标识或过滤参数处理
                    if (AuthMiddleware::isMethodAllowed($controllerName, $actionParam)) {
                        // 先检查是否允许该方法
                        // 再确认控制器是否真的有这个方法
                        $controllerClass = CONTROLLER_CLASS_MAP[$controllerName] ?? ($controllerName . 'Controller');
                        $controllerFile = APP_PATH . '/Controllers/Admin/' . $controllerName . 'Controller.php';
                        
                        $methodExists = false;
                        if (file_exists($controllerFile)) {
                            if (!class_exists($controllerClass)) {
                                require $controllerFile;
                            }
                            $methodExists = method_exists($controllerClass, $actionParam);
                        }
                        
                        if ($methodExists) {
                            // 是真实的方法且允许调用
                            $methodName = $actionParam;
                        }
                        // 否则：保持 index()，将 sub/method 作为视图参数处理，不触发 403
                    }
                    // 如果不在方法白名单中：不触发 403，保持 index()
                }
            }
        }

        // ========================================
        // 操作级权限校验（菜单级校验通过后追加，仅拦截映射表内的动作）
        // ========================================
        // 1) 系统设置保存（save 方法）：按 config_save 拆分顺序校验
        if ($methodName === 'save' && isset(SETTINGS_SAVE_PERMISSION_MAP[$controllerName])) {
            $currentUser = isset($_SESSION['admin']) ? $_SESSION['admin'] : null;
            $saveAllowed = false;
            if (is_array($currentUser) && !empty($currentUser['role'])) {
                if ((int)$currentUser['role'] === 1) {
                    // 第一层：系统管理员无条件放行
                    $saveAllowed = true;
                } elseif (RoleModel::checkUserPermission($currentUser['id'], 'config_save')) {
                    // 第二层：持有通用保存权限 config_save，全子页放行
                    $saveAllowed = true;
                } else {
                    // 第三层：持有对应子页菜单码（旧版统一入口按 POST sub_page 动态映射）
                    $subPageCode = SETTINGS_SAVE_PERMISSION_MAP[$controllerName];
                    if ($controllerName === 'Config') {
                        $subPageKey  = isset($_POST['sub_page']) ? $_POST['sub_page'] : (isset($_GET['sub']) ? $_GET['sub'] : '');
                        $subPageCode = isset(CONFIG_SUB_PAGE_PERMISSION_MAP[$subPageKey]) ? CONFIG_SUB_PAGE_PERMISSION_MAP[$subPageKey] : null;
                    }
                    if ($subPageCode !== null && RoleModel::checkUserPermission($currentUser['id'], $subPageCode)) {
                        $saveAllowed = true;
                    }
                }
            }
            if (!$saveAllowed) {
                AuthMiddleware::deny();
            }
        } elseif ($controllerName === 'Plugin') {
            // 2) 插件管理子操作：实际动作名取 sub 参数（经 handleSubAction 转发）
            $pluginSubAction = isset($_GET['sub']) ? $_GET['sub'] : '';
            if ($pluginSubAction !== '' && isset(SUB_ACTION_PERMISSION_MAP['Plugin'][$pluginSubAction])) {
                if (!checkOperationPermission(SUB_ACTION_PERMISSION_MAP['Plugin'][$pluginSubAction])) {
                    AuthMiddleware::deny();
                }
            }
        } elseif (isset(SUB_ACTION_PERMISSION_MAP[$controllerName][$methodName])) {
            // 3) 普通控制器子方法：按最终解析出的方法名精确校验操作权限码
            if (!checkOperationPermission(SUB_ACTION_PERMISSION_MAP[$controllerName][$methodName])) {
                AuthMiddleware::deny();
            }
        }

        $controller = new $controllerClass();

        if (method_exists($controller, $methodName)) {
            $controller->$methodName();
        } else {
            ExceptionHandler::handle404();
        }
    } else {
        ExceptionHandler::handle404();
    }
} else {
    ExceptionHandler::handle404();
}
