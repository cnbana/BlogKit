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
 * 配置服务类
 * 负责配置数据的验证、构建和持久化
 * 从 ConfigController 中提取，避免"上帝控制器"
 */
class ConfigService {

    /**
     * 完整执行配置保存流程
     * @param array $postData  POST 数据
     * @param array $fileData  $_FILES 数据
     * @return array ['success' => bool, 'errors' => [], 'changedCount' => int]
     */
    public static function save(array $postData, array $fileData = []) {
        $db = Database::getInstance();
        $configData = [];
        $errors = [];

        // 依次收集各域名的配置
        self::collectBasicConfig($postData, $fileData, $configData, $errors);
        self::collectEmailConfig($postData, $configData, $errors);
        self::collectUserConfig($postData, $configData);
        self::collectCommentConfig($postData, $configData, $errors);
        self::collectSearchConfig($postData, $configData, $errors);
        self::collectLoginConfig($postData, $configData, $errors);
        self::collectRegisterConfig($postData, $configData, $errors);
        self::collectCaptchaConfig($postData, $configData, $errors);
        self::collectSecurityConfig($postData, $configData, $errors);
        self::collectImageConfig($postData, $configData, $errors);
        self::collectIpWhitelistConfig($postData, $configData);
        self::collectCacheConfig($postData, $configData, $errors);
        self::collectRewriteConfig($postData, $configData);
        self::collectSeoConfig($postData, $configData, $errors);
        self::collectSitemapConfig($postData, $configData, $errors);
        self::collectApiConfig($postData, $configData, $errors);
        self::collectDebugConfig($postData, $configData, $errors);

        // —— 插件扩展点：允许插件把自己的字段注入到 $configData ——
        // 插件钩子 callback 签名：function(array $postData): array { return ['field_name' => 'field_value', ...]; }
        // 主系统只负责合并和持久化，不会关心具体字段含义，一切以插件为准。
        if (class_exists('Hook') && class_exists('Plugin')) {
            $pluginFields = Hook::triggerWithReturn('admin_config_collect_custom', $postData);
            if (!empty($pluginFields) && is_array($pluginFields)) {
                foreach ($pluginFields as $pluginResult) {
                    if (is_array($pluginResult)) {
                        foreach ($pluginResult as $k => $v) {
                            $configData[$k] = $v;
                        }
                    }
                }
            }
        }

        // 更新最后更新时间
        $configData['last_update'] = time();

        // 如果有验证错误，返回错误
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'changedCount' => 0];
        }

        // 持久化
        self::saveToDatabase($db, $configData);

        // 处理 robots.txt 文件
        if (isset($configData['robots_txt'])) {
            self::saveRobotsTxt($configData['robots_txt']);
        }

        // 记录日志
        require_once CORE_PATH . '/lib/Log.php';
        Log::init();
        $changedCount = count($configData);
        Log::info('系统配置更新', 'system', [
            'changed_count' => $changedCount,
            'sub_page' => isset($postData['sub_page']) ? $postData['sub_page'] : 'basic'
        ]);

        return ['success' => true, 'errors' => [], 'changedCount' => $changedCount];
    }

    // ========== 基本设置 ==========

    private static function collectBasicConfig($postData, $fileData, &$configData, &$errors) {
        if (isset($postData['site_name'])) {
            $siteName = trim($postData['site_name']);
            if (empty($siteName)) {
                $errors['site_name'] = '站点名称不能为空';
            } elseif (strlen($siteName) > 100) {
                $errors['site_name'] = '站点名称不能超过100个字符';
            }
            $configData['site_name'] = $siteName;
        }

        if (isset($postData['site_description'])) {
            $siteDescription = trim($postData['site_description']);
            if (strlen($siteDescription) > 500) {
                $errors['site_description'] = '站点描述不能超过500个字符';
            }
            $configData['site_description'] = $siteDescription;
        }

        if (isset($postData['site_keywords'])) {
            $siteKeywords = trim($postData['site_keywords']);
            $siteKeywords = str_replace(['，', ' ', '　'], ',', $siteKeywords);
            $siteKeywords = preg_replace('/,+/', ',', $siteKeywords);
            $siteKeywords = trim($siteKeywords, ',');
            if (strlen($siteKeywords) > 200) {
                $errors['site_keywords'] = '站点关键词不能超过200个字符';
            }
            $configData['site_keywords'] = $siteKeywords;
        }

        if (isset($postData['site_url'])) {
            $siteUrl = trim($postData['site_url']);
            if (!empty($siteUrl) && !filter_var($siteUrl, FILTER_VALIDATE_URL)) {
                $errors['site_url'] = '站点URL格式不正确';
            }
            $configData['site_url'] = $siteUrl;
        }

        // 处理LOGO清除/上传
        if (isset($postData['clear_logo']) && $postData['clear_logo'] == '1') {
            $currentLogo = Config::get('site_logo', '');
            if (!empty($currentLogo)) {
                $logoPath = ROOT_PATH . $currentLogo;
                if (file_exists($logoPath)) {
                    unlink($logoPath);
                }
            }
            $configData['site_logo'] = '';
        } elseif (isset($fileData['site_logo']) && $fileData['site_logo']['error'] == 0) {
            self::handleLogoUpload($fileData['site_logo'], $configData, $errors);
        }

        if (isset($postData['default_theme'])) {
            $defaultTheme = trim($postData['default_theme']);
            if (strlen($defaultTheme) > 50) {
                $errors['default_theme'] = '默认主题名称不能超过50个字符';
            }
            $configData['default_theme'] = $defaultTheme;
        }

        if (isset($postData['logo_display_mode'])) {
            $logoDisplayMode = trim($postData['logo_display_mode']);
            if (!in_array($logoDisplayMode, ['auto', 'image', 'text'])) {
                $errors['logo_display_mode'] = 'LOGO显示方式无效';
            }
            $configData['logo_display_mode'] = $logoDisplayMode;
        }

        // 分页设置（统一处理）
        $paginationKeys = [
            'pagination_count', 'pagination_followers_count', 'pagination_following_count',
            'pagination_comments_count', 'pagination_articles_count', 'pagination_favorites_count',
            'pagination_likes_count', 'pagination_messages_count', 'pagination_history_count',
            'pagination_article_comments_count', 'pagination_search_count', 'pagination_tag_count'
        ];
        $paginationLabels = [
            'pagination_count' => '分页数量', 'pagination_followers_count' => '粉丝页面分页数量',
            'pagination_following_count' => '关注页面分页数量', 'pagination_comments_count' => '评论页面分页数量',
            'pagination_articles_count' => '文章页面分页数量', 'pagination_favorites_count' => '收藏页面分页数量',
            'pagination_likes_count' => '点赞页面分页数量', 'pagination_messages_count' => '消息中心分页数量',
            'pagination_history_count' => '阅读历史分页数量', 'pagination_article_comments_count' => '文章页面评论分页数量',
            'pagination_search_count' => '搜索结果页面分页数量', 'pagination_tag_count' => '标签页分页数量'
        ];
        self::validateIntegerRange($postData, $paginationKeys, 1, 100, $configData, $errors, $paginationLabels);

        // 时区设置
        if (isset($postData['site_timezone'])) {
            $configData['site_timezone'] = trim($postData['site_timezone']);
        }

        // 版权设置
        if (isset($postData['copyright_start_year'])) {
            $year = trim($postData['copyright_start_year']);
            if (!empty($year) && (!is_numeric($year) || strlen($year) !== 4)) {
                $errors['copyright_start_year'] = '版权起始年份格式不正确（如 2024）';
            }
            $configData['copyright_start_year'] = $year;
        }
        if (isset($postData['copyright_owner'])) {
            $configData['copyright_owner'] = trim($postData['copyright_owner']);
        }

        // ICP备案设置
        // 滑动开关（checkbox）：未勾选时浏览器不提交字段，按「未提交即关闭」兜底
        $configData['icp_display'] = isset($postData['icp_display']) ? ($postData['icp_display'] == '1' ? '1' : '0') : '0';
        if (isset($postData['icp_number'])) {
            $configData['icp_number'] = trim($postData['icp_number']);
        }
        if (isset($postData['icp_link'])) {
            $link = trim($postData['icp_link']);
            if (!empty($link) && !filter_var($link, FILTER_VALIDATE_URL)) {
                $errors['icp_link'] = 'ICP备案链接格式不正确';
            }
            $configData['icp_link'] = $link;
        }

        // 公安备案设置
        // 滑动开关（checkbox）：未勾选时浏览器不提交字段，按「未提交即关闭」兜底
        $configData['gongan_display'] = isset($postData['gongan_display']) ? ($postData['gongan_display'] == '1' ? '1' : '0') : '0';
        if (isset($postData['gongan_number'])) {
            $configData['gongan_number'] = trim($postData['gongan_number']);
        }
        if (isset($postData['gongan_link'])) {
            $link = trim($postData['gongan_link']);
            if (!empty($link) && !filter_var($link, FILTER_VALIDATE_URL)) {
                $errors['gongan_link'] = '公安备案链接格式不正确';
            }
            $configData['gongan_link'] = $link;
        }

        // ========================================
        // 站点状态与维护模式设置
        // 说明：这些配置项由 core/bootstrap.php 在每次请求时检查
        // - maintenance_mode: 维护模式开启后，非管理员访问将看到维护提示
        // - site_status: 站点运行状态，open/closed/private
        //   * open: 正常开放
        //   * closed: 关闭站点（非管理员不可访问）
        //   * private: 仅登录用户可访问（未登录用户重定向至登录页）
        // ========================================

        // 维护模式开关（滑动开关）：未勾选时浏览器不提交字段，按「未提交即关闭」兜底
        $configData['maintenance_mode'] = isset($postData['maintenance_mode']) ? ($postData['maintenance_mode'] == '1' ? '1' : '0') : '0';

        // 维护模式提示语
        if (isset($postData['maintenance_message'])) {
            $msg = trim($postData['maintenance_message']);
            if (strlen($msg) > 500) {
                $errors['maintenance_message'] = '维护模式提示信息不能超过500个字符';
            }
            $configData['maintenance_message'] = $msg;
        }

        // 站点运行状态
        if (isset($postData['site_status'])) {
            $siteStatus = trim($postData['site_status']);
            if (!in_array($siteStatus, ['open', 'closed', 'private'], true)) {
                $errors['site_status'] = '站点运行状态设置无效';
            } else {
                $configData['site_status'] = $siteStatus;
            }
        }

        // 站点关闭时的提示语
        if (isset($postData['site_closed_message'])) {
            $msg = trim($postData['site_closed_message']);
            if (strlen($msg) > 500) {
                $errors['site_closed_message'] = '站点关闭提示信息不能超过500个字符';
            }
            $configData['site_closed_message'] = $msg;
        }

        // ========================================
        // RSS 订阅设置
        // 控制站点 RSS 订阅功能的开关与展示方式
        // ========================================
        // 滑动开关（checkbox）：未勾选时浏览器不提交该字段，按「未提交即关闭」兜底
        $configData['rss_enabled'] = isset($postData['rss_enabled']) ? ($postData['rss_enabled'] == '1' ? '1' : '0') : '0';
        self::validateInteger($postData, 'rss_item_count', 1, 200, 'RSS 文章数量', $configData, $errors);
        self::validateInteger($postData, 'rss_cache_duration', 0, 86400, 'RSS 缓存时长（秒）', $configData, $errors);
        if (isset($postData['rss_feed_type'])) {
            $feedType = trim($postData['rss_feed_type']);
            if (!in_array($feedType, ['excerpt', 'full'], true)) {
                $errors['rss_feed_type'] = 'RSS 展示方式无效';
            } else {
                $configData['rss_feed_type'] = $feedType;
            }
        }
        if (isset($postData['rss_language'])) {
            $configData['rss_language'] = trim($postData['rss_language']);
        }

        // ========================================
        // 隐私政策与服务条款
        // 用于页脚/注册页面等位置展示链接
        // ========================================
        if (isset($postData['privacy_policy_url'])) {
            $url = trim($postData['privacy_policy_url']);
            if (!empty($url) && !filter_var($url, FILTER_VALIDATE_URL)) {
                $errors['privacy_policy_url'] = '隐私政策链接格式不正确';
            }
            $configData['privacy_policy_url'] = $url;
        }
        if (isset($postData['terms_of_service_url'])) {
            $url = trim($postData['terms_of_service_url']);
            if (!empty($url) && !filter_var($url, FILTER_VALIDATE_URL)) {
                $errors['terms_of_service_url'] = '服务条款链接格式不正确';
            }
            $configData['terms_of_service_url'] = $url;
        }
    }

    private static function handleLogoUpload($file, &$configData, &$errors) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        if (!in_array($file['type'], $allowedTypes)) {
            $errors['site_logo'] = '请上传JPEG、PNG、GIF或WebP格式的图片';
        } elseif ($file['size'] > $maxSize) {
            $errors['site_logo'] = '图片大小不能超过5MB';
        } else {
            $uploadDir = UPLOADS_PATH . '/logo/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fileName = 'logo_' . time() . '.' . $ext;
            $filePath = $uploadDir . $fileName;
            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                $configData['site_logo'] = '/uploads/logo/' . $fileName;
                // 触发上传完成钩子（绕过 Upload 类的上传点，云存储插件依赖此钩子做镜像同步）
                Hook::trigger(Hook::FILE_UPLOAD_AFTER, array('name' => $fileName, 'path' => $filePath, 'relative_path' => $configData['site_logo'], 'type' => 'logo'));
            } else {
                $errors['site_logo'] = '图片上传失败';
            }
        }
    }

    // ========== 邮箱配置 ==========

    private static function collectEmailConfig($postData, &$configData, &$errors) {
        if (isset($postData['email_smtp_port'])) {
            self::validateInteger($postData['email_smtp_port'], 1, 65535, 'email_smtp_port', 'SMTP端口', $configData, $errors);
        }
        if (isset($postData['email_from_address'])) {
            $addr = trim($postData['email_from_address']);
            if (!empty($addr) && !filter_var($addr, FILTER_VALIDATE_EMAIL)) {
                $errors['email_from_address'] = '发件人邮箱地址格式不正确';
            }
            $configData['email_from_address'] = $addr;
        }
        if (isset($postData['email_smtp_username'])) $configData['email_smtp_username'] = trim($postData['email_smtp_username']);

        // SMTP 密码：加密后入库，避免明文泄露
        if (isset($postData['email_smtp_password'])) {
            $plainPassword = $postData['email_smtp_password'];
            // 当管理员未修改密码时，表单中会带"••••••"之类的占位符，这种情况不能当作新值入库
            // 约定：若 password 字段值为 "__KEEP__"，则保留原数据库中已加密的值不变
            if (is_string($plainPassword) && $plainPassword === '__KEEP__') {
                // 跳过，由调用方从数据库读取旧值回填
                // 但为了保证 save() 的幂等性，这里显式从数据库里取出并写回
                $existing = self::rawConfigValue('email_smtp_password');
                if ($existing !== null && $existing !== '') {
                    $configData['email_smtp_password'] = $existing;
                }
            } else {
                $configData['email_smtp_password'] = Config::encryptSensitive(trim((string)$plainPassword));
            }
        }
        if (isset($postData['email_smtp_encryption'])) $configData['email_smtp_encryption'] = $postData['email_smtp_encryption'];
        if (isset($postData['email_from_name'])) $configData['email_from_name'] = trim($postData['email_from_name']);
        if (isset($postData['email_smtp_host'])) $configData['email_smtp_host'] = trim($postData['email_smtp_host']);
    }

    /**
     * 从 bk_config 表中直接读取某个配置项的原始值
     * 用于"保留原密码"的场景：当管理员点击"发送测试邮件"或提交表单时
     * password 字段被浏览器掩码显示为占位字符，不能覆盖入库
     *
     * @param string $name
     * @return string|null
     */
    private static function rawConfigValue($name) {
        try {
            $db = Database::getInstance();
            $row = $db->fetchOne("SELECT value FROM {$db->table('config')} WHERE name = ?", [$name]);
            if (is_array($row) && isset($row['value'])) {
                return $row['value'];
            }
        } catch (Exception $e) {
            error_log('rawConfigValue: ' . $e->getMessage());
        }
        return null;
    }

    // ========== 用户设置 ==========

    private static function collectUserConfig($postData, &$configData) {
        // 滑动开关（checkbox）字段：未勾选时浏览器不提交字段，按「未提交即关闭」兜底
        $configData['user_registration'] = isset($postData['user_registration']) ? (int)$postData['user_registration'] : 0;
        $configData['profile_avatar_enabled'] = isset($postData['profile_avatar_enabled']) ? (int)$postData['profile_avatar_enabled'] : 0;
    }

    // ========== 评论设置 ==========

    private static function collectCommentConfig($postData, &$configData, &$errors) {
        // 滑动开关（checkbox）：未勾选时浏览器不提交字段，按「未提交即关闭」兜底
        $configData['comment_enabled'] = isset($postData['comment_enabled']) ? (int)$postData['comment_enabled'] : 0;
        $configData['comment_moderation'] = isset($postData['comment_moderation']) ? (int)$postData['comment_moderation'] : 0;

        self::validateInteger($postData, 'comment_max_length', 10, 5000, '评论最大长度', $configData, $errors);
        self::validateInteger($postData, 'comment_min_length', 1, 100, '评论最小长度', $configData, $errors);

        // 评论反垃圾设置（滑动开关字段按「未提交即关闭」兜底）
        $configData['comment_rate_limit_enabled'] = isset($postData['comment_rate_limit_enabled']) ? (int)$postData['comment_rate_limit_enabled'] : 0;
        if (isset($postData['comment_rate_limit_seconds'])) $configData['comment_rate_limit_seconds'] = (int)$postData['comment_rate_limit_seconds'];
        $configData['comment_dedup_enabled'] = isset($postData['comment_dedup_enabled']) ? (int)$postData['comment_dedup_enabled'] : 0;
        if (isset($postData['comment_blocked_keywords'])) $configData['comment_blocked_keywords'] = trim($postData['comment_blocked_keywords']);
        $configData['comment_keyword_filter_enabled'] = isset($postData['comment_keyword_filter_enabled']) ? (int)$postData['comment_keyword_filter_enabled'] : 0;
        $configData['comment_link_check_enabled'] = isset($postData['comment_link_check_enabled']) ? (int)$postData['comment_link_check_enabled'] : 0;
        if (isset($postData['comment_link_action'])) $configData['comment_link_action'] = trim($postData['comment_link_action']);
        $configData['comment_ip_rate_limit_enabled'] = isset($postData['comment_ip_rate_limit_enabled']) ? (int)$postData['comment_ip_rate_limit_enabled'] : 0;
        if (isset($postData['comment_ip_rate_limit_count'])) $configData['comment_ip_rate_limit_count'] = (int)$postData['comment_ip_rate_limit_count'];
        if (isset($postData['comment_ip_rate_limit_window'])) $configData['comment_ip_rate_limit_window'] = (int)$postData['comment_ip_rate_limit_window'];
    }

    // ========== 搜索设置 ==========

    private static function collectSearchConfig($postData, &$configData, &$errors) {
        if (isset($postData['search_min_length'])) {
            self::validateInteger($postData, 'search_min_length', 1, 50, '最小搜索长度', $configData, $errors);
        }
        if (isset($postData['search_max_length'])) {
            self::validateInteger($postData, 'search_max_length', 1, 500, '最大搜索长度', $configData, $errors);
        }
        // 滑动开关（checkbox）：未勾选时浏览器不提交字段，按「未提交即关闭」兜底
        $configData['search_enable_blacklist'] = isset($postData['search_enable_blacklist']) ? (int)$postData['search_enable_blacklist'] : 0;
        if (isset($postData['search_blacklist'])) {
            $configData['search_blacklist'] = trim($postData['search_blacklist']);
        }
        $configData['search_enable_code_filter'] = isset($postData['search_enable_code_filter']) ? (int)$postData['search_enable_code_filter'] : 0;
    }

    // ========== 登录设置 ==========

    private static function collectLoginConfig($postData, &$configData, &$errors) {
        // 滑动开关（checkbox）字段：未勾选时浏览器不提交字段，按「未提交即关闭」兜底
        $configData['login_enabled'] = isset($postData['login_enabled']) ? (int)$postData['login_enabled'] : 0;
        if (isset($postData['login_method'])) $configData['login_method'] = trim($postData['login_method']);
        $configData['login_allow_email'] = isset($postData['login_allow_email']) ? (int)$postData['login_allow_email'] : 0;
        $configData['login_allow_username'] = isset($postData['login_allow_username']) ? (int)$postData['login_allow_username'] : 0;
        self::validateInteger($postData, 'login_attempt_limit', 1, 20, '登录尝试次数限制', $configData, $errors);
        self::validateInteger($postData, 'login_lock_time', 300, 86400, '登录锁定时间', $configData, $errors);
    }

    // ========== 注册设置 ==========

    private static function collectRegisterConfig($postData, &$configData, &$errors) {
        // 滑动开关（checkbox）字段：未勾选时浏览器不提交字段，按「未提交即关闭」兜底
        $configData['register_force_email_verify'] = isset($postData['register_force_email_verify']) ? (int)$postData['register_force_email_verify'] : 0;
        self::validateInteger($postData, 'register_ip_limit_time', 1, 1440, 'IP注册限制时间', $configData, $errors);
        self::validateInteger($postData, 'register_ip_limit_count', 1, 100, '同一IP注册数量限制', $configData, $errors);
        $configData['register_username_ban_pure_number'] = isset($postData['register_username_ban_pure_number']) ? (int)$postData['register_username_ban_pure_number'] : 0;
        $configData['register_username_ban_simple_string'] = isset($postData['register_username_ban_simple_string']) ? (int)$postData['register_username_ban_simple_string'] : 0;
        self::validateInteger($postData, 'register_username_min_length', 2, 20, '用户名最小长度', $configData, $errors);
        self::validateInteger($postData, 'register_username_max_length', 3, 50, '用户名最大长度', $configData, $errors);
        // 用户名违禁词：前后端约定使用英文逗号或中文逗号分隔，保存时做归一化
        if (isset($postData['register_username_ban_keywords'])) {
            $keywords = $postData['register_username_ban_keywords'];
            $keywords = preg_replace('/[,，]/u', ',', $keywords);
            $keywords = preg_replace('/,+/', ',', $keywords);
            $keywords = trim($keywords, " \t\n\r,，");
            $configData['register_username_ban_keywords'] = $keywords;
        }
        self::validateInteger($postData, 'register_password_min_length', 6, 50, '密码最小长度', $configData, $errors);
        $configData['register_password_require_uppercase'] = isset($postData['register_password_require_uppercase']) ? (int)$postData['register_password_require_uppercase'] : 0;
        $configData['register_password_require_lowercase'] = isset($postData['register_password_require_lowercase']) ? (int)$postData['register_password_require_lowercase'] : 0;
        $configData['register_password_require_number'] = isset($postData['register_password_require_number']) ? (int)$postData['register_password_require_number'] : 0;
        $configData['register_password_require_special'] = isset($postData['register_password_require_special']) ? (int)$postData['register_password_require_special'] : 0;
        $configData['register_new_user_ban_post'] = isset($postData['register_new_user_ban_post']) ? (int)$postData['register_new_user_ban_post'] : 0;
        $configData['register_new_user_ban_comment'] = isset($postData['register_new_user_ban_comment']) ? (int)$postData['register_new_user_ban_comment'] : 0;
        $configData['register_new_user_ban_like'] = isset($postData['register_new_user_ban_like']) ? (int)$postData['register_new_user_ban_like'] : 0;
        $configData['register_new_user_ban_favorite'] = isset($postData['register_new_user_ban_favorite']) ? (int)$postData['register_new_user_ban_favorite'] : 0;
        self::validateInteger($postData, 'register_new_user_restrict_hours', 0, 720, '新用户限制时长', $configData, $errors);
        $configData['register_enable_honeypot'] = isset($postData['register_enable_honeypot']) ? (int)$postData['register_enable_honeypot'] : 0;
        $configData['register_enable_device_limit'] = isset($postData['register_enable_device_limit']) ? (int)$postData['register_enable_device_limit'] : 0;
    }

    // ========== 验证码设置 ==========

    private static function collectCaptchaConfig($postData, &$configData, &$errors) {
        // 滑动开关（checkbox）：未勾选时浏览器不提交字段，按「未提交即关闭」兜底
        $configData['captcha_enabled'] = isset($postData['captcha_enabled']) ? (int)$postData['captcha_enabled'] : 0;
        if (isset($postData['captcha_type'])) $configData['captcha_type'] = trim($postData['captcha_type']);
        self::validateInteger($postData, 'captcha_expire', 30, 3600, '验证码过期时间', $configData, $errors);
        self::validateInteger($postData, 'captcha_length', 4, 8, '验证码长度', $configData, $errors);
        self::validateInteger($postData, 'captcha_width', 80, 300, '验证码宽度', $configData, $errors);
        self::validateInteger($postData, 'captcha_height', 30, 150, '验证码高度', $configData, $errors);
        self::validateInteger($postData, 'captcha_font_size', 10, 40, '验证码字体大小', $configData, $errors);
        $configData['captcha_show_lines'] = isset($postData['captcha_show_lines']) ? (int)$postData['captcha_show_lines'] : 0;
        $configData['captcha_show_noise'] = isset($postData['captcha_show_noise']) ? (int)$postData['captcha_show_noise'] : 0;
        self::validateInteger($postData, 'captcha_noise_level', 1, 5, '噪点级别', $configData, $errors);
        self::validateInteger($postData, 'captcha_line_count', 0, 10, '干扰线数量', $configData, $errors);

        // 验证码背景色 RGB 格式
        if (isset($postData['captcha_bg_color'])) {
            $bgColor = trim($postData['captcha_bg_color']);
            if (!empty($bgColor)) {
                if (!preg_match('/^\d{1,3},\d{1,3},\d{1,3}$/', $bgColor)) {
                    $errors['captcha_bg_color'] = '验证码背景色格式不正确，请使用RGB格式，如：255,255,255';
                } else {
                    foreach (explode(',', $bgColor) as $v) {
                        if ((int)$v < 0 || (int)$v > 255) {
                            $errors['captcha_bg_color'] = '验证码背景色RGB值必须在0-255之间';
                            break;
                        }
                    }
                }
            }
            $configData['captcha_bg_color'] = $bgColor;
        }

        // 各场景启用开关
        // 应用场景开关（滑动开关）：未勾选时浏览器不提交字段，按「未提交即关闭」兜底
        $configData['captcha_login_enabled'] = isset($postData['captcha_login_enabled']) ? (int)$postData['captcha_login_enabled'] : 0;
        $configData['captcha_register_enabled'] = isset($postData['captcha_register_enabled']) ? (int)$postData['captcha_register_enabled'] : 0;
        $configData['captcha_comment_enabled'] = isset($postData['captcha_comment_enabled']) ? (int)$postData['captcha_comment_enabled'] : 0;
        $configData['captcha_forgot_password_enabled'] = isset($postData['captcha_forgot_password_enabled']) ? (int)$postData['captcha_forgot_password_enabled'] : 0;
        $configData['captcha_reset_enabled'] = isset($postData['captcha_reset_enabled']) ? (int)$postData['captcha_reset_enabled'] : 0;
        $configData['captcha_admin_login_enabled'] = isset($postData['captcha_admin_login_enabled']) ? (int)$postData['captcha_admin_login_enabled'] : 0;
    }

    // ========== 安全设置 ==========

    private static function collectSecurityConfig($postData, &$configData, &$errors) {
        // 滑动开关（checkbox）：未勾选时浏览器不提交字段，按「未提交即关闭」兜底
        $configData['security_xss_protection'] = isset($postData['security_xss_protection']) ? (int)$postData['security_xss_protection'] : 0;
        $configData['security_csrf_protection'] = isset($postData['security_csrf_protection']) ? (int)$postData['security_csrf_protection'] : 0;
        $configData['security_file_upload_enabled'] = isset($postData['security_file_upload_enabled']) ? (int)$postData['security_file_upload_enabled'] : 0;
        self::validateInteger($postData, 'security_file_upload_max_size', 1024, 52428800, '文件上传最大大小', $configData, $errors);
        if (isset($postData['security_file_upload_allowed_types'])) $configData['security_file_upload_allowed_types'] = trim($postData['security_file_upload_allowed_types']);
        if (isset($postData['security_file_upload_forbidden_exts'])) $configData['security_file_upload_forbidden_exts'] = trim($postData['security_file_upload_forbidden_exts']);
    }

    // ========== 图片优化设置 ==========

    private static function collectImageConfig($postData, &$configData, &$errors) {
        if (isset($postData['image_webp_enabled'])) $configData['image_webp_enabled'] = (int)$postData['image_webp_enabled'];
        if (isset($postData['image_compress_enabled'])) $configData['image_compress_enabled'] = (int)$postData['image_compress_enabled'];
        if (isset($postData['image_compress_quality'])) {
            $quality = (int)$postData['image_compress_quality'];
            if ($quality >= 1 && $quality <= 100) {
                $configData['image_compress_quality'] = $quality;
            }
        }
        if (isset($postData['image_max_width'])) {
            $maxWidth = (int)$postData['image_max_width'];
            if ($maxWidth >= 100 && $maxWidth <= 5000) {
                $configData['image_max_width'] = $maxWidth;
            }
        }
        // 缩略图开关与尺寸（供图片优化生成缩略图时读取）
        if (isset($postData['image_thumbnail_enabled'])) $configData['image_thumbnail_enabled'] = (int)$postData['image_thumbnail_enabled'];
        foreach (['small' => [20, 2000], 'medium' => [50, 3000], 'large' => [100, 5000]] as $sizeName => $range) {
            $key = 'image_thumbnail_' . $sizeName;
            if (isset($postData[$key])) {
                $size = (int)$postData[$key];
                if ($size >= $range[0] && $size <= $range[1]) {
                    $configData[$key] = $size;
                }
            }
        }
    }

    // ========== IP白名单设置 ==========

    private static function collectIpWhitelistConfig($postData, &$configData) {
        // 滑动开关（checkbox）：未勾选时浏览器不提交字段，按「未提交即关闭」兜底
        $configData['admin_ip_whitelist_enabled'] = isset($postData['admin_ip_whitelist_enabled']) ? (int)$postData['admin_ip_whitelist_enabled'] : 0;
        if (isset($postData['admin_ip_whitelist'])) {
            $whitelist = json_decode($postData['admin_ip_whitelist'], true);
            if (is_array($whitelist)) {
                $configData['admin_ip_whitelist'] = json_encode($whitelist);
            }
        }
        if (isset($postData['admin_ip_trusted_proxies'])) {
            $trustedProxies = json_decode($postData['admin_ip_trusted_proxies'], true);
            if (is_array($trustedProxies)) {
                $configData['admin_ip_trusted_proxies'] = json_encode($trustedProxies);
            }
        }
    }

    // ========== 缓存设置 ==========

    private static function collectCacheConfig($postData, &$configData, &$errors) {
        // 开关类字段使用滑动开关（checkbox）：未勾选时浏览器不提交该字段，
        // 因此按「未提交即关闭」兜底为 0，保证取消勾选也能保存为关闭状态
        $configData['cache_enabled'] = isset($postData['cache_enabled']) ? (int)$postData['cache_enabled'] : 0;
        if (isset($postData['cache_type'])) $configData['cache_type'] = trim($postData['cache_type']);
        self::validateInteger($postData, 'cache_expire', 60, 86400, '缓存过期时间', $configData, $errors);
        self::validateInteger($postData, 'cache_size_limit', 1, 1000, '缓存大小限制', $configData, $errors);
        if (isset($postData['cache_path'])) {
            $cachePath = trim($postData['cache_path']);
            if (empty($cachePath)) {
                $errors['cache_path'] = '缓存路径不能为空';
            } elseif (!is_dir($cachePath) && !mkdir($cachePath, 0755, true)) {
                $errors['cache_path'] = '缓存路径不存在且无法创建';
            }
            $configData['cache_path'] = $cachePath;
        }

        // 缓存清理策略（目前仅 LRU 真实实现；LFU 预留，配置项仍写入以便未来扩展）
        if (isset($postData['cache_cleanup_strategy'])) $configData['cache_cleanup_strategy'] = trim($postData['cache_cleanup_strategy']);
        $configData['cache_compression'] = isset($postData['cache_compression']) ? (int)$postData['cache_compression'] : 0;
        if (isset($postData['cache_key_prefix'])) $configData['cache_key_prefix'] = trim($postData['cache_key_prefix']);
        $configData['cache_batch_operation'] = isset($postData['cache_batch_operation']) ? (int)$postData['cache_batch_operation'] : 0;

        // 缓存监控
        $configData['cache_monitoring_enabled'] = isset($postData['cache_monitoring_enabled']) ? (int)$postData['cache_monitoring_enabled'] : 0;
        self::validateInteger($postData, 'cache_history_days', 1, 30, '缓存历史数据保留天数', $configData, $errors);
    }

    // ========== URL重写设置 ==========

    private static function collectRewriteConfig($postData, &$configData) {
        // 滑动开关（checkbox）：未勾选时浏览器不提交字段，按「未提交即关闭」兜底
        $configData['rewrite_enabled'] = isset($postData['rewrite_enabled']) ? (int)$postData['rewrite_enabled'] : 0;

        $rewriteKeys = [
            'rewrite_article', 'rewrite_category', 'rewrite_tag', 'rewrite_tags',
            'rewrite_archives', 'rewrite_page',
            'rewrite_login', 'rewrite_register', 'rewrite_logout', 'rewrite_profile',
            'rewrite_forgot_password', 'rewrite_reset_password', 'rewrite_user',
            'rewrite_search', 'rewrite_rss',
            'rewrite_follow', 'rewrite_following', 'rewrite_followers',
            'rewrite_profile_articles', 'rewrite_profile_comments', 'rewrite_profile_settings',
            'rewrite_profile_favorites', 'rewrite_profile_likes', 'rewrite_profile_notifications',
            'rewrite_profile_history', 'rewrite_profile_following', 'rewrite_profile_followers'
        ];
        foreach ($rewriteKeys as $key) {
            if (isset($postData[$key])) $configData[$key] = trim($postData[$key]);
        }
    }

    // ========== SEO设置 ==========

    private static function collectSeoConfig($postData, &$configData, &$errors) {
        $seoPages = ['home', 'article', 'category', 'tag', 'page', 'search', 'user'];
        $seoFields = ['title' => 200, 'description' => 500, 'keywords' => 500];

        foreach ($seoPages as $page) {
            foreach ($seoFields as $field => $maxLen) {
                $key = "{$page}_seo_{$field}";
                if (isset($postData[$key])) {
                    $val = trim($postData[$key]);
                    if (strlen($val) > $maxLen) {
                        $errors[$key] = "{$page}页{$field}不能超过{$maxLen}个字符";
                    }
                    $configData[$key] = $val;
                }
            }
        }

        // archives 归档页面 SEO 设置
        if (isset($postData['archives_seo_title'])) {
            $val = trim($postData['archives_seo_title']);
            if (strlen($val) > 200) {
                $errors['archives_seo_title'] = '归档页面标题不能超过200个字符';
            }
            $configData['archives_seo_title'] = $val;
        }
        if (isset($postData['archives_seo_description'])) {
            $val = trim($postData['archives_seo_description']);
            if (strlen($val) > 500) {
                $errors['archives_seo_description'] = '归档页面描述不能超过500个字符';
            }
            $configData['archives_seo_description'] = $val;
        }

        // 404页面设置（仅保留SEO相关字段）
        if (isset($postData['404_seo_title'])) $configData['404_seo_title'] = trim($postData['404_seo_title']);
        if (isset($postData['404_seo_description'])) $configData['404_seo_description'] = trim($postData['404_seo_description']);
        if (isset($postData['404_seo_keywords'])) $configData['404_seo_keywords'] = trim($postData['404_seo_keywords']);

        // robots.txt
        if (isset($postData['robots_txt'])) {
            $configData['robots_txt'] = trim($postData['robots_txt']);
        }
    }

    // ========== 站点地图设置 ==========

    private static function collectSitemapConfig($postData, &$configData, &$errors) {
        // 滑动开关（checkbox）：未勾选时浏览器不提交字段，按「未提交即关闭」兜底
        $configData['sitemap_enabled'] = isset($postData['sitemap_enabled']) ? (int)$postData['sitemap_enabled'] : 0;
        if (isset($postData['sitemap_filename'])) {
            $filename = trim($postData['sitemap_filename']);
            if (empty($filename)) {
                $errors['sitemap_filename'] = '站点地图文件名不能为空';
            } elseif (strlen($filename) > 50) {
                $errors['sitemap_filename'] = '站点地图文件名不能超过50个字符';
            }
            $configData['sitemap_filename'] = $filename;
        }
        // 包含内容类型
        $includeTypes = [];
        if (isset($postData['sitemap_include_home'])) $includeTypes[] = 'home';
        if (isset($postData['sitemap_include_articles'])) $includeTypes[] = 'articles';
        if (isset($postData['sitemap_include_categories'])) $includeTypes[] = 'categories';
        if (isset($postData['sitemap_include_pages'])) $includeTypes[] = 'pages';
        if (isset($postData['sitemap_include_tags'])) $includeTypes[] = 'tags';
        $configData['sitemap_include_types'] = implode(',', $includeTypes);
    }

    // ========== API设置 ==========

    private static function collectApiConfig($postData, &$configData, &$errors) {
        if (isset($postData['api_enabled'])) $configData['api_enabled'] = (int)$postData['api_enabled'];
        if (isset($postData['api_auth_type'])) $configData['api_auth_type'] = trim($postData['api_auth_type']);
        if (isset($postData['api_rate_limit_enabled'])) $configData['api_rate_limit_enabled'] = (int)$postData['api_rate_limit_enabled'];
        self::validateInteger($postData, 'api_rate_limit_count', 10, 10000, 'API限流次数', $configData, $errors);
        self::validateInteger($postData, 'api_rate_limit_time', 10, 3600, 'API限流时间窗口', $configData, $errors);
        if (isset($postData['api_cors_enabled'])) $configData['api_cors_enabled'] = (int)$postData['api_cors_enabled'];
        if (isset($postData['api_cors_origins'])) $configData['api_cors_origins'] = trim($postData['api_cors_origins']);
        self::validateInteger($postData, 'api_token_expire', 300, 86400, 'API令牌有效期', $configData, $errors);
        if (isset($postData['api_response_format'])) $configData['api_response_format'] = trim($postData['api_response_format']);
        self::validateInteger($postData, 'api_pagination_default', 10, 100, 'API默认分页数量', $configData, $errors);
        self::validateInteger($postData, 'api_pagination_max', 20, 500, 'API最大分页数量', $configData, $errors);
        
        // API 版本配置
        if (isset($postData['api_available_versions'])) {
            $apiAvailableVersions = trim($postData['api_available_versions']);
            $versionsArray = array_filter(array_map('trim', explode(',', $apiAvailableVersions)));
            $validVersionsArray = [];
            
            foreach ($versionsArray as $ver) {
                if (preg_match('/^v\d+$/', $ver)) {
                    $validVersionsArray[] = $ver;
                }
            }
            
            if (empty($validVersionsArray)) {
                $errors['api_available_versions'] = '至少需要一个有效的版本号（例如：v1）';
            } else {
                $configData['api_available_versions'] = json_encode($validVersionsArray);
            }
        }
        
        if (isset($postData['api_default_version'])) {
            $apiDefaultVersion = trim($postData['api_default_version']);
            $availableVersions = isset($configData['api_available_versions']) 
                ? json_decode($configData['api_available_versions'], true) 
                : json_decode(Config::get('api_available_versions', '["v1"]'), true);
            
            if (!in_array($apiDefaultVersion, $availableVersions)) {
                $errors['api_default_version'] = '默认版本必须在可用版本列表中';
            } else {
                $configData['api_default_version'] = $apiDefaultVersion;
            }
        }
        
        if (isset($postData['api_versions'])) {
            $apiVersions = $postData['api_versions'];
            $availableVersions = isset($configData['api_available_versions']) 
                ? json_decode($configData['api_available_versions'], true) 
                : json_decode(Config::get('api_available_versions', '["v1"]'), true);
                
            if (!is_array($apiVersions)) {
                $apiVersions = [];
            }
            $validVersions = array_intersect($apiVersions, $availableVersions);
            if (empty($validVersions)) {
                $validVersions = [$availableVersions[0] ?? 'v1'];
            }
            $configData['api_enabled_versions'] = json_encode($validVersions);
        }
    }

    // ========== 调试设置 ==========

    private static function collectDebugConfig($postData, &$configData, &$errors) {
        // 基础调试设置
        self::validateInteger($postData, 'debug_slow_query_time', 0.1, 10, '慢查询时间阈值', $configData, $errors);
        if (isset($postData['debug_enabled'])) $configData['debug_enabled'] = (int)$postData['debug_enabled'];
        if (isset($postData['debug_panel_enabled'])) $configData['debug_panel_enabled'] = (int)$postData['debug_panel_enabled'];
        if (isset($postData['debug_error_display'])) $configData['debug_error_display'] = (int)$postData['debug_error_display'];
        if (isset($postData['debug_slow_query_log'])) $configData['debug_slow_query_log'] = (int)$postData['debug_slow_query_log'];
    }

    // ========== 通用工具方法 ==========

    /**
     * 验证整数范围
     */
    private static function validateInteger($postData, $key, $min, $max, $label, &$configData, &$errors) {
        if (isset($postData[$key])) {
            $val = (int)$postData[$key];
            if ($val < $min || $val > $max) {
                $errors[$key] = "{$label}必须在{$min}-{$max}之间";
            } else {
                $configData[$key] = $val;
            }
        }
    }

    /**
     * 批量验证整数范围
     */
    private static function validateIntegerRange($postData, $keys, $min, $max, &$configData, &$errors, $labels = []) {
        foreach ($keys as $key) {
            if (isset($postData[$key])) {
                $val = (int)$postData[$key];
                $label = $labels[$key] ?? $key;
                if ($val < $min || $val > $max) {
                    $errors[$key] = "{$label}必须在{$min}-{$max}之间";
                } else {
                    $configData[$key] = $val;
                }
            }
        }
    }

    // ========== 持久化 ==========

    /**
     * 将配置数据保存到数据库（批量优化，避免 N+1 查询）
     * v2.1.0: 200+ 次查询 → 1 次批量 SELECT + 按需 INSERT/UPDATE
     */
    private static function saveToDatabase($db, array $configData) {
        $names = array_keys($configData);
        if (empty($names)) {
            return;
        }

        // 1. 批量查询所有现有配置（1 次 SQL）
        $placeholders = implode(',', array_fill(0, count($names), '?'));
        $existing = $db->fetchAll(
            "SELECT name, value FROM {$db->table('config')} WHERE name IN ({$placeholders})",
            $names
        );

        // 2. 构建现有值映射
        $existingMap = [];
        foreach ($existing as $row) {
            $existingMap[$row['name']] = $row['value'];
        }

        // 3. 仅对新增和变更的项执行写入
        foreach ($configData as $name => $value) {
            if (array_key_exists($name, $existingMap)) {
                if ($existingMap[$name] != $value) {
                    $db->update('config', ['value' => $value], ['name' => $name]);
                }
            } else {
                $db->insert('config', [
                    'name' => $name,
                    'value' => $value,
                    'description' => '',
                    'type' => 'string'
                ]);
            }
        }
    }

    /**
     * 保存 robots.txt 文件
     */
    private static function saveRobotsTxt($robotsContent) {
        $siteUrl = Config::get('site.url');
        if (empty($siteUrl)) {
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
            $siteUrl = $protocol . '://' . $host;
        }
        $robotsContent = str_replace('{site.url}', rtrim($siteUrl, '/'), $robotsContent);
        $robotsPath = ROOT_PATH . '/robots.txt';
        file_put_contents($robotsPath, $robotsContent);

        require_once CORE_PATH . '/lib/Log.php';
        Log::init();
        Log::info('更新robots.txt文件', 'system');
    }
}
