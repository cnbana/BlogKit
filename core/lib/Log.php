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
 * BlogKit 统一日志管理系统
 * 
 * 合并了原 LogWriter（日志写入）功能，数据写入方法内联至本类。
 * 日志读取、清理、报告生成委托至 LogManager。
 *
 * 使用方法：
 *   Log::info('message', 'category');
 *   Log::error('message', 'category');
 *   Log::getLogs(['category' => 'api'], 1, 20);
 */
class Log {
    private static $instance = null;
    
    // 引入日志管理器（合并了原 LogReader / LogCleaner / LogReporter）
    private static function _manager()  { require_once CORE_PATH . '/lib/LogManager.php'; }

    /** 日志级别 */
    const LEVEL_DEBUG = 0;
    const LEVEL_INFO = 1;
    const LEVEL_WARNING = 2;
    const LEVEL_ERROR = 3;
    const LEVEL_SECURITY = 4;

    /** 日志分类 */
    const CATEGORY_SYSTEM = 'system';
    const CATEGORY_OPERATION = 'operation';
    const CATEGORY_EMAIL = 'email';
    const CATEGORY_API = 'api';
    const CATEGORY_PLUGIN = 'plugin';
    const CATEGORY_THEME = 'theme';
    const CATEGORY_DATABASE = 'database';
    const CATEGORY_SECURITY = 'security';
    const CATEGORY_HOOK = 'hook';
    const CATEGORY_UPLOAD = 'upload';
    const CATEGORY_BACKUP = 'backup';
    const CATEGORY_LOGIN = 'login';

    /** 日志存储方式 */
    const STORAGE_FILE = 'file';
    const STORAGE_DATABASE = 'database';

    /** 日志配置 */
    public static $config = [];

    /** 相对项目根目录的日志目录（唯一允许值） */
    const PATH_REL = 'storage/logs/';

    // ==================== 初始化 ====================

    public static function init() {
        $configuredPath = Config::get('debug.log_path', self::PATH_REL);
        $path = self::normalizeLogPath($configuredPath);
        if ($configuredPath !== $path) {
            self::persistLogPath($path);
        }

        self::$config = [
            'enabled' => Config::get('debug.log_enabled', 0), // 兜底默认关闭，与 install.sql 保持一致
            'storage' => Config::get('debug.log_storage', self::STORAGE_FILE),
            'level' => Config::get('debug.log_level', 'info'),
            'log_rotation' => Config::get('debug.log_rotation', 7),
            'log_retention' => Config::get('debug.log_retention', 7),
            'log_file_size' => Config::get('debug.log_file_size', 10),
            'path' => $path,
            'log_format' => Config::get('debug.log_format', 'text'),
            'database' => Config::get('debug.log_database', 0),
            'enabled_categories' => json_decode(Config::get('debug.log_enabled_categories', '"all"'), true)
        ];
        self::ensureLogDirectory();
    }

    /**
     * 规范化日志目录（统一到 storage/logs/）
     */
    public static function normalizeLogPath($path = null) {
        $path = trim(str_replace('\\', '/', (string)($path ?? self::PATH_REL)));
        if ($path === '' || $path[0] === '/' || strpos($path, '..') !== false) {
            return self::PATH_REL;
        }
        $path = rtrim($path, '/') . '/';
        if ($path === 'logs/' || $path !== self::PATH_REL) {
            return self::PATH_REL;
        }
        return self::PATH_REL;
    }

    /**
     * 日志目录绝对路径
     */
    public static function getLogDir() {
        return defined('LOG_PATH') ? LOG_PATH : (ROOT_PATH . '/storage/logs');
    }

    /**
     * 将规范化后的路径写回配置（内存 + 数据库）
     */
    private static function persistLogPath($path) {
        Config::set('debug_log_path', $path);
        Config::set('debug.log_path', $path);
        try {
            require_once CORE_PATH . '/lib/Database.php';
            $db = Database::getInstance();
            $prefix = Config::get('database.prefix', 'bk_');
            $existing = $db->fetch("SELECT id FROM {$prefix}config WHERE name = ?", ['debug_log_path']);
            if ($existing) {
                $db->update('config', ['value' => $path], ['name' => 'debug_log_path']);
            }
        } catch (Exception $e) {
            // 安装阶段或数据库不可用时仅使用内存配置
        }
    }

