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


class FavoriteModel {
    private $db;
    private $prefix;
    
    public function __construct() {
        $config = Config::get('database');
        $this->db = Database::getInstance();
        $this->prefix = $config['prefix'];
    }
    
    // 添加收藏
    public function addFavorite($userId, $articleId) {
        if (!$this->checkFavorite($userId, $articleId)) {
            $sql = "INSERT INTO {$this->prefix}favorite (user_id, article_id, created_at) VALUES (?, ?, ?)";
            return $this->db->query($sql, [$userId, $articleId, time()]);
        }
        return true; // 已经收藏过，返回成功
    }
    
    // 取消收藏
    public function removeFavorite($userId, $articleId) {
        $sql = "DELETE FROM {$this->prefix}favorite WHERE user_id = ? AND article_id = ?";
        return $this->db->query($sql, [$userId, $articleId]);
    }
    
    // 检查用户是否已收藏文章
    public function checkFavorite($userId, $articleId) {
        $sql = "SELECT COUNT(*) as count FROM {$this->prefix}favorite WHERE user_id = ? AND article_id = ?";
        $result = $this->db->query($sql, [$userId, $articleId]);
        $row = $result->fetch();
        return $row['count'] > 0;
    }
    
    // 获取文章的收藏数量
    public function getFavoriteCount($articleId) {
        $sql = "SELECT COUNT(*) as count FROM {$this->prefix}favorite WHERE article_id = ?";
        $result = $this->db->query($sql, [$articleId]);
        $row = $result->fetch();
        return $row['count'];
    }
    
    // 获取用户的收藏列表
    public function getUserFavorites($userId, $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;

        $sql = "SELECT
                    f.created_at as favorited_at_original,
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
                    {$this->prefix}favorite f
                LEFT JOIN
                    {$this->prefix}article a ON f.article_id = a.id
                LEFT JOIN
                    {$this->prefix}category c ON a.category_id = c.id
                LEFT JOIN
                    {$this->prefix}user u ON a.user_id = u.id
                WHERE
                    f.user_id = ? AND a.status = 1 AND a.is_deleted = 0
                ORDER BY
                    f.created_at DESC
                LIMIT ? OFFSET ?";

        $result = $this->db->query($sql, [$userId, $limit, $offset]);
        $favorites = $result->fetchAll();

        // 转换时间戳为可读格式，并确保数值字段为整型
        foreach ($favorites as &$fav) {
            $fav['created_at'] = date('Y-m-d H:i:s', $fav['article_created_at']);
            $fav['favorited_at'] = date('Y-m-d H:i:s', $fav['favorited_at_original']);
            // 补充 excerpt 字段（从 description 复制，兼容用户主页模板 {field name='excerpt'}）
            $fav['excerpt'] = $fav['description'] ?? '';
            // 补充 created_at_formatted 字段（兼容用户主页模板 {field name="created_at_formatted"}）
            $fav['created_at_formatted'] = $fav['created_at'];
            // 从 content 中提取第一张图片作为 first_image（作为 cover_image 的后备）
            if (empty($fav['cover_image']) && !empty($fav['content'])) {
                if (preg_match('/<img[^>]+src\s*=\s*["\']([^"\']+)["\']/i', $fav['content'], $imgMatches)) {
                    $fav['first_image'] = trim($imgMatches[1]);
                } else {
                    $fav['first_image'] = '';
                }
            } else {
                $fav['first_image'] = '';
            }
            // 确保数值字段为整型
            $fav['view_count'] = (int)($fav['view_count'] ?? 0);
            $fav['like_count'] = (int)($fav['like_count'] ?? 0);
            $fav['comment_count'] = (int)($fav['comment_count'] ?? 0);
        }

        return $favorites;
    }
    
    // 获取用户收藏总数
    public function getUserFavoritesCount($userId) {
        $sql = "SELECT COUNT(*) as count FROM {$this->prefix}favorite f
                LEFT JOIN {$this->prefix}article a ON f.article_id = a.id
                WHERE f.user_id = ? AND a.status = 1 AND a.is_deleted = 0";
        $result = $this->db->query($sql, [$userId]);
        $row = $result->fetch();
        return $row['count'];
    }
}
