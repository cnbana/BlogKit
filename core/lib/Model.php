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


class Model {
    protected $db;
    protected $table;
    
    /**
     * 构造函数
     * @param string $table 表名
     */
    public function __construct($table) {
        $this->db = Database::getInstance();
        $this->table = $table;
    }
    
    /**
     * 获取单条记录
     * @param mixed $id 主键值
     * @param string $primaryKey 主键名
     * @return array
     */
    public function find($id, $primaryKey = 'id') {
        $sql = "SELECT * FROM {$this->db->table($this->table)} WHERE {$primaryKey} = :id";
        return $this->db->fetch($sql, ['id' => $id]);
    }
    
    /**
     * 获取所有记录
     * @return array
     */
    public function all() {
        $sql = "SELECT * FROM {$this->db->table($this->table)}";
        return $this->db->fetchAll($sql);
    }
    
    /**
     * 根据条件获取记录
     * @param array $where 条件
     * @param array $orderBy 排序
     * @param int $limit 限制
     * @param int $offset 偏移
     * @return array
     */
    public function get($where = [], $orderBy = [], $limit = 0, $offset = 0) {
        $sql = "SELECT * FROM {$this->db->table($this->table)}";
        $params = [];
        
        // 处理条件
        if (!empty($where)) {
            $whereClause = [];
            foreach ($where as $key => $value) {
                $whereClause[] = "{$key} = :{$key}";
                $params[$key] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $whereClause);
        }
        
        // 处理排序
        if (!empty($orderBy)) {
            $orderClause = [];
            foreach ($orderBy as $field => $direction) {
                $orderClause[] = "{$field} {$direction}";
            }
            $sql .= " ORDER BY " . implode(', ', $orderClause);
        }
        
        // 处理分页
        if ($limit > 0) {
            $sql .= " LIMIT :limit";
            $params['limit'] = $limit;
            
            if ($offset > 0) {
                $sql .= " OFFSET :offset";
                $params['offset'] = $offset;
            }
        }
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * 插入数据
     * @param array $data 数据
     * @return int 插入ID
     */
    public function insert($data) {
        return $this->db->insert($this->table, $data);
    }
    
    /**
     * 更新数据
     * @param array $data 数据
     * @param array $where 条件
     * @return int 受影响行数
     */
    public function update($data, $where) {
        return $this->db->update($this->table, $data, $where);
    }
    
    /**
     * 删除数据
     * @param array $where 条件
     * @return int 受影响行数
     */
    public function delete($where) {
        return $this->db->delete($this->table, $where);
    }
    
    /**
     * 获取记录总数
     * @param array $where 条件
     * @return int 总数
     */
    public function count($where = []) {
        $sql = "SELECT COUNT(*) as count FROM {$this->db->table($this->table)}";
        $params = [];
        
        if (!empty($where)) {
            $whereClause = [];
            foreach ($where as $key => $value) {
                $whereClause[] = "{$key} = :{$key}";
                $params[$key] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $whereClause);
        }
        
        $result = $this->db->fetch($sql, $params);
        return $result['count'];
    }
}
