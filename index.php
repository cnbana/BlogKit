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
 * BlogKit 前台统一入口
 * 加载 bootstrap.php 处理公共初始化，然后执行前台专属逻辑。
 */

require_once __DIR__ . '/core/bootstrap.php';

// 前台专属路径常量
define('THEMES_PATH', ROOT_PATH . '/themes');
define('PLUGINS_PATH', ROOT_PATH . '/plugins');
// 上传路径统一使用根目录下的 uploads
define('UPLOADS_PATH', ROOT_PATH . '/uploads');

// ========================================
// 资产处理器：直接 serve 主题和管理后台的 CSS/JS/图片
// 资源随主题/模板目录存放，无需"发布"到 static/ 目录
// ========================================
$requestUri = $_SERVER['REQUEST_URI'];
$requestUri = strtok($requestUri, '?'); // 去查询字符串

// 主题资产：/themes/{theme}/assets/{path} → themes/{theme}/assets/{path}
if (preg_match('#^/themes/([^/]+)/assets/(.+)$#', $requestUri, $m)) {
    $theme = $m[1];
    $path  = $m[2];
    $file  = ROOT_PATH . '/themes/' . $theme . '/assets/' . $path;
    serveStaticFile($file);
}
// 兼容旧格式（无 /assets/ 前缀）：/themes/{theme}/{path} → themes/{theme}/assets/{path}
elseif (preg_match('#^/themes/([^/]+)/(.+)$#', $requestUri, $m)) {
    $theme = $m[1];
    $path  = $m[2];
    $file  = ROOT_PATH . '/themes/' . $theme . '/assets/' . $path;
    serveStaticFile($file);
}

// 后台资产：/admin/assets/{path} → admin/assets/
if (preg_match('#^/admin/assets/(.+)$#', $requestUri, $m)) {
    $file = ROOT_PATH . '/admin/assets/' . $m[1];
    serveStaticFile($file);
}

// ========================================
// 处理"记住我"自动登录
// ========================================
require_once APP_PATH . '/Controllers/Front/AuthController.php';
AuthController::validateRememberToken();

// ========================================
// 路由分发
// ========================================
$router = new Router();
$router->dispatch();

// ========================================
// 静态文件服务函数
// ========================================
function serveStaticFile($file) {
    if (!file_exists($file)) {
        ExceptionHandler::handle404();
    }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $mimeTypes = [
        'css'   => 'text/css',
        'js'    => 'application/javascript',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'svg'   => 'image/svg+xml',
        'ico'   => 'image/x-icon',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf',
        'eot'   => 'application/vnd.ms-fontobject',
        'json'  => 'application/json',
        'map'   => 'application/json',
    ];

    $mime = isset($mimeTypes[$ext]) ? $mimeTypes[$ext] : 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Cache-Control: public, max-age=86400');
    header('Content-Length: ' . filesize($file));

    $lastModified = filemtime($file);
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');

    // 304 支持
    if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) &&
        strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $lastModified) {
        header('HTTP/1.1 304 Not Modified');
        exit;
    }

    readfile($file);
    exit;
}
