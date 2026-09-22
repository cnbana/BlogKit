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

class HookController {
    /**
     * 钩子管理首页
     */
    public function index() {
        // 获取所有可用钩子
        $hooks = $this->getAllHooks();
        
        // 获取已注册的钩子回调
        $registeredHooks = $this->getRegisteredHooks();
        
        // 显示钩子管理页面
        include ADMIN_PATH . '/templates/hook.html';
    }
    
    /**
     * 获取所有可用钩子
     * @return array 钩子列表
     */
    private function getAllHooks() {
        // 从Hook类中获取所有钩子常量
        $hookClass = new ReflectionClass('Hook');
        $constants = $hookClass->getConstants();
        
        $hooks = [];
        foreach ($constants as $name => $value) {
            $hooks[] = [
                'name' => $name,
                'value' => $value,
                'description' => $this->getHookDescription($value)
            ];
        }
        
        return $hooks;
    }
    
    /**
     * 获取钩子描述
     * @param string $hook 钩子名称
     * @return string 钩子描述
     */
    private function getHookDescription($hook) {
        $descriptions = [
            // 用户相关钩子
            'user_login_before' => '用户登录前触发',
            'user_login_after' => '用户登录后触发',
            'user_login_failed' => '用户登录失败时触发',
            'user_logout_before' => '用户登出前触发',
            'user_logout_after' => '用户登出后触发',
            'user_register_before' => '用户注册前触发',
            'user_register_after' => '用户注册后触发',
            'user_profile_update_before' => '用户资料更新前触发',
            'user_profile_update_after' => '用户资料更新后触发',
            'user_password_change_before' => '用户密码修改前触发',
            'user_password_change_after' => '用户密码修改后触发',
            'user_password_reset' => '用户密码重置时触发',
            'user_delete_before' => '用户删除前触发',
            'user_delete_after' => '用户删除后触发',
            'user_ban_before' => '用户封禁前触发',
            'user_ban_after' => '用户封禁后触发',
            'user_unban_before' => '用户解封前触发',
            'user_unban_after' => '用户解封后触发',
            'user_follow_before' => '用户关注前触发',
            'user_follow_after' => '用户关注后触发',
            'user_unfollow_before' => '用户取消关注前触发',
            'user_unfollow_after' => '用户取消关注后触发',
            
            // 文章相关钩子
            'article_create_before' => '文章创建前触发',
            'article_create_after' => '文章创建后触发',
            'article_update_before' => '文章更新前触发',
            'article_update_after' => '文章更新后触发',
            'article_delete_before' => '文章删除前触发',
            'article_delete_after' => '文章删除后触发',
            'article_restore_before' => '文章恢复前触发',
            'article_restore_after' => '文章恢复后触发',
            'article_publish_before' => '文章发布前触发',
            'article_publish_after' => '文章发布后触发',
            'article_view_before' => '文章查看前触发',
            'article_view_after' => '文章查看后触发',
            'article_like_before' => '文章点赞前触发',
            'article_like_after' => '文章点赞后触发',
            'article_unlike_before' => '文章取消点赞前触发',
            'article_unlike_after' => '文章取消点赞后触发',
            'article_favorite_before' => '文章收藏前触发',
            'article_favorite_after' => '文章收藏后触发',
            'article_unfavorite_before' => '文章取消收藏前触发',
            'article_unfavorite_after' => '文章取消收藏后触发',
            'article_search_before' => '文章搜索前触发',
            'article_search_after' => '文章搜索后触发',
            
            // 评论相关钩子
            'comment_add_before' => '评论添加前触发',
            'comment_add_after' => '评论添加后触发',
            'comment_update_before' => '评论更新前触发',
            'comment_update_after' => '评论更新后触发',
            'comment_delete_before' => '评论删除前触发',
            'comment_delete_after' => '评论删除后触发',
            'comment_approve_before' => '评论审核前触发',
            'comment_approve_after' => '评论审核后触发',
            'comment_reject_before' => '评论拒绝前触发',
            'comment_reject_after' => '评论拒绝后触发',
            'comment_like_before' => '评论点赞前触发',
            'comment_like_after' => '评论点赞后触发',
            'comment_unlike_before' => '评论取消点赞前触发',
            'comment_unlike_after' => '评论取消点赞后触发',
            'comment_reply_before' => '评论回复前触发',
            'comment_reply_after' => '评论回复后触发',
            
            // 页面相关钩子
            'page_create_before' => '页面创建前触发',
            'page_create_after' => '页面创建后触发',
            'page_update_before' => '页面更新前触发',
            'page_update_after' => '页面更新后触发',
            'page_delete_before' => '页面删除前触发',
            'page_delete_after' => '页面删除后触发',
            'page_view_before' => '页面查看前触发',
            'page_view_after' => '页面查看后触发',
            
            // 分类相关钩子
            'category_create_before' => '分类创建前触发',
            'category_create_after' => '分类创建后触发',
            'category_update_before' => '分类更新前触发',
            'category_update_after' => '分类更新后触发',
            'category_delete_before' => '分类删除前触发',
            'category_delete_after' => '分类删除后触发',
            
            // 标签相关钩子
            'tag_create_before' => '标签创建前触发',
            'tag_create_after' => '标签创建后触发',
            'tag_update_before' => '标签更新前触发',
            'tag_update_after' => '标签更新后触发',
            'tag_delete_before' => '标签删除前触发',
            'tag_delete_after' => '标签删除后触发',
            
            // 系统相关钩子
            'system_init' => '系统初始化时触发',
            'system_shutdown' => '系统关闭时触发',
            'system_cache_clear' => '系统缓存清除时触发',
            'system_backup_before' => '系统备份前触发',
            'system_backup_after' => '系统备份后触发',
            'system_restore_before' => '系统恢复前触发',
            'system_restore_after' => '系统恢复后触发',
            'system_config_update' => '系统配置更新时触发',
            'system_error' => '系统错误时触发',
            'system_cron' => '系统定时任务触发',
            
            // 文件上传相关钩子
            'file_upload_before' => '文件上传前触发',
            'file_upload_after' => '文件上传后触发',
            'file_upload_error' => '文件上传错误时触发',
            'file_delete_before' => '文件删除前触发',
            'file_delete_after' => '文件删除后触发',
            
            // 通知相关钩子
            'send_notification' => '发送通知时触发',
            'notification_send_before' => '通知发送前触发',
            'notification_send_after' => '通知发送后触发',
            'notification_read' => '通知读取时触发',
            
            // 邮件相关钩子
            'email_send_before' => '邮件发送前触发',
            'email_send_after' => '邮件发送后触发',
            'email_send_error' => '邮件发送错误时触发',
            
            // 短信相关钩子
            'sms_send_before' => '短信发送前触发',
            'sms_send_after' => '短信发送后触发',
            'sms_send_error' => '短信发送错误时触发',
            
            // 管理后台相关钩子
            'admin_login_before' => '后台登录前触发',
            'admin_login_after' => '后台登录后触发',
            'admin_login_failed' => '后台登录失败时触发',
            'admin_logout_before' => '后台登出前触发',
            'admin_logout_after' => '后台登出后触发',
            'admin_menu_render' => '后台菜单渲染时触发',
            'admin_dashboard_render' => '后台仪表盘渲染时触发',
            'admin_action_before' => '后台操作前触发',
            'admin_action_after' => '后台操作后触发',
            
            // API相关钩子
            'api_request_before' => 'API请求前触发',
            'api_request_after' => 'API请求后触发',
            'api_response_before' => 'API响应前触发',
            'api_response_after' => 'API响应后触发',
            'api_error' => 'API错误时触发',
            
            // 模板相关钩子
            'template_render_before' => '模板渲染前触发',
            'template_render_after' => '模板渲染后触发',
            'template_assign' => '模板变量赋值时触发',
            
            // 数据库相关钩子
            'database_query_before' => '数据库查询前触发',
            'database_query_after' => '数据库查询后触发',
            'database_error' => '数据库错误时触发',
            
            // 安全相关钩子
            'security_check' => '安全检查时触发',
            'security_breach' => '安全 breach 时触发',
            'security_login_attempt' => '安全登录尝试时触发',
            
            // SEO相关钩子
            'seo_meta_generate' => 'SEO元数据生成时触发',
            'seo_sitemap_generate' => 'SEO站点地图生成时触发',
            'seo_robots_generate' => 'SEO robots.txt生成时触发',
            
            // 主题相关钩子
            'theme_activate_before' => '主题激活前触发',
            'theme_activate_after' => '主题激活后触发',
            'theme_deactivate_before' => '主题停用前触发',
            'theme_deactivate_after' => '主题停用时触发',
            'theme_settings_update' => '主题设置更新时触发',
            // theme_preview 钩子已随主题预览功能移除而删除
            'theme_customize' => '主题定制时触发',
            
            // 插件相关钩子
            'plugin_install_before' => '插件安装前触发',
            'plugin_install_after' => '插件安装后触发',
            'plugin_uninstall_before' => '插件卸载前触发',
            'plugin_uninstall_after' => '插件卸载后触发',
            'plugin_activate_before' => '插件激活前触发',
            'plugin_activate_after' => '插件激活后触发',
            'plugin_deactivate_before' => '插件停用前触发',
            'plugin_deactivate_after' => '插件停用时触发',
            'plugin_config_update' => '插件配置更新时触发',
            
            // 会员相关钩子
            'membership_level_change' => '会员等级变更时触发',
            'membership_expire' => '会员到期时触发',
            'membership_renew' => '会员续费时触发',
            
            // 支付相关钩子
            'payment_process_before' => '支付处理前触发',
            'payment_process_after' => '支付处理后触发',
            'payment_success' => '支付成功时触发',
            'payment_failed' => '支付失败时触发',
            'payment_refund' => '支付退款时触发',
            
            // 搜索相关钩子
            'search_query_before' => '搜索查询前触发',
            'search_query_after' => '搜索查询后触发',
            'search_results_filter' => '搜索结果过滤时触发',
            
            // 缓存相关钩子
            'cache_clear_before' => '缓存清除前触发',
            'cache_clear_after' => '缓存清除后触发',
            'cache_get' => '获取缓存时触发',
            'cache_set' => '设置缓存时触发',
            
            // 性能相关钩子
            'performance_optimize' => '性能优化时触发',
            'resource_load' => '资源加载时触发',
            'page_load_complete' => '页面加载完成时触发',
            
            // 多语言相关钩子
            'language_switch' => '语言切换时触发',
            'translation_load' => '翻译加载时触发',
            'translation_filter' => '翻译过滤时触发',
            
            // 社交相关钩子
            'social_share' => '社交分享时触发',
            'social_login' => '社交登录时触发',
            'social_connect' => '社交连接时触发',
            
            // 统计相关钩子
            'statistics_collect' => '统计数据收集时触发',
            'statistics_report' => '统计报表生成时触发'
        ];
        
        return $descriptions[$hook] ?? '无描述';
    }
    
