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
 * 主程序在线升级控制器（U1 一期，方案 blogkit-online-update-plan.md）
 *
 * 站长在后台一键完成主程序升级：下载 → 校验 → 备份 → 覆盖 → 引导 DB 迁移，
 * 替代"红点提醒 + 手动下载覆盖"的旧流程。入口：应用市场页红点横幅「立即升级」。
 *
 * 升级七步（run() 内逐步执行，任一步失败即中止并清理临时文件）：
 *   ① 预检    绑定状态 / 目标版本码仅允许向前 / 磁盘空间 ≥ 包体积×3 / 目录可写
 *   ② 下载    官网一次性令牌（site_key 身份签发）→ storage/tmp/update-{ver}.zip
 *   ③ 校验    sha256 比对官网下发值 + 文件大小 + ZipArchive 完整性
 *             + 逐条 zip-slip / 保护清单预检 + 包内 version.php 版本 == 目标版本
 *   ④ 备份    文件：白名单目录打包 storage/backups/pre-update-*.zip（保留 3 份）
 *             数据库：复用 BackupController::doBackup('database') 全量导出（gzip SQL，
 *             与备份管理页同格式，可在「数据备份」页直接恢复）
 *   ⑤ 覆盖    解压临时目录 → 白名单校验 → 逐条复制覆盖落盘（version.php 随包更新）
 *   ⑥ DB迁移  升级完成页比对 install.sql 目标版本与 bk_config.db_migration_version，
 *             有差异时给出「数据库迁移」页引导链接（迁移引擎 core/lib/Migrate.php）
 *   ⑦ 收尾    删临时文件；bk_config 记 last_update_info（from/to/时间）；清旧备份
 *
 * 安全设计（方案三）：
 * - 保护文件（命中即中止）：config.php / install.lock / .htaccess —— 恶意包特征；
 * - 保护目录（跳过不覆盖）：uploads/ plugins/ themes/ storage/ data/ install/ ——
 *   用户数据与装完即无用的安装器；官方发布包与升级包同源（决策点 3），包内可能
 *   携带这些目录的占位内容，跳过而非中止，避免误杀官方包；
 * - 其余条目（app/ core/ public/ admin/ vendor/ config/ 及根目录文件）允许覆盖；
 * - zip-slip：拒绝 ../ 上跳、绝对路径、盘符，双保险 realpath 复核。
 *
 * 说明：复用 MarketController 的版本检测公开方法 checkNewVersion()（10 分钟缓存），
 * HTTP/解压等辅助方法因原类为 private 而在本类内最小化重写（不改动市场控制器）。
 *
 * @package BlogKit
 */
class UpdateController
{
    /** @var array 保护文件清单（包内根级命中即中止升级，恶意包特征） */
    const PROTECTED_FILES = ['config.php', 'install.lock', '.htaccess'];

    /** @var array 保护目录清单（包内命中跳过不覆盖：用户数据/运行时数据/安装器） */
    const PROTECTED_DIRS = ['uploads', 'plugins', 'themes', 'storage', 'data', 'install'];

    /** @var array 文件备份白名单目录（打包进 pre-update-*.zip 的顶层目录） */
    const BACKUP_DIRS = ['app', 'core', 'public', 'admin', 'vendor'];

    /** @var int pre-update 文件备份保留份数（方案 4：保留最近 3 份） */
    const BACKUP_KEEP = 3;

    /**
     * 入口：按 op 参数分流（'' 升级页 / run 执行升级 / check 手动检测更新）
     * 与 MarketController 同策略：本控制器不在 admin.php 的 SUB_METHOD_CONTROLLERS
     * 白名单内，op 由内部路由，避免改动主系统白名单配置。
     */
    public function index()
    {
        $op = isset($_GET['op']) ? (string)$_GET['op'] : '';
        if ($op === 'run') {
            $this->run();
        } elseif ($op === 'check') {
            $this->checkUpdate();
        } else {
            $this->updatePage();
        }
    }

