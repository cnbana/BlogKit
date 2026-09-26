-- BlogKit 完整数据库安装脚本
-- 版本：1.0.0
-- 更新日期：2026-07-25
-- 说明：包含所有功能的完整数据库结构，新用户安装即可体验全部功能
-- 注意：数据库名由用户在安装时指定，不在此文件中硬编码

-- ============================================
-- 清理残留（先关外键检查，防止重复安装时 FK 冲突）
-- ============================================
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `bk_article_tag`, `bk_comment_like`, `bk_like`, `bk_favorite`, `bk_user_follow`, `bk_read_history`, `bk_comments`, `bk_notifications`, `bk_login_tokens`, `bk_article`, `bk_role_permission`, `bk_category`, `bk_tag`, `bk_user`, `bk_page`, `bk_config`, `bk_plugin`, `bk_theme`, `bk_email_template`, `bk_role`, `bk_permission`, `bk_friendlink`, `bk_api_keys`, `bk_log`, `bk_log_archive`, `bk_migration_log`, `bk_audit_log`, `bk_cache_stats`;
SET FOREIGN_KEY_CHECKS = 1;

-- 创建数据库表结构
CREATE TABLE IF NOT EXISTS `bk_article` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL COMMENT '文章标题',
  `content` longtext NOT NULL COMMENT '文章内容',
  `description` text DEFAULT NULL COMMENT '文章描述',
  `excerpt` text DEFAULT NULL COMMENT '文章摘要',
  `cover_image` varchar(255) DEFAULT NULL COMMENT '封面图路径',
  `category_id` bigint(20) NOT NULL COMMENT '分类ID',
  `user_id` bigint(20) NOT NULL COMMENT '作者ID',
  `status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '状态：1-发布，0-草稿',
  `view_count` int(11) NOT NULL DEFAULT '0' COMMENT '浏览次数',
  `like_count` int(11) NOT NULL DEFAULT '0' COMMENT '点赞数',
  `comment_count` int(11) NOT NULL DEFAULT '0' COMMENT '评论数',
  `top_type` tinyint(4) NOT NULL DEFAULT '0' COMMENT '置顶类型：0-普通，1-全局置顶，2-首页置顶，3-分类置顶',
  `created_at` int(11) NOT NULL COMMENT '创建时间',
  `updated_at` int(11) NOT NULL COMMENT '更新时间',
  `is_deleted` tinyint(4) NOT NULL DEFAULT '0' COMMENT '是否被软删除：0-正常，1-已删除',
  `slug` varchar(200) DEFAULT NULL COMMENT '文章别名（SEO友好URL）',
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `category_id` (`category_id`),
  KEY `user_id` (`user_id`),
  KEY `created_at` (`created_at`),
  KEY `top_type` (`top_type`),
  KEY `idx_status_created` (`status`, `created_at`),
  KEY `idx_category_status` (`category_id`, `status`, `created_at`),
  KEY `idx_user_status` (`user_id`, `status`),
  FULLTEXT KEY `ft_search` (`title`, `content`, `description`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='文章表';

-- 分类表
CREATE TABLE IF NOT EXISTS `bk_category` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL COMMENT '分类名称',
  `description` varchar(255) DEFAULT NULL COMMENT '分类描述',
  `keywords` varchar(255) DEFAULT NULL COMMENT '分类关键词',
  `parent_id` bigint(20) NOT NULL DEFAULT '0' COMMENT '父分类ID',
  `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序',
  `slug` varchar(255) NOT NULL DEFAULT '' COMMENT '分类别名',
  `is_menu` tinyint(4) NOT NULL DEFAULT '1' COMMENT '是否显示在分类菜单：1-显示，0-隐藏',
  `redirect_url` varchar(500) DEFAULT '' COMMENT '跳转链接，填写后点击分类会跳转到该链接',
  `created_at` int(11) NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='分类表';

-- 标签表
CREATE TABLE IF NOT EXISTS `bk_tag` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL COMMENT '标签名称',
  `count` int(11) NOT NULL DEFAULT '0' COMMENT '使用次数',
  `created_at` int(11) NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='标签表';

