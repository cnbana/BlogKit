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
 *
 * 本程序为自由软件，依据 GNU General Public License v3.0 (GPLv3) 授权发布：
 * 您可依据协议自由使用、修改与再分发，但依据 GPLv3 第 4 条，
 * 分发时须保留本版权声明与许可声明，并随附协议全文；
 * 本程序不提供任何担保。协议全文：https://www.gnu.org/licenses/gpl-3.0.html
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * BlogKit 一页式安装器
 *
 * 设计原则（轻量级系统配套轻量级安装流程）：
 *   - 单页单步：环境自检 + 数据库信息 + 管理员账号同屏完成，一次提交全部执行；
 *   - 无外部依赖：样式全部内联，安装环境无需网络可达 CDN；
 *   - 安全基线：CSRF 防跨站提交、输入白名单校验、密码仅以单向哈希入库、
 *     安装锁防重复安装、配置文件落盘后收紧权限。
 *
 * 安装执行链（与 core/config/install.sql 的占位符契约一一对应）：
 *   1. 读取 install.sql，替换表前缀（bk_）与站点域名（http://localhost）；
 *   2. 替换管理员占位符 __ADMIN_USERNAME__ / __ADMIN_PASSWORD_HASH__ / __ADMIN_EMAIL__
 *      （独立占位符精确替换，杜绝历史版本全文 str_replace('admin', ...) 的误伤问题）；
 *   3. 逐条执行 SQL 建库建表；
 *   4. 写入 core/config/database.php（var_export 生成，特殊字符安全）；
 *   5. 生成 install.lock 安装锁（仅时间戳）。
 */

// ============================================================================
// 路径常量
// ============================================================================
define('ROOT_PATH', __DIR__);
define('CORE_PATH', ROOT_PATH . '/core');
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('INSTALL_LOCK', ROOT_PATH . '/install.lock');

// ============================================================================
// 会话与安全响应头（CSRF 需要 session；安全头防范点击劫持与 MIME 嗅探）
// ============================================================================
session_start();
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

// CSRF 令牌：GET 渲染表单时生成，POST 提交时校验
if (empty($_SESSION['install_csrf'])) {
    $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
}

// 读取版本号（品牌区展示 + 与 install.sql 版本头一致性的直观确认）
$versionInfo = is_file(CORE_PATH . '/config/version.php') ? include CORE_PATH . '/config/version.php' : [];
$version = $versionInfo['version'] ?? '1.0.0';

// ============================================================================
// 已安装检测：安装锁存在时不再渲染任何表单（防重复安装/防重放安装流程）
// ============================================================================
$installed = is_file(INSTALL_LOCK);

// ============================================================================
// 环境自检（GET/POST 都要执行：POST 失败回显表单时同样展示检测结果）
// $must = true 的项不通过则阻断安装；$must = false 仅为推荐项，缺失时警示
// ============================================================================
$requirements = [
    ['name' => 'PHP 版本 ≥ 7.4',   'must' => true,  'pass' => version_compare(PHP_VERSION, '7.4', '>='),      'current' => PHP_VERSION],
    ['name' => 'MySQLi 扩展',      'must' => true,  'pass' => extension_loaded('mysqli'),                      'current' => extension_loaded('mysqli') ? '已加载' : '未加载'],
    ['name' => 'PDO MySQL 扩展',   'must' => true,  'pass' => extension_loaded('pdo_mysql'),                   'current' => extension_loaded('pdo_mysql') ? '已加载' : '未加载'],
    ['name' => 'GD 扩展（图片处理）',       'must' => false, 'pass' => extension_loaded('gd'),                 'current' => extension_loaded('gd') ? '已加载' : '未加载'],
    ['name' => 'cURL 扩展（在线升级/市场）', 'must' => false, 'pass' => extension_loaded('curl'),               'current' => extension_loaded('curl') ? '已加载' : '未加载'],
    ['name' => 'Fileinfo 扩展（上传检测）',  'must' => false, 'pass' => extension_loaded('fileinfo'),           'current' => extension_loaded('fileinfo') ? '已加载' : '未加载'],
];

