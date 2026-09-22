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
 * 日志管理权限初始化类
 * 用于初始化日志管理相关的权限
 */
class LogPermission {
    /**
     * 初始化日志管理权限
     */
    public static function init() {
        // 加载权限模型
        require_once APP_PATH . '/Models/PermissionModel.php';
        require_once APP_PATH . '/Models/RoleModel.php';
        
        // 检查日志管理权限是否已存在
        if (!PermissionModel::permissionExists('log_manage')) {
            // 创建日志管理权限
            $logPermission = PermissionModel::createPermission([
                'name' => '日志管理',
                'code' => 'log_manage',
                'type' => 1,
                'parent_id' => 0,
                'path' => 'admin.php?action=log',
                'icon' => 'fa-file-text-o',
                'sort' => 15,
                'status' => 1
            ]);
            
            // 给管理员角色添加日志管理权限
            if ($logPermission) {
                $db = Database::getInstance();
                $db->insert('role_permission', [
                    'role_id' => 1, // 管理员角色
                    'permission_id' => $logPermission,
                    'created_at' => time()
                ]);
            }
        }
    }
}
