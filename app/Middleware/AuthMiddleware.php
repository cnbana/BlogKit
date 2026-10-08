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
 * BlogKit 后台认证中间件
 * 
 * 统一处理后台登录状态检查和权限验证。
 * 从 admin.php 中抽取，实现认证逻辑与路由分发的分离。
 * 
 * @package BlogKit
 * @since 2.0.0
 */
class AuthMiddleware
{
    /**
     * 公开页面（无需登录即可访问）
     */
    const PUBLIC_ACTIONS = [
        'login', 'logout', 'forgot-password', 'reset-password', 'captcha'
    ];

    /**
     * 控制器方法白名单（允许通过 URL 参数调用的方法）
     */
    const ALLOWED_METHODS = [
        'Article'      => ['add', 'edit', 'delete', 'recycle', 'restore', 'permanentlyDelete', 'batchRestore', 'batchForceDelete', 'top', 'batchTop', 'batchDelete', 'batchStatus', 'batchMove', 'batchTags', 'mediaList'],
        'Comment'      => ['toggleStatus', 'delete', 'batchUpdateStatus', 'batchDelete', 'like', 'unlike', 'recycle', 'restore', 'batchRestore', 'forceDelete', 'batchForceDelete', 'edit', 'update', 'toggleTop'],
        'User'         => ['add', 'edit', 'delete', 'recycle', 'restore', 'permanentlyDelete', 'batchRestore', 'batchForceDelete', 'toggleStatus', 'detail', 'batchToggleStatus', 'batchDelete', 'batchUpdateRole', 'export', 'import', 'doImport', 'search'],
        // 分类/标签：batchDelete 为列表页批量删除入口（原先未实现的 batch 占位已由真实方法取代）
        'Category'     => ['add', 'edit', 'delete', 'updateOrder', 'batchDelete'],
        'Tag'          => ['add', 'edit', 'delete', 'batchDelete'],
        'Media'        => ['delete', 'batch_delete', 'rename', 'upload'],
        'Backup'       => ['create', 'delete', 'download', 'restore', 'saveSettings', 'doBackup'],
        'Config'       => ['save', 'testEmail', 'generateSitemap', 'clearCache', 'migrate', 'info'],
        'Login'        => ['forgotPassword', 'resetPassword'],
        // 角色/权限/页面：batchDelete 为列表页批量删除入口；页面另支持 batchStatus 批量发布/设为草稿
        'Role'         => ['add', 'edit', 'delete', 'batchDelete', 'permission', 'toggleStatus'],
        // 权限：repair 为一键修复内置权限入口（POST+CSRF，调用迁移引擎按 code 幂等补插）
        'Permission'   => ['add', 'edit', 'delete', 'batchDelete', 'toggleStatus', 'repair'],
        'Page'         => ['add', 'edit', 'delete', 'batchDelete', 'batchStatus'],
        'Friendlink'   => ['add', 'save_add', 'edit', 'save_edit', 'delete', 'batch_delete'],
        'Hook'         => ['test', 'logs', 'clear_logs', 'batchTest', 'export_logs', 'info'],
        'Theme'        => ['set_default', 'upload', 'uninstall', 'settings', 'save_settings', 'preview', 'export_settings', 'import_settings'],
        'EmailTemplate'=> ['save', 'delete', 'batchDelete', 'setDefault', 'preview'],
        'Captcha'      => ['save', 'test', 'verify', 'create'],
        'Dashboard'    => [],
        'Upload'       => ['config', 'uploadimage', 'uploadfile', 'uploadvideo', 'uploadscrawl'],
        'Migrate'      => ['index'],
        'Cache'        => ['clear', 'save'],
        'Debug'        => ['save'],
        'Security'        => ['save'],
        // §4.4/§4.5 页面归并：IpWhitelist/RegisterSettings/LoginSettings 控制器已删除，
        // 对应功能并入 Security（IP 白名单）/ UserSettings（注册+登录设置）保存链
    'BasicSettings'   => ['save'],
    'UserSettings'    => ['save'],
    'CommentSettings' => ['save'],
    'SearchSettings'  => ['save'],
    'SeoSettings'     => ['save'],
    'RewriteSettings' => ['save'],
    // test 为邮箱配置页「发送测试邮件」入口（POST+CSRF，JSON 响应）
    'EmailSettings'   => ['save', 'test'],
    'CaptchaSettings' => ['save'],
];

    /**
     * 特殊权限映射（action → permission_code）
     * 用于权限代码与实际 action 名不一致的情况
     */
    const PERMISSION_MAP = [
        // v2.1.0 拆分控制器：路由 action 与权限码不一致时映射到系统设置权限
        'migrate'             => 'config',
        // 系统升级：归入系统设置权限（零 DB 变更，与 migrate 同策略）
        'update'              => 'config',
        // system/info（系统信息页）已并入仪表盘，权限映射移除
        'sitemap_generate'    => 'config_save',
        'media.delete'        => 'media',
        'media.batch_delete'  => 'media',
        'media.rename'        => 'media',
        // 编辑器上传接口（受后台登录保护）
        'upload_config'       => 'media',
        'uploadimage'         => 'media',
        'uploadfile'          => 'media',
        'uploadvideo'         => 'media',
        'uploadscrawl'        => 'media',
        // 新增控制器权限映射
        'cache'               => 'config',
        'security'            => 'config',
        // §4.4/§4.5 页面归并：ip_whitelist/register_settings/login_settings 权限映射行已删，
        // 页面并入 security / user_settings（均映射 'config'）
    'basic'               => 'config',
    'user_settings'       => 'config',
    'comment_settings'    => 'config',
    'search_settings'     => 'config_search',
    'seo'                 => 'config',
    'rewrite'             => 'config',
    'email'               => 'config',
    'captcha_settings'    => 'config',
];

