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


// 加载权限模型类
// 加载日志类

class PermissionController {
    /**
     * 权限列表页面
     */
    public function index() {
        // 获取筛选条件
        $search = $_GET['search'] ?? '';
        $type = $_GET['type'] ?? '';
        $status = $_GET['status'] ?? '';

        // 构建筛选条件（与 RoleController::index 的 filters 组装方式一致：
        // 空值不入 filters，保证分页链接与筛选表单的参数回填干净）
        $filters = [];
        if (!empty($search)) {
            $filters['search'] = $search;
        }
        if ($type !== '') {
            $filters['type'] = $type;
        }
        if ($status !== '') {
            $filters['status'] = $status;
        }

        // 获取所有权限
        $allPermissions = PermissionModel::getAllPermissions($filters);

        // 构建权限树
        $permissionTree = PermissionModel::buildPermissionTree($allPermissions);

        // ==================== 分页处理 ====================
        // 权限列表按树形展示（父子相邻渲染），为避免「父在本页、子在别页」的父子失散问题，
        // 先按深度优先顺序把权限树展平为单列行序（与原整页展示顺序完全一致），再切片分页；
        // 展平后的行不携带 children 键，模板的递归行渲染函数对其同样适用（平铺逐行渲染）。
        $flatPermissions = [];
        $flattenTree = function ($nodes) use (&$flattenTree, &$flatPermissions) {
            foreach ($nodes as $node) {
                // 摘除子级引用：展平后的行仅保留自身数据，子级按深度优先顺序随后独立成行
                $children = isset($node['children']) ? $node['children'] : [];
                unset($node['children']);
                $flatPermissions[] = $node;
                if (!empty($children)) {
                    $flattenTree($children);
                }
            }
        };
        $flattenTree($permissionTree);

        // 分页参数（limit 白名单校验，与 FriendlinkController 等列表页惯例一致）
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $limit = ListQuery::pageSize();
        $allowedPageSizes = ListQuery::pageSizes();
        $offset = ($page - 1) * $limit;

        // 总条数与总页数（供分页组件展示统计与页码）
        $total = count($flatPermissions);
        $totalPages = ceil($total / $limit);

        // 当前页数据：切片后回填 $permissionTree，模板渲染与空状态判断逻辑无需改动
        $permissionTree = array_slice($flatPermissions, $offset, $limit);

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
            // 检查 CSRF 令牌（与 delete/batchDelete 同一校验标准）
            $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
            if (!Security::validateCsrfToken($token)) {
                $_SESSION['error'] = '非法请求';
                header('Location: admin.php?action=permission');
                exit;
            }

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
                Log::info('权限管理 - 添加权限：成功添加权限: ' . $data['name'] . ' (代码: ' . $data['code'] . ')', Log::CATEGORY_OPERATION);
                header('Location: admin.php?action=permission');
                exit;
            } else {
                Log::error('权限管理 - 添加权限：创建权限失败: ' . $data['name'], Log::CATEGORY_OPERATION);
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

        // 内置权限标记：模板据此锁定「权限编码」输入框（改码等于变相删除权限），
        // 模型层 updatePermission 亦会强制忽略内置权限的 code 变更（双重保险）
        $isSystemPermission = !empty($permission['is_system']);

        // 处理表单提交
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // 检查 CSRF 令牌（与 delete/batchDelete 同一校验标准）
            $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
            if (!Security::validateCsrfToken($token)) {
                $_SESSION['error'] = '非法请求';
                header('Location: admin.php?action=permission');
                exit;
            }

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

            // 内置权限锁码：服务端强制沿用原 code，不信任表单提交值
            if ($isSystemPermission) {
                $data['code'] = $permission['code'];
            }
            
            if (PermissionModel::updatePermission($id, $data)) {
                Log::info('权限管理 - 编辑权限：成功编辑权限: ' . $data['name'] . ' (ID: ' . $id . ')', Log::CATEGORY_OPERATION);
                header('Location: admin.php?action=permission');
                exit;
            } else {
                Log::error('权限管理 - 编辑权限：更新权限失败: ID ' . $id, Log::CATEGORY_OPERATION);
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
     *
     * 安全约束：仅接受 POST 提交 + CSRF 令牌校验（对齐批量删除标准，
     * 防 GET 链接误触发/跨站伪造）；内置权限（is_system=1）由模型层拒绝，
     * 此处先行判断以给出明确的错误提示。
     */
    public function delete() {
        // 仅接受 POST 提交（行内小表单提交），防 GET 链接误触发
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error'] = '非法请求';
            header('Location: admin.php?action=permission');
            exit;
        }

        // 检查 CSRF 令牌
        $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
        if (!Security::validateCsrfToken($token)) {
            $_SESSION['error'] = '非法请求';
            header('Location: admin.php?action=permission');
            exit;
        }

        // 获取权限ID
        $id = $_POST['id'] ?? 0;
        if (!$id) {
            header('Location: admin.php?action=permission');
            exit;
        }

        // 删除权限
        $permission = PermissionModel::getPermissionById($id);
        if (PermissionModel::deletePermission($id)) {
            Log::info('权限管理 - 删除权限：成功删除权限: ' . $permission['name'] . ' (ID: ' . $id . ')', Log::CATEGORY_OPERATION);
            $_SESSION['success'] = '权限删除成功';
        } elseif (!empty($permission['is_system'])) {
            // 内置权限（is_system=1）由安装/升级引擎维护，禁止删除；
            // 误删的内置权限可通过工具栏「一键修复权限」补回
            Log::error('权限管理 - 删除权限：拒绝删除内置权限: ' . $permission['name'] . ' (ID: ' . $id . ')', Log::CATEGORY_OPERATION);
            $_SESSION['error'] = '系统内置权限不允许删除';
        } else {
            Log::error('权限管理 - 删除权限：删除权限失败: ' . $permission['name'] . ' (ID: ' . $id . ')', Log::CATEGORY_OPERATION);
            $_SESSION['error'] = '权限删除失败，可能是因为该权限有子权限';
        }

        // 跳转回权限列表
        header('Location: admin.php?action=permission');
        exit;
    }

    /**
     * 批量删除权限
     *
     * 与单条 delete() 语义一致：逐条复用 PermissionModel::deletePermission()，
     * 该方法的保护逻辑为「存在子权限时返回 false 拒绝删除」，删除成功后
     * 会同步清理角色-权限关联（bk_role_permission）。循环内不做任何跳转，
     * 全部执行完后在返回消息中分别说明成功删除与被跳过的数量。
     */
    public function batchDelete() {
        // 批量删除仅接受 POST 提交（前端 submitBatchForm 统一以 POST + csrf_token 提交）
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error'] = '非法请求';
            header('Location: admin.php?action=permission');
            exit;
        }

        // 检查 CSRF 令牌，防止跨站伪造的批量删除请求
        $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
        if (!Security::validateCsrfToken($token)) {
            $_SESSION['error'] = '非法请求';
            header('Location: admin.php?action=permission');
            exit;
        }

        $ids = isset($_POST['ids']) ? $_POST['ids'] : [];
        if (empty($ids)) {
            $_SESSION['error'] = '请选择要删除的权限';
            header('Location: admin.php?action=permission');
            exit;
        }

        $successCount = 0; // 成功删除的权限数量
        $skipCount = 0;    // 因存在子权限或删除失败而被跳过的数量

        foreach ($ids as $id) {
            $id = (int)$id;
            if (!$id) {
                $skipCount++;
                continue;
            }

            // deletePermission 内部校验子权限并清理角色-权限关联，失败（如有子权限）计入跳过数
            if (PermissionModel::deletePermission($id)) {
                $successCount++;
            } else {
                $skipCount++;
            }
        }

        // 记录批量删除日志（沿用本控制器的日志风格：模块 + 动作 + 说明 + 分类）
        if ($successCount > 0) {
            Log::info('权限管理 - 批量删除：成功批量删除权限 ' . $successCount . ' 个 (IDs: ' . implode(',', array_map('intval', $ids)) . ')', Log::CATEGORY_OPERATION);
        }

        // 汇总消息（沿用本控制器 delete() 的 $_SESSION['success'] / $_SESSION['error'] 机制；
        // 内置权限由模型层拒绝删除并计入跳过数量）
        if ($successCount > 0 && $skipCount > 0) {
            $_SESSION['success'] = '成功删除 ' . $successCount . ' 个权限，跳过 ' . $skipCount . ' 个（内置权限/存在子权限或删除失败）';
        } elseif ($successCount > 0) {
            $_SESSION['success'] = '权限删除成功（共 ' . $successCount . ' 个）';
        } else {
            $_SESSION['error'] = '没有权限被删除（所选权限为系统内置或存在子权限）';
        }

        // 跳转回权限列表
        header('Location: admin.php?action=permission');
        exit;
    }

