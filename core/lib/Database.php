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


if (!class_exists('Config')) {
    require CORE_PATH . '/lib/Config.php';
}

if (!class_exists('Log')) {
    require_once CORE_PATH . '/lib/Log.php';
    Log::init();
}

// Cache class is loaded by autoloader

class Database {
    private static $instance = null;
    private $pdo;
    private $prefix;
    
    /**
     * 查询日志数组
     */
    private static $queryLog = [];
    
    /**
     * 构造函数，建立数据库连接
     */
    private function __construct() {
        $config = Config::get('database');
        $this->prefix = $config['prefix'];
        
        try {
            // 尝试使用配置的字符集
            $charset = isset($config['charset']) ? $config['charset'] : 'utf8mb4';
            $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$charset}";
            $this->pdo = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            // 如果字符集错误，尝试使用utf8字符集
            if (strpos($e->getMessage(), 'Unknown character set') !== false) {
                try {
                    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8";
                    $this->pdo = new PDO($dsn, $config['username'], $config['password'], [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]);
                } catch (PDOException $e2) {
                    Log::error('Database connection failed', 'database', ['message' => $e2->getMessage()]);
                    self::failSafeDie();
                }
            } else {
                Log::error('Database connection failed', 'database', ['message' => $e->getMessage()]);
                self::failSafeDie();
            }
        }
    }
    
    /**
     * 获取单例实例
     * @return Database
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 数据库连接失败时的安全退出
     * 生产环境下不暴露任何数据库错误详情到页面，只记录到日志
     */
    private static function failSafeDie() {
        if (defined('IS_API_REQUEST') && IS_API_REQUEST) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
            echo json_encode([
                'code' => 500,
                'message' => '服务器繁忙，请稍后重试',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        if (!headers_sent()) {
            http_response_code(500);
        }
        // 显示通用错误页面，不暴露任何技术细节
        die('服务器繁忙，请稍后重试');
    }
    
    /**
     * 获取带前缀的表名
     * @param string $table 表名
     * @return string
     */
    public function table($table) {
        return $this->prefix . $table;
    }
    
    /**
     * 执行查询
     * @param string $sql SQL语句
     * @param array $params 参数
     * @param bool $cache 是否缓存
     * @param int $expire 缓存过期时间（秒）
     * @return PDOStatement
     */
    public function query($sql, $params = [], $cache = false, $expire = 3600) {
        // 记录查询开始时间
        $startTime = microtime(true);
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        // 计算执行时间
        $endTime = microtime(true);
        $executionTime = round(($endTime - $startTime) * 1000, 2); // 毫秒
        
        // 记录查询日志
        $this->logQuery($sql, $params, $executionTime);
        
        return $stmt;
    }
    
    /**
     * 记录查询日志
     * @param string $sql SQL语句
     * @param array $params 参数
     * @param float $executionTime 执行时间（毫秒）
     */
    private function logQuery($sql, $params, $executionTime) {
        // 检查是否启用调试模式（调试面板会用到查询日志数组）
        $debugEnabled = Config::get('debug.enabled', false);
        
        // 检查是否启用慢查询日志
        $slowQueryLogEnabled = Config::get('debug_slow_query_log', 0);
        $slowQueryTime = Config::get('debug_slow_query_time', 1); // 秒
        
        // 如果既没启用调试面板，也没开启慢查询日志，则直接返回，不做日志构建
        if (!$debugEnabled && !$slowQueryLogEnabled) {
            return;
        }
        
        // 构建查询信息（调试面板渲染时会读取 self::$queryLog）
        $queryInfo = [
            'sql' => $sql,
            'params' => $params,
            'execution_time' => $executionTime,
            'timestamp' => time()
        ];
        
        // 添加到查询日志数组
        self::$queryLog[] = $queryInfo;
        
        // 检查是否需要记录慢查询日志
        if ($slowQueryLogEnabled && $executionTime >= ($slowQueryTime * 1000)) {
            if (class_exists('Log')) {
                $context = [
                    'query' => $sql,
                    'time' => $executionTime,
                    'params' => $params,
                    'slow_query_threshold' => $slowQueryTime
                ];
                Log::warning("Slow SQL Query", $context);
            }
        }
    }
    
    /**
     * 获取查询日志
     * @return array 查询日志数组
     */
    public static function getQueryLog() {
        return self::$queryLog;
    }
    
    /**
     * 清空查询日志
     */
    public static function clearQueryLog() {
        self::$queryLog = [];
    }
    
    /**
     * 获取单条记录
     * @param string $sql SQL语句
     * @param array $params 参数
     * @param bool $cache 是否缓存
     * @param int $expire 缓存过期时间（秒）
     * @return array
     */
    public function fetch($sql, $params = [], $cache = false, $expire = 3600) {
        if ($cache && Config::get('cache.enabled', false)) {
            $cacheKey = 'db_fetch_' . md5($sql . json_encode($params));
            $cachedData = Cache::getInstance()->get($cacheKey);
            if ($cachedData !== false) {
                return $cachedData;
            }
        }
        
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        
        if ($cache && Config::get('cache.enabled', false) && $result !== false) {
            $cacheKey = 'db_fetch_' . md5($sql . json_encode($params));
            Cache::getInstance()->set($cacheKey, $result, $expire);
        }
        
        return $result;
    }
    
    /**
     * 获取多条记录
     * @param string $sql SQL语句
     * @param array $params 参数
     * @param bool $cache 是否缓存
     * @param int $expire 缓存过期时间（秒）
     * @return array
     */
    public function fetchAll($sql, $params = [], $cache = false, $expire = 3600) {
        if ($cache && Config::get('cache.enabled', false)) {
            $cacheKey = 'db_fetchall_' . md5($sql . json_encode($params));
            $cachedData = Cache::getInstance()->get($cacheKey);
            if ($cachedData !== false) {
                // 确保缓存数据是数组
                return is_array($cachedData) ? $cachedData : [];
            }
        }
        
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetchAll();
        // 确保始终返回数组
        $result = is_array($result) ? $result : [];
        
        if ($cache && Config::get('cache.enabled', false)) {
            $cacheKey = 'db_fetchall_' . md5($sql . json_encode($params));
            Cache::getInstance()->set($cacheKey, $result, $expire);
        }
        
        return $result;
    }
    
    /**
     * 插入数据
     * @param string $table 表名
     * @param array $data 数据
     * @return bool|int 插入ID或true（如果没有自增ID）
     */
    public function insert($table, $data) {
        $table = $this->table($table);
        $fields = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO {$table} ({$fields}) VALUES ({$placeholders})";
        $stmt = $this->query($sql, $data);
        
        // 检查是否有受影响的行
        $rowCount = $stmt->rowCount();
        if ($rowCount > 0) {
            // 清理缓存
            $this->clearCache($table);
            // 获取插入ID，如果没有自增ID则返回true
            $insertId = $this->pdo->lastInsertId();
            return $insertId ? $insertId : true;
        }
        
        return false;
    }
    
    /**
     * 更新数据
     * @param string $table 表名
     * @param array $data 数据
     * @param array $where 条件
     * @return int 受影响行数
     */
    public function update($table, $data, $where) {
        $table = $this->table($table);
        $set = [];
        $params = [];
        
        foreach ($data as $key => $value) {
            // 检查是否是SQL表达式（以SQL:开头）
            if (is_string($value) && substr($value, 0, 4) === 'SQL:') {
                $set[] = "{$key} = " . substr($value, 4);
            } else {
                $set[] = "{$key} = :{$key}";
                $params[$key] = $value;
            }
        }
        $set = implode(', ', $set);
        
        $whereClause = [];
        // 确保where参数是数组
        if (is_array($where)) {
            foreach ($where as $key => $value) {
                $whereClause[] = "{$key} = :where_{$key}";
                $params["where_{$key}"] = $value;
            }
            $whereClause = implode(' AND ', $whereClause);
        } else {
            // 如果是字符串，直接使用
            $whereClause = $where;
        }
        
        $sql = "UPDATE {$table} SET {$set} WHERE {$whereClause}";
        $stmt = $this->query($sql, $params);
        
        // 清理缓存
        $this->clearCache($table);
        
        return $stmt->rowCount();
    }
    
    /**
     * 删除数据
     * @param string $table 表名
     * @param array $where 条件
     * @return int 受影响行数
     */
    public function delete($table, $where) {
        $table = $this->table($table);
        $whereClause = [];
        $params = [];
        foreach ($where as $key => $value) {
            $whereClause[] = "{$key} = :{$key}";
            $params[$key] = $value;
        }
        $whereClause = implode(' AND ', $whereClause);
        
        $sql = "DELETE FROM {$table} WHERE {$whereClause}";
        $stmt = $this->query($sql, $params);
        
        // 清理缓存
        $this->clearCache($table);
        
        return $stmt->rowCount();
    }
    
    /**
     * 获取PDO实例
     * @return PDO
     */
    public function getPdo() {
        return $this->pdo;
    }
    
    /**
     * 开始事务
     * @return bool
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * 提交事务
     * @return bool
     */
    public function commit() {
        return $this->pdo->commit();
    }
    
    /**
     * 回滚事务
     * @return bool
     */
    public function rollback() {
        return $this->pdo->rollback();
    }
    
    /**
     * 清理缓存
     * @param string $table 表名
     */
    private function clearCache($table) {
        if (Config::get('cache.enabled', false)) {
            // 清理与该表相关的所有缓存
            $cacheKeyPattern = 'db_*';
            Cache::deleteByPattern($cacheKeyPattern);
        }
    }
}
