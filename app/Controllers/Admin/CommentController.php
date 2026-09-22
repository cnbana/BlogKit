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


// 包含必要的模型
if (!class_exists('CommentModel')) {
}

if (!class_exists('Template')) {
}

// 加载日志类

class AdminCommentController {
    protected $template;

    public function __construct() {
        // 初始化模板引擎
        $this->template = new Template();
        // 检查权限
        if (!$this->hasPermission('comment')) {
            $this->showError('没有权限访问该页面');
            exit;
        }
    }

    /**
     * 检查权限
     */
    protected function hasPermission($permission) {
        // 加载RoleModel类
        if (!class_exists('RoleModel')) {
        }
        
        // 使用系统统一的权限检查机制
        if (isset($_SESSION['admin'])) {
            return RoleModel::checkUserPermission($_SESSION['admin']['id'], $permission);
        }
        return false;
    }

    /**
     * 显示错误信息
     */
    protected function showError($message) {
        echo '<div style="color: red; padding: 10px; border: 1px solid red; margin: 10px;">' . $message . '</div>';
    }

    /**
     * 渲染模板
     */
    protected function render($template, $data = []) {
        $this->template->render(ADMIN_PATH . '/templates/' . $template . '.html', $data);
    }

    /**
     * JSON响应
     */
    protected function jsonResponse($data) {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * 评论列表页
     */
    public function index() {
        // 获取筛选参数
        $filters = [
            'status' => isset($_GET['status']) ? $_GET['status'] : '',
            'article_id' => isset($_GET['article_id']) ? $_GET['article_id'] : '',
            'search' => isset($_GET['search']) ? $_GET['search'] : ''
        ];

        // 分页参数（limit 白名单校验）
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $pageSize = ListQuery::pageSize();
        $allowedPageSizes = ListQuery::pageSizes();

        // 表头排序参数：白名单校验（模型内再做二次校验）
        list($sort, $order) = ListQuery::sort(['id', 'created_at']);
        // 获取评论列表（嵌套结构）
        $commentModel = new CommentModel();
        $comments = $commentModel->getNestedComments($page, $pageSize, $filters, $sort, $order);
        $totalCount = $commentModel->getCommentCount($filters);

        // 初始化分页类
        if (!class_exists('Pagination')) {
        }
        $pagination = new Pagination($totalCount, $pageSize, '', 'page');
        $pagination_html = $pagination->createLinks();
        $pagination_info = "第{$pagination->getCurrentPage()}页，共{$pagination->getTotalPages()}页，总{$pagination->getTotal()}条记录";
        
        // 计算总页数（用于公共分页组件）
        $totalPages = ceil($totalCount / $pageSize);

        // 包含模板文件
        include ADMIN_PATH . '/templates/comment.html';
    }

    /**
     * 切换评论状态
     */
    public function toggleStatus() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->jsonResponse(['code' => 400, 'msg' => '请求方法错误']);
            return;
        }

        $commentId = isset($_POST['id']) ? intval($_POST['id']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);
        if (!$commentId) {
            $this->jsonResponse(['code' => 400, 'msg' => '参数错误']);
            return;
        }

        $commentModel = new CommentModel();
        $comment = $commentModel->getCommentById($commentId);
        if (!$comment) {
            $this->jsonResponse(['code' => 404, 'msg' => '评论不存在']);
            return;
        }

        // 切换状态
        $newStatus = $comment['status'] == 1 ? 0 : 1;
        $result = $commentModel->updateCommentStatus($commentId, $newStatus);

