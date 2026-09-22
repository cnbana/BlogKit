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
 * 通知服务层
 * 
 * 封装用户通知的创建、分发、聚合等核心业务逻辑，
 * 供各类事件（评论、关注、文章审核等）调用。
 * 
 * 职责：
 *   - 创建通知（支持多种类型：评论、关注、系统、文章）
 *   - 批量通知分发
 *   - 未读计数聚合
 *   - 通知标记已读/全部已读
 *   - 通知清理
 */
class NotificationService
{
    /** 通知类型常量 */
    const TYPE_COMMENT       = 'comments';
    const TYPE_FOLLOW        = 'follows';
    const TYPE_SYSTEM        = 'system';
    const TYPE_ARTICLE       = 'articles';
    const TYPE_ARTICLE_LIKE  = 'article_likes';
    const TYPE_COMMENT_LIKE  = 'comment_likes';
    const TYPE_FAVORITE      = 'favorites';

    /**
     * 创建一条通知
     *
     * @param int    $userId  接收用户ID
     * @param string $type    通知类型
     * @param string $title   标题
     * @param string $content 内容
     * @param array  $meta    附加元数据（如 link, source_id 等）
     * @return int|false      通知ID 或 false
     */
    public static function create($userId, $type, $title, $content, array $meta = [])
    {
        $notificationModel = new NotificationModel();

        return $notificationModel->createNotification([
            'user_id'    => $userId,
            'type'       => $type,
            'title'      => $title,
            'content'    => $content,
            'url'        => $meta['link'] ?? '',
        ]);
    }

    /**
     * 当有新评论时通知文章作者
     *
     * @param int    $articleId
     * @param int    $commenterId  评论者ID
     * @param string $commentContent 评论内容（截取前100字）
     * @return void
     */
    public static function notifyArticleAuthor($articleId, $commenterId, $commentContent)
    {
        $articleModel = new ArticleModel();
        $article = $articleModel->getArticleById($articleId);

        if (!$article || $article['user_id'] == $commenterId) {
            return; // 作者自己评论自己，不通知
        }

        // 使用 UserModel 的静态方法获取用户信息
        $commenter = UserModel::getUserById($commenterId);

        $commenterName = $commenter['nickname'] ?? $commenter['username'] ?? '匿名';
        $excerpt = mb_strlen($commentContent) > 100
            ? mb_substr($commentContent, 0, 100) . '...'
            : $commentContent;

        $link = "/index.php/article/{$articleId}#comments";

        self::create(
            $article['user_id'],
            self::TYPE_COMMENT,
            "{$commenterName} 评论了你的文章",
            $excerpt,
            ['link' => $link, 'article_id' => $articleId, 'commenter_id' => $commenterId]
        );
    }

    /**
     * 当有人回复评论时通知原评论作者
     *
     * @param int    $parentCommentId 被回复的评论ID
     * @param int    $replyerId      回复者ID
     * @param string $replyContent   回复内容
     * @return void
     */
    public static function notifyCommentReply($parentCommentId, $replyerId, $replyContent)
    {
        $commentModel = new CommentModel();
        $parentComment = $commentModel->getCommentById($parentCommentId);

        if (!$parentComment || $parentComment['user_id'] == $replyerId) {
            return;
        }

        // 使用 UserModel 的静态方法获取用户信息
        $replyer = UserModel::getUserById($replyerId);
        $replyerName = $replyer['nickname'] ?? $replyer['username'] ?? '匿名';

        $excerpt = mb_strlen($replyContent) > 100
            ? mb_substr($replyContent, 0, 100) . '...'
            : $replyContent;

        $link = "/index.php/article/{$parentComment['article_id']}#comment-{$parentCommentId}";

        self::create(
            $parentComment['user_id'],
            self::TYPE_COMMENT,
            "{$replyerName} 回复了你的评论",
            $excerpt,
            ['link' => $link, 'comment_id' => $parentCommentId, 'replyer_id' => $replyerId]
        );
    }

    /**
     * 当有新关注时通知被关注者
     *
     * @param int $followerId  关注者ID
     * @param int $followingId 被关注者ID
     * @return void
     */
    public static function notifyNewFollower($followerId, $followingId)
    {
        // 使用 UserModel 的静态方法获取用户信息
        $follower = UserModel::getUserById($followerId);
        $followerName = $follower['nickname'] ?? $follower['username'] ?? '匿名';

        $link = "/index.php/profile/{$followerId}";

        self::create(
            $followingId,
            self::TYPE_FOLLOW,
            "{$followerName} 关注了你",
            '',
            ['link' => $link, 'follower_id' => $followerId]
        );
    }

    /**
     * 当文章审核状态变更时通知作者
     *
     * @param int    $articleId
     * @param string $newStatus 新状态（approved/rejected）
     * @param string $reason    原因（可选）
     * @return void
     */
    public static function notifyArticleStatusChange($articleId, $newStatus, $reason = '')
    {
        $articleModel = new ArticleModel();
        $article = $articleModel->getArticleById($articleId);

        if (!$article) return;

        $statusLabels = [
            'approved' => '已通过审核',
            'rejected' => '未通过审核',
        ];
        $label = $statusLabels[$newStatus] ?? $newStatus;

        $content = $reason ?: "你的文章《{$article['title']}》{$label}。";
        $link = "/index.php/article/{$articleId}";

        self::create(
            $article['user_id'],
            self::TYPE_ARTICLE,
            "文章审核结果",
            $content,
            ['link' => $link, 'article_id' => $articleId, 'status' => $newStatus]
        );
    }

