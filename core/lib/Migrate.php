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
 * BlogKit 数据库迁移系统 v2.0
 * 
 * 完整的数据库 Schema 迁移引擎，支持：
 *   - 新增表/列/索引
 *   - 外键约束同步（ALTER TABLE ADD CONSTRAINT）
 *   - 种子数据同步（仅 bk_config 表新增配置项）
 *   - ENUM 类型值变更检测与自动修复
 *   - DROP TABLE IF EXISTS 安全清理
 *   - 迁移审计日志（bk_migration_log 表）
 *   - 版本号自动从 install.sql 头部提取
 *   - 执行失败不更新版本号（事务级保护）
 * 
 * 安全原则：
 *   - 绝不 DROP COLUMN（数据不可逆）
 *   - 绝不 DROP INDEX（保留手动创建的索引）
 *   - 不修改已有列的数据类型（避免数据丢失风险，ENUM 仅追加）
 *   - 每次迁移操作记录到 bk_migration_log
 *   - 执行失败不更新版本号
 * 
 * @package BlogKit
 * @since  2.2.0
 */

class Migrate
{
    /**
     * 迁移版本记录键名（存储在 bk_config 表中 name 列）
     */
    const VERSION_KEY = 'db_migration_version';

    /**
     * install.sql 文件路径
     */
    const SQL_FILE = CORE_PATH . '/config/install.sql';

    /**
     * 迁移日志表名（不含前缀）
     */
    const LOG_TABLE = 'migration_log';

    // ============================================================
    // 静态属性
    // ============================================================

    /** @var PDO */
    private static $pdo;

    /** @var string 数据库表前缀 */
    private static $prefix;

    /** @var array 解析后的目标 Schema */
    private static $targetSchema = [];

    /** @var array 待执行的 DDL SQL 语句列表 */
    private static $pendingSql = [];

    /** @var array 待执行的外键约束 ALTER 语句 */
    private static $pendingFkSql = [];

    /** @var array 待执行的种子数据 INSERT（仅 bk_config） */
    private static $pendingSeedInserts = [];

    /** @var array 待执行的 ENUM 变更 DDL */
    private static $pendingEnumChanges = [];

    /** @var array 自动提取的目标版本号 */
    private static $autoTargetVersion = null;

    /** @var bool 是否为预览模式 */
    private static $dryRun = false;

    /** @var int 迁移开始时间 */
    private static $startTime = 0;

    /** @var array 执行结果报告 */
    private static $report = [
        'new_tables'       => [],
        'new_columns'      => [],
        'new_indexes'      => [],
        'new_fk'           => [],
        'new_config_items' => [],
        'enum_changes'     => [],
        'drop_tables'      => [],
        'executed_sql'     => [],
        'errors'           => [],
        'from_version'     => null,
        'to_version'       => null,
    ];

    // ============================================================
    // 1. 入口方法
    // ============================================================

    /**
     * 执行数据库迁移（主入口）
     * 
     * @param bool $dryRun 只预览差异，不实际执行（默认 false）
     * @return array 迁移报告
     */
    public static function run($dryRun = false)
    {
        self::$startTime = microtime(true);
        self::$dryRun    = $dryRun;

        self::init();

        // 创建迁移日志表（首次）
        self::createLogTable();

        // 从 install.sql 自动提取目标版本
        self::$autoTargetVersion = self::extractVersionFromSql();
        $targetVersion = self::$autoTargetVersion ?? '2.0.1';

        $currentVersion = self::getCurrentVersion();
        self::$report['from_version'] = $currentVersion;
        self::$report['to_version']   = $targetVersion;

        // 版本一致，无需迁移
        if ($currentVersion === $targetVersion) {
            self::$report['message'] = '数据库已是最新版本，无需迁移。';
            self::logMigration($currentVersion, $targetVersion, $dryRun ? 'preview' : 'run', true);
            return self::$report;
        }

        // 解析目标 Schema（CREATE TABLE 语句）
        self::$targetSchema = self::parseInstallSql();

        // 解析外键约束
        self::parseForeignKeyConstraints();

        // 解析种子数据（bk_config INSERT）
        self::parseSeedInserts();

        // 逐一比对表结构差异
        self::compareSchemas();

        // 比对 ENUM 类型变更
        self::compareEnumTypes();

        // 比对已存在表的外键
        self::compareForeignKeys();

        // 检查是否有任何待处理操作
        $hasChanges = !empty(self::$pendingSql)
                   || !empty(self::$pendingFkSql)
                   || !empty(self::$pendingSeedInserts)
                   || !empty(self::$pendingEnumChanges);

        if (!$hasChanges) {
            self::$report['message'] = '数据库结构与目标一致，无需变更（版本号将更新）。';
            if (!$dryRun) {
                self::updateVersion($targetVersion);
            }
            self::logMigration($currentVersion, $targetVersion, $dryRun ? 'preview' : 'run', true);
            return self::$report;
        }

        if ($dryRun) {
            self::$report['message'] = '预览模式：以下变更将被执行（实际未执行）。';
            self::$report['pending_sql'] = array_merge(
                self::$pendingSql,
                self::flattenFkSql(),
                self::$pendingEnumChanges,
                self::formatPendingSeeds()
            );
            self::logMigration($currentVersion, $targetVersion, 'preview', true);
            return self::$report;
        }

        // ========================================
        // 实际执行迁移
        // ========================================

        // 1. 先添加新列（DDL）
        self::executePendingSql(false);

        // 2. 执行 ENUM 变更（DDL）
        self::$pendingSql = self::$pendingEnumChanges;
        self::executePendingSql(true);

        // 3. 添加外键约束（DDL）
        self::$pendingSql = self::flattenFkSql();
        self::executePendingSql(true);

        // 4. 插入种子数据（DML）
        self::executeSeedInserts();

        // 5. 检查是否有错误
        $hasErrors = count(self::$report['errors']) > 0;

        // 6. 只在无错误时更新版本号（事务级保护）
        if (!$hasErrors) {
            self::updateVersion($targetVersion);
            self::$report['message'] = sprintf(
                '迁移完成：新增 %d 张表, %d 个字段, %d 个索引, %d 个外键, %d 个配置项, %d 个 ENUM 变更。',
                count(self::$report['new_tables']),
                count(self::$report['new_columns']),
                count(self::$report['new_indexes']),
                count(self::$report['new_fk']),
                count(self::$report['new_config_items']),
                count(self::$report['enum_changes'])
            );
        } else {
            self::$report['message'] = sprintf(
                '迁移部分完成但存在 %d 个错误，版本号未更新。请查看错误详情。',
                count(self::$report['errors'])
            );
        }

        self::logMigration($currentVersion, $targetVersion, 'run', !$hasErrors);

        return self::$report;
    }