// 目录写入权限检测：覆盖安装器写入点 + 系统运行时目录（缺失则尝试自动创建）
$checkDirs = [
    ROOT_PATH,                       // 写 install.lock
    CORE_PATH . '/config',           // 写 database.php
    ROOT_PATH . '/uploads',          // 用户上传目录（与 UPLOADS_PATH 常量保持一致）
    STORAGE_PATH,                    // 运行时数据根（logs/cache 由代码自动补建）
];
$dirWritable = true;
foreach ($checkDirs as $dir) {
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) { $dirWritable = false; continue; }
    if (!is_writable($dir)) { $dirWritable = false; }
}
$requirements[] = ['name' => '目录写入权限', 'must' => true, 'pass' => $dirWritable, 'current' => $dirWritable ? '可写' : '不可写'];

// 必需项全部通过才允许提交安装
$envReady = true;
foreach ($requirements as $req) {
    if ($req['must'] && !$req['pass']) { $envReady = false; break; }
}

// ============================================================================
// 安装执行（仅 POST 且环境就绪时进入；结果回显在同一页面）
// ============================================================================
$error = '';        // 错误信息（回显在表单顶部，输入值保留）
$done = false;      // 安装成功标记（true 时渲染成功卡片）
$adminName = '';    // 成功页回显管理员用户名