    /**
     * 发送系统通知
     *
     * @param int    $userId  接收用户（0 = 全体用户）
     * @param string $title
     * @param string $content
     * @return void
     */
    public static function sendSystemNotification($userId, $title, $content)
    {
        self::create($userId, self::TYPE_SYSTEM, $title, $content);
    }

    /**
     * 当文章被点赞时通知文章作者
     *
     * @param int    $articleId 文章ID
     * @param int    $likerId   点赞者ID
     * @return void
     */
    public static function notifyArticleLike($articleId, $likerId)
    {
        $articleModel = new ArticleModel();
        $article = $articleModel->getArticleById($articleId);

        if (!$article || $article['user_id'] == $likerId) {
            return;
        }

        // 使用 UserModel 的静态方法获取用户信息
        $liker = UserModel::getUserById($likerId);
        $likerName = $liker['nickname'] ?? $liker['username'] ?? '匿名';

        $link = "/index.php/article/{$articleId}";

        self::create(
            $article['user_id'],
            self::TYPE_ARTICLE_LIKE,
            "{$likerName} 点赞了你的文章",
            "你的文章《{$article['title']}》收到了点赞",
            ['link' => $link, 'article_id' => $articleId, 'liker_id' => $likerId]
        );
    }

    /**
     * 当评论被点赞时通知评论作者
     *
     * @param int    $commentId 评论ID
     * @param int    $likerId   点赞者ID
     * @return void
     */
    public static function notifyCommentLike($commentId, $likerId)
    {
        $commentModel = new CommentModel();
        $comment = $commentModel->getCommentById($commentId);

        if (!$comment || $comment['user_id'] == $likerId) {
            return;
        }

        // 使用 UserModel 的静态方法获取用户信息
        $liker = UserModel::getUserById($likerId);
        $likerName = $liker['nickname'] ?? $liker['username'] ?? '匿名';

        $articleModel = new ArticleModel();
        $article = $articleModel->getArticleById($comment['article_id']);
        $articleTitle = $article['title'] ?? '未知文章';

        $link = "/index.php/article/{$comment['article_id']}#comment-{$commentId}";

        self::create(
            $comment['user_id'],
            self::TYPE_COMMENT_LIKE,
            "{$likerName} 点赞了你的评论",
            "你在文章《{$articleTitle}》中的评论收到了点赞",
            ['link' => $link, 'comment_id' => $commentId, 'liker_id' => $likerId]
        );
    }

    /**
     * 当文章被收藏时通知文章作者
     *
     * @param int    $articleId 文章ID
     * @param int    $favoriterId 收藏者ID
     * @return void
     */
    public static function notifyArticleFavorite($articleId, $favoriterId)
    {
        $articleModel = new ArticleModel();
        $article = $articleModel->getArticleById($articleId);

        if (!$article || $article['user_id'] == $favoriterId) {
            return;
        }

        // 使用 UserModel 的静态方法获取用户信息
        $favoriter = UserModel::getUserById($favoriterId);
        $favoriterName = $favoriter['nickname'] ?? $favoriter['username'] ?? '匿名';

        $link = "/index.php/article/{$articleId}";

        self::create(
            $article['user_id'],
            self::TYPE_FAVORITE,
            "{$favoriterName} 收藏了你的文章",
            "你的文章《{$article['title']}》被收藏了",
            ['link' => $link, 'article_id' => $articleId, 'favoriter_id' => $favoriterId]
        );
    }

    /**
     * 获取用户未读通知数量（带缓存）
     *
     * @param int $userId
     * @return int
     */
    public static function getUnreadCount($userId)
    {
        $cacheKey = "notification_unread_{$userId}";
        $cached = Cache::getCache($cacheKey);

        if ($cached !== null) {
            return (int)$cached;
        }

        $notificationModel = new NotificationModel();
        $count = $notificationModel->getUnreadCount($userId);

        Cache::setCache($cacheKey, $count, 120); // 缓存 2 分钟

        return $count;
    }

    /**
     * 标记单条通知为已读
     *
     * @param int $notificationId
     * @param int $userId
     * @return bool
     */
    public static function markAsRead($notificationId, $userId)
    {
        $notificationModel = new NotificationModel();
        $result = $notificationModel->markAsRead($notificationId, $userId);

        if ($result) {
            Cache::deleteCache("notification_unread_{$userId}");
        }

        return $result;
    }

    /**
     * 标记全部通知为已读
     *
     * @param int $userId
     * @return int 标记数量
     */
    public static function markAllAsRead($userId)
    {
        $notificationModel = new NotificationModel();
        $count = $notificationModel->markAllAsRead($userId);

        Cache::deleteCache("notification_unread_{$userId}");

        return $count;
    }

    /**
     * 清理过期通知
     *
     * @param int $days 保留天数（默认 90 天）
     * @return int 清理数量
     */
    public static function cleanExpired($days = 90)
    {
        $notificationModel = new NotificationModel();
        return $notificationModel->deleteExpired($days);
    }
}
