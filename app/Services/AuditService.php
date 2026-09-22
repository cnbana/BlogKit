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
 * 审计日志服务类
 * 负责记录和管理系统操作审计日志
 */
class AuditService {
    
    /**
     * @var Database 数据库实例
     */
    private $db;
    
    /**
     * @var string 数据库表前缀
     */
    private $prefix;
    
    /**
     * 构造函数
     */
    public function __construct() {
        $config = Config::get('database');
        $this->db = Database::getInstance();
        $this->prefix = $config['prefix'];
        
        // 确保审计日志表存在
        $this->ensureTableExists();
    }
    
    /**
     * 确保审计日志表存在
     */
    private function ensureTableExists() {
        try {
            $sql = "SHOW TABLES LIKE '{$this->prefix}audit_log'";
            $result = $this->db->fetch($sql);
            
            if (!$result) {
                // 创建审计日志表
                $createSql = "CREATE TABLE {$this->prefix}audit_log (
                    id BIGINT(20) NOT NULL AUTO_INCREMENT,
                    user_id BIGINT(20) NOT NULL,
                    user_name VARCHAR(50) NOT NULL,
                    action VARCHAR(50) NOT NULL,
                    target_type VARCHAR(50) NOT NULL,
                    target_id BIGINT(20) NOT NULL,
                    data TEXT,
                    ip VARCHAR(45) NOT NULL,
                    user_agent VARCHAR(255) DEFAULT NULL,
                    created_at INT(11) NOT NULL,
                    PRIMARY KEY (id),
                    KEY user_id (user_id),
                    KEY target_type (target_type),
                    KEY created_at (created_at),
                    KEY action (action)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='审计日志表'";
                
                $this->db->query($createSql);
            }
        } catch (Exception $e) {
            // 表可能已存在，忽略错误
        }
    }
    
    /**
     * 记录审计日志
     * 
     * @param string $action 操作类型（create/update/delete等）
     * @param string $targetType 目标类型（article/user/comment等）
     * @param int $targetId 目标ID
     * @param array $data 额外数据（可选）
     */
    public static function log($action, $targetType, $targetId, $data = []) {
        $service = new self();
        $service->recordLog($action, $targetType, $targetId, $data);
    }
    
    /**
     * 记录日志（内部方法）
     * 
     * @param string $action 操作类型
     * @param string $targetType 目标类型
     * @param int $targetId 目标ID
     * @param array $data 额外数据
     */
    private function recordLog($action, $targetType, $targetId, $data = []) {
        // 获取当前登录用户信息
        $userId = $_SESSION['admin']['id'] ?? 0;
        $userName = $_SESSION['admin']['username'] ?? 'system';
        
        // 获取客户端IP
        $ip = $this->getClientIp();
        
        // 获取User-Agent
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // 准备插入数据
        $sql = "INSERT INTO {$this->prefix}audit_log 
                (user_id, user_name, action, target_type, target_id, data, ip, user_agent, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $userId,
            $userName,
            $action,
            $targetType,
            $targetId,
            json_encode($data),
            $ip,
            $userAgent,
            time()
        ];
        
        try {
            $this->db->query($sql, $params);
        } catch (Exception $e) {
            // 记录审计日志失败时记录到系统日志
            if (class_exists('Log')) {
                Log::error('Audit log record failed: ' . $e->getMessage(), 'audit', $params);
            }
        }
    }
    
