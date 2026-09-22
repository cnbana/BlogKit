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


class Search {
    private static $instance = null;
    
    private function __construct() {
        // 私有构造函数
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 搜索文章
     * 
     * @param string $query 搜索关键词
     * @param array $options 搜索选项
     * @return array ['total' => 总数, 'hits' => 结果数组]
     */
    public function search($query, $options = []) {
        $db = Database::getInstance();
        $prefix = Config::get('database')['prefix'];
        
        $page = $options['page'] ?? 1;
        $limit = $options['limit'] ?? 10;
        $offset = ($page - 1) * $limit;
        
        $params = [];
        $whereConditions = ['status = 1'];
        
        if (!empty($options['category_id'])) {
            $whereConditions[] = 'category_id = ?';
            $params[] = (int)$options['category_id'];
        }
        
        $queryType = $options['mode'] ?? 'natural';
        
        if ($queryType === 'boolean') {
            $searchCondition = "MATCH(title, content, description) AGAINST(? IN BOOLEAN MODE)";
        } else {
            $searchCondition = "MATCH(title, content, description) AGAINST(? IN NATURAL LANGUAGE MODE)";
        }
        
        $whereConditions[] = $searchCondition;
        $params[] = $query;
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $sql = "SELECT 
                    id, 
                    title, 
                    content, 
                    description, 
                    category_id,
                    user_id,
                    view_count,
                    like_count,
                    comment_count,
                    created_at,
                    MATCH(title, content, description) AGAINST(? IN NATURAL LANGUAGE MODE) AS relevance
                FROM {$prefix}article 
                WHERE {$whereClause}
                ORDER BY relevance DESC, created_at DESC 
                LIMIT ? OFFSET ?";
        
        $params[] = $query;
        $params[] = $limit;
        $params[] = $offset;
        
        $results = $db->fetchAll($sql, $params);
        
        foreach ($results as &$result) {
            $result['highlight'] = $this->highlightText($result, $query);
            $result['excerpt'] = $this->createExcerpt($result['content'], $query);
            unset($result['content']);
        }
        
        $countSql = "SELECT COUNT(*) as total FROM {$prefix}article WHERE {$whereClause}";
        $countParams = array_slice($params, 0, -3);
        $countResult = $db->fetch($countSql, $countParams);
        
        return [
            'total' => $countResult['total'] ?? 0,
            'hits' => $results
        ];
    }
    
    /**
     * 创建搜索摘要
     */
    private function createExcerpt($content, $query, $length = 200) {
        $content = strip_tags($content);
        $query = strtolower($query);
        $contentLower = strtolower($content);
        
        $pos = strpos($contentLower, $query);
        
        if ($pos !== false) {
            $start = max(0, $pos - 50);
            $excerpt = substr($content, $start, $length);
            
            if ($start > 0) {
                $excerpt = '...' . $excerpt;
            }
            if (strlen($content) > $start + $length) {
                $excerpt .= '...';
            }
        } else {
            $excerpt = substr($content, 0, $length);
            if (strlen($content) > $length) {
                $excerpt .= '...';
            }
        }
        
        return $excerpt;
    }
    
    /**
     * 高亮搜索关键词
     */
    private function highlightText($article, $query) {
        $terms = explode(' ', trim($query));
        $highlighted = [];
        
        $title = $article['title'];
        foreach ($terms as $term) {
            $term = trim($term);
            if (!empty($term)) {
                $title = preg_replace(
                    '/(' . preg_quote($term, '/') . ')/i',
                    '<mark>$1</mark>',
                    $title
                );
            }
        }
        $highlighted['title'] = $title;
        
        if (!empty($article['description'])) {
            $description = $article['description'];
            foreach ($terms as $term) {
                $term = trim($term);
                if (!empty($term)) {
                    $description = preg_replace(
                        '/(' . preg_quote($term, '/') . ')/i',
                        '<mark>$1</mark>',
                        $description
                    );
                }
            }
            $highlighted['description'] = $description;
        }
        
        return $highlighted;
    }
    
    /**
     * 热门搜索词
     */
    public function getHotSearches($limit = 10) {
        $defaultHotSearches = [
            ['keyword' => '技术分享', 'count' => 120],
            ['keyword' => 'PHP开发', 'count' => 85],
            ['keyword' => '前端技术', 'count' => 72],
            ['keyword' => '数据库优化', 'count' => 68],
            ['keyword' => '性能优化', 'count' => 55],
        ];
        
        return $defaultHotSearches;
    }
    
    /**
     * 相关搜索建议
     */
    public function getSuggestions($query, $limit = 5) {
        $db = Database::getInstance();
        $prefix = Config::get('database')['prefix'];
        
        $sql = "SELECT DISTINCT title FROM {$prefix}article 
                WHERE status = 1 AND title LIKE ?
                ORDER BY created_at DESC LIMIT ?";
        
        $results = $db->fetchAll($sql, ['%' . $query . '%', $limit]);
        
        $suggestions = [];
        foreach ($results as $row) {
            $suggestions[] = $row['title'];
        }
        
        return $suggestions;
    }
}