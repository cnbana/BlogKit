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
 * BlogKit 钩子系统扩展
 * 提供完整的钩子定义和触发器
 * 
 * 使用方法：
 * 1. 在需要触发钩子的地方调用 Hook::trigger('hook_name', $data)
 * 2. 插件中注册钩子：Plugin::getInstance()->addHook('hook_name', $callback)
 */

class Hook {
    
    /**
     * 用户相关钩子
     */
    const USER_LOGIN_BEFORE = 'user_login_before';
    const USER_LOGIN_AFTER = 'user_login_after';
    const USER_LOGIN_FAILED = 'user_login_failed';
    const USER_LOGOUT_BEFORE = 'user_logout_before';
    const USER_LOGOUT_AFTER = 'user_logout_after';
    const USER_REGISTER_BEFORE = 'user_register_before';
    const USER_REGISTER_AFTER = 'user_register_after';
    const USER_PROFILE_UPDATE_BEFORE = 'user_profile_update_before';
    const USER_PROFILE_UPDATE_AFTER = 'user_profile_update_after';
    const USER_PASSWORD_CHANGE_BEFORE = 'user_password_change_before';
    const USER_PASSWORD_CHANGE_AFTER = 'user_password_change_after';
    const USER_PASSWORD_RESET = 'user_password_reset';
    const USER_DELETE_BEFORE = 'user_delete_before';
    const USER_DELETE_AFTER = 'user_delete_after';
    const USER_BAN_BEFORE = 'user_ban_before';
    const USER_BAN_AFTER = 'user_ban_after';
    const USER_UNBAN_BEFORE = 'user_unban_before';
    const USER_UNBAN_AFTER = 'user_unban_after';
    const USER_FOLLOW_BEFORE = 'user_follow_before';
    const USER_FOLLOW_AFTER = 'user_follow_after';
    const USER_UNFOLLOW_BEFORE = 'user_unfollow_before';
    const USER_UNFOLLOW_AFTER = 'user_unfollow_after';
    
    /**
     * 文章相关钩子
     */
    const ARTICLE_CREATE_BEFORE = 'article_create_before';
    const ARTICLE_CREATE_AFTER = 'article_create_after';
    const ARTICLE_UPDATE_BEFORE = 'article_update_before';
    const ARTICLE_UPDATE_AFTER = 'article_update_after';
    const ARTICLE_DELETE_BEFORE = 'article_delete_before';
    const ARTICLE_DELETE_AFTER = 'article_delete_after';
    const ARTICLE_RESTORE_BEFORE = 'article_restore_before';
    const ARTICLE_RESTORE_AFTER = 'article_restore_after';
    const ARTICLE_PUBLISH_BEFORE = 'article_publish_before';
    const ARTICLE_PUBLISH_AFTER = 'article_publish_after';
    const ARTICLE_VIEW_BEFORE = 'article_view_before';
    const ARTICLE_VIEW_AFTER = 'article_view_after';
    const ARTICLE_LIKE_BEFORE = 'article_like_before';
    const ARTICLE_LIKE_AFTER = 'article_like_after';
    const ARTICLE_UNLIKE_BEFORE = 'article_unlike_before';
    const ARTICLE_UNLIKE_AFTER = 'article_unlike_after';
    const ARTICLE_FAVORITE_BEFORE = 'article_favorite_before';
    const ARTICLE_FAVORITE_AFTER = 'article_favorite_after';
    const ARTICLE_UNFAVORITE_BEFORE = 'article_unfavorite_before';
    const ARTICLE_UNFAVORITE_AFTER = 'article_unfavorite_after';
    const ARTICLE_SEARCH_BEFORE = 'article_search_before';
    const ARTICLE_SEARCH_AFTER = 'article_search_after';
    
    /**
     * 评论相关钩子
     */
    const COMMENT_ADD_BEFORE = 'comment_add_before';
    const COMMENT_ADD_AFTER = 'comment_add_after';
    const COMMENT_UPDATE_BEFORE = 'comment_update_before';
    const COMMENT_UPDATE_AFTER = 'comment_update_after';
    const COMMENT_DELETE_BEFORE = 'comment_delete_before';
    const COMMENT_DELETE_AFTER = 'comment_delete_after';
    const COMMENT_APPROVE_BEFORE = 'comment_approve_before';
    const COMMENT_APPROVE_AFTER = 'comment_approve_after';
    const COMMENT_REJECT_BEFORE = 'comment_reject_before';
    const COMMENT_REJECT_AFTER = 'comment_reject_after';
    const COMMENT_LIKE_BEFORE = 'comment_like_before';
    const COMMENT_LIKE_AFTER = 'comment_like_after';
    const COMMENT_UNLIKE_BEFORE = 'comment_unlike_before';
    const COMMENT_UNLIKE_AFTER = 'comment_unlike_after';
    const COMMENT_REPLY_BEFORE = 'comment_reply_before';
    const COMMENT_REPLY_AFTER = 'comment_reply_after';
    