    /**
     * 获取客户端真实IP
     * 
     * @return string IP地址
     */
    private function getClientIp() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        // 检查代理头
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED'
        ];
        
        foreach ($headers as $header) {
            if (isset($_SERVER[$header])) {
                $forwardedIps = explode(',', $_SERVER[$header]);
                $ip = trim($forwardedIps[0]);
                
                // 验证IP格式
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    break;
                }
            }
        }
        
        return $ip;
    }
    
    /**
     * 查询审计日志
     * 
     * @param array $filters 筛选条件
     * @param int $page 页码
     * @param int $limit 每页数量
     * @return array ['total' => 总数, 'data' => 日志列表]
     */
    public function queryLogs($filters = [], $page = 1, $limit = 20) {
        $sql = "SELECT * FROM {$this->prefix}audit_log WHERE 1 = 1";
        $totalSql = "SELECT COUNT(*) as total FROM {$this->prefix}audit_log WHERE 1 = 1";
        $params = [];
        
        // 按用户ID筛选
        if (!empty($filters['user_id'])) {
            $sql .= " AND user_id = ?";
            $totalSql .= " AND user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        // 按操作类型筛选
        if (!empty($filters['action'])) {
            $sql .= " AND action = ?";
            $totalSql .= " AND action = ?";
            $params[] = $filters['action'];
        }
        
        // 按目标类型筛选
        if (!empty($filters['target_type'])) {
            $sql .= " AND target_type = ?";
            $totalSql .= " AND target_type = ?";
            $params[] = $filters['target_type'];
        }
        
        // 按目标ID筛选
        if (!empty($filters['target_id'])) {
            $sql .= " AND target_id = ?";
            $totalSql .= " AND target_id = ?";
            $params[] = $filters['target_id'];
        }
        
        // 按IP筛选
        if (!empty($filters['ip'])) {
            $sql .= " AND ip LIKE ?";
            $totalSql .= " AND ip LIKE ?";
            $params[] = '%' . $filters['ip'] . '%';
        }
        
        // 按时间范围筛选
        if (!empty($filters['start_time'])) {
            $sql .= " AND created_at >= ?";
            $totalSql .= " AND created_at >= ?";
            $params[] = strtotime($filters['start_time']);
        }
        
        if (!empty($filters['end_time'])) {
            $sql .= " AND created_at <= ?";
            $totalSql .= " AND created_at <= ?";
            $params[] = strtotime($filters['end_time'] . ' 23:59:59');
        }
        
        // 排序
        $sql .= " ORDER BY created_at DESC";
        
        // 分页
        $offset = ($page - 1) * $limit;
        $sql .= " LIMIT ? OFFSET ?";
        $limitParams = $params;
        $limitParams[] = $limit;
        $limitParams[] = $offset;
        
        // 获取总数
        $totalResult = $this->db->fetch($totalSql, $params);
        $total = $totalResult['total'] ?? 0;
        
        // 获取数据
        $data = $this->db->fetchAll($sql, $limitParams);
        
        // 解析JSON数据
        foreach ($data as &$item) {
            $item['data'] = json_decode($item['data'], true) ?? [];
            $item['created_at_formatted'] = date('Y-m-d H:i:s', $item['created_at']);
        }
        
        return [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'data' => $data
        ];
    }
    
    /**
     * 获取操作类型列表
     * 
     * @return array 操作类型列表
     */
    public function getActionTypes() {
        return [
            'create' => '创建',
            'update' => '更新',
            'delete' => '删除',
            'login' => '登录',
            'logout' => '登出',
            'import' => '导入',
            'export' => '导出',
            'publish' => '发布',
            'draft' => '保存草稿',
            'approve' => '审核通过',
            'reject' => '拒绝',
            'restore' => '恢复',
            'clear_cache' => '清理缓存',
            'backup' => '备份',
            'migrate' => '数据迁移'
        ];
    }
    
    /**
     * 获取目标类型列表
     * 
     * @return array 目标类型列表
     */
    public function getTargetTypes() {
        return [
            'article' => '文章',
            'category' => '分类',
            'tag' => '标签',
            'user' => '用户',
            'comment' => '评论',
            'config' => '配置',
            'plugin' => '插件',
            'theme' => '主题',
            'media' => '媒体文件',
            'page' => '页面'
        ];
    }
    
    /**
     * 获取用户操作统计
     * 
     * @param int $days 天数
     * @return array 用户操作统计
     */
    public function getUserStats($days = 7) {
        $startTime = time() - ($days * 24 * 60 * 60);
        
        $sql = "SELECT user_id, user_name, COUNT(*) as count 
                FROM {$this->prefix}audit_log 
                WHERE created_at >= ?
                GROUP BY user_id, user_name 
                ORDER BY count DESC";
        
        return $this->db->fetchAll($sql, [$startTime]);
    }
    
    /**
     * 获取操作统计
     * 
     * @param int $days 天数
     * @return array 操作统计
     */
    public function getActionStats($days = 7) {
        $startTime = time() - ($days * 24 * 60 * 60);
        
        $sql = "SELECT action, COUNT(*) as count 
                FROM {$this->prefix}audit_log 
                WHERE created_at >= ?
                GROUP BY action 
                ORDER BY count DESC";
        
        return $this->db->fetchAll($sql, [$startTime]);
    }
    
    /**
     * 获取最近的审计日志
     * 
     * @param int $limit 返回数量
     * @return array 日志列表
     */
    public function getRecentLogs($limit = 10) {
        $sql = "SELECT * FROM {$this->prefix}audit_log 
                ORDER BY created_at DESC 
                LIMIT ?";
        
        $data = $this->db->fetchAll($sql, [$limit]);
        
        foreach ($data as &$item) {
            $item['data'] = json_decode($item['data'], true) ?? [];
            $item['created_at_formatted'] = date('Y-m-d H:i:s', $item['created_at']);
        }
        
        return $data;
    }
    
    /**
     * 删除指定天数之前的日志
     * 
     * @param int $days 天数
     * @return int 删除的记录数
     */
    public function cleanOldLogs($days = 90) {
        $cutoffTime = time() - ($days * 24 * 60 * 60);
        
        $sql = "DELETE FROM {$this->prefix}audit_log WHERE created_at < ?";
        $this->db->query($sql, [$cutoffTime]);
        
        return $this->db->rowCount();
    }
    
    /**
     * 导出审计日志
     * 
     * @param array $filters 筛选条件
     * @return array 日志数据
     */
    public function exportLogs($filters = []) {
        $sql = "SELECT * FROM {$this->prefix}audit_log WHERE 1 = 1";
        $params = [];
        
        if (!empty($filters['start_time'])) {
            $sql .= " AND created_at >= ?";
            $params[] = strtotime($filters['start_time']);
        }
        
        if (!empty($filters['end_time'])) {
            $sql .= " AND created_at <= ?";
            $params[] = strtotime($filters['end_time'] . ' 23:59:59');
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        $data = $this->db->fetchAll($sql, $params);
        
        foreach ($data as &$item) {
            $item['data'] = json_decode($item['data'], true) ?? [];
            $item['created_at_formatted'] = date('Y-m-d H:i:s', $item['created_at']);
        }
        
        return $data;
    }
}