    // ==================== 写入方法 ====================

    public static function debug($message, $category = self::CATEGORY_SYSTEM, $context = []) {
        self::write($message, self::LEVEL_DEBUG, $category, $context);
    }
    public static function info($message, $category = self::CATEGORY_SYSTEM, $context = []) {
        self::write($message, self::LEVEL_INFO, $category, $context);
    }
    public static function warning($message, $category = self::CATEGORY_SYSTEM, $context = []) {
        self::write($message, self::LEVEL_WARNING, $category, $context);
    }
    public static function error($message, $category = self::CATEGORY_SYSTEM, $context = []) {
        self::write($message, self::LEVEL_ERROR, $category, $context);
    }
    public static function security($message, $category = self::CATEGORY_SECURITY, $context = []) {
        self::write($message, self::LEVEL_SECURITY, $category, $context);
    }
    public static function sql($sql, $executionTime, $params = []) {
        $context = [
            'execution_time' => $executionTime,
            'params' => $params
        ];
        self::write($sql, self::LEVEL_DEBUG, 'database', $context);
    }
    public static function api($method, $path, $statusCode, $executionTime, $requestData = [], $responseData = []) {
        $context = [
            'method' => $method,
            'path' => $path,
            'status_code' => $statusCode,
            'execution_time' => $executionTime,
            'request_data' => $requestData,
            'response_data' => $responseData
        ];
        $message = "{$method} {$path} - {$statusCode}";
        $level = $statusCode >= 400 ? self::LEVEL_ERROR : self::LEVEL_INFO;
        self::write($message, $level, 'api', $context);
    }

    // ==================== 读取 (委托至 LogManager) ====================

    public static function getLogs($filters = [], $page = 1, $limit = 20) {
        self::_manager(); return LogManager::getLogs($filters, $page, $limit);
    }
    public static function getStats() {
        self::_manager(); return LogManager::getStats();
    }

    // ==================== 清理 (委托至 LogManager) ====================

    public static function cleanExpiredLogs() {
        self::_manager(); LogManager::cleanExpiredLogs();
    }

    // ==================== 报告 (委托至 LogManager) ====================

    public static function generateReport($params = []) {
        self::_manager(); return LogManager::generateReport($params);
    }

    // ==================== 工具方法 ====================

    /**
     * 获取级别名称
     */
    public static function getLevelName($level) {
        if ($level === -1) return 'all';
        $levels = [
            self::LEVEL_DEBUG => 'debug', self::LEVEL_INFO => 'info',
            self::LEVEL_WARNING => 'warning', self::LEVEL_ERROR => 'error',
            self::LEVEL_SECURITY => 'security'
        ];
        return isset($levels[$level]) ? $levels[$level] : 'unknown';
    }

    /**
     * 从名称获取级别
     */
    public static function getLevelFromName($name) {
        if ($name === 'all') return -1;
        $levels = [
            'debug' => self::LEVEL_DEBUG, 'info' => self::LEVEL_INFO,
            'warning' => self::LEVEL_WARNING, 'error' => self::LEVEL_ERROR,
            'security' => self::LEVEL_SECURITY
        ];
        return isset($levels[$name]) ? $levels[$name] : self::LEVEL_ERROR;
    }

    /**
     * 获取所有日志分类
     */
    public static function getCategories() {
        return [
            self::CATEGORY_SYSTEM => '系统', self::CATEGORY_OPERATION => '操作',
            self::CATEGORY_EMAIL => '邮件', self::CATEGORY_API => 'API',
            self::CATEGORY_PLUGIN => '插件', self::CATEGORY_THEME => '主题',
            self::CATEGORY_DATABASE => '数据库', self::CATEGORY_SECURITY => '安全',
            self::CATEGORY_HOOK => '钩子', self::CATEGORY_UPLOAD => '上传',
            self::CATEGORY_BACKUP => '备份', self::CATEGORY_LOGIN => '登录'
        ];
    }

