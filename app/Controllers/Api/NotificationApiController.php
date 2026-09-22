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
 * 通知API控制器
 * 提供通知相关的API接口
 * 
 * 基础URL: /api.php/v1/notifications
 */

class NotificationApiController extends ApiController {
    
    /**
     * 获取通知列表
     * GET /api.php/v1/notifications
     * 
     * 查询参数：
     * - page: 页码（默认1）
     * - page_size: 每页数量（默认读取后台配置）
     */
    public function index() {
        $this->requireLogin();
        
        $page = $this->getIntParam('page', 1);
        $pageSize = $this->getPageSize();
        
        $notificationModel = new NotificationModel();
        $notifications = $notificationModel->getNotifications($this->currentUser['id'], $page, $pageSize);
        $total = $notificationModel->getNotificationCount($this->currentUser['id']);
        
        $this->paginate($notifications, $total, $page, $pageSize, '获取成功');
    }
    
    /**
     * 获取通知详情
     * GET /api.php/v1/notifications/{id}
     */
    public function show($id = 0) {
        $this->requireLogin();
        
        if (is_array($id)) {
            $notificationId = isset($id['id']) ? intval($id['id']) : 0;
        } else {
            $notificationId = intval($id);
        }
        
        if ($notificationId <= 0) {
            $this->error('通知ID无效', 400);
            return;
        }
        
        $notificationModel = new NotificationModel();
        $notification = $notificationModel->getNotificationById($notificationId);
        
        if (!$notification) {
            $this->error('通知不存在', 404);
            return;
        }
        
        if ($notification['user_id'] != $this->currentUser['id']) {
            $this->error('无权查看此通知', 403);
            return;
        }
        
        $this->success($notification, '获取成功');
    }
    
    /**
     * 标记通知为已读
     * POST /api.php/v1/notifications/{id}/read
     */
    public function read($id = 0) {
        $this->requireLogin();
        
        if (is_array($id)) {
            $notificationId = isset($id['id']) ? intval($id['id']) : 0;
        } else {
            $notificationId = intval($id);
        }
        
        if ($notificationId <= 0) {
            $this->error('通知ID无效', 400);
            return;
        }
        
        $notificationModel = new NotificationModel();
        $notification = $notificationModel->getNotificationById($notificationId);
        
        if (!$notification) {
            $this->error('通知不存在', 404);
            return;
        }
        
        if ($notification['user_id'] != $this->currentUser['id']) {
            $this->error('无权操作此通知', 403);
            return;
        }
        
        $result = $notificationModel->markAsRead($notificationId);
        
        if ($result) {
            $this->success(null, '标记成功');
        } else {
            $this->error('标记失败');
        }
    }
    
    /**
     * 标记所有通知为已读
     * POST /api.php/v1/notifications/read-all
     */
    public function readAll() {        $this->requireLogin();
        
        $notificationModel = new NotificationModel();
        $result = $notificationModel->markAllAsRead($this->currentUser['id']);
        
        if ($result) {
            $this->success(null, '全部标记成功');
        } else {
            $this->error('标记失败');
        }
    }
    
    /**
     * 删除通知
     * DELETE /api.php/v1/notifications/{id}
     */
    public function destroy($id = 0) {
        $this->requireLogin();
        
        if (is_array($id)) {
            $notificationId = isset($id['id']) ? intval($id['id']) : 0;
        } else {
            $notificationId = intval($id);
        }
        
        if ($notificationId <= 0) {
            $this->error('通知ID无效', 400);
            return;
        }
        
        $notificationModel = new NotificationModel();
        $notification = $notificationModel->getNotificationById($notificationId);
        
        if (!$notification) {
            $this->error('通知不存在', 404);
            return;
        }
        
        if ($notification['user_id'] != $this->currentUser['id']) {
            $this->error('无权删除此通知', 403);
            return;
        }
        
        $result = $notificationModel->deleteNotification($notificationId);
        
        if ($result) {
            $this->success(null, '删除成功');
        } else {
            $this->error('删除失败');
        }
    }

    /**
     * 获取未读通知数量
     * GET /api.php/v1/notifications/unread
     */
    public function unread() {
        $this->requireLogin();

        $notificationModel = new NotificationModel();
        $unreadCount = $notificationModel->getUnreadCount($this->currentUser['id']);

        $this->success(['unread_count' => $unreadCount], '获取成功');
    }
}