    /**
     * 获取已注册的钩子回调
     * @return array 已注册的钩子回调
     */
    private function getRegisteredHooks() {
        if (!class_exists('Plugin')) {
            return [];
        }
        
        $plugin = Plugin::getInstance();
        $reflection = new ReflectionClass($plugin);
        $property = $reflection->getProperty('hooks');
        $property->setAccessible(true);
        
        return $property->getValue($plugin);
    }
    
    /**
     * 测试钩子
     */
    public function test() {
        $hook = $_GET['hook'];
        
        // 触发钩子测试
        $startTime = microtime(true);
        $result = Hook::trigger($hook, ['test' => 'data']);
        $executionTime = round((microtime(true) - $startTime) * 1000, 2);
        
        // 检查是否有注册的钩子回调
        $hasCallbacks = Hook::hasCallback($hook);
        
        // 生成测试结果信息
        $testResult = [
            'hook' => $hook,
            'has_callbacks' => $hasCallbacks,
            'execution_time' => $executionTime,
            'result' => $result,
            'message' => $hasCallbacks ? '钩子测试成功，执行了注册的回调函数' : '钩子测试成功，但没有注册的回调函数'
        ];
        
        // 记录测试日志
        Log::info('成功测试钩子: ' . $hook, Log::CATEGORY_OPERATION, ['hook' => $hook, 'result' => $result]);
        
        // 显示测试结果
        include ADMIN_PATH . '/templates/hook_test.html';
    }
    
