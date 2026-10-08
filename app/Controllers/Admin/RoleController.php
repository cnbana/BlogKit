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


// 加载角色和权限模型类
// 加载日志类

class RoleController {
    /**
     * 角色列表页面
     */
    public function index() {
        // 获取筛选条件
        $search = $_GET['search'] ?? '';
        $status = $_GET['status'] ?? '';
        $page = $_GET['page'] ?? 1;
        // 每页条数（limit 白名单校验）
        $limit = ListQuery::pageSize();
        $allowedPageSizes = ListQuery::pageSizes();
        
        // 构建筛选条件
        $filters = [];
        if (!empty($search)) {
            $filters['search'] = $search;
        }
        if ($status !== '') {
            $filters['status'] = $status;
        }
        
        // 获取角色列表
        $result = RoleModel::getAllRoles($page, $limit, $filters);
        $roles = $result['data'];
        $total = $result['total'];
        
        // 计算分页
        $totalPages = ceil($total / $limit);
        $offset = ($page - 1) * $limit;
        
        // 获取所有角色（用于父角色选择）
        $allRoles = RoleModel::getAllRoles(1, 999999, ['status' => 1]);
        $allRoles = $allRoles['data'];
        
        // 转换状态名称
        foreach ($roles as &$role) {
            $role['status_name'] = RoleModel::getStatusName($role['status']);
            // 获取父角色名称
            $role['parent_name'] = '';
            foreach ($allRoles as $parentRole) {
                if ($parentRole['id'] == $role['parent_id']) {
                    $role['parent_name'] = $parentRole['name'];
                    break;
                }
            }
        }
        
        // 清除引用，防止后续操作中数据重复
        unset($role);
        
        // 显示角色列表页面
        include ADMIN_PATH . '/templates/role.html';
    }
    
    /**
     * 添加角色页面
     */
    public function add() {
        // 处理表单提交
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'name' => $_POST['name'],
                'description' => $_POST['description'] ?? '',
                'parent_id' => $_POST['parent_id'] ?? 0,
                'status' => isset($_POST['status']) ? 1 : 0
            ];
            
