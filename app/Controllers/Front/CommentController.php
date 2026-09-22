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


// 包含模型类

class CommentController {

    /**
     * 提交评论
     */
    public function submit() {
        try {
            // 检查是否登录
            if (!$this->isLogin()) {
                $this->jsonResponse(['code' => 403, 'msg' => '请先登录']);
                return;
            }

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->jsonResponse(['code' => 400, 'msg' => '请求方法错误']);
                return;
            }

            // 新用户限制检查
            if (AuthController::isNewUserRestricted() && Config::get('register_new_user_ban_comment', 0)) {
                $this->jsonResponse(['code' => 403, 'msg' => '新用户注册期间暂不允许评论，请稍后再试']);
                return;
            }

            // 检查评论功能是否启用（主开关，对主评论和回复评论统一生效）
            $commentEnabled = (int)Config::get('comment_enabled', 1);
            if ($commentEnabled !== 1) {
                $this->jsonResponse(['code' => 403, 'msg' => '评论功能已关闭']);
                return;
            }

            // 验证码验证
            
            $configModel = new ConfigModel();
            $captchaConfig = $configModel->getConfigByPrefix('captcha');
            
            // 检查评论页面是否启用验证码
            $captchaCommentEnabled = isset($captchaConfig['captcha_comment_enabled']) && $captchaConfig['captcha_comment_enabled'] == '1';
            $captchaEnabled = isset($captchaConfig['captcha_enabled']) && $captchaConfig['captcha_enabled'] == '1';
            
            if ($captchaEnabled && $captchaCommentEnabled) {
                $captchaCode = isset($_POST['captcha_code']) ? $_POST['captcha_code'] : '';
                if (empty($captchaCode)) {
                    $this->jsonResponse(['code' => 400, 'msg' => '请输入验证码']);
                    return;
                } elseif (!Captcha::check($captchaCode)) {
                    $this->jsonResponse(['code' => 400, 'msg' => '验证码错误']);
                    return;
                }
            }

            // 获取参数
            $articleId = isset($_POST['article_id']) ? intval($_POST['article_id']) : 0;
            $content = isset($_POST['content']) ? trim($_POST['content']) : '';
            $parentId = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;

            // 验证参数
            if (!$articleId || !$content) {
                $this->jsonResponse(['code' => 400, 'msg' => '参数错误']);
                return;
            }

            // 内容长度限制
            $commentMaxLength = (int)Config::get('comment_max_length', 1000);
            if (mb_strlen($content) > $commentMaxLength) {
                $this->jsonResponse(['code' => 400, 'msg' => '评论内容不能超过' . $commentMaxLength . '个字符']);
                return;
            }

            // 内容最小长度检查
            $commentMinLength = (int)Config::get('comment_min_length', 1);
            if (mb_strlen($content) < $commentMinLength) {
                $this->jsonResponse(['code' => 400, 'msg' => '评论内容不能为空']);
                return;
            }

            // 准备评论数据
            $commentData = [
                'article_id' => $articleId,
                'user_id' => $_SESSION['user']['id'],
                'content' => $content,
                'parent_id' => $parentId,
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT']
            ];

            // 反垃圾检测
            $commentModel = new CommentModel();
            $spamCheck = $commentModel->antiSpamCheck($commentData);
            if (!$spamCheck['pass']) {
                $this->jsonResponse(['code' => 400, 'msg' => $spamCheck['error']]);
                return;
            }

            // 保存评论
            $result = $commentModel->addComment($commentData);

            if ($result) {
                // 根据是否开启评论审核返回不同的成功消息
                $commentModeration = Config::get('comment_moderation', 0);
                $successMsg = $commentModeration ? '评论已提交，等待审核' : '评论提交成功';
                $this->jsonResponse(['code' => 200, 'msg' => $successMsg, 'is_pending' => $commentModeration]);
            } else {
                $this->jsonResponse(['code' => 500, 'msg' => '评论提交失败，请重试']);
            }
        } catch (Exception $e) {
            $this->jsonResponse(['code' => 500, 'msg' => '评论提交失败，请重试']);
        }
    }

    /**
     * 获取评论列表
     */
    public function getList() {
        try {
            $articleId = isset($_GET['article_id']) ? intval($_GET['article_id']) : 0;
            $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
            $sort = isset($_GET['sort']) && ($_GET['sort'] === 'hottest' || $_GET['sort'] === 'latest') ? $_GET['sort'] : 'latest';

            if (!$articleId) {
                $this->jsonResponse(['code' => 400, 'msg' => '参数错误']);
                return;
            }

            $commentModel = new CommentModel();
            
            // 获取当前登录用户ID
            $userId = $this->isLogin() ? $_SESSION['user']['id'] : 0;
            
            // 从配置读取每页评论数（默认10）
            $pageSize = (int)Config::get('pagination_article_comments_count', 10);
            
            $comments = $commentModel->getCommentsByArticleId($articleId, $page, $pageSize, $userId, $sort);
            
            $totalCount = $commentModel->getCommentCountByArticleId($articleId);
            $totalMainComments = $commentModel->getMainCommentCountByArticleId($articleId);

            $this->jsonResponse([
                'code' => 200,
                'data' => [
                    'comments' => $comments,
                    'totalCount' => $totalCount,
                    'totalMainComments' => $totalMainComments,
                    'pageSize' => $pageSize
                ]
            ]);
        } catch (Exception $e) {
            $this->jsonResponse(['code' => 500, 'msg' => '服务器内部错误']);
        }
    }

    /**
     * 点赞评论
     */
    public function like() {
        try {
            // 检查是否登录
            if (!$this->isLogin()) {
                $this->jsonResponse(['code' => 403, 'msg' => '请先登录']);
                return;
            }

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->jsonResponse(['code' => 400, 'msg' => '请求方法错误']);
                return;
            }

            // 获取参数
            $commentId = isset($_POST['comment_id']) ? intval($_POST['comment_id']) : 0;
            $action = isset($_POST['action']) ? $_POST['action'] : 'like';

            if (!$commentId) {
                $this->jsonResponse(['code' => 400, 'msg' => '参数错误']);
                return;
            }

            $commentModel = new CommentModel();
            
            // 根据action参数决定是点赞还是取消点赞
            if ($action === 'unlike') {
                $result = $commentModel->unlikeComment($commentId, $_SESSION['user']['id']);
                if ($result) {
                    $likeCount = $commentModel->getCommentLikeCount($commentId);
                    $this->jsonResponse(['code' => 200, 'msg' => '取消点赞成功', 'data' => ['like_count' => $likeCount]]);
                } else {
                    $this->jsonResponse(['code' => 500, 'msg' => '取消点赞失败，请重试']);
                }
                return;
            }

            // 点赞评论
            $commentModel = new CommentModel();
            $result = $commentModel->likeComment($commentId, $_SESSION['user']['id']);

            if ($result) {
                $likeCount = $commentModel->getCommentLikeCount($commentId);
                $this->jsonResponse(['code' => 200, 'msg' => '点赞成功', 'data' => ['like_count' => $likeCount]]);
            } else {
                $this->jsonResponse(['code' => 500, 'msg' => '点赞失败，请重试']);
            }
        } catch (Exception $e) {
            $this->jsonResponse(['code' => 500, 'msg' => '点赞失败，请重试']);
        }
    }

    /**
     * 取消点赞评论
     */
    public function unlike() {
        try {
            // 检查是否登录
            if (!$this->isLogin()) {
                $this->jsonResponse(['code' => 403, 'msg' => '请先登录']);
                return;
            }

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->jsonResponse(['code' => 400, 'msg' => '请求方法错误']);
                return;
            }

            // 获取参数
            $commentId = isset($_POST['comment_id']) ? intval($_POST['comment_id']) : 0;

            if (!$commentId) {
                $this->jsonResponse(['code' => 400, 'msg' => '参数错误']);
                return;
            }

            // 取消点赞
            $commentModel = new CommentModel();
            $result = $commentModel->unlikeComment($commentId, $_SESSION['user']['id']);

            if ($result) {
                $likeCount = $commentModel->getCommentLikeCount($commentId);
                $this->jsonResponse(['code' => 200, 'msg' => '取消点赞成功', 'data' => ['like_count' => $likeCount]]);
            } else {
                $this->jsonResponse(['code' => 500, 'msg' => '取消点赞失败，请重试']);
            }
        } catch (Exception $e) {
            $this->jsonResponse(['code' => 500, 'msg' => '取消点赞失败，请重试']);
        }
    }
    
    /**
     * 返回JSON响应
     */
    private function jsonResponse($data) {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    /**
     * 检查用户是否已登录
     */
    private function isLogin() {
        return isset($_SESSION['user']);
    }
    
    /**
     * 删除评论
     */
    public function delete() {
        try {
            // 检查是否登录
            if (!$this->isLogin()) {
                $this->jsonResponse(['code' => 403, 'msg' => '请先登录']);
                return;
            }

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->jsonResponse(['code' => 400, 'msg' => '请求方法错误']);
                return;
            }

            // 获取参数
            $commentId = isset($_POST['comment_id']) ? intval($_POST['comment_id']) : 0;

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

            // 检查权限：评论作者或文章作者或管理员可以删除
            $userId = $_SESSION['user']['id'];
            $articleModel = new ArticleModel();
            $article = $articleModel->getArticleById($comment['article_id']);
            
            $isAdmin = isset($_SESSION['admin']) || isset($_SESSION['user']['is_admin']);
            $isCommentAuthor = $comment['user_id'] == $userId;
            $isArticleAuthor = $article && $article['user_id'] == $userId;
            
            if (!$isAdmin && !$isCommentAuthor && !$isArticleAuthor) {
                $this->jsonResponse(['code' => 403, 'msg' => '无权限删除此评论']);
                return;
            }

            // 删除评论（管理员和文章作者可以删除，评论作者只能删除自己的未审核评论）
            if ($isCommentAuthor && $comment['status'] == 0) {
                $result = $commentModel->deleteComment($commentId);
            } elseif ($isAdmin || $isArticleAuthor) {
                $result = $commentModel->deleteComment($commentId);
            } else {
                $this->jsonResponse(['code' => 403, 'msg' => '无权限删除此评论']);
                return;
            }

            if ($result) {
                $this->jsonResponse(['code' => 200, 'msg' => '删除成功']);
            } else {
                $this->jsonResponse(['code' => 500, 'msg' => '删除失败，请重试']);
            }
        } catch (Exception $e) {
            $this->jsonResponse(['code' => 500, 'msg' => '删除失败，请重试']);
        }
    }
    
    /**
     * 置顶/取消置顶评论
     */
    public function top() {
        try {
            // 检查是否登录
            if (!$this->isLogin()) {
                $this->jsonResponse(['code' => 403, 'msg' => '请先登录']);
                return;
            }

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->jsonResponse(['code' => 400, 'msg' => '请求方法错误']);
                return;
            }

            // 获取参数
            $commentId = isset($_POST['comment_id']) ? intval($_POST['comment_id']) : 0;

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

            // 检查权限：只有文章作者可以置顶评论
            $userId = $_SESSION['user']['id'];
            $articleModel = new ArticleModel();
            $article = $articleModel->getArticleById($comment['article_id']);
            
            $isAdmin = isset($_SESSION['admin']) || isset($_SESSION['user']['is_admin']);
            $isArticleAuthor = $article && $article['user_id'] == $userId;
            
            if (!$isAdmin && !$isArticleAuthor) {
                $this->jsonResponse(['code' => 403, 'msg' => '无权限置顶此评论']);
                return;
            }

            // 切换置顶状态
            $newTopStatus = $comment['is_top'] ? 0 : 1;
            $result = $commentModel->updateComment($commentId, ['is_top' => $newTopStatus]);

            if ($result) {
                $msg = $newTopStatus ? '置顶成功' : '取消置顶成功';
                $this->jsonResponse(['code' => 200, 'msg' => $msg]);
            } else {
                $this->jsonResponse(['code' => 500, 'msg' => '操作失败，请重试']);
            }
        } catch (Exception $e) {
            $this->jsonResponse(['code' => 500, 'msg' => '操作失败，请重试']);
        }
    }
    
    /**
     * 获取评论回复
     */
    public function getReplies() {
        try {
            $commentId = isset($_GET['comment_id']) ? intval($_GET['comment_id']) : 0;
            $start = isset($_GET['start']) ? intval($_GET['start']) : 0;
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 3;

            if (!$commentId) {
                $this->jsonResponse(['code' => 400, 'msg' => '参数错误']);
                return;
            }

            $commentModel = new CommentModel();
            $userId = $this->isLogin() ? $_SESSION['user']['id'] : 0;
            $replies = $commentModel->getRepliesByCommentId($commentId, $start, $limit, $userId);

            $this->jsonResponse([
                'code' => 200,
                'data' => [
                    'replies' => $replies
                ]
            ]);
        } catch (Exception $e) {
            $this->jsonResponse(['code' => 500, 'msg' => '服务器内部错误']);
        }
    }
}
