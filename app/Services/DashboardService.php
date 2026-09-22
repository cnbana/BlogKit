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
 * 仪表盘服务类
 * 负责处理仪表盘页面的所有业务逻辑
 */
class DashboardService {
    
    /**
     * @var Database 数据库实例
     */
    private $db;
    
    /**
     * @var Cache 缓存实例
     */
    private $cache;
    
    /**
     * 构造函数
     */
    public function __construct() {
        $this->db = Database::getInstance();
        $this->cache = Cache::getInstance();
    }
    
    /**
     * 获取系统健康状态
     * 
     * @return array 包含健康状态信息的数组
     */
    public function getSystemHealth() {
        $health = ['status' => 'healthy', 'items' => []];
        
        // 数据库连接检测
        $dbStatus = $this->checkDatabaseConnection();
        $health['items'][] = $dbStatus;
        
        // 磁盘空间检测
        $diskStatus = $this->checkDiskSpace();
        $health['items'][] = $diskStatus;
        
        // PHP版本检测
        $phpStatus = $this->checkPhpVersion();
        $health['items'][] = $phpStatus;
        
        // PHP内存限制检测
        $memoryStatus = $this->checkMemoryLimit();
        $health['items'][] = $memoryStatus;
        
        // 检查是否有警告或错误
        foreach ($health['items'] as $item) {
            if ($item['status'] === 'error') {
                $health['status'] = 'error';
                break;
            } elseif ($item['status'] === 'warning' && $health['status'] !== 'error') {
                $health['status'] = 'warning';
            }
        }
        
        return $health;
    }
    
    /**
     * 检查数据库连接
     * 
     * @return array 数据库连接状态
     */
    private function checkDatabaseConnection() {
        $status = ['name' => '数据库连接', 'status' => 'success', 'message' => '正常'];
        
        try {
            $startTime = microtime(true);
            $this->db->fetch("SELECT 1");
            $queryTime = round((microtime(true) - $startTime) * 1000, 2);
            $status['message'] = "正常 (响应: {$queryTime}ms)";
        } catch (Exception $e) {
            $status['status'] = 'error';
            $status['message'] = '连接失败: ' . $e->getMessage();
        }
        
        return $status;
    }
    
    /**
     * 检查磁盘空间
     * 
     * @return array 磁盘空间状态
     */
    private function checkDiskSpace() {
        $status = ['name' => '磁盘空间', 'status' => 'success', 'message' => '正常'];
        
        try {
            $freeSpace = disk_free_space(ROOT_PATH);
            $totalSpace = disk_total_space(ROOT_PATH);
            $usedPercent = round((1 - $freeSpace / $totalSpace) * 100, 2);
            
            $freeSpaceFormatted = $this->formatBytes($freeSpace);
            $totalSpaceFormatted = $this->formatBytes($totalSpace);
            
            $status['message'] = "已使用 {$usedPercent}% ({$freeSpaceFormatted} / {$totalSpaceFormatted})";
            
            if ($usedPercent > 90) {
                $status['status'] = 'error';
            } elseif ($usedPercent > 80) {
                $status['status'] = 'warning';
            }
        } catch (Exception $e) {
            $status['status'] = 'error';
            $status['message'] = '检测失败: ' . $e->getMessage();
        }
        
        return $status;
    }
    
    /**
     * 检查PHP版本
     * 
     * @return array PHP版本状态
     */
    private function checkPhpVersion() {
        $status = ['name' => 'PHP版本', 'status' => 'success', 'message' => '正常'];
        
        $currentVersion = PHP_VERSION;
        $requiredVersion = '7.4.0';
        
        $status['message'] = "当前: {$currentVersion} (最低要求: {$requiredVersion})";
        
        if (version_compare($currentVersion, $requiredVersion, '<')) {
            $status['status'] = 'error';
            $status['message'] = "版本过低: {$currentVersion} (需要 {$requiredVersion})";
        }
        
        return $status;
    }
    
    /**
     * 检查PHP内存限制
     * 
     * @return array 内存限制状态
     */
    private function checkMemoryLimit() {
        $status = ['name' => 'PHP内存限制', 'status' => 'success', 'message' => '正常'];
        
        $memoryLimit = ini_get('memory_limit');
        $status['message'] = "限制: {$memoryLimit}";
        
        return $status;
    }
    
