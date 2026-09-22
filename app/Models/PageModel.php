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


class PageModel {
    private $db;
    private $table;
    
    /**
     * 构造函数
     */
    public function __construct() {
        $this->db = Database::getInstance();
        $this->table = $this->db->table('page');
        
        // 检查页面表是否存在，如果不存在则创建
        $this->checkAndCreateTable();
    }
    
    /**
     * 检查页面表是否存在，如果不存在则创建
     */
    private function checkAndCreateTable() {
        $sql = "SHOW TABLES LIKE '{$this->table}'";
        $result = $this->db->fetch($sql);
        
        if (!$result) {
            // 页面表不存在，创建它
            $sql = "CREATE TABLE IF NOT EXISTS `{$this->table}` (
              `id` bigint(20) NOT NULL AUTO_INCREMENT,
              `title` varchar(255) NOT NULL COMMENT '页面标题',
              `content` longtext NOT NULL COMMENT '页面内容',
              `description` text DEFAULT NULL COMMENT '页面描述',
              `keywords` varchar(255) DEFAULT NULL COMMENT '页面关键词',
            `slug` varchar(100) DEFAULT NULL COMMENT '页面别名（可为空，为空时使用ID访问）',
              `status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '状态：1-发布，0-草稿',
              `is_menu` tinyint(4) NOT NULL DEFAULT '1' COMMENT '是否显示在页面菜单：1-显示，0-隐藏',
              `created_at` int(11) NOT NULL COMMENT '创建时间',
              `updated_at` int(11) NOT NULL COMMENT '更新时间',
              PRIMARY KEY (`id`),
              UNIQUE KEY `slug` (`slug`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='页面表';";
            
            $this->db->query($sql);
        } else {
            // 检查is_menu字段是否存在，如果不存在则添加
            $sql = "SHOW COLUMNS FROM `{$this->table}` LIKE 'is_menu'";
            $column = $this->db->fetch($sql);
            if (!$column) {
                $sql = "ALTER TABLE `{$this->table}` ADD `is_menu` tinyint(4) NOT NULL DEFAULT '1' COMMENT '是否显示在页面菜单：1-显示，0-隐藏'";
                $this->db->query($sql);
            }

            // 检查slug字段，如果它是 NOT NULL 则改为允许 NULL（旧版本的数据库升级）
            $sql = "SHOW COLUMNS FROM `{$this->table}` LIKE 'slug'";
            $column = $this->db->fetch($sql);
            if ($column && strtoupper($column['Null'] ?? '') === 'NO') {
                $sql = "ALTER TABLE `{$this->table}` MODIFY COLUMN `slug` varchar(100) DEFAULT NULL COMMENT '页面别名（可为空，为空时使用ID访问）'";
                $this->db->query($sql);
            }

            // 检查description字段是否存在，如果不存在则添加
            $sql = "SHOW COLUMNS FROM `{$this->table}` LIKE 'description'";
            $column = $this->db->fetch($sql);
            if (!$column) {
                $sql = "ALTER TABLE `{$this->table}` ADD `description` text DEFAULT NULL COMMENT '页面描述'";
                $this->db->query($sql);
            }
            
            // 检查keywords字段是否存在，如果不存在则添加
            $sql = "SHOW COLUMNS FROM `{$this->table}` LIKE 'keywords'";
            $column = $this->db->fetch($sql);
            if (!$column) {
                $sql = "ALTER TABLE `{$this->table}` ADD `keywords` varchar(255) DEFAULT NULL COMMENT '页面关键词'";
                $this->db->query($sql);
            }
        }
        
        // 添加一个示例页面（仅当页面表为空时）
        $sql = "SELECT COUNT(*) as count FROM {$this->table}";
        $result = $this->db->fetch($sql);
        
        if ($result && $result['count'] == 0) {
            $timestamp = time();
            $samplePage = [
                'title' => '关于我们',
                'content' => '<h2>关于我们</h2><p>这是一个使用BlogKit创建的示例页面。</p>',
                'slug' => 'about',
                'status' => 1,
                'is_menu' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp
            ];
            
            $this->db->insert('page', $samplePage);
        }
    }
    
    /**
     * 根据ID获取页面
     * @param int $id 页面ID
     * @return array 页面信息
     */
    public function getPageById($id) {
        $sql = "SELECT * FROM {$this->table} WHERE id = ? AND status = 1";
        $page = $this->db->fetch($sql, [$id]);
        
        if ($page) {
            // 转换时间戳为可读日期
            $page['created_at'] = date('Y-m-d H:i:s', $page['created_at']);
            $page['updated_at'] = date('Y-m-d H:i:s', $page['updated_at']);
        }
        
        return $page;
    }
    
    /**
     * 根据别名获取页面
     * @param string $slug 页面别名
     * @return array 页面信息
     */
    public function getPageBySlug($slug) {
        $sql = "SELECT * FROM {$this->table} WHERE slug = ? AND status = 1";
        $page = $this->db->fetch($sql, [$slug]);
        
        if ($page) {
            // 转换时间戳为可读日期
            $page['created_at'] = date('Y-m-d H:i:s', $page['created_at']);
            $page['updated_at'] = date('Y-m-d H:i:s', $page['updated_at']);
        }
        
        return $page;
    }
    
    /**
     * 获取所有页面
     * @param int $page 页码
     * @param int $limit 每页数量
     * @return array 页面列表
     */
    public function getAllPages($page = 1, $limit = 20, $sort = '', $order = 'desc') {
        $offset = ($page - 1) * $limit;
        // 排序：白名单校验排序字段（防 SQL 注入），默认按创建时间倒序
        $sortWhiteList = ['id', 'created_at', 'updated_at', 'title'];
        $orderDir = (strtolower($order) === 'asc') ? 'ASC' : 'DESC';
        if ($sort !== '' && in_array($sort, $sortWhiteList)) {
            $sql = "SELECT * FROM {$this->table} ORDER BY {$sort} {$orderDir}, created_at DESC LIMIT ? OFFSET ?";
        } else {
            $sql = "SELECT * FROM {$this->table} ORDER BY created_at DESC LIMIT ? OFFSET ?";
        }
        $pages = $this->db->fetchAll($sql, [$limit, $offset]);
        
        // 转换时间戳为可读日期
        foreach ($pages as &$page) {
            $page['created_at'] = date('Y-m-d H:i:s', $page['created_at']);
            $page['updated_at'] = date('Y-m-d H:i:s', $page['updated_at']);
        }
        
        return $pages;
    }
    
    /**
     * 获取页面总数
     * @return int 页面总数
     */
    public function getPageCount() {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}";
        $result = $this->db->fetch($sql, []);
        return $result['count'] ?? 0;
    }
    
    /**
     * 获取显示在菜单中的页面
     * @return array 菜单页面列表
     */
    public function getMenuPages() {
        $sql = "SELECT * FROM {$this->table} WHERE status = 1 AND is_menu = 1 ORDER BY created_at DESC";
        $pages = $this->db->fetchAll($sql);
        
        return $pages;
    }
    
    /**
     * 创建页面
     * @param array $data 页面数据
     * @return int 页面ID
     */
    public function createPage($data) {
        // 验证必要字段（slug 允许为空，空值时使用 ID 访问）
        if (empty($data['title']) || empty($data['content'])) {
            return false;
        }

        // 如果 slug 为空字符串，保存为 null，以便使用 ID 作为访问路径；
        // 如果 slug 非空，去除首尾空白再保存。
        $slug = isset($data['slug']) ? trim((string)$data['slug']) : '';

        // 准备数据
        $timestamp = time();
        $pageData = [
            'title' => $data['title'],
            'content' => $data['content'],
            'description' => $data['description'] ?? '',
            'keywords' => $data['keywords'] ?? '',
            'slug' => $slug === '' ? null : $slug,
            'status' => $data['status'] ?? 1,
            'is_menu' => $data['is_menu'] ?? 1,
            'created_at' => $timestamp,
            'updated_at' => $timestamp
        ];

        // 插入页面
        return $this->db->insert('page', $pageData);
    }

    /**
     * 更新页面
     * @param int $id 页面ID
     * @param array $data 页面数据
     * @return bool 更新结果
     */
    public function updatePage($id, $data) {
        // 验证必要字段（slug 允许为空，空值时使用 ID 访问）
        if (empty($id) || empty($data['title']) || empty($data['content'])) {
            return false;
        }

        // 如果 slug 为空字符串，保存为 null，以便使用 ID 作为访问路径
        $slug = isset($data['slug']) ? trim((string)$data['slug']) : '';

        // 准备数据
        $timestamp = time();
        $pageData = [
            'title' => $data['title'],
            'content' => $data['content'],
            'description' => $data['description'] ?? '',
            'keywords' => $data['keywords'] ?? '',
            'slug' => $slug === '' ? null : $slug,
            'status' => $data['status'] ?? 1,
            'is_menu' => $data['is_menu'] ?? 0,
            'updated_at' => $timestamp
        ];

        // 更新页面
        return $this->db->update('page', $pageData, ['id' => $id]);
    }
    
    /**
     * 检查ID是否存在
     * @param int $id 页面ID
     * @return bool 是否存在
     */
    public function exists($id) {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE id = ?";
        $result = $this->db->fetch($sql, [$id]);
        return $result['count'] > 0;
    }

    /**
     * 删除页面
     * @param int $id 页面ID
     * @return bool 删除结果
     */
    public function deletePage($id) {
        return $this->db->delete('page', ['id' => $id]);
    }
}
