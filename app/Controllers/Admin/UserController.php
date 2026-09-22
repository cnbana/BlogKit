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


// 加载用户模型类
// 加载日志类
// 加载配置类

class UserController {
    public function index() {
        // 获取分页参数（limit 白名单校验）
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $page = max(1, $page);
        $limit = ListQuery::pageSize();
        $allowedPageSizes = ListQuery::pageSizes();
        
        // 构建筛选条件
        $filters = [];
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $filters['search'] = $_GET['search'];
        }
        if (isset($_GET['role']) && $_GET['role'] !== '') {
            $filters['role'] = $_GET['role'];
        }
        if (isset($_GET['status']) && $_GET['status'] !== '') {
            $filters['status'] = $_GET['status'];
        }
        if (isset($_GET['created_at_start']) && !empty($_GET['created_at_start'])) {
            $filters['created_at_start'] = $_GET['created_at_start'];
        }
        if (isset($_GET['created_at_end']) && !empty($_GET['created_at_end'])) {
            $filters['created_at_end'] = $_GET['created_at_end'];
        }
        if (isset($_GET['last_login_start']) && !empty($_GET['last_login_start'])) {
            $filters['last_login_start'] = $_GET['last_login_start'];
        }
        if (isset($_GET['last_login_end']) && !empty($_GET['last_login_end'])) {
            $filters['last_login_end'] = $_GET['last_login_end'];
        }
        
        // 获取用户列表
        // 表头排序参数：白名单校验（模型内再做二次校验）
        list($sort, $order) = ListQuery::sort(['id', 'created_at', 'last_login_at']);
        $usersResult = UserModel::getAllUsers($page, $limit, $filters, $sort, $order);
        $users = $usersResult['data'];
        $total = $usersResult['total'];
        $totalPages = ceil($total / $limit);
        
        // 获取角色列表 - 使用RoleModel确保与添加/编辑页面一致
        $roleList = RoleModel::getRoleList();
        
        // 转换时间格式和添加角色名称
        foreach ($users as &$user) {
            $user['created_at'] = date('Y-m-d H:i:s', (int)$user['created_at']);
            $user['last_login_at'] = !empty($user['last_login_at']) ? date('Y-m-d H:i:s', (int)$user['last_login_at']) : '-';
            // 添加状态名称
            $user['status_name'] = UserModel::getStatusName($user['status']);
            // 添加角色名称
            $user['role_name'] = $roleList[$user['role']] ?? '未知角色';
            // 添加角色CSS类
            $roleClass = 'role-default';
            switch ($user['role']) {
                case 1:
                    $roleClass = 'role-admin';
                    break;
                case 4:
                    $roleClass = 'role-author';
                    break;
                case 3:
                    $roleClass = 'role-editor';
                    break;
                case 2:
                    $roleClass = 'role-visitor';
                    break;
            }
            $user['role_class'] = $roleClass;
            
            // 注销状态由 accountdeletion 插件注入（通过钩子 admin_user_list_field）
        }
        // 解除引用，避免潜在问题
        unset($user);
        
        // 插件注入用户列表字段（如注销状态）
        $filtered = Hook::filter('admin_user_list_field', ['users' => $users]);
        // 插件回调直接返回 $users 数组，未安装插件时返回 ['users' => $users]
        $users = is_array($filtered) ? (isset($filtered['users']) ? $filtered['users'] : $filtered) : $users;
        