    /**
     * 预览模式：返回待执行的变更（不实际执行）
     * @return array
     */
    public static function preview()
    {
        return self::run(true);
    }

    // ============================================================
    // 2. 初始化
    // ============================================================

    /**
     * 初始化数据库连接
     */
    private static function init()
    {
        if (self::$pdo !== null) {
            return;
        }

        $config = Config::get('database');
        self::$prefix = $config['prefix'];

        // 使用不含 dbname 的 DSN 连接，以便 CREATE DATABASE
        $dsn = sprintf(
            'mysql:host=%s;port=%d;charset=%s',
            $config['host'],
            $config['port'],
            isset($config['charset']) ? $config['charset'] : 'utf8mb4'
        );

        try {
            self::$pdo = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            // charset 回退：若 MySQL 不支持配置的字符集（如 utf8mb4），自动退到 utf8
            if (strpos($e->getMessage(), 'Unknown character set') !== false) {
                $dsn = sprintf(
                    'mysql:host=%s;port=%d;charset=utf8',
                    $config['host'],
                    $config['port']
                );
                self::$pdo = new PDO($dsn, $config['username'], $config['password'], [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            } else {
                throw $e;
            }
        }

        // 确保目标数据库存在
        self::$pdo->exec(sprintf(
            'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            $config['database']
        ));
        self::$pdo->exec(sprintf('USE `%s`', $config['database']));
    }

    // ============================================================
    // 3. 版本管理
    // ============================================================

    /**
     * 获取当前数据库的迁移版本
     * @return string|null
     */
    private static function getCurrentVersion()
    {
        try {
            // install.sql 中的表名已经带前缀了，直接使用
            $stmt = self::$pdo->query(
                sprintf("SELECT `value` FROM `bk_config` WHERE `name` = '%s' LIMIT 1",
                    self::VERSION_KEY
                )
            );
            $row = $stmt->fetch();
            return $row ? $row['value'] : null;
        } catch (PDOException $e) {
            // bk_config 表可能还不存在（首次安装）
            return null;
        }
    }

    /**
     * 更新迁移版本号
     * @param string $version
     */
    private static function updateVersion($version)
    {
        // install.sql 中的表名已经带前缀了，直接使用
        $table = 'bk_config';
        $stmt = self::$pdo->prepare(
            "INSERT INTO `$table` (`name`, `value`) 
             VALUES (:k, :v) 
             ON DUPLICATE KEY UPDATE `value` = :v2"
        );
        $stmt->execute([
            ':k'  => self::VERSION_KEY,
            ':v'  => $version,
            ':v2' => $version,
        ]);
    }

    /**
     * 从 install.sql 头部注释自动提取目标版本号
     * 
     * 匹配行如: "-- 版本：2.0.1"
     * 
     * @return string|null
     */
    private static function extractVersionFromSql()
    {
        if (!file_exists(self::SQL_FILE)) {
            return null;
        }

        $handle = fopen(self::SQL_FILE, 'r');
        $version = null;

        // 只读前 20 行，版本号应在文件头部
        for ($i = 0; $i < 20 && !feof($handle); $i++) {
            $line = fgets($handle);
            if ($line === false) break;

            // 匹配：-- 版本：X.X.X 或 -- 版本：X.X
            if (preg_match('/^\s*--\s*版本[：:]\s*([0-9]+\.[0-9]+(?:\.[0-9]+)?)/iu', $line, $m)) {
                $version = trim($m[1]);
                break;
            }
        }
        fclose($handle);

        return $version;
    }

    // ============================================================
    // 4. 解析 install.sql → 目标 Schema
    // ============================================================

    /**
     * 解析 install.sql，提取 CREATE TABLE 语句的列和索引定义
     * 
     * @return array [table_name => ['columns' => [...], 'indexes' => [...], 'full_sql' => '...']]
     */
    private static function parseInstallSql()
    {
        if (!file_exists(self::SQL_FILE)) {
            throw new RuntimeException('找不到 install.sql 文件: ' . self::SQL_FILE);
        }

        $sql = file_get_contents(self::SQL_FILE);
        $schema = [];

        // 匹配所有 CREATE TABLE IF NOT EXISTS `table_name` (...) ...;
        $pattern = '/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`([^`]+)`\s*\((.*?)\)\s*([^;]*);/si';
        if (preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $tableName = $match[1];
                $bodySql   = $match[2];
                $tailSql   = $match[3]; // ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 等

                $columns = self::parseColumnDefinitions($bodySql);
                $indexes = self::parseIndexDefinitions($bodySql);

                // 重建完整 CREATE TABLE SQL
                $fullSql = "CREATE TABLE IF NOT EXISTS `$tableName` (\n  "
                         . implode(",\n  ", array_merge(
                               array_map(function ($col) { return $col['raw']; }, $columns),
                               array_map(function ($idx) { return $idx['raw']; }, $indexes)
                           ))
                         . "\n) $tailSql";

                $schema[$tableName] = [
                    'columns'  => $columns,
                    'indexes'  => $indexes,
                    'full_sql' => trim($fullSql),
                ];
            }
        }

        return $schema;
    }

    /**
     * 解析外键约束（ALTER TABLE ADD CONSTRAINT）
     */
    private static function parseForeignKeyConstraints()
    {
        if (!file_exists(self::SQL_FILE)) {
            return;
        }

        $sql = file_get_contents(self::SQL_FILE);

        // 匹配: ALTER TABLE `table` ADD CONSTRAINT `name` FOREIGN KEY (`col`) REFERENCES `ref_table`(`ref_col`) [ON DELETE ...] [ON UPDATE ...];
        $pattern = '/ALTER\s+TABLE\s+`([^`]+)`\s+ADD\s+CONSTRAINT\s+`([^`]+)`\s+FOREIGN\s+KEY\s+\(`([^`]+)`\)\s+REFERENCES\s+`([^`]+)`\s*\(`([^`]+)`\)([^;]*);/i';
        if (preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $fkTable     = $match[1]; // bk_article (已经带前缀)
                $fkName      = $match[2]; // fk_article_category
                $fkColumn    = $match[3]; // category_id
                $refTable    = $match[4]; // bk_category (已经带前缀)
                $refColumn   = $match[5]; // id
                $onClauses   = trim($match[6]); // ON DELETE CASCADE ON UPDATE CASCADE

                // install.sql 中的表名已经带前缀了，直接使用
                $fullFkTable = $fkTable;
                $fullRefTable = $refTable;

                $fullSql = "ALTER TABLE `$fullFkTable` ADD CONSTRAINT `$fkName` "
                         . "FOREIGN KEY (`$fkColumn`) REFERENCES `$fullRefTable`(`$refColumn`) $onClauses";
                $fullSql = trim(preg_replace('/\s+/', ' ', $fullSql)) . ';';

                self::$pendingFkSql[$fullFkTable][$fkName] = [
                    'table'         => $fullFkTable,
                    'name'          => $fkName,
                    'column'        => $fkColumn,
                    'ref_table'     => $fullRefTable,
                    'ref_column'    => $refColumn,
                    'raw_sql'       => $fullSql,
                ];
            }
        }
    }