    /**
     * 获取所有日志级别
     */
    public static function getLevels() {
        return [
            self::LEVEL_DEBUG => 'debug', self::LEVEL_INFO => 'info',
            self::LEVEL_WARNING => 'warning', self::LEVEL_ERROR => 'error',
            self::LEVEL_SECURITY => 'security'
        ];
    }

    // ==================== 核心写入方法（原 LogWriter，已内联） ====================

    /**
     * 核心日志记录方法
     */
    private static function write($message, $level, $category, $context = []) {
        if (!isset(self::$config['enabled']) || !self::$config['enabled']) {
            return;
        }

        $minLevel = self::getLevelFromName(self::$config['level']);
        if (!isset(self::$config['level']) || ($minLevel != -1 && $level < $minLevel)) {
            return;
        }

        if (isset(self::$config['enabled_categories'])) {
            if (self::$config['enabled_categories'] !== 'all') {
                if (is_array(self::$config['enabled_categories']) && empty(self::$config['enabled_categories'])) {
                    return;
                } elseif (!in_array($category, self::$config['enabled_categories'])) {
                    return;
                }
            }
        }

        $logData = [
            'time' => time(),
            'level' => $level,
            'level_name' => self::getLevelName($level),
            'category' => $category,
            'message' => $message,
            'context' => $context,
            'ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1',
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            'user_id' => isset($_SESSION['admin']['id']) ? $_SESSION['admin']['id'] : 0,
            'request_uri' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : ''
        ];

        switch (self::$config['storage']) {
            case self::STORAGE_DATABASE:
                self::logToDatabase($logData);
                break;
            case self::STORAGE_FILE:
            default:
                self::logToFile($logData);
                break;
        }
    }

    /**
     * 根据轮转周期（天）返回当前周期的文件名标识（避免跨期写入同一文件）。
     * 1 => 按日   : category-YYYYMMDD.log
     * 7 => 按周   : category-YYYY-Www.log（ISO 周号）
     * 30 => 按月  : category-YYYYMM.log
     * 其它值       : category.log（仅按大小切割）
     */
    private static function getPeriodicLogFile($category, $now = null) {
        $logDir = self::getLogDir();
        $rotation = isset(self::$config['log_rotation']) ? (int)self::$config['log_rotation'] : 0;
        if ($now === null) $now = time();

        if ($rotation === 1) {
            $suffix = date('Ymd', $now);
        } elseif ($rotation === 7) {
            // ISO 周号
            $suffix = date('o', $now) . '-W' . date('W', $now);
        } elseif ($rotation === 30) {
            $suffix = date('Ym', $now);
        } else {
            return $logDir . '/' . $category . '.log';
        }
        return $logDir . '/' . $category . '-' . $suffix . '.log';
    }

