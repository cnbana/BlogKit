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


class CategoryModel
{
    private $db;
    private $prefix;
    
    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
        $config = Config::get('database');
        $this->prefix = $config['prefix'];
    }
    
    public function getAllCategories()
    {
        $sql = "SELECT c.*, COUNT(a.id) as article_count FROM {$this->prefix}category c LEFT JOIN {$this->prefix}article a ON c.id = a.category_id GROUP BY c.id ORDER BY c.sort ASC, c.id ASC";
        return $this->db->fetchAll($sql);
    }

    public function getCategoryById($id)
    {
        $sql = "SELECT *, name as category_name, description as category_description FROM {$this->prefix}category WHERE id = ?";
        return $this->db->fetch($sql, [$id]);
    }
    
    public function getCategoryBySlug($slug)
    {
        $sql = "SELECT *, name as category_name, description as category_description FROM {$this->prefix}category WHERE slug = ?";
        return $this->db->fetch($sql, [$slug]);
    }

    public function getCategoryCount()
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->prefix}category";
        $result = $this->db->fetch($sql);
        return $result['count'];
    }
    
    public function addCategory($data)
    {
        // 验证排序值必须为非负整数
        $sort = (int)$data['order_by'];
        if ($sort < 0) {
            $sort = 0;
        }
        
        $slug = isset($data['slug']) ? $data['slug'] : '';
        $keywords = isset($data['keywords']) ? $data['keywords'] : '';
        $is_menu = isset($data['is_menu']) ? (int)$data['is_menu'] : 1;
        $redirect_url = isset($data['redirect_url']) ? trim($data['redirect_url']) : '';
        
        $sql = "INSERT INTO {$this->prefix}category (name, parent_id, sort, description, keywords, slug, is_menu, redirect_url, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $this->db->query($sql, [$data['name'], $data['parent_id'], $sort, $data['description'], $keywords, $slug, $is_menu, $redirect_url, time()]);
        return $this->db->getPdo()->lastInsertId();
    }
    
    public function updateCategory($id, $data)
    {
        // 验证排序值必须为非负整数
        $sort = (int)$data['order_by'];
        if ($sort < 0) {
            $sort = 0;
        }
        
        $slug = isset($data['slug']) ? $data['slug'] : '';
        $keywords = isset($data['keywords']) ? $data['keywords'] : '';
        $is_menu = isset($data['is_menu']) ? (int)$data['is_menu'] : 1;
        $redirect_url = isset($data['redirect_url']) ? trim($data['redirect_url']) : '';
        
        $sql = "UPDATE {$this->prefix}category SET name = ?, parent_id = ?, sort = ?, description = ?, keywords = ?, slug = ?, is_menu = ?, redirect_url = ? WHERE id = ?";
        return $this->db->query($sql, [$data['name'], $data['parent_id'], $sort, $data['description'], $keywords, $slug, $is_menu, $redirect_url, $id]);
    }
    
    public function deleteCategory($id)
    {
        // 检查是否有子分类
        $sql = "SELECT COUNT(*) as count FROM {$this->prefix}category WHERE parent_id = ?";
        $result = $this->db->fetch($sql, [$id]);
        if ($result['count'] > 0) {
            return false; // 有子分类，不能删除
        }
        
        // 检查是否有文章使用该分类
        $sql = "SELECT COUNT(*) as count FROM {$this->prefix}article WHERE category_id = ?";
        $result = $this->db->fetch($sql, [$id]);
        if ($result['count'] > 0) {
            return false; // 有文章使用，不能删除
        }
        
        // 删除分类
        $sql = "DELETE FROM {$this->prefix}category WHERE id = ?";
        return $this->db->query($sql, [$id]);
    }
    
    public function updateCategoryOrder($id, $order)
    {
        // 验证排序值必须为非负整数
        $sort = (int)$order;
        if ($sort < 0) {
            $sort = 0;
        }
        
        $sql = "UPDATE {$this->prefix}category SET sort = ? WHERE id = ?";
        return $this->db->query($sql, [$sort, $id]);
    }
    
    public function getCategoriesTree($categories = null)
    {
        if ($categories === null) {
            $categories = $this->getAllCategories();
        }
        
        $tree = array();
        
        // 先创建所有顶级分类
        foreach ($categories as $category) {
            if ($category['parent_id'] == 0) {
                $tree[$category['id']] = $category;
                $tree[$category['id']]['children'] = array();
            }
        }
        
        // 然后添加子分类
        foreach ($categories as $category) {
            if ($category['parent_id'] != 0 && isset($tree[$category['parent_id']])) {
                $tree[$category['parent_id']]['children'][] = $category;
            }
        }
        
        return $tree;
    }
    
    public function getCategoriesList($parent_id = 0, $prefix = '', $categories = null)
    {
        if ($categories === null) {
            $categories = $this->getAllCategories();
        }
        
        $list = array();
        
        foreach ($categories as $category) {
            if ($category['parent_id'] == $parent_id) {
                $category['name_with_prefix'] = $prefix . $category['name'];
                $list[$category['id']] = $category;
                
                // 递归获取子分类
                $children = $this->getCategoriesList($category['id'], $prefix . '└─ ', $categories);
                if (!empty($children)) {
                    $list = array_merge($list, $children);
                }
            }
        }
        
        return $list;
    }
    
    /**
     * 获取指定分类的直接子分类
     * @param int $parentId 父分类ID
     * @return array
     */
    public function getChildCategories($parentId)
    {
        $sql = "SELECT * FROM {$this->prefix}category WHERE parent_id = ? ORDER BY sort ASC, id ASC";
        return $this->db->fetchAll($sql, [$parentId]);
    }
    
    /**
     * 获取只显示在菜单中的分类
     * @return array
     */
    public function getMenuCategories()
    {
        $sql = "SELECT c.*, COUNT(a.id) as article_count FROM {$this->prefix}category c LEFT JOIN {$this->prefix}article a ON c.id = a.category_id WHERE c.is_menu = 1 GROUP BY c.id ORDER BY c.sort ASC, c.id ASC";
        return $this->db->fetchAll($sql);
    }
    
    /**
     * 检查指定ID的分类是否存在
     * @param int $id 分类ID
     * @return bool 是否存在
     */
    public function exists($id)
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->prefix}category WHERE id = ?";
        $result = $this->db->fetch($sql, [$id]);
        return $result['count'] > 0;
    }
}
