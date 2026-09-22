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
 * 通知管理控制器
 * 用于管理系统通知和用户通知
 */
class NotificationController {
    
    /**
     * 构造函数
     */
    public function __construct() {
        // 检查用户是否已登录
        if (!isset($_SESSION['admin'])) {
            header('Location: admin.php?action=login');
            exit;
        }
        
        // 检查权限
        if (!RoleModel::checkUserPermission($_SESSION['admin']['id'], 'notification')) {
            $this->error('您没有权限访问此页面');
            exit;
        }
    }
    
    /**
     * 通知列表页
     */
    public function index() {
        // 获取请求参数
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        // 每页条数（limit 白名单校验）
        $pageSize = ListQuery::pageSize();
        $allowedPageSizes = ListQuery::pageSizes();
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $type = isset($_GET['type']) ? $_GET['type'] : 'all';
        $status = isset($_GET['status']) ? $_GET['status'] : 'all';
        
        // 初始化NotificationModel
        $notificationModel = new NotificationModel();
        
        // 获取通知列表（自动去重）
        $notifications = $notificationModel->getAllUniqueNotifications($page, $pageSize, $type, $search, $status);
        
        // 获取通知总数（去重后的数量）
        $totalNotifications = $notificationModel->getAllUniqueNotificationsCount($type, $search, $status);
        
        // 计算总页数
        $totalPages = ceil($totalNotifications / $pageSize);
        
        // 初始化分页类（保留用于向后兼容）
        $pagination = new Pagination($totalNotifications, $pageSize, $page);
        
        // 直接包含模板文件
        include ADMIN_PATH . '/templates/notification_list.html';
    }
    
    /**
     * 发送系统通知页面
     */
    public function send() {
        // 处理表单提交
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            // 获取表单数据
            $title = isset($_POST['title']) ? trim($_POST['title']) : '';
            $content = isset($_POST['content']) ? trim($_POST['content']) : '';
            $sendTo = isset($_POST['send_to']) ? $_POST['send_to'] : 'all';
            $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
            $type = isset($_POST['type']) ? trim($_POST['type']) : 'system';
            
            // 验证表单数据
            if (empty($title)) {
                $this->error('通知标题不能为空');
                return;
            }
            
            if (empty($content)) {
                $this->error('通知内容不能为空');
                return;
            }
            
            // 验证用户ID（当发送给单个用户时）
            if ($sendTo !== 'all' && $userId <= 0) {
                $this->error('请选择有效的用户');
                return;
            }
            
            // 初始化NotificationModel
            $notificationModel = new NotificationModel();
            
            // 发送通知
            $result = $notificationModel->sendSystemNotification($title, $content, $sendTo, $userId, $type);
            
            if ($result) {
                $sendToText = $sendTo == 'all' ? '所有用户' : '用户ID: ' . $userId;
                Log::info('通知管理', '发送系统通知', '成功发送系统通知: ' . $title . ' (发送给: ' . $sendToText . ')', Log::CATEGORY_OPERATION);
                $this->success('系统通知发送成功', 'admin.php?action=notification');
            } else {
                Log::error('通知管理', '发送系统通知', '系统通知发送失败: ' . $title, Log::CATEGORY_OPERATION);
                $this->error('系统通知发送失败');
            }
            return;
        }
        
