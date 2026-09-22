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


class ArticleModel {
    private $db;
    private $prefix;
    
    public function __construct() {
        $config = Config::get('database');
        $this->db = Database::getInstance();
        $this->prefix = $config['prefix'];
    }
    
    // 获取所有文章（带分页和筛选）
    public function getArticles($page = 1, $limit = 10, $includeDrafts = false, $filters = [], $filter = 'latest', $sort = '', $order = 'desc') {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT 
                    a.*, 
                    c.name as category_name,
                    c.slug as category_slug,
                    u.nickname as author,
                    u.username as username,
                    COUNT(DISTINCT l.user_id, l.article_id) as like_count,
                    COUNT(DISTINCT f.user_id, f.article_id) as favorite_count,
                    COUNT(DISTINCT com.id) as comment_count
                FROM 
                    {$this->prefix}article a
                LEFT JOIN 
                    {$this->prefix}category c ON a.category_id = c.id
                LEFT JOIN 
                    {$this->prefix}user u ON a.user_id = u.id
                LEFT JOIN 
                    {$this->prefix}like l ON a.id = l.article_id
                LEFT JOIN 
                    {$this->prefix}favorite f ON a.id = f.article_id
                LEFT JOIN 
                    {$this->prefix}comments com ON a.id = com.article_id AND com.status = 1
                WHERE 
                    1 = 1 AND a.is_deleted = 0";
        
        // 初始化查询参数
        $params = [];
        
        // 如果不包含草稿，只获取已发布的文章
        if (!$includeDrafts) {
            $sql .= " AND a.status = 1";
        }
        
        // 分类筛选
        if (!empty($filters['category'])) {
            $sql .= " AND a.category_id = ?";
            $params[] = $filters['category'];
        }
        
        // 状态筛选
        if (isset($filters['status'])) {
            $sql .= " AND a.status = ?";
            $params[] = $filters['status'];
        }
        
        // 用户筛选
        if (!empty($filters['user_id'])) {
            $sql .= " AND a.user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        // 搜索筛选
        if (!empty($filters['search'])) {
            $sql .= " AND (a.title LIKE ? OR a.content LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        // 置顶类型筛选
        if (isset($filters['top_type'])) {
            $sql .= " AND a.top_type = ?";
            $params[] = $filters['top_type'];
        }
        
        // 时间范围筛选
        if (!empty($filters['date_from'])) {
            $sql .= " AND a.created_at >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND a.created_at <= ?";
            $params[] = $filters['date_to'];
        }
        
        // 分组
        $sql .= " GROUP BY a.id";
        
        // 排序
        switch ($filter) {
            case 'most-viewed':
                // 按阅读数倒序
                $sql .= " ORDER BY 
                            CASE 
                                WHEN a.top_type = 1 THEN 3  -- 全局置顶优先级最高
                                WHEN a.top_type = 2 THEN 2  -- 首页置顶次之
                                WHEN a.top_type = 3 THEN 1  -- 分类置顶再次之
                                ELSE 0  -- 不置顶
                            END DESC, 
                            a.view_count DESC, 
                            a.created_at DESC 
                        ";
                break;
            case 'most-liked':
                // 按点赞数倒序
                $sql .= " ORDER BY 
                            CASE 
                                WHEN a.top_type = 1 THEN 3  -- 全局置顶优先级最高
                                WHEN a.top_type = 2 THEN 2  -- 首页置顶次之
                                WHEN a.top_type = 3 THEN 1  -- 分类置顶再次之
                                ELSE 0  -- 不置顶
                            END DESC, 
                            like_count DESC, 
                            a.created_at DESC 
                        ";
                break;
            case 'most-favorited':
                // 按收藏数倒序
                $sql .= " ORDER BY 
                            CASE 
                                WHEN a.top_type = 1 THEN 3  -- 全局置顶优先级最高
                                WHEN a.top_type = 2 THEN 2  -- 首页置顶次之
                                WHEN a.top_type = 3 THEN 1  -- 分类置顶再次之
                                ELSE 0  -- 不置顶
                            END DESC, 
                            favorite_count DESC, 
                            a.created_at DESC 
                        ";
                break;
            case 'most-commented':
                // 按评论数倒序
                $sql .= " ORDER BY 
                            CASE 
                                WHEN a.top_type = 1 THEN 3  -- 全局置顶优先级最高
                                WHEN a.top_type = 2 THEN 2  -- 首页置顶次之
                                WHEN a.top_type = 3 THEN 1  -- 分类置顶再次之
                                ELSE 0  -- 不置顶
                            END DESC, 
                            comment_count DESC, 
                            a.created_at DESC 
                        ";
                break;
            case 'latest':
            default:
                // 按创建时间倒序（默认）
                $sql .= " ORDER BY
                            CASE
                                WHEN a.top_type = 1 THEN 3  -- 全局置顶优先级最高
                                WHEN a.top_type = 2 THEN 2  -- 首页置顶次之
                                WHEN a.top_type = 3 THEN 1  -- 分类置顶再次之
                                ELSE 0  -- 不置顶
                            END DESC,
                            a.created_at DESC
                        ";
                break;
        }

        // 后台表头排序：使用白名单防止 SQL 注入，仅允许指定的列名（标题/置顶状态不支持排序）
        $sortWhiteList = ['id', 'created_at', 'updated_at', 'view_count'];
        if ($sort !== '' && in_array($sort, $sortWhiteList)) {
            // 升降序同样使用白名单，非法值一律回退为 desc
            $orderDir = (strtolower($order) === 'asc') ? 'ASC' : 'DESC';
            // 置顶文章仍保持优先展示，其次按指定字段排序，最后按时间倒序兜底
            $sql = preg_replace('/ORDER BY.*$/s', '', $sql); // 移除默认排序，改用指定排序
            $sql .= " ORDER BY
                            CASE
                                WHEN a.top_type = 1 THEN 3  -- 全局置顶优先级最高
                                WHEN a.top_type = 2 THEN 2  -- 首页置顶次之
                                WHEN a.top_type = 3 THEN 1  -- 分类置顶再次之
                                ELSE 0  -- 不置顶
                            END DESC,
                            a.{$sort} {$orderDir},
                            a.created_at DESC
                        ";
        }
        
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $articles = $this->db->fetchAll($sql, $params);
        
        // 为每篇文章生成摘要、提取首图、转换时间戳
        foreach ($articles as $index => $article) {
            $articles[$index]['excerpt'] = $this->generateExcerpt($article, 150);
            // 从文章正文提取第一张图片URL（当未设置封面图时使用）
            $articles[$index]['first_image'] = $this->extractFirstImage($article['content']);
            // 保存原始时间戳用于排序，同时提供格式化的时间显示
            $articles[$index]['created_at_formatted'] = date('Y-m-d H:i:s', $article['created_at']);
            $articles[$index]['updated_at_formatted'] = date('Y-m-d H:i:s', $article['updated_at']);
            // 确保view_count始终是整数
            $articles[$index]['view_count'] = (int)$article['view_count'];
            // 确保统计数据始终是整数
            $articles[$index]['like_count'] = (int)$article['like_count'];
            $articles[$index]['favorite_count'] = (int)$article['favorite_count'];
            $articles[$index]['comment_count'] = (int)$article['comment_count'];
        }
        
        return $articles;
    }
    
    // 获取回收站中的文章（带分页和筛选）
    public function getRecycledArticles($page = 1, $limit = 10, $filters = []) {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT 
                    a.*, 
                    c.name as category_name,
                    c.slug as category_slug,
                    u.nickname as author,
                    u.username as username
                FROM 
                    {$this->prefix}article a
                LEFT JOIN 
                    {$this->prefix}category c ON a.category_id = c.id
                LEFT JOIN 
                    {$this->prefix}user u ON a.user_id = u.id
                WHERE 
                    1 = 1 AND a.is_deleted = 1";
        
        // 初始化查询参数
        $params = [];
        
        // 分类筛选
        if (!empty($filters['category'])) {
            $sql .= " AND a.category_id = ?";
            $params[] = $filters['category'];
        }
        
        // 搜索筛选
        if (!empty($filters['search'])) {
            $sql .= " AND (a.title LIKE ? OR a.content LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        // 排序
        $sql .= " ORDER BY a.updated_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $articles = $this->db->fetchAll($sql, $params);
        
        // 为每篇文章生成摘要
        foreach ($articles as $index => $article) {
            $articles[$index]['excerpt'] = $this->generateExcerpt($article, 150);
            // 保存原始时间戳用于排序，同时提供格式化的时间显示
            $articles[$index]['created_at_formatted'] = date('Y-m-d H:i:s', $article['created_at']);
            $articles[$index]['deleted_at_formatted'] = date('Y-m-d H:i:s', $article['updated_at']);
            // 确保view_count始终是整数
            $articles[$index]['view_count'] = (int)$article['view_count'];
        }
        
        return $articles;
    }
    
    // 获取最新文章
    public function getLatestArticles($limit = 5) {
        $sql = "SELECT 
                    a.*, 
                    c.name as category_name,
                    c.slug as category_slug,
                    u.nickname as author,
                    u.username as username
                FROM 
                    {$this->prefix}article a
                LEFT JOIN 
                    {$this->prefix}category c ON a.category_id = c.id
                LEFT JOIN 
                    {$this->prefix}user u ON a.user_id = u.id
                WHERE 
                    a.status = 1
                ORDER BY 
                    a.created_at DESC
                LIMIT ?";
        
        $articles = $this->db->fetchAll($sql, [$limit]);
        
        // 为每篇文章生成摘要、提取首图，保留时间戳用于排序，同时提供格式化的时间显示
        foreach ($articles as $index => $article) {
            $articles[$index]['excerpt'] = $this->generateExcerpt($article, 150);
            // 从文章正文提取第一张图片URL（当未设置封面图时使用）
            $articles[$index]['first_image'] = $this->extractFirstImage($article['content']);
            $articles[$index]['created_at_formatted'] = date('Y-m-d H:i:s', $article['created_at']);
            // 确保view_count始终是整数
            $articles[$index]['view_count'] = (int)$article['view_count'];
        }
        
        return $articles;
    }
    
    // 根据ID获取文章
    public function getArticleById($id) {
        $sql = "SELECT 
                    a.*, 
                    c.name as category_name,
                    c.slug as category_slug,
                    c.id as category_id_field,
                    u.nickname as author,
                    u.username as username,
                    u.id as user_id
                FROM 
                    {$this->prefix}article a
                LEFT JOIN 
                    {$this->prefix}category c ON a.category_id = c.id
                LEFT JOIN 
                    {$this->prefix}user u ON a.user_id = u.id
                WHERE 
                    a.id = ? AND a.is_deleted = 0";
        
        $article = $this->db->fetch($sql, [$id]);
        
        if ($article) {
            // 增加阅读量
            $this->db->update('article', 
                            ['view_count' => 'SQL:view_count + 1'], 
                            ['id' => $id]);
            
            // 更新当前返回的文章数据中的阅读量
            $article['view_count'] = (int)$article['view_count'] + 1;
            
            // 保留时间戳用于过滤器使用，同时提供格式化的时间显示
            $article['created_at_formatted'] = date('Y-m-d H:i:s', $article['created_at']);
            // 为模板提供正确的字段名
            $article['views'] = $article['view_count'];
            
            // 生成文章摘要
            $article['excerpt'] = $this->generateExcerpt($article, 200);
        }
        
        return $article;
    }
    
    // 根据分类ID获取文章列表
    public function getArticlesByCategoryId($categoryId, $page = 1, $limit = 10, $filter = 'latest', $excludeTop = true) {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT 
                    a.*, 
                    c.name as category_name,
                    c.slug as category_slug,
                    u.nickname as author,
                    u.username as username,
                    COUNT(DISTINCT l.user_id, l.article_id) as like_count,
                    COUNT(DISTINCT f.user_id, f.article_id) as favorite_count,
                    COUNT(DISTINCT com.id) as comment_count
                FROM 
                    {$this->prefix}article a
                LEFT JOIN 
                    {$this->prefix}category c ON a.category_id = c.id
                LEFT JOIN 
                    {$this->prefix}user u ON a.user_id = u.id
                LEFT JOIN 
                    {$this->prefix}like l ON a.id = l.article_id
                LEFT JOIN 
                    {$this->prefix}favorite f ON a.id = f.article_id
                LEFT JOIN 
                    {$this->prefix}comments com ON a.id = com.article_id AND com.status = 1
                WHERE 
                    a.category_id = ? AND a.status = 1 AND a.is_deleted = 0";
        
        // 如果需要排除置顶文章
        if ($excludeTop) {
            $sql .= " AND a.top_type = 0";
        }
        
        // 分组
        $sql .= " GROUP BY a.id";
        
        // 排序
        switch ($filter) {
            case 'most-viewed':
                // 按阅读数倒序
                $sql .= " ORDER BY a.view_count DESC, a.created_at DESC";
                break;
            case 'most-liked':
                // 按点赞数倒序
                $sql .= " ORDER BY like_count DESC, a.created_at DESC";
                break;
            case 'most-favorited':
                // 按收藏数倒序
                $sql .= " ORDER BY favorite_count DESC, a.created_at DESC";
                break;
            case 'most-commented':
                // 按评论数倒序
                $sql .= " ORDER BY comment_count DESC, a.created_at DESC";
                break;
            case 'latest':
            default:
                // 按创建时间倒序（默认）
                $sql .= " ORDER BY a.created_at DESC";
                break;
        }
        
        $sql .= " LIMIT ? OFFSET ?";
        
        $articles = $this->db->fetchAll($sql, [$categoryId, $limit, $offset]);
        
        // 为每篇文章生成摘要、提取首图，保留时间戳用于排序，同时提供格式化的时间显示
        foreach ($articles as $index => $article) {
            $articles[$index]['excerpt'] = $this->generateExcerpt($article, 150);
            // 从文章正文提取第一张图片URL（当未设置封面图时使用）
            $articles[$index]['first_image'] = $this->extractFirstImage($article['content']);
            $articles[$index]['created_at_formatted'] = date('Y-m-d H:i:s', $article['created_at']);
            // 确保view_count始终是整数
            $articles[$index]['view_count'] = (int)$article['view_count'];
            // 确保统计数据始终是整数
            $articles[$index]['like_count'] = (int)$article['like_count'];
            $articles[$index]['favorite_count'] = (int)$article['favorite_count'];
            $articles[$index]['comment_count'] = (int)$article['comment_count'];
        }
        
        return $articles;
    }
    
    // 获取文章总数（带筛选）
    public function getArticleCount($includeDrafts = false, $filters = []) {
        $sql = "SELECT COUNT(*) as count FROM {$this->prefix}article a WHERE 1 = 1 AND a.is_deleted = 0";
        
        // 初始化查询参数
        $params = [];
        
        // 如果不包含草稿，只统计已发布的文章
        if (!$includeDrafts) {
            $sql .= " AND a.status = 1";
        }
        
        // 分类筛选
        if (!empty($filters['category'])) {
            $sql .= " AND a.category_id = ?";
            $params[] = $filters['category'];
        }
        
        // 状态筛选
        if (isset($filters['status'])) {
            $sql .= " AND a.status = ?";
            $params[] = $filters['status'];
        }
        
        // 搜索筛选
        if (!empty($filters['search'])) {
            $sql .= " AND (a.title LIKE ? OR a.content LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        // 置顶类型筛选
        if (isset($filters['top_type'])) {
            $sql .= " AND a.top_type = ?";
            $params[] = $filters['top_type'];
        }
        
        // 时间范围筛选
        if (!empty($filters['date_from'])) {
            $sql .= " AND a.created_at >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND a.created_at <= ?";
            $params[] = $filters['date_to'];
        }
        
        return $this->db->fetch($sql, $params)['count'];
    }
    
    // 获取回收站中的文章总数（带筛选）
    public function getRecycledArticleCount($filters = []) {
        $sql = "SELECT COUNT(*) as count FROM {$this->prefix}article a WHERE 1 = 1 AND a.is_deleted = 1";
        
        // 初始化查询参数
        $params = [];
        
        // 分类筛选
        if (!empty($filters['category'])) {
            $sql .= " AND a.category_id = ?";
            $params[] = $filters['category'];
        }
        
        // 搜索筛选
        if (!empty($filters['search'])) {
            $sql .= " AND (a.title LIKE ? OR a.content LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        return $this->db->fetch($sql, $params)['count'];
    }
    
    // 根据分类ID获取文章总数
    public function getArticleCountByCategoryId($categoryId) {
        $sql = "SELECT COUNT(*) as count FROM {$this->prefix}article WHERE category_id = ? AND status = 1";
        return $this->db->fetch($sql, [$categoryId])['count'];
    }
    
    // 根据标签ID获取文章列表
    public function getArticlesByTagId($tagId, $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT 
                    a.*, 
                    c.name as category_name,
                    c.slug as category_slug,
                    u.nickname as author,
                    u.username as username
                FROM 
                    {$this->prefix}article a
                LEFT JOIN 
                    {$this->prefix}category c ON a.category_id = c.id
                LEFT JOIN 
                    {$this->prefix}user u ON a.user_id = u.id
                INNER JOIN 
                    {$this->prefix}article_tag at ON a.id = at.article_id
                WHERE 
                    at.tag_id = ? AND a.status = 1
                ORDER BY 
                    a.created_at DESC
                LIMIT ? OFFSET ?";
        
        $articles = $this->db->fetchAll($sql, [$tagId, $limit, $offset]);
        
        // 为每篇文章生成摘要、提取首图，保留时间戳用于排序，同时提供格式化的时间显示
        foreach ($articles as $index => $article) {
            $articles[$index]['excerpt'] = $this->generateExcerpt($article, 150);
            // 从文章正文提取第一张图片URL（当未设置封面图时使用）
            $articles[$index]['first_image'] = $this->extractFirstImage($article['content']);
            $articles[$index]['created_at_formatted'] = date('Y-m-d H:i:s', $article['created_at']);
            // 确保view_count始终是整数
            $articles[$index]['view_count'] = (int)$article['view_count'];
        }
        
        return $articles;
    }
    
    // 根据标签ID获取文章总数
    public function getArticleCountByTagId($tagId) {
        $sql = "SELECT COUNT(*) as count FROM {$this->prefix}article a
                INNER JOIN {$this->prefix}article_tag at ON a.id = at.article_id
                WHERE at.tag_id = ? AND a.status = 1";
        return $this->db->fetch($sql, [$tagId])['count'];
    }
    
    // 根据关键词搜索文章（FULLTEXT + LIKE 回退）
    public function searchArticles($keyword, $page = 1, $limit = 10, $sort = 'relevance', $useFulltext = null) {
        $offset = ($page - 1) * $limit;
        
        // 判断是否使用FULLTEXT（短关键词或纯数字用LIKE更好）
        if ($useFulltext === null) {
            $useFulltext = mb_strlen($keyword) >= 2 && !is_numeric($keyword);
        }
        
        // 根据排序方式构建SQL
        $orderBy = '';
        $relevanceExpr = '';
        switch ($sort) {
            case 'newest':
                $orderBy = 'a.created_at DESC';
                break;
            case 'oldest':
                $orderBy = 'a.created_at ASC';
                break;
            case 'most-viewed':
                $orderBy = 'a.view_count DESC, a.created_at DESC';
                break;
            case 'most-liked':
                $orderBy = 'like_count DESC, a.created_at DESC';
                break;
            case 'most-favorited':
                $orderBy = 'like_count DESC, a.created_at DESC'; // 回退到 like_count，因为没有 favorite_count 字段在文章表
                break;
            case 'most-commented':
                $orderBy = 'comment_count DESC, a.created_at DESC';
                break;
            case 'relevance':
            default:
                if ($useFulltext) {
                    // FULLTEXT 相关性排序
                    $relevanceExpr = "MATCH(a.title, a.content) AGAINST(? IN BOOLEAN MODE) as relevance_score";
                    $orderBy = "relevance_score DESC, a.created_at DESC";
                } else {
                    // LIKE 相关性排序：标题匹配 > 内容匹配
                    $relevanceExpr = "
                        CASE 
                            WHEN a.title LIKE ? THEN 3 
                            WHEN a.content LIKE ? THEN 2
                            ELSE 1 
                        END as relevance_score";
                    $orderBy = "relevance_score DESC, a.created_at DESC";
                }
                break;
        }
        
        // 构建搜索条件
        if ($useFulltext) {
            $searchCondition = "MATCH(a.title, a.content) AGAINST(? IN BOOLEAN MODE)";
            $searchParam = $keyword;
        } else {
            $searchCondition = "(a.title LIKE ? OR a.content LIKE ?)";
            $searchParam = '%' . $keyword . '%';
        }
        
        $sql = "SELECT 
                    a.*, 
                    c.name as category_name,
                    c.slug as category_slug,
                    u.nickname as author,
                    u.username as username,
                    COUNT(DISTINCT l.user_id, l.article_id) as like_count,
                    COUNT(DISTINCT com.id) as comment_count";
        
        if (!empty($relevanceExpr)) {
            $sql .= ", $relevanceExpr";
        }
        
        $sql .= " FROM 
                    {$this->prefix}article a
                LEFT JOIN 
                    {$this->prefix}category c ON a.category_id = c.id
                LEFT JOIN 
                    {$this->prefix}user u ON a.user_id = u.id
                LEFT JOIN 
                    {$this->prefix}like l ON a.id = l.article_id
                LEFT JOIN 
                    {$this->prefix}favorite f ON a.id = f.article_id
                LEFT JOIN 
                    {$this->prefix}comments com ON a.id = com.article_id AND com.status = 1
                WHERE 
                    a.status = 1 AND a.is_deleted = 0
                    AND $searchCondition
                GROUP BY 
                    a.id
                ORDER BY 
                    $orderBy
                LIMIT ? OFFSET ?";
        
        // 构建参数数组
        $params = [];
        
        if ($sort == 'relevance' && !$useFulltext) {
            // LIKE 模式需要额外的相关性参数
            $params[] = '%' . $keyword . '%';
            $params[] = '%' . $keyword . '%';
        }
        
        if ($useFulltext) {
            $params[] = $keyword;
        } else {
            $params[] = '%' . $keyword . '%';
            $params[] = '%' . $keyword . '%';
        }
        
        // 添加分页参数
        $params[] = $limit;
        $params[] = $offset;
        
        try {
            $articles = $this->db->fetchAll($sql, $params);
        } catch (Exception $e) {
            // FULLTEXT 失败时回退到 LIKE
            if ($useFulltext) {
                $this->logSearchFallback($keyword, $e->getMessage());
                return $this->searchArticles($keyword, $page, $limit, $sort, false);
            }
            // 已经是 LIKE 模式但仍然失败，返回空数组
            return [];
        }
        
        // 确保 $articles 是数组
        if (!is_array($articles)) {
            $articles = [];
        }
        
        // 为每篇文章生成摘要、提取首图、高亮关键词，保留时间戳用于排序，同时提供格式化的时间显示
        foreach ($articles as $index => $article) {
            $articles[$index]['title'] = $article['title'];
            $articles[$index]['highlighted_title'] = $this->highlightKeyword($article['title'], $keyword);
            $excerpt = $this->generateExcerpt($article, 150);
            $articles[$index]['excerpt'] = $excerpt;
            $articles[$index]['highlighted_excerpt'] = $this->highlightKeyword($excerpt, $keyword);
            // 从文章正文提取第一张图片URL（当未设置封面图时使用）
            $articles[$index]['first_image'] = $this->extractFirstImage($article['content']);
            $articles[$index]['created_at_formatted'] = date('Y-m-d H:i:s', $article['created_at']);
            $articles[$index]['view_count'] = (int)$article['view_count'];
            $articles[$index]['like_count'] = (int)$article['like_count'];
            $articles[$index]['comment_count'] = (int)$article['comment_count'];
        }
        
        return $articles;
    }
    
    // 记录搜索回退日志
    private function logSearchFallback($keyword, $error) {
        if (class_exists('Log')) {
            Log::warning("搜索回退到LIKE模式: keyword={$keyword}, error={$error}", 'search');
        }
    }
    
    // 获取搜索结果总数（使用FULLTEXT）
    public function getSearchArticleCount($keyword) {
        $useFulltext = mb_strlen($keyword) >= 2 && !is_numeric($keyword);
        
        if ($useFulltext) {
            $sql = "SELECT COUNT(*) as count FROM {$this->prefix}article a
                    WHERE a.status = 1 AND a.is_deleted = 0
                    AND MATCH(a.title, a.content) AGAINST(? IN BOOLEAN MODE)";
            try {
                $result = $this->db->fetch($sql, [$keyword]);
                return $result['count'];
            } catch (Exception $e) {
                // 回退到 LIKE
            }
        }
        
        $searchTerm = '%' . $keyword . '%';
        $sql = "SELECT COUNT(*) as count FROM {$this->prefix}article a
                WHERE a.status = 1 AND a.is_deleted = 0
                AND (a.title LIKE ? OR a.content LIKE ?)";
        $result = $this->db->fetch($sql, [$searchTerm, $searchTerm]);
        return $result['count'];
    }
    
    // 高亮关键词
    private function highlightKeyword($text, $keyword) {
        if (empty($keyword)) {
            return $text;
        }
        
        // 使用正则表达式进行不区分大小写的替换
        $escapedKeyword = preg_quote($keyword, '/');
        $text = preg_replace('/(' . $escapedKeyword . ')/i', '<span class="search-highlight">$1</span>', $text);
        
        return $text;
    }
    
    /**
     * 从文章正文中提取第一张图片的URL
     * 当文章没有设置封面图时，可以使用此URL作为替代
     *
     * @param string $content 文章正文HTML
     * @return string|null 第一张图片的URL，或null如果没有图片
     */
    private function extractFirstImage($content) {
        if (empty($content)) {
            return null;
        }
        
        // 使用正则表达式匹配HTML中的第一个 <img> 标签
        // 同时支持单引号和双引号包裹的 src 属性
        if (preg_match('/<img[^>]+src\s*=\s*["\']([^"\']+)["\']/i', $content, $matches)) {
            $imageUrl = trim($matches[1]);
            // 确保不为空
            if (!empty($imageUrl)) {
                return $imageUrl;
            }
        }
        
        return null;
    }
    
    // 生成文章摘要
    private function generateExcerpt($article, $length = 150) {
        // 优先使用存储的description字段
        if (!empty($article['description'])) {
            $content = $article['description'];
        } else {
            // 否则从内容中生成
            $content = $article['content'];
            // 移除HTML标签
            $content = strip_tags($content);
            // 移除多余空格
            $content = preg_replace('/\s+/', ' ', $content);
        }
        // 截取指定长度
        if (strlen($content) > $length) {
            $content = substr($content, 0, $length) . '...';
        }
        return $content;
    }
    
    // 根据ID获取文章详情（不限制状态，用于后台编辑）
    public function getArticleDetailById($id) {
        $sql = "SELECT 
                    a.*, 
                    c.name as category_name,
                    c.slug as category_slug,
                    u.nickname as author,
                    u.username as username
                FROM 
                    {$this->prefix}article a
                LEFT JOIN 
                    {$this->prefix}category c ON a.category_id = c.id
                LEFT JOIN 
                    {$this->prefix}user u ON a.user_id = u.id
                WHERE 
                    a.id = ?";
        
        return $this->db->fetch($sql, [$id]);
    }
    
    // 创建新文章
    public function createArticle($data) {
        // 验证必要字段
        if (empty($data['title']) || empty($data['content']) || empty($data['category_id'])) {
            return false;
        }
        
        // 准备数据
        $timestamp = time();
        $articleData = [
            'title' => $data['title'],
            'content' => $data['content'],
            'description' => $data['description'] ?? '',
            'cover_image' => $data['cover_image'] ?? '',
            'category_id' => $data['category_id'],
            'user_id' => $data['user_id'] ?? 1, // 默认用户ID为1
            'status' => $data['status'] ?? 1,
            'top_type' => $data['top_type'] ?? 0,
            'slug' => $this->generateSlug($data),
            'created_at' => $timestamp,
            'updated_at' => $timestamp
        ];
        
        // 触发文章创建前钩子
        Hook::trigger(Hook::ARTICLE_CREATE_BEFORE, $articleData);
        
        // 插入文章
        $articleId = $this->db->insert('article', $articleData);
        
        if ($articleId) {
            // 如果有标签，插入标签关联
            if (!empty($data['tags'])) {
                $this->insertArticleTags($articleId, $data['tags']);
            }
            
            // 获取完整的文章数据
            $article = $this->getArticleDetailById($articleId);
            
            // 触发文章创建后钩子
            Hook::trigger(Hook::ARTICLE_CREATE_AFTER, $article);
            
            // 直接发送通知，不通过插件
            require_once APP_PATH . '/Models/NotificationModel.php';
            $notificationModel = new NotificationModel();
            
            // 获取关注该作者的用户列表
            $followers = $this->db->fetchAll(
                "SELECT follower_id FROM {$this->db->table('user_follow')} WHERE following_id = ?", 
                [$article['user_id']]
            );
            
            // 给每个关注者发送通知
            foreach ($followers as $follower) {
                $notificationModel->createNotification([
                    'user_id' => $follower['follower_id'],
                    'type' => 'articles',
                    'title' => '新文章通知',
                    'content' => "您关注的作者发布了新文章《{$article['title']}》",
                    'is_read' => 0,
                    'created_at' => time(),
                    'read_at' => 0
                ]);
            }
        }
        
        return $articleId;
    }
    
    // 更新文章
    public function updateArticle($id, $data) {
        // 验证必要字段
        if (empty($id)) {
            return false;
        }
        
        // 获取当前文章
        $currentArticle = $this->getArticleDetailById($id);
        
        // 准备数据
        $timestamp = time();
        $articleData = [
            'updated_at' => $timestamp
        ];
        
        // 只更新提供的字段
        if (isset($data['title'])) {
            $articleData['title'] = $data['title'];
        }
        if (isset($data['content'])) {
            $articleData['content'] = $data['content'];
        }
        if (isset($data['description'])) {
            $articleData['description'] = $data['description'];
        }
        if (isset($data['cover_image'])) {
            $articleData['cover_image'] = $data['cover_image'];
        }
        if (isset($data['category_id'])) {
            $articleData['category_id'] = $data['category_id'];
        }
        if (isset($data['status'])) {
            $articleData['status'] = $data['status'];
        }
        if (isset($data['top_type'])) {
            $articleData['top_type'] = $data['top_type'];
        }
        if (isset($data['slug'])) {
            // 手动设置 slug：非空则净化，空则设置为 null（使用 ID 访问）
            $articleData['slug'] = !empty($data['slug']) ? $this->sanitizeSlug($data['slug'], $id) : null;
        }
        
        // 发布时间逻辑优化：
        // 1. 如果文章第一次从草稿变为发布，设置发布时间为当前时间
        // 2. 如果文章之前已经发布过，即使之后转为草稿再发布，也保持第一次发布的时间
        if (isset($articleData['status']) && $currentArticle['status'] == 0 && $articleData['status'] == 1) {
            // 检查文章是否曾经发布过
            // 我们可以通过查看文章的创建历史来判断
            // 这里我们假设，如果文章从未发布过，created_at字段的值与updated_at字段的值相同（因为草稿保存时两者都会更新）
            // 而如果文章曾经发布过，created_at字段会被设置为发布时间，与updated_at字段的值不同
            if ($currentArticle['created_at'] == $currentArticle['updated_at']) {
                // 文章从未发布过，第一次从草稿变为发布，更新创建时间为当前时间
                $articleData['created_at'] = $timestamp;
            }
        }
        
        // 更新文章
        $result = $this->db->update('article', $articleData, ['id' => $id]);
        
        // 如果有标签，先删除旧的关联，再插入新的关联
        if ($result && isset($data['tags'])) {
            $this->deleteArticleTags($id);
            if (!empty($data['tags'])) {
                $this->insertArticleTags($id, $data['tags']);
            }
        }
        
        return $result;
    }
    
    // 删除文章（移到回收站）
    public function deleteArticle($id) {
        // 将文章移到回收站
        $result = $this->db->update('article', [
            'is_deleted' => 1,
            'updated_at' => time()
        ], ['id' => $id]);
        
        return $result;
    }
    
    // 恢复文章（从回收站）
    public function restoreArticle($id) {
        // 将文章从回收站恢复
        $result = $this->db->update('article', [
            'is_deleted' => 0,
            'updated_at' => time()
        ], ['id' => $id]);
        
        return $result;
    }
    
    // 永久删除文章
    public function permanentlyDeleteArticle($id) {
        // 永久删除文章
        $result = $this->db->delete('article', ['id' => $id]);
        
        // 删除标签关联
        if ($result) {
            $this->deleteArticleTags($id);
        }
        
        return $result;
    }
    
    // 获取上一篇文章
    public function getPreviousArticle($currentId) {
        $sql = "SELECT 
                    a.id, 
                    a.title,
                    u.nickname as author
                FROM 
                    {$this->prefix}article a
                LEFT JOIN 
                    {$this->prefix}user u ON a.user_id = u.id
                WHERE 
                    a.id < ? AND a.status = 1 AND a.is_deleted = 0
                ORDER BY 
                    a.id DESC
                LIMIT 1";
        
        return $this->db->fetch($sql, [$currentId]);
    }
    
    // 获取下一篇文章
    public function getNextArticle($currentId) {
        $sql = "SELECT 
                    a.id, 
                    a.title,
                    u.nickname as author
                FROM 
                    {$this->prefix}article a
                LEFT JOIN 
                    {$this->prefix}user u ON a.user_id = u.id
                WHERE 
                    a.id > ? AND a.status = 1 AND a.is_deleted = 0
                ORDER BY 
                    a.id ASC
                LIMIT 1";
        
        return $this->db->fetch($sql, [$currentId]);
    }
    
    // 获取相关文章（根据相同标签）
    public function getRelatedArticles($articleId, $limit = 5) {
        $sql = "SELECT 
                    a.*, 
                    c.name as category_name,
                    c.slug as category_slug,
                    u.nickname as author,
                    u.username as username
                FROM 
                    {$this->prefix}article a
                LEFT JOIN 
                    {$this->prefix}category c ON a.category_id = c.id
                LEFT JOIN 
                    {$this->prefix}user u ON a.user_id = u.id
                INNER JOIN 
                    {$this->prefix}article_tag at ON a.id = at.article_id
                WHERE 
                    at.tag_id IN (
                        SELECT tag_id 
                        FROM {$this->prefix}article_tag 
                        WHERE article_id = ?
                    ) 
                    AND a.id != ? 
                    AND a.status = 1 
                    AND a.is_deleted = 0
                GROUP BY 
                    a.id
                ORDER BY 
                    COUNT(at.tag_id) DESC, a.created_at DESC
                LIMIT ?";
        
        $articles = $this->db->fetchAll($sql, [$articleId, $articleId, $limit]);
        
        // 为每篇文章生成摘要，保留时间戳用于排序，同时提供格式化的时间显示
        foreach ($articles as $index => $article) {
            $articles[$index]['excerpt'] = $this->generateExcerpt($article, 150);
            $articles[$index]['created_at_formatted'] = date('Y-m-d H:i:s', $article['created_at']);
            // 确保view_count始终是整数
            $articles[$index]['view_count'] = (int)$article['view_count'];
        }
        
        return $articles;
    }
    
    // 插入文章标签关联
    private function insertArticleTags($articleId, $tagIds) {
        if (empty($tagIds) || !is_array($tagIds)) {
            return;
        }
        
        // 循环插入标签关联
        foreach ($tagIds as $tagId) {
            if (!empty($tagId)) {
                $this->db->insert('article_tag', [
                    'article_id' => $articleId,
                    'tag_id' => $tagId
                ]);
            }
        }
    }
    
    // 删除文章标签关联
    private function deleteArticleTags($articleId) {
        $this->db->delete('article_tag', ['article_id' => $articleId]);
    }
    
    /**
     * 设置文章置顶状态
     * @param int $articleId 文章ID
     * @param int $topType 置顶类型：0-不置顶，1-全局置顶，2-首页置顶，3-分类置顶
     * @return bool 是否设置成功
     */
    public function setArticleTop($articleId, $topType) {
        try {
            $result = $this->db->update('article', 
                ['top_type' => $topType, 'updated_at' => time()], 
                ['id' => $articleId]);
            return $result;
        } catch (Exception $e) {
            // 如果top_type字段不存在，返回false
            return false;
        }
    }
    
    /**
     * 获取指定类型的置顶文章
     * @param array $topTypes 置顶类型数组：[1, 2, 3]
     * @param int $categoryId 分类ID（仅分类置顶时有效）
     * @param int $limit 限制数量
     * @return array 置顶文章列表
     */
    public function getTopArticles($topTypes, $categoryId = 0, $limit = 5) {
        try {
            $placeholders = implode(',', array_fill(0, count($topTypes), '?'));
            $sql = "SELECT 
                        a.*, 
                        c.name as category_name,
                        c.slug as category_slug,
                        u.nickname as author,
                        u.username as username
                    FROM 
                        {$this->prefix}article a
                    LEFT JOIN 
                        {$this->prefix}category c ON a.category_id = c.id
                    LEFT JOIN 
                        {$this->prefix}user u ON a.user_id = u.id
                    WHERE 
                        a.is_deleted = 0 AND a.status = 1 AND a.top_type IN ($placeholders)";
            
            $params = $topTypes;
            
            // 添加分类筛选（仅分类置顶时有效）
            if ($categoryId) {
                $sql .= " AND (a.top_type != 3 OR a.category_id = ?)";
                $params[] = $categoryId;
            }
            
            // 排序
            $sql .= " ORDER BY 
                        CASE 
                            WHEN a.top_type = 1 THEN 3 
                            WHEN a.top_type = 2 THEN 2 
                            WHEN a.top_type = 3 THEN 1 
                            ELSE 0 
                        END DESC, 
                        a.created_at DESC 
                    LIMIT ?";
            
            $params[] = $limit;
            
            $articles = $this->db->fetchAll($sql, $params);
            
            // 处理文章数据
            foreach ($articles as $index => $article) {
                $articles[$index]['excerpt'] = $this->generateExcerpt($article, 150);
                $articles[$index]['created_at_formatted'] = date('Y-m-d H:i:s', $article['created_at']);
                $articles[$index]['updated_at_formatted'] = date('Y-m-d H:i:s', $article['updated_at']);
                $articles[$index]['view_count'] = (int)$article['view_count'];
            }
            
            return $articles;
        } catch (Exception $e) {
            // 如果top_type字段不存在，返回空数组
            return [];
        }
    }
    
    /**
     * 根据 slug 获取文章
     * @param string $slug 文章别名
     * @return array|null 文章数据或null
     */
    public function getArticleBySlug($slug) {
        $sql = "SELECT 
                    a.*, 
                    c.name as category_name,
                    c.slug as category_slug,
                    c.id as category_id_field,
                    u.nickname as author,
                    u.username as username,
                    u.id as user_id
                FROM 
                    {$this->prefix}article a
                LEFT JOIN 
                    {$this->prefix}category c ON a.category_id = c.id
                LEFT JOIN 
                    {$this->prefix}user u ON a.user_id = u.id
                WHERE 
                    a.slug = ? AND a.is_deleted = 0 AND a.status = 1";
        
        $article = $this->db->fetch($sql, [$slug]);
        
        if ($article) {
            $this->db->update('article', 
                            ['view_count' => 'SQL:view_count + 1'], 
                            ['id' => $article['id']]);
            
            $article['view_count'] = (int)$article['view_count'] + 1;
            // 保留时间戳用于过滤器使用，同时提供格式化的时间显示
            $article['created_at_formatted'] = date('Y-m-d H:i:s', $article['created_at']);
            $article['views'] = $article['view_count'];
            $article['excerpt'] = $this->generateExcerpt($article, 200);
        }
        
        return $article;
    }
    
    /**
     * 生成文章 slug
     * @param array $data 文章数据（包含title，可选slug手动指定）
     * @param int $excludeId 排除的文章ID（更新时避免与自己冲突）
     * @return string|null 生成的slug，留空则返回null（使用ID访问）
     */
    private function generateSlug($data, $excludeId = 0) {
        // 只在手动指定 slug 时才处理
        if (!empty($data['slug'])) {
            return $this->sanitizeSlug($data['slug'], $excludeId);
        }
        
        // slug 留空则返回 null，使用 ID 访问
        return null;
    }
    
    /**
     * 净化并确保 slug 唯一
     * @param string $raw 原始文本
     * @param int $excludeId 排除的文章ID
     * @return string 唯一slug
     */
    private function sanitizeSlug($raw, $excludeId = 0) {
        // 将标题转为 ASCII 友好格式
        $slug = strtolower(trim($raw));
        
        // 检测是否包含非ASCII字符（如中文）
        if (preg_match('/[^\x00-\x7F]/', $slug)) {
            // 包含中文等非ASCII字符，先用拼音方案；如果没有拼音扩展，用ID方案
            $slug = $this->pinyinSlug($slug);
        }
        
        // 替换非字母数字字符为连字符
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        // 去除首尾连字符
        $slug = trim($slug, '-');
        // 限制长度
        $slug = substr($slug, 0, 200);
        
        // 确保不为空
        if (empty($slug)) {
            $slug = 'article';
        }
        
        // 确保唯一性
        $originalSlug = $slug;
        $counter = 1;
        while ($this->slugExists($slug, $excludeId)) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
            if ($counter > 100) break; // 安全限制
        }
        
        return $slug;
    }
    
    /**
     * 中文转拼音 slug（回退到时间戳方案）
     * @param string $text 中文文本
     * @return string 拼音化的slug
     */
    private function pinyinSlug($text) {
        // 简单方案：提取英文和数字，中间用连字符连接
        // 对于纯中文标题，用 "article-" + 时间戳 + 随机数
        $ascii = preg_replace('/[^a-z0-9]/', '', strtolower($text));
        if (strlen($ascii) >= 4) {
            return $ascii;
        }
        return 'article-' . substr(md5($text . time()), 0, 8);
    }
    
    /**
     * 检查 slug 是否已存在
     * @param string $slug
     * @param int $excludeId 排除的文章ID
     * @return bool
     */
    private function slugExists($slug, $excludeId = 0) {
        $sql = "SELECT COUNT(*) as count FROM {$this->prefix}article WHERE slug = ?";
        $params = [$slug];
        if ($excludeId > 0) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        $result = $this->db->fetch($sql, $params);
        return $result && $result['count'] > 0;
    }

    /**
     * 检查 slug 是否存在（公开方法，供 Controller 验证使用）
     * @param string $slug 要检查的别名
     * @param int $excludeId 排除的文章ID（编辑文章时排除自身）
     * @return bool true=存在，false=不存在
     */
    public function isSlugExists($slug, $excludeId = 0) {
        return $this->slugExists($slug, $excludeId);
    }

    /**
     * 获取指定用户发布的文章列表（分页）
     * @param int $userId 用户ID
     * @param int $page 当前页码
     * @param int $limit 每页数量
     * @return array 文章列表
     */
    public function getArticlesByUserId($userId, $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;

        $sql = "SELECT
                    a.*,
                    c.name as category_name,
                    c.slug as category_slug,
                    u.nickname as author,
                    u.username as username,
                    COUNT(DISTINCT l.user_id, l.article_id) as like_count,
                    COUNT(DISTINCT f.user_id, f.article_id) as favorite_count,
                    COUNT(DISTINCT com.id) as comment_count
                FROM
                    {$this->prefix}article a
                LEFT JOIN
                    {$this->prefix}category c ON a.category_id = c.id
                LEFT JOIN
                    {$this->prefix}user u ON a.user_id = u.id
                LEFT JOIN
                    {$this->prefix}like l ON a.id = l.article_id
                LEFT JOIN
                    {$this->prefix}favorite f ON a.id = f.article_id
                LEFT JOIN
                    {$this->prefix}comments com ON a.id = com.article_id AND com.status = 1
                WHERE
                    a.user_id = ? AND a.status = 1 AND a.is_deleted = 0
                GROUP BY a.id
                ORDER BY a.created_at DESC
                LIMIT ? OFFSET ?";

        $articles = $this->db->fetchAll($sql, [$userId, $limit, $offset]);

        // 为每篇文章生成摘要、提取首图，保留时间戳用于排序，同时提供格式化的时间显示
        foreach ($articles as $index => $article) {
            $articles[$index]['excerpt'] = $this->generateExcerpt($article, 150);
            $articles[$index]['first_image'] = $this->extractFirstImage($article['content']);
            $articles[$index]['created_at_formatted'] = date('Y-m-d H:i:s', $article['created_at']);
            $articles[$index]['view_count'] = (int)$article['view_count'];
            $articles[$index]['like_count'] = (int)$article['like_count'];
            $articles[$index]['favorite_count'] = (int)$article['favorite_count'];
            $articles[$index]['comment_count'] = (int)$article['comment_count'];
        }

        return $articles;
    }

    /**
     * 获取指定用户发布的文章总数
     * @param int $userId 用户ID
     * @return int 文章总数
     */
    public function getArticleCountByUserId($userId) {
        $sql = "SELECT COUNT(*) as count FROM {$this->prefix}article WHERE user_id = ? AND status = 1 AND is_deleted = 0";
        $result = $this->db->fetch($sql, [$userId]);
        return $result ? (int)$result['count'] : 0;
    }

    /**
     * 获取指定用户发布的所有文章的总点赞数
     * @param int $userId 用户ID
     * @return int 总点赞数
     */
    public function getTotalLikesByUserId($userId) {
        $sql = "SELECT COUNT(*) as total FROM `{$this->prefix}like` l
                INNER JOIN {$this->prefix}article a ON l.article_id = a.id
                WHERE a.user_id = ? AND a.status = 1 AND a.is_deleted = 0";
        $result = $this->db->fetch($sql, [$userId]);
        return $result ? (int)$result['total'] : 0;
    }

    /**
     * 获取指定用户发布的所有文章的总浏览量
     * @param int $userId 用户ID
     * @return int 总浏览量
     */
    public function getTotalViewsByUserId($userId) {
        $sql = "SELECT COALESCE(SUM(view_count), 0) as total FROM {$this->prefix}article
                WHERE user_id = ? AND status = 1 AND is_deleted = 0";
        $result = $this->db->fetch($sql, [$userId]);
        return $result ? (int)$result['total'] : 0;
    }

    /**
     * 在用户发布的文章/点赞的文章/收藏的文章中搜索（综合搜索）
     * @param int $userId 用户ID
     * @param string $keyword 搜索关键词
     * @param int $page 当前页码
     * @param int $limit 每页数量
     * @return array 搜索结果列表，每项包含 source（published/liked/favorited）标识来源
     */
    public function searchUserRelatedArticles($userId, $keyword, $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        $likeKeyword = '%' . $keyword . '%';

        // 使用 UNION 同时搜索三种来源
        $sql = "(SELECT
                    a.*,
                    'published' as source,
                    c.name as category_name,
                    c.slug as category_slug,
                    u.nickname as author,
                    u.username as username,
                    a.created_at as sort_time
                FROM {$this->prefix}article a
                LEFT JOIN {$this->prefix}category c ON a.category_id = c.id
                LEFT JOIN {$this->prefix}user u ON a.user_id = u.id
                WHERE a.user_id = ? AND a.status = 1 AND a.is_deleted = 0
                    AND (a.title LIKE ? OR a.content LIKE ?))

                UNION ALL

                (SELECT
                    a.*,
                    'liked' as source,
                    c.name as category_name,
                    c.slug as category_slug,
                    u.nickname as author,
                    u.username as username,
                    l.created_at as sort_time
                FROM `{$this->prefix}like` l
                LEFT JOIN {$this->prefix}article a ON l.article_id = a.id
                LEFT JOIN {$this->prefix}category c ON a.category_id = c.id
                LEFT JOIN {$this->prefix}user u ON a.user_id = u.id
                WHERE l.user_id = ? AND a.status = 1 AND a.is_deleted = 0
                    AND (a.title LIKE ? OR a.content LIKE ?))

                UNION ALL

                (SELECT
                    a.*,
                    'favorited' as source,
                    c.name as category_name,
                    c.slug as category_slug,
                    u.nickname as author,
                    u.username as username,
                    f.created_at as sort_time
                FROM {$this->prefix}favorite f
                LEFT JOIN {$this->prefix}article a ON f.article_id = a.id
                LEFT JOIN {$this->prefix}category c ON a.category_id = c.id
                LEFT JOIN {$this->prefix}user u ON a.user_id = u.id
                WHERE f.user_id = ? AND a.status = 1 AND a.is_deleted = 0
                    AND (a.title LIKE ? OR a.content LIKE ?))

                ORDER BY sort_time DESC
                LIMIT ? OFFSET ?";

        $articles = $this->db->fetchAll($sql, [
            $userId, $likeKeyword, $likeKeyword,
            $userId, $likeKeyword, $likeKeyword,
            $userId, $likeKeyword, $likeKeyword,
            $limit, $offset
        ]);

        // 为每篇文章生成摘要和格式化数据
        foreach ($articles as $index => $article) {
            $articles[$index]['excerpt'] = $this->generateExcerpt($article, 150);
            $articles[$index]['first_image'] = $this->extractFirstImage($article['content']);
            $articles[$index]['created_at_formatted'] = date('Y-m-d H:i:s', $article['created_at']);
            $articles[$index]['view_count'] = (int)$article['view_count'];
        }

        return $articles;
    }

    /**
     * 获取用户相关文章搜索结果总数
     * @param int $userId 用户ID
     * @param string $keyword 搜索关键词
     * @return int 搜索结果总数
     */
    public function getSearchUserRelatedCount($userId, $keyword) {
        $likeKeyword = '%' . $keyword . '%';

        $sql = "(SELECT COUNT(*) as cnt FROM {$this->prefix}article a
                WHERE a.user_id = ? AND a.status = 1 AND a.is_deleted = 0
                    AND (a.title LIKE ? OR a.content LIKE ?))

                UNION ALL

                (SELECT COUNT(*) as cnt FROM `{$this->prefix}like` l
                LEFT JOIN {$this->prefix}article a ON l.article_id = a.id
                WHERE l.user_id = ? AND a.status = 1 AND a.is_deleted = 0
                    AND (a.title LIKE ? OR a.content LIKE ?))

                UNION ALL

                (SELECT COUNT(*) as cnt FROM {$this->prefix}favorite f
                LEFT JOIN {$this->prefix}article a ON f.article_id = a.id
                WHERE f.user_id = ? AND a.status = 1 AND a.is_deleted = 0
                    AND (a.title LIKE ? OR a.content LIKE ?))";

        $results = $this->db->fetchAll($sql, [
            $userId, $likeKeyword, $likeKeyword,
            $userId, $likeKeyword, $likeKeyword,
            $userId, $likeKeyword, $likeKeyword
        ]);

        $total = 0;
        foreach ($results as $row) {
            $total += (int)$row['cnt'];
        }
        return $total;
    }
}
