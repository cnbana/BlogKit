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


class ReadHistoryModel {
    private $db;
    private $prefix;
    
    public function __construct() {
        $config = Config::get('database');
        $this->db = Database::getInstance();
        $this->prefix = $config['prefix'];
        
        // 自动创建阅读历史表（如果不存在）
        $this->createTable();
    }
    
    // 创建阅读历史表
    private function createTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `{$this->prefix}read_history` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `user_id` bigint(20) NOT NULL COMMENT '用户ID',
            `article_id` bigint(20) NOT NULL COMMENT '文章ID',
            `read_at` int(11) NOT NULL COMMENT '阅读时间',
            `is_deleted` tinyint(4) NOT NULL DEFAULT '0' COMMENT '是否删除：0-未删除，1-已删除',
            PRIMARY KEY (`id`),
            UNIQUE KEY `user_article` (`user_id`,`article_id`),
            KEY `read_at` (`read_at`),
            KEY `user_id` (`user_id`),
            KEY `article_id` (`article_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='阅读历史表';";
        
        try {
            $this->db->query($sql);
        } catch (Exception $e) {
            // 忽略创建表的错误
        }
    }
    
    // 添加或更新阅读历史
    public function addReadHistory($userId, $articleId) {
        // 检查是否已存在阅读记录
        $sql = "SELECT id FROM {$this->prefix}read_history WHERE user_id = ? AND article_id = ? AND is_deleted = 0";
        $result = $this->db->query($sql, [$userId, $articleId]);
        $row = $result->fetch();
        
        if ($row) {
            // 更新阅读时间
            $sql = "UPDATE {$this->prefix}read_history SET read_at = ? WHERE id = ?";
            return $this->db->query($sql, [time(), $row['id']]);
        } else {
            // 插入新的阅读记录
            $sql = "INSERT INTO {$this->prefix}read_history (user_id, article_id, read_at) VALUES (?, ?, ?)";
            return $this->db->query($sql, [$userId, $articleId, time()]);
        }
    }
    
    // 删除指定的阅读记录
    public function deleteReadHistory($userId, $historyId) {
        $sql = "DELETE FROM {$this->prefix}read_history WHERE id = ? AND user_id = ?";
        return $this->db->query($sql, [$historyId, $userId]);
    }
    
    // 清空用户的阅读历史
    public function clearReadHistory($userId) {
        $sql = "DELETE FROM {$this->prefix}read_history WHERE user_id = ?";
        return $this->db->query($sql, [$userId]);
    }
    
    // 获取用户的阅读历史列表
    public function getReadHistory($userId, $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT 
                    r.id, 
                    r.read_at, 
                    a.id as article_id, 
                    a.title, 
                    a.view_count, 
                    a.created_at as article_created_at,
                    c.name as category_name,
                    u.nickname as author
                FROM 
                    {$this->prefix}read_history r
                LEFT JOIN 
                    {$this->prefix}article a ON r.article_id = a.id
                LEFT JOIN 
                    {$this->prefix}category c ON a.category_id = c.id
                LEFT JOIN 
                    {$this->prefix}user u ON a.user_id = u.id
                WHERE 
                    r.user_id = ? AND r.is_deleted = 0 AND a.status = 1 AND a.is_deleted = 0
                ORDER BY 
                    r.read_at DESC
                LIMIT ? OFFSET ?";
        
        $result = $this->db->query($sql, [$userId, $limit, $offset]);
        $articles = $result->fetchAll();
        
        // 格式化时间
        foreach ($articles as $index => $article) {
            $articles[$index]['read_at_formatted'] = date('Y-m-d H:i:s', $article['read_at']);
            $articles[$index]['article_created_at_formatted'] = date('Y-m-d H:i:s', $article['article_created_at']);
        }
        
        return $articles;
    }
    
    // 获取用户的阅读历史总数
    public function getReadHistoryCount($userId) {
        $sql = "SELECT COUNT(*) as count FROM {$this->prefix}read_history r
                LEFT JOIN {$this->prefix}article a ON r.article_id = a.id
                WHERE r.user_id = ? AND r.is_deleted = 0 AND a.status = 1 AND a.is_deleted = 0";
        $result = $this->db->query($sql, [$userId]);
        $row = $result->fetch();
        return $row['count'];
    }
}
?>