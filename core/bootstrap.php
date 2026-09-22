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
 * BlogKit 统一引导文件
 * 
 * 集中处理所有入口文件（index.php, admin.php, api.php）的公共初始化逻辑。
 * 包含：系统常量定义、安装检查、自动加载、核心类加载、系统初始化、
 * 维护/站点状态检查、Session 安全配置、CSRF 保护、安全 HTTP 响应头。
 * 
 * @package BlogKit
 * @since 1.0.0
 */

// ========================================
// 0. 开启输出缓冲
// ========================================
// 防止 headers already sent 错误，确保可以在任何时候设置 header
if (!ob_get_level()) {
    ob_start();
}

// ========================================
// 1. 系统常量定义
// ========================================
define('ROOT_PATH', dirname(__DIR__));
define('CORE_PATH', ROOT_PATH . '/core');
define('APP_PATH', ROOT_PATH . '/app');
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('LOG_PATH', STORAGE_PATH . '/logs');
define('LOG_PATH_REL', 'storage/logs/');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('INSTALL_LOCK', ROOT_PATH . '/install.lock');

// ========================================
// 2. 安装状态检查
// ========================================
if (!file_exists(INSTALL_LOCK)) {
    header('Location: /install.php');
    exit;
}

// ========================================
// 3. 自动加载
// ========================================
require CORE_PATH . '/lib/autoload.php';

// ========================================
// 4. 加载核心类库
// ========================================
require CORE_PATH . '/lib/Config.php';
require CORE_PATH . '/lib/Database.php';
require CORE_PATH . '/lib/Model.php';
require CORE_PATH . '/lib/Router.php';
// 多语言（Lang 类）已从核心移除，若需启用请通过插件方式在 plugins/ 目录中加载
require CORE_PATH . '/lib/Debug.php';
require CORE_PATH . '/lib/Security.php';
require CORE_PATH . '/lib/ExceptionHandler.php';
require CORE_PATH . '/template/Template.php';

// ========================================
// 5. 系统初始化
// ========================================
Config::init();
// Lang::init(); —— 多语言已从核心移除，若启用多语言插件请由插件在 system_init 勾子中完成
Debug::init();

// 注册统一异常处理器（必须在 Config::init() 之后，以便获取 debug 配置）
ExceptionHandler::register();

// ========================================
// 5.1 时区配置
// ========================================
$timezone = Config::get('site_timezone', 'Asia/Shanghai');
if ($timezone && in_array($timezone, timezone_identifiers_list())) {
    date_default_timezone_set($timezone);
}

// ========================================
// 6. Session 安全配置
// ========================================
$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';

// 设置 session 保存路径
$sessionPath = dirname(__DIR__) . '/storage/sessions';
if (!is_dir($sessionPath)) {
    @mkdir($sessionPath, 0755, true);
}
session_save_path($sessionPath);

// session 独立命名（BKSID）：官网（guanwangsite）与主系统同域部署时共用默认 PHPSESSID，
// 任一系统登录时的 session_regenerate_id(true) 会作废另一系统的登录会话（互踢）。
// 独立 cookie 名使两系统会话完全隔离，各自登录/退出互不影响。
session_name('BKSID');

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $isHttps,
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

// ========================================
// 7. 滑动过期机制（30分钟无操作会清除登录状态）
// ========================================
$sessionLifetime = 1800;
$lastActivity    = isset($_SESSION['last_activity']) ? $_SESSION['last_activity'] : 0;
$now             = time();

if ($lastActivity > 0 && ($now - $lastActivity) > $sessionLifetime) {
    unset($_SESSION['user']);
    unset($_SESSION['admin']);
    $_SESSION['session_expired'] = true;
}

$_SESSION['last_activity'] = $now;

// ========================================
// 7.1 维护模式检查
// ========================================
$maintenanceMode = Config::get('maintenance_mode', 0);
if ($maintenanceMode && php_sapi_name() !== 'cli') {
    $isAdmin = isset($_SESSION['admin']) && !empty($_SESSION['admin']);
    if (!$isAdmin) {
        $message = Config::get('maintenance_message', '网站正在维护中，请稍后再来。');
        header('HTTP/1.0 503 Service Unavailable');
        header('Retry-After: 1800');
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>维护中</title><style>body{font-family:Arial,sans-serif;text-align:center;padding:100px 20px;background:#f5f5f5}h1{color:#333}p{color:#666;max-width:500px;margin:20px auto}</style></head><body><h1>维护中</h1><p>' . htmlspecialchars($message) . '</p></body></html>';
        exit;
    }
}

// ========================================
// 7.2 站点运行状态检查（open/closed/private）
// ========================================
$siteStatus = Config::get('site_status', 'open');
if (php_sapi_name() !== 'cli' && $siteStatus !== 'open') {
    $isAdmin = isset($_SESSION['admin']) && !empty($_SESSION['admin']);
    $isLoggedIn = isset($_SESSION['user']) && !empty($_SESSION['user']);
    
    if ($siteStatus === 'closed') {
        if (!$isAdmin) {
            $message = Config::get('site_closed_message', '网站暂时关闭，请稍后再来。');
            header('HTTP/1.0 503 Service Unavailable');
            header('Retry-After: 3600');
            echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>网站暂停服务</title><style>body{font-family:Arial,sans-serif;text-align:center;padding:100px 20px;background:#f5f5f5}h1{color:#333}p{color:#666;max-width:500px;margin:20px auto}</style></head><body><h1>网站暂停服务</h1><p>' . htmlspecialchars($message) . '</p></body></html>';
            exit;
        }
    } elseif ($siteStatus === 'private') {
        if (!$isLoggedIn && !$isAdmin) {
            $requestUri = $_SERVER['REQUEST_URI'] ?? '';
            if (strpos($requestUri, '/login') === false && strpos($requestUri, '/register') === false) {
                header('Location: /index.php/login');
                exit;
            }
        }
    }
}

// ========================================
// 8. CSRF Token 生成
// ========================================
if (!isset($_SESSION['csrf_token'])) {
    Security::generateCsrfToken();
}

// ========================================
// 9. 安全 HTTP 响应头
// ========================================
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('X-Permitted-Cross-Domain-Policies: none');
header('X-Download-Options: noopen');
header('Referrer-Policy: strict-origin-when-cross-origin');
if ($isHttps) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
// CSP 策略
$cspNonce = bin2hex(random_bytes(16));
$_SESSION['csp_nonce'] = $cspNonce;
// CSP 策略：CMS 主题模板中存在大量内联 style 属性和脚本，使用 'unsafe-inline'
// 注意：当 CSP 含 nonce 时，浏览器会忽略 'unsafe-inline'——但 HTML 元素上的 style="..." 无法设 nonce
// 因此仅使用 'unsafe-inline'，放弃 nonce 方案以确保兼容性
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self'; frame-ancestors 'self';");