    /**
     * 一键修复权限
     *
     * 调用迁移引擎 Migrate::repairPermissions()（仅 bk_permission 补插与
     * 内置标记回填，无任何 DDL/其他表变更，幂等安全）：
     * - 补回被误删的内置权限行，并同步为角色 1（管理员）授权；
     * - 为老站存量菜单行补上 is_system 内置标记。
     * 仅接受 POST + CSRF（写操作统一标准）。
     */
    public function repair() {
        // 仅接受 POST 提交 + CSRF 令牌校验
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error'] = '非法请求';
            header('Location: admin.php?action=permission');
            exit;
        }
        $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
        if (!Security::validateCsrfToken($token)) {
            $_SESSION['error'] = '非法请求';
            header('Location: admin.php?action=permission');
            exit;
        }

        // 执行修复并记录日志
        $result = Migrate::repairPermissions();
        if ($result['success']) {
            Log::info('权限管理 - 一键修复：' . $result['message'] . '（补回 ' . $result['inserted'] . ' 个，补标记 ' . $result['flagged'] . ' 个）', Log::CATEGORY_OPERATION);
            $_SESSION['success'] = $result['message'];
        } else {
            Log::error('权限管理 - 一键修复：' . $result['message'], Log::CATEGORY_OPERATION);
            $_SESSION['error'] = $result['message'];
        }

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
            Log::info('权限管理 - 状态变更：成功' . $statusText . '权限: ' . $permission['name'] . ' (ID: ' . $id . ')', Log::CATEGORY_OPERATION);
            $_SESSION['success'] = '权限状态更新成功';
        } else {
            Log::error('权限管理 - 状态变更：' . $statusText . '权限失败: ' . $permission['name'] . ' (ID: ' . $id . ')', Log::CATEGORY_OPERATION);
            $_SESSION['error'] = '权限状态更新失败';
        }
        
        // 跳转回权限列表
        header('Location: admin.php?action=permission');
        exit;
    }
}