    /**
     * 手动检测更新（2026-09-26 入口显性化决议③：升级页「检测更新」按钮）
     *
     * 清除 checkNewVersion() 的 bk_config 10 分钟缓存（含失败空结果缓存）后
     * 重新请求官网 latest-version，弹窗反馈结果并回升级页（页面以新缓存重渲染）。
     * POST + CSRF：动作会写 bk_config 缓存键，与升级执行同安全策略。
     */
    private function checkUpdate()
    {
        $back = 'admin.php?action=update';
        if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->showMessage('非法请求', $back);
        }
        if (!Security::validateCsrfToken(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
            $this->showMessage('安全校验失败，请刷新页面后重试', $back);
        }
        $apiUrl = rtrim((string)Config::get('market_api_url', ''), '/');
        $siteKey = (string)Config::get('market_site_key', '');
        if ($apiUrl === '' || $siteKey === '') {
            $this->showMessage('尚未绑定官网站点，请先在「应用市场 → 市场设置」完成绑定', 'admin.php?action=market&op=settings');
        }
        // 清除版本检测缓存（含"失败空结果"缓存），强制下次检测实时请求官网
        $this->saveConfig('market_latest_ver', '');
        $this->saveConfig('market_latest_time', 0);
        try {
            list($latestVersion, $hasNew) = (new MarketController())->checkNewVersion();
        } catch (Exception $e) {
            $latestVersion = '';
            $hasNew = 0;
        }
        if ($hasNew && $latestVersion !== '') {
            $this->showMessage('检测到新版本：BlogKit ' . $latestVersion . '，请点击「开始升级」完成升级', $back);
        }
        $this->showMessage($latestVersion !== ''
            ? '当前已是最新版本 BlogKit ' . $latestVersion
            : '检测失败：无法连接官网，请稍后重试', $back);
    }

