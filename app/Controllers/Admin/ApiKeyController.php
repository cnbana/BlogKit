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


// 加载日志类

/**
 * API Key管理控制器
 * 实现API Key的后台管理功能
 */
class ApiKeyController {
    
    /**
     * 构造函数
     */
    public function __construct() {
        // 检查登录状态
        if (!isset($_SESSION['admin'])) {
            header('Location: admin.php?action=login');
            exit;
        }
        
        // 检查权限（简单实现，假设管理员都有配置权限）
        $admin = $_SESSION['admin'];
        if ($admin['role'] < 1) {
            header('Location: admin.php?action=dashboard');
            exit;
        }
    }
    
    /**
     * API Key管理主页面
     * 处理API Key的列表、创建、编辑等所有操作
     */
    public function index() {
        
        // 初始化变量
        $apiKeys = [];
        $apiKey = null;
        $showList = true; // 默认显示列表
        $action = isset($_GET['method']) ? $_GET['method'] : '';
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $plainApiKey = null; // 创建API Key时返回的明文Key
        
        // 处理不同的操作
        switch ($action) {
            case 'create':
                // 显示创建表单
                $showList = false;
                break;
            case 'edit':
                // 显示编辑表单
                $showList = false;
                if ($id > 0) {
                    $apiKey = ApiKeyModel::getById($id);
                    if ($apiKey) {
                        // 格式化过期时间
                        $apiKey['expire_time_formatted'] = '';
                        if (!empty($apiKey['expire_time'])) {
                            $apiKey['expire_time_formatted'] = date('Y-m-d H:i:s', $apiKey['expire_time']);
                        }
                    }
                }
                break;
            case 'save':
                // 处理保存操作
                if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                    $name = $_POST['name'] ?? '';
                $platform = $_POST['platform'] ?? '';
                $description = $_POST['description'] ?? '';
                $permissions = $_POST['permissions'] ?? [];
                    $ipWhitelist = $_POST['ip_whitelist'] ?? '';
                    $domainWhitelist = $_POST['domain_whitelist'] ?? '';
                    $rateLimitEnabled = $_POST['rate_limit_enabled'] ?? 0;
                    $rateLimitCount = $_POST['rate_limit_count'] ?? 100;
                    $rateLimitTime = $_POST['rate_limit_time'] ?? 60;
                    $rateLimitUnit = $_POST['rate_limit_unit'] ?? 'minute';
                    $expireTime = $_POST['expire_time'] ?? '';
                    $customApiKey = $_POST['custom_api_key'] ?? '';
                    $useCustomApiKey = $_POST['use_custom_api_key'] ?? 0;
                    $editId = $_POST['id'] ?? 0;
                    
                    // 验证表单
                    $errors = [];
                    if (empty($name)) {
                        $errors[] = '请输入API Key名称';
                    }
                    
                    // 根据是否启用限制设置值
                    if ($rateLimitEnabled == 1) {
                        if (!is_numeric($rateLimitCount) || $rateLimitCount < 1) {
                            $errors[] = '请输入有效的请求次数限制';
                        }
                        if (!is_numeric($rateLimitTime) || $rateLimitTime < 1) {
                            $errors[] = '请输入有效的限制时间窗口';
                        }
                        // 验证限流时间单位的合法性
                        $validUnits = ['second', 'minute', 'hour', 'day'];
                        if (!in_array($rateLimitUnit, $validUnits)) {
                            $rateLimitUnit = 'minute';
                        }
                    } else {
                        // 不限制时，将次数和时间设为0
                        $rateLimitCount = 0;
                        $rateLimitTime = 0;
                        $rateLimitUnit = 'minute';
                    }

                    // 解析权限配置：文本框中每行一个路径，或以逗号分隔
                    if (!empty($permissions) && is_string($permissions)) {
                        $permissions = array_filter(array_map('trim', preg_split("/[\s,;\n\r]+/", $permissions)));
                    } else {
                        $permissions = [];
                    }
                    
                    // 处理过期时间
                    $expireTimestamp = null;
                    if (!empty($expireTime)) {
                        $expireTimestamp = strtotime($expireTime);
                        if ($expireTimestamp === false || $expireTimestamp <= time()) {
                            $errors[] = '请输入有效的过期时间';
                        }
                    }
                    
                    // 处理自定义API Key（仅在创建时）
                    if ($editId == 0) {
                        if ($useCustomApiKey == 1) {
                            $customApiKey = $_POST['custom_api_key'] ?? '';
                            if (empty($customApiKey)) {
                                $errors[] = '请输入自定义API Key';
                            }
                        } else {
                            $customApiKey = null;
                        }
                    }
                    
                    if (empty($errors)) {
                        if ($editId > 0) {
                            // 更新API Key（不更新API Key本身）
                            $result = ApiKeyModel::update($editId, $name, $platform, $ipWhitelist, $domainWhitelist, (int)$rateLimitCount, (int)$rateLimitTime, $rateLimitUnit, $expireTimestamp, $description, $permissions);
                            if ($result) {
                                Log::info('API Key管理', '更新API Key', '成功更新API Key: ' . $name . ' (ID: ' . $editId . ')', Log::CATEGORY_OPERATION);
                                $_SESSION['message'] = 'API Key更新成功';
                                $_SESSION['message_type'] = 'success';
                            } else {
                                Log::error('API Key管理', '更新API Key', '更新API Key失败: ' . $name . ' (ID: ' . $editId . ')', Log::CATEGORY_OPERATION);
                                $_SESSION['message'] = 'API Key更新失败';
                                $_SESSION['message_type'] = 'error';
                            }
                        } else {
                            // 创建API Key —— 注意第7个参数是 rate_limit_unit（之前默认被硬编码为 'minute'）
                            $result = ApiKeyModel::create($name, $platform, $ipWhitelist, $domainWhitelist, (int)$rateLimitCount, (int)$rateLimitTime, $rateLimitUnit, $expireTimestamp, $description, $permissions, $_SESSION['admin']['id'], $customApiKey);
                            if ($result) {
                                $plainApiKey = $result['api_key'];
                                Log::info('API Key管理', '创建API Key', '成功创建API Key: ' . $name, Log::CATEGORY_OPERATION);
                                $_SESSION['message'] = 'API Key创建成功，请注意保存，仅显示一次！';
                                $_SESSION['message_type'] = 'success';
                            } else {
                                Log::error('API Key管理', '创建API Key', '创建API Key失败: ' . $name, Log::CATEGORY_OPERATION);
                                $_SESSION['message'] = 'API Key创建失败';
                                $_SESSION['message_type'] = 'error';
                            }
                        }
                    } else {
                        $_SESSION['message'] = implode('<br>', $errors);
                        $_SESSION['message_type'] = 'error';
                        // 保存失败，继续显示表单
                        $showList = false;
                        if ($editId > 0) {
                            $apiKey = ApiKeyModel::getById($editId);
                            if ($apiKey) {
                                $apiKey['expire_time_formatted'] = '';
                                if (!empty($apiKey['expire_time'])) {
                                    $apiKey['expire_time_formatted'] = date('Y-m-d H:i:s', $apiKey['expire_time']);
                                }
                            }
                        }
                    }
                }
                $showList = true; // 保存成功后显示列表
                break;
            case 'batch':
                // 处理批量操作
                if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                    $selectedIds = $_POST['selected_ids'] ?? [];
                    $batchAction = $_POST['batch_action'] ?? '';
                    
                    if (empty($selectedIds)) {
                        Log::warning('API Key管理', '批量操作', '请选择要操作的API Key', Log::CATEGORY_OPERATION);
                        $_SESSION['message'] = '请选择要操作的API Key';
                        $_SESSION['message_type'] = 'error';
                    } else {
                        switch ($batchAction) {
                            case 'delete':
                                $result = ApiKeyModel::batchDelete($selectedIds);
                                Log::info('API Key管理', '批量删除API Key', "成功批量删除 {$result} 个API Key", Log::CATEGORY_OPERATION);
                                $_SESSION['message'] = "成功删除 {$result} 个API Key";
                                $_SESSION['message_type'] = 'success';
                                break;
                            case 'activate':
                                $result = ApiKeyModel::batchActivate($selectedIds);
                                Log::info('API Key管理', '批量激活API Key', "成功批量激活 {$result} 个API Key", Log::CATEGORY_OPERATION);
                                $_SESSION['message'] = "成功激活 {$result} 个API Key";
                                $_SESSION['message_type'] = 'success';
                                break;
                            case 'revoke':
                                $result = ApiKeyModel::batchRevoke($selectedIds);
                                Log::info('API Key管理', '批量撤销API Key', "成功批量撤销 {$result} 个API Key", Log::CATEGORY_OPERATION);
                                $_SESSION['message'] = "成功撤销 {$result} 个API Key";
                                $_SESSION['message_type'] = 'success';
                                break;
                            default:
                                Log::warning('API Key管理', '批量操作', '无效的批量操作', Log::CATEGORY_OPERATION);
                                $_SESSION['message'] = '无效的批量操作';
                                $_SESSION['message_type'] = 'error';
                                break;
                        }
                    }
                }
                $showList = true;
                break;
            case 'export':
                // 处理导出操作
                $exportFormat = $_GET['format'] ?? 'csv';
                $apiKeys = ApiKeyModel::getAllApiKeys();
                