        // 直接包含模板文件
        include ADMIN_PATH . '/templates/notification_send.html';
    }
    
    /**
     * 删除通知（软删除，移到回收站）
     */
    public function delete() {
        // 获取通知ID
        $notificationId = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if ($notificationId <= 0) {
            $this->error('通知ID无效');
            return;
        }
        
        // 初始化NotificationModel
        $notificationModel = new NotificationModel();
        
        // 软删除通知（移到回收站）
        $result = $notificationModel->softDeleteNotification($notificationId);
        
        if ($result) {
            Log::info('通知管理', '删除通知', '成功将通知移到回收站: ID ' . $notificationId, Log::CATEGORY_OPERATION);
            $this->success('通知已移到回收站', 'admin.php?action=notification');
        } else {
            Log::error('通知管理', '删除通知', '移到回收站失败: ID ' . $notificationId, Log::CATEGORY_OPERATION);
            $this->error('操作失败');
        }
    }
    
    /**
     * 批量删除通知（软删除，移到回收站）
     */
    public function batchDelete() {
        // 获取通知ID列表
        $notificationIds = isset($_GET['ids']) ? explode(',', $_GET['ids']) : [];
        
        if (empty($notificationIds)) {
            $notificationIds = isset($_POST['notification_ids']) ? $_POST['notification_ids'] : [];
        }
        
        if (empty($notificationIds)) {
            $this->error('请选择要删除的通知');
            return;
        }
        
        // 转换为整数数组
        $notificationIds = array_map('intval', $notificationIds);
        
        // 初始化NotificationModel
        $notificationModel = new NotificationModel();
        
        // 批量软删除通知（移到回收站）
        foreach ($notificationIds as $id) {
            $notificationModel->softDeleteNotification($id);
        }
        
        Log::info('通知管理', '批量删除通知', '成功将 ' . count($notificationIds) . ' 条通知移到回收站', Log::CATEGORY_OPERATION);
        $this->success('批量删除成功，已移到回收站', 'admin.php?action=notification');
    }
    
    /**
     * 回收站页面
     */
    public function recycle() {
        // 获取请求参数
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        // 每页条数（limit 白名单校验）
        $pageSize = ListQuery::pageSize();
        $allowedPageSizes = ListQuery::pageSizes();
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        
        // 初始化NotificationModel
        $notificationModel = new NotificationModel();
        
        // 获取回收站通知列表
        $notifications = $notificationModel->getDeletedNotifications($page, $pageSize, $search);
        
        // 获取通知总数
        $totalNotifications = $notificationModel->getDeletedNotificationsCount($search);
        
        // 计算总页数
        $totalPages = ceil($totalNotifications / $pageSize);
        
        // 直接包含模板文件
        include ADMIN_PATH . '/templates/notification_recycle.html';
    }
    
    /**
     * 获取通知详情（用于弹窗展示）
     */
    public function getNotification() {
        // 获取通知ID
        $notificationId = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if ($notificationId <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => '通知ID无效']);
            return;
        }
        
        // 初始化NotificationModel
        $notificationModel = new NotificationModel();
        
        // 获取通知详情
        $notification = $notificationModel->getNotificationById($notificationId);
        
        header('Content-Type: application/json');
        if ($notification) {
            $notification['created_at_formatted'] = date('Y-m-d H:i:s', $notification['created_at']);
            $notification['type_label'] = NotificationModel::getTypeLabel($notification['type']);
            echo json_encode(['success' => true, 'notification' => $notification]);
        } else {
            echo json_encode(['success' => false, 'message' => '通知不存在']);
        }
    }
    
    /**
     * 恢复通知（从回收站恢复）
     */
    public function restore() {
        // 获取通知ID
        $notificationId = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if ($notificationId <= 0) {
            $this->error('通知ID无效');
            return;
        }
        
        // 初始化NotificationModel
        $notificationModel = new NotificationModel();
        
        // 恢复通知
        $result = $notificationModel->restoreNotification($notificationId);
        
        if ($result) {
            Log::info('通知管理', '恢复通知', '成功恢复通知ID: ' . $notificationId, Log::CATEGORY_OPERATION);
            $this->success('通知恢复成功', 'admin.php?action=notification&method=recycle');
        } else {
            Log::error('通知管理', '恢复通知', '恢复通知失败: ID ' . $notificationId, Log::CATEGORY_OPERATION);
            $this->error('操作失败');
        }
    }
    
    /**
     * 批量恢复通知
     */
    public function batchRestore() {
        // 获取通知ID列表
        $notificationIds = isset($_GET['ids']) ? explode(',', $_GET['ids']) : [];
        
        if (empty($notificationIds)) {
            $notificationIds = isset($_POST['notification_ids']) ? $_POST['notification_ids'] : [];
        }
        
        if (empty($notificationIds)) {
            $this->error('请选择要恢复的通知');
            return;
        }
        
        // 转换为整数数组
        $notificationIds = array_map('intval', $notificationIds);
        
        // 初始化NotificationModel
        $notificationModel = new NotificationModel();
        
        // 批量恢复通知
        $result = $notificationModel->batchRestoreNotifications($notificationIds);
        
        if ($result) {
            Log::info('通知管理', '批量恢复通知', '成功恢复 ' . count($notificationIds) . ' 条通知', Log::CATEGORY_OPERATION);
            $this->success('批量恢复成功', 'admin.php?action=notification&method=recycle');
        } else {
            Log::error('通知管理', '批量恢复通知', '批量恢复通知失败', Log::CATEGORY_OPERATION);
            $this->error('批量恢复失败');
        }
    }
    
    /**
     * 永久删除通知
     */
    public function forceDelete() {
        // 获取通知ID
        $notificationId = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if ($notificationId <= 0) {
            $this->error('通知ID无效');
            return;
        }
        
        // 初始化NotificationModel
        $notificationModel = new NotificationModel();
        
        // 永久删除通知
        $result = $notificationModel->permanentlyDeleteNotification($notificationId);
        
        if ($result) {
            Log::info('通知管理', '永久删除通知', '成功永久删除通知ID: ' . $notificationId, Log::CATEGORY_OPERATION);
            $this->success('通知已永久删除', 'admin.php?action=notification&method=recycle');
        } else {
            Log::error('通知管理', '永久删除通知', '永久删除通知失败: ID ' . $notificationId, Log::CATEGORY_OPERATION);
            $this->error('操作失败');
        }
    }
    
    /**
     * 批量永久删除通知
     */
    public function batchForceDelete() {
        // 获取通知ID列表
        $notificationIds = isset($_GET['ids']) ? explode(',', $_GET['ids']) : [];
        
        if (empty($notificationIds)) {
            $notificationIds = isset($_POST['notification_ids']) ? $_POST['notification_ids'] : [];
        }
        
        if (empty($notificationIds)) {
            $this->error('请选择要永久删除的通知');
            return;
        }
        
        // 转换为整数数组
        $notificationIds = array_map('intval', $notificationIds);
        
        // 初始化NotificationModel
        $notificationModel = new NotificationModel();
        
        // 批量永久删除通知
        $result = $notificationModel->batchPermanentlyDeleteNotifications($notificationIds);
        
        if ($result) {
            Log::info('通知管理', '批量永久删除通知', '成功永久删除 ' . count($notificationIds) . ' 条通知', Log::CATEGORY_OPERATION);
            $this->success('批量永久删除成功', 'admin.php?action=notification&method=recycle');
        } else {
            Log::error('通知管理', '批量永久删除通知', '批量永久删除通知失败', Log::CATEGORY_OPERATION);
            $this->error('批量永久删除失败');
        }
    }
    
    /**
     * 通知统计页面
     */
    public function stats() {
        // 初始化NotificationModel
        $notificationModel = new NotificationModel();
        
        // 获取统计数据
        $stats = $notificationModel->getNotificationStats();
        
        // 直接包含模板文件
        include ADMIN_PATH . '/templates/notification_stats.html';
    }
    
    /**
     * 获取未读通知数量（API）
     */
    public function getUnreadCount() {
        if (!isset($_SESSION['admin']['id'])) {
            echo json_encode(['success' => false, 'message' => '未登录']);
            return;
        }
        
        $userId = (int)$_SESSION['admin']['id'];
        $notificationModel = new NotificationModel();
        $count = $notificationModel->getUnreadCount($userId);
        
        echo json_encode([
            'success' => true,
            'data' => ['count' => $count]
        ]);
    }
    
    /**
     * 获取最近通知列表（API）
     */
    public function getRecent() {
        if (!isset($_SESSION['admin']['id'])) {
            echo json_encode(['success' => false, 'message' => '未登录']);
            return;
        }
        
        $userId = (int)$_SESSION['admin']['id'];
        $notificationModel = new NotificationModel();
        $notifications = $notificationModel->getRecentNotifications($userId, 15);
        
        // 格式化时间
        foreach ($notifications as &$notification) {
            $notification['time_ago'] = $this->formatTimeAgo($notification['created_at']);
            $notification['type_label'] = NotificationModel::getTypeLabel($notification['type']);
            $notification['type_icon'] = NotificationModel::getTypeIcon($notification['type']);
        }
        
        echo json_encode([
            'success' => true,
            'data' => $notifications
        ]);
    }
    
    /**
     * 标记通知为已读（API）
     */
    public function markAsRead() {
        if (!isset($_SESSION['admin']['id'])) {
            echo json_encode(['success' => false, 'message' => '未登录']);
            return;
        }
        
        $userId = (int)$_SESSION['admin']['id'];
        $notificationId = (int)($_POST['id'] ?? 0);
        
        if ($notificationId <= 0) {
            echo json_encode(['success' => false, 'message' => '参数错误']);
            return;
        }
        
        $notificationModel = new NotificationModel();
        $result = $notificationModel->markAsRead($notificationId, $userId);
        
        echo json_encode([
            'success' => $result,
            'message' => $result ? '已标记为已读' : '操作失败'
        ]);
    }
    
    /**
     * 标记所有通知为已读（API）
     */
    public function markAllAsRead() {
        if (!isset($_SESSION['admin']['id'])) {
            echo json_encode(['success' => false, 'message' => '未登录']);
            return;
        }
        
        $userId = (int)$_SESSION['admin']['id'];
        $notificationModel = new NotificationModel();
        $notificationModel->markAllAsRead($userId);
        
        echo json_encode([
            'success' => true,
            'message' => '全部已标记为已读'
        ]);
    }
    
    /**
     * 格式化时间为相对时间
     */
    private function formatTimeAgo(int $timestamp): string {
        $diff = time() - $timestamp;
        
        if ($diff < 60) {
            return '刚刚';
        } elseif ($diff < 3600) {
            return floor($diff / 60) . '分钟前';
        } elseif ($diff < 86400) {
            return floor($diff / 3600) . '小时前';
        } elseif ($diff < 604800) {
            return floor($diff / 86400) . '天前';
        } else {
            return date('Y-m-d', $timestamp);
        }
    }
    
    /**
     * 显示错误信息
     */
    private function error($message) {
        $_SESSION['error_message'] = $message;
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    }
    
    /**
     * 显示成功信息
     */
    private function success($message, $redirectUrl = '') {
        $_SESSION['success_message'] = $message;
        if (!empty($redirectUrl)) {
            header('Location: ' . $redirectUrl);
        } else {
            header('Location: ' . $_SERVER['HTTP_REFERER']);
        }
        exit;
    }
}
?>