    /**
     * 判断当前 action 是否为公开页面（无需登录）
     * @param string $action
     * @return bool
     */
    public static function isPublicAction($action)
    {
        return in_array($action, self::PUBLIC_ACTIONS);
    }

    /**
     * 验证后台登录状态
     * 未登录时重定向到登录页面
     * 注意：不仅要求存在 session，还必须通过 RoleModel::userHasAdminAccess
     * 的权限校验（role=1 管理员或角色被分配了后台权限）。
     */
    public static function requireLogin()
    {
        $currentUser = $_SESSION['admin'] ?? null;
        $isAdmin = is_array($currentUser) && RoleModel::userHasAdminAccess($currentUser);

        if (!$isAdmin) {
            // 清理非管理员的旧 session，防止残留
            if (isset($_SESSION['admin'])) {
                unset($_SESSION['admin']);
            }

            $currentUri = $_SERVER['REQUEST_URI'] ?? '';
            // 仅拦截非登录相关页面
            if (
                strpos($currentUri, 'login') === false &&
                strpos($currentUri, 'forgot-password') === false &&
                strpos($currentUri, 'reset-password') === false
            ) {
                header('Location: admin.php?action=login');
                exit;
            }
        }
    }

    /**
     * 检查当前用户对指定 action 的权限
     * 
     * @param string $action 当前操作名
     * @return bool
     */
    public static function checkPermission($action)
    {
        // 公共页面和登出操作无需权限检查
        if (self::isPublicAction($action) || $action === 'logout') {
            return true;
        }

        // 特殊处理：需要登录但没有特定权限要求的操作
        $noPermCheck = ['search'];
        if (in_array($action, $noPermCheck)) {
            $currentUser = $_SESSION['admin'] ?? null;
            return is_array($currentUser) && RoleModel::userHasAdminAccess($currentUser);
        }

        $currentUser = $_SESSION['admin'] ?? null;
        if (!$currentUser || !is_array($currentUser) || !RoleModel::userHasAdminAccess($currentUser)) {
            return false;
        }

        // 插件后台页面（action 形如 plugin.{slug}.{page}）：两级访问控制。
        // 侧栏不渲染插件菜单（插件管理页统一从插件详情页进入）、
        // bk_permission 表不存插件菜单行、角色授权表亦无插件授权行，
        // 页面访问控制分两层：
        //   1. 插件启用状态：插件已启用且目标页面已在 plugin.json 的
        //      admin_routes 注册 → 通过；插件停用/卸载或路由未注册 → 拒绝
        //      （hasAdminRoute 返回 false，输出 403 页面；停用插件的分发
        //      亦会被 dispatchAdminRoute 以 404 兜底）；
        //   2. 角色授权名单（ACL）：bk_config 的 plugin_admin_acl 键按插件
        //      配置可访问角色（详情页「访问授权」维护）——未配置缺省放行
        //      （向后兼容），配置后仅名单内角色可访问，超级管理员始终放行。
        if (strpos($action, 'plugin.') === 0) {
            if (!Plugin::getInstance()->hasAdminRoute($action)) {
                return false;
            }
            // 第二层：角色授权名单校验（从 action 解析插件 slug）
            $segments = explode('.', $action);
            return Plugin::checkAdminAcl($segments[1], (int)$currentUser['role']);
        }

        // 确定权限代码
        $permissionCode = $action;
        if (isset(self::PERMISSION_MAP[$action])) {
            $permissionCode = self::PERMISSION_MAP[$action];
        }

        // 媒体操作前缀处理
        if (strpos($action, 'media.') === 0) {
            $permissionCode = 'media';
        }

        // 加载权限模型
        if (!class_exists('RoleModel')) {
            require_once APP_PATH . '/Models/RoleModel.php';
        }
        if (!class_exists('PermissionModel')) {
            require_once APP_PATH . '/Models/PermissionModel.php';
        }

        try {
            $hasPermission = RoleModel::checkUserPermission($currentUser['id'], $permissionCode);

            // 如果没有直接权限，检查父级权限
            if (!$hasPermission) {
                $permission = PermissionModel::getPermissionByCode($permissionCode);
                if ($permission && $permission['parent_id'] > 0) {
                    $parentPermission = PermissionModel::getPermissionById($permission['parent_id']);
                    if ($parentPermission) {
                        $hasPermission = RoleModel::checkUserPermission($currentUser['id'], $parentPermission['code']);
                    }
                }
            }

            return $hasPermission;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 处理权限拒绝（输出 403 页面）
     */
    public static function deny()
    {
        ExceptionHandler::handle403();
    }

    /**
     * 处理服务器错误（输出 500 页面）
     */
    public static function error()
    {
        throw new Exception('服务器内部错误');
    }

    /**
     * 验证请求方法是否在允许白名单中
     * 
     * @param string $controllerName 控制器名
     * @param string $methodName 请求的方法名
     * @return bool
     */
    public static function isMethodAllowed($controllerName, $methodName)
    {
        // Plugin 控制器有特殊处理
        if ($controllerName === 'Plugin') {
            return true;
        }

        $allowedList = self::ALLOWED_METHODS[$controllerName] ?? [];

        return in_array($methodName, $allowedList);
    }
}
