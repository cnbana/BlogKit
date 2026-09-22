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


class PermissionModel {
    /**
     * 获取所有权限
     * @param array $filters 筛选条件
     * @return array
     */
    public static function getAllPermissions($filters = []) {
        $config = Config::get('database');
        $prefix = $config['prefix'];
        $db = Database::getInstance();
        
        $sql = "SELECT * FROM {$prefix}permission WHERE 1 = 1";
        $params = [];
        
        // 类型筛选
        if (isset($filters['type']) && $filters['type'] != '') {
            $typeCondition = " AND type = ?";
            $sql .= $typeCondition;
            $params[] = $filters['type'];
        }
        
        // 状态筛选
        if (isset($filters['status']) && $filters['status'] != '') {
            $statusCondition = " AND status = ?";
            $sql .= $statusCondition;
            $params[] = $filters['status'];
        }
        
        // 父权限筛选
        if (isset($filters['parent_id']) && $filters['parent_id'] != '') {
            $parentCondition = " AND parent_id = ?";
            $sql .= $parentCondition;
            $params[] = $filters['parent_id'];
        }
        
        // 排序
        $sql .= " ORDER BY sort ASC, id ASC";
        
        return $db->fetchAll($sql, $params);
    }
    
    /**
     * 根据ID获取权限
     * @param int $id 权限ID
     * @return array
     */
    public static function getPermissionById($id) {
        $config = Config::get('database');
        $prefix = $config['prefix'];
        $db = Database::getInstance();
        $sql = "SELECT * FROM {$prefix}permission WHERE id = ?";
        return $db->fetch($sql, [$id]);
    }
    
    /**
     * 根据编码获取权限
     * @param string $code 权限编码
     * @return array
     */
    public static function getPermissionByCode($code) {
        $config = Config::get('database');
        $prefix = $config['prefix'];
        $db = Database::getInstance();
        $sql = "SELECT * FROM {$prefix}permission WHERE code = ?";
        return $db->fetch($sql, [$code]);
    }
    
    /**
     * 创建权限
     * @param array $data 权限数据
     * @return int|bool 权限ID或false
     */
    public static function createPermission($data) {
        // 验证必要字段
        if (empty($data['name']) || empty($data['code'])) {
            return false;
        }
        
        // 检查权限编码是否已存在
        if (self::getPermissionByCode($data['code'])) {
            return false;
        }
        
        // 准备数据
        $timestamp = time();
        $permissionData = [
            'name' => $data['name'],
            'code' => $data['code'],
            'type' => $data['type'] ?? 1,
            'parent_id' => $data['parent_id'] ?? 0,
            'path' => $data['path'] ?? '',
            'icon' => $data['icon'] ?? '',
            'sort' => $data['sort'] ?? 0,
            'status' => $data['status'] ?? 1,
            'created_at' => $timestamp,
            'updated_at' => $timestamp
        ];
        
        // 插入权限
        $db = Database::getInstance();
        return $db->insert('permission', $permissionData);
    }
    
    /**
     * 更新权限
     * @param int $id 权限ID
     * @param array $data 权限数据
     * @return bool
     */
    public static function updatePermission($id, $data) {
        // 验证必要字段
        if (empty($id)) {
            return false;
        }
        
        // 检查权限编码是否已存在（排除当前权限）
        if (isset($data['code'])) {
            $existing = self::getPermissionByCode($data['code']);
            if ($existing && $existing['id'] != $id) {
                return false;
            }
        }
        
        // 准备数据
        $timestamp = time();
        $permissionData = [
            'updated_at' => $timestamp
        ];
        
        // 只更新提供的字段
        if (isset($data['name'])) {
            $permissionData['name'] = $data['name'];
        }
        if (isset($data['code'])) {
            $permissionData['code'] = $data['code'];
        }
        if (isset($data['type'])) {
            $permissionData['type'] = $data['type'];
        }
        if (isset($data['parent_id'])) {
            // 不能将自己设为父权限
            if ($id == $data['parent_id']) {
                return false;
            }
            $permissionData['parent_id'] = $data['parent_id'];
        }
        if (isset($data['path'])) {
            $permissionData['path'] = $data['path'];
        }
        if (isset($data['icon'])) {
            $permissionData['icon'] = $data['icon'];
        }
        if (isset($data['sort'])) {
            $permissionData['sort'] = $data['sort'];
        }
        if (isset($data['status'])) {
            $permissionData['status'] = $data['status'];
        }
        
        // 更新权限
        $db = Database::getInstance();
        return $db->update('permission', $permissionData, ['id' => $id]);
    }
    
