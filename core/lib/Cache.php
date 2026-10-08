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


class Cache {
    private static $instance;
    private $cacheType;
    private $cachePath;
    private $cacheExpire;
    private $cacheSizeLimit;
    private $redis = null;
    private $redisEnabled = false;
    private $stats = [
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0,
        'clears' => 0,
        'start_time' => 0
    ];
    
    /**
     * 单例模式获取缓存实例
     * @return Cache
     */
    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 构造函数
     */
    private function __construct() {
        // 初始化统计信息
        $this->stats['start_time'] = time();
        
        // 从配置中获取缓存设置（支持两种格式的配置键）
        $this->cacheType = Config::get('cache_type', Config::get('cache.type', 'file'));
        $this->cachePath = Config::get('cache_path', Config::get('cache.path', STORAGE_PATH . '/cache'));
        $this->cacheExpire = Config::get('cache_expire', Config::get('cache.expire', 3600));
        $this->cacheSizeLimit = Config::get('cache_size_limit', Config::get('cache.size_limit', 100)) * 1024 * 1024; // 转换为字节
        // 注意：当前仅实现了 LRU（按文件最后修改时间清理最旧的缓存文件）。
        // cache_cleanup_strategy 的配置项在后台页面展示为“只读说明”，
        // 此处不再依赖它做逻辑分支，以避免“页面声明与实际行为不一致”。
        $this->cacheCleanupStrategy = 'lru';
        $this->cacheCompression = Config::get('cache_compression', 0);
        $this->cacheKeyPrefix = Config::get('cache_key_prefix', 'blog_');
        $this->cacheBatchOperation = Config::get('cache_batch_operation', 1);
        $this->cacheMonitoringEnabled = Config::get('cache_monitoring_enabled', 1);
        
        // 确保缓存目录存在
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }
        
        // 初始化 Redis 连接（如果配置为 redis 类型或启用了 redis）
        $this->initRedis();
        
