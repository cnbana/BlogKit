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


/**
 * 数据备份与恢复控制器
 * 功能：数据库备份、文件备份、备份列表管理、一键恢复、自动备份计划
 */
class BackupController {

    /** 备份存储目录 */
    public $backupDir;

    /** 数据库表前缀 */
    private $prefix;

    public function __construct() {
        $this->backupDir = ROOT_PATH . '/storage/backups/';
        $this->prefix = Config::get('database.prefix', 'bk_');
    }

    /**
     * 备份管理首页 - 显示备份列表
     */
    public function index() {
        $this->checkPermission();

        $backups = $this->getBackupList();
        $diskInfo = $this->getDiskInfo();

        // 获取备份配置
        $backupConfig = [
            'auto_backup' => Config::get('backup_auto_enabled', 0),
            'frequency' => Config::get('backup_frequency', 'daily'),
            'retain_count' => Config::get('backup_retain_count', 5),
            'backup_database' => Config::get('backup_include_database', 1),
            'backup_files' => Config::get('backup_include_files', 0),
        ];

        include ADMIN_PATH . '/templates/backup.html';
    }

    /**
     * 创建备份
     */
    public function create() {
        $this->checkPermission();

        $type = isset($_GET['type']) ? $_GET['type'] : 'database';

        try {
            $result = $this->doBackup($type);
            $_SESSION['backup_success'] = ($type === 'full' ? '全量' : ($type === 'files' ? '文件' : '数据库'))
                . '备份创建成功！文件: ' . basename($result['file']);
        } catch (Exception $e) {
            $_SESSION['backup_error'] = '备份失败：' . $e->getMessage();
            error_log('Backup create error: ' . $e->getMessage());
        }

        header('Location: admin.php?action=backup');
        exit;
    }

    /**
     * 恢复备份
     */
    public function restore() {
        $this->checkPermission();

        $file = isset($_GET['file']) ? basename($_GET['file']) : '';

        if (empty($file)) {
            $_SESSION['backup_error'] = '请指定要恢复的备份文件';
            header('Location: admin.php?action=backup');
            exit;
        }

        $backupFile = $this->backupDir . $file;

        if (!file_exists($backupFile)) {
            $_SESSION['backup_error'] = '备份文件不存在';
            header('Location: admin.php?action=backup');
            exit;
        }

        try {
            $this->doRestore($backupFile);
            $_SESSION['backup_success'] = '备份恢复成功！数据已从 ' . $file . ' 恢复';
        } catch (Exception $e) {
            $_SESSION['backup_error'] = '恢复失败：' . $e->getMessage();
            error_log('Backup restore error: ' . $e->getMessage());
        }

        header('Location: admin.php?action=backup');
        exit;
    }

    /**
     * 下载备份文件
     */
    public function download() {
        $this->checkPermission();

        $file = isset($_GET['file']) ? basename($_GET['file']) : '';

        if (empty($file)) {
            die('请指定要下载的文件');
        }

        $backupFile = $this->backupDir . $file;
        if (!file_exists($backupFile)) {
            die('备份文件不存在');
        }

        // 清除输出缓冲
        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($backupFile));
        header('Cache-Control: no-cache, must-revalidate');