// 表单旧值（POST 失败时回填，避免用户重输）
$old = [
    'db_host' => 'localhost', 'db_port' => '3306', 'db_name' => '', 'db_user' => '',
    'db_pass' => '', 'db_prefix' => 'bk_', 'db_charset' => 'utf8mb4',
    'admin_username' => '', 'admin_email' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$installed) {
    // ---- CSRF 校验：拒绝跨站伪造的安装请求 ----
    $csrfOk = isset($_POST['csrf_token'], $_SESSION['install_csrf'])
        && hash_equals($_SESSION['install_csrf'], (string)$_POST['csrf_token']);
    if (!$csrfOk) {
        $error = '页面令牌已失效，请刷新页面后重试';
    } else {
        // ---- 收集输入并回填 ----
        foreach ($old as $k => $v) {
            if (isset($_POST[$k])) { $old[$k] = trim((string)$_POST[$k]); }
        }

        // ---- 输入白名单校验（占位符会进入 SQL 与配置文件，必须收紧字符集）----
        // 数据库名/表前缀/主机名：仅字母数字下划线点，杜绝引号与特殊字符注入
        // 管理员用户名：字母数字下划线 3-32 位；邮箱：标准格式校验
        if ($old['db_name'] === '' || !preg_match('/^[A-Za-z0-9_]+$/', $old['db_name'])) {
            $error = '数据库名称只能包含字母、数字与下划线';
        } elseif (!preg_match('/^[A-Za-z0-9_.\-]+$/', $old['db_host'])) {
            $error = '数据库主机格式不正确';
        } elseif ((int)$old['db_port'] < 1 || (int)$old['db_port'] > 65535) {
            $error = '数据库端口不正确';
        } elseif ($old['db_user'] === '') {
            $error = '请填写数据库用户名';
        } elseif ($old['db_prefix'] !== '' && !preg_match('/^[A-Za-z0-9_]+$/', $old['db_prefix'])) {
            $error = '表前缀只能包含字母、数字与下划线';
        } elseif (!in_array($old['db_charset'], ['utf8mb4', 'utf8'], true)) {
            $error = '字符集仅支持 utf8mb4 或 utf8';
        } elseif (!preg_match('/^[A-Za-z0-9_]{3,32}$/', $old['admin_username'])) {
            $error = '管理员用户名须为 3-32 位字母、数字或下划线';
        } elseif (strlen($_POST['admin_password'] ?? '') < 8) {
            $error = '管理员密码长度至少 8 位';
        } elseif (!filter_var($old['admin_email'], FILTER_VALIDATE_EMAIL)) {
            $error = '管理员邮箱格式不正确';
        } else {
            // ---- 全部校验通过，进入安装执行 ----
            $adminPassword = (string)$_POST['admin_password'];

            try {
                // ① 连接数据库服务器（不选定库），失败给出中文可操作提示
                $conn = @mysqli_connect($old['db_host'], $old['db_user'], $old['db_pass'], '', (int)$old['db_port']);
                if (!$conn) {
                    throw new Exception('无法连接数据库服务器：' . mysqli_connect_error() . '，请检查主机、端口、用户名与密码');
                }

                // ② 选定数据库：优先直接选用（虚拟主机等受限账号常见「库已存在、无建库权限」场景）；
                //    仅当库不存在时才尝试创建，创建失败给出可操作提示
                if (!mysqli_select_db($conn, $old['db_name'])) {
                    $sqlDb = "CREATE DATABASE IF NOT EXISTS `{$old['db_name']}` DEFAULT CHARACTER SET {$old['db_charset']} COLLATE {$old['db_charset']}_unicode_ci";
                    if (!mysqli_query($conn, $sqlDb) || !mysqli_select_db($conn, $old['db_name'])) {
                        throw new Exception('数据库不存在且创建失败：' . mysqli_error($conn) . '，请先在主机面板创建数据库后再安装');
                    }
                }
                mysqli_set_charset($conn, $old['db_charset']);

                // ③ 读取 install.sql 并执行三段替换
                $sqlFile = CORE_PATH . '/config/install.sql';
                if (!is_file($sqlFile)) { throw new Exception('安装脚本缺失：core/config/install.sql 不存在'); }
                $sql = file_get_contents($sqlFile);

                // 表前缀：默认 bk_ 与种子数据/代码引用一致；自定义前缀时全量替换
                $prefix = $old['db_prefix'] !== '' ? $old['db_prefix'] : 'bk_';
                $sql = str_replace('bk_', $prefix, $sql);
                // 站点域名：菜单 path 中的占位域名替换为当前访问域名
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $siteUrl = $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
                $sql = str_replace('http://localhost', $siteUrl, $sql);
                // 管理员信息：独立占位符精确替换（密码仅以单向哈希形态落库）
                $sql = str_replace('__ADMIN_USERNAME__', $old['admin_username'], $sql);
                $sql = str_replace('__ADMIN_PASSWORD_HASH__', password_hash($adminPassword, PASSWORD_DEFAULT), $sql);
                $sql = str_replace('__ADMIN_EMAIL__', $old['admin_email'], $sql);

                // ④ 逐条执行 SQL（install.sql 为自建库脚本，按分号拆分顺序执行）
                $queries = array_filter(array_map('trim', explode(';', $sql)), 'strlen');
                foreach ($queries as $query) {
                    if (!mysqli_query($conn, $query)) {
                        throw new Exception('数据表创建失败：' . mysqli_error($conn));
                    }
                }

                // ⑤ 写入数据库配置文件（var_export 生成合法 PHP 数组，特殊字符安全）
                $dbConfig = [
                    'host'     => $old['db_host'],
                    'port'     => (int)$old['db_port'],
                    'database' => $old['db_name'],
                    'username' => $old['db_user'],
                    'password' => $old['db_pass'],
                    'charset'  => $old['db_charset'],
                    'prefix'   => $prefix,
                ];
                $configContent = "<?php\n// 数据库连接配置（由安装器生成）\nreturn " . var_export($dbConfig, true) . ";\n";
                if (!file_put_contents(CORE_PATH . '/config/database.php', $configContent)) {
                    throw new Exception('无法写入 core/config/database.php，请检查目录写入权限');
                }
                @chmod(CORE_PATH . '/config/database.php', 0644);

                // ⑥ 生成安装锁（仅时间戳，不泄露任何敏感信息）并收尾
                file_put_contents(INSTALL_LOCK, date('Y-m-d H:i:s'));
                @chmod(INSTALL_LOCK, 0644);
                session_destroy();   // 安装流程结束，CSRF 会话使命完成

                $done = true;
                $adminName = $old['admin_username'];
            } catch (Exception $e) {
                // 失败：错误回显表单顶部，输入值保留（$old 已回填）；已建的表不自动清理，
                // 站长可自行 DROP 后重试（避免误删生产数据）
                $error = $e->getMessage();
            }
        }
    }
}

