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
 * BlogKit 全局常量定义文件
 * 
 * 将所有魔法数字、魔法字符串集中定义为常量，
 * 提高代码可读性和可维护性。
 * 
 * 注意：此文件在 autoload.php 之后、Config::init() 之后加载。
 */

// ============================================
// 1. 文章状态常量
// ============================================
define('ARTICLE_STATUS_DRAFT', 0);       // 草稿
define('ARTICLE_STATUS_PUBLISHED', 1);   // 已发布
define('ARTICLE_STATUS_TRASH', 2);       // 回收站

// ============================================
// 2. 文章置顶等级
// ============================================
define('ARTICLE_TOP_NONE', 0);           // 不置顶
define('ARTICLE_TOP_CATEGORY', 1);       // 分类置顶
define('ARTICLE_TOP_HOME', 2);           // 首页置顶
define('ARTICLE_TOP_GLOBAL', 3);         // 全局置顶

// ============================================
// 3. 用户角色常量
// ============================================
define('USER_ROLE_ADMIN', 'admin');
define('USER_ROLE_EDITOR', 'editor');
define('USER_ROLE_AUTHOR', 'author');
define('USER_ROLE_USER', 'user');
define('USER_ROLE_GUEST', 'guest');

// ============================================
// 4. 用户状态常量
// ============================================
define('USER_STATUS_ACTIVE', 1);         // 正常
define('USER_STATUS_INACTIVE', 0);       // 禁用
define('USER_STATUS_BANNED', -1);        // 封禁

// ============================================
// 5. 用户注销状态常量
// ============================================
define('DELETION_STATUS_NONE', 0);       // 未申请注销
define('DELETION_STATUS_PENDING', 1);    // 冷却期内
define('DELETION_STATUS_DELETED', 2);    // 已注销（匿名化）
define('DELETION_COOLDOWN_DAYS', 7);     // 冷却期天数

// ============================================
// 6. 评论状态常量
// ============================================
define('COMMENT_STATUS_PENDING', 0);     // 待审核
define('COMMENT_STATUS_APPROVED', 1);    // 已通过
define('COMMENT_STATUS_REJECTED', 2);    // 已拒绝
define('COMMENT_STATUS_SPAM', 3);        // 垃圾评论

// ============================================
// 7. 软删除标记
// ============================================
define('SOFT_DELETE_NO', 0);
define('SOFT_DELETE_YES', 1);

// ============================================
// 8. 布尔状态通用常量
// ============================================
define('STATUS_DISABLED', 0);
define('STATUS_ENABLED', 1);
define('STATUS_ON', 1);
define('STATUS_OFF', 0);

// ============================================
// 9. 缓存相关常量
// ============================================
define('CACHE_TTL_SHORT', 300);          // 5分钟
define('CACHE_TTL_MEDIUM', 1800);        // 30分钟
define('CACHE_TTL_LONG', 3600);          // 1小时
define('CACHE_TTL_DAY', 86400);          // 1天
define('CACHE_TTL_WEEK', 604800);        // 1周
define('CACHE_TTL_MONTH', 2592000);      // 30天

// ============================================
// 10. 分页常量
// ============================================
define('PAGE_SIZE_DEFAULT', 10);         // 默认每页条数
define('PAGE_SIZE_ADMIN', 20);           // 后台每页条数
define('PAGE_SIZE_API', 15);             // API每页条数

// ============================================
// 11. 文件上传常量
// ============================================
define('UPLOAD_MAX_SIZE_IMAGE', 5242880);     // 图片最大5MB
define('UPLOAD_MAX_SIZE_FILE', 10485760);     // 文件最大10MB
define('UPLOAD_MAX_SIZE_AVATAR', 2097152);    // 头像最大2MB
define('UPLOAD_ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp']);
define('UPLOAD_ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'rar', 'txt']);

// ============================================
// 12. HTTP 状态码常量
// ============================================
define('HTTP_OK', 200);
define('HTTP_CREATED', 201);
define('HTTP_NO_CONTENT', 204);
define('HTTP_BAD_REQUEST', 400);
define('HTTP_UNAUTHORIZED', 401);
define('HTTP_FORBIDDEN', 403);
define('HTTP_NOT_FOUND', 404);
define('HTTP_METHOD_NOT_ALLOWED', 405);
define('HTTP_UNPROCESSABLE_ENTITY', 422);
define('HTTP_TOO_MANY_REQUESTS', 429);
define('HTTP_INTERNAL_ERROR', 500);
define('HTTP_SERVICE_UNAVAILABLE', 503);

// ============================================
// 13. 时间常量（秒）
// ============================================
define('SECONDS_PER_MINUTE', 60);
define('SECONDS_PER_HOUR', 3600);
define('SECONDS_PER_DAY', 86400);
define('SECONDS_PER_WEEK', 604800);
define('SECONDS_PER_MONTH', 2592000);

// ============================================
// 14. CSRF 令牌过期时间
// ============================================
define('CSRF_TOKEN_LIFETIME', 7200);     // 2小时

// ============================================
// 15. 密码相关常量
// ============================================
define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_MAX_LENGTH', 128);
define('USERNAME_MIN_LENGTH', 3);
define('USERNAME_MAX_LENGTH', 20);
define('REMEMBER_TOKEN_DAYS', 30);       // 记住我令牌有效期

// ============================================
// 16. 日志轮转常量
// ============================================
define('LOG_MAX_FILE_SIZE_MB', 10);      // 单个日志文件最大10MB
define('LOG_RETENTION_DAYS', 30);        // 日志保留30天

// ============================================
// 17. 会话常量
// ============================================
define('SESSION_LIFETIME', 1800);        // 30分钟滑动过期
define('SESSION_REMEMBER_LIFETIME', 2592000); // 记住我30天

// ============================================
// 18. 验证码常量
// ============================================
define('CAPTCHA_WIDTH', 120);
define('CAPTCHA_HEIGHT', 40);
define('CAPTCHA_FONT_SIZE', 20);
define('CAPTCHA_LENGTH', 4);
define('CAPTCHA_EXPIRE', 300);           // 验证码5分钟过期

// ============================================
// 19. 阅读历史
// ============================================
define('READ_HISTORY_MAX_COUNT', 100);   // 最大保留数量

// ============================================
// 20. 登录尝试限制
// ============================================
define('LOGIN_MAX_ATTEMPTS', 5);         // 最大登录尝试次数
define('LOGIN_LOCKOUT_MINUTES', 15);     // 锁定时长（分钟）
define('LOGIN_LOCKOUT_SECONDS', 900);    // 锁定时长（秒）
