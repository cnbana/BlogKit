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
        'api'   => ['keys' => 'api_key'],
        'email' => ['email_template' => 'email_template'],
        // v2.1.0: 向后兼容 — 旧 config&sub=migrate 重定向到新控制器（sub=info 已随系统信息页并入仪表盘而移除）
        'migrate' => 'migrate',
    ],
    // interaction 子页由 InteractionController 内部 sub=like|favorite 处理，不在此映射
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
// 方案 B：不仅校验 session 是否存在，还通过 RoleModel::userHasAdminAccess
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
    // 系统管理
    'config'             => ['controller' => 'Config',       'method' => 'index'],
    'theme'              => ['controller' => 'Theme',        'method' => 'index'],
    'plugin'             => ['controller' => 'Plugin',       'method' => 'index'],
    // 应用市场（P3 在线安装）：列表/设置/安装由 MarketController 内部按 op 分流
    'market'             => ['controller' => 'Market',       'method' => 'index'],
    // 系统升级（U1 在线升级）：升级页/执行升级由 UpdateController 内部按 op 分流
    'update'             => ['controller' => 'Update',       'method' => 'index'],
    'hook'               => ['controller' => 'Hook',         'method' => 'index'],
    'backup'             => ['controller' => 'Backup',       'method' => 'index'],
    'log'                => ['controller' => 'Log',          'method' => 'index'],
    // 权限与角色
    'role'               => ['controller' => 'Role',         'method' => 'index'],
    'permission'         => ['controller' => 'Permission',   'method' => 'index'],
    // 扩展功能
    'email_template'     => ['controller' => 'EmailTemplate','method' => 'index'],
    'api_key'            => ['controller' => 'ApiKey',       'method' => 'index'],
    'notification'       => ['controller' => 'Notification', 'method' => 'index'],
    'friendlink'         => ['controller' => 'Friendlink',   'method' => 'index'],
    'interaction'        => ['controller' => 'Interaction',  'method' => 'index'],
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
    'debug'              => ['controller' => 'Debug',          'method' => 'index'],
    'api'                => ['controller' => 'ApiConfig',      'method' => 'index'],
    'security'           => ['controller' => 'Security',       'method' => 'index'],
    'ip_whitelist'       => ['controller' => 'IpWhitelist',    'method' => 'index'],
    // 系统设置所有子菜单独立控制器
    'basic'              => ['controller' => 'BasicSettings',      'method' => 'index'],
    'feature'            => ['controller' => 'FeatureSettings',    'method' => 'index'],
    'user_settings'      => ['controller' => 'UserSettings',       'method' => 'index'],
    'register_settings'  => ['controller' => 'RegisterSettings',   'method' => 'index'],
    'login_settings'     => ['controller' => 'LoginSettings',      'method' => 'index'],
    'comment_settings'   => ['controller' => 'CommentSettings',    'method' => 'index'],
    'search_settings'    => ['controller' => 'SearchSettings',     'method' => 'index'],
    'seo'                => ['controller' => 'SeoSettings',        'method' => 'index'],
    'rewrite'            => ['controller' => 'RewriteSettings',    'method' => 'index'],
    'email'              => ['controller' => 'EmailSettings',      'method' => 'index'],
    'captcha_settings'   => ['controller' => 'CaptchaSettings',    'method' => 'index'],
    'captcha'            => ['controller' => 'Captcha',            'method' => 'index'],
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
    'Config', 'Interaction', 'EmailTemplate', 'ApiKey', 'Notification', 
    'Cache', 'Debug', 'ApiConfig', 'Security', 'IpWhitelist',
    'BasicSettings', 'FeatureSettings', 'UserSettings', 'RegisterSettings',
    'LoginSettings', 'CommentSettings', 'SearchSettings',
    'SeoSettings', 'RewriteSettings', 'EmailSettings', 'CaptchaSettings',
    'Captcha'
];

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
