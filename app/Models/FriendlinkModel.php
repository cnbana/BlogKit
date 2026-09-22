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
 * 友情链接模型
 * 处理友情链接的数据库操作
 */
class FriendlinkModel {
    private $db;
    
    /**
     * 构造函数
     */
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * 获取所有友情链接
     * @param array $conditions 条件
     * @param int $limit 限制数量
     * @param int $offset 偏移量
     * @return array 友情链接列表
     */
    public function getFriendlinks($conditions = [], $limit = 0, $offset = 0) {
        $where = [];
        $params = [];
        
        if (!empty($conditions)) {
            foreach ($conditions as $key => $value) {
                $where[] = "$key = ?";
                $params[] = $value;
            }
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $limitClause = $limit > 0 ? "LIMIT $limit OFFSET $offset" : '';
        
        $sql = "SELECT * FROM {$this->db->table('friendlink')} $whereClause ORDER BY sort ASC, id DESC $limitClause";
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * 获取友情链接总数
     * @param array $conditions 条件
     * @return int 总数
     */
    public function getFriendlinkCount($conditions = []) {
        $where = [];
        $params = [];
        
        if (!empty($conditions)) {
            foreach ($conditions as $key => $value) {
                $where[] = "$key = ?";
                $params[] = $value;
            }
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT COUNT(*) as count FROM {$this->db->table('friendlink')} $whereClause";
        $result = $this->db->fetch($sql, $params);
        return $result['count'];
    }
    
    /**
     * 根据ID获取友情链接
     * @param int $id 友情链接ID
     * @return array 友情链接信息
     */
    public function getFriendlinkById($id) {
        $sql = "SELECT * FROM {$this->db->table('friendlink')} WHERE id = ?";
        return $this->db->fetch($sql, [$id]);
    }
    
    /**
     * 添加友情链接
     * @param array $data 友情链接数据
     * @return int 插入的ID
     */
    public function addFriendlink($data) {
        $now = time();
        $data['created_at'] = $now;
        $data['updated_at'] = $now;
        
        return $this->db->insert('friendlink', $data);
    }
    
    /**
     * 更新友情链接
     * @param int $id 友情链接ID
     * @param array $data 友情链接数据
     * @return bool 是否更新成功
     */
    public function updateFriendlink($id, $data) {
        $data['updated_at'] = time();
        return $this->db->update('friendlink', $data, ['id' => $id]);
    }
    
    /**
     * 删除友情链接
     * @param int $id 友情链接ID
     * @return bool 是否删除成功
     */
    public function deleteFriendlink($id) {
        return $this->db->delete('friendlink', ['id' => $id]);
    }
    
    /**
     * 批量删除友情链接
     * @param array $ids 友情链接ID数组
     * @return bool 是否删除成功
     */
    public function batchDeleteFriendlinks($ids) {
        if (empty($ids)) {
            return false;
        }
        
        $placeholders = rtrim(str_repeat('?,', count($ids)), ',');
        $sql = "DELETE FROM {$this->db->table('friendlink')} WHERE id IN ($placeholders)";
        return $this->db->query($sql, $ids);
    }
    
    /**
     * 获取启用的友情链接
     * @param int $limit 限制数量
     * @return array 友情链接列表
     */
    public function getEnabledFriendlinks($limit = 0) {
        $limitClause = $limit > 0 ? "LIMIT $limit" : '';
        $sql = "SELECT * FROM {$this->db->table('friendlink')} WHERE status = 1 ORDER BY sort ASC, id DESC $limitClause";
        return $this->db->fetchAll($sql);
    }
}