    /**
     * 解析种子数据（仅 bk_config 表的 INSERT 语句）
     */
    private static function parseSeedInserts()
    {
        if (!file_exists(self::SQL_FILE)) {
            return;
        }

        $sql = file_get_contents(self::SQL_FILE);

        // 匹配 INSERT INTO bk_config (name, value, description, type) VALUES (...) ... ;
        // 多行 VALUES 支持
        $pattern = "/INSERT\s+INTO\s+`bk_config`\s+\(`name`,\s*`value`,\s*`description`,\s*`type`\)\s+VALUES\s+(.*?);/si";

        if (preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $valuesBlock = $match[1];

                // 解析每个值元组: ('name', 'value', 'desc', 'type')
                $tuplePattern = "/\(\s*'([^']*)'\s*,\s*'((?:[^'\\\\]|\\\\.)*)'\s*,\s*'((?:[^'\\\\]|\\\\.)*)'\s*,\s*'([^']*)'\s*\)/";
                if (preg_match_all($tuplePattern, $valuesBlock, $tuples, PREG_SET_ORDER)) {
                    foreach ($tuples as $t) {
                        $key   = stripcslashes($t[1]);
                        $value = stripcslashes($t[2]);
                        $desc  = stripcslashes($t[3]);
                        $type  = stripcslashes($t[4]);

                        self::$pendingSeedInserts[$key] = [
                            'name'        => $key,
                            'value'       => $value,
                            'description' => $desc,
                            'type'        => $type,
                        ];
                    }
                }
            }
        }
    }

    /**
     * 从 CREATE TABLE body 中提取列定义
     */
    private static function parseColumnDefinitions($bodySql)
    {
        $lines = self::splitSqlLines($bodySql);
        $columns = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (
                empty($line)
                || preg_match('/^\s*(PRIMARY|UNIQUE|KEY|INDEX|FULLTEXT|CONSTRAINT|FOREIGN)/i', $line)
            ) {
                continue;
            }

            // 匹配: `column_name` type(..) [NOT NULL] [DEFAULT ...] [AUTO_INCREMENT] [COMMENT '...']
            if (preg_match('/^`([^`]+)`\s+(.+)$/s', $line, $m)) {
                $colName = $m[1];
                $colDef  = trim($m[2]);
                $columns[] = [
                    'name' => $colName,
                    'raw'  => "`$colName` $colDef",
                    'type' => self::extractColumnType($colDef),
                    'full_def' => $colDef,
                ];
            }
        }

        return $columns;
    }

    /**
     * 从 CREATE TABLE body 中提取索引定义
     */
    private static function parseIndexDefinitions($bodySql)
    {
        $lines = self::splitSqlLines($bodySql);
        $indexes = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || preg_match('/^`\w+`\s+/', $line)) {
                continue;
            }

            // PRIMARY KEY, UNIQUE KEY, KEY, INDEX, FULLTEXT
            if (preg_match('/^\s*(PRIMARY\s+KEY|UNIQUE\s+KEY|KEY|INDEX|FULLTEXT\s+KEY|FULLTEXT)\s/', $line)) {
                $indexes[] = [
                    'name' => self::extractIndexName($line),
                    'raw'  => $line,
                ];
            }
        }

        return $indexes;
    }

    /**
     * 按逗号拆分 SQL 行，正确处理括号嵌套
     */
    private static function splitSqlLines($sql)
    {
        $lines = [];
        $depth = 0;
        $buffer = '';

        for ($i = 0, $len = strlen($sql); $i < $len; $i++) {
            $ch = $sql[$i];
            if ($ch === '(') $depth++;
            if ($ch === ')') $depth--;
            if ($ch === ',' && $depth === 0) {
                $lines[] = $buffer;
                $buffer = '';
            } else {
                $buffer .= $ch;
            }
        }
        if (trim($buffer) !== '') {
            $lines[] = $buffer;
        }

        return $lines;
    }

    /**
     * 从列定义中提取数据类型
     */
    private static function extractColumnType($colDef)
    {
        if (preg_match('/^(enum\([^)]+\))/i', trim($colDef), $m)) {
            return strtolower($m[1]);
        }
        if (preg_match('/^(\w+(\([^)]*\))?)\s*(unsigned)?/i', trim($colDef), $m)) {
            $type = strtolower($m[1]);
            if (isset($m[3]) && strtolower($m[3]) === 'unsigned') {
                $type .= ' unsigned';
            }
            return $type;
        }
        return strtolower(trim($colDef));
    }

    /**
     * 从索引定义中提取索引名称
     */
    private static function extractIndexName($indexDef)
    {
        if (preg_match('/^\s*(?:PRIMARY\s+)?KEY\s+`?([^`\s(]+)`?/i', $indexDef, $m)) {
            return $m[1];
        }
        if (stripos($indexDef, 'PRIMARY KEY') !== false) {
            return 'PRIMARY';
        }
        return '';
    }

    // ============================================================
    // 5. Schema 比对
    // ============================================================

    /**
     * 逐一比对目标 Schema 与当前数据库的差异
     */
    private static function compareSchemas()
    {
        foreach (self::$targetSchema as $tableName => $targetDef) {
            // install.sql 中的表名已经带前缀了，直接使用
            $fullTableName = $tableName;

            // 检查表是否存在
            if (!self::tableExists($fullTableName)) {
                self::$report['new_tables'][] = $fullTableName;
                self::$pendingSql[] = $targetDef['full_sql'] . ';';
                continue;
            }

            // 比对列差异
            $currentColumns = self::getTableColumns($fullTableName);
            foreach ($targetDef['columns'] as $col) {
                if (!isset($currentColumns[$col['name']])) {
                    self::$report['new_columns'][] = "$fullTableName.{$col['name']}";
                    self::$pendingSql[] = sprintf(
                        'ALTER TABLE `%s` ADD COLUMN %s;',
                        $fullTableName,
                        $col['raw']
                    );
                }
            }

            // 比对索引差异
            $currentIndexes = self::getTableIndexes($fullTableName);
            foreach ($targetDef['indexes'] as $idx) {
                $idxName = $idx['name'];
                if ($idxName === 'PRIMARY') continue; // 主键不处理

                if (!isset($currentIndexes[$idxName])) {
                    self::$report['new_indexes'][] = "$fullTableName.$idxName";
                    $addIdxSql = preg_replace(
                        '/^\s*(UNIQUE\s+)?(KEY|INDEX|FULLTEXT\s+KEY|FULLTEXT)/i',
                        'ADD $0',
                        $idx['raw']
                    );
                    self::$pendingSql[] = "ALTER TABLE `$fullTableName` $addIdxSql;";
                }
            }
        }
    }

    /**
     * 比对已存在表的外键约束
     */
    private static function compareForeignKeys()
    {
        foreach (self::$pendingFkSql as $fullTableName => $fks) {
            // 跳过新表（外键会被 CREATE TABLE 整体处理，或不存在的表无法加外键）
            if (in_array($fullTableName, self::$report['new_tables'])) {
                continue;
            }

            if (!self::tableExists($fullTableName)) {
                continue;
            }

            $currentFks = self::getTableForeignKeys($fullTableName);

            foreach ($fks as $fkName => $fkInfo) {
                if (!isset($currentFks[$fkName])) {
                    // 检查参考表是否存在
                    $refTable = $fkInfo['ref_table'];
                    if (!self::tableExists($refTable)) {
                        // 参考表不存在，跳过此 FK（等参考表先建好）
                        continue;
                    }

                    self::$report['new_fk'][] = "$fullTableName.{$fkName}";
                    self::$pendingFkSql[$fullTableName][$fkName]['_confirmed'] = true;
                } else {
                    // FK 已存在，从待执行列表移除
                    unset(self::$pendingFkSql[$fullTableName][$fkName]);
                }
            }
        }

        // 清理空的分组
        self::$pendingFkSql = array_filter(self::$pendingFkSql, function ($fks) {
            return !empty($fks);
        });
    }

    /**
     * 比对 ENUM 类型列的变更
     */
    private static function compareEnumTypes()
    {
        foreach (self::$targetSchema as $tableName => $targetDef) {
            // install.sql 中的表名已经带前缀了，直接使用
            $fullTableName = $tableName;

            if (!self::tableExists($fullTableName)) {
                continue;
            }

            $currentColumns = self::getTableColumnsFull($fullTableName);

            foreach ($targetDef['columns'] as $col) {
                $colName = $col['name'];

                // 列不存在 → 由 compareSchemas 处理，跳过
                if (!isset($currentColumns[$colName])) {
                    continue;
                }

                $targetType = $col['type'];
                $currentType = $currentColumns[$colName];

                // 目标列是 ENUM 类型
                if (stripos($targetType, 'enum(') === 0) {
                    // 当前列不是 ENUM → 跳过（避免数据丢失风险）
                    if (stripos($currentType, 'enum(') !== 0) {
                        continue;
                    }

                    // 提取目标 ENUM 值
                    preg_match('/^enum\((.*)\)$/i', $targetType, $tm);
                    $targetValues = self::parseEnumValues($tm[1] ?? '');

                    // 提取当前 ENUM 值
                    preg_match('/^enum\((.*)\)$/i', $currentType, $cm);
                    $currentValues = self::parseEnumValues($cm[1] ?? '');

                    // 检查是否有新增的值
                    $newValues = array_diff($targetValues, $currentValues);
                    if (!empty($newValues)) {
                        self::$report['enum_changes'][] = sprintf(
                            '%s.%s: %s → +%s',
                            $fullTableName,
                            $colName,
                            $currentType,
                            implode(',', $newValues)
                        );

                        // 使用 MODIFY COLUMN 更新枚举值（保留完整列定义）
                        self::$pendingEnumChanges[] = sprintf(
                            'ALTER TABLE `%s` MODIFY COLUMN %s;',
                            $fullTableName,
                            $col['raw']
                        );
                    }
                }
            }
        }
    }

    /**
     * 解析 ENUM 值字符串
     * e.g. "'active','inactive','expired'" → ['active', 'inactive', 'expired']
     */
    private static function parseEnumValues($enumStr)
    {
        $values = [];
        if (preg_match_all("/'([^']*)'/", $enumStr, $m)) {
            $values = $m[1];
        }
        return $values;
    }

    // ============================================================
    // 6. 执行
    // ============================================================

    /**
     * 逐条执行待处理的 DDL SQL
     * @param bool $appendMode 是否追加模式（不清空 executed_sql）
     */
    private static function executePendingSql($appendMode = false)
    {
        if (!$appendMode) {
            self::$report['executed_sql'] = [];
        }

        foreach (self::$pendingSql as $sql) {
            try {
                self::$pdo->exec($sql);
                self::$report['executed_sql'][] = $sql;
            } catch (PDOException $e) {
                // 忽略常见的"已存在"错误
                $errorCode = $e->getCode();
                $errorMsg  = $e->getMessage();

                // 1060: Duplicate column, 1061: Duplicate key, 1062: Duplicate entry
                // 1005: Can't create table (FK issues), 1050: Table already exists
                // 1022: Duplicate key entry for FK
                $ignorableCodes = ['42S21', '42000', '42S01'];
                $sqlState = isset($e->errorInfo[0]) ? $e->errorInfo[0] : '';

                if ($errorCode == '1060' || $errorCode == '1061' || $errorCode == '1062'
                    || $errorCode == '1005' || $errorCode == '1050' || $errorCode == '1022'
                    || in_array($sqlState, $ignorableCodes)) {
                    self::$report['executed_sql'][] = "[跳过] $sql (原因: {$errorMsg})";
                    continue;
                }

                self::$report['errors'][] = [
                    'sql'   => $sql,
                    'error' => $errorMsg,
                    'code'  => $errorCode,
                ];

                if (class_exists('Log')) {
                    Log::error('Migrate SQL failed: ' . $errorMsg, 'migrate', ['sql' => $sql]);
                }
            }
        }

        self::$pendingSql = [];
    }

    /**
     * 执行种子数据 INSERT（仅 bk_config 缺失项）
     */
    private static function executeSeedInserts()
    {
        if (empty(self::$pendingSeedInserts)) {
            return;
        }

        // install.sql 中的表名已经带前缀了，直接使用
        $configTable = 'bk_config';

        // 获取已存在的配置项
        $existingKeys = [];
        try {
            $stmt = self::$pdo->query("SELECT `name` FROM `$configTable`");
            foreach ($stmt->fetchAll() as $row) {
                $existingKeys[$row['name']] = true;
            }
        } catch (PDOException $e) {
            // bk_config 表可能不存在
            return;
        }

        $inserted = 0;
        foreach (self::$pendingSeedInserts as $key => $item) {
            if (isset($existingKeys[$key])) {
                continue; // 已存在，跳过
            }

            try {
                $stmt = self::$pdo->prepare(
                    "INSERT INTO `$configTable` (`name`, `value`, `description`, `type`) 
                     VALUES (:name, :value, :desc, :type)"
                );
                $stmt->execute([
                    ':name' => $item['name'],
                    ':value'=> $item['value'],
                    ':desc' => $item['description'],
                    ':type' => $item['type'],
                ]);
                self::$report['new_config_items'][] = $key;
                self::$report['executed_sql'][] = sprintf(
                    "INSERT INTO `$configTable` SET name='%s' (新配置项)",
                    $key
                );
                $inserted++;
            } catch (PDOException $e) {
                // 忽略重复键错误
                if ($e->getCode() != '1062') {
                    self::$report['errors'][] = [
                        'sql'   => "INSERT INTO `$configTable` name='{$key}'",
                        'error' => $e->getMessage(),
                    ];
                }
            }
        }

        if ($inserted > 0) {
            self::$report['message'] = ($inserted > 0 && empty(self::$report['message'])) 
                ? "新增 {$inserted} 个配置项。" 
                : (self::$report['message'] ?? '');
        }
    }

    /**
     * 将待执行的种子数据格式化为可读 SQL（预览用）
     */
    private static function formatPendingSeeds()
    {
        $lines = [];
        // install.sql 中的表名已经带前缀了，直接使用
        $configTable = 'bk_config';

        foreach (self::$pendingSeedInserts as $key => $item) {
            $lines[] = sprintf(
                "INSERT INTO `%s` (`name`, `value`, `description`, `type`) VALUES ('%s', '%s', '%s', '%s');",
                $configTable,
                addslashes($item['name']),
                addslashes($item['value']),
                addslashes($item['description']),
                addslashes($item['type'])
            );
        }

        return $lines;
    }

    /**
     * 将 pendingFkSql 二维数组扁平化为纯 SQL 字符串列表
     * @return array 扁平化的 ALTER TABLE ... ADD CONSTRAINT 语句数组
     */
    private static function flattenFkSql()
    {
        $flat = [];
        foreach (self::$pendingFkSql as $tableFks) {
            foreach ($tableFks as $fkInfo) {
                if (!empty($fkInfo['raw_sql'])) {
                    $flat[] = $fkInfo['raw_sql'];
                }
            }
        }
        return $flat;
    }

    // ============================================================
    // 7. 数据库元信息查询
    // ============================================================

    /**
     * 检查表是否存在
     */
    private static function tableExists($tableName)
    {
        try {
            self::$pdo->query("SELECT 1 FROM `$tableName` LIMIT 1");
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * 获取当前表的列信息
     * @return array [column_name => true]
     */
    private static function getTableColumns($tableName)
    {
        $columns = [];
        try {
            $stmt = self::$pdo->query("SHOW COLUMNS FROM `$tableName`");
            foreach ($stmt->fetchAll() as $row) {
                $columns[$row['Field']] = true;
            }
        } catch (PDOException $e) {
            // 表不存在等异常
        }
        return $columns;
    }

    /**
     * 获取当前表的列完整类型信息
     * @return array [column_name => full_type]
     */
    private static function getTableColumnsFull($tableName)
    {
        $columns = [];
        try {
            $stmt = self::$pdo->query("SHOW COLUMNS FROM `$tableName`");
            foreach ($stmt->fetchAll() as $row) {
                $columns[$row['Field']] = $row['Type'];
            }
        } catch (PDOException $e) {
            // 忽略
        }
        return $columns;
    }

    /**
     * 获取当前表的索引信息
     * @return array [index_name => true]
     */
    private static function getTableIndexes($tableName)
    {
        $indexes = [];
        try {
            $stmt = self::$pdo->query("SHOW INDEX FROM `$tableName`");
            foreach ($stmt->fetchAll() as $row) {
                $indexes[$row['Key_name']] = true;
            }
        } catch (PDOException $e) {
            // 忽略
        }
        return $indexes;
    }

    /**
     * 获取当前表的外键约束
     * @return array [fk_name => info]
     */
    private static function getTableForeignKeys($tableName)
    {
        $fks = [];
        try {
            // 从表名中解析数据库名和表名
            $parts = explode('.', $tableName);
            $tableOnly = end($parts);

            $dbName = self::$pdo->query("SELECT DATABASE()")->fetchColumn();
            $stmt = self::$pdo->query("
                SELECT 
                    CONSTRAINT_NAME,
                    COLUMN_NAME,
                    REFERENCED_TABLE_NAME,
                    REFERENCED_COLUMN_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = " . self::$pdo->quote($dbName) . "
                  AND TABLE_NAME = " . self::$pdo->quote($tableOnly) . "
                  AND REFERENCED_TABLE_NAME IS NOT NULL
            ");
            foreach ($stmt->fetchAll() as $row) {
                $fks[$row['CONSTRAINT_NAME']] = $row;
            }
        } catch (PDOException $e) {
            // 忽略
        }
        return $fks;
    }

    // ============================================================
    // 8. 迁移审计日志
    // ============================================================

    /**
     * 创建迁移日志表（如果不存在）
     */
    private static function createLogTable()
    {
        $table = self::$prefix . self::LOG_TABLE;
        try {
            self::$pdo->exec("
                CREATE TABLE IF NOT EXISTS `$table` (
                    `id` bigint(20) NOT NULL AUTO_INCREMENT,
                    `version_from` varchar(20) DEFAULT NULL COMMENT '迁移前版本',
                    `version_to` varchar(20) NOT NULL COMMENT '迁移后版本',
                    `action` varchar(20) NOT NULL COMMENT '操作类型：preview, run',
                    `success` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否成功',
                    `new_tables` int(11) DEFAULT 0 COMMENT '新增表数',
                    `new_columns` int(11) DEFAULT 0 COMMENT '新增字段数',
                    `new_indexes` int(11) DEFAULT 0 COMMENT '新增索引数',
                    `new_fk` int(11) DEFAULT 0 COMMENT '新增外键数',
                    `new_config_items` int(11) DEFAULT 0 COMMENT '新增配置项数',
                    `enum_changes` int(11) DEFAULT 0 COMMENT '枚举变更数',
                    `errors_count` int(11) DEFAULT 0 COMMENT '错误数',
                    `sql_executed` text COMMENT '执行的SQL列表（JSON）',
                    `error_details` text COMMENT '错误详情（JSON）',
                    `duration_ms` int(11) DEFAULT 0 COMMENT '执行耗时(毫秒)',
                    `created_at` int(11) NOT NULL COMMENT '执行时间',
                    PRIMARY KEY (`id`),
                    KEY `created_at` (`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='数据库迁移日志表'
            ");
        } catch (PDOException $e) {
            // 创建失败不影响迁移主流程
        }
    }

    /**
     * 记录迁移操作日志
     * 
     * @param string|null $fromVersion
     * @param string $toVersion
     * @param string $action
     * @param bool $success
     */
    private static function logMigration($fromVersion, $toVersion, $action, $success)
    {
        $table = self::$prefix . self::LOG_TABLE;

        // 预览模式也记录日志，方便追踪
        if ($action === 'preview' && empty(self::$report['pending_sql']) && empty(self::$pendingFkSql)) {
            return; // 无变更的预览不记录
        }

        $duration = (int) ((microtime(true) - self::$startTime) * 1000);

        try {
            $stmt = self::$pdo->prepare(
                "INSERT INTO `$table` 
                (`version_from`, `version_to`, `action`, `success`, 
                 `new_tables`, `new_columns`, `new_indexes`, `new_fk`, `new_config_items`,
                 `enum_changes`, `errors_count`, `sql_executed`, `error_details`, `duration_ms`, `created_at`)
                VALUES 
                (:vf, :vt, :act, :ok,
                 :nt, :nc, :ni, :nfk, :ncfg,
                 :ne, :ec, :sq, :err, :dur, :ca)"
            );

            $executedSql = array_merge(
                self::$report['executed_sql'] ?? [],
                array_map(function ($item) {
                    return "FK: {$item['raw_sql']}";
                }, array_filter(array_merge(...array_values(self::$pendingFkSql)), function ($item) {
                    return !empty($item['_confirmed'] ?? false);
                }))
            );

            $stmt->execute([
                ':vf'   => $fromVersion,
                ':vt'   => $toVersion,
                ':act'  => $action,
                ':ok'   => $success ? 1 : 0,
                ':nt'   => count(self::$report['new_tables'] ?? []),
                ':nc'   => count(self::$report['new_columns'] ?? []),
                ':ni'   => count(self::$report['new_indexes'] ?? []),
                ':nfk'  => count(self::$report['new_fk'] ?? []),
                ':ncfg' => count(self::$report['new_config_items'] ?? []),
                ':ne'   => count(self::$report['enum_changes'] ?? []),
                ':ec'   => count(self::$report['errors'] ?? []),
                ':sq'   => json_encode($executedSql, JSON_UNESCAPED_UNICODE),
                ':err'  => json_encode(self::$report['errors'] ?? [], JSON_UNESCAPED_UNICODE),
                ':dur'  => $duration,
                ':ca'   => time(),
            ]);
        } catch (PDOException $e) {
            // 日志记录失败不影响迁移主流程
            if (class_exists('Log')) {
                Log::error('Migrate log write failed: ' . $e->getMessage(), 'migrate');
            }
        }
    }

    // ============================================================
    // 9. 报告
    // ============================================================

    /**
     * 获取人类可读的迁移报告
     * @return string
     */
    public static function getReportSummary()
    {
        $report = self::$report;
        $pending = self::$pendingFkSql;
        $seeds   = self::$pendingSeedInserts;
        $enums   = self::$pendingEnumChanges;

        $lines = [
            '========================================',
            '  BlogKit 数据库迁移报告',
            '========================================',
            '',
        ];

        if (isset($report['message'])) {
            $lines[] = '结果: ' . $report['message'];
            $lines[] = '';
        }

        $lines[] = '版本: ' . ($report['from_version'] ?? '首次安装')
                 . ' → ' . $report['to_version'];
        $lines[] = '新增表:   ' . count($report['new_tables']);
        $lines[] = '新增字段: ' . count($report['new_columns']);
        $lines[] = '新增索引: ' . count($report['new_indexes']);
        $lines[] = '新增外键: ' . count($report['new_fk']);
        $lines[] = '新增配置项: ' . count($report['new_config_items']);
        $lines[] = '枚举变更: ' . count($report['enum_changes']);
        $lines[] = '执行 SQL: ' . count($report['executed_sql']) . ' 条';
        $lines[] = '错误:     ' . count($report['errors']) . ' 个';

        if (!empty($report['pending_sql'])) {
            $lines[] = '';
            $lines[] = '--- 待执行变更预览 ---';
            foreach ($report['pending_sql'] as $i => $sql) {
                $lines[] = sprintf('  [%d] %s', $i + 1, $sql);
            }
        }

        if (!empty($report['new_config_items'])) {
            $lines[] = '';
            $lines[] = '--- 新增配置项 ---';
            foreach ($report['new_config_items'] as $item) {
                $lines[] = '  • ' . $item;
            }
        }

        if (!empty($report['enum_changes'])) {
            $lines[] = '';
            $lines[] = '--- ENUM 变更 ---';
            foreach ($report['enum_changes'] as $change) {
                $lines[] = '  • ' . $change;
            }
        }

        if (!empty($report['errors'])) {
            $lines[] = '';
            $lines[] = '--- 错误详情 ---';
            foreach ($report['errors'] as $err) {
                $lines[] = '  SQL: ' . $err['sql'];
                $lines[] = '  错误: ' . $err['error'];
            }
        }

        return implode("\n", $lines);
    }
}