    /**
     * 页面相关钩子
     */
    const PAGE_CREATE_BEFORE = 'page_create_before';
    const PAGE_CREATE_AFTER = 'page_create_after';
    const PAGE_UPDATE_BEFORE = 'page_update_before';
    const PAGE_UPDATE_AFTER = 'page_update_after';
    const PAGE_DELETE_BEFORE = 'page_delete_before';
    const PAGE_DELETE_AFTER = 'page_delete_after';
    const PAGE_VIEW_BEFORE = 'page_view_before';
    const PAGE_VIEW_AFTER = 'page_view_after';
    
    /**
     * 分类相关钩子
     */
    const CATEGORY_CREATE_BEFORE = 'category_create_before';
    const CATEGORY_CREATE_AFTER = 'category_create_after';
    const CATEGORY_UPDATE_BEFORE = 'category_update_before';
    const CATEGORY_UPDATE_AFTER = 'category_update_after';
    const CATEGORY_DELETE_BEFORE = 'category_delete_before';
    const CATEGORY_DELETE_AFTER = 'category_delete_after';
    
    /**
     * 标签相关钩子
     */
    const TAG_CREATE_BEFORE = 'tag_create_before';
    const TAG_CREATE_AFTER = 'tag_create_after';
    const TAG_UPDATE_BEFORE = 'tag_update_before';
    const TAG_UPDATE_AFTER = 'tag_update_after';
    const TAG_DELETE_BEFORE = 'tag_delete_before';
    const TAG_DELETE_AFTER = 'tag_delete_after';
    
    /**
     * 系统相关钩子
     */
    const SYSTEM_INIT = 'system_init';
    const SYSTEM_SHUTDOWN = 'system_shutdown';
    const SYSTEM_CACHE_CLEAR = 'system_cache_clear';
    const SYSTEM_BACKUP_BEFORE = 'system_backup_before';
    const SYSTEM_BACKUP_AFTER = 'system_backup_after';
    const SYSTEM_RESTORE_BEFORE = 'system_restore_before';
    const SYSTEM_RESTORE_AFTER = 'system_restore_after';
    const SYSTEM_CONFIG_UPDATE = 'system_config_update';
    const ADMIN_CONFIG_COLLECT_CUSTOM = 'admin_config_collect_custom'; // 后台保存配置时：插件可在此把自定义字段注入 $configData
    const SYSTEM_ERROR = 'system_error';
    const SYSTEM_CRON = 'system_cron';
    
    /**
     * 文件上传相关钩子
     */
    const FILE_UPLOAD_BEFORE = 'file_upload_before';
    const FILE_UPLOAD_AFTER = 'file_upload_after';
    const FILE_UPLOAD_ERROR = 'file_upload_error';
    const FILE_DELETE_BEFORE = 'file_delete_before';
    const FILE_DELETE_AFTER = 'file_delete_after';
    
    /**
     * 通知相关钩子
     */
    const NOTIFICATION_SEND = 'send_notification';
    const NOTIFICATION_SEND_BEFORE = 'notification_send_before';
    const NOTIFICATION_SEND_AFTER = 'notification_send_after';
    const NOTIFICATION_READ = 'notification_read';
    
    /**
     * 邮件相关钩子
     */
    const EMAIL_SEND_BEFORE = 'email_send_before';
    const EMAIL_SEND_AFTER = 'email_send_after';
    const EMAIL_SEND_ERROR = 'email_send_error';
    
    /**
     * 短信相关钩子
     */
    const SMS_SEND_BEFORE = 'sms_send_before';
    const SMS_SEND_AFTER = 'sms_send_after';
    const SMS_SEND_ERROR = 'sms_send_error';
    