        if ($result) {
            $statusText = $newStatus == 1 ? '启用' : '禁用';
            Log::info('评论管理', '状态变更', '成功' . $statusText . '评论ID: ' . $commentId, Log::CATEGORY_OPERATION);
            
            // 如果是从待审核变为审核通过，发送通知
            if ($comment['status'] == 0 && $newStatus == 1) {
                $notificationModel = new NotificationModel();
                
                // 获取文章信息
                $db = Database::getInstance();
                $article = $db->fetch("SELECT title FROM {$db->table('article')} WHERE id = ?", [$comment['article_id']]);
                $articleTitle = $article['title'] ?? '未知文章';
                
                // 创建通知
                $notificationModel->createNotification([
                    'user_id' => $comment['user_id'],
                    'type' => 'comments',
                    'title' => '评论审核通过',
                    'content' => "您在文章《{$articleTitle}》的评论已通过审核",
                    'is_read' => 0,
                    'created_at' => time(),
                    'read_at' => 0
                ]);
            }
            
            $this->jsonResponse(['code' => 200, 'msg' => '状态更新成功', 'status' => $newStatus]);
        } else {
            Log::error('评论管理', '状态变更', '状态更新失败: 评论ID ' . $commentId, Log::CATEGORY_OPERATION);
            $this->jsonResponse(['code' => 500, 'msg' => '状态更新失败']);
        }
    }

    /**
     * 删除评论
     */
    public function delete() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->jsonResponse(['code' => 400, 'msg' => '请求方法错误']);
            return;
        }

        $commentId = isset($_POST['id']) ? intval($_POST['id']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);
        if (!$commentId) {
            $this->jsonResponse(['code' => 400, 'msg' => '参数错误']);
            return;
        }

        $commentModel = new CommentModel();
        $result = $commentModel->deleteComment($commentId);

        if ($result) {
            Log::info('评论管理', '删除评论', '成功删除评论ID: ' . $commentId, Log::CATEGORY_OPERATION);
            $this->jsonResponse(['code' => 200, 'msg' => '删除成功']);
        } else {
            Log::error('评论管理', '删除评论', '删除评论失败: ID ' . $commentId, Log::CATEGORY_OPERATION);
            $this->jsonResponse(['code' => 500, 'msg' => '删除失败']);
        }
    }

    /**
     * 批量更新评论状态
     */
    public function batchUpdateStatus() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['code' => 400, 'msg' => '请求方法错误']);
            return;
        }

        $commentIds = isset($_POST['ids']) ? $_POST['ids'] : [];
        $status = isset($_POST['status']) ? intval($_POST['status']) : -1;

        if (empty($commentIds) || !in_array($status, [0, 1])) {
            $this->jsonResponse(['code' => 400, 'msg' => '参数错误']);
            return;
        }

        // 转换为整数数组
        $commentIds = array_map('intval', $commentIds);

        $commentModel = new CommentModel();
        $result = $commentModel->batchUpdateStatus($commentIds, $status);

        if ($result) {
            $statusText = $status == 1 ? '启用' : '禁用';
            Log::info('评论管理', '批量状态变更', '成功批量' . $statusText . '评论: ' . count($commentIds) . ' 条', Log::CATEGORY_OPERATION);
            
            // 如果是批量审核通过，发送通知
            if ($status == 1) {
                $notificationModel = new NotificationModel();
                $db = Database::getInstance();
                
                foreach ($commentIds as $commentId) {
                    // 获取评论信息
                    $comment = $commentModel->getCommentById($commentId);
                    if ($comment && $comment['status'] == 0) { // 确保是从待审核变为审核通过
                        // 获取文章信息
                        $article = $db->fetch("SELECT title FROM {$db->table('article')} WHERE id = ?", [$comment['article_id']]);
                        $articleTitle = $article['title'] ?? '未知文章';
                        
                        // 创建通知
                        $notificationModel->createNotification([
                            'user_id' => $comment['user_id'],
                            'type' => 'comments',
                            'title' => '评论审核通过',
                            'content' => "您在文章《{$articleTitle}》的评论已通过审核",
                            'is_read' => 0,
                            'created_at' => time(),
                            'read_at' => 0
                        ]);
                    }
                }
            }
            
            $this->jsonResponse(['code' => 200, 'msg' => '批量更新成功']);
        } else {
            Log::error('评论管理', '批量状态变更', '批量更新失败', Log::CATEGORY_OPERATION);
            $this->jsonResponse(['code' => 500, 'msg' => '批量更新失败']);
        }
    }

    /**
     * 批量删除评论
     */
    public function batchDelete() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['code' => 400, 'msg' => '请求方法错误']);
            return;
        }

        $commentIds = isset($_POST['ids']) ? $_POST['ids'] : [];

        if (empty($commentIds)) {
            $this->jsonResponse(['code' => 400, 'msg' => '请选择要删除的评论']);
            return;
        }

        // 转换为整数数组
        $commentIds = array_map('intval', $commentIds);

        $commentModel = new CommentModel();
        $result = $commentModel->batchDelete($commentIds);

        if ($result) {
            Log::info('评论管理', '批量删除', '成功批量删除评论: ' . count($commentIds) . ' 条', Log::CATEGORY_OPERATION);
            $this->jsonResponse(['code' => 200, 'msg' => '批量删除成功']);
        } else {
            Log::error('评论管理', '批量删除', '批量删除失败', Log::CATEGORY_OPERATION);
            $this->jsonResponse(['code' => 500, 'msg' => '批量删除失败']);
        }
    }

    /**
     * 点赞评论
     */
    public function like() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['code' => 400, 'msg' => '请求方法错误']);
            return;
        }

        // 检查用户是否登录
        if (!isset($_SESSION['user'])) {
            $this->jsonResponse(['code' => 401, 'msg' => '请先登录']);
            return;
        }

        $commentId = isset($_POST['comment_id']) ? intval($_POST['comment_id']) : 0;
        if (!$commentId) {
            $this->jsonResponse(['code' => 400, 'msg' => '参数错误']);
            return;
        }

        $commentModel = new CommentModel();
        $result = $commentModel->likeComment($commentId, $_SESSION['user']['id']);

        if ($result) {
            $newLikeCount = $commentModel->getCommentLikeCount($commentId);
            $this->jsonResponse(['code' => 200, 'msg' => '点赞成功', 'data' => ['like_count' => $newLikeCount]]);
        } else {
            $this->jsonResponse(['code' => 500, 'msg' => '点赞失败']);
        }
    }

    /**
     * 取消点赞评论
     */
    public function unlike() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['code' => 400, 'msg' => '请求方法错误']);
            return;
        }

        // 检查用户是否登录
        if (!isset($_SESSION['user'])) {
            $this->jsonResponse(['code' => 401, 'msg' => '请先登录']);
            return;
        }

        $commentId = isset($_POST['comment_id']) ? intval($_POST['comment_id']) : 0;
        if (!$commentId) {
            $this->jsonResponse(['code' => 400, 'msg' => '参数错误']);
            return;
        }

        $commentModel = new CommentModel();
        $result = $commentModel->unlikeComment($commentId, $_SESSION['user']['id']);

        if ($result) {
            $newLikeCount = $commentModel->getCommentLikeCount($commentId);
            $this->jsonResponse(['code' => 200, 'msg' => '取消点赞成功', 'data' => ['like_count' => $newLikeCount]]);
        } else {
            $this->jsonResponse(['code' => 500, 'msg' => '取消点赞失败']);
        }
    }
    
    /**
     * 评论回收站页面
     */
    public function recycle() {
        // 获取筛选参数
        $filters = [
            'status' => isset($_GET['status']) ? $_GET['status'] : '',
            'article_id' => isset($_GET['article_id']) ? $_GET['article_id'] : '',
            'search' => isset($_GET['search']) ? $_GET['search'] : '',
            'is_deleted' => 1 // 只显示已删除的评论
        ];

        // 分页参数（limit 白名单校验）
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $pageSize = ListQuery::pageSize();
        $allowedPageSizes = ListQuery::pageSizes();

        // 获取评论列表
        $commentModel = new CommentModel();
        $comments = $commentModel->getComments($page, $pageSize, $filters);
        $totalCount = $commentModel->getCommentCount($filters);

        // 初始化分页类
        if (!class_exists('Pagination')) {
        }
        $pagination = new Pagination($totalCount, $pageSize, '', 'page');
        $pagination_html = $pagination->createLinks();
        $pagination_info = "第{$pagination->getCurrentPage()}页，共{$pagination->getTotalPages()}页，总{$pagination->getTotal()}条记录";
        
        // 计算总页数（用于公共分页组件）
        $totalPages = ceil($totalCount / $pageSize);

        // 包含模板文件
        include ADMIN_PATH . '/templates/comment_recycle.html';
    }
    
    /**
     * 恢复评论
     */
    public function restore() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->jsonResponse(['code' => 400, 'msg' => '请求方法错误']);
            return;
        }

        $commentId = isset($_POST['id']) ? intval($_POST['id']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);
        if (!$commentId) {
            $this->jsonResponse(['code' => 400, 'msg' => '参数错误']);
            return;
        }

        $commentModel = new CommentModel();
        $result = $commentModel->restoreComment($commentId);

        if ($result) {
            Log::info('评论管理', '恢复评论', '成功恢复评论ID: ' . $commentId, Log::CATEGORY_OPERATION);
            $this->jsonResponse(['code' => 200, 'msg' => '恢复成功']);
        } else {
            Log::error('评论管理', '恢复评论', '恢复评论失败: ID ' . $commentId, Log::CATEGORY_OPERATION);
            $this->jsonResponse(['code' => 500, 'msg' => '恢复失败']);
        }
    }
    
    /**
     * 批量恢复评论
     */
    public function batchRestore() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['code' => 400, 'msg' => '请求方法错误']);
            return;
        }

        $commentIds = isset($_POST['ids']) ? $_POST['ids'] : [];

        if (empty($commentIds)) {
            $this->jsonResponse(['code' => 400, 'msg' => '请选择要恢复的评论']);
            return;
        }

        // 转换为整数数组
        $commentIds = array_map('intval', $commentIds);

        $commentModel = new CommentModel();
        $result = $commentModel->batchRestore($commentIds);

        if ($result) {
            Log::info('评论管理', '批量恢复评论', '成功批量恢复评论: ' . count($commentIds) . ' 条', Log::CATEGORY_OPERATION);
            $this->jsonResponse(['code' => 200, 'msg' => '批量恢复成功']);
        } else {
            Log::error('评论管理', '批量恢复评论', '批量恢复失败', Log::CATEGORY_OPERATION);
            $this->jsonResponse(['code' => 500, 'msg' => '批量恢复失败']);
        }
    }
    
    /**
     * 彻底删除评论
     */
    public function forceDelete() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->jsonResponse(['code' => 400, 'msg' => '请求方法错误']);
            return;
        }

        $commentId = isset($_POST['id']) ? intval($_POST['id']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);
        if (!$commentId) {
            $this->jsonResponse(['code' => 400, 'msg' => '参数错误']);
            return;
        }

        $commentModel = new CommentModel();
        $result = $commentModel->forceDeleteComment($commentId);

        if ($result) {
            Log::info('评论管理', '彻底删除评论', '成功彻底删除评论ID: ' . $commentId, Log::CATEGORY_OPERATION);
            $this->jsonResponse(['code' => 200, 'msg' => '彻底删除成功']);
        } else {
            Log::error('评论管理', '彻底删除评论', '彻底删除评论失败: ID ' . $commentId, Log::CATEGORY_OPERATION);
            $this->jsonResponse(['code' => 500, 'msg' => '彻底删除失败']);
        }
    }
    
    /**
     * 批量彻底删除评论
     */
    public function batchForceDelete()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['code' => 400, 'msg' => '请求方法错误']);
            return;
        }

        $commentIds = isset($_POST['ids']) ? $_POST['ids'] : [];

        if (empty($commentIds)) {
            $this->jsonResponse(['code' => 400, 'msg' => '请选择要彻底删除的评论']);
            return;
        }

        // 转换为整数数组
        $commentIds = array_map('intval', $commentIds);

        $commentModel = new CommentModel();
        $result = $commentModel->batchForceDelete($commentIds);

        if ($result) {
            Log::info('评论管理', '批量彻底删除评论', '成功批量彻底删除评论: ' . count($commentIds) . ' 条', Log::CATEGORY_OPERATION);
            $this->jsonResponse(['code' => 200, 'msg' => '批量彻底删除成功']);
        } else {
            Log::error('评论管理', '批量彻底删除评论', '批量彻底删除失败', Log::CATEGORY_OPERATION);
            $this->jsonResponse(['code' => 500, 'msg' => '批量彻底删除失败']);
        }
    }

    /**
     * 编辑评论页面
     */
    public function edit()
    {
        $commentId = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if (!$commentId) {
            $this->showError('参数错误');
            return;
        }

        $commentModel = new CommentModel();
        $comment = $commentModel->getCommentById($commentId);
        if (!$comment) {
            $this->showError('评论不存在');
            return;
        }

        // 包含编辑页面
        include ADMIN_PATH . '/templates/comment_edit.html';
    }

    /**
     * 更新评论
     */
    public function update()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['code' => 400, 'msg' => '请求方法错误']);
            return;
        }

        $commentId = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $content = isset($_POST['content']) ? trim($_POST['content']) : '';

        if (!$commentId || empty($content)) {
            $this->jsonResponse(['code' => 400, 'msg' => '参数错误']);
            return;
        }

        $commentModel = new CommentModel();
        $comment = $commentModel->getCommentById($commentId);
        if (!$comment) {
            $this->jsonResponse(['code' => 404, 'msg' => '评论不存在']);
            return;
        }

        $result = $commentModel->updateComment($commentId, [
            'content' => $content,
            'updated_at' => time()
        ]);

        if ($result) {
            Log::info('评论管理', '编辑评论', '成功编辑评论 ID: ' . $commentId, Log::CATEGORY_OPERATION);
            $this->jsonResponse(['code' => 200, 'msg' => '更新成功']);
        } else {
            Log::error('评论管理', '编辑评论', '更新失败: ID ' . $commentId, Log::CATEGORY_OPERATION);
            $this->jsonResponse(['code' => 500, 'msg' => '更新失败']);
        }
    }

    /**
     * 置顶/取消置顶评论
     */
    public function toggleTop()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->jsonResponse(['code' => 400, 'msg' => '请求方法错误']);
            return;
        }

        $commentId = isset($_POST['id']) ? intval($_POST['id']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);
        if (!$commentId) {
            $this->jsonResponse(['code' => 400, 'msg' => '参数错误']);
            return;
        }

        $commentModel = new CommentModel();
        $comment = $commentModel->getCommentById($commentId);
        if (!$comment) {
            $this->jsonResponse(['code' => 404, 'msg' => '评论不存在']);
            return;
        }

        $newTop = $comment['is_top'] == 1 ? 0 : 1;
        $result = $commentModel->updateComment($commentId, ['is_top' => $newTop]);

        if ($result) {
            $action = $newTop == 1 ? '置顶' : '取消置顶';
            Log::info('评论管理', $action . '评论', '成功' . $action . '评论 ID: ' . $commentId, Log::CATEGORY_OPERATION);
            $this->jsonResponse(['code' => 200, 'msg' => $action . '成功', 'is_top' => $newTop]);
        } else {
            Log::error('评论管理', '置顶评论', '操作失败: ID ' . $commentId, Log::CATEGORY_OPERATION);
            $this->jsonResponse(['code' => 500, 'msg' => '操作失败']);
        }
    }
}
