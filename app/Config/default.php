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
 * 系统配置默认值 — 唯一权威来源
 * 
 * 所有模块的配置默认值在此集中定义，供以下消费者统一引用：
 *   - ConfigController 配置表单渲染
 *   - install.sql 安装脚本（由脚本动态读取）
 *   - ConfigService 保存时合并默认值
 * 
 * 格式说明：
 *   配置项名 => 默认值
 * 
 * 使用方式：
 *   $defaults = require APP_PATH . '/Config/default.php';
 */

return [
    // ========== 基本设置 ==========
    'site_name'                          => 'BlogKit',
    'site_description'                   => 'A simple Blog System',
    'site_keywords'                      => 'BlogKit,博客系统,轻量级博客,个人网站,内容管理系统,CMS系统,建站工具,博客系统推荐,博客管理系统,个人博客搭建,博客网站建设',
    'site_url'                           => '',
    'site_logo'                          => '',
    'logo_display_mode'                  => 'auto',
    'custom_header'                     => '',
    'custom_footer'                     => '',
    'default_theme'                      => 'default',
    'site_status'                        => 'open',
    'site_closed_message'                => '网站暂时关闭，请稍后再来。',
    'site_timezone'                      => 'Asia/Shanghai',
    'site_charset'                       => 'UTF-8',
    'system_version'                     => '1.0.0',
    'pagination_count'                   => 10,
    'pagination_followers_count'         => 10,
    'pagination_following_count'         => 10,
    'pagination_comments_count'          => 10,
    'pagination_articles_count'          => 10,
    'pagination_favorites_count'         => 10,
    'pagination_likes_count'             => 10,
    'pagination_messages_count'          => 10,
    'pagination_history_count'           => 10,
    'pagination_article_comments_count'  => 10,
    'pagination_search_count'            => 10,
    'pagination_tag_count'               => 10,
    'admin_pagination_count'             => 20,
    'install_time'                       => 0,
    'last_update'                        => 0,
    'site_favicon'                       => '',

    // ========== 版权与品牌 ==========
    'copyright_start_year'               => '',
    'copyright_owner'                    => '',

    // ========== ICP备案 ==========
    'icp_number'                         => '',
    'icp_link'                           => 'https://beian.miit.gov.cn/',
    'icp_display'                        => 0,

    // ========== 公安备案 ==========
    'gongan_number'                      => '',
    'gongan_link'                        => 'https://www.beian.gov.cn/',
    'gongan_display'                     => 0,

    // ========== 隐私与协议 ==========
    'privacy_policy_url'                 => '',
    'terms_of_service_url'               => '',

    // ========== 维护模式 ==========
    'maintenance_mode'                   => 0,
    'maintenance_message'                => '网站正在维护中，请稍后再来。',

    // ========== 邮箱配置 ==========
    'email_smtp_host'                    => '',
    'email_smtp_port'                    => 465,
    'email_smtp_username'                => '',
    'email_smtp_password'                => '',
    'email_smtp_encryption'              => 'ssl',
    'email_from_address'                 => '',
    'email_from_name'                    => '网站名称',

    // ========== 用户设置 ==========
    'user_registration'                  => 1,
    'profile_avatar_enabled'             => 1,

    // ========== 评论设置 ==========
    'comment_enabled'                    => 1,
    'comment_moderation'                 => 0,
    'comment_auto_approve'               => 0,
    'comment_max_length'                 => 1000,
    'comment_min_length'                 => 1,
    'comment_rate_limit_enabled'         => 1,
    'comment_rate_limit_seconds'         => 10,
    'comment_dedup_enabled'              => 1,
    'comment_keyword_filter_enabled'     => 1,
    'comment_blocked_keywords'           => '',
    'comment_link_check_enabled'         => 1,
    'comment_link_action'                => 'moderate',
    'comment_ip_rate_limit_enabled'      => 0,
    'comment_ip_rate_limit_count'        => 20,
    'comment_ip_rate_limit_window'       => 3600,

    // ========== RSS设置 ==========
    'rss_enabled'                        => 1,
    'rss_item_count'                     => 20,
    'rss_cache_duration'                 => 3600,
    'rss_feed_type'                      => 'excerpt',
    'rss_language'                       => 'zh-CN',

    // ========== 注册设置 ==========
    'register_force_email_verify'        => 0,
    'register_ip_limit_time'             => 60,
    'register_ip_limit_count'            => 3,
    'register_username_ban_pure_number'  => 0,
    'register_username_ban_simple_string'=> 0,
    'register_username_min_length'       => 3,
    'register_username_max_length'       => 20,
    'register_username_ban_keywords'     => '管理员,客服,认证,官方,色情,赌博,暴力,政治',
    'register_password_min_length'       => 8,
    'register_password_require_uppercase'=> 0,
    'register_password_require_lowercase'=> 0,
    'register_password_require_number'   => 0,
    'register_password_require_special'  => 0,
    'register_new_user_restrict_hours'   => 24,
    'register_new_user_ban_post'         => 0,
    'register_new_user_ban_comment'      => 0,
    'register_new_user_ban_like'         => 0,
    'register_new_user_ban_favorite'     => 0,
    'register_enable_honeypot'           => 0,
    'register_enable_device_limit'       => 0,

    // ========== 验证码设置 ==========
    'captcha_enabled'                    => 1,
    'captcha_type'                       => 'mixed',
    'captcha_expire'                     => 300,
    'captcha_length'                     => 4,
    'captcha_width'                      => 120,
    'captcha_height'                     => 40,
    'captcha_font_size'                  => 20,
    'captcha_show_lines'                 => 1,
    'captcha_show_noise'                 => 1,
    'captcha_noise_level'                => 2,
    'captcha_line_count'                 => 3,
    'captcha_bg_color'                   => '255,255,255',
    'captcha_login_enabled'              => 1,
    'captcha_register_enabled'           => 1,
    'captcha_comment_enabled'            => 1,
    'captcha_forgot_password_enabled'    => 1,
    'captcha_reset_enabled'              => 1,
    'captcha_admin_login_enabled'        => 0,

    // ========== 登录设置 ==========
    'login_enabled'                      => 1,
    'login_attempt_limit'                => 5,
    'login_lock_time'                    => 1800,
    'login_allow_email'                  => 1,
    'login_allow_username'               => 1,

    // ========== 功能开关 ==========
    'like_enabled'                       => 1,
    'favorite_enabled'                   => 1,
    'follow_enabled'                     => 1,
    'read_history_enabled'               => 1,
    'message_enabled'                    => 1,
    'message_duration'                   => 3,

    // ========== URL重写设置 ==========
    'rewrite_enabled'                    => 0,
    'rewrite_article'                    => 'article/{id}',
    'rewrite_category'                   => 'category/{id}',
    'rewrite_tag'                        => 'tag/{id}',
    'rewrite_tags'                       => 'tags',
    'rewrite_archives'                   => 'archives',
    'rewrite_page'                       => 'page/{id}',
    'rewrite_login'                      => 'login',
    'rewrite_register'                   => 'register',
    'rewrite_logout'                     => 'logout',
    'rewrite_profile'                    => 'profile',
    'rewrite_forgot_password'            => 'forgot-password',
    'rewrite_reset_password'             => 'reset-password',
    'rewrite_user'                       => 'user/{id}',
    'rewrite_search'                     => 'search',
    'rewrite_follow'                     => 'follow/{id}',
    'rewrite_following'                  => 'following/{id}',
    'rewrite_followers'                  => 'followers/{id}',
    'rewrite_profile_articles'           => 'profile/articles',
    'rewrite_profile_comments'           => 'profile/comments',
    'rewrite_profile_settings'           => 'profile/settings',
    'rewrite_profile_favorites'          => 'profile/favorites',
    'rewrite_profile_likes'              => 'profile/likes',
    'rewrite_profile_notifications'      => 'profile/notifications',
    'rewrite_profile_history'            => 'profile/history',
    'rewrite_profile_following'          => 'profile/following',
    'rewrite_profile_followers'          => 'profile/followers',
    'rewrite_rss'                        => 'rss',

    // ========== SEO设置 ==========
    'home_seo_title'                     => '{site.name}',
    'home_seo_description'               => '{site.description}',
    'home_seo_keywords'                  => '{site.keywords}',
    'article_seo_title'                  => '{article.title} - {article.category} - {site.name}',
    'article_seo_description'            => '{article.summary}',
    'article_seo_keywords'               => '{article.tags}',
    'category_seo_title'                 => '{category.name} - {site.name}',
    'category_seo_description'           => '{category.description}',
    'category_seo_keywords'              => '{category.keywords}',
    'tag_seo_title'                      => '{tag.name} - {site.name}',
    'tag_seo_description'                => '{site.description}',
    'tag_seo_keywords'                   => '{tag.name}, {site.keywords}',
    'page_seo_title'                     => '{page.title} - {site.name}',
    'page_seo_description'               => '{page.description}',
    'page_seo_keywords'                  => '{page.keywords}',
    'search_seo_title'                   => '{search.keyword} - {site.name}',
    'search_seo_description'             => '{site.description}',
    'search_seo_keywords'                => '{search.keyword}, {site.keywords}',
    'user_seo_title'                     => '{user.nickname} - {site.name}',
    'user_seo_description'               => '{user.nickname}的主页',
    'user_seo_keywords'                  => '{user.nickname}, {site.keywords}',
    'archives_seo_title'                 => '文章归档 - {site.name}',
    'archives_seo_description'           => '发布的全部文章汇总 - {site.name}',
    '404_seo_title'                      => '404 - 页面未找到',
    '404_seo_description'                => '您访问的页面不存在或已被删除',
    '404_seo_keywords'                   => '404,页面未找到',
    'robots_txt'                         => "User-agent: *\nDisallow: /admin/\nDisallow: /api/\nAllow: /\n\nSitemap: http://localhost/sitemap.xml",

    // ========== 站点地图设置 ==========
    'sitemap_enabled'                    => 0,
    'sitemap_filename'                   => 'sitemap.xml',
    'sitemap_include_articles'          => 1,
    'sitemap_include_categories'        => 1,
    'sitemap_include_tags'              => 1,
    'sitemap_include_pages'             => 1,

    // ========== API设置 ==========
    'api_enabled'                        => 1,
    'api_auth_type'                      => 'session',
    'api_token_expire'                   => 3600,
    'api_rate_limit_enabled'             => 0,
    'api_rate_limit_count'               => 100,
    'api_rate_limit_time'                => 60,
    'api_response_format'                => 'json',
    'api_pagination_default'             => 20,
    'api_pagination_max'                 => 100,
    'api_cors_enabled'                   => 0,
    'api_cors_origins'                   => '*',
    'api_available_versions'             => '["v1"]',
    'api_enabled_versions'               => '["v1"]',
    'api_default_version'                => 'v1',

    // ========== 调试设置 ==========
    'debug_enabled'                      => 0,
    'debug_panel_enabled'                => 0,
    'debug_error_display'                => 0,
    'debug_slow_query_log'               => 0,
    'debug_slow_query_time'              => 1,

    // ========== 日志系统配置 ==========
    'debug_log_enabled'                  => 0, // 安装后默认关闭，可在后台日志设置中开启
    'debug_log_storage'                  => 'file',
    'debug_log_level'                    => 'info',
    'debug_log_rotation'                 => 7,
    'debug_log_retention'                => 30,
    'debug_log_file_size'                => 10,
    'debug_log_path'                     => 'storage/logs/',
    'debug_log_format'                   => 'text',
    'debug_log_database'                 => 0,
    'debug_log_enabled_categories'       => 'all',

    // ========== 会话设置 ==========
    'session_lifetime'                   => 1800,
    'session_cookie_lifetime'            => 86400,
    'session_gc_maxlifetime'             => 1800,
    'session_secure'                     => 0,
    'session_httponly'                   => 1,
    'session_samesite'                   => 'Lax',

    // ========== 安全设置 ==========
    'security_xss_protection'            => 1,
    'security_csrf_protection'           => 1,
    'security_file_upload_enabled'       => 1,
    'security_file_upload_max_size'      => 5242880,
    'security_file_upload_allowed_types' => 'image/jpeg,image/png,image/gif,image/webp,video/mp4',
    'security_file_upload_forbidden_exts'=> 'php,php3,php4,php5,phtml,exe,dll,asp,aspx,jsp,js,html,htm,shtml,cgi,pl',

    // ========== 图片优化设置 ==========
    'image_webp_enabled'                 => 1,
    'image_compress_enabled'             => 1,
    'image_compress_quality'             => 85,
    'image_max_width'                    => 1920,
    // 多尺寸缩略图（与 install.sql 保持一致，可在后台安全设置中调整）
    // 默认关闭：前台并未消费 _small/_medium/_large 缩略图，开启只会占用磁盘
    'image_thumbnail_enabled'            => 0,
    'image_thumbnail_small'              => 150,
    'image_thumbnail_medium'             => 400,
    'image_thumbnail_large'              => 800,

    // ========== 缓存设置 ==========
    'cache_enabled'                      => 0, // 安装后默认关闭，可在后台缓存设置中开启
    'cache_type'                         => 'file',
    'cache_expire'                        => 3600,
    'cache_size_limit'                   => 100,
    'cache_path'                         => STORAGE_PATH . '/cache', // 与 install.sql 默认一致
    'cache_cleanup_strategy'             => 'lru',
    'cache_compression'                  => 0,
    'cache_key_prefix'                   => 'blog_',
    'cache_batch_operation'              => 1,
    'cache_monitoring_enabled'           => 1,
    'cache_history_days'                 => 7,

    // ========== 备份设置 ==========
    'backup_auto_enabled'                => 0,
    'backup_frequency'                   => 'daily',
    'backup_retain_count'                => 5,
    'backup_include_database'            => 1,
    'backup_include_files'               => 0,
    'backup_last_time'                   => 0,

    // ========== 定时任务设置 ==========
    'cron_secret_key'                    => '',

    // ========== 文件上传配置 ==========
    'path_uploads'                       => 'uploads/',

    // ========== 搜索设置 ==========
    'search_min_length'                  => 1,
    'search_max_length'                  => 100,
    'search_enable_blacklist'            => 1,
    'search_blacklist'                   => '',
    'search_enable_code_filter'          => 1,

    // ========== IP白名单设置 ==========
    'admin_ip_whitelist_enabled'         => 0,
    'admin_ip_whitelist'                 => '[]',
    'admin_ip_trusted_proxies'           => '[]',
];
