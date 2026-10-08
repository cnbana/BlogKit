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
 * 配置模型
 * 用于处理配置相关的数据操作
 */
class ConfigModel {
    private $db;
    
    /**
     * 构造函数
     */
    public function __construct() {
        require_once CORE_PATH . '/lib/Database.php';
        $this->db = Database::getInstance();
    }
    
    /**
     * 获取所有配置
     * @return array 配置数组
     */
    public function getAllConfig() {
        $sql = "SELECT * FROM `" . $this->db->table('config') . "`;";
        $result = $this->db->query($sql);
        $config = [];
        
        if ($result) {
            while ($row = $result->fetch()) {
                $config[$row['name']] = $row['value'];
            }
        }
        
        return $config;
    }
    
    /**
     * 根据前缀获取配置
     * @param string $prefix 配置前缀
     * @return array 配置数组
     */
    public function getConfigByPrefix($prefix) {
        $sql = "SELECT * FROM `" . $this->db->table('config') . "` WHERE `name` LIKE ?";
        $param = [$prefix . '%'];
        $result = $this->db->query($sql, $param);
        $config = [];
        
        if ($result) {
            while ($row = $result->fetch()) {
                $config[$row['name']] = $row['value'];
            }
        }
        
        return $config;
    }
    
    /**
     * 根据名称获取单个配置
     * @param string $name 配置名称
     * @return mixed 配置值
     */
    public function getConfigByName($name) {
        $sql = "SELECT * FROM `" . $this->db->table('config') . "` WHERE `name` = ?";
        $param = [$name];
        $result = $this->db->query($sql, $param);
        
        if ($result) {
            $row = $result->fetch();
            return $row ? $row['value'] : null;
        }
        
        return null;
    }
    
    /**
     * 设置配置
     * @param string $name 配置名称
     * @param mixed $value 配置值
     * @param string $description 配置描述
     * @param string $type 配置类型
     * @return bool 是否设置成功
     */
    public function setConfig($name, $value, $description = '', $type = 'string') {
        // 初始化默认的插入配置
        $sql = "INSERT INTO `" . $this->db->table('config') . "` (`name`, `value`, `description`, `type`) VALUES (?, ?, ?, ?)";
        $param = [$name, $value, $description, $type];
        
        // 检查配置是否存在
        $checkSql = "SELECT * FROM `" . $this->db->table('config') . "` WHERE `name` = ?";
        $checkParam = [$name];
        $result = $this->db->query($checkSql, $checkParam);
        
        if ($result) {
            $row = $result->fetch();
            if ($row) {
                // 如果配置存在，更新配置
                $sql = "UPDATE `" . $this->db->table('config') . "` SET `value` = ?, `description` = ?, `type` = ? WHERE `name` = ?";
                $param = [$value, $description, $type, $name];
            }
        }
        
        // 执行SQL并返回结果
        try {
            $result = $this->db->query($sql, $param);
            return true;
        } catch (PDOException $e) {
            // 记录错误日志
            error_log('Failed to save config: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 批量设置配置
     * @param array $configs 配置数组，格式：[['name' => 'xxx', 'value' => 'xxx', 'description' => 'xxx', 'type' => 'xxx'], ...]
     * @return bool 是否设置成功
     */
    public function setBatchConfig($configs) {
        $success = true;
        
        foreach ($configs as $config) {
            if (!isset($config['name']) || !isset($config['value'])) {
                continue;
            }
            
            $description = isset($config['description']) ? $config['description'] : '';
            $type = isset($config['type']) ? $config['type'] : 'string';
            
            if (!$this->setConfig($config['name'], $config['value'], $description, $type)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * 删除配置
     * @param string $name 配置名称
     * @return bool 是否删除成功
     */
    public function deleteConfig($name) {
        $sql = "DELETE FROM `" . $this->db->table('config') . "` WHERE `name` = ?";
        $param = [$name];
        return $this->db->query($sql, $param);
    }
}