    /**
     * 记录日志到文件
     */
    private static function logToFile($logData) {
        $logDir = self::getLogDir();
        // 按周期 + 分类命名活动日志；该文件一旦超大小阈值仍会被 rotateLogFile 归档
        $logFile = self::getPeriodicLogFile($logData['category'], $logData['time']);

        if (self::$config['log_format'] == 'text') {
            $logLine = date('Y-m-d H:i:s', $logData['time']) . ' [' . $logData['level_name'] . '] [' . $logData['category'] . '] ' . $logData['message'];
            if (isset($logData['ip']) && !empty($logData['ip'])) {
                $logLine .= ' [IP: ' . $logData['ip'] . ']';
            }
            if (isset($logData['user_id']) && $logData['user_id'] > 0) {
                $logLine .= ' [User: ' . $logData['user_id'] . ']';
            }
            if (isset($logData['request_uri']) && !empty($logData['request_uri'])) {
                $logLine .= ' [URI: ' . $logData['request_uri'] . ']';
            }
            if (isset($logData['user_agent']) && !empty($logData['user_agent'])) {
                $userAgent = substr($logData['user_agent'], 0, 50);
                if (strlen($logData['user_agent']) > 50) {
                    $userAgent .= '...';
                }
                $logLine .= ' [UA: ' . $userAgent . ']';
            }
            if (isset($logData['context']) && !empty($logData['context']) && is_array($logData['context'])) {
                $contextInfo = [];
                foreach ($logData['context'] as $key => $value) {
                    if (is_scalar($value)) {
                        $contextInfo[] = $key . ': ' . $value;
                    } elseif (is_array($value) && count($value) > 0) {
                        $contextInfo[] = $key . ': ' . json_encode($value);
                    }
                }
                if (!empty($contextInfo)) {
                    $logLine .= ' [Context: ' . implode(', ', $contextInfo) . ']';
                }
            }
            $logLine .= PHP_EOL;
        } elseif (self::$config['log_format'] == 'csv') {
            $csvData = [
                date('Y-m-d H:i:s', $logData['time']),
                $logData['level_name'],
                $logData['category'],
                $logData['message'],
                isset($logData['ip']) ? $logData['ip'] : '',
                isset($logData['user_id']) ? $logData['user_id'] : 0,
                isset($logData['request_uri']) ? $logData['request_uri'] : '',
                isset($logData['user_agent']) ? substr($logData['user_agent'], 0, 100) : '',
                isset($logData['context']) && !empty($logData['context']) ? json_encode($logData['context']) : ''
            ];
            foreach ($csvData as &$value) {
                if (strpos($value, ',') !== false || strpos($value, '"') !== false || strpos($value, '\n') !== false) {
                    $value = '"' . str_replace('"', '""', $value) . '"';
                }
            }
            $logLine = implode(',', $csvData) . PHP_EOL;
        } elseif (self::$config['log_format'] == 'kv') {
            $kvData = [
                'time=' . date('Y-m-d H:i:s', $logData['time']),
                'level=' . $logData['level_name'],
                'category=' . $logData['category'],
                'message=' . str_replace(' ', '\ ', $logData['message'])
            ];
            if (isset($logData['ip']) && !empty($logData['ip'])) {
                $kvData[] = 'ip=' . $logData['ip'];
            }
            if (isset($logData['user_id']) && $logData['user_id'] > 0) {
                $kvData[] = 'user=' . $logData['user_id'];
            }
            if (isset($logData['request_uri']) && !empty($logData['request_uri'])) {
                $kvData[] = 'uri=' . str_replace(' ', '\ ', $logData['request_uri']);
            }
            if (isset($logData['user_agent']) && !empty($logData['user_agent'])) {
                $userAgent = substr($logData['user_agent'], 0, 50);
                if (strlen($logData['user_agent']) > 50) {
                    $userAgent .= '...';
                }
                $kvData[] = 'ua=' . str_replace(' ', '\ ', $userAgent);
            }
            if (isset($logData['context']) && !empty($logData['context'])) {
                $contextInfo = [];
                foreach ($logData['context'] as $key => $value) {
                    if (is_scalar($value)) {
                        $contextInfo[] = $key . ':' . $value;
                    }
                }
                if (!empty($contextInfo)) {
                    $kvData[] = 'context=' . str_replace(' ', '\ ', implode(',', $contextInfo));
                }
            }
            $logLine = implode(' ', $kvData) . PHP_EOL;
        } else {
            $logLine = json_encode($logData) . PHP_EOL;
        }

        file_put_contents($logFile, $logLine, FILE_APPEND);
        self::rotateLogFile($logFile);
    }

    /**
     * 记录日志到数据库
     */
    private static function logToDatabase($logData) {
        if (!self::$config['database']) {
            return;
        }

        try {
            $db = Database::getInstance();
            $db->insert('log', [
                'time' => $logData['time'],
                'level' => $logData['level'],
                'level_name' => $logData['level_name'],
                'category' => $logData['category'],
                'message' => $logData['message'],
                'context' => json_encode($logData['context']),
                'ip' => $logData['ip'],
                'user_agent' => $logData['user_agent'],
                'user_id' => $logData['user_id'],
                'request_uri' => $logData['request_uri'],
                'format' => isset(self::$config['log_format']) ? self::$config['log_format'] : 'text'
            ]);
        } catch (Exception $e) {
            self::logToFile($logData);
        }
    }

    /**
     * 轮转日志文件
     */
    private static function rotateLogFile($logFile) {
        $maxSize = self::$config['log_file_size'] * 1024 * 1024;
        if (file_exists($logFile) && filesize($logFile) > $maxSize) {
            $backupFile = $logFile . '.' . date('YmdHis');
            rename($logFile, $backupFile);
            file_put_contents($logFile, '');
        }
    }

