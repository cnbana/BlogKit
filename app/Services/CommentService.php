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
 * 评论服务层
 * 
 * 封装评论发布、审核、反垃圾等核心业务逻辑，
 * 供 Front/CommentController、Admin/CommentController 等调用。
 * 
 * 职责：
 *   - 评论提交流程（验证、审核、通知）
 *   - 反垃圾检测（频率限制、内容重复、敏感词）
 *   - 评论审核管理
 *   - 评论互动（点赞、回复）
 */
class CommentService
{
    /**
     * 提交一条评论（完整业务流程）
     *
     * @param array $data [article_id, content, parent_id]
     * @param array $user 当前登录用户信息 [id, username, ...]
     * @return array ['success' => bool, 'code' => int, 'msg' => string, 'comment_id' => int|null]
     */
    public static function submitComment(array $data, array $user)
    {
        $articleId = (int)($data['article_id'] ?? 0);
        $content   = trim($data['content'] ?? '');
        $parentId  = (int)($data['parent_id'] ?? 0);

        // --- 1. 基本校验 ---
        if (!$articleId || $content === '') {
            return ['success' => false, 'code' => 400, 'msg' => '参数错误', 'comment_id' => null];
        }

        // 内容长度
        $maxLen = (int)Config::get('comment_max_length', 1000);
        $minLen = (int)Config::get('comment_min_length', 1);
        $len = mb_strlen($content);
        if ($len < $minLen || $len > $maxLen) {
            return [
                'success'    => false,
                'code'       => 400,
                'msg'        => $len > $maxLen
                    ? "评论内容不能超过 {$maxLen} 个字符"
                    : '评论内容不能为空',
                'comment_id' => null,
            ];
        }

        // --- 2. 准备评论数据 ---
        $commentData = [
            'article_id' => $articleId,
            'user_id'    => $user['id'],
            'content'    => Security::filterXss($content),
            'parent_id'  => $parentId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ];

        // --- 3. 反垃圾检测 ---
        $commentModel = new CommentModel();
        $spamCheck = $commentModel->antiSpamCheck($commentData);
        if (!$spamCheck['pass']) {
            return ['success' => false, 'code' => 400, 'msg' => $spamCheck['error'], 'comment_id' => null];
        }

        // --- 4. 审核策略 ---
        $autoApprove = self::shouldAutoApprove($user);
        $commentData['status'] = $autoApprove ? 1 : 0;

        // --- 5. 写入数据库 ---
        $commentId = $commentModel->addComment($commentData);

        if (!$commentId) {
            return ['success' => false, 'code' => 500, 'msg' => '评论提交失败', 'comment_id' => null];
        }

        // --- 6. 发送通知 ---
        self::notifyAfterComment($commentId, $commentData, $autoApprove);

        // --- 7. 返回结果 ---
        $msg = $autoApprove ? '评论成功' : '评论已提交，等待审核';

        return [
            'success'    => true,
            'code'       => 200,
            'msg'        => $msg,
            'comment_id' => $commentId,
        ];
    }

    /**
     * 判断用户评论是否需要审核
     *
     * @param array $user
     * @return bool true = 免审核, false = 需审核
     */
    private static function shouldAutoApprove(array $user)
    {
        // 管理员免审核
        if (isset($user['role']) && in_array($user['role'], ['admin', 'editor'])) {
            return true;
        }

        // 检查全局审核开关
        $commentModeration = Config::get('comment_moderation', 0);
        if ($commentModeration) {
            return false;
        }

        // 已知用户（有通过审核的历史评论）免审核
        $commentModel = new CommentModel();
        $approvedCount = $commentModel->getUserApprovedCount($user['id']);
        if ($approvedCount > 0) {
            return true;
        }

        return true; // 默认免审核，由全局开关控制
    }

    /**
     * 评论后的通知触发
     */
    private static function notifyAfterComment($commentId, array $commentData, $autoApprove)
    {
        // 触发钩子，由 NotificationService 或插件处理
        if (class_exists('Hook')) {
            Hook::trigger('comment_posted', [
                'comment_id'   => $commentId,
                'article_id'   => $commentData['article_id'],
                'user_id'      => $commentData['user_id'],
                'parent_id'    => $commentData['parent_id'],
                'auto_approve' => $autoApprove,
            ]);
        }
    }

    /**
     * 管理员审核评论
     *
     * @param int  $commentId
     * @param bool $approve true=通过, false=拒绝
     * @return bool
     */
    public static function moderateComment($commentId, $approve = true)
    {
        $commentModel = new CommentModel();
        $status = $approve ? 1 : 2; // 1=通过, 2=拒绝
        return $commentModel->updateStatus($commentId, $status);
    }

    /**
     * 批量审核评论
     *
     * @param array $commentIds
     * @param bool  $approve
     * @return int 成功数
     */
    public static function batchModerate(array $commentIds, $approve = true)
    {
        $commentModel = new CommentModel();
        $count = 0;
        foreach ($commentIds as $id) {
            if ($commentModel->updateStatus((int)$id, $approve ? 1 : 2)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * 获取评论列表（带分页和用户交互状态）
     *
     * @param int      $articleId
     * @param int      $page
     * @param int      $perPage
     * @param int|null $currentUserId 当前用户ID（用于判断点赞状态）
     * @return array ['comments' => [...], 'total' => int, 'page' => int]
     */
    public static function getCommentList($articleId, $page = 1, $perPage = 20, $currentUserId = null)
    {
        $commentModel = new CommentModel();
        $comments = $commentModel->getCommentsByArticleId($articleId, $page, $perPage);

        // 批量获取评论点赞状态
        if ($currentUserId && !empty($comments)) {
            $commentIds = array_column($comments, 'id');
            $likedIds = $commentModel->getUserLikedCommentIds($currentUserId, $commentIds);
            foreach ($comments as &$comment) {
                $comment['is_liked'] = in_array($comment['id'], $likedIds);
            }
        }

        $total = $commentModel->getCommentCountByArticleId($articleId);

        return [
            'comments' => $comments,
            'total'    => $total,
            'page'     => $page,
        ];
    }

    /**
     * 切换评论点赞状态
     *
     * @param int $commentId
     * @param int $userId
     * @return array ['liked' => bool, 'count' => int]
     */
    public static function toggleCommentLike($commentId, $userId)
    {
        $commentModel = new CommentModel();
        $liked = $commentModel->toggleCommentLike($commentId, $userId);
        $count = $commentModel->getCommentLikeCount($commentId);

        return ['liked' => $liked, 'count' => $count];
    }
}