    /**
     * 升级页（GET）：当前版本 → 目标版本、绑定状态、备份说明、升级历史、迁移引导
     */
    private function updatePage()
    {
        // 当前版本（core/config/version.php 唯一真相来源）
        $versionInfo = include CORE_PATH . '/config/version.php';
        $currentVersion = isset($versionInfo['version']) ? $versionInfo['version'] : '0.0.0';

        // 绑定状态（未绑定时升级入口不可用，页面给出「市场设置」引导）
        $apiUrl = rtrim((string)Config::get('market_api_url', ''), '/');
        $siteKey = (string)Config::get('market_site_key', '');
        $bound = ($apiUrl !== '' && $siteKey !== '');

        // 目标版本：复用市场控制器版本检测（bk_config 10 分钟缓存，静默降级）
        $latestVersion = '';
        $hasNewVersion = 0;
        if ($bound) {
            try {
                list($latestVersion, $hasNewVersion) = (new MarketController())->checkNewVersion();
            } catch (Exception $e) {
                $latestVersion = '';
                $hasNewVersion = 0;
            }
        }

        // 升级历史（bk_config.last_update_info，一期只读展示最近一次）
        $lastUpdateInfo = null;
        $raw = (string)Config::get('last_update_info', '');
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $lastUpdateInfo = $decoded;
            }
        }

        // DB 迁移检测：install.sql 目标版本 vs bk_config.db_migration_version
        // （与 MigrateController::index 同口径；不一致时页面显示迁移引导）
        list($dbTargetVersion, $dbCurrentVersion) = $this->dbVersionStatus();
        $needMigrate = ($dbTargetVersion !== '' && $dbTargetVersion !== (string)$dbCurrentVersion) ? 1 : 0;

        $currentPage = 'market'; // 侧栏高亮沿用「应用市场」菜单
        $pageTitle = '系统升级';
        include ADMIN_PATH . '/templates/update.html';
    }

    /**
     * 执行升级（POST + CSRF，方案四七步全链路）
     *
     * 任一步失败：删除临时文件 → 弹窗报错说明卡在哪一步（文件/DB 备份均在，
     * 可按「数据备份」页手动恢复）。成功：弹窗提示 → 回升级页（页面自动展示
     * 新版本状态与迁移引导）。
     */
    private function run()
    {
        $back = 'admin.php?action=update';
        if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->showMessage('非法请求', $back);
        }
        // CSRF 校验（与市场设置/主题插件管理同策略）
        if (!Security::validateCsrfToken(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
            $this->showMessage('安全校验失败，请刷新页面后重试', $back);
        }
        // 下载 + 备份 + 覆盖可能超过默认 30 秒执行时限
        @set_time_limit(600);

        $fromVersion = $this->currentVersion();

        // ---------- ① 预检：绑定 / 令牌 / 版本向前 / 磁盘空间 / 可写 ----------
        $apiUrl = rtrim((string)Config::get('market_api_url', ''), '/');
        $siteKey = (string)Config::get('market_site_key', '');
        if ($apiUrl === '' || $siteKey === '') {
            $this->showMessage('尚未绑定官网站点，请先在「应用市场 → 市场设置」完成绑定', 'admin.php?action=market&op=settings');
        }

        // 重新请求 latest-version（携 site_key 换取完整信息 + 一次性下载令牌；
        // 版本检测的 10 分钟缓存不含 sha256/令牌，升级执行必须实时取）
        $resp = $this->httpGet($apiUrl . '/index.php/api/latest-version?site_key=' . urlencode($siteKey));
        $latest = $this->parseApi($resp);
        if ($latest === null || empty($latest['version'])) {
            $this->showMessage('① 预检失败：无法连接官网获取版本信息，请稍后重试', $back);
        }
        $targetVersion = (string)$latest['version'];
        $targetCode = isset($latest['version_code']) ? (int)$latest['version_code'] : 0;
        $expectedSha = isset($latest['sha256']) ? (string)$latest['sha256'] : '';
        $expectedSize = isset($latest['file_size']) ? (int)$latest['file_size'] : 0;
        $downloadToken = isset($latest['download_token']) ? (string)$latest['download_token'] : '';
        // 分发包类型（2026-09-26 双包分发决议①：官网未上传升级差量包时回退完整包）
        $packageType = isset($latest['package_type']) ? (string)$latest['package_type'] : 'full';

        // 版本码仅允许向前（防官网数据异常导致降级覆盖）
        if ($targetCode <= 0 || $targetCode <= $this->currentVersionCode()) {
            $this->showMessage('① 预检失败：目标版本（' . $targetVersion . '）不高于当前版本（' . $fromVersion . '），无需升级', $back);
        }
        // 令牌缺失：官网未成功签发（未绑定/被禁用/表异常）
        if ($downloadToken === '' || !preg_match('/^[a-f0-9]{64}$/', $downloadToken)) {
            $this->showMessage('① 预检失败：未获取到官网下载令牌，请确认站点绑定状态后重试', $back);
        }
        // 磁盘空间 ≥ 包体积×3（升级包 + 解压临时 + 备份余量）
        if ($expectedSize > 0) {
            $free = @disk_free_space(ROOT_PATH);
            if ($free !== false && $free < $expectedSize * 3) {
                $this->showMessage('① 预检失败：磁盘剩余空间不足（需约 ' . round($expectedSize * 3 / 1048576) . ' MB）', $back);
            }
        }
        // 关键目录可写（临时目录、备份目录、站点根）
        if (!$this->ensureDir(STORAGE_PATH . '/tmp') || !$this->ensureDir(STORAGE_PATH . '/backups') || !is_writable(ROOT_PATH)) {
            $this->showMessage('① 预检失败：storage/tmp、storage/backups 或站点根目录不可写，请检查目录权限', $back);
        }

        // ---------- ② 下载：一次性令牌拉取升级包到临时目录 ----------
        $tmpZip = STORAGE_PATH . '/tmp/update-' . $targetVersion . '.zip';
        if (is_file($tmpZip)) {
            @unlink($tmpZip); // 清理上次失败残留（令牌一次性，旧包必已过期）
        }
        $ok = $this->httpDownload($apiUrl . '/index.php/api/mainprogram/download?token=' . urlencode($downloadToken), $tmpZip);
        if (!$ok || !is_file($tmpZip) || filesize($tmpZip) === 0) {
            @unlink($tmpZip);
            $this->showMessage('② 下载失败：升级包下载中断或官网不可达（令牌已作废，请重试升级）', $back);
        }

        // zip 魔数校验：正常 zip 以 PK（0x50 0x4B）开头。
        // 下载虽 HTTP 200，也可能被 WAF/反代/备案拦截页替换成 HTML——此时 sha256
        // 必然不匹配，但「摘要不匹配=包被篡改」的提示会严重误导，先做魔数校验给出直白原因。
        $fh = fopen($tmpZip, 'rb');
        $magic = $fh ? fread($fh, 2) : '';
        if ($fh) {
            fclose($fh);
        }
        if (strlen($magic) < 2 || $magic[0] !== 'P' || $magic[1] !== 'K') {
            $head = mb_substr((string)@file_get_contents($tmpZip, false, null, 0, 120), 0, 120);
            @unlink($tmpZip);
            $this->showMessage('② 下载失败：服务器返回的不是有效升级包（开头：' . $head
                . '）。通常是服务器到官网的网络被防火墙/备案拦截，请稍后重试或检查网络', $back);
        }

        // ---------- ③ 校验：sha256 / 大小 / zip 完整性 / 恶意条目 / 包内版本 ----------
        if ($expectedSize > 0 && filesize($tmpZip) !== $expectedSize) {
            @unlink($tmpZip);
            $this->showMessage('③ 校验失败：升级包大小与官网登记不符（下载不完整）', $back);
        }
        if ($expectedSha !== '' && hash_file('sha256', $tmpZip) !== $expectedSha) {
            @unlink($tmpZip);
            $this->showMessage('③ 校验失败：SHA256 摘要不匹配，升级包可能被篡改（已中止并删除）', $back);
        }
        $zip = new ZipArchive();
        if ($zip->open($tmpZip) !== true) {
            @unlink($tmpZip);
            $this->showMessage('③ 校验失败：升级包不是有效的 zip 文件', $back);
        }
        // 逐条预检：zip-slip / 保护文件命中即中止（此时未落盘任何内容，零风险中止）
        $precheck = $this->precheckZipEntries($zip);
        if ($precheck !== '') {
            $zip->close();
            @unlink($tmpZip);
            $this->showMessage('③ 校验失败：' . $precheck . '（已中止升级并删除升级包）', $back);
        }
        $zip->close();

        // 解压到临时目录（预检已过，extractZipSafe 二次防 zip-slip）并定位包根
        $tmpDir = STORAGE_PATH . '/tmp/update-' . $targetVersion . '-dir';
        if (is_dir($tmpDir)) {
            $this->rrmdir($tmpDir);
        }
        $extractReason = '';
        if (!$this->extractZipSafe($tmpZip, $tmpDir, $extractReason)) {
            @unlink($tmpZip);
            $this->rrmdir($tmpDir);
            $this->showMessage('③ 校验失败：升级包解压失败' . ($extractReason !== '' ? '（' . $extractReason . '）' : '或包含非法路径') . '（已中止）', $back);
        }
        $pkgRoot = $this->locatePackageRoot($tmpDir);
        // 包内 version.php 版本号必须 == 目标版本（防传错包/官网数据错位）
        $pkgVersionFile = $pkgRoot . '/core/config/version.php';
        if (!is_file($pkgVersionFile)) {
            $this->cleanup($tmpZip, $tmpDir);
            $this->showMessage('③ 校验失败：升级包缺少 core/config/version.php（非完整发布包）', $back);
        }
        $pkgVersion = include $pkgVersionFile;
        $pkgVersionStr = is_array($pkgVersion) && isset($pkgVersion['version']) ? (string)$pkgVersion['version'] : '';
        if ($pkgVersionStr !== $targetVersion) {
            $this->cleanup($tmpZip, $tmpDir);
            $this->showMessage('③ 校验失败：包内版本（' . $pkgVersionStr . '）与目标版本（' . $targetVersion . '）不一致', $back);
        }

        // ---------- ④ 备份：白名单文件 zip + DB 全量导出 ----------
        try {
            $backupZip = $this->backupFiles($fromVersion);
            // DB 备份复用数据备份控制器（backup_db_*.sql.gz，备份管理页可列出并恢复）
            $dbBackup = (new BackupController())->doBackup('database');
            $dbBackupName = is_array($dbBackup) && !empty($dbBackup['file']) ? $dbBackup['file'] : '';
        } catch (Exception $e) {
            $this->cleanup($tmpZip, $tmpDir);
            $this->showMessage('④ 备份失败：' . $e->getMessage() . '（已中止升级，系统未做任何修改）', $back);
        }
        // 备份是升级前的最后安全网：任一备份未生成则中止（不允许无备份覆盖）
        if ($backupZip === '' || $dbBackupName === '') {
            $this->cleanup($tmpZip, $tmpDir);
            $this->showMessage('④ 备份失败：文件或数据库备份未能生成（已中止升级，系统未做任何修改）', $back);
        }
        $this->cleanOldBackups(); // 清理 3 份以外的旧 pre-update 备份

        // ---------- ⑤ 覆盖：白名单校验后逐条复制落盘 ----------
        try {
            $copied = $this->applyPackage($pkgRoot);
        } catch (Exception $e) {
            $this->cleanup($tmpZip, $tmpDir);
            $this->showMessage('⑤ 覆盖失败：' . $e->getMessage() . '（可从 storage/backups/ 按提示手动恢复）', $back);
        }

        // 覆盖后复核：version.php 已随包落位为目标版本
        $afterVersion = $this->currentVersion();

        // ---------- ⑦ 收尾：删临时文件 + 记录升级历史 ----------
        $this->cleanup($tmpZip, $tmpDir);
        $this->saveConfig('last_update_info', json_encode([
            'from'    => $fromVersion,
            'to'      => $afterVersion,
            'time'    => date('Y-m-d H:i:s'),
            'files'   => $copied,
            'file_backup'   => basename($backupZip),
            'db_backup'     => $dbBackupName,
            'result' => 1,
        ], JSON_UNESCAPED_UNICODE));

        // 弹窗成功 → 回升级页（页面展示新版本状态 + 按需的迁移引导）
        $this->showMessage('升级成功：' . $fromVersion . ' → ' . $afterVersion
            . '（文件备份 ' . basename($backupZip) . '，数据库备份 ' . $dbBackupName . '，共覆盖 ' . $copied . ' 个文件'
            // 双包回退提示：官网未上传升级差量包时本次实际下发的是完整安装包
            . ($packageType === 'full' ? '；官网未上传升级差量包，本次使用完整包升级' : '')
            . '）', $back);
    }

    // ================================================
    // 私有辅助：版本 / DB 版本状态 / 备份 / 覆盖
    // ================================================

    /**
     * 当前主系统版本号（core/config/version.php 为唯一真相来源）
     */
    private function currentVersion()
    {
        $v = include CORE_PATH . '/config/version.php';
        return isset($v['version']) ? $v['version'] : '0.0.0';
    }

    /**
     * 当前主系统版本码
     */
    private function currentVersionCode()
    {
        $v = include CORE_PATH . '/config/version.php';
        return isset($v['version_code']) ? (int)$v['version_code'] : 0;
    }

    /**
     * DB 版本状态：install.sql 目标版本 vs bk_config.db_migration_version
     *
     * 与 MigrateController::index 同口径：目标版本取 install.sql 前 20 行的
     * "-- 版本：x.x.x" 注释；当前版本读 bk_config。
     *
     * @return array [string 目标版本（读不到为空串）, string 当前版本（未迁移过为空串）]
     */
    private function dbVersionStatus()
    {
        $target = '';
        $sqlFile = CORE_PATH . '/config/install.sql';
        if (file_exists($sqlFile)) {
            $handle = fopen($sqlFile, 'r');
            for ($i = 0; $handle && $i < 20 && !feof($handle); $i++) {
                $line = fgets($handle);
                if ($line === false) {
                    break;
                }
                if (preg_match('/^\s*--\s*版本[：:]\s*([0-9]+\.[0-9]+(?:\.[0-9]+)?)/iu', $line, $m)) {
                    $target = trim($m[1]);
                    break;
                }
            }
            if ($handle) {
                fclose($handle);
            }
        }
        $current = '';
        try {
            $db = Database::getInstance();
            $row = $db->fetch("SELECT `value` FROM `{$db->table('config')}` WHERE `name` = 'db_migration_version' LIMIT 1");
            if ($row) {
                $current = (string)$row['value'];
            }
        } catch (Exception $e) {
            // 查询失败按"未知"处理，页面不强制引导迁移
        }
        return [$target, $current];
    }

    /**
     * zip 条目逐条预检（未解压状态，零落盘风险中止）
     *
     * 规则（方案三 + 决策点 3 同源包适配，详见类注释安全设计）：
     * - zip-slip（../ 上跳 / 绝对路径 / 盘符）→ 返回错误（中止）；
     * - 根级命中保护文件（config.php/install.lock/.htaccess）→ 返回错误（恶意包特征）；
     * - 保护目录（uploads/plugins/themes/storage/data/install）内的条目 → 跳过不算错误；
     * - 其余条目（白名单目录 + 根文件）→ 允许。
     *
     * @param ZipArchive $zip 已打开的 zip
     * @return string 空串=通过；非空=第一个错误描述
     */
    private function precheckZipEntries($zip)
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string)$zip->getNameIndex($i);
            if ($name === '') {
                continue;
            }
            // zip-slip：禁 ../ 上跳、禁绝对路径、禁盘符（Windows）
            if (strpos($name, '..') !== false || $name[0] === '/' || $name[0] === '\\' || preg_match('#^[a-zA-Z]:#', $name)) {
                return '升级包包含非法路径条目（' . $name . '）';
            }
            // 条目内部含反斜杠：Windows 压缩工具产物。Linux 上 extractTo 不会将其视为
            // 目录分隔符，会解压出畸形文件名（官方包一律正斜杠，命中即视为打包异常）
            if (strpos($name, '\\') !== false) {
                return '升级包条目使用了反斜杠路径（' . $name . '），请用标准 zip 工具（正斜杠路径）重新打包';
            }
            $name = str_replace('\\', '/', $name); // Windows 打包的反斜杠归一
            $first = strtolower((string)strtok($name, '/')); // 第一段（目录或根级文件名）
            if ($first === '') {
                continue;
            }
            // 根级保护文件：恶意包试图覆盖用户配置/安装锁 → 中止
            if (strpos($name, '/') === false && in_array($first, self::PROTECTED_FILES, true)) {
                return '升级包试图覆盖受保护文件（' . $name . '）';
            }
            // 保护目录内的条目在覆盖阶段跳过，预检不视为错误（官方包可能带占位内容）
        }
        return '';
    }

    /**
     * 定位包实际根目录：解压根下直接是 core/ 等则用之；
     * 若仅有单个子目录（发布打包带顶层目录形态）则进入该子目录
     *
     * @param string $tmpDir 解压根目录
     * @return string 包根目录
     */
    private function locatePackageRoot($tmpDir)
    {
        $dirs = glob($tmpDir . '/*', GLOB_ONLYDIR);
        $files = glob($tmpDir . '/*');
        if (count($dirs) === 1 && count($files) === 1 && is_file($dirs[0] . '/core/config/version.php')) {
            return $dirs[0]; // 单顶层目录形态
        }
        return $tmpDir;
    }

    /**
     * 文件备份：白名单目录 + 根目录 php 文件打包为 pre-update-{fromVer}-{Ymd_His}.zip
     *
     * zip 内路径相对站点根（还原时直接解压回根目录即可，为二期一键回滚预留格式）。
     *
     * @param string $fromVersion 升级前版本号（用于备份文件名）
     * @return string 备份文件绝对路径（失败抛异常）
     */
    private function backupFiles($fromVersion)
    {
        $zipPath = STORAGE_PATH . '/backups/pre-update-' . $fromVersion . '-' . date('Ymd_His') . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception('无法创建文件备份 zip');
        }
        try {
            // 1) 白名单目录（存在才打包）
            foreach (self::BACKUP_DIRS as $dir) {
                $fullDir = ROOT_PATH . '/' . $dir;
                if (!is_dir($fullDir)) {
                    continue;
                }
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($fullDir, FilesystemIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST
                );
                foreach ($iterator as $item) {
                    $local = $dir . '/' . $iterator->getSubPathName();
                    $local = str_replace('\\', '/', $local);
                    if ($item->isDir()) {
                        $zip->addEmptyDir($local);
                    } elseif (!$zip->addFile($item->getPathname(), $local)) {
                        throw new Exception('备份文件添加失败：' . $local);
                    }
                }
            }
            // 2) 根目录 php 入口文件（index.php / admin.php / api.php 等）
            foreach (glob(ROOT_PATH . '/*.php') as $file) {
                if (!$zip->addFile($file, basename($file))) {
                    throw new Exception('备份文件添加失败：' . basename($file));
                }
            }
            if ($zip->numFiles === 0) {
                throw new Exception('备份内容为空');
            }
        } finally {
            $zip->close();
        }
        return $zipPath;
    }

    /**
     * 清理旧 pre-update 备份（仅保留最近 BACKUP_KEEP 份）
     */
    private function cleanOldBackups()
    {
        $files = glob(STORAGE_PATH . '/backups/pre-update-*.zip');
        if (count($files) <= self::BACKUP_KEEP) {
            return;
        }
        // 按修改时间倒序，删除保留份数之外的更旧备份
        usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        foreach (array_slice($files, self::BACKUP_KEEP) as $file) {
            @unlink($file);
        }
    }

    /**
     * 覆盖落盘：遍历包根，逐条复制到站点根（方案三白名单语义）
     *
     * - 保护目录内条目：跳过（用户数据不触碰）；
     * - 根级保护文件：防御性二次校验，命中抛异常（预检已拦，双保险）；
     * - 其余条目：目录递归创建 + 文件覆盖复制。
     *
     * @param string $pkgRoot 包根目录
     * @return int 实际覆盖的文件数
     * @throws Exception 覆盖失败（写权限/IO 错误/防御校验命中）
     */
    private function applyPackage($pkgRoot)
    {
        $copied = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($pkgRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY // 只迭代文件，目录按需创建
        );
        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }
            $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($pkgRoot) + 1));
            $first = strtolower((string)strtok($relative, '/'));

            // 防御性二次校验：根级保护文件（预检已拦，此处兜底）
            if (strpos($relative, '/') === false && in_array($first, self::PROTECTED_FILES, true)) {
                throw new Exception('升级包试图覆盖受保护文件：' . $relative);
            }
            // 保护目录：跳过（uploads/plugins/themes/storage/data/install 等用户数据/运行时数据）
            if (in_array($first, self::PROTECTED_DIRS, true)) {
                continue;
            }
            // 目标目录确保存在（新版本新增的子目录一并创建）
            $targetDir = dirname(ROOT_PATH . '/' . $relative);
            if (!is_dir($targetDir) && !@mkdir($targetDir, 0755, true)) {
                throw new Exception('目录创建失败：' . $targetDir);
            }
            // 覆盖复制（失败即中止：半覆盖状态有备份兜底，报错提示手动恢复）
            if (!@copy($item->getPathname(), ROOT_PATH . '/' . $relative)) {
                throw new Exception('文件覆盖失败：' . $relative);
            }
            $copied++;
        }
        return $copied;
    }

    /**
     * 清理临时升级文件（zip + 解压目录）
     */
    private function cleanup($tmpZip, $tmpDir)
    {
        if (is_file($tmpZip)) {
            @unlink($tmpZip);
        }
        if (is_dir($tmpDir)) {
            $this->rrmdir($tmpDir);
        }
    }

    /**
     * 确保目录存在（可写）
     *
     * @param string $dir
     * @return bool
     */
    private function ensureDir($dir)
    {
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return false;
        }
        return is_writable($dir);
    }

    /**
     * 保存单项配置到 bk_config（存在则更新，不存在则插入；与 MarketController 同策略）
     *
     * @param string $name  配置键名
     * @param mixed  $value 配置值（标量）
     */
    private function saveConfig($name, $value)
    {
        $db = Database::getInstance();
        $existing = $db->fetch("SELECT id FROM {$db->table('config')} WHERE name = ?", [$name]);
        if ($existing) {
            $db->update('config', ['value' => (string)$value], ['name' => $name]);
        } else {
            $db->insert('config', ['name' => $name, 'value' => (string)$value]);
        }
    }

    /**
     * GET 请求（curl，10 秒超时；与 MarketController::httpGet 同实现）
     *
     * @param string $url
     * @return string|false 响应体（失败 false）
     */
    private function httpGet($url)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        return $body;
    }

    /**
     * 文件下载（curl，300 秒超时适配大包，写临时文件）
     *
     * @param string $url
     * @param string $target 保存路径
     * @return bool 是否成功
     */
    private function httpDownload($url, $target)
    {
        $fp = fopen($target, 'wb');
        if (!$fp) {
            return false;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fp,
            CURLOPT_TIMEOUT        => 300,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false, // 防 SSRF：不跟随跳转
        ]);
        $ok = curl_exec($ch) !== false && curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
        curl_close($ch);
        fclose($fp);
        return $ok;
    }

    /**
     * 解析官网 API JSON 响应（code=0 视为成功；与 MarketController::parseApi 同实现）
     *
     * @param string|false $body
     * @return array|null 成功返回 data 部分，失败 null
     */
    private function parseApi($body)
    {
        if ($body === false || $body === '') {
            return null;
        }
        $json = json_decode($body, true);
        if (!is_array($json) || !isset($json['code']) || (int)$json['code'] !== 0) {
            return null;
        }
        return isset($json['data']) && is_array($json['data']) ? $json['data'] : [];
    }

    /**
     * 安全解压 zip（防 zip-slip：entry 目标路径必须位于解压目录内；
     * 与 MarketController::extractZipSafe 同实现）
     *
     * @param string $zipFile zip 路径
     * @param string $destDir 解压目标目录（自动创建）
     * @param string $reason  失败原因输出（系统层错误等，供调用方展示）
     * @return bool 是否成功
     */
    private function extractZipSafe($zipFile, $destDir, &$reason = '')
    {
        $reason = '';
        if (!class_exists('ZipArchive')) {
            $reason = '服务器未启用 zip 扩展';
            return false;
        }
        $zip = new ZipArchive();
        if ($zip->open($zipFile) !== true) {
            $reason = '升级包无法打开（文件损坏或传输不完整）';
            return false;
        }
        if (!is_dir($destDir) && !mkdir($destDir, 0755, true)) {
            $zip->close();
            $reason = '无法创建临时解压目录：' . $destDir;
            return false;
        }
        // 基准目录 realpath 兜底：部分环境（宝塔 open_basedir + PHP-FPM）对站点 storage
        // 下刚创建的目录 realpath 可能返回 false，退化为词法路径（当前仅分量级校验，
        // 保持与 MarketController::extractZipSafe 的防护基准一致）
        $destReal = realpath($destDir);
        if ($destReal === false) {
            $destReal = $destDir;
        }
        $destReal = rtrim(str_replace('\\', '/', $destReal), '/');
        $ok = true;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', (string)$zip->getNameIndex($i));
            if ($name === '') {
                continue;
            }
            // zip-slip 校验：禁 ../ 上跳、禁绝对路径、禁盘符（Windows）
            if (strpos($name, '..') !== false || $name[0] === '/' || preg_match('#^[a-zA-Z]:#', $name)) {
                $ok = false;
                $reason = '包含非法路径条目（zip-slip 风险）：' . $name;
                break;
            }
        }
        if ($ok && !$zip->extractTo($destDir)) {
            $ok = false;
            // getStatusString() 返回系统层原因（Permission denied / No space left on device）
            $sysErr = method_exists($zip, 'getStatusString') ? (string)$zip->getStatusString() : '';
            $reason = '写入解压文件失败' . ($sysErr !== '' ? '（系统错误：' . $sysErr . '）' : '（检查目录权限或磁盘空间）');
        }
        $zip->close();
        return $ok;
    }

    /**
     * 递归删除目录（清理临时解压目录用）
     *
     * @param string $dir
     * @return bool 是否成功
     */
    private function rrmdir($dir)
    {
        if (!is_dir($dir)) {
            return false;
        }
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        return @rmdir($dir);
    }

    /**
     * 弹窗提示并跳转（与 MarketController::showMessage 同输出形态；
     * 消息内的反斜杠/单引号转义防 JS 语法破坏）
     *
     * @param string $message  提示文案
     * @param string $redirect 跳转地址
     */
    private function showMessage($message, $redirect)
    {
        // 转义消息：反斜杠/单引号防 JS 字符串截断，换行转为 \n 字面量（异常原文可能含换行，
        // 裸换行出现在单引号 JS 字符串中会直接语法错误导致空白页）
        $message = str_replace(['\\', "'", "\r", "\n"], ['\\\\', "\\'", '', '\\n'], $message);
        $redirect = htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8');
        echo "<script>alert('{$message}'); window.location.href = '{$redirect}';</script>";
        exit;
    }
}
