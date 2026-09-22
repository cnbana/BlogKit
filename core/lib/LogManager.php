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
 * BlogKit 日志管理器
 * 
 * 合并了原 LogReader（日志读取）、LogCleaner（日志清理）、
 * LogReporter（报告生成）三个组件的全部功能。
 * 由 Log.php 门面类调用。
 * 
 * @package BlogKit
 * @since 2.0.0
 */

// ============================================
// 第 1 部分：日志读取器（原 LogReader）
// ============================================

class LogManager {

    /**
     * 获取日志列表
     */
    public static function getLogs($filters = [], $page = 1, $limit = 20) {
        switch (Log::$config['storage']) {
            case Log::STORAGE_DATABASE:
                return self::getLogsFromDatabase($filters, $page, $limit);
            case Log::STORAGE_FILE:
            default:
                return self::getLogsFromFile($filters, $page, $limit);
        }
    }

    /**
     * 从文件获取日志（支持多种格式：JSON/CSV/KV/Text）
     */
    private static function getLogsFromFile($filters = [], $page = 1, $limit = 20) {
        $allLogs = [];
        $logDir = Log::getLogDir();

        if (is_dir($logDir)) {
            $files = glob($logDir . '/*.log');
            foreach ($files as $file) {
                $category = basename($file, '.log');
                if (isset($filters['category']) && $filters['category'] != $category) {
                    continue;
                }

                $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $log = json_decode($line, true);
                    if ($log) {
                        if (!isset($log['message'])) {
                            if (isset($log['hook'])) {
                                $log['message'] = "Hook: {$log['hook']} - Status: {$log['status']}";
                                $log['level'] = Log::LEVEL_INFO;
                                $log['level_name'] = 'info';
                                $log['category'] = 'hook';
                                $log['ip'] = '127.0.0.1';
                                $log['user_id'] = 0;
                            } else {
                                $log['message'] = json_encode($log);
                                $log['level'] = Log::LEVEL_INFO;
                                $log['level_name'] = 'info';
                                $log['category'] = $category;
                                $log['ip'] = '127.0.0.1';
                                $log['user_id'] = 0;
                            }
                        }
                        $log['format'] = 'json';

                        if (self::passFilter($log, $filters)) {
                            $allLogs[] = $log;
                        }
                    } else {
                        // CSV格式解析
                        if (strpos($line, ',') !== false && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2},/', $line)) {
                            $log = self::parseCsvLine($line, $category);
                            if ($log && self::passFilter($log, $filters)) {
                                $allLogs[] = $log;
                            }
                            continue;
                        }

                        // KV格式解析
                        if (preg_match('/^time=\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $line)) {
                            $log = self::parseKvLine($line, $category);
                            if ($log && self::passFilter($log, $filters)) {
                                $allLogs[] = $log;
                            }
                            continue;
                        }

                        // 文本格式解析
                        if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) \[(\w+)\] \[(\w+)\] (.*)$/', $line, $matches)) {
                            $log = self::parseTextLine($matches);
                            if ($log && self::passFilter($log, $filters)) {
                                $allLogs[] = $log;
                            }
                        }
                    }
                }
            }

            // 按时间倒序
            usort($allLogs, function($a, $b) {
                return $b['time'] - $a['time'];
            });
        }

        $total = count($allLogs);
        $offset = ($page - 1) * $limit;
        $logs = array_slice($allLogs, $offset, $limit);

        return ['logs' => $logs, 'total' => $total];
    }

    /**
     * 从数据库获取日志
     */
    private static function getLogsFromDatabase($filters = [], $page = 1, $limit = 20) {
        $logs = [];
        $total = 0;

        try {
            $db = Database::getInstance();
            $baseSql = "SELECT * FROM {$db->table('log')} WHERE 1=1";
            $countSql = "SELECT COUNT(*) as total FROM {$db->table('log')} WHERE 1=1";
            $params = [];

            if (isset($filters['category'])) {
                $baseSql .= " AND category = ?";
                $countSql .= " AND category = ?";
                $params[] = $filters['category'];
            }
            if (isset($filters['level'])) {
                $baseSql .= " AND level = ?";
                $countSql .= " AND level = ?";
                $params[] = $filters['level'];
            }
            if (isset($filters['start_time'])) {
                $baseSql .= " AND time >= ?";
                $countSql .= " AND time >= ?";
                $params[] = $filters['start_time'];
            }
            if (isset($filters['end_time'])) {
                $baseSql .= " AND time <= ?";
                $countSql .= " AND time <= ?";
                $params[] = $filters['end_time'];
            }
            if (isset($filters['keyword'])) {
                $baseSql .= " AND message LIKE ?";
                $countSql .= " AND message LIKE ?";
                $params[] = '%' . $filters['keyword'] . '%';
            }

            $countParams = $params;
            $result = $db->fetch($countSql, $countParams);
            $total = $result['total'] ?? 0;

            $baseSql .= " ORDER BY time DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = ($page - 1) * $limit;

            $logs = $db->fetchAll($baseSql, $params);

            foreach ($logs as &$log) {
                if ($log['context']) {
                    $log['context'] = json_decode($log['context'], true);
                }
                if (!isset($log['format'])) {
                    $log['format'] = 'text';
                }
            }
        } catch (Exception $e) {
            // 数据库错误时返回空数组
        }

        return ['logs' => $logs, 'total' => $total];
    }

    /**
     * 获取日志统计信息
     */
    public static function getStats() {
        $stats = ['total' => 0, 'by_category' => [], 'by_level' => [], 'recent_errors' => 0];
        $logDir = Log::getLogDir();

        if (is_dir($logDir)) {
            $files = glob($logDir . '/*.log');
            foreach ($files as $file) {
                $category = basename($file, '.log');
                $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $count = count($lines);
                $stats['total'] += $count;
                $stats['by_category'][$category] = $count;

                foreach ($lines as $line) {
                    $log = json_decode($line, true);
                    if ($log && isset($log['level']) && $log['level'] >= Log::LEVEL_ERROR) {
                        $stats['recent_errors']++;
                    }
                    if (isset($log['level_name'])) {
                        if (isset($stats['by_level'][$log['level_name']])) {
                            $stats['by_level'][$log['level_name']]++;
                        } else {
                            $stats['by_level'][$log['level_name']] = 1;
                        }
                    }
                }
            }
        }

        return $stats;
    }

    /**
     * 过滤条件检查
     */
    private static function passFilter($log, $filters) {
        if (isset($filters['level']) && isset($log['level']) && $log['level'] != $filters['level']) {
            return false;
        }
        if (isset($filters['start_time']) && isset($log['time']) && $log['time'] < $filters['start_time']) {
            return false;
        }
        if (isset($filters['end_time']) && isset($log['time']) && $log['time'] > $filters['end_time']) {
            return false;
        }
        if (isset($filters['keyword']) && strpos($log['message'], $filters['keyword']) === false) {
            return false;
        }
        return true;
    }

    /**
     * 解析CSV格式日志行
     */
    private static function parseCsvLine($line, $category) {
        $csvData = str_getcsv($line);
        if (count($csvData) < 4) return null;

        $time = strtotime($csvData[0]);
        $levelName = $csvData[1];
        $logCategory = $csvData[2];
        $message = $csvData[3];

        return [
            'time' => $time,
            'level' => Log::getLevelFromName($levelName),
            'level_name' => $levelName,
            'category' => $logCategory,
            'message' => $message,
            'ip' => isset($csvData[4]) ? $csvData[4] : '127.0.0.1',
            'user_id' => isset($csvData[5]) ? (int)$csvData[5] : 0,
            'request_uri' => isset($csvData[6]) ? $csvData[6] : '',
            'user_agent' => isset($csvData[7]) ? $csvData[7] : '',
            'context' => isset($csvData[8]) && !empty($csvData[8]) ? json_decode($csvData[8], true) : [],
            'format' => 'csv'
        ];
    }

    /**
     * 解析KV格式日志行
     */
    private static function parseKvLine($line, $category) {
        $log = [
            'time' => time(), 'level' => Log::LEVEL_INFO, 'level_name' => 'info',
            'category' => $category, 'message' => $line, 'ip' => '127.0.0.1',
            'user_id' => 0, 'request_uri' => '', 'user_agent' => '', 'context' => [], 'format' => 'kv'
        ];

        $parts = explode(' ', $line);
        foreach ($parts as $part) {
            if (strpos($part, '=') !== false) {
                list($key, $value) = explode('=', $part, 2);
                $value = str_replace('\\ ', ' ', $value);
                switch ($key) {
                    case 'time': $log['time'] = strtotime($value); break;
                    case 'level': $log['level_name'] = $value; $log['level'] = Log::getLevelFromName($value); break;
                    case 'category': $log['category'] = $value; break;
                    case 'message': $log['message'] = $value; break;
                    case 'ip': $log['ip'] = $value; break;
                    case 'user': $log['user_id'] = (int)$value; break;
                    case 'uri': $log['request_uri'] = $value; break;
                    case 'ua': $log['user_agent'] = $value; break;
                    case 'context':
                        $ctxParts = explode(',', $value);
                        foreach ($ctxParts as $cp) {
                            if (strpos($cp, ':') !== false) {
                                list($ck, $cv) = explode(':', $cp, 2);
                                $log['context'][$ck] = $cv;
                            }
                        }
                        break;
                }
            }
        }
        return $log;
    }

    /**
     * 解析文本格式日志行
     */
    private static function parseTextLine($matches) {
        $timeStr = $matches[1];
        $levelName = $matches[2];
        $logCategory = $matches[3];
        $messagePart = $matches[4];

        $time = strtotime($timeStr);
        $level = Log::getLevelFromName($levelName);

        $message = $messagePart;
        if (strpos($messagePart, ' [') !== false) {
            $message = substr($messagePart, 0, strpos($messagePart, ' ['));
        }

        $log = [
            'time' => $time, 'level' => $level, 'level_name' => $levelName,
            'category' => $logCategory, 'message' => $message,
            'ip' => '127.0.0.1', 'user_id' => 0, 'request_uri' => '',
            'user_agent' => '', 'context' => [], 'format' => 'text'
        ];

        if (preg_match('/\[IP: ([^\]]+)\]/', $messagePart, $ipMatch)) $log['ip'] = $ipMatch[1];
        if (preg_match('/\[User: ([^\]]+)\]/', $messagePart, $userMatch)) $log['user_id'] = (int)$userMatch[1];
        if (preg_match('/\[URI: ([^\]]+)\]/', $messagePart, $uriMatch)) $log['request_uri'] = $uriMatch[1];
        if (preg_match('/\[UA: ([^\]]+)\]/', $messagePart, $uaMatch)) $log['user_agent'] = $uaMatch[1];
        if (preg_match('/\[Context: ([^\]]+)\]/', $messagePart, $contextMatch)) {
            $contextStr = $contextMatch[1];
            $contextParts = explode(', ', $contextStr);
            foreach ($contextParts as $part) {
                if (strpos($part, ': ') !== false) {
                    list($key, $value) = explode(': ', $part, 2);
                    $log['context'][$key] = $value;
                }
            }
        }

        return $log;
    }

    // ============================================
    // 第 2 部分：日志清理器（原 LogCleaner）
    // ============================================

    /**
     * 清理过期日志
     * 执行完整的日志清理流程：轮转→压缩→清理
     */
    public static function cleanExpiredLogs() {
        // 1. 先对当前日志文件执行轮转检查
        self::rotateAllCurrentLogs();

        // 2. 压缩未压缩的轮转日志文件
        self::compressRotatedLogs();

        // 3. 清理过期文件
        self::removeExpiredLogs();

        // 4. 清理数据库日志
        self::cleanDatabaseLogs();

        // 5. 检查总大小限制
        self::enforceTotalSizeLimit();
    }

    /**
     * 对所有当前日志文件执行轮转检查
     */
    private static function rotateAllCurrentLogs() {
        $logDir = Log::getLogDir();
        if (!is_dir($logDir)) return;

        $maxSize = (int)Log::$config['log_file_size'] * 1024 * 1024;
        $files = glob($logDir . '/*.log');
        foreach ($files as $file) {
            if (file_exists($file) && filesize($file) > $maxSize) {
                $backupFile = $file . '.' . date('YmdHis');
                rename($file, $backupFile);
                file_put_contents($file, '');
            }
        }
    }

    /**
     * 压缩未压缩的轮转日志文件（.log.YYYYMMDDHHMMSS → .log.gz）
     */
    private static function compressRotatedLogs() {
        $logDir = Log::getLogDir();
        if (!is_dir($logDir) || !function_exists('gzopen')) return;

        $files = glob($logDir . '/*.log.[0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9]');
        foreach ($files as $file) {
            $gzFile = $file . '.gz';
            if (file_exists($gzFile)) {
                if (filemtime($gzFile) >= filemtime($file)) {
                    @unlink($file);
                }
                continue;
            }

            $content = @file_get_contents($file);
            if ($content === false) continue;

            $fp = @gzopen($gzFile, 'w9');
            if ($fp) {
                gzwrite($fp, $content);
                gzclose($fp);
                @unlink($file);
            }
        }
    }

    /**
     * 删除过期的日志文件
     */
    private static function removeExpiredLogs() {
        $days = (int)Log::$config['log_retention'];
        $logDir = Log::getLogDir();

        if (is_dir($logDir)) {
            $cutoffTime = time() - ($days * 24 * 3600);
            $files = glob($logDir . '/*.log*');
            foreach ($files as $file) {
                $basename = basename($file);
                if (preg_match('/^[^.]+\.log$/', $basename)) continue;

                if (filemtime($file) < $cutoffTime) {
                    @unlink($file);
                }
            }
        }
    }

    /**
     * 清理数据库日志
     */
    private static function cleanDatabaseLogs() {
        if (!Log::$config['database']) return;

        $days = (int)Log::$config['log_retention'];
        try {
            $db = Database::getInstance();
            $cutoffTime = time() - ($days * 24 * 3600);
            $db->query("DELETE FROM {$db->table('log')} WHERE time < ?", [$cutoffTime]);
        } catch (Exception $e) {
            // 忽略数据库错误
        }
    }

    /**
     * 强制总大小限制
     */
    private static function enforceTotalSizeLimit() {
        $logDir = Log::getLogDir();
        if (!is_dir($logDir)) return;

        $maxTotalSize = defined('LOG_MAX_TOTAL_SIZE') ? LOG_MAX_TOTAL_SIZE : 500 * 1024 * 1024;
        $configMaxSize = Config::get('debug.log_max_total_size', 0);
        if ($configMaxSize > 0) {
            $maxTotalSize = (int)$configMaxSize * 1024 * 1024;
        }

        $totalSize = self::getDirectorySize($logDir);
        if ($totalSize <= $maxTotalSize) return;

        $files = [];
        foreach (glob($logDir . '/*.log*') as $file) {
            $basename = basename($file);
            if (preg_match('/^[^.]+\.log$/', $basename)) continue;
            $files[$file] = filemtime($file);
        }
        asort($files);

        foreach ($files as $file => $mtime) {
            $size = filesize($file);
            @unlink($file);
            $totalSize -= $size;
            if ($totalSize <= $maxTotalSize) break;
        }
    }

    /**
     * 计算目录总大小
     */
    private static function getDirectorySize($dir) {
        $size = 0;
        foreach (glob(rtrim($dir, '/') . '/*', GLOB_NOSORT) as $file) {
            $size += is_file($file) ? filesize($file) : self::getDirectorySize($file);
        }
        return $size;
    }

    /**
     * 获取日志目录统计信息
     */
    public static function getLogStats() {
        $logDir = Log::getLogDir();
        $stats = [
            'total_files' => 0,
            'current_files' => 0,
            'rotated_files' => 0,
            'compressed_files' => 0,
            'total_size' => 0,
            'total_size_human' => '0 B',
            'oldest_file' => null,
            'newest_file' => null
        ];

        if (!is_dir($logDir)) return $stats;

        $oldestTime = null;
        $newestTime = null;

        foreach (glob($logDir . '/*.log*') as $file) {
            $stats['total_files']++;
            $stats['total_size'] += filesize($file);

            $basename = basename($file);
            $mtime = filemtime($file);

            if (preg_match('/^[^.]+\.log$/', $basename)) {
                $stats['current_files']++;
            } elseif (strpos($basename, '.gz') !== false) {
                $stats['compressed_files']++;
            } else {
                $stats['rotated_files']++;
            }

            if ($oldestTime === null || $mtime < $oldestTime) {
                $oldestTime = $mtime;
                $stats['oldest_file'] = $basename;
            }
            if ($newestTime === null || $mtime > $newestTime) {
                $newestTime = $mtime;
                $stats['newest_file'] = $basename;
            }
        }

        // 格式化大小
        $size = $stats['total_size'];
        if ($size >= 1073741824) {
            $stats['total_size_human'] = round($size / 1073741824, 2) . ' GB';
        } elseif ($size >= 1048576) {
            $stats['total_size_human'] = round($size / 1048576, 2) . ' MB';
        } elseif ($size >= 1024) {
            $stats['total_size_human'] = round($size / 1024, 2) . ' KB';
        } else {
            $stats['total_size_human'] = $size . ' B';
        }

        return $stats;
    }

    // ============================================
    // 第 3 部分：日志报告生成器（原 LogReporter）
    // ============================================

    /**
     * 生成日志报告
     */
    public static function generateReport($params = []) {
        $reportType = isset($params['type']) ? $params['type'] : 'summary';
        $startTime = isset($params['start_time']) ? $params['start_time'] : strtotime('-7 days');
        $endTime = isset($params['end_time']) ? $params['end_time'] : time();
        $categories = isset($params['categories']) ? $params['categories'] : [];
        $levels = isset($params['levels']) ? $params['levels'] : [];

        $filters = ['start_time' => $startTime, 'end_time' => $endTime];
        if (!empty($categories)) $filters['category'] = $categories;
        if (!empty($levels)) $filters['level'] = $levels;

        $allLogs = Log::getLogs($filters, 1, PHP_INT_MAX);
        $logs = $allLogs['logs'];

        switch ($reportType) {
            case 'category': return self::generateCategoryReport($logs);
            case 'level': return self::generateLevelReport($logs);
            case 'user': return self::generateUserReport($logs);
            case 'error': return self::generateErrorReport($logs);
            case 'all': return self::generateAllReport($logs, $startTime, $endTime);
            case 'summary':
            default: return self::generateSummaryReport($logs, $startTime, $endTime);
        }
    }

    private static function generateSummaryReport($logs, $startTime, $endTime) {
        $stats = ['total' => count($logs), 'by_level' => [], 'by_category' => [], 'by_day' => [], 'errors' => 0, 'warnings' => 0];

        foreach ($logs as $log) {
            if (!isset($stats['by_level'][$log['level_name']])) $stats['by_level'][$log['level_name']] = 0;
            $stats['by_level'][$log['level_name']]++;

            $category = $log['category'];
            if (!isset($stats['by_category'][$category])) $stats['by_category'][$category] = 0;
            $stats['by_category'][$category]++;

            $day = date('Y-m-d', $log['time']);
            if (!isset($stats['by_day'][$day])) $stats['by_day'][$day] = 0;
            $stats['by_day'][$day]++;

            if ($log['level'] >= Log::LEVEL_ERROR) {
                $stats['errors']++;
            } elseif ($log['level'] == Log::LEVEL_WARNING) {
                $stats['warnings']++;
            }
        }

        arsort($stats['by_level']);
        arsort($stats['by_category']);
        ksort($stats['by_day']);

        return [
            'type' => 'summary',
            'period' => ['start' => date('Y-m-d H:i:s', $startTime), 'end' => date('Y-m-d H:i:s', $endTime)],
            'stats' => $stats, 'logs' => $logs,
            'top_events' => array_slice($logs, 0, 10)
        ];
    }

    private static function generateCategoryReport($logs) {
        $stats = ['total' => count($logs), 'by_category' => [], 'by_level_in_category' => []];
        foreach ($logs as $log) {
            $category = $log['category'];
            if (!isset($stats['by_category'][$category])) $stats['by_category'][$category] = 0;
            $stats['by_category'][$category]++;
            if (!isset($stats['by_level_in_category'][$category])) $stats['by_level_in_category'][$category] = [];
            if (!isset($stats['by_level_in_category'][$category][$log['level_name']])) $stats['by_level_in_category'][$category][$log['level_name']] = 0;
            $stats['by_level_in_category'][$category][$log['level_name']]++;
        }
        arsort($stats['by_category']);
        return ['type' => 'category', 'stats' => $stats, 'logs' => $logs];
    }

    private static function generateLevelReport($logs) {
        $stats = ['total' => count($logs), 'by_level' => [], 'by_category_in_level' => []];
        foreach ($logs as $log) {
            $levelName = $log['level_name'];
            if (!isset($stats['by_level'][$levelName])) $stats['by_level'][$levelName] = 0;
            $stats['by_level'][$levelName]++;
            if (!isset($stats['by_category_in_level'][$levelName])) $stats['by_category_in_level'][$levelName] = [];
            if (!isset($stats['by_category_in_level'][$levelName][$log['category']])) $stats['by_category_in_level'][$levelName][$log['category']] = 0;
            $stats['by_category_in_level'][$levelName][$log['category']]++;
        }
        arsort($stats['by_level']);
        return ['type' => 'level', 'stats' => $stats, 'logs' => $logs];
    }

    private static function generateUserReport($logs) {
        $stats = ['total' => count($logs), 'by_user' => [], 'user_operations' => []];
        foreach ($logs as $log) {
            $userId = $log['user_id'] > 0 ? $log['user_id'] : 'system';
            if (!isset($stats['by_user'][$userId])) $stats['by_user'][$userId] = 0;
            $stats['by_user'][$userId]++;
            if (!isset($stats['user_operations'][$userId])) $stats['user_operations'][$userId] = [];
            $stats['user_operations'][$userId][] = $log;
        }
        arsort($stats['by_user']);
        return ['type' => 'user', 'stats' => $stats, 'logs' => $logs];
    }

    private static function generateErrorReport($logs) {
        $errorLogs = [];
        $stats = ['total' => 0, 'by_level' => [], 'by_category' => [], 'by_day' => []];
        foreach ($logs as $log) {
            if ($log['level'] >= Log::LEVEL_WARNING) {
                $errorLogs[] = $log;
                $stats['total']++;
                if (!isset($stats['by_level'][$log['level_name']])) $stats['by_level'][$log['level_name']] = 0;
                $stats['by_level'][$log['level_name']]++;
                if (!isset($stats['by_category'][$log['category']])) $stats['by_category'][$log['category']] = 0;
                $stats['by_category'][$log['category']]++;
                $day = date('Y-m-d', $log['time']);
                if (!isset($stats['by_day'][$day])) $stats['by_day'][$day] = 0;
                $stats['by_day'][$day]++;
            }
        }
        arsort($stats['by_level']);
        arsort($stats['by_category']);
        ksort($stats['by_day']);
        return ['type' => 'error', 'stats' => $stats, 'logs' => $errorLogs];
    }

    private static function generateAllReport($logs, $startTime, $endTime) {
        return [
            'type' => 'all',
            'period' => ['start' => date('Y-m-d H:i:s', $startTime), 'end' => date('Y-m-d H:i:s', $endTime)],
            'summary' => self::generateSummaryReport($logs, $startTime, $endTime),
            'category' => self::generateCategoryReport($logs),
            'level' => self::generateLevelReport($logs),
            'user' => self::generateUserReport($logs),
            'error' => self::generateErrorReport($logs),
            'logs' => $logs
        ];
    }
}