                // 准备导出数据
                $exportData = [];
                foreach ($apiKeys as $key) {
                    $exportData[] = [
                        'id' => $key['id'],
                        'name' => $key['name'],
                        'platform' => $key['platform'],
                        'status' => $key['status'],
                        'created_at' => date('Y-m-d H:i:s', $key['created_at']),
                        'expire_time' => $key['expire_time'] ? date('Y-m-d H:i:s', $key['expire_time']) : '永不过期',
                        'rate_limit' => $key['rate_limit_count'] > 0 ? "{$key['rate_limit_count']}/{$key['rate_limit_time']}{$key['rate_limit_unit']}" : '不限制'
                    ];
                }
                
                if ($exportFormat == 'json') {
                    // 导出为JSON
                    header('Content-Type: application/json');
                    header('Content-Disposition: attachment; filename="api_keys_' . date('Y-m-d') . '.json"');
                    echo json_encode($exportData, JSON_PRETTY_PRINT);
                    exit;
                } else {
                    // 导出为CSV
                    header('Content-Type: text/csv; charset=utf-8');
                    header('Content-Disposition: attachment; filename="api_keys_' . date('Y-m-d') . '.csv"');
                    
                    $output = fopen('php://output', 'w');
                    fputcsv($output, array_keys($exportData[0]));
                    
                    foreach ($exportData as $row) {
                        fputcsv($output, $row);
                    }
                    
                    fclose($output);
                    exit;
                }
                break;
            default:
                // 显示列表
                $showList = true;
                break;
        }
        
        // 获取API Key列表（始终获取，用于列表显示）
        $apiKeys = ApiKeyModel::getAllApiKeys();
        
        // 加载视图
        $message = isset($_SESSION['message']) ? $_SESSION['message'] : null;
        $messageType = isset($_SESSION['message_type']) ? $_SESSION['message_type'] : null;
        unset($_SESSION['message'], $_SESSION['message_type']);
        
        // 设置currentAction为config，确保侧边栏"系统设置"菜单项为活动状态
        $currentAction = 'config';
        
        // 系统设置子菜单，与ConfigController保持一致
        $subPages = [
            'basic' => '基本设置',
            'email' => '邮箱配置',
            'user' => '用户设置',
            'article' => '文章设置',
            'comment' => '评论设置',
            'search' => '搜索设置',
            'register' => '注册设置',
            'captcha' => '验证码设置',
            'login' => '登录设置',
            'feature' => '功能开关',
            'rewrite' => '伪静态设置',
            'seo' => 'SEO设置',
            'api' => 'API设置',
            'info' => '系统信息',
            'debug' => '调试设置'
        ];
        
        // 当前子页面，设置为api
        $sub = 'api';
        
        extract([
            'apiKeys' => $apiKeys,
            'apiKey' => $apiKey,
            'showList' => $showList,
            'message' => $message,
            'message_type' => $messageType,
            'plainApiKey' => $plainApiKey,
            'currentAction' => $currentAction,
            'subPages' => $subPages,
            'sub' => $sub
        ]);
        
        include ADMIN_PATH . '/templates/api_key_list.html';
    }
    
    /**
     * 删除API Key
     */
    public function delete() {
        $id = $_GET['id'] ?? 0;
        if (empty($id)) {
            $_SESSION['message'] = '无效的API Key ID';
            $_SESSION['message_type'] = 'error';
            header('Location: admin.php?action=api_key');
            exit;
        }
        
        $apiKey = ApiKeyModel::getById($id);
        $result = ApiKeyModel::delete($id);
        
        if ($result) {
            Log::info('API Key管理', '删除API Key', '成功删除API Key: ' . $apiKey['name'] . ' (ID: ' . $id . ')', Log::CATEGORY_OPERATION);
            $_SESSION['message'] = 'API Key删除成功';
            $_SESSION['message_type'] = 'success';
        } else {
            Log::error('API Key管理', '删除API Key', '删除API Key失败: ID ' . $id, Log::CATEGORY_OPERATION);
            $_SESSION['message'] = 'API Key删除失败';
            $_SESSION['message_type'] = 'error';
        }
        
        header('Location: admin.php?action=api_key');
        exit;
    }
    
    /**
     * 激活API Key
     */
    public function activate() {
        $id = $_GET['id'] ?? 0;
        if (empty($id)) {
            $_SESSION['message'] = '无效的API Key ID';
            $_SESSION['message_type'] = 'error';
            header('Location: admin.php?action=api_key');
            exit;
        }
        
        $apiKey = ApiKeyModel::getById($id);
        $result = ApiKeyModel::activate($id);
        
        if ($result) {
            Log::info('API Key管理', '激活API Key', '成功激活API Key: ' . $apiKey['name'] . ' (ID: ' . $id . ')', Log::CATEGORY_OPERATION);
            $_SESSION['message'] = 'API Key激活成功';
            $_SESSION['message_type'] = 'success';
        } else {
            Log::error('API Key管理', '激活API Key', '激活API Key失败: ID ' . $id, Log::CATEGORY_OPERATION);
            $_SESSION['message'] = 'API Key激活失败';
            $_SESSION['message_type'] = 'error';
        }
        
        header('Location: admin.php?action=api_key');
        exit;
    }
    
    /**
     * 撤销API Key
     */
    public function revoke() {
        $id = $_GET['id'] ?? 0;
        if (empty($id)) {
            $_SESSION['message'] = '无效的API Key ID';
            $_SESSION['message_type'] = 'error';
            header('Location: admin.php?action=api_key');
            exit;
        }
        
        $apiKey = ApiKeyModel::getById($id);
        $result = ApiKeyModel::revoke($id);
        
        if ($result) {
            Log::info('API Key管理', '撤销API Key', '成功撤销API Key: ' . $apiKey['name'] . ' (ID: ' . $id . ')', Log::CATEGORY_OPERATION);
            $_SESSION['message'] = 'API Key撤销成功';
            $_SESSION['message_type'] = 'success';
        } else {
            Log::error('API Key管理', '撤销API Key', '撤销API Key失败: ID ' . $id, Log::CATEGORY_OPERATION);
            $_SESSION['message'] = 'API Key撤销失败';
            $_SESSION['message_type'] = 'error';
        }
        
        header('Location: admin.php?action=api_key');
        exit;
    }
    

}