// ============================================================================
// 辅助函数：表单值输出（HTML 转义防 XSS）
// ============================================================================
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>安装 BlogKit <?= e($version) ?></title>
<style>
/* ============================================================
   BlogKit 安装器样式（自包含，无外部依赖）
   设计基调：浅色简洁，与后台登录页一致的居中卡片形态
   ============================================================ */
:root {
    --brand: #2563eb;           /* 品牌主色 */
    --brand-dark: #1d4ed8;      /* 品牌色 hover 态 */
    --ink: #1e293b;             /* 主文字 */
    --ink-2: #64748b;           /* 次级文字 */
    --line: #e2e8f0;            /* 分隔线/描边 */
    --bg: #f1f5f9;              /* 页面底色 */
    --ok: #16a34a;              /* 通过 */
    --warn: #d97706;            /* 推荐项缺失 */
    --bad: #dc2626;             /* 必需项失败 */
    --radius: 12px;
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC",
                 "Hiragino Sans GB", "Microsoft YaHei", sans-serif;
    background: var(--bg);
    color: var(--ink);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px 16px;
    line-height: 1.6;
}
.wrap { width: 100%; max-width: 520px; }

/* 品牌区 */
.brand { text-align: center; margin-bottom: 20px; }
.brand .logo {
    display: inline-block; font-size: 26px; font-weight: 700; letter-spacing: .5px;
    color: var(--brand); text-decoration: none;
}
.brand .logo span { color: var(--ink); }
.brand .ver {
    display: inline-block; margin-left: 8px; font-size: 12px; color: var(--ink-2);
    background: #fff; border: 1px solid var(--line); border-radius: 999px; padding: 1px 10px;
    vertical-align: 3px;
}
.brand p { font-size: 13px; color: var(--ink-2); margin-top: 4px; }

/* 卡片 */
.card {
    background: #fff; border: 1px solid var(--line); border-radius: var(--radius);
    box-shadow: 0 4px 24px rgba(15, 23, 42, .06);
    padding: 28px 28px 24px;
}
.card h1 { font-size: 17px; font-weight: 600; margin-bottom: 4px; }
.card .sub { font-size: 13px; color: var(--ink-2); margin-bottom: 20px; }

/* 环境自检 chips */
.env { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 22px; }
.env .item {
    display: flex; align-items: center; gap: 8px; font-size: 13px;
    border: 1px solid var(--line); border-radius: 8px; padding: 7px 12px; background: #fafbfc;
}
.env .dot { width: 8px; height: 8px; border-radius: 50%; flex: none; }
.env .ok   .dot { background: var(--ok); }
.env .warn .dot { background: var(--warn); }
.env .bad  .dot { background: var(--bad); }
.env .warn { color: var(--warn); }
.env .bad  { color: var(--bad); border-color: #fecaca; background: #fef2f2; }
.env .cur  { color: var(--ink-2); font-size: 12px; margin-left: auto; }
.env-note { font-size: 12px; color: var(--ink-2); margin: -12px 0 18px; }
.env-note b { color: var(--bad); font-weight: 600; }

/* 分组与表单 */
.sec-title {
    display: flex; align-items: center; gap: 6px;
    font-size: 13px; font-weight: 600; color: var(--ink-2);
    margin: 18px 0 10px; text-transform: none;
}
.sec-title::after { content: ""; flex: 1; height: 1px; background: var(--line); }
.grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.field { margin-bottom: 12px; }
.field label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 5px; }
.field .tip { font-weight: 400; color: var(--ink-2); }
input[type=text], input[type=password], input[type=number], input[type=email] {
    width: 100%; font-size: 14px; padding: 9px 12px;
    border: 1px solid var(--line); border-radius: 8px; background: #fff; color: var(--ink);
    outline: none; transition: border-color .15s, box-shadow .15s;
}
input:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(37, 99, 235, .12); }
.pwd-row { position: relative; }
.pwd-row .gen {
    position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
    font-size: 12px; color: var(--brand); background: none; border: none; cursor: pointer; padding: 2px 4px;
}
.pwd-row .gen:hover { text-decoration: underline; }

