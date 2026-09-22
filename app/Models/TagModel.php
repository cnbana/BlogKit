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


class TagModel {
    private $db;
    private $prefix;
    
    public function __construct() {
        $config = Config::get('database');
        $this->db = Database::getInstance();
        $this->prefix = $config['prefix'];
    }
    
    // 创建标签
     public function createTag($name) {
         $sql = "INSERT INTO {$this->prefix}tag (name) VALUES (?)";
         return $this->db->query($sql, [$name]);
     }
     
     // 更新标签
     public function updateTag($id, $name) {
         $sql = "UPDATE {$this->prefix}tag SET name = ? WHERE id = ?";
         return $this->db->query($sql, [$name, $id]);
     }
     
     // 删除标签（同时删除关联的文章标签关系）
     public function deleteTag($id) {
         // 先删除文章标签关联记录
         $sql = "DELETE FROM {$this->prefix}article_tag WHERE tag_id = ?";
         $this->db->query($sql, [$id]);
         
         // 再删除标签本身
         $sql = "DELETE FROM {$this->prefix}tag WHERE id = ?";
         return $this->db->query($sql, [$id]);
     }
    
    // 获取所有标签（包含文章计数）
    public function getAllTags() {
        $sql = "SELECT 
                    t.*, 
                    COUNT(at.article_id) as article_count
                FROM 
                    {$this->prefix}tag t
                LEFT JOIN 
                    {$this->prefix}article_tag at ON t.id = at.tag_id
                GROUP BY 
                    t.id
                ORDER BY 
                    article_count DESC";
        return $this->db->fetchAll($sql);
    }
    
    // 根据文章ID获取标签
    public function getTagsByArticleId($articleId) {
        $sql = "SELECT 
                    t.*
                FROM 
                    {$this->prefix}tag t
                INNER JOIN 
                    {$this->prefix}article_tag at ON t.id = at.tag_id
                WHERE 
                    at.article_id = ?
                ORDER BY 
                    t.name ASC";
        
        return $this->db->fetchAll($sql, [$articleId]);
    }
    
    // 获取标签总数
    public function getTagCount() {
        $sql = "SELECT COUNT(*) as count FROM {$this->prefix}tag";
        return $this->db->fetch($sql)['count'];
    }
    
    // 根据ID获取标签
    public function getTagById($id) {
        $sql = "SELECT * FROM {$this->prefix}tag WHERE id = ?";
        return $this->db->fetch($sql, [$id]);
    }
    
    // 根据标签名获取或创建标签，并返回标签ID
    /**
     * 根据标签名获取或创建标签ID
     * @param string $name 标签名
     * @return int 标签ID
     */
    public function getOrCreateTagId($name) {
        // 去除空格
        $name = trim($name);
        if (empty($name)) {
            return false;
        }
        
        // 检查标签是否已存在
        $tag = $this->db->fetch("SELECT id FROM {$this->db->table('tag')} WHERE name = ?", [$name]);
        
        if ($tag) {
            // 标签已存在，返回ID
            return $tag['id'];
        }
        
        // 创建新标签
        $createdAt = time();
        $tagData = [
            'name' => $name,
            'created_at' => $createdAt
        ];
        
        // 使用Database类的insert方法创建标签
        return $this->db->insert('tag', $tagData);
    }
    
    // 处理用户输入的标签字符串，返回标签ID数组
    public function processTags($tagsString) {
        // 去除两端空格
        $tagsString = trim($tagsString);
        if (empty($tagsString)) {
            return [];
        }
        
        // 将中文逗号（，）替换为英文逗号（,）
        $tagsString = str_replace('，', ',', $tagsString);
        
        // 使用逗号或空格分割标签
        $tagNames = preg_split('/[,\s]+/', $tagsString, -1, PREG_SPLIT_NO_EMPTY);
        
        // 去重
        $tagNames = array_unique($tagNames);
        
        // 获取或创建每个标签的ID
        $tagIds = [];
        foreach ($tagNames as $tagName) {
            $tagId = $this->getOrCreateTagId($tagName);
            if ($tagId) {
                $tagIds[] = $tagId;
            }
        }
        
        return $tagIds;
    }
}
