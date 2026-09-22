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
 * BlogKit 自动加载器 v2.0
 * 
 * 支持 PSR-4 风格路径映射，覆盖：
 *   - 核心库类 (core/lib/)
 *   - 异常类 (core/lib/exceptions/)
 *   - 模板组件 (core/template/)
 *   - 模型 (app/Models/)
 *   - 控制器 Front/Admin/Api
 *   - 服务层 (app/Services/)
 *   - 中间件 (app/Middleware/)
 *   - 插件类 (plugins/*)
 * 
 * @package BlogKit
 * @since 2.1.0
 */

/**
 * PSR-4 风格类路径映射表
 * 支持命名空间前缀 → 基础目录的映射
 */
function autoload($className) {
    // ============================================
    // 1. PSR-4 命名空间映射
    // ============================================
    static $namespaceMap = null;
    if ($namespaceMap === null) {
        $namespaceMap = [
            // 核心库类
            'BlogKit\\Core\\'     => CORE_PATH . '/lib/',
            'BlogKit\\Exception\\' => CORE_PATH . '/lib/exceptions/',
            'BlogKit\\Template\\' => CORE_PATH . '/template/',
            
            // 应用层
            'BlogKit\\Models\\'      => APP_PATH . '/Models/',
            'BlogKit\\Services\\'    => APP_PATH . '/Services/',
            'BlogKit\\Controllers\\Front\\' => APP_PATH . '/Controllers/Front/',
            'BlogKit\\Controllers\\Admin\\' => APP_PATH . '/Controllers/Admin/',
            'BlogKit\\Controllers\\Api\\'   => APP_PATH . '/Controllers/Api/',
            'BlogKit\\Middleware\\'  => APP_PATH . '/Middleware/',
        ];
    }

    // 尝试 PSR-4 命名空间匹配
    $normalizedClass = ltrim($className, '\\');
    foreach ($namespaceMap as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($normalizedClass, $prefix, $len) === 0) {
            $relativeClass = substr($normalizedClass, $len);
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }

    // ============================================
    // 2. 无命名空间的传统类名扫描（向后兼容）
    // ============================================
    static $legacyPaths = null;
    if ($legacyPaths === null) {
        $legacyPaths = [
            // 核心类
            'lib'           => CORE_PATH . '/lib/',
            'exceptions'    => CORE_PATH . '/lib/exceptions/',
            'template'      => CORE_PATH . '/template/',
            
            // 应用层
            'model'             => APP_PATH . '/Models/',
            'service'           => APP_PATH . '/Services/',
            'middleware'        => APP_PATH . '/Middleware/',
            'controller_front'  => APP_PATH . '/Controllers/Front/',
            'controller_admin'  => APP_PATH . '/Controllers/Admin/',
            'controller_admin_config'  => APP_PATH . '/Controllers/Admin/Config/',
            'controller_api'    => APP_PATH . '/Controllers/Api/',
            
            // 插件
            'plugin' => PLUGINS_PATH . '/',
        ];
    }

    foreach ($legacyPaths as $type => $basePath) {
        $file = $basePath . $className . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }

    // ============================================
    // 3. 插件智能扫描
    // ============================================
    if (isset($PLUGINS_PATH) || defined('PLUGINS_PATH')) {
        $pluginsPath = defined('PLUGINS_PATH') ? PLUGINS_PATH : $PLUGINS_PATH;
        
        // 检查插件根目录 (e.g., "HelloWorldPlugin.php" → plugins/helloworld/HelloWorldPlugin.php)
        if (preg_match('/^(.+?)(Plugin|Extension|Hook)$/i', $className, $m)) {
            $pluginDir = strtolower($m[1]);
            $pluginFile = $pluginsPath . '/' . $pluginDir . '/' . $className . '.php';
            if (file_exists($pluginFile)) {
                require_once $pluginFile;
                return;
            }
        }
    }
}

// ============================================
// 常量定义（只在未定义时定义）
// ============================================
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__, 2));
}
if (!defined('CORE_PATH')) {
    define('CORE_PATH', ROOT_PATH . '/core');
}
if (!defined('APP_PATH')) {
    define('APP_PATH', ROOT_PATH . '/app');
}
if (!defined('ADMIN_PATH')) {
    define('ADMIN_PATH', ROOT_PATH . '/admin');
}
if (!defined('PLUGINS_PATH')) {
    define('PLUGINS_PATH', ROOT_PATH . '/plugins');
}
if (!defined('STORAGE_PATH')) {
    define('STORAGE_PATH', ROOT_PATH . '/storage');
}
if (!defined('UPLOADS_PATH')) {
    define('UPLOADS_PATH', ROOT_PATH . '/uploads');
}

// ============================================
// URL 路径常量
// ============================================
if (!defined('ADMIN_URL')) {
    define('ADMIN_URL', '/admin');
}

// ============================================
// 安装锁文件路径
// ============================================
if (!defined('INSTALL_LOCK')) {
    define('INSTALL_LOCK', ROOT_PATH . '/install.lock');
}

// ============================================
// 加载全局常量（HTTP状态码, 文章状态, 用户角色等）
// ============================================
require_once CORE_PATH . '/constants.php';

// ============================================
// 注册自动加载函数
// ============================================
spl_autoload_register('autoload');