/* 提交按钮 */
.submit {
    width: 100%; margin-top: 8px; padding: 11px; font-size: 15px; font-weight: 600;
    color: #fff; background: var(--brand); border: none; border-radius: 8px; cursor: pointer;
    transition: background .15s;
}
.submit:hover { background: var(--brand-dark); }
.submit:disabled { background: #94a3b8; cursor: not-allowed; }

/* 错误提示条 */
.alert {
    display: flex; gap: 8px; font-size: 13px; color: var(--bad);
    background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px;
    padding: 10px 12px; margin-bottom: 16px;
}

/* 成功页 */
.done { text-align: center; padding: 12px 0 4px; }
.done .mark {
    width: 64px; height: 64px; margin: 0 auto 16px; border-radius: 50%;
    background: #f0fdf4; color: var(--ok); font-size: 34px; line-height: 64px;
}
.done h1 { font-size: 20px; margin-bottom: 8px; }
.done .who { font-size: 14px; color: var(--ink-2); margin-bottom: 20px; }
.done .who b { color: var(--ink); }
.btn {
    display: inline-block; padding: 10px 28px; font-size: 14px; font-weight: 600;
    color: #fff; background: var(--brand); border-radius: 8px; text-decoration: none;
    transition: background .15s;
}
.btn:hover { background: var(--brand-dark); }
.done .tips { font-size: 12px; color: var(--ink-2); margin-top: 18px; text-align: left;
    border-top: 1px dashed var(--line); padding-top: 14px; }
.done .tips li { margin: 4px 0 4px 16px; }

/* 已安装态 */
.locked { text-align: center; padding: 20px 0; }
.locked .mark { font-size: 40px; margin-bottom: 10px; }
.locked p { font-size: 14px; color: var(--ink-2); margin-bottom: 6px; }
.locked code { background: var(--bg); border: 1px solid var(--line); border-radius: 6px;
    padding: 1px 8px; font-size: 13px; }

footer { text-align: center; font-size: 12px; color: var(--ink-2); margin-top: 18px; }
footer a { color: var(--ink-2); }
@media (max-width: 480px) { .env { grid-template-columns: 1fr; } .grid2 { grid-template-columns: 1fr; } }
</style>
</head>
<body>
<div class="wrap">
    <div class="brand">
        <span class="logo">Blog<span>Kit</span></span><span class="ver">v<?= e($version) ?></span>
        <p>轻量开源博客系统</p>
    </div>

    <div class="card">
<?php if ($installed): ?>
        <!-- 已安装态：不渲染任何表单，杜绝重复安装 -->
        <div class="locked">
            <div class="mark">🔒</div>
            <h1>系统已安装</h1>
            <p>如需重新安装，请先删除站点根目录下的 <code>install.lock</code> 文件。</p>
            <p style="margin-top:10px;">⚠️ 重新安装会清空现有数据，请务必先备份数据库。</p>
        </div>
<?php elseif ($done): ?>
        <!-- 安装成功态 -->
        <div class="done">
            <div class="mark">✓</div>
            <h1>安装完成</h1>
            <p class="who">管理员账号：<b><?= e($adminName) ?></b>（密码为你设置的密码）</p>
            <a class="btn" href="admin.php">进入后台登录</a>
            <ul class="tips">
                <li>建议将 <code>core/config/database.php</code> 权限保持 0644（已自动设置）。</li>
                <li>本安装器在检测到 <code>install.lock</code> 后将拒绝再次运行，无需手动删除。</li>
                <li>遇到问题可访问 <a href="https://www.blogkit.cn/docs" target="_blank" rel="noopener">官方文档</a>。</li>
            </ul>
        </div>
<?php else: ?>
        <!-- 安装表单态（首次进入或 POST 失败回显） -->
        <?php if ($error): ?><div class="alert">✕ <?= e($error) ?></div><?php endif; ?>

        <h1>环境自检</h1>
        <p class="sub">所有必需项通过后即可安装，推荐项缺失不影响运行。</p>
        <div class="env">
        <?php foreach ($requirements as $req): ?>
            <?php
            // 状态分级：必需项未过=bad（阻断）；推荐项未过=warn（警示）；通过=ok
            $cls = $req['pass'] ? 'ok' : ($req['must'] ? 'bad' : 'warn');
            ?>
            <div class="item <?= $cls ?>"><span class="dot"></span><?= e($req['name']) ?><span class="cur"><?= e($req['current']) ?></span></div>
        <?php endforeach; ?>
        </div>
        <?php if (!$envReady): ?>
        <p class="env-note"><b>存在未通过的必需项</b>，请调整服务器环境后刷新本页再安装。</p>
        <?php endif; ?>

        <form method="post" autocomplete="off" id="installForm">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['install_csrf']) ?>">

            <div class="sec-title">数据库</div>
            <div class="grid2">
                <div class="field">
                    <label>主机地址 <span class="tip">（一般无需改动）</span></label>
                    <input type="text" name="db_host" value="<?= e($old['db_host']) ?>" required>
                </div>
                <div class="field">
                    <label>端口 <span class="tip">（默认 3306）</span></label>
                    <input type="text" name="db_port" value="<?= e($old['db_port']) ?>" required>
                </div>
            </div>
            <div class="field">
                <label>数据库名 <span class="tip">（不存在将自动创建）</span></label>
                <input type="text" name="db_name" value="<?= e($old['db_name']) ?>" required placeholder="blogkit">
            </div>
            <div class="grid2">
                <div class="field">
                    <label>用户名</label>
                    <input type="text" name="db_user" value="<?= e($old['db_user']) ?>" required placeholder="root">
                </div>
                <div class="field">
                    <label>密码 <span class="tip">（无密码可留空）</span></label>
                    <input type="password" name="db_pass" value="<?= e($old['db_pass']) ?>">
                </div>
            </div>
            <div class="grid2">
                <div class="field">
                    <label>表前缀 <span class="tip">（保持默认即可）</span></label>
                    <input type="text" name="db_prefix" value="<?= e($old['db_prefix']) ?>">
                </div>
                <div class="field">
                    <label>字符集 <span class="tip">（推荐 utf8mb4）</span></label>
                    <input type="text" name="db_charset" value="<?= e($old['db_charset']) ?>" required>
                </div>
            </div>

            <div class="sec-title">管理员账号</div>
            <div class="field">
                <label>用户名 <span class="tip">（3-32 位字母/数字/下划线）</span></label>
                <input type="text" name="admin_username" value="<?= e($old['admin_username']) ?>" required placeholder="admin">
            </div>
            <div class="field">
                <label>密码 <span class="tip">（至少 8 位）</span></label>
                <div class="pwd-row">
                    <input type="password" name="admin_password" id="adminPassword" required minlength="8" placeholder="">
                    <button type="button" class="gen" onclick="genPwd()">随机生成</button>
                </div>
            </div>
            <div class="field">
                <label>邮箱 <span class="tip">（用于找回密码等）</span></label>
                <input type="email" name="admin_email" value="<?= e($old['admin_email']) ?>" required placeholder="you@example.com">
            </div>

            <button type="submit" class="submit" id="submitBtn" <?= $envReady ? '' : 'disabled' ?>>
                <?= $envReady ? '开始安装' : '环境未就绪，无法安装' ?>
            </button>
        </form>
<?php endif; ?>
    </div>

    <footer>BlogKit · GPLv3 开源 · <a href="https://www.blogkit.cn" target="_blank" rel="noopener">www.blogkit.cn</a></footer>
</div>

<script>
// 随机生成强密码：大小写字母 + 数字 + 符号，16 位（符号避开 SQL/HTML 敏感字符）
function genPwd() {
    var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#$%*?-_=+';
    var pwd = '';
    var rnd = new Uint32Array(16);
    (window.crypto || window.msCrypto).getRandomValues(rnd);
    for (var i = 0; i < 16; i++) pwd += chars.charAt(rnd[i] % chars.length);
    var box = document.getElementById('adminPassword');
    box.value = pwd;
    box.type = 'text';   // 生成后切到明文便于抄录
}
// 提交时禁用按钮防止重复安装请求
document.getElementById('installForm').addEventListener('submit', function () {
    var btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.textContent = '正在安装，请稍候…';
});
</script>
</body>
</html>