            if (RoleModel::createRole($data)) {
                Log::info('角色管理 - 添加角色：成功添加角色: ' . $data['name'], Log::CATEGORY_OPERATION);
                header('Location: admin.php?action=role');
                exit;
            } else {
                Log::error('角色管理 - 添加角色：创建角色失败: ' . $data['name'], Log::CATEGORY_OPERATION);
                $error = '创建角色失败，请检查输入信息';
            }
        }
        
        // 获取所有角色（用于父角色选择）
        $roles = RoleModel::getAllRoles(1, 999999, ['status' => 1]);
        $roles = $roles['data'];
        
        // 显示添加角色页面
        include ADMIN_PATH . '/templates/role_add.html';
    }
    
    /**
     * 编辑角色页面
     */
    public function edit() {
        // 获取角色ID
        $id = $_GET['id'] ?? 0;
        if (!$id) {
            header('Location: admin.php?action=role');
            exit;
        }
        
        // 获取角色信息
        $role = RoleModel::getRoleById($id);
        if (!$role) {
            header('Location: admin.php?action=role');
            exit;
        }
        
        // 判断是否为系统保护角色（管理员、游客、普通用户）
        $isProtectedRole = in_array($id, [1, 2, 3]);
        
        // 处理表单提交
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'name' => $_POST['name'],
                'description' => $_POST['description'] ?? ''
            ];
            
            // 非系统保护角色可以修改父角色和状态
            if (!$isProtectedRole) {
                $data['parent_id'] = $_POST['parent_id'] ?? 0;
                $data['status'] = isset($_POST['status']) ? 1 : 0;
            }
            
            if (RoleModel::updateRole($id, $data)) {
                Log::info('角色管理 - 编辑角色：成功编辑角色: ' . $data['name'] . ' (ID: ' . $id . ')', Log::CATEGORY_OPERATION);
                header('Location: admin.php?action=role');
                exit;
            } else {
                Log::error('角色管理 - 编辑角色：更新角色失败: ID ' . $id, Log::CATEGORY_OPERATION);
                $error = '更新角色失败，请检查输入信息';
            }
        }
        
        // 获取所有角色（用于父角色选择）
        $roles = RoleModel::getAllRoles(1, 999999, ['status' => 1]);
        $roles = $roles['data'];
        
        // 显示编辑角色页面
        include ADMIN_PATH . '/templates/role_edit.html';
    }
    
    /**
     * 删除角色
     */
    public function delete() {
        // 获取角色ID
        $id = $_GET['id'] ?? 0;
        if (!$id) {
            header('Location: admin.php?action=role');
            exit;
        }
        
        // 检查是否为系统保护角色
        if (in_array($id, [1, 2, 3])) {
            $role = RoleModel::getRoleById($id);
            $_SESSION['error'] = $role['name'] . '角色无法删除';
            header('Location: admin.php?action=role');
            exit;
        }
        
        // 删除角色
        $role = RoleModel::getRoleById($id);
        if (RoleModel::deleteRole($id)) {
            Log::info('角色管理 - 删除角色：成功删除角色: ' . $role['name'] . ' (ID: ' . $id . ')', Log::CATEGORY_OPERATION);
            $_SESSION['success'] = '角色删除成功';
        } else {
            Log::error('角色管理 - 删除角色：删除角色失败: ' . $role['name'] . ' (ID: ' . $id . ')', Log::CATEGORY_OPERATION);
            $_SESSION['error'] = '角色删除失败，可能是因为该角色有子角色或默认角色无法删除';
        }
        
        // 跳转回角色列表
        header('Location: admin.php?action=role');
        exit;
    }

    /**
     * 批量删除角色
     *
     * 保护逻辑与单条 delete() 完全一致，逐条检查、逐条执行（循环内不做任何跳转）：
     * 1. 内置系统保护角色（ID 1 管理员 / 2 游客 / 3 普通用户）不允许删除，逐条跳过；
     * 2. RoleModel::deleteRole() 内部还会拦截默认角色（ID 1-4）并返回 false，
     *    此类兜底失败同样计入跳过数量。
     * 全部执行完后在返回消息中分别说明成功删除与被跳过的数量。
     */
    public function batchDelete() {
        // 批量删除仅接受 POST 提交（前端 submitBatchForm 统一以 POST + csrf_token 提交）
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error'] = '非法请求';
            header('Location: admin.php?action=role');
            exit;
        }

        // 检查 CSRF 令牌，防止跨站伪造的批量删除请求
        $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
        if (!Security::validateCsrfToken($token)) {
            $_SESSION['error'] = '非法请求';
            header('Location: admin.php?action=role');
            exit;
        }

        $ids = isset($_POST['ids']) ? $_POST['ids'] : [];
        if (empty($ids)) {
            $_SESSION['error'] = '请选择要删除的角色';
            header('Location: admin.php?action=role');
            exit;
        }

        $successCount = 0; // 成功删除的角色数量
        $skipCount = 0;    // 因内置保护角色或删除失败而被跳过的数量

        foreach ($ids as $id) {
            $id = (int)$id;
            if (!$id) {
                $skipCount++;
                continue;
            }

            // 保护检查：内置系统保护角色（管理员/游客/普通用户）与单条 delete() 一致直接跳过
            if (in_array($id, [1, 2, 3])) {
                $skipCount++;
                continue;
            }

            // 模型层兜底校验（默认角色 ID 1-4 等）失败时同样计入跳过数
            if (RoleModel::deleteRole($id)) {
                $successCount++;
            } else {
                $skipCount++;
            }
        }

        // 记录批量删除日志（沿用本控制器的日志风格：模块 + 动作 + 说明 + 分类）
        if ($successCount > 0) {
            Log::info('角色管理 - 批量删除：成功批量删除角色 ' . $successCount . ' 个 (IDs: ' . implode(',', array_map('intval', $ids)) . ')', Log::CATEGORY_OPERATION);
        }

        // 汇总消息（沿用本控制器 delete() 的 $_SESSION['success'] / $_SESSION['error'] 机制）
        if ($successCount > 0 && $skipCount > 0) {
            $_SESSION['success'] = '成功删除 ' . $successCount . ' 个角色，跳过 ' . $skipCount . ' 个（内置保护角色或删除失败）';
        } elseif ($successCount > 0) {
            $_SESSION['success'] = '角色删除成功（共 ' . $successCount . ' 个）';
        } else {
            $_SESSION['error'] = '没有角色被删除（所选角色为内置保护角色）';
        }

        // 跳转回角色列表
        header('Location: admin.php?action=role');
        exit;
    }

    /**
     * 角色权限分配页面
     */
    public function permission() {
        // 获取角色ID
        $id = $_GET['id'] ?? 0;
        if (!$id) {
            header('Location: admin.php?action=role');
            exit;
        }
        
        // 获取角色信息
        $role = RoleModel::getRoleById($id);
        if (!$role) {
            header('Location: admin.php?action=role');
            exit;
        }
        
        // 判断是否为系统保护角色
        $isProtectedRole = in_array($id, [1, 2, 3]);
        
        // 处理权限分配
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isProtectedRole) {
            $permissionIds = $_POST['permissions'] ?? [];
            
            if (RoleModel::assignPermissions($id, $permissionIds)) {
                $role = RoleModel::getRoleById($id);
                Log::info('角色管理 - 权限分配：成功为角色分配权限: ' . $role['name'] . ' (ID: ' . $id . ')', Log::CATEGORY_OPERATION);
                $_SESSION['success'] = '权限分配成功';
                header('Location: admin.php?action=role');
                exit;
            } else {
                Log::error('角色管理 - 权限分配：权限分配失败: 角色ID ' . $id, Log::CATEGORY_OPERATION);
                $error = '权限分配失败';
            }
        }
        
        // 获取权限树（getPermissionTree 在核心权限树之上运行时合并插件菜单
        // 节点，保证非超管角色的授权界面能看到插件菜单项）
        $permissionTree = PermissionModel::getPermissionTree(['status' => 1]);
        
        // 获取角色已分配的权限ID
        $assignedPermissionIds = RoleModel::getRolePermissionIds($id);
        
        // 显示角色权限分配页面
        include ADMIN_PATH . '/templates/role_permission.html';
    }
    
    /**
     * 切换角色状态
     */
    public function toggleStatus() {
        // 获取角色ID和状态
        $id = $_GET['id'] ?? 0;
        $status = $_GET['status'] ?? 0;
        
        if (!$id) {
            header('Location: admin.php?action=role');
            exit;
        }
        
        // 检查是否为系统保护角色
        if (in_array($id, [1, 2, 3])) {
            $role = RoleModel::getRoleById($id);
            $_SESSION['error'] = $role['name'] . '角色状态无法更改';
            header('Location: admin.php?action=role');
            exit;
        }
        
        // 更新角色状态
        $role = RoleModel::getRoleById($id);
        $statusText = $status == 1 ? '启用' : '禁用';
        if (RoleModel::updateRole($id, ['status' => $status])) {
            Log::info('角色管理 - 状态变更：成功' . $statusText . '角色: ' . $role['name'] . ' (ID: ' . $id . ')', Log::CATEGORY_OPERATION);
            $_SESSION['success'] = '角色状态更新成功';
        } else {
            Log::error('角色管理 - 状态变更：' . $statusText . '角色失败: ' . $role['name'] . ' (ID: ' . $id . ')', Log::CATEGORY_OPERATION);
            $_SESSION['error'] = '角色状态更新失败';
        }
        
        // 跳转回角色列表
        header('Location: admin.php?action=role');
        exit;
    }
}