    /**
     * 管理后台相关钩子
     */
    const ADMIN_LOGIN_BEFORE = 'admin_login_before';
    const ADMIN_LOGIN_AFTER = 'admin_login_after';
    const ADMIN_LOGIN_FAILED = 'admin_login_failed';
    const ADMIN_LOGOUT_BEFORE = 'admin_logout_before';
    const ADMIN_LOGOUT_AFTER = 'admin_logout_after';
    const ADMIN_MENU_RENDER = 'admin_menu_render';
    const ADMIN_DASHBOARD_RENDER = 'admin_dashboard_render';
    const ADMIN_ACTION_BEFORE = 'admin_action_before';
    const ADMIN_ACTION_AFTER = 'admin_action_after';
    
    /**
     * API相关钩子
     */
    const API_REQUEST_BEFORE = 'api_request_before';
    const API_REQUEST_AFTER = 'api_request_after';
    const API_RESPONSE_BEFORE = 'api_response_before';
    const API_RESPONSE_AFTER = 'api_response_after';
    const API_ERROR = 'api_error';
    
    /**
     * 模板相关钩子
     */
    const TEMPLATE_RENDER_BEFORE = 'template_render_before';
    const TEMPLATE_RENDER_AFTER = 'template_render_after';
    const TEMPLATE_ASSIGN = 'template_assign';
    
    /**
     * 数据库相关钩子
     */
    const DATABASE_QUERY_BEFORE = 'database_query_before';
    const DATABASE_QUERY_AFTER = 'database_query_after';
    const DATABASE_ERROR = 'database_error';
    
    /**
     * 安全相关钩子
     */
    const SECURITY_CHECK = 'security_check';
    const SECURITY_BREACH = 'security_breach';
    const SECURITY_LOGIN_ATTEMPT = 'security_login_attempt';
    
    /**
     * SEO相关钩子
     */
    const SEO_META_GENERATE = 'seo_meta_generate';
    const SEO_SITEMAP_GENERATE = 'seo_sitemap_generate';
    const SEO_ROBOTS_GENERATE = 'seo_robots_generate';
    
    /**
     * 主题相关钩子
     */
    const THEME_ACTIVATE_BEFORE = 'theme_activate_before';
    const THEME_ACTIVATE_AFTER = 'theme_activate_after';
    const THEME_DEACTIVATE_BEFORE = 'theme_deactivate_before';
    const THEME_DEACTIVATE_AFTER = 'theme_deactivate_after';
    const THEME_SETTINGS_UPDATE = 'theme_settings_update';
    // 说明：THEME_PREVIEW（主题预览）钩子已随主题预览功能移除而删除
    const THEME_CUSTOMIZE = 'theme_customize';
    
    /**
     * 插件相关钩子
     */
    const PLUGIN_INSTALL_BEFORE = 'plugin_install_before';
    const PLUGIN_INSTALL_AFTER = 'plugin_install_after';
    const PLUGIN_UNINSTALL_BEFORE = 'plugin_uninstall_before';
    const PLUGIN_UNINSTALL_AFTER = 'plugin_uninstall_after';
    const PLUGIN_ACTIVATE_BEFORE = 'plugin_activate_before';
    const PLUGIN_ACTIVATE_AFTER = 'plugin_activate_after';
    const PLUGIN_DEACTIVATE_BEFORE = 'plugin_deactivate_before';
    const PLUGIN_DEACTIVATE_AFTER = 'plugin_deactivate_after';
    const PLUGIN_CONFIG_UPDATE = 'plugin_config_update';
    
    /**
     * 会员相关钩子
     */
    const MEMBERSHIP_LEVEL_CHANGE = 'membership_level_change';
    const MEMBERSHIP_EXPIRE = 'membership_expire';
    const MEMBERSHIP_RENEW = 'membership_renew';
    
    /**
     * 支付相关钩子
     */
    const PAYMENT_PROCESS_BEFORE = 'payment_process_before';
    const PAYMENT_PROCESS_AFTER = 'payment_process_after';
    const PAYMENT_SUCCESS = 'payment_success';
    const PAYMENT_FAILED = 'payment_failed';
    const PAYMENT_REFUND = 'payment_refund';
    
    /**
     * 搜索相关钩子
     */
    const SEARCH_QUERY_BEFORE = 'search_query_before';
    const SEARCH_QUERY_AFTER = 'search_query_after';
    const SEARCH_RESULTS_FILTER = 'search_results_filter';
    
    /**
     * 缓存相关钩子
     */
    const CACHE_CLEAR_BEFORE = 'cache_clear_before';
    const CACHE_CLEAR_AFTER = 'cache_clear_after';
    const CACHE_GET = 'cache_get';
    const CACHE_SET = 'cache_set';
    
