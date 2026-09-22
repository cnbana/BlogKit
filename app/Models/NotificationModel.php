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

/**
 * 通知模型
 * 
 * 提供通知的增删改查功能
 */

class NotificationModel {
    protected $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->ensureTableStructure();
    }
    
    /**
     * 确保表结构完整（自动迁移）
     */
    private function ensureTableStructure() {
        // 检查是否已有 is_deleted 字段
        $stmt = $this->db->query("SHOW COLUMNS FROM {$this->db->table('notifications')} LIKE 'is_deleted'");
        if ($stmt->rowCount() == 0) {
            $this->db->query("ALTER TABLE {$this->db->table('notifications')} ADD COLUMN is_deleted tinyint(4) NOT NULL DEFAULT '0' COMMENT '是否已删除：0-未删除，1-已删除'");
        }
        
        $stmt = $this->db->query("SHOW COLUMNS FROM {$this->db->table('notifications')} LIKE 'deleted_at'");
        if ($stmt->rowCount() == 0) {
            $this->db->query("ALTER TABLE {$this->db->table('notifications')} ADD COLUMN deleted_at int(11) NULL COMMENT '删除时间'");
        }
    }
    
    /**
     * 获取用户未读通知数量
     * 
     * @param int $userId 用户ID
     * @return int
     */
    public function getUnreadCount(int $userId): int {
        $stmt = $this->db->query("SELECT COUNT(*) FROM bk_notifications WHERE user_id = ? AND `read` = 0 AND is_deleted = 0", [$userId]);
        return (int)$stmt->fetchColumn();
    }
    
    /**
     * 获取用户通知数量
     * 
     * @param int $userId 用户ID
     * @param string $type 消息类型过滤
     * @param string $search 搜索关键词
     * @param bool $unreadOnly 只统计未读通知
     * @return int
     */
    public function getNotificationCount(int $userId, string $type = 'all', string $search = '', bool $unreadOnly = false): int {
        $sql = "SELECT COUNT(*) FROM bk_notifications WHERE user_id = ? AND is_deleted = 0";
        $params = [$userId];
        
        if ($type !== 'all') {
            $sql .= " AND type = ?";
            $params[] = $type;
        }
        
        if (!empty($search)) {
            $sql .= " AND (title LIKE ? OR content LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        if ($unreadOnly) {
            $sql .= " AND `read` = 0";
        }
        
        $stmt = $this->db->query($sql, $params);
        return (int)$stmt->fetchColumn();
    }
    
    /**
     * 获取用户通知列表
     * 
     * @param int $userId 用户ID
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @param string $type 类型过滤
     * @param string $search 搜索关键词
     * @param string $status 状态过滤
     * @param string $sort 排序方式
     * @return array
     */
    public function getNotifications(int $userId, int $page = 1, int $pageSize = 10, string $type = 'all', string $search = '', string $status = 'all', string $sort = 'desc'): array {
        $offset = ($page - 1) * $pageSize;
        $sql = "SELECT * FROM bk_notifications WHERE user_id = ? AND is_deleted = 0";
        $params = [$userId];
        
        if ($type !== 'all') {
            $sql .= " AND type = ?";
            $params[] = $type;
        }
        
        if (!empty($search)) {
            $sql .= " AND (title LIKE ? OR content LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        if ($status === 'unread') {
            $sql .= " AND `read` = 0";
        } elseif ($status === 'read') {
            $sql .= " AND `read` = 1";
        }
        
        // 处理排序方式：recent = 最新优先（DESC），oldest = 最早优先（ASC）
        $sortLower = strtolower($sort);
        if ($sortLower === 'oldest' || $sortLower === 'asc') {
            $orderDir = 'ASC';
        } else {
            // recent / desc / 默认：最新优先
            $orderDir = 'DESC';
        }
        $sql .= " ORDER BY created_at $orderDir LIMIT ? OFFSET ?";
        $params[] = $pageSize;
        $params[] = $offset;
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 获取用户最近通知（用于弹窗预览）
     * 
     * @param int $userId 用户ID
     * @param int $limit 数量限制，默认5条
     * @return array
     */
    public function getRecentNotifications(int $userId, int $limit = 5): array {
        return $this->getNotifications($userId, 1, $limit);
    }
    
    /**
     * 标记通知为已读
     * 
     * @param int $notificationId 通知ID
     * @param int $userId 用户ID（用于验证权限）
     * @return bool
     */
    public function markAsRead(int $notificationId, int $userId): bool {
        $stmt = $this->db->query("UPDATE bk_notifications SET `read` = 1 WHERE id = ? AND user_id = ?", [$notificationId, $userId]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * 标记所有通知为已读
     * 
     * @param int $userId 用户ID
     * @return bool
     */
    public function markAllAsRead(int $userId): bool {
        $this->db->query("UPDATE bk_notifications SET `read` = 1 WHERE user_id = ? AND `read` = 0", [$userId]);
        return true;
    }
    
    /**
     * 软删除通知（移到回收站）
     * 
     * @param int $notificationId 通知ID
     * @return bool
     */
    public function softDeleteNotification(int $notificationId): bool {
        $stmt = $this->db->query("UPDATE bk_notifications SET is_deleted = 1, deleted_at = ? WHERE id = ?", [time(), $notificationId]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * 恢复通知（从回收站恢复）
     * 
     * @param int $notificationId 通知ID
     * @return bool
     */
    public function restoreNotification(int $notificationId): bool {
        $stmt = $this->db->query("UPDATE bk_notifications SET is_deleted = 0, deleted_at = NULL WHERE id = ?", [$notificationId]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * 批量恢复通知
     * 
     * @param array $notificationIds 通知ID列表
     * @return bool
     */
    public function batchRestoreNotifications(array $notificationIds): bool {
        if (empty($notificationIds)) {
            return false;
        }
        
        $placeholders = implode(',', array_fill(0, count($notificationIds), '?'));
        $stmt = $this->db->query("UPDATE bk_notifications SET is_deleted = 0, deleted_at = NULL WHERE id IN ({$placeholders})", $notificationIds);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * 永久删除通知
     * 
     * @param int $notificationId 通知ID
     * @return bool
     */
    public function permanentlyDeleteNotification(int $notificationId): bool {
        $stmt = $this->db->query("DELETE FROM bk_notifications WHERE id = ?", [$notificationId]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * 批量永久删除通知
     * 
     * @param array $notificationIds 通知ID列表
     * @return bool
     */
    public function batchPermanentlyDeleteNotifications(array $notificationIds): bool {
        if (empty($notificationIds)) {
            return false;
        }
        
        $placeholders = implode(',', array_fill(0, count($notificationIds), '?'));
        $stmt = $this->db->query("DELETE FROM bk_notifications WHERE id IN ({$placeholders})", $notificationIds);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * 获取回收站中的通知列表
     * 
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @param string $search 搜索关键词
     * @return array
     */
    public function getDeletedNotifications(int $page = 1, int $pageSize = 20, string $search = ''): array {
        $offset = ($page - 1) * $pageSize;
        
        $where = 'n.is_deleted = 1';
        $params = [];
        
        if (!empty($search)) {
            $where .= ' AND (n.title LIKE ? OR n.content LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        
        $params[] = $offset;
        $params[] = $pageSize;
        
        $sql = "SELECT n.*, u.username FROM bk_notifications n " .
               "LEFT JOIN bk_user u ON n.user_id = u.id " .
               "WHERE {$where} ORDER BY n.deleted_at DESC LIMIT ?, ?";
        
        $notifications = $this->db->fetchAll($sql, $params);
        
        foreach ($notifications as &$notification) {
            $notification['created_at_formatted'] = date('Y-m-d H:i:s', $notification['created_at']);
            $notification['deleted_at_formatted'] = $notification['deleted_at'] ? date('Y-m-d H:i:s', $notification['deleted_at']) : '';
            $notification['type_label'] = $this->getTypeLabel($notification['type']);
            $notification['read_status'] = $notification['read'] == 0 ? '未读' : '已读';
        }
        
        return $notifications;
    }
    
    /**
     * 获取回收站通知总数
     * 
     * @param string $search 搜索关键词
     * @return int
     */
    public function getDeletedNotificationsCount(string $search = ''): int {
        $where = 'is_deleted = 1';
        $params = [];
        
        if (!empty($search)) {
            $where .= ' AND (title LIKE ? OR content LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        
        $sql = "SELECT COUNT(*) as total FROM bk_notifications WHERE {$where}";
        $result = $this->db->fetch($sql, $params);
        
        return $result ? (int)$result['total'] : 0;
    }
    
    /**
     * 删除通知
     * 
     * @param int $notificationId 通知ID
     * @param int $userId 用户ID（用于验证权限）
     * @return bool
     */
    public function deleteNotification(int $notificationId, int $userId): bool {
        $stmt = $this->db->query("DELETE FROM bk_notifications WHERE id = ? AND user_id = ?", [$notificationId, $userId]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * 添加通知（数组参数版本，兼容其他模块调用）
     * 
     * @param array $data 通知数据
     * @return int|false 新通知ID或false
     */
    public function createNotification(array $data) {
        $userId = $data['user_id'] ?? 0;
        $type = $data['type'] ?? 'default';
        $title = $data['title'] ?? '';
        $content = $data['content'] ?? '';
        $url = $data['url'] ?? '';
        
        return $this->addNotification($userId, $type, $title, $content, $url);
    }
    
    /**
     * 添加通知
     * 
     * @param int $userId 用户ID
     * @param string $type 通知类型
     * @param string $title 标题
     * @param string $content 内容
     * @param string $url 跳转链接
     * @return int|false 新通知ID或false
     */
    public function addNotification(int $userId, string $type, string $title, string $content, string $url = '') {
        $this->db->query("INSERT INTO bk_notifications (user_id, type, title, content, url, `read`, created_at) VALUES (?, ?, ?, ?, ?, 0, ?)", [$userId, $type, $title, $content, $url, time()]);
        $stmt = $this->db->query("SELECT LAST_INSERT_ID() as id");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['id'] : false;
    }
    
    /**
     * 根据ID获取通知详情
     * 
     * @param int $notificationId 通知ID
     * @return array|null
     */
    public function getNotificationById(int $notificationId): ?array {
        $stmt = $this->db->query("SELECT n.*, u.username FROM bk_notifications n LEFT JOIN bk_user u ON n.user_id = u.id WHERE n.id = ?", [$notificationId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * 获取通知类型图标
     * 
     * @param string $type 通知类型
     * @return string
     */
    public static function getTypeIcon(string $type): string {
        $icons = [
            'comments' => 'message-square',
            'comment_reply' => 'message-square',
            'follows' => 'user-plus',
            'new_follower' => 'user-plus',
            'articles' => 'file-text',
            'article_approved' => 'check-circle',
            'article_rejected' => 'alert-circle',
            'system' => 'settings',
            'system_update' => 'refresh-cw',
            'article_likes' => 'heart',
            'comment_likes' => 'heart',
            'likes' => 'heart',
            'favorites' => 'star',
            'default' => 'info'
        ];
        return $icons[$type] ?? $icons['default'];
    }
    
    /**
     * 获取通知类型描述
     * 
     * @param string $type 通知类型
     * @return string
     */
    public static function getTypeLabel(string $type): string {
        $labels = [
            'comments' => '评论通知',
            'comment_reply' => '评论回复',
            'follows' => '关注通知',
            'new_follower' => '新粉丝',
            'articles' => '文章通知',
            'article_approved' => '文章通过',
            'article_rejected' => '文章退回',
            'system' => '系统通知',
            'system_update' => '系统更新',
            'article_likes' => '文章点赞',
            'comment_likes' => '评论点赞',
            'likes' => '点赞通知',
            'favorites' => '收藏通知',
            'default' => '通知'
        ];
        return $labels[$type] ?? $labels['default'];
    }
    
    /**
     * 获取所有通知（管理后台用）
     * 
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @param string $type 类型过滤
     * @param string $search 搜索关键词
     * @param string $status 状态过滤
     * @return array
     */
    public function getAllNotifications(int $page, int $pageSize, string $type = 'all', string $search = '', string $status = 'all'): array {
        $offset = ($page - 1) * $pageSize;
        $sql = "SELECT * FROM bk_notifications WHERE is_deleted = 0";
        $params = [];
        
        if ($type !== 'all') {
            $sql .= " AND type = ?";
            $params[] = $type;
        }
        
        if (!empty($search)) {
            $sql .= " AND (title LIKE ? OR content LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        if ($status === 'unread') {
            $sql .= " AND `read` = 0";
        } elseif ($status === 'read') {
            $sql .= " AND `read` = 1";
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $pageSize;
        $params[] = $offset;
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 获取所有通知（关联用户信息，管理后台用）
     * 
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @param string $type 类型过滤
     * @param string $search 搜索关键词
     * @param string $status 状态过滤
     * @return array
     */
    public function getAllNotificationsWithUser(int $page, int $pageSize, string $type = 'all', string $search = '', string $status = 'all'): array {
        $offset = ($page - 1) * $pageSize;
        
        $sql = "SELECT n.id, n.title, n.type, n.content, n.created_at, n.read, u.username 
                FROM bk_notifications n 
                LEFT JOIN bk_user u ON n.user_id = u.id 
                WHERE n.is_deleted = 0";
        $params = [];
        
        if ($type !== 'all') {
            $sql .= " AND n.type = ?";
            $params[] = $type;
        }
        
        if (!empty($search)) {
            $sql .= " AND (n.title LIKE ? OR n.content LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        if ($status === 'unread') {
            $sql .= " AND n.`read` = 0";
        } elseif ($status === 'read') {
            $sql .= " AND n.`read` = 1";
        }
        
        $sql .= " ORDER BY n.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $pageSize;
        $params[] = $offset;
        
        $stmt = $this->db->query($sql, $params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $uniqueResults = [];
        $seen = [];
        foreach ($results as $row) {
            $key = $row['title'] . '|' . $row['created_at'] . '|' . $row['content'];
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $uniqueResults[] = $row;
            }
        }
        
        return $uniqueResults;
    }
    
    /**
     * 获取所有通知数量（管理后台用）
     * 
     * @param string $type 类型过滤
     * @param string $search 搜索关键词
     * @param string $status 状态过滤
     * @return int
     */
    public function getAllNotificationsCount(string $type = 'all', string $search = '', string $status = 'all'): int {
        $sql = "SELECT COUNT(*) FROM bk_notifications WHERE is_deleted = 0";
        $params = [];
        
        if ($type !== 'all') {
            $sql .= " AND type = ?";
            $params[] = $type;
        }
        
        if (!empty($search)) {
            $sql .= " AND (title LIKE ? OR content LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        if ($status === 'unread') {
            $sql .= " AND `read` = 0";
        } elseif ($status === 'read') {
            $sql .= " AND `read` = 1";
        }
        
        $stmt = $this->db->query($sql, $params);
        $total = (int)$stmt->fetchColumn();
        
        $sql = "SELECT COUNT(*) FROM (SELECT 1 FROM bk_notifications WHERE is_deleted = 0";
        $params2 = [];
        
        if ($type !== 'all') {
            $sql .= " AND type = ?";
            $params2[] = $type;
        }
        
        if (!empty($search)) {
            $sql .= " AND (title LIKE ? OR content LIKE ?)";
            $params2[] = "%$search%";
            $params2[] = "%$search%";
        }
        
        if ($status === 'unread') {
            $sql .= " AND `read` = 0";
        } elseif ($status === 'read') {
            $sql .= " AND `read` = 1";
        }
        
        $sql .= " GROUP BY title, created_at, content) AS temp";
        
        $stmt = $this->db->query($sql, $params2);
        return (int)$stmt->fetchColumn();
    }
    
    /**
     * 发送系统通知
     * 
     * @param string $title 标题
     * @param string $content 内容
     * @param string $sendTo 发送对象（all或指定用户ID）
     * @param int $userId 用户ID
     * @param string $type 通知类型
     * @return bool
     */
    public function sendSystemNotification(string $title, string $content, string $sendTo = 'all', int $userId = 0, string $type = 'system'): bool {
        if ($sendTo === 'all') {
            $stmt = $this->db->query("SELECT id FROM {$this->db->table('user')} WHERE status = 1");
            $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
            
            foreach ($userIds as $uid) {
                $this->addNotification($uid, $type, $title, $content);
            }
            return true;
        } else {
            return $this->addNotification($userId, $type, $title, $content) !== false;
        }
    }
    
    /**
     * 获取所有唯一通知列表（用于管理后台，自动去重）
     * 
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @param string $type 类型过滤
     * @param string $search 搜索关键词
     * @param string $status 状态过滤
     * @return array
     */
    public function getAllUniqueNotificationsCount(string $type = 'all', string $search = '', string $status = 'all'): int {
        $sql = "SELECT n.id, n.title, n.content, n.created_at 
                FROM bk_notifications n 
                WHERE n.is_deleted = 0";
        $params = [];
        
        if ($type !== 'all') {
            $sql .= " AND n.type = ?";
            $params[] = $type;
        }
        
        if (!empty($search)) {
            $sql .= " AND (n.title LIKE ? OR n.content LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        if ($status === 'unread') {
            $sql .= " AND n.`read` = 0";
        } elseif ($status === 'read') {
            $sql .= " AND n.`read` = 1";
        }
        
        $stmt = $this->db->query($sql, $params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $seen = [];
        foreach ($results as $row) {
            $key = md5($row['title'] . $row['content'] . $row['created_at']);
            $seen[$key] = true;
        }
        
        return count($seen);
    }
    
    public function getAllUniqueNotifications(int $page, int $pageSize, string $type = 'all', string $search = '', string $status = 'all'): array {
        $sql = "SELECT n.id, n.title, n.type, n.content, n.created_at, n.read, u.username 
                FROM bk_notifications n 
                LEFT JOIN bk_user u ON n.user_id = u.id 
                WHERE n.is_deleted = 0";
        $params = [];
        
        if ($type !== 'all') {
            $sql .= " AND n.type = ?";
            $params[] = $type;
        }
        
        if (!empty($search)) {
            $sql .= " AND (n.title LIKE ? OR n.content LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        if ($status === 'unread') {
            $sql .= " AND n.`read` = 0";
        } elseif ($status === 'read') {
            $sql .= " AND n.`read` = 1";
        }
        
        $sql .= " ORDER BY n.created_at DESC";
        
        $stmt = $this->db->query($sql, $params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $uniqueResults = [];
        $seen = [];
        foreach ($results as $row) {
            $key = md5($row['title'] . $row['content'] . $row['created_at']);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $uniqueResults[] = $row;
            }
        }
        
        $total = count($uniqueResults);
        $offset = ($page - 1) * $pageSize;
        $uniqueResults = array_slice($uniqueResults, $offset, $pageSize);
        
        foreach ($uniqueResults as &$row) {
            $row['created_at_formatted'] = date('Y-m-d H:i:s', $row['created_at']);
            $row['read_status'] = $row['read'] ? '已读' : '未读';
            $row['type_label'] = self::getTypeLabel($row['type']);
        }
        
        return $uniqueResults;
    }
    
    /**
     * 获取所有可用的系统通知类型
     * 
     * @return array
     */
    public static function getSystemNotificationTypes(): array {
        return [
            'system' => '系统通知',
            'system_update' => '系统更新',
            'article_approved' => '文章通过',
            'article_rejected' => '文章退回',
            'new_follower' => '新粉丝',
            'article_likes' => '文章点赞',
            'comment_likes' => '评论点赞',
            'favorites' => '收藏通知',
            'default' => '普通通知'
        ];
    }
    
    /**
     * 删除通知（管理后台用，不需要用户ID验证）
     * 
     * @param int $notificationId 通知ID
     * @return bool
     */
    public function deleteNotificationById(int $notificationId): bool {
        $stmt = $this->db->query("DELETE FROM bk_notifications WHERE id = ?", [$notificationId]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * 批量软删除通知（进入回收站）
     * 
     * @param array $notificationIds 通知ID列表
     * @return bool
     */
    public function batchDeleteNotifications(array $notificationIds): bool {
        if (empty($notificationIds)) {
            return false;
        }
        
        $placeholders = implode(',', array_fill(0, count($notificationIds), '?'));
        $params = array_merge([time()], $notificationIds);
        $stmt = $this->db->query("UPDATE bk_notifications SET is_deleted = 1, deleted_at = ? WHERE id IN ($placeholders)", $params);
        return $stmt->rowCount() > 0;
    }

    /**
     * 软删除用户所有通知（进入回收站）
     * 
     * @param int $userId 用户ID
     * @return bool
     */
    public function softDeleteAllByUserId(int $userId): bool {
        $stmt = $this->db->query("UPDATE bk_notifications SET is_deleted = 1, deleted_at = ? WHERE user_id = ? AND is_deleted = 0", [time(), $userId]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * 获取通知统计数据
     * 
     * @return array
     */
    public function getNotificationStats(): array {
        $stats = [];
        
        $stmt = $this->db->query("SELECT COUNT(*) FROM bk_notifications");
        $stats['total'] = (int)$stmt->fetchColumn();
        
        $stmt = $this->db->query("SELECT COUNT(*) FROM bk_notifications WHERE `read` = 0");
        $stats['unread'] = (int)$stmt->fetchColumn();
        
        $stmt = $this->db->query("SELECT type, COUNT(*) as count FROM bk_notifications GROUP BY type");
        $stats['by_type'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $today = strtotime(date('Y-m-d'));
        $stmt = $this->db->query("SELECT COUNT(*) FROM bk_notifications WHERE created_at >= ?", [$today]);
        $stats['today'] = (int)$stmt->fetchColumn();
        
        return $stats;
    }
    
    /**
     * 清理过期通知（用户登录时调用，清理超过30天的已读通知）
     * 
     * @param int $userId 用户ID
     * @return int 删除的通知数量
     */
    public function cleanExpiredNotifications(int $userId): int {
        $thirtyDaysAgo = strtotime('-30 days');
        $stmt = $this->db->query(
            "DELETE FROM {$this->db->table('notifications')} WHERE user_id = ? AND `read` = 1 AND created_at < ?",
            [$userId, $thirtyDaysAgo]
        );
        return $stmt->rowCount();
    }
}
?>