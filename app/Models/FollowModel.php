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
 * 用户关注模型
 */
class FollowModel {
    private $db;
    private $table = 'user_follow';
    
    public function __construct() {
        $this->db = Database::getInstance();
        // 检查并创建关注关系表
        $this->checkAndCreateTable();
    }
    
    /**
     * 检查并创建关注关系表
     */
    private function checkAndCreateTable() {
        try {
            // 获取表名（带前缀）
            $tableName = $this->db->table($this->table);
            
            // 检查表是否存在
            $checkSql = "SHOW TABLES LIKE '$tableName'";
            $result = $this->db->fetch($checkSql, []);
            
            if (!$result) {
                // 表不存在，创建表
                $createSql = "CREATE TABLE IF NOT EXISTS `$tableName` (
                    `id` bigint(20) NOT NULL AUTO_INCREMENT,
                    `follower_id` bigint(20) NOT NULL COMMENT '粉丝ID（关注者）',
                    `following_id` bigint(20) NOT NULL COMMENT '被关注者ID',
                    `created_at` int(11) NOT NULL COMMENT '关注时间',
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `follower_following_unique` (`follower_id`, `following_id`),
                    KEY `follower_id` (`follower_id`),
                    KEY `following_id` (`following_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户关注关系表'";
                $this->db->query($createSql, []);
            }
        } catch (Exception $e) {
            // 忽略错误，继续执行
        }
    }
    
    /**
     * 关注用户
     * @param int $followerId 关注者ID
     * @param int $followingId 被关注者ID
     * @return bool
     */
    public function followUser($followerId, $followingId) {
        // 检查是否已经关注
        if ($this->isFollowing($followerId, $followingId)) {
            return true;
        }

        // 不能关注自己
        if ($followerId == $followingId) {
            return false;
        }

        // 触发关注前钩子
        $followData = [
            'follower_id' => $followerId,
            'followed_id' => $followingId
        ];
        Hook::trigger(Hook::USER_FOLLOW_BEFORE, $followData);

        // 插入关注记录
        $data = [
            'follower_id' => $followerId,
            'following_id' => $followingId,
            'created_at' => time()
        ];

        $result = $this->db->insert($this->table, $data);
        
        if ($result) {
            // 触发关注后钩子
            Hook::trigger(Hook::USER_FOLLOW_AFTER, $followData);
            
            // 直接发送通知，不通过插件
            require_once APP_PATH . '/Models/NotificationModel.php';
            $notificationModel = new NotificationModel();
            
            // 获取关注者信息
            $follower = $this->db->fetch("SELECT * FROM {$this->db->table('user')} WHERE id = ?", [$followerId]);
            if ($follower) {
                $notificationModel->createNotification([
                    'user_id' => $followingId,
                    'type' => 'follows',
                    'title' => '新关注通知',
                    'content' => "用户 {$follower['nickname']} 关注了您",
                    'is_read' => 0,
                    'created_at' => time(),
                    'read_at' => 0
                ]);
            }
        }
        
        return $result;
    }

    /**
     * 取消关注用户
     * @param int $followerId 关注者ID
     * @param int $followingId 被关注者ID
     * @return bool
     */
    public function unfollowUser($followerId, $followingId) {
        // 触发取消关注前钩子
        $unfollowData = [
            'follower_id' => $followerId,
            'followed_id' => $followingId
        ];
        Hook::trigger(Hook::USER_UNFOLLOW_BEFORE, $unfollowData);
        
        $result = $this->db->delete($this->table, ['follower_id' => $followerId, 'following_id' => $followingId]);
        
        if ($result) {
            // 触发取消关注后钩子
            Hook::trigger(Hook::USER_UNFOLLOW_AFTER, $unfollowData);
        }
        
        return $result;
    }
    
    /**
     * 检查是否已关注
     * @param int $followerId 关注者ID
     * @param int $followingId 被关注者ID
     * @return bool
     */
    public function isFollowing($followerId, $followingId) {
        $sql = "SELECT id FROM " . $this->db->table($this->table) . " WHERE follower_id = ? AND following_id = ?";
        $result = $this->db->fetch($sql, [$followerId, $followingId]);
        return !empty($result);
    }
    
    /**
     * 获取关注数量
     * @param int $userId 用户ID
     * @return int
     */
    public function getFollowingCount($userId) {
        $sql = "SELECT COUNT(*) as count FROM " . $this->db->table($this->table) . " WHERE follower_id = ?";
        $result = $this->db->fetch($sql, [$userId]);
        return $result['count'] ?? 0;
    }
    
    /**
     * 获取粉丝数量
     * @param int $userId 用户ID
     * @return int
     */
    public function getFollowersCount($userId) {
        $sql = "SELECT COUNT(*) as count FROM " . $this->db->table($this->table) . " WHERE following_id = ?";
        $result = $this->db->fetch($sql, [$userId]);
        return $result['count'] ?? 0;
    }
    
    /**
     * 获取关注列表
     * @param int $userId 用户ID
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @return array
     */
    public function getFollowingList($userId, $page = 1, $pageSize = 10) {
        $offset = ($page - 1) * $pageSize;
        $sql = "SELECT u.*, uf.created_at as follow_time FROM " . $this->db->table($this->table) . " uf
                LEFT JOIN " . $this->db->table('user') . " u ON uf.following_id = u.id
                WHERE uf.follower_id = ? AND u.is_deleted = 0
                ORDER BY uf.created_at DESC
                LIMIT ?, ?";
        return $this->db->fetchAll($sql, [$userId, $offset, $pageSize]);
    }
    
    /**
     * 获取粉丝列表
     * @param int $userId 用户ID
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @return array
     */
    public function getFollowersList($userId, $page = 1, $pageSize = 10) {
        $offset = ($page - 1) * $pageSize;
        $sql = "SELECT u.*, uf.created_at as follow_time FROM " . $this->db->table($this->table) . " uf
                LEFT JOIN " . $this->db->table('user') . " u ON uf.follower_id = u.id
                WHERE uf.following_id = ? AND u.is_deleted = 0
                ORDER BY uf.created_at DESC
                LIMIT ?, ?";
        return $this->db->fetchAll($sql, [$userId, $offset, $pageSize]);
    }
}