    /**
     * 查看钩子日志
     */
    public function logs() {
        // 获取钩子执行日志
        $logs = $this->getHookLogs();
        
        // 显示钩子日志页面
        include ADMIN_PATH . '/templates/hook_logs.html';
    }
    
    /**
     * 获取钩子执行日志
     * @return array 日志列表
     */
    private function getHookLogs() {
        require_once CORE_PATH . '/lib/Log.php';
        Log::init();

        $result = Log::getLogs(['category' => 'hook'], 1, 500);
        $logs = [];

        foreach ($result['logs'] as $log) {
            $hook = '';
            if (!empty($log['hook'])) {
                $hook = $log['hook'];
            } elseif (preg_match('/Hook executed: (.+)$/', $log['message'] ?? '', $m)) {
                $hook = trim($m[1]);
            } elseif (preg_match('/Hook: (.+?) -/', $log['message'] ?? '', $m)) {
                $hook = trim($m[1]);
            }

            $ctx = $log['context'] ?? [];
            if (!is_array($ctx)) {
                $ctx = json_decode($ctx, true) ?: [];
            }

            $logs[] = [
                'time' => is_numeric($log['time']) ? (int)$log['time'] : strtotime($log['time']),
                'hook' => $hook,
                'execution_time' => $ctx['execution_time'] ?? ($log['execution_time'] ?? 0),
                'status' => $ctx['status'] ?? ($log['status'] ?? 'success'),
                'error' => $ctx['error'] ?? ($log['error'] ?? ''),
                'data' => $ctx['data'] ?? ($log['data'] ?? null),
                'result' => $ctx['result'] ?? ($log['result'] ?? null),
            ];
        }

        return $logs;
    }
    