    /**
     * 格式化字节数
     * 
     * @param int $bytes 字节数
     * @return string 格式化后的字符串
     */
    private function formatBytes($bytes) {
        if ($bytes < 1024) {
            return $bytes . ' B';
        } elseif ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 2) . ' KB';
        } elseif ($bytes < 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024), 2) . ' MB';
        } else {
            return round($bytes / (1024 * 1024 * 1024), 2) . ' GB';
        }
    }
    
    /**
     * 获取系统统计数据
     * 
     * @return array 统计数据
     */
    public function getStatistics() {
        $cacheKey = 'dashboard_stats';
        
        // 尝试从缓存获取
        $stats = $this->cache->get($cacheKey);
        
        if ($stats) {
            return $stats;
        }
        
        // 从数据库查询
        $stats = [
            'articles' => $this->getArticleCount(),
            'categories' => $this->getCategoryCount(),
            'tags' => $this->getTagCount(),
            'users' => $this->getUserCount(),
            'comments' => $this->getCommentCount(),
            'pendingComments' => $this->getPendingCommentCount(),
            'todayViews' => $this->getTodayViewCount(),
            'totalViews' => $this->getTotalViewCount(),
        ];
        
        // 缓存10分钟
        $this->cache->set($cacheKey, $stats, 600);
        
        return $stats;
    }
    
    /**
     * 获取文章总数
     * 
     * @return int 文章数量
     */
    public function getArticleCount() {
        $result = $this->db->fetch("SELECT COUNT(*) as count FROM {$this->db->table('article')} WHERE is_deleted = 0");
        return $result['count'] ?? 0;
    }
    
    /**
     * 获取分类总数
     * 
     * @return int 分类数量
     */
    public function getCategoryCount() {
        $result = $this->db->fetch("SELECT COUNT(*) as count FROM {$this->db->table('category')}");
        return $result['count'] ?? 0;
    }
    
    /**
     * 获取标签总数
     * 
     * @return int 标签数量
     */
    public function getTagCount() {
        $result = $this->db->fetch("SELECT COUNT(*) as count FROM {$this->db->table('tag')}");
        return $result['count'] ?? 0;
    }
    
    /**
     * 获取用户总数
     * 
     * @return int 用户数量
     */
    public function getUserCount() {
        $result = $this->db->fetch("SELECT COUNT(*) as count FROM {$this->db->table('user')}");
        return $result['count'] ?? 0;
    }
    
    /**
     * 获取评论总数
     * 
     * @return int 评论数量
     */
    public function getCommentCount() {
        $result = $this->db->fetch("SELECT COUNT(*) as count FROM {$this->db->table('comment')} WHERE is_deleted = 0");
        return $result['count'] ?? 0;
    }
    
    /**
     * 获取待审核评论数量
     * 
     * @return int 待审核评论数量
     */
    public function getPendingCommentCount() {
        $result = $this->db->fetch("SELECT COUNT(*) as count FROM {$this->db->table('comment')} WHERE status = 0 AND is_deleted = 0");
        return $result['count'] ?? 0;
    }
    
    /**
     * 获取今日访问量
     * 
     * @return int 今日访问量
     */
    public function getTodayViewCount() {
        $today = date('Y-m-d');
        $result = $this->db->fetch("SELECT SUM(view_count) as count FROM {$this->db->table('article')} WHERE DATE(updated_at) = ?", [$today]);
        return $result['count'] ?? 0;
    }
    
    /**
     * 获取总访问量
     * 
     * @return int 总访问量
     */
    public function getTotalViewCount() {
        $result = $this->db->fetch("SELECT SUM(view_count) as count FROM {$this->db->table('article')} WHERE is_deleted = 0");
        return $result['count'] ?? 0;
    }
    
    /**
     * 获取热门文章排行
     * 
     * @param int $limit 返回数量
     * @return array 热门文章列表
     */
    public function getHotArticles($limit = 10) {
        $sql = "SELECT id, title, view_count, created_at 
                FROM {$this->db->table('article')} 
                WHERE is_deleted = 0 AND status = 1
                ORDER BY view_count DESC 
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$limit]);
    }
    
    /**
     * 获取最近更新的文章
     * 
     * @param int $limit 返回数量
     * @return array 最近更新的文章列表
     */
    public function getRecentArticles($limit = 10) {
        $sql = "SELECT id, title, updated_at, status 
                FROM {$this->db->table('article')} 
                WHERE is_deleted = 0
                ORDER BY updated_at DESC 
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$limit]);
    }
    
    /**
     * 获取待处理事项
     * 
     * @return array 待处理事项列表
     */
    public function getPendingTasks() {
        $tasks = [];
        
        // 待审核评论
        $pendingComments = $this->getPendingCommentCount();
        if ($pendingComments > 0) {
            $tasks[] = [
                'type' => 'comment',
                'title' => '待审核评论',
                'count' => $pendingComments,
                'url' => 'admin.php?action=comment&status=pending'
            ];
        }
        
        // 待审核文章
        $pendingArticles = $this->db->fetch("SELECT COUNT(*) as count FROM {$this->db->table('article')} WHERE status = 0 AND is_deleted = 0");
        if ($pendingArticles['count'] > 0) {
            $tasks[] = [
                'type' => 'article',
                'title' => '待审核文章',
                'count' => $pendingArticles['count'],
                'url' => 'admin.php?action=article&status=pending'
            ];
        }
        
        return $tasks;
    }
    
    /**
     * 清除仪表盘缓存
     */
    public function clearCache() {
        $this->cache->delete('dashboard_stats');
    }
}