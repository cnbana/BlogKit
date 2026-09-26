-- ============================================================
-- BlogKit 主系统升级 SQL：侧栏新增「系统升级」菜单项
-- 适用：已安装的旧版本库升级（新装库直接执行 core/config/install.sql 即可，勿重复执行本文件）
-- 执行方式（phpstudy MySQL 5.7 示例）：
-- mysql -ublogkit -p blogkit -e "source C:/path/to/upgrade_add_update_menu.sql"
-- 变更内容：
-- 1. bk_permission 新增「系统升级」菜单（与「应用市场」同分组 parent_id=13，
--    code=update，path=admin.php?action=update，图标 refresh）；
--    权限校验沿用 AuthMiddleware::PERMISSION_MAP（update→config），本行仅控制侧栏可见性；
-- 2. 为角色 1（管理员）补授权（INSERT IGNORE 防重复执行）。
-- 幂等：菜单行带 NOT EXISTS 防重，可安全重复执行。
-- ============================================================

-- 1. 新增菜单行（已存在则跳过）
INSERT INTO `bk_permission` (`name`, `code`, `type`, `parent_id`, `path`, `icon`, `sort`, `status`, `created_at`, `updated_at`)
SELECT '系统升级', 'update', 1, 13, 'admin.php?action=update', 'refresh', 5, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `bk_permission` WHERE `code` = 'update');

-- 2. 角色 1（管理员）授权该菜单（与 install.sql 的 INSERT...SELECT 同口径）
INSERT IGNORE INTO `bk_role_permission` (`role_id`, `permission_id`, `created_at`)
SELECT 1, id, UNIX_TIMESTAMP() FROM `bk_permission` WHERE `code` = 'update' AND `status` = 1;