    /**
     * 删除权限
     * @param int $id 权限ID
     * @return bool
     */
    public static function deletePermission($id) {
        // 验证必要字段
        if (empty($id)) {
            return false;
        }
        
        // 检查是否有子权限
        $children = self::getAllPermissions(['parent_id' => $id]);
        if (!empty($children)) {
            return false;
        }
        
        // 删除权限
        $db = Database::getInstance();
        $result = $db->delete('permission', ['id' => $id]);
        
        // 删除角色-权限关联
        if ($result) {
            $db->delete('role_permission', ['permission_id' => $id]);
        }
        
        return $result;
    }
    
    /**
     * 构建权限树
     * @param array $permissions 权限列表
     * @param int $parentId 父权限ID
     * @return array
     */
    public static function buildPermissionTree($permissions, $parentId = 0) {
        $tree = [];
        
        foreach ($permissions as $permission) {
            if ($permission['parent_id'] == $parentId) {
                $children = self::buildPermissionTree($permissions, $permission['id']);
                if (!empty($children)) {
                    $permission['children'] = $children;
                }
                $tree[] = $permission;
            }
        }
        
        return $tree;
    }
    
    /**
     * 获取权限树
     * @param array $filters 筛选条件
     * @return array
     */
    public static function getPermissionTree($filters = []) {
        $permissions = self::getAllPermissions($filters);
        return self::buildPermissionTree($permissions);
    }
    
    /**
     * 获取权限类型名称
     * @param int $type 权限类型
     * @return string
     */
    public static function getPermissionTypeName($type) {
        switch ($type) {
            case 1:
                return '菜单';
            case 2:
                return '操作';
            default:
                return '未知';
        }
    }
    
    /**
     * 获取权限类型列表
     * @return array
     */
    public static function getPermissionTypeList() {
        return [
            1 => '菜单',
            2 => '操作'
        ];
    }
    
    /**
     * 检查权限是否存在
     * @param string $code 权限编码
     * @return bool
     */
    public static function permissionExists($code) {
        $permission = self::getPermissionByCode($code);
        return !empty($permission);
    }
    
    /**
     * 获取菜单权限
     * @param int $roleId 角色ID
     * @return array
     */
    public static function getMenuPermissions($roleId) {
        // 加载角色模型
        require_once APP_PATH . '/Models/RoleModel.php';
        
        // 如果角色ID为0，直接返回空数组
        if ($roleId == 0) {
            return [];
        }
        
        // 获取角色的所有权限
        $permissions = RoleModel::getRolePermissions($roleId);
        
        // 筛选菜单权限
        $menuPermissions = array_filter($permissions, function($permission) {
            return $permission['type'] == 1 && $permission['status'] == 1;
        });
        
        // 构建菜单树
        return self::buildPermissionTree($menuPermissions);
    }
    
    /**
     * 检查用户是否有访问当前页面的权限
     * @param int $userId 用户ID
     * @param string $currentPath 当前页面路径
     * @return bool
     */
    public static function checkPagePermission($userId, $currentPath) {
        // 加载必要的模型
        require_once APP_PATH . '/Models/RoleModel.php';
        require_once APP_PATH . '/Models/UserModel.php';
        
        // 获取用户信息
        $user = UserModel::getUserById($userId);
        if (!$user) {
            return false;
        }
        
        // 获取用户角色的所有菜单权限
        $menuPermissions = self::getMenuPermissions($user['role']);
        
        // 递归检查菜单权限
        return self::checkPermissionInTree($menuPermissions, $currentPath);
    }
    
    /**
     * 递归检查权限树中是否包含某个路径
     * @param array $menuTree 菜单树
     * @param string $currentPath 当前路径
     * @return bool
     */
    private static function checkPermissionInTree($menuTree, $currentPath) {
        foreach ($menuTree as $menu) {
            // 检查当前菜单
            if ($menu['path'] == $currentPath) {
                return true;
            }
            
            // 检查子菜单
            if (isset($menu['children']) && !empty($menu['children'])) {
                if (self::checkPermissionInTree($menu['children'], $currentPath)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * 获取所有父权限列表（用于下拉选择）
     * @param int $type 权限类型
     * @return array
     */
    public static function getParentPermissionList($type = null) {
        $filters = ['type' => $type, 'status' => 1];
        $permissions = self::getAllPermissions($filters);
        
        $parentList = [0 => '顶级权限'];
        
        foreach ($permissions as $permission) {
            $parentList[$permission['id']] = $permission['name'];
        }
        
        return $parentList;
    }
}