        // 初始化缓存监控
        if ($this->cacheMonitoringEnabled) {
            $this->initCacheMonitoring();
        }
    }
    
    /**
     * 初始化 Redis 连接
     */
    private function initRedis() {
        // 如果配置为 redis 类型，尝试连接 Redis
        if ($this->cacheType == 'redis') {
            try {
                $redisConfig = Config::get('redis', []);
                $host = $redisConfig['host'] ?? '127.0.0.1';
                $port = $redisConfig['port'] ?? 6379;
                $password = $redisConfig['password'] ?? '';
                $timeout = $redisConfig['timeout'] ?? 2.5;
                
                $this->redis = new Redis();
                $this->redis->connect($host, $port, $timeout);
                
                if (!empty($password)) {
                    $this->redis->auth($password);
                }
                
                // 测试连接
                $this->redis->ping();
                $this->redisEnabled = true;
            } catch (Exception $e) {
                error_log('Redis connection failed: ' . $e->getMessage());
                $this->redis = null;
                $this->redisEnabled = false;
                // 降级到文件缓存
                $this->cacheType = 'file';
            }
        }
    }
    
    /**
     * 初始化缓存监控
     */
    private function initCacheMonitoring() {
        // 检查缓存监控数据表是否存在
        $db = Database::getInstance();
        $result = $db->query("SHOW TABLES LIKE 'bk_cache_stats'");
        if ($result->num_rows === 0) {
            // 表不存在，创建表
            $createTableSql = "CREATE TABLE `bk_cache_stats` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `timestamp` datetime NOT NULL,
                `hits` int(11) NOT NULL,
                `misses` int(11) NOT NULL,
                `sets` int(11) NOT NULL,
                `deletes` int(11) NOT NULL,
                `cache_size` bigint(20) NOT NULL,
                `hit_rate` decimal(5,2) NOT NULL,
                PRIMARY KEY (`id`),
                KEY `timestamp` (`timestamp`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            $db->query($createTableSql);
        }
    }
    
    /**
     * 设置缓存（支持标签）
     * @param string $key 缓存键
     * @param mixed $value 缓存值
     * @param int|array $expireOrTags 过期时间（秒）或标签数组，默认使用配置值
     * @param array $tags 标签数组（当第三个参数是过期时间时使用）
     * @return bool
     */
    public function set($key, $value, $expireOrTags = null, $tags = []) {
        // 兼容旧的调用方式：set(key, value, expire) 和新的：set(key, value, tags)
        $expire = $this->cacheExpire;
        if (is_numeric($expireOrTags)) {
            $expire = $expireOrTags;
        } elseif (is_array($expireOrTags)) {
            $tags = $expireOrTags;
        }
        
        $cacheKey = $this->generateCacheKey($key);
        
        try {
            if ($this->cacheType == 'file') {
                $result = $this->setFileCache($cacheKey, $value, $expire);
                if ($result && !empty($tags)) {
                    $this->saveTagIndex($cacheKey, $tags);
                }
                return $result;
            } elseif ($this->cacheType == 'redis') {
                return $this->setRedisCache($cacheKey, $value, $expire);
            }
        } catch (Exception $e) {
            error_log('Cache set error: ' . $e->getMessage());
            return false;
        }
        
        return false;
    }
    
    /**
     * 获取缓存
     * @param string $key 缓存键
     * @param mixed $default 默认值
     * @return mixed
     */
    public function get($key, $default = null) {
        $cacheKey = $this->generateCacheKey($key);
        
        try {
            if ($this->cacheType == 'file') {
                $value = $this->getFileCache($cacheKey);
            } elseif ($this->cacheType == 'redis') {
                $value = $this->getRedisCache($cacheKey);
            } else {
                $value = false;
            }
            
            if ($value !== false) {
                $this->stats['hits']++;
                return $value;
            } else {
                $this->stats['misses']++;
                return $default;
            }
        } catch (Exception $e) {
            error_log('Cache get error: ' . $e->getMessage());
            $this->stats['misses']++;
            return $default;
        }
    }
    
    /**
     * 删除缓存
     * @param string $key 缓存键
     * @return bool
     */
    public function delete($key) {
        $cacheKey = $this->generateCacheKey($key);
        
        try {
            if ($this->cacheType == 'file') {
                $result = $this->deleteFileCache($cacheKey);
            } elseif ($this->cacheType == 'redis') {
                $result = $this->deleteRedisCache($cacheKey);
            } else {
                $result = false;
            }
            
            if ($result) {
                $this->stats['deletes']++;
            }
            return $result;
        } catch (Exception $e) {
            error_log('Cache delete error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 清空所有缓存
     * @return bool
     */
    public function clear() {
        try {
            if ($this->cacheType == 'file') {
                $result = $this->clearFileCache();
            } elseif ($this->cacheType == 'redis') {
                $result = $this->clearRedisCache();
            } else {
                $result = false;
            }
            
            if ($result) {
                $this->stats['clears']++;
            }
            return $result;
        } catch (Exception $e) {
            error_log('Cache clear error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 获取缓存统计信息
     * @return array
     */
    public function getStats() {
        // 计算缓存命中率
        $total = $this->stats['hits'] + $this->stats['misses'];
        $hitRate = $total > 0 ? ($this->stats['hits'] / $total) * 100 : 0;
        
        // 计算运行时间
        $uptime = time() - $this->stats['start_time'];
        
        return array_merge($this->stats, [
            'hit_rate' => round($hitRate, 2),
            'uptime' => $uptime,
            'cache_size' => $this->getCacheSize(),
            'cache_size_mb' => round($this->getCacheSize() / (1024 * 1024), 2)
        ]);
    }
    
    /**
     * 获取缓存大小
     * @return int 缓存大小（字节）
     */
    public function getCacheSize() {
        if ($this->cacheType == 'file') {
            return $this->getDirectorySize($this->cachePath);
        }
        return 0;
    }
    
    /**
     * 生成缓存键（统一命名空间: prefix + namespace + md5）
     * @param string $key 原始键
     * @return string
     */
    private function generateCacheKey($key) {
        $prefix = $this->cacheKeyPrefix;
        // 自动检测命名空间：例如 "article:list:home" -> "article_list_home_"
        if (strpos($key, ':') !== false) {
            $parts = explode('|', $key, 2);
            $keyPart = $parts[0];
            $namespace = str_replace(':', '_', $keyPart) . '_';
            return $prefix . $namespace . md5($key);
        }
        return $prefix . md5($key);
    }
    
    /**
     * 保存标签索引（将缓存键关联到标签）
     * @param string $cacheKey 缓存键（已md5）
     * @param array $tags 标签数组
     */
    private function saveTagIndex($cacheKey, $tags) {
        $tagDir = $this->cachePath . '/tags/';
        if (!is_dir($tagDir)) {
            mkdir($tagDir, 0755, true);
        }
        foreach ($tags as $tag) {
            $tagFile = $tagDir . md5($tag) . '.tag';
            $keys = [];
            if (file_exists($tagFile)) {
                $keys = @unserialize(file_get_contents($tagFile)) ?: [];
            }
            if (!in_array($cacheKey, $keys)) {
                $keys[] = $cacheKey;
            }
            file_put_contents($tagFile, serialize($keys));
        }
    }
    
    /**
     * 按标签失效缓存（删除所有关联该标签的缓存）
     * @param string $tag 标签名
     * @return int 删除的缓存条目数
     */
    public function invalidateTag($tag) {
        $tagFile = $this->cachePath . '/tags/' . md5($tag) . '.tag';
        $count = 0;
        if (file_exists($tagFile)) {
            $keys = @unserialize(file_get_contents($tagFile)) ?: [];
            foreach ($keys as $cacheKey) {
                if ($this->deleteFileCache($cacheKey)) {
                    $count++;
                }
            }
            // 清除空的标签文件
            unlink($tagFile);
        }
        return $count;
    }
    
    /**
     * 批量失效标签
     * @param array $tags 标签名数组
     * @return int 删除的缓存条目数
     */
    public function invalidateTags($tags) {
        $count = 0;
        foreach ($tags as $tag) {
            $count += $this->invalidateTag($tag);
        }
        return $count;
    }
    
    /**
     * 静态方法：按标签失效
     * @param string $tag
     * @return int
     */
    public static function invalidateTagStatic($tag) {
        return self::getInstance()->invalidateTag($tag);
    }
    
    /**
     * 静态方法：批量失效标签
     * @param array $tags
     * @return int
     */
    public static function invalidateTagsStatic($tags) {
        return self::getInstance()->invalidateTags($tags);
    }
    
    /**
     * 静态方法：设置缓存（带标签）
     * @param string $key
     * @param mixed $value
     * @param int $expire
     * @param array $tags
     * @return bool
     */
    public static function setCache($key, $value, $expire = null, $tags = []) {
        return self::getInstance()->set($key, $value, $expire, $tags);
    }
    
    /**
     * 设置文件缓存
     * @param string $key 缓存键
     * @param mixed $value 缓存值
     * @param int $expire 过期时间
     * @return bool
     */
    private function setFileCache($key, $value, $expire) {
        $file = $this->cachePath . '/' . $key . '.cache';
        $data = [
            'value' => $value,
            'expire' => time() + $expire,
            'created' => time(),
            'compressed' => false
        ];
        
        // 应用缓存压缩
        if ($this->cacheCompression) {
            $serializedData = serialize($data);
            $compressedData = gzcompress($serializedData, 6);
            if ($compressedData) {
                $data['compressed'] = true;
                if (file_put_contents($file, $compressedData) !== false) {
                    $this->stats['sets']++;
                    $this->checkCacheSize();
                    $this->saveCacheStats();
                    return true;
                }
            }
        }
        
        // 未启用压缩或压缩失败时，使用普通序列化
        if (file_put_contents($file, serialize($data)) !== false) {
            $this->stats['sets']++;
            $this->checkCacheSize();
            $this->saveCacheStats();
            return true;
        }
        return false;
    }
    
    /**
     * 获取文件缓存
     * @param string $key 缓存键
     * @return mixed
     */
    private function getFileCache($key) {
        $file = $this->cachePath . '/' . $key . '.cache';
        
        if (!file_exists($file)) {
            return false;
        }
        
        $fileContent = file_get_contents($file);
        $data = false;
        
        // 尝试解压
        $uncompressedData = @gzuncompress($fileContent);
        if ($uncompressedData) {
            $data = unserialize($uncompressedData);
        } else {
            // 尝试直接反序列化
            $data = @unserialize($fileContent);
        }
        
        if (!$data || time() > $data['expire']) {
            unlink($file);
            return false;
        }
        
        return $data['value'];
    }
    
    /**
     * 删除文件缓存
     * @param string $key 缓存键
     * @return bool
     */
    private function deleteFileCache($key) {
        $file = $this->cachePath . '/' . $key . '.cache';
        
        if (file_exists($file)) {
            return unlink($file);
        }
        return false;
    }
    
    /**
     * 清空文件缓存
     * @return bool
     */
    private function clearFileCache() {
        // 清理.cache后缀的缓存文件
        $files = glob($this->cachePath . '/*.cache');
        
        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        
        // 清理RSS缓存文件
        $rssCacheFile = $this->cachePath . '/rss_feed.xml';
        if (file_exists($rssCacheFile)) {
            unlink($rssCacheFile);
        }
        
        return true;
    }
    
    /**
     * 设置Redis缓存
     * @param string $key 缓存键
     * @param mixed $value 缓存值
     * @param int $expire 过期时间
     * @return bool
     */
    private function setRedisCache($key, $value, $expire) {
        if (!$this->redisEnabled || !$this->redis) {
            return false;
        }
        
        try {
            $serializedData = serialize($value);
            
            // 如果启用了压缩，压缩数据
            if ($this->cacheCompression) {
                $serializedData = gzcompress($serializedData, 6);
            }
            
            // 使用 setEx 设置带过期时间的键
            $result = $this->redis->setEx($key, $expire, $serializedData);
            
            if ($result) {
                $this->stats['sets']++;
                $this->saveCacheStats();
            }
            
            return $result;
        } catch (Exception $e) {
            error_log('Redis set error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 获取Redis缓存
     * @param string $key 缓存键
     * @return mixed
     */
    private function getRedisCache($key) {
        if (!$this->redisEnabled || !$this->redis) {
            return false;
        }
        
        try {
            $data = $this->redis->get($key);
            
            if ($data === false) {
                return false;
            }
            
            // 尝试解压
            $uncompressedData = @gzuncompress($data);
            if ($uncompressedData !== false) {
                $data = $uncompressedData;
            }
            
            return unserialize($data);
        } catch (Exception $e) {
            error_log('Redis get error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 删除Redis缓存
     * @param string $key 缓存键
     * @return bool
     */
    private function deleteRedisCache($key) {
        if (!$this->redisEnabled || !$this->redis) {
            return false;
        }
        
        try {
            $result = $this->redis->del($key);
            
            if ($result > 0) {
                $this->stats['deletes']++;
            }
            
            return $result > 0;
        } catch (Exception $e) {
            error_log('Redis delete error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 清空Redis缓存
     * @return bool
     */
    private function clearRedisCache() {
        if (!$this->redisEnabled || !$this->redis) {
            return false;
        }
        
        try {
            $this->redis->flushDb();
            $this->stats['clears']++;
            $this->saveCacheStats();
            return true;
        } catch (Exception $e) {
            error_log('Redis clear error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 检查缓存大小
     */
    private function checkCacheSize() {
        $currentSize = $this->getDirectorySize($this->cachePath);
        // $this->cacheSizeLimit 已在构造函数中转换为字节（见第42行），此处无需再次转换
        $maxSize = $this->cacheSizeLimit;
        
        if ($currentSize > $maxSize) {
            $this->cleanOldCache();
        }
    }
    
    /**
     * 清理旧缓存
     */
    private function cleanOldCache() {
        $files = glob($this->cachePath . '/*.cache');
        $fileInfos = [];
        
        foreach ($files as $file) {
            if (file_exists($file)) {
                $fileInfos[] = [
                    'file' => $file,
                    'mtime' => filemtime($file),
                    'size' => filesize($file)
                ];
            }
        }
        
        // 按修改时间排序
        usort($fileInfos, function($a, $b) {
            return $a['mtime'] <=> $b['mtime'];
        });
        
        // 删除最旧的文件，直到缓存大小符合限制
        $currentSize = $this->getDirectorySize($this->cachePath);
        // $this->cacheSizeLimit 已在构造函数中转换为字节（见第42行），此处无需再次转换
        $maxSize = $this->cacheSizeLimit;
        $deletedSize = 0;
        
        foreach ($fileInfos as $info) {
            if ($currentSize <= $maxSize) {
                break;
            }
            
            if (file_exists($info['file'])) {
                unlink($info['file']);
                $deletedSize += $info['size'];
                $currentSize -= $info['size'];
            }
        }
    }
    
    /**
     * 获取目录大小
     * @param string $dir 目录路径
     * @return int 目录大小（字节）
     */
    private function getDirectorySize($dir) {
        $size = 0;
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($files as $file) {
            $size += $file->getSize();
        }
        
        return $size;
    }
    
    /**
     * 静态方法：获取缓存
     * @param string $key 缓存键
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function getCache($key, $default = null) {
        return self::getInstance()->get($key, $default);
    }
    
    /**
     * 静态方法：删除缓存
     * @param string $key 缓存键
     * @return bool
     */
    public static function deleteCache($key) {
        return self::getInstance()->delete($key);
    }
    
    /**
     * 静态方法：清空缓存
     * @return bool
     */
    public static function clearCache() {
        return self::getInstance()->clear();
    }
    
    /**
     * 静态方法：获取缓存统计信息
     * @return array
     */
    public static function getCacheStats() {
        return self::getInstance()->getStats();
    }
    
    /**
     * 静态方法：获取缓存大小
     * @return int
     */
    public static function getCacheSizeStatic() {
        return self::getInstance()->getCacheSize();
    }
    
    /**
     * 静态方法：获取缓存（别名）
     * @param string $key 缓存键
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function getStatic($key, $default = null) {
        return self::getCache($key, $default);
    }
    
    /**
     * 静态方法：设置缓存（别名）
     * @param string $key 缓存键
     * @param mixed $value 缓存值
     * @param int $expire 过期时间
     * @return bool
     */
    public static function setStatic($key, $value, $expire = null) {
        return self::setCache($key, $value, $expire);
    }
    
    /**
     * 静态方法：删除缓存（别名）
     * @param string $key 缓存键
     * @return bool
     */
    public static function deleteStatic($key) {
        return self::deleteCache($key);
    }
    
    /**
     * 静态方法：清空缓存（别名）
     * @return bool
     */
    public static function clearStatic() {
        return self::clearCache();
    }
    
    /**
     * 静态方法：根据模式删除缓存
     * @param string $pattern 缓存键模式
     * @return bool
     */
    public static function deleteByPattern($pattern) {
        $instance = self::getInstance();
        if ($instance->cacheType == 'file') {
            $files = glob($instance->cachePath . '/' . $pattern . '*.cache');
            foreach ($files as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
            return true;
        }
        return false;
    }
    
    /**
     * 批量设置缓存
     * @param array $items 缓存项数组，格式为 ['key' => ['value' => $value, 'expire' => $expire]]
     * @return bool
     */
    public function batchSet($items) {
        if (!$this->cacheBatchOperation) {
            return false;
        }
        
        $success = true;
        foreach ($items as $key => $item) {
            $value = $item['value'];
            $expire = isset($item['expire']) ? $item['expire'] : $this->cacheExpire;
            if (!$this->set($key, $value, $expire)) {
                $success = false;
            }
        }
        return $success;
    }
    
    /**
     * 批量获取缓存
     * @param array $keys 缓存键数组
     * @param mixed $default 默认值
     * @return array
     */
    public function batchGet($keys, $default = null) {
        if (!$this->cacheBatchOperation) {
            return [];
        }
        
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }
        return $result;
    }
    
    /**
     * 批量删除缓存
     * @param array $keys 缓存键数组
     * @return bool
     */
    public function batchDelete($keys) {
        if (!$this->cacheBatchOperation) {
            return false;
        }
        
        $success = true;
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }
        return $success;
    }
    
    /**
     * 保存缓存统计信息
     */
    private function saveCacheStats() {
        if (!$this->cacheMonitoringEnabled) {
            return;
        }
        
        try {
            $db = Database::getInstance();
            $stats = $this->getStats();
            $hitRate = $stats['hit_rate'];
            $cacheSize = $stats['cache_size'];
            
            $sql = "INSERT INTO `bk_cache_stats` (`timestamp`, `hits`, `misses`, `sets`, `deletes`, `cache_size`, `hit_rate`) 
                    VALUES (NOW(), ?, ?, ?, ?, ?, ?)";
            $db->query($sql, [
                $this->stats['hits'],
                $this->stats['misses'],
                $this->stats['sets'],
                $this->stats['deletes'],
                $cacheSize,
                $hitRate
            ]);
            
            // 清理过期的统计数据
            $historyDays = Config::get('cache_history_days', 7);
            $cleanupSql = "DELETE FROM `bk_cache_stats` WHERE `timestamp` < DATE_SUB(NOW(), INTERVAL ? DAY)";
            $db->query($cleanupSql, [$historyDays]);
        } catch (Exception $e) {
            // 忽略数据库错误，不影响缓存功能
        }
    }
    
    /**
     * 获取缓存健康状态
     * @return array
     */
    public function getHealthStatus() {
        $status = [
            'status' => 'healthy',
            'message' => '缓存系统运行正常',
            'details' => []
        ];
        
        // 检查 Redis 状态（如果配置为 redis 类型）
        if ($this->cacheType == 'redis') {
            if ($this->redisEnabled && $this->redis) {
                try {
                    $this->redis->ping();
                    $status['details']['redis'] = 'connected';
                    
                    // 获取 Redis 信息
                    $info = $this->redis->info();
                    $status['details']['redis_version'] = $info['redis_version'] ?? 'unknown';
                    $status['details']['redis_used_memory'] = $info['used_memory_human'] ?? 'unknown';
                } catch (Exception $e) {
                    $status['status'] = 'critical';
                    $status['message'] = 'Redis 连接失败';
                    $status['details']['redis'] = 'disconnected';
                }
            } else {
                $status['status'] = 'warning';
                $status['message'] = 'Redis 不可用，已降级到文件缓存';
                $status['details']['redis'] = 'fallback_to_file';
            }
        }
        
        // 检查缓存目录（文件缓存或降级时）
        if ($this->cacheType == 'file' || !$this->redisEnabled) {
            if (!is_dir($this->cachePath)) {
                $status['status'] = 'critical';
                $status['message'] = '缓存目录不存在';
            } elseif (!is_writable($this->cachePath)) {
                $status['status'] = 'critical';
                $status['message'] = '缓存目录不可写';
            } else {
                $diskFree = disk_free_space($this->cachePath);
                $diskTotal = disk_total_space($this->cachePath);
                $diskUsage = ($diskTotal - $diskFree) / $diskTotal * 100;
                $status['details']['disk_usage'] = round($diskUsage, 2) . '%';
                if ($diskUsage > 90) {
                    $status['status'] = 'warning';
                    $status['message'] = '磁盘空间不足';
                }
            }
        }
        
        // 检查缓存大小
        $cacheSize = $this->getCacheSize();
        $sizeLimit = $this->cacheSizeLimit;
        $cacheUsage = $sizeLimit > 0 ? ($cacheSize / $sizeLimit) * 100 : 0;
        $status['details']['cache_usage'] = round($cacheUsage, 2) . '%';
        
        // 检查缓存监控
        if ($this->cacheMonitoringEnabled) {
            try {
                $db = Database::getInstance();
                $result = $db->query("SHOW TABLES LIKE 'bk_cache_stats'");
                if ($result->num_rows === 0) {
                    $status['details']['monitoring'] = 'disabled';
                } else {
                    $status['details']['monitoring'] = 'enabled';
                }
            } catch (Exception $e) {
                $status['details']['monitoring'] = 'error';
            }
        }
        
        return $status;
    }
    
    /**
     * 静态方法：批量设置缓存
     * @param array $items 缓存项数组
     * @return bool
     */
    public static function batchSetCache($items) {
        return self::getInstance()->batchSet($items);
    }
    
    /**
     * 静态方法：批量获取缓存
     * @param array $keys 缓存键数组
     * @param mixed $default 默认值
     * @return array
     */
    public static function batchGetCache($keys, $default = null) {
        return self::getInstance()->batchGet($keys, $default);
    }
    
    /**
     * 静态方法：批量删除缓存
     * @param array $keys 缓存键数组
     * @return bool
     */
    public static function batchDeleteCache($keys) {
        return self::getInstance()->batchDelete($keys);
    }
    
    /**
     * 静态方法：获取缓存健康状态
     * @return array
     */
    public static function getCacheHealthStatus() {
        return self::getInstance()->getHealthStatus();
    }
}