        // 显示用户列表
        include ADMIN_PATH . '/templates/user.html';
    }
    
    // 用户回收站——已提取为插件 (plugins/accountdeletion)
    public function recycle() {
        $data = Hook::trigger('admin_user_recycle_page', [
            'page' => max(1, intval($_GET['page'] ?? 1)),
            'limit' => 20
        ]);
        // 插件会通过钩子直接输出或重定向
        if (empty($data)) {
            header('Location: admin.php?action=user');
            exit;
        }
    }

    public function add() {
        // 检查用户是否有添加用户的权限
        $currentUser = $_SESSION['admin'];
        if (!RoleModel::checkUserPermission($currentUser['id'], 'user_add')) {
            ExceptionHandler::handle403();
        }
        
        // 处理表单提交
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $role = $_POST['role'];
            
            // 不允许设置游客角色（role=2）
            if ($role == 2) {
                Log::error('用户管理', '添加用户', '尝试设置游客角色，已拒绝', Log::CATEGORY_OPERATION);
                $error = '不允许设置游客角色';
            } else {
                $data = [
                    'username' => $_POST['username'],
                    'password' => $_POST['password'],
                    'nickname' => $_POST['nickname'],
                    'email' => $_POST['email'],
                    'role' => $role,
                    'status' => isset($_POST['status']) ? 1 : 0
                ];
                
                if (UserModel::createUser($data)) {
                    Log::info('用户管理', '添加用户', '成功添加用户: ' . $data['username'] . ' (昵称: ' . $data['nickname'] . ')', Log::CATEGORY_OPERATION);
                    header('Location: admin.php?action=user');
                    exit;
                } else {
                    Log::error('用户管理', '添加用户', '创建用户失败: ' . $data['username'], Log::CATEGORY_OPERATION);
                    $error = '创建用户失败，请检查输入信息';
                }
            }
        }
        
        // 获取角色列表
        $roles = RoleModel::getRoleList();
        
        // 显示添加用户页面
        include ADMIN_PATH . '/templates/user_add.html';
    }
    
    public function edit() {
        // 检查用户是否有编辑用户的权限
        $currentUser = $_SESSION['admin'];
        if (!RoleModel::checkUserPermission($currentUser['id'], 'user_edit')) {
            ExceptionHandler::handle403();
        }
        
        // 获取用户ID
        $id = $_GET['id'] ?? 0;
        if (!$id) {
            header('Location: admin.php?action=user');
            exit;
        }
        
        // 获取用户信息
        $user = UserModel::getUserById($id);
        if (!$user) {
            header('Location: admin.php?action=user');
            exit;
        }
        
        // 处理表单提交
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'username' => $_POST['username'],
                'password' => $_POST['password'],
                'nickname' => $_POST['nickname'],
                'email' => $_POST['email']
            ];
            
            // 如果不是ID为1的系统初始管理员，才允许修改角色和状态
            if ($user['id'] != 1) {
                $role = $_POST['role'];
                
                // 不允许设置游客角色（role=2）
                if ($role == 2) {
                    Log::error('用户管理', '编辑用户', '尝试设置游客角色，已拒绝', Log::CATEGORY_OPERATION);
                    $error = '不允许设置游客角色';
                } else {
                    $data['role'] = $role;
                    $data['status'] = isset($_POST['status']) ? 1 : 0;
                    
                    if (UserModel::updateUser($id, $data)) {
                        Log::info('用户管理', '编辑用户', '成功编辑用户ID: ' . $id . ' (用户名: ' . $data['username'] . ')', Log::CATEGORY_OPERATION);
                        header('Location: admin.php?action=user');
                        exit;
                    } else {
                        Log::error('用户管理', '编辑用户', '更新用户失败ID: ' . $id, Log::CATEGORY_OPERATION);
                        $error = '更新用户失败，请检查输入信息';
                    }
                }
            } else {
                // ID为1的系统初始管理员，只修改其他信息
                if (UserModel::updateUser($id, $data)) {
                    Log::info('用户管理', '编辑用户', '成功编辑用户ID: ' . $id . ' (用户名: ' . $data['username'] . ')', Log::CATEGORY_OPERATION);
                    header('Location: admin.php?action=user');
                    exit;
                } else {
                    Log::error('用户管理', '编辑用户', '更新用户失败ID: ' . $id, Log::CATEGORY_OPERATION);
                    $error = '更新用户失败，请检查输入信息';
                }
            }
        }
        
        // 获取角色列表
        $roles = RoleModel::getRoleList();
        
        // 显示编辑用户页面
        include ADMIN_PATH . '/templates/user_edit.html';
    }
    
    public function delete() {
        // 检查用户是否有删除用户的权限
        $currentUser = $_SESSION['admin'];
        if (!RoleModel::checkUserPermission($currentUser['id'], 'user_delete')) {
            ExceptionHandler::handle403();
        }
        
        // 获取用户ID
        $id = $_GET['id'] ?? 0;
        if (!$id) {
            header('Location: admin.php?action=user');
            exit;
        }
        
        // 检查要删除的用户是否是ID为1的系统初始管理员
        $user = UserModel::getUserById($id);
        if ($user && $user['id'] == 1) {
            // ID为1的系统初始管理员禁止删除，添加错误提示
            $_SESSION['error'] = '系统初始管理员禁止删除！';
            header('Location: admin.php?action=user');
            exit;
        }
        
        // 软删除用户——已提取为插件 (plugins/accountdeletion)
        Hook::trigger('admin_user_soft_delete', ['id' => $id]);
        
        header('Location: admin.php?action=user');
        exit;
    }
    
    // 恢复用户——已提取为插件
    public function restore() {
        Hook::trigger('admin_user_restore', ['id' => $_GET['id'] ?? 0]);
        header('Location: admin.php?action=user&method=recycle');
        exit;
    }
    
    // 永久删除用户——已提取为插件
    public function permanentlyDelete() {
        Hook::trigger('admin_user_perm_delete', ['id' => $_GET['id'] ?? 0]);
        header('Location: admin.php?action=user&method=recycle');
        exit;
    }
    
    // 禁用/解封用户
    public function toggleStatus() {
        // 获取用户ID和状态
        $id = $_GET['id'] ?? 0;
        $status = $_GET['status'] ?? 0;
        
        if (!$id) {
            header('Location: admin.php?action=user');
            exit;
        }
        
        // 检查要操作的用户是否是ID为1的系统初始管理员
        $user = UserModel::getUserById($id);
        if ($user && $user['id'] == 1) {
            // ID为1的系统初始管理员禁止操作，添加错误提示
            $_SESSION['error'] = '系统初始管理员禁止操作！';
            header('Location: admin.php?action=user');
            exit;
        }
        
        // 更新用户状态
        $user = UserModel::getUserById($id);
        $statusText = $status == 1 ? '启用' : '禁用';
        if (UserModel::updateUser($id, ['status' => $status, 'updated_at' => time()])) {
            Log::info('用户管理', '状态变更', '成功' . $statusText . '用户: ' . $user['username'] . ' (ID: ' . $id . ')', Log::CATEGORY_OPERATION);
        } else {
            Log::error('用户管理', '状态变更', $statusText . '用户失败ID: ' . $id, Log::CATEGORY_OPERATION);
        }
        
        // 跳转回用户列表
        header('Location: admin.php?action=user');
        exit;
    }
    
    // 用户详情页面
    public function detail() {
        // 获取用户ID
        $userId = $_GET['id'] ?? 0;
        if (!$userId) {
            header('Location: admin.php?action=user');
            exit;
        }
        
        // 确保数据库字段存在
        UserModel::checkAndCreateNewFields();
        
        // 获取用户信息
        $user = UserModel::getUserById($userId);
        if (!$user) {
            header('Location: admin.php?action=user');
            exit;
        }
        
        // 加载角色模型
        
        // 转换时间格式
        $user['created_at'] = date('Y-m-d H:i:s', $user['created_at']);
        $user['updated_at'] = date('Y-m-d H:i:s', $user['updated_at']);
        if (!empty($user['last_login_at'])) {
            $user['last_login_at'] = date('Y-m-d H:i:s', $user['last_login_at']);
        }
        $user['status_name'] = UserModel::getStatusName($user['status']);
        $user['role_name'] = RoleModel::getRoleName($user['role']);
        
        // 注销状态由 accountdeletion 插件通过钩子注入
        Hook::trigger('admin_user_detail_field', ['user' => &$user]);
        
        // 添加角色CSS类
        $roleClass = 'role-default';
        switch ($user['role']) {
            case 1:
                $roleClass = 'role-admin';
                break;
            case 4:
                $roleClass = 'role-author';
                break;
            case 3:
                $roleClass = 'role-editor';
                break;
            case 2:
                $roleClass = 'role-visitor';
                break;
        }
        $user['role_class'] = $roleClass;
        
        // 显示用户详情页面
        include ADMIN_PATH . '/templates/user_detail.html';
    }
    
    // 批量切换用户状态
    public function batchToggleStatus() {
        // 获取参数
        $status = $_GET['status'] ?? 0;
        $userIdsStr = $_GET['user_ids'] ?? '';
        
        // 参数验证
        if (empty($userIdsStr)) {
            $_SESSION['error'] = '请选择要操作的用户';
            header('Location: admin.php?action=user');
            exit;
        }
        
        // 解析用户ID数组
        $userIds = explode(',', $userIdsStr);
        
        // 批量更新用户状态
        $successCount = 0;
        foreach ($userIds as $userId) {
            $userId = intval($userId);
            if (!$userId) continue;
            
            // 检查是否是ID为1的系统初始管理员
            $user = UserModel::getUserById($userId);
            if ($user && $user['id'] == 1) {
                continue; // 跳过系统初始管理员
            }
            
            // 更新用户状态
            if (UserModel::updateUser($userId, ['status' => $status, 'updated_at' => time()])) {
                $successCount++;
            }
        }
        
        // 设置消息并跳转
            $statusText = $status == 1 ? '启用' : '禁用';
            if ($successCount > 0) {
                Log::info('用户管理', '批量状态变更', "成功{$statusText} {$successCount} 个用户", Log::CATEGORY_OPERATION);
                $_SESSION['success'] = "成功{$statusText} {$successCount} 个用户";
            } else {
                Log::error('用户管理', '批量状态变更', "批量{$statusText}失败", Log::CATEGORY_OPERATION);
                $_SESSION['error'] = "批量{$statusText}失败";
            }
            header('Location: admin.php?action=user');
            exit;
    }
    
    // 批量删除用户——已提取为插件
    public function batchDelete() {
        Hook::trigger('admin_user_batch_delete', ['user_ids' => $_GET['user_ids'] ?? '']);
        header('Location: admin.php?action=user');
        exit;
    }
    
    // 批量修改角色
    public function batchUpdateRole() {
        $userIdsStr = $_GET['user_ids'] ?? '';
        $roleId = $_GET['role_id'] ?? 0;
        
        if (empty($userIdsStr) || empty($roleId)) {
            $_SESSION['error'] = '请选择要操作的用户和角色';
            header('Location: admin.php?action=user');
            exit;
        }
        
        $userIds = explode(',', $userIdsStr);
        $count = UserModel::batchUpdateRole($userIds, $roleId);
        
        if ($count > 0) {
            Log::info('用户管理', '批量修改角色', "成功修改 {$count} 个用户的角色", Log::CATEGORY_OPERATION);
            $_SESSION['success'] = "成功修改 {$count} 个用户的角色";
        } else {
            Log::error('用户管理', '批量修改角色', '批量修改角色失败', Log::CATEGORY_OPERATION);
            $_SESSION['error'] = '批量修改角色失败';
        }
        
        header('Location: admin.php?action=user');
        exit;
    }
    
    // 导出用户数据
    public function export() {
        // 检查用户是否有添加用户的权限（导出用户数据需要管理权限）
        $currentUser = $_SESSION['admin'];
        if (!RoleModel::checkUserPermission($currentUser['id'], 'user_edit')) {
            ExceptionHandler::handle403();
        }
        
        
        $format = $_GET['format'] ?? 'csv';
        $filters = [];
        
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $filters['search'] = $_GET['search'];
        }
        if (isset($_GET['role']) && $_GET['role'] != '') {
            $filters['role'] = $_GET['role'];
        }
        
        $users = UserModel::getAllUsersForExport($filters);
        $roleList = RoleModel::getRoleList();
        
        // 设置最大导出数量限制
        $maxExportCount = 10000;
        $exportCount = count($users);
        
        if ($exportCount > $maxExportCount) {
            $users = array_slice($users, 0, $maxExportCount);
            Log::warning('用户管理', '导出用户', "导出用户数量超过限制 ({$exportCount} > {$maxExportCount})，已截断", Log::CATEGORY_OPERATION);
        }
        
        $filename = 'users_' . date('YmdHis');
        
        // 记录导出操作日志
        Log::info('用户管理', '导出用户', "成功导出 {$exportCount} 条用户数据 (格式: {$format})", Log::CATEGORY_OPERATION);
        
        if ($format === 'csv') {
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            fputcsv($output, ['ID', '用户名', '昵称', '邮箱', '角色', '状态', '注册时间', '最后登录时间']);
            
            foreach ($users as $user) {
                $roleName = $roleList[$user['role']] ?? '未知';
                $statusName = UserModel::getStatusName($user['status']);
                $createdAt = date('Y-m-d H:i:s', $user['created_at']);
                $lastLoginAt = !empty($user['last_login_at']) ? date('Y-m-d H:i:s', $user['last_login_at']) : '-';
                
                fputcsv($output, [
                    $user['id'],
                    $user['username'],
                    $user['nickname'],
                    $user['email'],
                    $roleName,
                    $statusName,
                    $createdAt,
                    $lastLoginAt
                ]);
            }
            
            fclose($output);
        } elseif ($format === 'xls') {
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
            echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head>';
            echo '<body><table border="1">';
            
            echo '<tr>';
            echo '<th>ID</th>';
            echo '<th>用户名</th>';
            echo '<th>昵称</th>';
            echo '<th>邮箱</th>';
            echo '<th>角色</th>';
            echo '<th>状态</th>';
            echo '<th>注册时间</th>';
            echo '<th>最后登录时间</th>';
            echo '</tr>';
            
            foreach ($users as $user) {
                $roleName = $roleList[$user['role']] ?? '未知';
                $statusName = UserModel::getStatusName($user['status']);
                $createdAt = date('Y-m-d H:i:s', $user['created_at']);
                $lastLoginAt = !empty($user['last_login_at']) ? date('Y-m-d H:i:s', $user['last_login_at']) : '-';
                
                echo '<tr>';
                echo '<td>' . htmlspecialchars($user['id']) . '</td>';
                echo '<td>' . htmlspecialchars($user['username']) . '</td>';
                echo '<td>' . htmlspecialchars($user['nickname']) . '</td>';
                echo '<td>' . htmlspecialchars($user['email']) . '</td>';
                echo '<td>' . htmlspecialchars($roleName) . '</td>';
                echo '<td>' . htmlspecialchars($statusName) . '</td>';
                echo '<td>' . htmlspecialchars($createdAt) . '</td>';
                echo '<td>' . htmlspecialchars($lastLoginAt) . '</td>';
                echo '</tr>';
            }
            
            echo '</table></body></html>';
        }
        
        exit;
    }
    
    // 显示导入用户页面
    public function import() {
        // 检查用户是否有添加用户的权限
        $currentUser = $_SESSION['admin'];
        if (!RoleModel::checkUserPermission($currentUser['id'], 'user_add')) {
            header('HTTP/1.0 403 Forbidden');
            echo '403 Permission Denied';
            exit;
        }
        
        include ADMIN_PATH . '/templates/user_import.html';
    }
    
    // 处理用户导入
    public function doImport() {
        // 检查用户是否有添加用户的权限
        $currentUser = $_SESSION['admin'];
        if (!RoleModel::checkUserPermission($currentUser['id'], 'user_add')) {
            ExceptionHandler::handle403();
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['import_file'])) {
            $_SESSION['error'] = '请选择要导入的文件';
            header('Location: admin.php?action=user&sub=import');
            exit;
        }
        
        $file = $_FILES['import_file'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = '文件上传失败';
            header('Location: admin.php?action=user&sub=import');
            exit;
        }
        
        $filePath = $file['tmp_name'];
        $fileName = $file['name'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        if (!in_array($fileExt, ['csv'])) {
            $_SESSION['error'] = '只支持CSV格式文件';
            header('Location: admin.php?action=user&sub=import');
            exit;
        }
        
        $importResults = [
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => []
        ];
        
        // 设置最大导入数量限制，防止性能问题
        $maxImportCount = 1000;
        $currentCount = 0;
        
        $roleList = RoleModel::getRoleList();
        
        $row = 0;
        if (($handle = fopen($filePath, 'r')) !== false) {
            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                $row++;
                
                if ($row == 1) {
                    continue;
                }
                
                // 检查是否超过最大导入数量
                $currentCount++;
                if ($currentCount > $maxImportCount) {
                    $importResults['failed']++;
                    $importResults['errors'][] = "第 {$row} 行：已达到最大导入限制 ({$maxImportCount} 条)，剩余数据已跳过";
                    break;
                }
                
                if (count($data) < 4) {
                    $importResults['failed']++;
                    $importResults['errors'][] = "第 {$row} 行：数据格式错误";
                    continue;
                }
                
                $username = trim($data[0]);
                $nickname = trim($data[1]);
                $email = trim($data[2]);
                $password = trim($data[3]);
                $roleName = isset($data[4]) ? trim($data[4]) : '';
                
                if (empty($username) || empty($password)) {
                    $importResults['failed']++;
                    $importResults['errors'][] = "第 {$row} 行：用户名和密码不能为空";
                    continue;
                }
                
                $existingUser = UserModel::getUserByUsername($username);
                if ($existingUser) {
                    $importResults['skipped']++;
                    $importResults['errors'][] = "第 {$row} 行：用户名 '{$username}' 已存在，已跳过";
                    continue;
                }
                
                if (!empty($email)) {
                    $existingEmail = UserModel::getUserByEmail($email);
                    if ($existingEmail) {
                        $importResults['failed']++;
                        $importResults['errors'][] = "第 {$row} 行：邮箱 '{$email}' 已存在";
                        continue;
                    }
                }
                
                $roleId = 3;
                if (!empty($roleName)) {
                    $roleIdMap = array_flip($roleList);
                    if (isset($roleIdMap[$roleName])) {
                        $roleId = $roleIdMap[$roleName];
                        // 禁止分配游客角色（ID=2）和管理员角色（ID=1）给注册用户
                        if ($roleId == 2) {
                            $importResults['failed']++;
                            $importResults['errors'][] = "第 {$row} 行：禁止分配'游客'角色给注册用户，已改为'普通用户'";
                            $roleId = 3;
                        } elseif ($roleId == 1) {
                            $importResults['failed']++;
                            $importResults['errors'][] = "第 {$row} 行：禁止通过导入分配'管理员'角色，已改为'普通用户'";
                            $roleId = 3;
                        }
                    }
                }
                
                $userData = [
                    'username' => $username,
                    'nickname' => !empty($nickname) ? $nickname : $username,
                    'email' => $email,
                    'password' => $password,
                    'role' => $roleId,
                    'status' => 1
                ];
                
                $userId = UserModel::createUser($userData);
                if ($userId) {
                    $importResults['success']++;
                    Log::info('用户管理', '导入用户', "成功导入用户: {$username}", Log::CATEGORY_OPERATION);
                } else {
                    $importResults['failed']++;
                    $importResults['errors'][] = "第 {$row} 行：创建用户 '{$username}' 失败";
                }
            }
            fclose($handle);
        }
        
        $_SESSION['import_results'] = $importResults;
        
        header('Location: admin.php?action=user&sub=import');
        exit;
    }
    
    /**
     * 搜索用户（API）
     * 用于发送系统通知时选择用户
     * 支持用户名、邮箱、用户ID搜索
     */
    public function search() {
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['admin'])) {
            echo json_encode(['success' => false, 'message' => '未登录']);
            return;
        }
        
        $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
        
        if (empty($keyword)) {
            echo json_encode(['success' => true, 'data' => []]);
            return;
        }
        
        $db = Database::getInstance();
        $prefix = Config::get('database')['prefix'];
        
        // 判断是否为数字（用户ID）
        if (is_numeric($keyword)) {
            // 按用户ID精确搜索
            $sql = "SELECT id, username, email FROM {$prefix}user 
                    WHERE id = ? AND status = 1 
                    ORDER BY id DESC LIMIT ?";
            $users = $db->fetchAll($sql, [$keyword, $limit]);
        } else {
            // 按用户名或邮箱模糊搜索
            $sql = "SELECT id, username, email FROM {$prefix}user 
                    WHERE (username LIKE ? OR email LIKE ?) AND status = 1 
                    ORDER BY id DESC LIMIT ?";
            $users = $db->fetchAll($sql, ["%{$keyword}%", "%{$keyword}%", $limit]);
        }
        
        echo json_encode(['success' => true, 'data' => $users]);
    }
}