-- 文章标签关联表
CREATE TABLE IF NOT EXISTS `bk_article_tag` (
  `article_id` bigint(20) NOT NULL COMMENT '文章ID',
  `tag_id` bigint(20) NOT NULL COMMENT '标签ID',
  PRIMARY KEY (`article_id`,`tag_id`),
  KEY `tag_id` (`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='文章标签关联表';

-- 用户表
CREATE TABLE IF NOT EXISTS `bk_user` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL COMMENT '用户名',
  `password` varchar(255) NOT NULL COMMENT '密码',
  `email` varchar(100) DEFAULT NULL COMMENT '邮箱',
  `nickname` varchar(50) DEFAULT NULL COMMENT '昵称',
  `avatar` varchar(255) DEFAULT NULL COMMENT '头像',
  `bio` text COMMENT '个人简介',
  `website` varchar(255) DEFAULT NULL COMMENT '个人网站',
  `role` int(11) NOT NULL DEFAULT '3' COMMENT '角色ID，关联到bk_role表',
  `status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '状态：1-正常，0-禁用',
  `is_deleted` tinyint(4) NOT NULL DEFAULT '0' COMMENT '是否被软删除：0-正常，1-已删除',
  `deleted_by` bigint(20) DEFAULT NULL COMMENT '删除者ID',
  `deleted_at` int(11) DEFAULT NULL COMMENT '删除时间',
  `reset_token` varchar(255) DEFAULT NULL COMMENT '密码重置令牌',
  `reset_token_expire` int(11) DEFAULT NULL COMMENT '密码重置令牌过期时间',
  `pending_email` varchar(100) DEFAULT NULL COMMENT '待验证的新邮箱',
  `email_verification_code` varchar(10) DEFAULT NULL COMMENT '邮箱验证码',
  `email_verification_expire` int(11) DEFAULT NULL COMMENT '邮箱验证码过期时间',
  `last_read_comments_time` int(11) NOT NULL DEFAULT '0' COMMENT '最后阅读评论消息的时间',
  `last_login_at` int(11) NOT NULL DEFAULT '0' COMMENT '最后登录时间',
  `notification_settings` TEXT DEFAULT NULL COMMENT '通知设置（JSON格式）',
  `privacy_settings` TEXT DEFAULT NULL COMMENT '隐私设置（JSON格式，包含关注/粉丝/点赞/收藏的可见范围）',
  `register_ip` varchar(45) DEFAULT NULL COMMENT '注册IP地址',
  `register_user_agent` TEXT DEFAULT NULL COMMENT '注册User-Agent',
  `register_referer` varchar(255) DEFAULT NULL COMMENT '注册来源',
  `register_device_type` varchar(20) DEFAULT NULL COMMENT '注册设备类型',
  `register_os` varchar(50) DEFAULT NULL COMMENT '注册操作系统',
  `register_browser` varchar(50) DEFAULT NULL COMMENT '注册浏览器',
  `register_browser_version` varchar(20) DEFAULT NULL COMMENT '注册浏览器版本',
  `register_device_fingerprint` varchar(32) DEFAULT NULL COMMENT '注册设备指纹（IP + UA 的 MD5，用于设备注册频率限制）',

  `created_at` int(11) NOT NULL COMMENT '创建时间',
  `updated_at` int(11) NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户表';

-- 登录令牌表（用于"记住我"自动登录功能）
CREATE TABLE IF NOT EXISTS `bk_login_tokens` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL COMMENT '用户ID',
  `token` varchar(64) NOT NULL COMMENT '登录令牌（明文，用于查询）',
  `token_hash` varchar(255) NOT NULL COMMENT '令牌哈希',
  `expires_at` int(11) NOT NULL COMMENT '过期时间（Unix时间戳）',
  `created_at` int(11) NOT NULL COMMENT '创建时间（Unix时间戳）',
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `user_id` (`user_id`),
  KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='登录令牌表（记住我功能）';

-- 页面表
CREATE TABLE IF NOT EXISTS `bk_page` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL COMMENT '页面标题',
  `content` longtext NOT NULL COMMENT '页面内容',
  `description` text DEFAULT NULL COMMENT '页面描述',
  `keywords` varchar(255) DEFAULT NULL COMMENT '页面关键词',
  `slug` varchar(100) DEFAULT NULL COMMENT '页面别名（可为空，为空时使用ID访问）',
  `status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '状态：1-发布，0-草稿',
  `is_menu` tinyint(4) NOT NULL DEFAULT '1' COMMENT '是否显示在页面菜单：1-显示，0-隐藏',
  `created_at` int(11) NOT NULL COMMENT '创建时间',
  `updated_at` int(11) NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='页面表';

-- 配置表
CREATE TABLE IF NOT EXISTS `bk_config` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL COMMENT '配置项名称',
  `value` text NOT NULL COMMENT '配置项值',
  `description` varchar(255) DEFAULT NULL COMMENT '配置项描述',
  `type` varchar(20) NOT NULL DEFAULT 'string' COMMENT '配置项类型',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='配置表';

-- 插件表
CREATE TABLE IF NOT EXISTS `bk_plugin` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL COMMENT '插件名称',
  `slug` varchar(50) NOT NULL COMMENT '插件标识',
  `description` text NOT NULL COMMENT '插件描述',
  `version` varchar(20) NOT NULL COMMENT '插件版本',
  `status` tinyint(4) NOT NULL DEFAULT '0' COMMENT '状态：1-启用，0-禁用',
  `settings` text DEFAULT NULL COMMENT '插件设置',
  `created_at` int(11) NOT NULL COMMENT '创建时间',
  `installed_at` int(11) NOT NULL COMMENT '安装时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='插件表';

-- 主题表
CREATE TABLE IF NOT EXISTS `bk_theme` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL COMMENT '主题名称',
  `slug` varchar(50) NOT NULL COMMENT '主题标识',
  `description` text NOT NULL COMMENT '主题描述',
  `version` varchar(20) NOT NULL COMMENT '主题版本',
  `status` tinyint(4) NOT NULL DEFAULT '0' COMMENT '状态：1-启用，0-禁用',
  `settings` text DEFAULT NULL COMMENT '主题设置',
  `created_at` int(11) NOT NULL COMMENT '创建时间',
  `installed_at` int(11) NOT NULL COMMENT '安装时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='主题表';

-- 文章点赞表
CREATE TABLE IF NOT EXISTS `bk_like` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL COMMENT '用户ID',
  `article_id` bigint(20) NOT NULL COMMENT '文章ID',
  `created_at` int(11) NOT NULL COMMENT '点赞时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_article` (`user_id`,`article_id`),
  KEY `article_id` (`article_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='文章点赞表';

-- 评论点赞表
CREATE TABLE IF NOT EXISTS `bk_comment_like` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `comment_id` bigint(20) NOT NULL COMMENT '评论ID',
  `user_id` bigint(20) NOT NULL COMMENT '用户ID',
  `created_at` int(11) NOT NULL COMMENT '点赞时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_comment_user` (`comment_id`, `user_id`),
  KEY `comment_id` (`comment_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='评论点赞表';

-- 用户关注关系表
CREATE TABLE IF NOT EXISTS `bk_user_follow` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `follower_id` bigint(20) NOT NULL COMMENT '粉丝ID（关注者）',
  `following_id` bigint(20) NOT NULL COMMENT '被关注者ID',
  `created_at` int(11) NOT NULL COMMENT '关注时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `follower_following_unique` (`follower_id`, `following_id`),
  KEY `follower_id` (`follower_id`),
  KEY `following_id` (`following_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户关注关系表';

-- 阅读历史表
CREATE TABLE IF NOT EXISTS `bk_read_history` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL COMMENT '用户ID',
  `article_id` bigint(20) NOT NULL COMMENT '文章ID',
  `read_at` int(11) NOT NULL COMMENT '阅读时间',
  `is_deleted` tinyint(4) NOT NULL DEFAULT '0' COMMENT '是否删除：0-未删除，1-已删除',
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_article` (`user_id`,`article_id`),
  KEY `read_at` (`read_at`),
  KEY `user_id` (`user_id`),
  KEY `article_id` (`article_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='阅读历史表';



-- 邮件模板表
CREATE TABLE IF NOT EXISTS `bk_email_template` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL COMMENT '模板名称',
  `type` varchar(50) NOT NULL COMMENT '模板类型（如：registration, password_reset, comment_notify等）',
  `subject` varchar(255) NOT NULL COMMENT '邮件主题模板',
  `content` text NOT NULL COMMENT '邮件内容模板（HTML）',
  `is_default` tinyint(4) NOT NULL DEFAULT '0' COMMENT '是否默认模板',
  `status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '状态（0禁用，1启用）',
  `created_at` int(11) NOT NULL COMMENT '创建时间',
  `updated_at` int(11) NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='邮件模板表';

-- 通知表
CREATE TABLE IF NOT EXISTS `bk_notifications` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL COMMENT '用户ID',
  `type` varchar(50) NOT NULL DEFAULT 'default' COMMENT '通知类型',
  `title` varchar(255) NOT NULL COMMENT '通知标题',
  `content` text NOT NULL COMMENT '通知内容',
  `url` varchar(255) DEFAULT '' COMMENT '跳转链接',
  `read` tinyint(4) NOT NULL DEFAULT '0' COMMENT '是否已读：0-未读，1-已读',
  `is_deleted` tinyint(4) NOT NULL DEFAULT '0' COMMENT '是否已删除：0-未删除，1-已删除',
  `deleted_at` int(11) NULL COMMENT '删除时间',
  `created_at` int(11) NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `read` (`read`),
  KEY `is_deleted` (`is_deleted`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='通知表';

-- 评论表
CREATE TABLE IF NOT EXISTS `bk_comments` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `article_id` bigint(20) NOT NULL COMMENT '文章ID',
  `user_id` bigint(20) NOT NULL COMMENT '用户ID',
  `content` text NOT NULL COMMENT '评论内容',
  `status` tinyint(4) NOT NULL DEFAULT '0' COMMENT '状态：1-已审核，0-待审核',
  `created_at` int(11) NOT NULL COMMENT '创建时间',
  `updated_at` int(11) DEFAULT NULL COMMENT '更新时间',
  `parent_id` bigint(20) NOT NULL DEFAULT '0' COMMENT '父评论ID（0表示主评论）',
  `ip_address` varchar(45) DEFAULT NULL COMMENT '用户IP地址',
  `user_agent` text DEFAULT NULL COMMENT '用户浏览器信息',
  `like_count` int(11) NOT NULL DEFAULT '0' COMMENT '点赞数',
  `is_deleted` tinyint(4) NOT NULL DEFAULT '0' COMMENT '是否被软删除：0-正常，1-已删除',
  `is_top` tinyint(4) NOT NULL DEFAULT '0' COMMENT '是否置顶：0-未置顶，1-已置顶',
  PRIMARY KEY (`id`),
  KEY `article_id` (`article_id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`),
  KEY `parent_id` (`parent_id`),
  KEY `created_at` (`created_at`),
  KEY `updated_at` (`updated_at`),
  KEY `is_deleted` (`is_deleted`),
  KEY `is_top` (`is_top`),
  KEY `idx_article_status` (`article_id`, `status`, `created_at`),
  KEY `idx_user_created` (`user_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='评论表';

-- 角色表
CREATE TABLE IF NOT EXISTS `bk_role` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL COMMENT '角色名称',
  `description` varchar(255) DEFAULT NULL COMMENT '角色描述',
  `parent_id` bigint(20) NOT NULL DEFAULT '0' COMMENT '父角色ID（用于继承）',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态（0禁用，1启用）',
  `created_at` int(11) NOT NULL COMMENT '创建时间',
  `updated_at` int(11) NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `parent_id` (`parent_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='角色表';

-- 权限表
CREATE TABLE IF NOT EXISTS `bk_permission` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL COMMENT '权限名称',
  `code` varchar(50) NOT NULL COMMENT '权限编码（唯一标识）',
  `type` tinyint(1) NOT NULL COMMENT '权限类型（1菜单，2操作）',
  `parent_id` bigint(20) NOT NULL DEFAULT '0' COMMENT '父权限ID',
  `path` varchar(100) DEFAULT NULL COMMENT '菜单路径',
  `icon` varchar(50) DEFAULT NULL COMMENT '菜单图标',
  `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态（0禁用，1启用）',
  `created_at` int(11) NOT NULL COMMENT '创建时间',
  `updated_at` int(11) NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `parent_id` (`parent_id`),
  KEY `type` (`type`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='权限表';

-- 角色-权限关联表
CREATE TABLE IF NOT EXISTS `bk_role_permission` (
  `role_id` bigint(20) NOT NULL COMMENT '角色ID',
  `permission_id` bigint(20) NOT NULL COMMENT '权限ID',
  `created_at` int(11) NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `permission_id` (`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='角色-权限关联表';

-- 文章收藏表
CREATE TABLE IF NOT EXISTS `bk_favorite` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL COMMENT '用户ID',
  `article_id` bigint(20) NOT NULL COMMENT '文章ID',
  `created_at` int(11) NOT NULL COMMENT '收藏时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_article` (`user_id`,`article_id`),
  KEY `article_id` (`article_id`),
  KEY `created_at` (`created_at`),
  KEY `idx_article_user` (`article_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='文章收藏表';

-- 友情链接表
CREATE TABLE IF NOT EXISTS `bk_friendlink` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT '链接名称',
  `url` varchar(255) NOT NULL COMMENT '链接地址',
  `description` text DEFAULT NULL COMMENT '链接描述',
  `logo` varchar(255) DEFAULT NULL COMMENT '链接logo',
  `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序',
  `status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '状态：1-启用，0-禁用',
  `created_at` int(11) NOT NULL COMMENT '创建时间',
  `updated_at` int(11) NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `sort` (`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='友情链接表';



-- API Keys表（新增）
CREATE TABLE IF NOT EXISTS `bk_api_keys` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `api_key` varchar(255) NOT NULL COMMENT 'API Key（加密存储）',
  `key_fingerprint` varchar(32) DEFAULT NULL COMMENT '明文API Key的md5指纹，用于验证时快速定位记录',
  `name` varchar(100) NOT NULL COMMENT 'API Key名称',
  `description` text COMMENT 'API Key描述',
  `platform` varchar(100) NOT NULL DEFAULT '' COMMENT '平台/应用名称',
  `permissions` text COMMENT '允许访问的API接口列表，JSON格式',
  `ip_whitelist` text COMMENT '允许访问的IP地址列表，用逗号分隔',
  `domain_whitelist` text COMMENT '允许访问的域名列表，用逗号分隔',
  `rate_limit_count` int(11) DEFAULT 100 COMMENT '请求次数限制',
  `rate_limit_time` int(11) DEFAULT 60 COMMENT '限制时间窗口（秒）',
  `rate_limit_unit` enum('second','minute','hour','day') DEFAULT 'minute' COMMENT '限流时间单位',
  `expire_time` int(11) DEFAULT NULL COMMENT '过期时间（Unix时间戳，null表示永不过期）',
  `status` enum('active','inactive','expired') NOT NULL DEFAULT 'active' COMMENT '状态',
  `created_by` int(11) DEFAULT NULL COMMENT '创建者ID',
  `last_used_at` int(11) DEFAULT NULL COMMENT '最后使用时间',
  `total_requests` int(11) DEFAULT '0' COMMENT '总请求次数',
  `failed_requests` int(11) DEFAULT '0' COMMENT '失败请求次数',
  `created_at` int(11) NOT NULL COMMENT '创建时间',
  `updated_at` int(11) NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `api_key` (`api_key`),
  KEY `key_fingerprint` (`key_fingerprint`),
  KEY `status` (`status`),
  KEY `expire_time` (`expire_time`),
  KEY `platform` (`platform`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='API Keys表';

-- 日志表（新增）
CREATE TABLE IF NOT EXISTS `bk_log` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `time` int(11) NOT NULL COMMENT '日志时间',
  `level` tinyint(4) NOT NULL COMMENT '日志级别',
  `level_name` varchar(20) NOT NULL COMMENT '日志级别名称',
  `category` varchar(50) NOT NULL COMMENT '日志分类',
  `message` text NOT NULL COMMENT '日志消息',
  `context` text DEFAULT NULL COMMENT '上下文信息（JSON格式）',
  `ip` varchar(45) DEFAULT NULL COMMENT '用户IP地址',
  `user_agent` text DEFAULT NULL COMMENT '用户浏览器信息',
  `user_id` bigint(20) DEFAULT NULL COMMENT '用户ID',
  `request_uri` text DEFAULT NULL COMMENT '请求URI',
  `format` varchar(20) DEFAULT 'text' COMMENT '日志格式',
  PRIMARY KEY (`id`),
  KEY `time` (`time`),
  KEY `level` (`level`),
  KEY `category` (`category`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='日志表';

-- 日志归档表（存放超过90天的历史日志）
CREATE TABLE IF NOT EXISTS `bk_log_archive` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `time` INT NOT NULL COMMENT '日志时间（Unix时间戳）',
  `level` TINYINT NOT NULL DEFAULT '1' COMMENT '日志级别',
  `level_name` VARCHAR(20) NOT NULL COMMENT '级别名称',
  `category` VARCHAR(50) NOT NULL COMMENT '日志分类',
  `message` TEXT NOT NULL COMMENT '日志消息',
  `context` TEXT COMMENT '上下文数据（JSON格式）',
  `ip` VARCHAR(45) DEFAULT NULL COMMENT 'IP地址',
  `user_agent` VARCHAR(500) DEFAULT NULL COMMENT '用户代理',
  `user_id` INT DEFAULT '0' COMMENT '用户ID',
  `request_uri` VARCHAR(500) DEFAULT NULL COMMENT '请求URI',
  `format` VARCHAR(20) DEFAULT 'text' COMMENT '日志格式',
  KEY `idx_time` (`time`),
  KEY `idx_level` (`level`),
  KEY `idx_category` (`category`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_time_category` (`time`, `category`),
  KEY `idx_time_level` (`time`, `level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='日志归档表（存放超过90天的日志）';

-- 迁移审计日志表（记录每次数据库迁移操作）
CREATE TABLE IF NOT EXISTS `bk_migration_log` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `version_from` varchar(20) DEFAULT NULL COMMENT '迁移前版本',
  `version_to` varchar(20) NOT NULL COMMENT '目标版本',
  `action` varchar(20) NOT NULL COMMENT '操作类型：preview/run',
  `success` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否成功：1-成功，0-失败',
  `new_tables` int(11) DEFAULT '0' COMMENT '新增表数量',
  `new_columns` int(11) DEFAULT '0' COMMENT '新增列数量',
  `new_indexes` int(11) DEFAULT '0' COMMENT '新增索引数量',
  `new_fk` int(11) DEFAULT '0' COMMENT '新增外键数量',
  `new_config_items` int(11) DEFAULT '0' COMMENT '新增配置项数量',
  `enum_changes` int(11) DEFAULT '0' COMMENT 'ENUM变更数量',
  `errors_count` int(11) DEFAULT '0' COMMENT '错误数量',
  `sql_executed` text COMMENT '执行的SQL语句（JSON格式）',
  `error_details` text COMMENT '错误详情',
  `duration_ms` int(11) DEFAULT '0' COMMENT '执行耗时（毫秒）',
  `created_at` int(11) NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='迁移审计日志表';

-- 操作审计日志表（记录管理员关键操作）
CREATE TABLE IF NOT EXISTS `bk_audit_log` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL COMMENT '操作人ID',
  `user_name` varchar(50) NOT NULL COMMENT '操作人名称',
  `action` varchar(50) NOT NULL COMMENT '操作类型：create/update/delete/import/export等',
  `target_type` varchar(50) NOT NULL COMMENT '目标类型：article/user/category/tag/comment等',
  `target_id` bigint(20) NOT NULL COMMENT '目标ID（0表示批量操作）',
  `data` text COMMENT '操作数据（JSON格式）',
  `ip` varchar(45) NOT NULL COMMENT '操作IP地址',
  `user_agent` varchar(255) DEFAULT NULL COMMENT '用户浏览器信息',
  `created_at` int(11) NOT NULL COMMENT '操作时间',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `target_type` (`target_type`),
  KEY `created_at` (`created_at`),
  KEY `idx_user_action` (`user_id`, `action`),
  KEY `idx_target_time` (`target_type`, `target_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='操作审计日志表';

-- 缓存监控统计表
CREATE TABLE IF NOT EXISTS `bk_cache_stats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `timestamp` datetime NOT NULL COMMENT '统计时间',
  `hits` int(11) NOT NULL DEFAULT '0' COMMENT '缓存命中次数',
  `misses` int(11) NOT NULL DEFAULT '0' COMMENT '缓存未命中次数',
  `sets` int(11) NOT NULL DEFAULT '0' COMMENT '缓存设置次数',
  `deletes` int(11) NOT NULL DEFAULT '0' COMMENT '缓存删除次数',
  `cache_size` bigint(20) NOT NULL DEFAULT '0' COMMENT '缓存占用大小（字节）',
  `hit_rate` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT '缓存命中率（百分比）',
  PRIMARY KEY (`id`),
  KEY `timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='缓存监控统计表';

-- ============================================
-- 外键约束（保证数据一致性）
-- ============================================
-- 文章表 → 分类表
ALTER TABLE `bk_article` ADD CONSTRAINT `fk_article_category` FOREIGN KEY (`category_id`) REFERENCES `bk_category`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;
-- 文章表 → 用户表（作者）
ALTER TABLE `bk_article` ADD CONSTRAINT `fk_article_user` FOREIGN KEY (`user_id`) REFERENCES `bk_user`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
-- 文章-标签关联表 → 文章表
ALTER TABLE `bk_article_tag` ADD CONSTRAINT `fk_article_tag_article` FOREIGN KEY (`article_id`) REFERENCES `bk_article`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
-- 文章-标签关联表 → 标签表
ALTER TABLE `bk_article_tag` ADD CONSTRAINT `fk_article_tag_tag` FOREIGN KEY (`tag_id`) REFERENCES `bk_tag`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
-- 评论表 → 文章表
ALTER TABLE `bk_comments` ADD CONSTRAINT `fk_comments_article` FOREIGN KEY (`article_id`) REFERENCES `bk_article`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
-- 评论表 → 用户表
ALTER TABLE `bk_comments` ADD CONSTRAINT `fk_comments_user` FOREIGN KEY (`user_id`) REFERENCES `bk_user`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
-- 点赞表 → 文章表
ALTER TABLE `bk_like` ADD CONSTRAINT `fk_like_article` FOREIGN KEY (`article_id`) REFERENCES `bk_article`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
-- 点赞表 → 用户表
ALTER TABLE `bk_like` ADD CONSTRAINT `fk_like_user` FOREIGN KEY (`user_id`) REFERENCES `bk_user`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
-- 收藏表 → 文章表
ALTER TABLE `bk_favorite` ADD CONSTRAINT `fk_favorite_article` FOREIGN KEY (`article_id`) REFERENCES `bk_article`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
-- 收藏表 → 用户表
ALTER TABLE `bk_favorite` ADD CONSTRAINT `fk_favorite_user` FOREIGN KEY (`user_id`) REFERENCES `bk_user`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
-- 关注表 → 用户表（关注者）
ALTER TABLE `bk_user_follow` ADD CONSTRAINT `fk_follow_follower` FOREIGN KEY (`follower_id`) REFERENCES `bk_user`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
-- 关注表 → 用户表（被关注者）
ALTER TABLE `bk_user_follow` ADD CONSTRAINT `fk_follow_following` FOREIGN KEY (`following_id`) REFERENCES `bk_user`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
-- 通知表 → 用户表
ALTER TABLE `bk_notifications` ADD CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `bk_user`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
-- 阅读历史表 → 文章表
ALTER TABLE `bk_read_history` ADD CONSTRAINT `fk_read_history_article` FOREIGN KEY (`article_id`) REFERENCES `bk_article`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
-- 阅读历史表 → 用户表
ALTER TABLE `bk_read_history` ADD CONSTRAINT `fk_read_history_user` FOREIGN KEY (`user_id`) REFERENCES `bk_user`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
-- 登录令牌表 → 用户表
ALTER TABLE `bk_login_tokens` ADD CONSTRAINT `fk_login_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `bk_user`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
-- 评论点赞表 → 评论表
ALTER TABLE `bk_comment_like` ADD CONSTRAINT `fk_comment_like_comment` FOREIGN KEY (`comment_id`) REFERENCES `bk_comments`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
-- 评论点赞表 → 用户表
ALTER TABLE `bk_comment_like` ADD CONSTRAINT `fk_comment_like_user` FOREIGN KEY (`user_id`) REFERENCES `bk_user`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
-- 角色权限表 → 角色表
ALTER TABLE `bk_role_permission` ADD CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `bk_role`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
-- 角色权限表 → 权限表
ALTER TABLE `bk_role_permission` ADD CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `bk_permission`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- 插入初始数据
-- 管理员用户（默认密码：12345678）
INSERT INTO `bk_user` (`username`, `password`, `email`, `nickname`, `role`, `status`, `is_deleted`, `notification_settings`, `privacy_settings`, `created_at`, `updated_at`) 
VALUES ('admin', '$2y$10$DUe8wdwUO6WEIF5wtIoH3u9NTIOfZVuEnKq8QvX1hzE0Iz.D7q32i', 'admin@example.com', '管理员', 1, 1, 0, '{\"types\":{\"comments\":1,\"follows\":1,\"system\":1,\"articles\":1,\"reviews\":1,\"article_likes\":1,\"comment_likes\":1,\"favorites\":1}}', '{\"show_following\":\"public\",\"show_followers\":\"public\",\"show_likes\":\"public\",\"show_favorites\":\"public\"}', UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

-- 初始分类
INSERT INTO `bk_category` (`name`, `description`, `parent_id`, `sort`, `is_menu`, `created_at`) 
VALUES ('默认分类', '默认文章分类', 0, 0, 1, UNIX_TIMESTAMP());

-- 初始主题（settings 值与 theme.json 中定义的默认值保持一致，确保新安装后主题设置立即生效）
INSERT INTO `bk_theme` (`name`, `slug`, `description`, `version`, `status`, `settings`, `created_at`, `installed_at`) 
VALUES ('默认主题', 'default', 'BlogKit 默认主题，提供简洁美观的用户界面', '1.0.0', 1, 
'{\"show_back_to_top\":\"1\",\"show_breadcrumb\":\"1\",\"sidebar_show_categories\":\"1\",\"sidebar_show_latest_articles\":\"1\",\"sidebar_show_tags\":\"1\",\"sidebar_show_friendlinks\":\"1\",\"sidebar_show_subscribe\":\"1\",\"sidebar_custom_html\":\"\",\"custom_header\":\"\",\"custom_footer\":\"\"}', 
UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

-- 初始配置
INSERT INTO `bk_config` (`name`, `value`, `description`, `type`) VALUES 
-- 基本设置
('site_name', 'BlogKit', '网站名称', 'string'),
('site_description', 'A simple Blog System', '网站描述', 'string'),
('site_keywords', 'BlogKit,博客系统,轻量级博客,个人网站,内容管理系统,CMS系统,建站工具,博客系统推荐,博客管理系统,个人博客搭建,博客网站建设', '网站关键词', 'string'),
('site_url', 'http://localhost', '网站URL', 'string'),
('site_favicon', '', '网站Favicon路径', 'string'),
('site_charset', 'UTF-8', '网站字符集', 'string'),
('system_version', '1.0.0', '系统版本', 'string'),
('install_time', UNIX_TIMESTAMP(), '系统安装时间', 'integer'),
('last_update', UNIX_TIMESTAMP(), '系统最后更新时间', 'integer'),
-- 伪静态设置
('rewrite_enabled', '0', 'URL重写是否启用', 'boolean'),
('rewrite_article', 'article/{id}', '文章URL格式', 'string'),
('rewrite_category', 'category/{id}', '分类URL格式', 'string'),
('rewrite_tag', 'tag/{id}', '标签URL格式', 'string'),
('rewrite_tags', 'tags', '标签列表URL格式', 'string'),
('rewrite_archives', 'archives', '归档页面URL格式', 'string'),
('rewrite_page', 'page/{id}', '页面URL格式', 'string'),
('rewrite_login', 'login', '登录URL格式', 'string'),
('rewrite_register', 'register', '注册URL格式', 'string'),
('rewrite_logout', 'logout', '注销URL格式', 'string'),
('rewrite_profile', 'profile', '个人资料URL格式', 'string'),
('rewrite_forgot_password', 'forgot-password', '忘记密码URL格式', 'string'),
('rewrite_reset_password', 'reset-password', '重置密码URL格式', 'string'),
('rewrite_user', 'user/{id}', '用户URL格式', 'string'),
('rewrite_search', 'search', '搜索URL格式', 'string'),
('rewrite_follow', 'follow/{id}', '关注URL格式', 'string'),
('rewrite_following', 'following/{id}', '关注列表URL格式', 'string'),
('rewrite_followers', 'followers/{id}', '粉丝列表URL格式', 'string'),
('rewrite_profile_articles', 'profile/articles', '个人资料文章列表URL格式', 'string'),
('rewrite_profile_comments', 'profile/comments', '个人资料评论列表URL格式', 'string'),
('rewrite_profile_settings', 'profile/settings', '个人资料设置URL格式', 'string'),
('rewrite_profile_favorites', 'profile/favorites', '个人资料收藏列表URL格式', 'string'),
('rewrite_profile_likes', 'profile/likes', '个人资料点赞列表URL格式', 'string'),
('rewrite_profile_notifications', 'profile/notifications', '个人资料通知列表URL格式', 'string'),
('rewrite_profile_history', 'profile/history', '个人资料历史记录URL格式', 'string'),
('rewrite_rss', 'rss', 'RSS订阅URL格式', 'string'),
-- 主题设置
('default_theme', 'default', '默认主题', 'string'),
-- 分页设置
-- 说明：仅保留 3 项前台分页配置 + 1 项后台分页配置。
-- 原有的"我的xxx"页面及标签页独立分页项（pagination_followers/following/comments/
-- articles/favorites/likes/messages/history/tag_count）已随基本设置页精简而移除，
-- 前台控制器均为 Config::get('pagination_xxx', Config::get('pagination_count')) 结构，
-- 未设置时自动回退到全站默认值 pagination_count，功能不受影响。
('pagination_count', '10', '每页文章数', 'integer'),
('pagination_article_comments_count', '10', '文章页面评论分页数量', 'integer'),
('pagination_search_count', '10', '搜索结果页面分页数量', 'integer'),
('admin_pagination_count', '20', '后台列表每页数量', 'integer'),
-- 功能开关
('message_enabled', '1', '消息通知是否启用', 'boolean'),
('message_duration', '3', '消息显示持续时间（秒）', 'integer'),
('like_enabled', '1', '点赞功能是否启用', 'boolean'),
('favorite_enabled', '1', '收藏功能是否启用', 'boolean'),

('follow_enabled', '1', '关注功能是否启用', 'boolean'),
-- 站点状态与维护模式
('site_status', 'open', '站点运行状态（open/closed/private）', 'string'),
('site_closed_message', '网站暂时关闭，请稍后再来。', '站点关闭提示信息', 'string'),
('maintenance_mode', '0', '维护模式开关（0-关闭，1-开启）', 'boolean'),
('maintenance_message', '系统正在维护升级中，预计30分钟后恢复，给您带来的不便敬请谅解。', '维护模式提示信息', 'string'),
-- RSS订阅设置
('rss_enabled', '1', '启用RSS订阅', 'boolean'),
('rss_item_count', '20', 'RSS文章数量', 'integer'),
('rss_cache_duration', '3600', 'RSS缓存时间（秒）', 'integer'),
('rss_feed_type', 'excerpt', 'RSS文章展示方式（excerpt/full）', 'string'),
('rss_language', 'zh-cn', 'RSS语言标识', 'string'),
-- 隐私政策与服务条款
('privacy_policy_url', '', '隐私政策链接', 'string'),
('terms_of_service_url', '', '服务条款链接', 'string'),
-- 网站LOGO设置
('site_logo', '', '网站LOGO路径', 'string'),
('logo_display_mode', 'auto', 'LOGO显示方式（auto/image/text）', 'string'),
-- 自定义内容
('custom_header', '', '自定义全局头部内容', 'textarea'),
('custom_footer', '', '自定义全局底部内容', 'textarea'),
-- 评论设置
('comment_moderation', '0', '评论是否需要审核', 'boolean'),
('comment_enabled', '1', '评论功能是否启用', 'boolean'),
('comment_auto_approve', '0', '自动批准评论', 'boolean'),
('comment_max_length', '1000', '评论最大长度', 'integer'),
('comment_min_length', '1', '评论最小长度', 'integer'),
-- 评论反垃圾设置
('comment_rate_limit_enabled', '1', '评论频率限制开关', 'boolean'),
('comment_rate_limit_seconds', '10', '评论频率限制间隔（秒）', 'integer'),
('comment_dedup_enabled', '1', '评论内容去重开关', 'boolean'),
('comment_keyword_filter_enabled', '1', '评论关键词过滤开关', 'boolean'),
('comment_blocked_keywords', '', '评论屏蔽关键词（逗号分隔）', 'string'),
('comment_link_check_enabled', '1', '评论链接检测开关', 'boolean'),
('comment_link_action', 'moderate', '评论包含链接处理方式：block-禁止, moderate-需审核', 'string'),
('comment_ip_rate_limit_enabled', '0', '评论IP频率限制开关', 'boolean'),
('comment_ip_rate_limit_count', '20', '评论IP频率限制次数', 'integer'),
('comment_ip_rate_limit_window', '3600', '评论IP频率限制时间窗口（秒）', 'integer'),
-- 阅读历史设置
('read_history_enabled', '1', '阅读历史功能是否启用', 'boolean'),
-- 用户注册设置
('user_registration', '1', '是否允许用户注册', 'boolean'),
-- 登录设置
('login_enabled', '1', '是否允许用户登录', 'boolean'),
('login_attempt_limit', '5', '登录尝试失败限制次数', 'integer'),
('login_lock_time', '1800', '登录失败后锁定时间（秒）', 'integer'),
('login_allow_email', '1', '是否允许使用邮箱登录', 'boolean'),
('login_allow_username', '1', '是否允许使用用户名登录', 'boolean'),
-- 用户设置
('profile_avatar_enabled', '1', '是否允许用户上传头像', 'boolean'),

-- SEO设置
-- 首页SEO设置
('home_seo_title', '{site.name}', '首页SEO标题', 'string'),
('home_seo_description', '{site.description}', '首页SEO描述', 'string'),
('home_seo_keywords', '{site.keywords}', '首页SEO关键词', 'string'),
-- 文章SEO设置
('article_seo_title', '{article.title} - {article.category} - {site.name}', '文章SEO标题', 'string'),
('article_seo_description', '{article.summary}', '文章SEO描述', 'string'),
('article_seo_keywords', '{article.tags}', '文章SEO关键词', 'string'),
-- 分类SEO设置
('category_seo_title', '{category.name} - {site.name}', '分类SEO标题', 'string'),
('category_seo_description', '{category.description}', '分类SEO描述', 'string'),
('category_seo_keywords', '{category.keywords}', '分类SEO关键词', 'string'),
-- 标签SEO设置
('tag_seo_title', '{tag.name} - {site.name}', '标签SEO标题', 'string'),
('tag_seo_description', '{site.description}', '标签SEO描述', 'string'),
('tag_seo_keywords', '{tag.name}, {site.keywords}', '标签SEO关键词', 'string'),
-- 页面SEO设置
('page_seo_title', '{page.title} - {site.name}', '页面SEO标题', 'string'),
('page_seo_description', '{page.description}', '页面SEO描述', 'string'),
('page_seo_keywords', '{page.keywords}', '页面SEO关键词', 'string'),
-- 搜索SEO设置
('search_seo_title', '{search.keyword} - {site.name}', '搜索SEO标题', 'string'),
('search_seo_description', '{site.description}', '搜索SEO描述', 'string'),
('search_seo_keywords', '{search.keyword}, {site.keywords}', '搜索SEO关键词', 'string'),
-- 用户主页SEO设置
('user_seo_title', '{user.nickname} - {site.name}', '用户主页SEO标题', 'string'),
('user_seo_description', '{user.nickname}的主页', '用户主页SEO描述', 'string'),
('user_seo_keywords', '{user.nickname}, {site.keywords}', '用户主页SEO关键词', 'string'),
-- 归档页面SEO设置
('archives_seo_title', '文章归档 - {site.name}', '归档页面SEO标题', 'string'),
('archives_seo_description', '发布的全部文章汇总 - {site.name}', '归档页面SEO描述', 'string'),
-- 404页面设置
('404_seo_title', '404 - 页面未找到', '404页面SEO标题', 'string'),
('404_seo_description', '您访问的页面不存在或已被删除', '404页面SEO描述', 'string'),
('404_seo_keywords', '404,页面未找到', '404页面SEO关键词', 'string'),
-- robots.txt设置
('robots_txt', 'User-agent: *\nDisallow: /admin/\nDisallow: /api/\nAllow: /\n\nSitemap: http://localhost/sitemap.xml', 'robots.txt内容', 'string'),
-- 站点地图设置
('sitemap_enabled', '0', '启用站点地图', 'boolean'),
('sitemap_filename', 'sitemap.xml', '站点地图文件名', 'string'),
('sitemap_include_articles', '1', '包含文章', 'boolean'),
('sitemap_include_categories', '1', '包含分类', 'boolean'),
('sitemap_include_tags', '1', '包含标签', 'boolean'),
('sitemap_include_pages', '1', '包含页面', 'boolean'),
-- 邮件配置
('email_smtp_host', '', 'SMTP服务器地址', 'string'),
('email_smtp_port', '465', 'SMTP服务器端口', 'integer'),
('email_smtp_username', '', 'SMTP用户名', 'string'),
('email_smtp_password', '', 'SMTP密码', 'string'),
('email_smtp_encryption', 'ssl', 'SMTP加密方式', 'string'),
('email_from_address', '', '发件人邮箱地址', 'string'),
('email_from_name', '网站名称', '发件人名称', 'string'),
-- 验证码配置
('captcha_enabled', '1', '验证码全局开关', 'boolean'),
('captcha_type', 'mixed', '默认验证码类型', 'string'),
('captcha_expire', '300', '验证码过期时间（秒）', 'integer'),
('captcha_length', '4', '验证码显示长度（4-6位）', 'integer'),
('captcha_width', '120', '验证码宽度', 'integer'),
('captcha_height', '40', '验证码高度', 'integer'),
('captcha_font_size', '20', '验证码字体大小', 'integer'),
('captcha_bg_color', '255,255,255', '验证码背景色（RGB）', 'string'),
('captcha_show_lines', '1', '是否显示干扰线', 'boolean'),
('captcha_show_noise', '1', '是否显示噪点', 'boolean'),
('captcha_noise_level', '2', '噪点级别（1-5）', 'integer'),
('captcha_line_count', '3', '干扰线数量', 'integer'),
('captcha_login_enabled', '1', '登录页面是否启用验证码', 'boolean'),
('captcha_register_enabled', '1', '注册页面是否启用验证码', 'boolean'),
('captcha_forgot_password_enabled', '1', '忘记密码页面是否启用验证码', 'boolean'),
('captcha_comment_enabled', '1', '评论功能是否启用验证码', 'boolean'),
('captcha_reset_enabled', '1', '密码重置是否启用验证码', 'boolean'),
('captcha_admin_login_enabled', '0', '后台登录是否启用验证码', 'boolean'),
-- 注册设置
('register_force_email_verify', '0', '是否开启强制邮箱验证', 'boolean'),
('register_ip_limit_time', '60', '同一IP限制时间（分钟）', 'integer'),
('register_ip_limit_count', '3', '同一IP最多注册账号数', 'integer'),
('register_username_ban_pure_number', '0', '禁止纯数字用户名', 'boolean'),
('register_username_ban_simple_string', '0', '禁止简单字符串用户名', 'boolean'),
('register_username_min_length', '3', '用户名最小长度', 'integer'),
('register_username_max_length', '20', '用户名最大长度', 'integer'),
('register_username_ban_keywords', '管理员,客服,认证,官方,色情,赌博,暴力,政治', '用户名违禁词（逗号分隔）', 'string'),
('register_password_require_uppercase', '0', '密码必须包含大写字母', 'boolean'),
('register_password_require_lowercase', '0', '密码必须包含小写字母', 'boolean'),
('register_password_require_number', '0', '密码必须包含数字', 'boolean'),
('register_password_require_special', '0', '密码必须包含特殊字符', 'boolean'),
('register_password_min_length', '8', '密码最小长度', 'integer'),
('register_new_user_ban_post', '0', '禁止新用户发帖', 'boolean'),
('register_new_user_ban_comment', '0', '禁止新用户评论', 'boolean'),
('register_new_user_ban_like', '0', '禁止新用户点赞', 'boolean'),
('register_new_user_ban_favorite', '0', '禁止新用户收藏', 'boolean'),
('register_new_user_restrict_hours', '24', '新用户限制时长（小时）', 'integer'),
('register_enable_honeypot', '0', '启用注册蜜罐防护', 'boolean'),
('register_enable_device_limit', '0', '启用设备注册限制', 'boolean'),
-- 文件上传配置
('path_uploads', 'uploads/', '文件上传路径', 'string'),
-- 图片优化设置
('image_webp_enabled', '1', '上传自动生成WebP格式', 'boolean'),
('image_compress_enabled', '1', '上传自动压缩图片', 'boolean'),
('image_compress_quality', '85', '图片压缩质量（1-100）', 'integer'),
('image_max_width', '1920', '图片最大宽度（像素）', 'integer'),
('image_thumbnail_enabled', '0', '上传自动生成多尺寸缩略图（前台未消费缩略图，默认关闭）', 'boolean'),
('image_thumbnail_small', '150', '缩略图 small 尺寸（像素）', 'integer'),
('image_thumbnail_medium', '400', '缩略图 medium 尺寸（像素）', 'integer'),
('image_thumbnail_large', '800', '缩略图 large 尺寸（像素）', 'integer'),
-- 调试设置
('debug_enabled', '0', '启用调试模式', 'boolean'),
('debug_panel_enabled', '0', '显示调试面板', 'boolean'),
('debug_error_display', '0', '显示详细错误', 'boolean'),
('debug_slow_query_log', '0', '启用慢查询日志', 'boolean'),
('debug_slow_query_time', '1', '慢查询时间阈值（秒）', 'float'),
-- 日志系统配置（调试设置下方，由 admin.php?action=log&method=settings 管理）
('debug_log_enabled', '0', '启用日志记录（安装后默认关闭，可在后台日志设置中开启）', 'boolean'),
('debug_log_storage', 'file', '日志存储方式（file/database）', 'string'),
('debug_log_level', 'info', '日志最低记录级别（all/debug/info/warning/error/security）', 'string'),
('debug_log_rotation', '7', '日志轮转周期（1=每日/7=每周/30=每月）', 'integer'),
('debug_log_retention', '30', '日志保留天数', 'integer'),
('debug_log_file_size', '10', '单日志文件大小限制（MB）', 'integer'),
('debug_log_path', 'storage/logs/', '日志文件目录', 'string'),
('debug_log_format', 'text', '日志格式（text/json/csv/kv）', 'string'),
('debug_log_database', '0', '是否写入数据库日志表', 'boolean'),
('debug_log_enabled_categories', '"all"', '启用的日志分类（JSON数组或"all"）', 'string'),
-- 会话设置
('session_lifetime', '1800', '会话有效时间（秒）', 'integer'),
('session_cookie_lifetime', '86400', '会话Cookie生命周期（秒）', 'integer'),
('session_gc_maxlifetime', '1800', '垃圾回收最大生命周期（秒）', 'integer'),
('session_secure', '0', '会话是否使用HTTPS', 'boolean'),
('session_httponly', '1', '会话Cookie是否仅HTTP访问', 'boolean'),
('session_samesite', 'Lax', '会话SameSite策略', 'string'),
-- 安全设置
('security_xss_protection', '1', 'XSS防护', 'boolean'),
('security_csrf_protection', '1', 'CSRF防护', 'boolean'),
('security_file_upload_enabled', '1', '启用文件上传', 'boolean'),
('security_file_upload_max_size', '5242880', '文件上传最大大小（字节）', 'integer'),
('security_file_upload_allowed_types', 'image/jpeg,image/png,image/gif,image/webp,video/mp4', '允许的文件上传类型', 'string'),
('security_file_upload_forbidden_exts', 'php,php3,php4,php5,phtml,exe,dll,asp,aspx,jsp,js,html,htm,shtml,cgi,pl', '禁止的文件上传扩展名', 'string'),
-- API设置（新增）
('api_enabled', '1', '启用API功能', 'boolean'),
('api_rate_limit_enabled', '0', '启用请求频率限制', 'boolean'),
('api_rate_limit_count', '100', '请求次数限制', 'integer'),
('api_rate_limit_time', '60', '限制时间窗口（秒）', 'integer'),
('api_cors_enabled', '0', '启用CORS跨域支持', 'boolean'),
('api_cors_origins', '*', '允许的跨域来源', 'string'),
('api_auth_type', 'session', '认证方式', 'string'),
('api_token_expire', '3600', 'Token过期时间（秒）', 'integer'),
('api_response_format', 'json', '响应格式', 'string'),
('api_pagination_default', '20', '默认分页数量', 'integer'),
('api_pagination_max', '100', '最大分页数量', 'integer'),
('api_available_versions', '["v1"]', '可用的API版本列表（JSON格式）', 'string'),
('api_enabled_versions', '["v1"]', '启用的API版本列表（JSON格式）', 'string'),
('api_default_version', 'v1', '默认API版本', 'string'),
-- 缓存设置（新增）
('cache_enabled', '0', '启用缓存功能（安装后默认关闭，可在后台缓存设置中开启）', 'boolean'),
('cache_type', 'file', '缓存类型：file, redis', 'string'),
('cache_expire', '3600', '缓存过期时间（秒）', 'integer'),
('cache_size_limit', '100', '缓存大小限制（MB）', 'integer'),
('cache_path', 'storage/cache', '缓存存储路径', 'string'),
('cache_cleanup_strategy', 'lru', '缓存清理策略（lru, lfu）', 'string'),
('cache_compression', '0', '启用缓存压缩', 'boolean'),
('cache_key_prefix', 'blog_', '缓存键前缀', 'string'),
('cache_batch_operation', '1', '启用批量操作', 'boolean'),
('cache_monitoring_enabled', '1', '启用缓存监控', 'boolean'),
('cache_history_days', '7', '缓存历史数据保留天数', 'integer'),
-- 备份设置（新增）
('backup_auto_enabled', '0', '启用自动备份', 'boolean'),
('backup_frequency', 'daily', '自动备份频率：daily/hourly/weekly', 'string'),
('backup_retain_count', '5', '保留最近备份份数', 'integer'),
('backup_include_database', '1', '备份包含数据库', 'boolean'),
('backup_include_files', '0', '备份包含上传文件', 'boolean'),
('backup_last_time', '0', '上次自动备份时间戳', 'integer'),
-- 定时任务设置（新增）
('cron_secret_key', '', '定时任务Web安全密钥（留空自动生成）', 'string'),
-- 版权与备案设置
('copyright_start_year', '', '版权起始年份（如 2024）', 'string'),
('copyright_owner', '', '版权所有者（留空使用站点名称）', 'string'),
('icp_display', '0', '显示ICP备案号', 'boolean'),
('icp_number', '', 'ICP备案号', 'string'),
('icp_link', '', 'ICP备案链接（留空默认工信部）', 'string'),
('gongan_display', '0', '显示公安备案号', 'boolean'),
('gongan_number', '', '公安备案号', 'string'),
('gongan_link', '', '公安备案链接（留空默认公安部）', 'string'),
('site_timezone', 'Asia/Shanghai', '站点时区', 'string'),
-- 搜索功能设置
('search_min_length', '1', '搜索关键词最小长度', 'integer'),
('search_max_length', '100', '搜索关键词最大长度', 'integer'),
('search_enable_blacklist', '1', '是否启用搜索关键词黑名单过滤', 'boolean'),
('search_blacklist', '', '搜索关键词黑名单（每行一个关键词）', 'textarea'),
('search_enable_code_filter', '1', '是否启用搜索内容代码检测（防止SQL注入/XSS攻击）', 'boolean');


-- 插入初始角色
INSERT INTO `bk_role` (`id`, `name`, `description`, `parent_id`, `status`, `created_at`, `updated_at`) VALUES
(1, '管理员', '系统管理员，拥有所有权限', 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(2, '游客', '默认角色，拥有最基本权限', 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(3, '普通用户', '普通注册用户', 2, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(4, '作者', '可以发布文章（自定义修改）', 3, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

-- 插入初始权限（新菜单结构）
INSERT INTO `bk_permission` (`id`, `name`, `code`, `type`, `parent_id`, `path`, `icon`, `sort`, `status`, `created_at`, `updated_at`) VALUES
-- 1. 仪表盘
(1, '仪表盘', 'dashboard', 1, 0, 'admin.php?action=dashboard', 'dashboard', 1, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
-- 2. 内容管理（分组）
(2, '内容管理', 'content_manage', 1, 0, '', 'file-text', 2, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(3, '文章管理', 'article', 1, 2, 'admin.php?action=article', 'file-text', 1, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(4, '分类管理', 'category', 1, 2, 'admin.php?action=category', 'folder', 2, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(5, '标签管理', 'tag', 1, 2, 'admin.php?action=tag', 'tag', 3, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(6, '页面管理', 'page', 1, 2, 'admin.php?action=page', 'file-text', 4, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(7, '评论管理', 'comment', 1, 2, 'admin.php?action=comment', 'message-square', 5, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(8, '友情链接', 'friendlink', 1, 2, 'admin.php?action=friendlink', 'link', 6, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
-- 3. 用户管理（分组）
(9, '用户管理', 'user_permission', 1, 0, '', 'users', 3, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(10, '用户列表', 'user', 1, 9, 'admin.php?action=user', 'users', 1, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(11, '角色管理', 'role', 1, 9, 'admin.php?action=role', 'shield', 2, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(12, '权限管理', 'permission', 1, 9, 'admin.php?action=permission', 'key', 3, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
-- 4. 外观扩展（分组）
(13, '外观扩展', 'appearance_extension', 1, 0, '', 'palette', 4, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(14, '主题管理', 'theme', 1, 13, 'admin.php?action=theme', 'palette', 1, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(15, '插件管理', 'plugin', 1, 13, 'admin.php?action=plugin', 'extension', 2, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(16, '钩子管理', 'hook', 1, 13, 'admin.php?action=hook', 'code', 3, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
-- 5. 媒体数据（分组）
(17, '媒体数据', 'media_data', 1, 0, '', 'file-text-o', 5, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(18, '媒体管理', 'media', 1, 17, 'admin.php?action=media', 'file-text-o', 1, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(19, '数据备份', 'backup', 1, 17, 'admin.php?action=backup', 'database', 2, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
-- 6. 互动通知（分组）
(20, '互动通知', 'interaction_notification', 1, 0, '', 'fa-bell', 6, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(21, '通知管理', 'notification', 1, 20, 'admin.php?action=notification', 'fa-bell', 1, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(22, '互动管理', 'interaction', 1, 20, 'admin.php?action=interaction', 'heart', 2, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
-- 7. 系统设置
(23, '系统设置', 'config', 1, 0, 'admin.php?action=config', 'settings', 7, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(24, '基本设置', 'config_basic', 1, 23, 'admin.php?action=basic', 'home', 1, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(25, '功能开关', 'config_feature', 1, 23, 'admin.php?action=feature', 'toggle', 2, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(26, '用户设置', 'config_user', 1, 23, 'admin.php?action=user_settings', 'user', 3, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(27, '注册设置', 'config_register', 1, 23, 'admin.php?action=register_settings', 'user-plus', 4, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(28, '登录设置', 'config_login', 1, 23, 'admin.php?action=login_settings', 'log-in', 5, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(30, '评论设置', 'config_comment', 1, 23, 'admin.php?action=comment_settings', 'message-square', 7, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(87, '搜索设置', 'config_search', 1, 23, 'admin.php?action=search_settings', 'search', 7.5, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(31, 'SEO设置', 'config_seo', 1, 23, 'admin.php?action=seo', 'trending-up', 8, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(32, '伪静态设置', 'config_rewrite', 1, 23, 'admin.php?action=rewrite', 'refresh', 9, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(33, '邮箱配置', 'config_email', 1, 23, 'admin.php?action=email', 'mail', 10, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(34, '安全设置', 'config_security', 1, 23, 'admin.php?action=security', 'shield', 11, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(35, '验证码设置', 'config_captcha', 1, 23, 'admin.php?action=captcha_settings', 'key', 12, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(36, 'IP白名单', 'config_ip_whitelist', 1, 23, 'admin.php?action=ip_whitelist', 'list', 13, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
-- 8. API管理（分组）
(37, 'API管理', 'api_manage', 1, 0, '', 'key', 8, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(38, 'API设置', 'api_settings', 1, 37, 'admin.php?action=api', 'key', 1, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(39, '密钥管理', 'api_key', 1, 37, 'admin.php?action=api_key', 'key', 2, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
-- 9. 系统维护（分组）
(40, '系统维护', 'system_maintenance', 1, 0, '', 'settings', 9, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(41, '缓存设置', 'config_cache', 1, 40, 'admin.php?action=cache', 'database', 1, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(42, '调试设置', 'config_debug', 1, 40, 'admin.php?action=debug', 'bug', 2, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(43, '日志管理', 'log_manage', 1, 40, 'admin.php?action=log', 'file-text-o', 3, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(44, '数据迁移', 'config_migrate', 1, 40, 'admin.php?action=migrate', 'refresh', 4, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
-- (45, '系统信息', 'config_info', ...) 已移除：系统信息页于 v2.x 精简合并到仪表盘，不再作为独立菜单
-- 操作权限
(46, '添加用户', 'user_add', 2, 10, '', '', 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(47, '编辑用户', 'user_edit', 2, 10, '', '', 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(48, '删除用户', 'user_delete', 2, 10, '', '', 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(49, '添加文章', 'article_add', 2, 3, '', '', 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(50, '编辑文章', 'article_edit', 2, 3, '', '', 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(51, '删除文章', 'article_delete', 2, 3, '', '', 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(52, '添加分类', 'category_add', 2, 4, '', '', 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(53, '编辑分类', 'category_edit', 2, 4, '', '', 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(54, '删除分类', 'category_delete', 2, 4, '', '', 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(55, '添加标签', 'tag_add', 2, 5, '', '', 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(56, '编辑标签', 'tag_edit', 2, 5, '', '', 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(57, '删除标签', 'tag_delete', 2, 5, '', '', 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(58, '审核评论', 'comment_approve', 2, 7, '', '', 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(59, '删除评论', 'comment_delete', 2, 7, '', '', 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(60, '添加页面', 'page_add', 2, 6, '', '', 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(61, '编辑页面', 'page_edit', 2, 6, '', '', 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(62, '删除页面', 'page_delete', 2, 6, '', '', 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(63, '添加友情链接', 'friendlink_add', 2, 8, '', '', 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(64, '编辑友情链接', 'friendlink_edit', 2, 8, '', '', 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(65, '删除友情链接', 'friendlink_delete', 2, 8, '', '', 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(66, '添加角色', 'role_add', 2, 11, '', '', 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(67, '编辑角色', 'role_edit', 2, 11, '', '', 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(68, '删除角色', 'role_delete', 2, 11, '', '', 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(69, '添加权限', 'permission_add', 2, 12, '', '', 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(70, '编辑权限', 'permission_edit', 2, 12, '', '', 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(71, '删除权限', 'permission_delete', 2, 12, '', '', 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(72, '切换主题', 'theme_switch', 2, 14, '', '', 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(73, '安装插件', 'plugin_install', 2, 15, '', '', 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(74, '启用插件', 'plugin_enable', 2, 15, '', '', 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(75, '禁用插件', 'plugin_disable', 2, 15, '', '', 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(76, '测试钩子', 'hook_test', 2, 16, '', '', 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(77, '查看钩子日志', 'hook_logs', 2, 16, '', '', 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(78, '清除钩子日志', 'hook_clear_logs', 2, 16, '', '', 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(79, '删除点赞记录', 'interaction_delete_like', 2, 22, '', '', 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(80, '删除收藏记录', 'interaction_delete_favorite', 2, 22, '', '', 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(81, '批量删除点赞记录', 'interaction_batch_delete_like', 2, 22, '', '', 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(82, '批量删除收藏记录', 'interaction_batch_delete_favorite', 2, 22, '', '', 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(83, '上传媒体', 'media_upload', 2, 18, '', '', 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(84, '删除媒体', 'media_delete', 2, 18, '', '', 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(85, '保存设置', 'config_save', 2, 26, '', '', 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(86, '邮件模板管理', 'email_template', 2, 26, '', '', 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

-- 应用市场菜单（P3 在线安装，方案 4.2）：单列插入避免与上方固定 ID 冲突（自增 ID）；
-- 角色 1 的授权由下方 role_permission 的 INSERT...SELECT 语句统一覆盖
INSERT INTO `bk_permission` (`name`, `code`, `type`, `parent_id`, `path`, `icon`, `sort`, `status`, `created_at`, `updated_at`) VALUES
('应用市场', 'market', 1, 13, 'admin.php?action=market', 'shopping-cart', 4, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

-- 系统升级菜单（2026-09-26 双包分发方案）：与「应用市场」同分组（parent_id=13），
-- 显性化在线升级入口（原入口仅市场页横幅）；权限校验沿用 PERMISSION_MAP（update→config），
-- 本行仅控制侧栏可见性；老站由迁移引擎自动补插（Migrate 按 code 幂等，方案 B），无需手工 SQL
INSERT INTO `bk_permission` (`name`, `code`, `type`, `parent_id`, `path`, `icon`, `sort`, `status`, `created_at`, `updated_at`) VALUES
('系统升级', 'update', 1, 13, 'admin.php?action=update', 'refresh', 5, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

-- 为管理员角色分配所有权限
INSERT IGNORE INTO `bk_role_permission` (`role_id`, `permission_id`, `created_at`) 
        SELECT 1, id, UNIX_TIMESTAMP() FROM `bk_permission` WHERE status = 1;



-- 插入初始邮件模板
-- 简化版本，避免多行文本导致的SQL语法错误
INSERT INTO `bk_email_template` (`name`, `type`, `subject`, `content`, `is_default`, `status`, `created_at`, `updated_at`) VALUES
('注册确认模板', 'registration', '欢迎注册 {site_name}', '欢迎注册 {site_name}！尊敬的 {username}：感谢您注册我们的网站！请点击以下链接确认您的注册：{confirm_link}如果您没有注册我们的网站，请忽略此邮件。-- {site_name} 团队', 1, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
('密码重置模板', 'password_reset', '{site_name} 密码重置请求', '密码重置请求尊敬的 {username}：您收到这封邮件是因为您请求重置密码。请点击以下链接重置您的密码：{reset_link}如果您没有请求重置密码，请忽略此邮件。此链接将在24小时后过期。-- {site_name} 团队', 1, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
('评论通知模板', 'comment_notify', '您的文章收到了新评论', '您的文章收到了新评论尊敬的 {username}：您的文章 {article_title} 收到了新评论。评论内容：{comment_content}-- {site_name} 团队', 1, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
('文章审核通知模板', 'article_approve', '您的文章已审核', '您的文章已审核尊敬的 {username}：您的文章 {article_title} 已审核。审核结果：{status_text}审核意见：{remark}-- {site_name} 团队', 1, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
('管理员通知模板', 'admin_notify', '{site_name} 管理员通知', '管理员通知尊敬的管理员：{message_content}-- {site_name} 系统', 1, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
('用户通知模板', 'user_notify', '{site_name} 通知', '系统通知尊敬的 {username}：{message_content}-- {site_name} 团队', 1, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
('邮箱验证模板', 'email_verification', '邮箱验证 - {site_name}', '邮箱验证尊敬的 {username}：您正在尝试修改邮箱地址，您的验证码是：{verification_code}此验证码将在30分钟后过期，请及时使用。如果您没有操作，请忽略此邮件。-- {site_name} 团队', 1, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

-- 默认文章数据
-- 这些文章会在安装完成后自动导入，让用户一安装即可体验

-- 插入示例文章
-- 使用已存在的默认分类(category_id=1)和管理员用户(user_id=1)

INSERT INTO `bk_article` (`title`, `content`, `description`, `excerpt`, `category_id`, `user_id`, `status`, `view_count`, `top_type`, `created_at`, `updated_at`) VALUES 
-- 第一篇文章：关于BlogKit的介绍
('欢迎使用 BlogKit', '
<p>祝您使用 BlogKit 愉快！🎉</p>
', 'BlogKit 是一款轻量级的博客系统', 'BlogKit 是一款轻量级的博客系统', 1, 1, 1, 15, 1, UNIX_TIMESTAMP() - 86400 * 7, UNIX_TIMESTAMP() - 86400 * 7);

-- 插入初始标签
INSERT INTO `bk_tag` (`name`, `count`, `created_at`) VALUES 
('博客系统', 1, UNIX_TIMESTAMP()),
('BlogKit', 1, UNIX_TIMESTAMP()),
('开源软件', 1, UNIX_TIMESTAMP()),
('个人博客', 1, UNIX_TIMESTAMP());

-- 为示例文章添加标签
INSERT INTO `bk_article_tag` (`article_id`, `tag_id`) VALUES 
(1, 1),
(1, 2),
(1, 3),
(1, 4);

-- 插入示例页面
INSERT INTO `bk_page` (`title`, `content`, `slug`, `status`, `is_menu`, `created_at`, `updated_at`) VALUES 
('关于我们', '<h2>关于我们</h2><p>欢迎访问我们的博客！我们致力于分享有价值的文章和知识。</p><h3>我们的使命</h3><p>通过高质量的内容帮助用户解决问题，提供实用的信息和建议。</p><h3>联系方式</h3><p>如果您有任何问题或建议，欢迎通过以下方式联系我们：</p><ul><li>邮箱：contact@example.com</li><li>社交媒体：@BlogKit</li></ul>', 'about', 1, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),

('隐私政策', '<h2>隐私政策</h2><p>我们重视您的隐私保护，以下是我们的隐私政策。</p><h3>收集的信息</h3><p>当您访问我们的网站时，我们可能会收集一些基本信息，如IP地址、浏览器类型等。</p><h3>信息使用</h3><p>我们收集的信息仅用于改进网站服务和用户体验，不会用于其他目的。</p><h3>信息保护</h3><p>我们采取各种安全措施保护您的信息安全。</p>', 'privacy', 1, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),

('使用条款', '<h2>使用条款</h2><p>请在使用我们的网站前仔细阅读以下条款。</p><h3>内容使用</h3><p>网站上的所有内容仅供个人学习和参考，未经许可不得用于商业目的。</p><h3>用户行为</h3><p>用户在使用网站时应遵守法律法规，不得发布违法或不当内容。</p><h3>责任声明</h3><p>我们对网站内容的准确性和完整性不做任何保证，使用时请自行判断。</p>', 'terms', 1, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

-- 插入IP白名单相关配置
INSERT INTO `bk_config` (`name`, `value`, `description`, `type`) VALUES 
('admin_ip_whitelist_enabled', '0', '后台IP白名单功能是否启用', 'boolean'),
('admin_ip_whitelist', '[]', '后台IP白名单列表（JSON格式）', 'array'),
('admin_ip_trusted_proxies', '[]', '可信代理IP列表（JSON格式）', 'array');