    /**
     * 清除钩子日志
     */
    public function clear_logs() {
        $logFile = Log::getLogDir() . '/hook.log';
        if (file_exists($logFile)) {
            unlink($logFile);
            Log::info('钩子管理', '清除日志', '成功清除钩子执行日志', Log::CATEGORY_OPERATION);
        } else {
            Log::warning('钩子管理', '清除日志', '钩子日志文件不存在', Log::CATEGORY_OPERATION);
        }
        
        // 重定向回日志页面
        header('Location: admin.php?action=hook&sub=logs');
        exit;
    }
    
    /**
     * 批量测试钩子
     */
    public function batchTest() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $hooks = $_POST['hooks'] ?? [];
            $testResults = [];
            
            foreach ($hooks as $hook) {
                $startTime = microtime(true);
                $result = Hook::trigger($hook, ['test' => 'data']);
                $executionTime = round((microtime(true) - $startTime) * 1000, 2);
                $hasCallbacks = Hook::hasCallback($hook);
                
                $testResults[] = [
                    'hook' => $hook,
                    'has_callbacks' => $hasCallbacks,
                    'execution_time' => $executionTime,
                    'result' => $result,
                    'message' => $hasCallbacks ? '钩子测试成功，执行了注册的回调函数' : '钩子测试成功，但没有注册的回调函数'
                ];
            }
            
            // 显示批量测试结果
            include ADMIN_PATH . '/templates/hook_batch_test.html';
        } else {
            // 获取所有可用钩子
            $hooks = $this->getAllHooks();
            
            // 显示批量测试页面
            include ADMIN_PATH . '/templates/hook_batch_test.html';
        }
    }
    
    /**
     * 导出钩子日志
     */
    public function export_logs() {
        // 获取钩子执行日志
        $logs = $this->getHookLogs();
        
        // 导出为CSV格式
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=hook_logs_' . date('Ymd') . '.csv');
        
        $output = fopen('php://output', 'w');
        
        // 写入表头
        fputcsv($output, ['时间', '钩子名称', '执行时间(ms)', '状态', '错误信息', '传递数据', '执行结果']);
        
        // 写入数据
        foreach ($logs as $log) {
            fputcsv($output, [
                date('Y-m-d H:i:s', $log['time']),
                $log['hook'],
                $log['execution_time'],
                $log['status'],
                $log['error'] ?? '',
                json_encode($log['data'] ?? []),
                $log['result'] ?? ''
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * 钩子详情
     */
    public function info() {
        $hook = $_GET['hook'];
        
        // 获取钩子信息
        $hookInfo = null;
        $hooks = $this->getAllHooks();
        foreach ($hooks as $h) {
            if ($h['value'] == $hook) {
                $hookInfo = $h;
                break;
            }
        }
        
        if (!$hookInfo) {
            $this->showMessage('钩子不存在', 'admin.php?action=hook');
            return;
        }
        
        // 获取已注册的钩子回调
        $registeredHooks = $this->getRegisteredHooks();
        $hookCallbacks = isset($registeredHooks[$hook]) ? $registeredHooks[$hook] : [];
        
        // 显示钩子详情页面
        include ADMIN_PATH . '/templates/hook_info.html';
    }
    
    /**
     * 处理子操作
     */
    public function handleSubAction() {
        if (isset($_GET['sub'])) {
            $subAction = $_GET['sub'];
            if (method_exists($this, $subAction)) {
                $this->$subAction();
            } else {
                $this->showMessage('操作不存在', 'admin.php?action=hook');
            }
        } else {
            $this->index();
        }
    }
    
    /**
     * 显示消息并跳转
     * @param string $message 消息内容
     * @param string $redirect 跳转地址
     */
    private function showMessage($message, $redirect) {
        echo "<script>alert('{$message}'); window.location.href = '{$redirect}';</script>";
        exit;
    }
}