        readfile($backupFile);
        exit;
    }

    /**
     * 删除备份文件
     */
    public function delete() {
        $this->checkPermission();

        $file = isset($_GET['file']) ? basename($_GET['file']) : '';

        if (empty($file)) {
            $_SESSION['backup_error'] = '请指定要删除的备份文件';
        } else {
            $backupFile = $this->backupDir . $file;
            if (file_exists($backupFile)) {
                @unlink($backupFile);
                $_SESSION['backup_success'] = '备份文件 ' . $file . ' 已删除';
            } else {
                $_SESSION['backup_error'] = '备份文件不存在';
            }
        }

        header('Location: admin.php?action=backup');
        exit;
    }

    /**
     * 保存备份设置
     */
    public function saveSettings() {
        $this->checkPermission();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: admin.php?action=backup');
            exit;
        }

        $db = Database::getInstance();

        $settings = [
            'backup_auto_enabled' => isset($_POST['auto_backup']) ? 1 : 0,
            'backup_frequency' => $_POST['frequency'] ?? 'daily',
            'backup_retain_count' => (int)($_POST['retain_count'] ?? 5),
            'backup_include_database' => isset($_POST['include_database']) ? 1 : 0,
            'backup_include_files' => isset($_POST['include_files']) ? 1 : 0,
        ];

        foreach ($settings as $key => $value) {
            $existing = $db->fetch("SELECT * FROM {$this->prefix}config WHERE name = ?", [$key]);
            if ($existing) {
                $db->update('config', ['value' => $value], ['name' => $key]);
            } else {
                $db->insert('config', [
                    'name' => $key, 'value' => $value,
                    'type' => is_bool($value) ? 'boolean' : (is_numeric($value) ? 'integer' : 'string'),
                    'description' => '备份配置'
                ]);
            }
            Config::set($key, $value);
        }

        $_SESSION['backup_success'] = '备份设置已保存';
        header('Location: admin.php?action=backup');
        exit;
    }

    // ==================== 内部方法 ====================

    /**
     * 权限检查
     */
    private function checkPermission() {
        if (!isset($_SESSION['admin'])) {
            header('Location: admin.php?action=login');
            exit;
        }

        if (!RoleModel::checkUserPermission($_SESSION['admin']['id'], 'config_save')) {
            die('没有权限执行此操作');
        }
    }

    /**
     * 获取备份列表
     * @return array
     */
    private function getBackupList() {
        $backups = [];
        $files = glob($this->backupDir . 'backup_*');

        foreach ($files as $file) {
            $basename = basename($file);
            // 解析文件名：backup_db_20260523_120000.sql.gz 或 backup_full_20260523_120000.zip
            if (preg_match('/^backup_(db|files|full)_(\d{8})_(\d{6})\.(sql\.gz|zip)$/', $basename, $matches)) {
                $stat = stat($file);
                $backups[] = [
                    'name' => $basename,
                    'type' => $matches[1],
                    'type_label' => $matches[1] === 'db' ? '数据库' : ($matches[1] === 'files' ? '文件' : '全量'),
                    'date' => $matches[2],
                    'time' => $matches[3],
                    'datetime' => substr($matches[2], 0, 4) . '-' . substr($matches[2], 4, 2) . '-' . substr($matches[2], 6, 2)
                        . ' ' . substr($matches[3], 0, 2) . ':' . substr($matches[3], 2, 2) . ':' . substr($matches[3], 4, 2),
                    'size' => $stat['size'],
                    'size_human' => $this->formatSize($stat['size']),
                    'mtime' => $stat['mtime'],
                    'ext' => $matches[4],
                ];
            }
        }

        // 按时间倒序排列
        usort($backups, function($a, $b) {
            return $b['mtime'] - $a['mtime'];
        });

        return $backups;
    }

    /**
     * 执行备份（public 供 cron 脚本调用）
     * @param string $type 备份类型: database, files, full
     * @return array 备份结果
     */
    public function doBackup($type = 'database') {
        $this->ensureBackupDir();
        $timestamp = date('Ymd_His');
        $result = ['success' => false, 'file' => ''];

        switch ($type) {
            case 'database':
                $filename = 'backup_db_' . $timestamp . '.sql.gz';
                $filepath = $this->backupDir . $filename;
                $this->backupDatabase($filepath);
                $result = ['success' => true, 'file' => $filename];
                break;

            case 'files':
                $filename = 'backup_files_' . $timestamp . '.zip';
                $filepath = $this->backupDir . $filename;
                $this->backupFiles($filepath);
                $result = ['success' => true, 'file' => $filename];
                break;

            case 'full':
                $filename = 'backup_full_' . $timestamp . '.zip';
                $filepath = $this->backupDir . $filename;
                $this->backupFull($filepath);
                $result = ['success' => true, 'file' => $filename];
                break;

            default:
                throw new Exception('不支持的备份类型: ' . $type);
        }

        // 生成 MD5 校验文件
        if ($result['success']) {
            $checksum = md5_file($filepath);
            file_put_contents($filepath . '.md5', $checksum);
        }

        // 自动清理旧备份（保留指定份数）
        $this->autoCleanOldBackups();

        // 记录日志
        if (class_exists('Log')) {
            Log::init();
            Log::info("创建{$type}备份: {$filename}", Log::CATEGORY_BACKUP);
        }

        return $result;
    }

    /**
     * 备份数据库到文件
     */
    private function backupDatabase($filepath) {
        $config = Config::get('database');
        $db = Database::getInstance();
        $pdo = $db->getPdo();

        $sql = "-- BlogKit Database Backup\n";
        $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        // 兼容两种配置键名：新版 database.php 用 host/database，旧版用 hostname/dbname
        // （直接读不存在的键会产生 Warning，被全局异常处理器转为异常导致备份失败）
        $sql .= "-- Server: " . (isset($config['host']) ? $config['host'] : (isset($config['hostname']) ? $config['hostname'] : '')) . "\n";
        $sql .= "-- Database: " . (isset($config['dbname']) ? $config['dbname'] : (isset($config['database']) ? $config['database'] : '')) . "\n\n";
        $sql .= "SET NAMES utf8mb4;\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        // 获取所有表
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            // 跳过备份配置表自身（避免恢复时覆盖）
            $sql .= "-- Table: {$table}\n";

            // 导出表结构
            $createTable = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
            $sql .= $createTable['Create Table'] . ";\n\n";

            // 导出表数据
            $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                $sql .= "INSERT INTO `{$table}` VALUES\n";
                $valueSets = [];
                foreach ($rows as $row) {
                    $values = [];
                    foreach ($row as $val) {
                        $values[] = $val === null ? 'NULL' : $pdo->quote($val);
                    }
                    $valueSets[] = '(' . implode(', ', $values) . ')';
                }
                $sql .= implode(",\n", $valueSets) . ";\n\n";
            }
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        // Gzip 压缩
        $fp = gzopen($filepath, 'w9');
        if (!$fp) {
            throw new Exception('无法创建备份文件');
        }
        gzwrite($fp, $sql);
        gzclose($fp);
    }

    /**
     * 备份上传文件
     */
    private function backupFiles($filepath) {
        $dirs = ['uploads'];
        $this->createZipArchive($filepath, $dirs);
    }

    /**
     * 全量备份（数据库 + 文件）
     */
    private function backupFull($filepath) {
        // 先导出数据库到临时文件
        $tmpDb = $this->backupDir . 'tmp_db_' . uniqid() . '.sql';
        $this->backupDatabase($tmpDb);

        $dirs = ['uploads'];

        // 创建 ZIP 包含数据库 SQL
        $this->createZipArchive($filepath, $dirs, [['path' => $tmpDb, 'name' => 'database.sql']]);

        // 清理临时文件
        @unlink($tmpDb);
    }

    /**
     * 创建 ZIP 归档
     */
    private function createZipArchive($zipPath, $dirs, $extraFiles = []) {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception('无法创建 ZIP 文件');
        }

        // 添加额外文件
        foreach ($extraFiles as $file) {
            if (file_exists($file['path'])) {
                $zip->addFile($file['path'], $file['name']);
            }
        }

        // 添加目录
        foreach ($dirs as $dir) {
            $fullDir = ROOT_PATH . '/' . $dir;
            if (!is_dir($fullDir)) continue;

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($fullDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                $localPath = $dir . '/' . $iterator->getSubPathName();
                $localPath = str_replace('\\', '/', $localPath);
                if ($item->isDir()) {
                    $zip->addEmptyDir($localPath);
                } else {
                    $zip->addFile($item->getRealPath(), $localPath);
                }
            }
        }

        $zip->close();
    }

    /**
     * 执行数据恢复
     */
    private function doRestore($backupFile) {
        $ext = pathinfo($backupFile, PATHINFO_EXTENSION);
        $basename = basename($backupFile);

        // 先校验完整性
        $md5File = $backupFile . '.md5';
        if (file_exists($md5File)) {
            $expectedMd5 = trim(file_get_contents($md5File));
            $actualMd5 = md5_file($backupFile);
            if ($expectedMd5 !== $actualMd5) {
                throw new Exception('备份文件校验失败，文件可能已损坏');
            }
        }

        // 根据文件扩展名和前缀处理不同类型的备份
        if (strpos($basename, 'backup_db_') === 0 && $ext === 'gz') {
            $this->restoreDatabase($backupFile);
        } elseif (strpos($basename, 'backup_files_') === 0) {
            $this->restoreFiles($backupFile);
        } elseif (strpos($basename, 'backup_full_') === 0) {
            $this->restoreFull($backupFile);
        } else {
            throw new Exception('无法识别的备份文件类型');
        }

        // 恢复后刷新配置缓存
        Config::loadFromDatabase();

        // 记录日志
        if (class_exists('Log')) {
            Log::init();
            Log::info("恢复备份: {$basename}", Log::CATEGORY_BACKUP);
        }
    }

    /**
     * 恢复数据库备份
     */
    private function restoreDatabase($filepath) {
        // 读取 gzip 压缩的 SQL
        $fp = gzopen($filepath, 'r');
        if (!$fp) {
            throw new Exception('无法读取备份文件');
        }

        $sql = '';
        while (!gzeof($fp)) {
            $sql .= gzread($fp, 8192);
        }
        gzclose($fp);

        $db = Database::getInstance();
        $pdo = $db->getPdo();

        // 分割 SQL 语句
        $statements = $this->splitSqlStatements($sql);

        $pdo->beginTransaction();
        try {
            foreach ($statements as $stmt) {
                $stmt = trim($stmt);
                if (empty($stmt) || strpos($stmt, '--') === 0) continue;
                $pdo->exec($stmt);
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            throw new Exception('数据库恢复失败: ' . $e->getMessage());
        }
    }

    /**
     * 分割 SQL 语句
     */
    private function splitSqlStatements($sql) {
        $statements = [];
        $current = '';
        $lines = explode("\n", $sql);

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (empty($trimmed) || strpos($trimmed, '--') === 0) {
                continue;
            }

            $current .= $line . "\n";
            if (substr($trimmed, -1) === ';') {
                $statements[] = $current;
                $current = '';
            }
        }

        if (!empty(trim($current))) {
            $statements[] = $current;
        }

        return $statements;
    }

    /**
     * 恢复文件备份
     */
    private function restoreFiles($filepath) {
        $zip = new ZipArchive();
        if ($zip->open($filepath) !== true) {
            throw new Exception('无法打开备份文件');
        }

        $zip->extractTo(ROOT_PATH . '/');
        $zip->close();
    }

    /**
     * 恢复全量备份
     */
    private function restoreFull($filepath) {
        // 先恢复文件
        $this->restoreFiles($filepath);

        // 再恢复数据库
        $zip = new ZipArchive();
        if ($zip->open($filepath) !== true) {
            throw new Exception('无法打开备份文件');
        }

        // 提取 database.sql 并恢复
        $tmpSql = $this->backupDir . 'tmp_restore_' . uniqid() . '.sql';
        $dbSql = $zip->getFromName('database.sql');
        if ($dbSql !== false) {
            file_put_contents($tmpSql, $dbSql);

            // 压缩数据库SQL并恢复
            $gzPath = $tmpSql . '.gz';
            $fp = gzopen($gzPath, 'w9');
            gzwrite($fp, $dbSql);
            gzclose($fp);

            $this->restoreDatabase($gzPath);
            @unlink($gzPath);
        }

        $zip->close();
        @unlink($tmpSql);
    }

    /**
     * 确保备份目录和防护文件存在（仅在执行备份时调用）
     */
    private function ensureBackupDir() {
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
        $htaccess = $this->backupDir . '.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Deny from all\n");
        }
        $indexFile = $this->backupDir . 'index.html';
        if (!file_exists($indexFile)) {
            file_put_contents($indexFile, '<html><head><title>403 Forbidden</title></head><body>403 Forbidden</body></html>');
        }
    }

    /**
     * 自动清理旧备份
     */
    private function autoCleanOldBackups() {
        $retainCount = (int)Config::get('backup_retain_count', 5);
        if ($retainCount <= 0) return;

        $backups = $this->getBackupList();
        if (count($backups) <= $retainCount) return;

        // 保留最近的 N 份，删除更旧的
        $toDelete = array_slice($backups, $retainCount);
        foreach ($toDelete as $backup) {
            $filepath = $this->backupDir . $backup['name'];
            @unlink($filepath);
            // 删除对应的 MD5 文件
            @unlink($filepath . '.md5');
        }
    }

    /**
     * 获取磁盘信息
     */
    private function getDiskInfo() {
        $totalSpace = @disk_total_space(ROOT_PATH);
        $freeSpace = @disk_free_space(ROOT_PATH);

        return [
            'total' => $totalSpace ? $this->formatSize($totalSpace) : '未知',
            'free' => $freeSpace ? $this->formatSize($freeSpace) : '未知',
            'used_percent' => ($totalSpace && $freeSpace) ? round(($totalSpace - $freeSpace) / $totalSpace * 100, 1) : 0,
            'backup_dir_size' => $this->formatSize($this->getDirSize($this->backupDir)),
        ];
    }

    /**
     * 格式化文件大小
     */
    private function formatSize($bytes) {
        if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
        return $bytes . ' B';
    }

    /**
     * 获取目录大小
     */
    private function getDirSize($dir) {
        if (!is_dir($dir)) return 0;
        $size = 0;
        foreach (glob(rtrim($dir, '/') . '/*', GLOB_NOSORT) as $file) {
            $size += is_file($file) ? filesize($file) : $this->getDirSize($file);
        }
        return $size;
    }
}
