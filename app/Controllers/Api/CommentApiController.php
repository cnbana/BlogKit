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
 * 评论API控制器
 * 提供评论相关的API接口
 * 
 * 基础URL: /api.php/v1/articles/{article_id}/comments
 */

class CommentApiController extends ApiController {
    
    /**
     * 获取文章评论列表
     * GET /api.php/v1/articles/{article_id}/comments
     * 
     * 查询参数：
     * - article_id: 文章ID（必填）
     * - page: 页码（默认1）
     * - page_size: 每页数量（默认读取后台配置）
     */
    public function index($article_id = 0) {
        if (is_array($article_id)) {
            $articleId = isset($article_id['article_id']) ? intval($article_id['article_id']) : 0;
        } else {
            $articleId = intval($article_id);
        }
        $page = $this->getIntParam('page', 1);
        $pageSize = $this->getPageSize();
        
        if ($articleId <= 0) {
            $this->error('文章ID无效', 400);
            return;
        }
        
        
        $articleModel = new ArticleModel();
        $article = $articleModel->getArticleById($articleId);
        
        if (!$article) {
            $this->error('文章不存在', 404);
            return;
        }
        
        $commentModel = new CommentModel();
        $comments = $commentModel->getCommentsByArticle($articleId, $page, $pageSize);
        $total = $commentModel->getCommentCountByArticle($articleId);
        
        $this->paginate($comments, $total, $page, $pageSize, '获取成功');
    }
    
    /**
     * 发表评论
     * POST /api.php/v1/articles/{article_id}/comments
     * 
     * 请求体：
     * - content: 评论内容（必填）
     */
    public function store() {        $this->requireLogin();
        
        $articleId = $this->getIntParam('article_id', 0);
        $content = $this->getStringParam('content');
        
        if ($articleId <= 0) {
            $this->error('文章ID无效', 400);
            return;
        }
        
        if (empty($content)) {
            $this->error('评论内容不能为空', 400);
            return;
        }
        
        
        $articleModel = new ArticleModel();
        $article = $articleModel->getArticleById($articleId);
        
        if (!$article) {
            $this->error('文章不存在', 404);
            return;
        }
        
        $commentModel = new CommentModel();
        $commentData = [
            'article_id' => $articleId,
            'user_id' => $this->currentUser['id'],
            'content' => $content,
            'status' => 0
        ];
        
        $commentId = $commentModel->createComment($commentData);
        
        if ($commentId) {
            $this->success(['comment_id' => $commentId], '评论发表成功');
        } else {
            $this->error('评论发表失败');
        }
    }
    
    /**
     * 更新评论
     * PUT/PATCH /api.php/v1/comments/{id}
     * 
     * 请求体：
     * - content: 评论内容（必填）
     */
    public function update($id = 0) {
        $this->requireLogin();
        
        if (is_array($id)) {
            $commentId = isset($id['id']) ? intval($id['id']) : 0;
        } else {
            $commentId = intval($id);
        }
        $content = $this->getStringParam('content');
        
        if ($commentId <= 0) {
            $this->error('评论ID无效', 400);
            return;
        }
        
        if (empty($content)) {
            $this->error('评论内容不能为空', 400);
            return;
        }
        
        $commentModel = new CommentModel();
        $comment = $commentModel->getCommentById($commentId);
        
        if (!$comment) {
            $this->error('评论不存在', 404);
            return;
        }
        
        if ($comment['user_id'] != $this->currentUser['id']) {
            $this->error('无权修改此评论', 403);
            return;
        }
        
        $commentData = [
            'content' => $content
        ];
        
        $result = $commentModel->updateComment($commentId, $commentData);
        
        if ($result) {
            $this->success(null, '评论更新成功');
        } else {
            $this->error('评论更新失败');
        }
    }
    
    /**
     * 删除评论
     * DELETE /api.php/v1/comments/{id}
     */
    public function destroy($id = 0) {
        $this->requireLogin();
        
        if (is_array($id)) {
            $commentId = isset($id['id']) ? intval($id['id']) : 0;
        } else {
            $commentId = intval($id);
        }
        
        if ($commentId <= 0) {
            $this->error('评论ID无效', 400);
            return;
        }
        
        $commentModel = new CommentModel();
        $comment = $commentModel->getCommentById($commentId);
        
        if (!$comment) {
            $this->error('评论不存在', 404);
            return;
        }
        
        if ($comment['user_id'] != $this->currentUser['id']) {
            $this->error('无权删除此评论', 403);
            return;
        }
        
        $result = $commentModel->deleteComment($commentId);
        
        if ($result) {
            $this->success(null, '评论删除成功');
        } else {
            $this->error('评论删除失败');
        }
    }
    
    /**
     * 点赞评论
     * POST /api.php/v1/comments/{id}/like
     */
    public function like($id = 0) {
        $this->requireLogin();
        
        if (is_array($id)) {
            $commentId = isset($id['id']) ? intval($id['id']) : 0;
        } else {
            $commentId = intval($id);
        }
        
        if ($commentId <= 0) {
            $this->error('评论ID无效', 400);
            return;
        }
        
        
        $commentModel = new CommentModel();
        $comment = $commentModel->getCommentById($commentId);
        
        if (!$comment) {
            $this->error('评论不存在', 404);
            return;
        }
        
        $likeModel = new LikeModel();
        $result = $likeModel->addLike($this->currentUser['id'], $commentId, 'comment');
        
        if ($result) {
            $likeCount = $likeModel->getLikeCount($commentId, 'comment');
            $this->success(['like_count' => $likeCount], '点赞成功');
        } else {
            $this->error('点赞失败');
        }
    }
    
    /**
     * 取消点赞评论
     * POST /api.php/v1/comments/{id}/unlike
     */
    public function unlike($id = 0) {
        $this->requireLogin();
        
        if (is_array($id)) {
            $commentId = isset($id['id']) ? intval($id['id']) : 0;
        } else {
            $commentId = intval($id);
        }
        
        if ($commentId <= 0) {
            $this->error('评论ID无效', 400);
            return;
        }
        
        
        $commentModel = new CommentModel();
        $comment = $commentModel->getCommentById($commentId);
        
        if (!$comment) {
            $this->error('评论不存在', 404);
            return;
        }
        
        $likeModel = new LikeModel();
        $result = $likeModel->removeLike($this->currentUser['id'], $commentId, 'comment');
        
        if ($result) {
            $likeCount = $likeModel->getLikeCount($commentId, 'comment');
            $this->success(['like_count' => $likeCount], '取消点赞成功');
        } else {
            $this->error('取消点赞失败');
        }
    }
}