    /**
     * 确保日志目录存在
     */
    public static function ensureLogDirectory() {
        $logDir = self::getLogDir();
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    // ==================== 配置管理 ====================

    public static function saveSettings($settings) {
        require_once CORE_PATH . '/lib/Config.php';

        $enabledCategories = isset($settings['enabled_categories']) ? $settings['enabled_categories'] : 'all';
        $enabledCategoriesValue = is_array($enabledCategories) ? json_encode($enabledCategories) : $enabledCategories;

        $debugConfig = [
            // 开关只允许写入 0/1 整数：避免 false 被 PDO 写成空字符串导致日志系统"假性禁用"
            'debug_log_enabled' => !empty($settings['enabled']) ? 1 : 0,
            'debug_log_storage' => isset($settings['storage']) ? $settings['storage'] : self::STORAGE_FILE,
            'debug_log_level' => isset($settings['log_level']) ? self::getLevelName($settings['log_level']) : 'info',
            'debug_log_rotation' => isset($settings['log_rotation']) ? $settings['log_rotation'] : 7,
            'debug_log_retention' => isset($settings['log_retention']) ? $settings['log_retention'] : 7,
            'debug_log_file_size' => isset($settings['log_file_size']) ? $settings['log_file_size'] : 10,
            'debug_log_path' => self::PATH_REL,
            'debug_log_format' => isset($settings['log_format']) ? $settings['log_format'] : 'text',
            'debug_log_database' => (isset($settings['storage']) && $settings['storage'] == self::STORAGE_DATABASE) ? 1 : 0,
            'debug_log_enabled_categories' => $enabledCategoriesValue
        ];

        require_once CORE_PATH . '/lib/Database.php';
        $db = Database::getInstance();
        // 使用运行时真实的表前缀，避免 fallback 到历史残留的 'blog_'
        $prefix = Config::get('database.prefix', 'bk_');

        foreach ($debugConfig as $key => $value) {
            $existingConfig = $db->fetch("SELECT * FROM {$prefix}config WHERE name = ?", [$key]);
            if ($existingConfig) {
                $db->update('config', ['value' => $value], ['name' => $key]);
            } else {
                $db->insert('config', [
                    'name' => $key, 'value' => $value,
                    'type' => is_bool($value) ? 'boolean' : (is_numeric($value) ? 'integer' : 'string'),
                    'description' => '日志系统配置'
                ]);
            }
            Config::set($key, $value);
            if (strpos($key, 'debug_') === 0) {
                Config::set('debug.' . substr($key, 6), $value);
            }
        }

        self::$config = array_merge(self::$config, [
            'enabled' => isset($settings['enabled']) ? $settings['enabled'] : 0,
            'storage' => isset($settings['storage']) ? $settings['storage'] : self::STORAGE_FILE,
            'level' => isset($settings['log_level']) ? self::getLevelName($settings['log_level']) : 'info',
            'log_rotation' => isset($settings['log_rotation']) ? $settings['log_rotation'] : 7,
            'log_retention' => isset($settings['log_retention']) ? $settings['log_retention'] : 7,
            'log_file_size' => isset($settings['log_file_size']) ? $settings['log_file_size'] : 10,
            'path' => self::PATH_REL,
            'log_format' => isset($settings['log_format']) ? $settings['log_format'] : 'text',
            'database' => (isset($settings['storage']) && $settings['storage'] == self::STORAGE_DATABASE) ? 1 : 0,
            'enabled_categories' => $enabledCategories
        ]);

        self::ensureLogDirectory();
    }

    public static function resetSettings() {
        self::saveSettings([
            'enabled' => 0, 'storage' => self::STORAGE_FILE, // 重置为默认关闭状态
            'log_level' => self::LEVEL_INFO, 'log_rotation' => 7,
            'log_retention' => 7, 'log_file_size' => 10,
            'path' => self::PATH_REL, 'log_format' => 'text', 'database' => 0,
            'enabled_categories' => ['system', 'operation', 'security', 'error']
        ]);
    }
    
    /**
     * 获取单例实例（用于插件系统服务容器）
     * @return Log
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