    /**
     * 性能相关钩子
     */
    const PERFORMANCE_OPTIMIZE = 'performance_optimize';
    const RESOURCE_LOAD = 'resource_load';
    const PAGE_LOAD_COMPLETE = 'page_load_complete';
    
    /**
     * 多语言相关钩子
     */
    const LANGUAGE_SWITCH = 'language_switch';
    const TRANSLATION_LOAD = 'translation_load';
    const TRANSLATION_FILTER = 'translation_filter';
    
    /**
     * 社交相关钩子
     */
    const SOCIAL_SHARE = 'social_share';
    const SOCIAL_LOGIN = 'social_login';
    const SOCIAL_CONNECT = 'social_connect';
    
    /**
     * 统计相关钩子
     */
    const STATISTICS_COLLECT = 'statistics_collect';
    const STATISTICS_REPORT = 'statistics_report';
    
    /**
     * 触发钩子
     * @param string $hookName 钩子名称
     * @param mixed $data 传递给钩子的数据
     * @return string 钩子执行结果
     */
    public static function trigger($hookName, $data = null) {
        $startTime = microtime(true);
        $status = 'success';
        $result = '';
        $error = '';
        
        try {
            if (class_exists('Plugin')) {
                $result = Plugin::triggerHook($hookName, $data);
            }
        } catch (Exception $e) {
            $status = 'error';
            $error = $e->getMessage();
        }
        
        // 记录钩子执行日志
        self::logHookExecution($hookName, $startTime, $status, $error, $data, $result);
        
        return $result;
    }
    
    /**
     * 记录钩子执行日志
     * @param string $hookName 钩子名称
     * @param float $startTime 开始时间
     * @param string $status 执行状态
     * @param string $error 错误信息
     * @param mixed $data 传递的数据
     * @param mixed $result 执行结果
     */
    private static function logHookExecution($hookName, $startTime, $status, $error, $data, $result) {
        // 检查日志系统是否启用
        require_once CORE_PATH . '/lib/Log.php';
        Log::init();
        if (!isset(Log::$config['enabled']) || !Log::$config['enabled']) {
            return;
        }
        
        // 检查钩子分类是否启用
        if (isset(Log::$config['enabled_categories']) && Log::$config['enabled_categories'] !== 'all' && !in_array('hook', Log::$config['enabled_categories'])) {
            return;
        }
        
        $executionTime = round((microtime(true) - $startTime) * 1000, 2);
        
        // 使用 Log 类记录钩子执行日志（通用日志）
        Log::info('Hook executed: ' . $hookName, 'hook', [
            'execution_time' => $executionTime,
            'status' => $status,
            'error' => $error,
            'data' => $data,
            'result' => $result
        ]);
    }
    
    /**
     * 触发钩子（带返回值）
     * @param string $hookName 钩子名称
     * @param mixed $data 传递给钩子的数据
     * @return array 所有钩子回调的返回值数组
     */
    public static function triggerWithReturn($hookName, $data = null) {
        $results = [];
        if (class_exists('Plugin')) {
            $plugin = Plugin::getInstance();
            $hooks = $plugin->getHooks($hookName);
            
            if ($hooks) {
                foreach ($hooks as $priority => $callbacks) {
                    foreach ($callbacks as $callback) {
                        $results[] = call_user_func($callback, $data);
                    }
                }
            }
        }
        return $results;
    }
    
    /**
     * 过滤数据
     * @param string $hookName 钩子名称
     * @param mixed $data 要过滤的数据
     * @return mixed 过滤后的数据
     */
    public static function filter($hookName, $data) {
        if (class_exists('Plugin')) {
            $plugin = Plugin::getInstance();
            $hooks = $plugin->getHooks($hookName);
            
            if ($hooks) {
                foreach ($hooks as $priority => $callbacks) {
                    foreach ($callbacks as $callback) {
                        $data = call_user_func($callback, $data);
                    }
                }
            }
        }
        return $data;
    }
    
    /**
     * 检查钩子是否有注册的回调
     * @param string $hookName 钩子名称
     * @return bool 是否有回调
     */
    public static function hasCallback($hookName) {
        if (class_exists('Plugin')) {
            $plugin = Plugin::getInstance();
            $hooks = $plugin->getHooks($hookName);
            return !empty($hooks);
        }
        return false;
    }
}
