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


class RoleModel {
    /**
     * 获取所有角色
     * @param int $page 页码
     * @param int $limit 每页数量
     * @param array $filters 筛选条件
     * @return array
     */
    public static function getAllRoles($page = 1, $limit = 10, $filters = []) {
        $config = Config::get('database');
        $prefix = $config['prefix'];
        $db = Database::getInstance();
        
        $sql = "SELECT * FROM {$prefix}role WHERE 1 = 1";
        $totalSql = "SELECT COUNT(*) as total FROM {$prefix}role WHERE 1 = 1";
        $params = [];
        
        // 搜索筛选
        if (!empty($filters['search'])) {
            $searchCondition = " AND (name LIKE ? OR description LIKE ?)";
            $sql .= $searchCondition;
            $totalSql .= $searchCondition;
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        // 状态筛选
        if (isset($filters['status']) && $filters['status'] != '') {
            $statusCondition = " AND status = ?";
            $sql .= $statusCondition;
            $totalSql .= $statusCondition;
            $params[] = $filters['status'];
        }
        
        // 父角色筛选
        if (isset($filters['parent_id']) && $filters['parent_id'] != '') {
            $parentCondition = " AND parent_id = ?";
            $sql .= $parentCondition;
            $totalSql .= $parentCondition;
            $params[] = $filters['parent_id'];
        }
        
        // 排序和分页
        $sql .= " ORDER BY id DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = ($page - 1) * $limit;
        
        // 获取总数
        $totalResult = $db->fetch($totalSql, $params);
        $total = $totalResult['total'] ?? 0;
        
        // 获取分页数据
        $data = $db->fetchAll($sql, $params);
        
        return [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'data' => $data
        ];
    }
    
    /**
     * 根据ID获取角色
     * @param int $id 角色ID
     * @return array
     */
    public static function getRoleById($id) {
        $config = Config::get('database');
        $prefix = $config['prefix'];
        $db = Database::getInstance();
        $sql = "SELECT * FROM {$prefix}role WHERE id = ?";
        return $db->fetch($sql, [$id]);
    }
    
    /**
     * 创建角色
     * @param array $data 角色数据
     * @return int|bool 角色ID或false
     */
    public static function createRole($data) {
        // 验证必要字段
        if (empty($data['name'])) {
            return false;
        }
        
        // 检查角色名称是否已存在
        $existingRole = self::getRoleByName($data['name']);
        if ($existingRole) {
            return false;
        }
        
        // 准备数据
        $timestamp = time();
        $roleData = [
            'name' => $data['name'],
            'description' => $data['description'] ?? '',
            'parent_id' => $data['parent_id'] ?? 0,
            'status' => $data['status'] ?? 1,
            'created_at' => $timestamp,
            'updated_at' => $timestamp
        ];
        
        // 插入角色
        $db = Database::getInstance();
        return $db->insert('role', $roleData);
    }
    
    /**
     * 根据名称获取角色
     * @param string $name 角色名称
     * @return array
     */
    public static function getRoleByName($name) {
        $config = Config::get('database');
        $prefix = $config['prefix'];
        $db = Database::getInstance();
        $sql = "SELECT * FROM {$prefix}role WHERE name = ?";
        return $db->fetch($sql, [$name]);
    }
    
    /**
     * 更新角色
     * @param int $id 角色ID
     * @param array $data 角色数据
     * @return bool
     */
    public static function updateRole($id, $data) {
        // 验证必要字段
        if (empty($id)) {
            return false;
        }
        
        // 准备数据
        $timestamp = time();
        $roleData = [
            'updated_at' => $timestamp
        ];
        
        // 只更新提供的字段
        if (isset($data['name'])) {
            $roleData['name'] = $data['name'];
        }
        if (isset($data['description'])) {
            $roleData['description'] = $data['description'];
        }
        
        // 管理员角色不能修改父角色和状态
        if ($id != 1) {
            if (isset($data['parent_id'])) {
                // 不能将自己设为父角色
                if ($id == $data['parent_id']) {
                    return false;
                }
                $roleData['parent_id'] = $data['parent_id'];
            }
            if (isset($data['status'])) {
                $roleData['status'] = $data['status'];
            }
        }
        
        // 更新角色
        $db = Database::getInstance();
        return $db->update('role', $roleData, ['id' => $id]);
    }
    
    /**
     * 删除角色
     * @param int $id 角色ID
     * @return bool
     */
    public static function deleteRole($id) {
        // 验证必要字段
        if (empty($id)) {
            return false;
        }
        
        // 不能删除默认角色
        if (in_array($id, [1, 2, 3, 4])) {
            return false;
        }
        
        // 删除角色
        $db = Database::getInstance();
        return $db->delete('role', ['id' => $id]);
    }
    
    /**
     * 获取角色的所有权限（包括继承的权限）
     * @param int $roleId 角色ID
     * @return array
     */
    public static function getRolePermissions($roleId) {
        $config = Config::get('database');
        $prefix = $config['prefix'];
        $db = Database::getInstance();
        
        // 获取角色及其所有父角色
        $roles = self::getAllParentRoles($roleId);
        $roleIds = array_column($roles, 'id');
        
        // 如果没有角色ID，直接返回空数组，避免SQL语法错误
        if (empty($roleIds)) {
            return [];
        }
        
        // 获取角色的所有权限
        $sql = "SELECT p.* FROM {$prefix}permission p 
                INNER JOIN {$prefix}role_permission rp ON p.id = rp.permission_id 
                WHERE rp.role_id IN (" . implode(',', $roleIds) . ") 
                AND p.status = 1 
                GROUP BY p.id 
                ORDER BY p.sort ASC, p.id ASC";
        
        return $db->fetchAll($sql);
    }
    
    /**
     * 获取角色的所有父角色（包括自己）
     * @param int $roleId 角色ID
     * @return array
     */
    public static function getAllParentRoles($roleId) {
        $config = Config::get('database');
        $prefix = $config['prefix'];
        $db = Database::getInstance();
        
        $roles = [];
        
        // 如果角色ID为0，直接返回空数组
        if ($roleId == 0) {
            return $roles;
        }
        
        $currentRole = self::getRoleById($roleId);
        
        if ($currentRole) {
            $roles[] = $currentRole;
            
            // 递归获取父角色
            while ($currentRole['parent_id'] != 0) {
                $parentRole = self::getRoleById($currentRole['parent_id']);
                if ($parentRole) {
                    $roles[] = $parentRole;
                    $currentRole = $parentRole;
                } else {
                    break;
                }
            }
        }
        
        return $roles;
    }
    
    /**
     * 为角色分配权限
     * @param int $roleId 角色ID
     * @param array $permissionIds 权限ID数组
     * @return bool
     */
    public static function assignPermissions($roleId, $permissionIds) {
        // 验证必要字段
        if (empty($roleId) || !is_array($permissionIds)) {
            return false;
        }
        
        // 管理员角色不能分配权限
        if ($roleId == 1) {
            return false;
        }
        
        // 删除角色原有的权限
        $db = Database::getInstance();
        $db->delete('role_permission', ['role_id' => $roleId]);
        
        // 分配新权限
        $timestamp = time();
        $success = true;
        
        // 开始事务
        $db->beginTransaction();
        
        try {
            foreach ($permissionIds as $permissionId) {
                if (!empty($permissionId)) {
                    $result = $db->insert('role_permission', [
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                        'created_at' => $timestamp
                    ]);
                    
                    if (!$result) {
                        throw new Exception('Insert failed');
                    }
                }
            }
            
            // 提交事务
            $db->commit();
        } catch (Exception $e) {
            // 回滚事务
            $db->rollback();
            $success = false;
        }
        
        return $success;
    }
    
    /**
     * 获取角色已分配的权限ID列表
     * @param int $roleId 角色ID
     * @return array
     */
    public static function getRolePermissionIds($roleId) {
        $config = Config::get('database');
        $prefix = $config['prefix'];
        $db = Database::getInstance();
        
        $sql = "SELECT permission_id FROM {$prefix}role_permission WHERE role_id = ?";
        $result = $db->fetchAll($sql, [$roleId]);
        
        return array_column($result, 'permission_id');
    }
    
    /**
     * 获取角色列表（用于下拉选择）
     * @return array
     */
    public static function getRoleList() {
        $config = Config::get('database');
        $prefix = $config['prefix'];
        $db = Database::getInstance();
        
        $sql = "SELECT id, name FROM {$prefix}role WHERE status = 1 ORDER BY id ASC";
        $result = $db->fetchAll($sql);
        
        $roles = [];
        foreach ($result as $role) {
            $roles[$role['id']] = $role['name'];
        }
        
        return $roles;
    }
    
    /**
     * 检查角色是否有某个权限
     * @param int $roleId 角色ID
     * @param string $permissionCode 权限编码
     * @return bool
     */
    public static function checkRolePermission($roleId, $permissionCode) {
        // 如果角色ID为0，说明没有分配角色，直接返回false
        if ($roleId == 0) {
            return false;
        }
        
        $permissions = self::getRolePermissions($roleId);
        $permissionCodes = array_column($permissions, 'code');
        
        return in_array($permissionCode, $permissionCodes);
    }
    
    /**
     * 检查用户是否有某个权限
     * @param int $userId 用户ID
     * @param string $permissionCode 权限编码
     * @return bool
     */
    public static function checkUserPermission($userId, $permissionCode) {
        // 加载用户模型
        if (!class_exists('UserModel')) {
            require APP_PATH . '/Models/UserModel.php';
        }
        
        // 获取用户信息
        $user = UserModel::getUserById($userId);
        if (!$user) {
            return false;
        }
        
        // 检查角色权限
        return self::checkRolePermission($user['role'], $permissionCode);
    }
    
    /**
     * 获取角色状态名称
     * @param int $status 状态值
     * @return string
     */
    public static function getStatusName($status) {
        switch ($status) {
            case 0:
                return '禁用';
            case 1:
                return '启用';
            default:
                return '未知';
        }
    }
    
    /**
     * 获取角色名称
     * @param int $roleId 角色ID
     * @return string
     */
    public static function getRoleName($roleId) {
        $role = self::getRoleById($roleId);
        return $role ? $role['name'] : '未知角色';
    }

    /**
     * 判断指定用户是否允许访问后台管理系统
     * 规则（方案 B）：
     *   1. role == 1 的「系统管理员」无条件放行；
     *   2. 其它角色必须通过 role_permission 被显式授予了至少一条
     *      状态为启用的权限，才允许进入后台；
     *   3. 角色本身必须是 status == 1（启用）状态，才能进入后台；
     *   4. 普通注册用户（role=3 及其继承链上）如果没有分配任何权限 → 拒绝。
     * 好处：后续新增 role=5 / role=6 ... 等自定义角色时，
     * 只要在「角色管理」里给它分配了菜单权限，即可自动登录后台，无需改代码。
     *
     * @param array $user 来自 bk_user 的一条记录（至少包含 role 字段）
     * @return bool
     */
    public static function userHasAdminAccess($user) {
        // 1) 基本参数校验
        if (!is_array($user) || empty($user) || empty($user['role'])) {
            return false;
        }

        $roleId = (int)$user['role'];

        // 2) 系统管理员（role=1）无条件放行
        if ($roleId === 1) {
            return true;
        }

        // 3) 角色本身必须处于启用状态
        $role = self::getRoleById($roleId);
        if (!$role || (int)$role['status'] !== 1) {
            return false;
        }

        // 4) 通过角色（含父角色继承链）是否分配了至少一条权限来判断
        //    RoleModel::getRolePermissions 内部已做：
        //      - 递归收集 getAllParentRoles；
        //      - 过滤 permission.status = 1；
        //      - GROUP BY p.id 去重。
        $permissions = self::getRolePermissions($roleId);

        return !empty($permissions);
    }
}