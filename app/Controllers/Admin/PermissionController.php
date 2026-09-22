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


// 加载权限模型类
// 加载日志类

class PermissionController {
    /**
     * 权限列表页面
     */
    public function index() {
        // 获取筛选条件
        $type = $_GET['type'] ?? '';
        $status = $_GET['status'] ?? '';
        
        // 构建筛选条件
        $filters = [];
        if ($type !== '') {
            $filters['type'] = $type;
        }
        if ($status !== '') {
            $filters['status'] = $status;
        }
        
        // 获取所有权限
        $permissions = PermissionModel::getAllPermissions($filters);
        
        // 构建权限树
        $permissionTree = PermissionModel::buildPermissionTree($permissions);
        
        // 获取权限类型列表
        $permissionTypes = PermissionModel::getPermissionTypeList();
        
        // 显示权限列表页面
        include ADMIN_PATH . '/templates/permission.html';
    }
    
    /**
     * 添加权限页面
     */
    public function add() {
        // 处理表单提交
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'name' => $_POST['name'],
                'code' => $_POST['code'],
                'type' => $_POST['type'] ?? 1,
                'parent_id' => $_POST['parent_id'] ?? 0,
                'path' => $_POST['path'] ?? '',
                'icon' => $_POST['icon'] ?? '',
                'sort' => $_POST['sort'] ?? 0,
                'status' => isset($_POST['status']) ? 1 : 0
            ];
            
            if (PermissionModel::createPermission($data)) {
                Log::info('权限管理', '添加权限', '成功添加权限: ' . $data['name'] . ' (代码: ' . $data['code'] . ')', Log::CATEGORY_OPERATION);
                header('Location: admin.php?action=permission');
                exit;
            } else {
                Log::error('权限管理', '添加权限', '创建权限失败: ' . $data['name'], Log::CATEGORY_OPERATION);
                $error = '创建权限失败，请检查输入信息';
            }
        }
        
        // 获取所有权限（用于父权限选择）
        $permissions = PermissionModel::getAllPermissions(['status' => 1]);
        
        // 获取权限类型列表
        $permissionTypes = PermissionModel::getPermissionTypeList();
        
        // 显示添加权限页面
        include ADMIN_PATH . '/templates/permission_add.html';
    }
    
    /**
     * 编辑权限页面
     */
    public function edit() {
        // 获取权限ID
        $id = $_GET['id'] ?? 0;
        if (!$id) {
            header('Location: admin.php?action=permission');
            exit;
        }
        
        // 获取权限信息
        $permission = PermissionModel::getPermissionById($id);
        if (!$permission) {
            header('Location: admin.php?action=permission');
            exit;
        }
        
        // 处理表单提交
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'name' => $_POST['name'],
                'code' => $_POST['code'],
                'type' => $_POST['type'] ?? 1,
                'parent_id' => $_POST['parent_id'] ?? 0,
                'path' => $_POST['path'] ?? '',
                'icon' => $_POST['icon'] ?? '',
                'sort' => $_POST['sort'] ?? 0,
                'status' => isset($_POST['status']) ? 1 : 0
            ];
            
            if (PermissionModel::updatePermission($id, $data)) {
                Log::info('权限管理', '编辑权限', '成功编辑权限: ' . $data['name'] . ' (ID: ' . $id . ')', Log::CATEGORY_OPERATION);
                header('Location: admin.php?action=permission');
                exit;
            } else {
                Log::error('权限管理', '编辑权限', '更新权限失败: ID ' . $id, Log::CATEGORY_OPERATION);
                $error = '更新权限失败，请检查输入信息';
            }
        }
        
        // 获取所有权限（用于父权限选择）
        $permissions = PermissionModel::getAllPermissions(['status' => 1]);
        
        // 获取权限类型列表
        $permissionTypes = PermissionModel::getPermissionTypeList();
        
        // 显示编辑权限页面
        include ADMIN_PATH . '/templates/permission_edit.html';
    }
    
    /**
     * 删除权限
     */
    public function delete() {
        // 获取权限ID
        $id = $_GET['id'] ?? 0;
        if (!$id) {
            header('Location: admin.php?action=permission');
            exit;
        }
        
        // 删除权限
        $permission = PermissionModel::getPermissionById($id);
        if (PermissionModel::deletePermission($id)) {
            Log::info('权限管理', '删除权限', '成功删除权限: ' . $permission['name'] . ' (ID: ' . $id . ')', Log::CATEGORY_OPERATION);
            $_SESSION['success'] = '权限删除成功';
        } else {
            Log::error('权限管理', '删除权限', '删除权限失败: ' . $permission['name'] . ' (ID: ' . $id . ')', Log::CATEGORY_OPERATION);
            $_SESSION['error'] = '权限删除失败，可能是因为该权限有子权限';
        }
        
        // 跳转回权限列表
        header('Location: admin.php?action=permission');
        exit;
    }
    
    /**
     * 切换权限状态
     */
    public function toggleStatus() {
        // 获取权限ID和状态
        $id = $_GET['id'] ?? 0;
        $status = $_GET['status'] ?? 0;
        
        if (!$id) {
            header('Location: admin.php?action=permission');
            exit;
        }
        
        // 更新权限状态
        $permission = PermissionModel::getPermissionById($id);
        $statusText = $status == 1 ? '启用' : '禁用';
        if (PermissionModel::updatePermission($id, ['status' => $status])) {
            Log::info('权限管理', '状态变更', '成功' . $statusText . '权限: ' . $permission['name'] . ' (ID: ' . $id . ')', Log::CATEGORY_OPERATION);
            $_SESSION['success'] = '权限状态更新成功';
        } else {
            Log::error('权限管理', '状态变更', $statusText . '权限失败: ' . $permission['name'] . ' (ID: ' . $id . ')', Log::CATEGORY_OPERATION);
            $_SESSION['error'] = '权限状态更新失败';
        }
        
        // 跳转回权限列表
        header('Location: admin.php?action=permission');
        exit;
    }
}
