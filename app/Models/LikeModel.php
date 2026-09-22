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


class LikeModel {
    private $db;
    private $prefix;
    
    public function __construct() {
        $config = Config::get('database');
        $this->db = Database::getInstance();
        $this->prefix = $config['prefix'];
    }
    
    // 添加点赞
    public function addLike($userId, $articleId) {
        if (!$this->checkLike($userId, $articleId)) {
            $sql = "INSERT INTO `{$this->prefix}like` (user_id, article_id, created_at) VALUES (?, ?, ?)";
            return $this->db->query($sql, [$userId, $articleId, time()]);
        }
        return true; // 已经点赞过，返回成功
    }
    
    // 取消点赞
    public function removeLike($userId, $articleId) {
        $sql = "DELETE FROM `{$this->prefix}like` WHERE user_id = ? AND article_id = ?";
        return $this->db->query($sql, [$userId, $articleId]);
    }
    
    // 检查用户是否已点赞文章
    public function checkLike($userId, $articleId) {
        $sql = "SELECT COUNT(*) as count FROM `{$this->prefix}like` WHERE user_id = ? AND article_id = ?";
        $result = $this->db->query($sql, [$userId, $articleId]);
        $row = $result->fetch();
        return $row['count'] > 0;
    }
    
    // 获取文章的点赞数量
    public function getLikeCount($articleId) {
        $sql = "SELECT COUNT(*) as count FROM `{$this->prefix}like` WHERE article_id = ?";
        $result = $this->db->query($sql, [$articleId]);
        $row = $result->fetch();
        return $row['count'];
    }
    
    // 获取用户的点赞列表
    public function getUserLikes($userId, $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;

        $sql = "SELECT
                    l.created_at as liked_at_original,
                    a.id as id,
                    a.id as article_id,
                    a.title,
                    a.description,
                    a.content,
                    a.cover_image,
                    a.view_count,
                    a.like_count,
                    a.comment_count,
                    a.created_at as article_created_at,
                    a.category_id,
                    c.name as category_name,
                    u.nickname as author
                FROM
                    `{$this->prefix}like` l
                LEFT JOIN
                    {$this->prefix}article a ON l.article_id = a.id
                LEFT JOIN
                    {$this->prefix}category c ON a.category_id = c.id
                LEFT JOIN
                    {$this->prefix}user u ON a.user_id = u.id
                WHERE
                    l.user_id = ? AND a.status = 1 AND a.is_deleted = 0
                ORDER BY
                    l.created_at DESC
                LIMIT ? OFFSET ?";

        $result = $this->db->query($sql, [$userId, $limit, $offset]);
        $likes = $result->fetchAll();

        // 转换时间戳为可读格式
        foreach ($likes as &$like) {
            $like['created_at'] = date('Y-m-d H:i:s', $like['article_created_at']);
            $like['liked_at'] = date('Y-m-d H:i:s', $like['liked_at_original']);
            // 添加 excerpt 字段（从 description 复制，兼容用户主页模板）
            $like['excerpt'] = $like['description'] ?? '';
            // 添加 created_at_formatted 字段（兼容用户主页模板的时间显示）
            $like['created_at_formatted'] = $like['created_at'];
            // 从 content 中提取第一张图片作为 first_image（作为 cover_image 的后备）
            if (empty($like['cover_image']) && !empty($like['content'])) {
                if (preg_match('/<img[^>]+src\s*=\s*["\']([^"\']+)["\']/i', $like['content'], $imgMatches)) {
                    $like['first_image'] = trim($imgMatches[1]);
                } else {
                    $like['first_image'] = '';
                }
            } else {
                $like['first_image'] = '';
            }
            // 确保数值字段为整型，以便模板过滤器正确工作
            $like['view_count'] = (int)($like['view_count'] ?? 0);
            $like['like_count'] = (int)($like['like_count'] ?? 0);
            $like['comment_count'] = (int)($like['comment_count'] ?? 0);
        }

        return $likes;
    }
    
    // 获取用户点赞总数
    public function getUserLikesCount($userId) {
        $sql = "SELECT COUNT(*) as count FROM `{$this->prefix}like` l
                LEFT JOIN {$this->prefix}article a ON l.article_id = a.id
                WHERE l.user_id = ? AND a.status = 1 AND a.is_deleted = 0";
        $result = $this->db->query($sql, [$userId]);
        $row = $result->fetch();
        return $row['count'];
    }
    
    // 获取用户点赞的评论列表
    public function getUserCommentLikes($userId, $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT 
                    cl.*, 
                    c.id as comment_id, 
                    c.content, 
                    c.created_at as comment_created_at,
                    a.id as article_id,
                    a.title as article_title,
                    u.nickname as author
                FROM 
                    `{$this->prefix}comment_like` cl
                LEFT JOIN 
                    {$this->prefix}comments c ON cl.comment_id = c.id
                LEFT JOIN 
                    {$this->prefix}article a ON c.article_id = a.id
                LEFT JOIN 
                    {$this->prefix}user u ON c.user_id = u.id
                WHERE 
                    cl.user_id = ? AND c.status = 1 AND a.status = 1 AND a.is_deleted = 0
                ORDER BY 
                    cl.created_at DESC
                LIMIT ? OFFSET ?";
        
        $result = $this->db->query($sql, [$userId, $limit, $offset]);
        $likes = $result->fetchAll();
        
        // 转换时间戳为可读格式
        foreach ($likes as &$like) {
            $like['comment_created_at'] = date('Y-m-d H:i:s', $like['comment_created_at']);
            $like['created_at'] = date('Y-m-d H:i:s', $like['created_at']);
        }
        
        return $likes;
    }
    
    // 获取用户评论点赞总数
    public function getUserCommentLikesCount($userId) {
        $sql = "SELECT COUNT(*) as count FROM `{$this->prefix}comment_like` cl
                LEFT JOIN {$this->prefix}comments c ON cl.comment_id = c.id
                LEFT JOIN {$this->prefix}article a ON c.article_id = a.id
                WHERE cl.user_id = ? AND c.status = 1 AND a.status = 1 AND a.is_deleted = 0";
        $result = $this->db->query($sql, [$userId]);
        $row = $result->fetch();
        return $row['count'];
    }
}