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


if (!class_exists('Database')) {
    require CORE_PATH . '/lib/Database.php';
}

if (!class_exists('Config')) {
    require CORE_PATH . '/lib/Config.php';
}

if (!class_exists('Hook')) {
    require CORE_PATH . '/lib/Hook.php';
}

class CommentModel {
    private $db;
    private $table = 'comments';

    public function __construct() {
        $this->db = Database::getInstance();
        // 自动创建comment_like表（如果不存在）
        $this->createCommentLikeTable();
    }
    
    /**
     * 自动创建comment_like表
     */
    private function createCommentLikeTable() {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS " . $this->db->table('comment_like') . " (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                comment_id bigint(20) NOT NULL COMMENT '评论ID',
                user_id bigint(20) NOT NULL COMMENT '用户ID',
                created_at INT(11) NOT NULL COMMENT '点赞时间',
                PRIMARY KEY (id),
                UNIQUE KEY unique_comment_user (comment_id, user_id),
                KEY comment_id (comment_id),
                KEY user_id (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='评论点赞表';";
            $this->db->query($sql);
        } catch (Exception $e) {
            // 忽略创建表的错误
            error_log('Failed to create comment_like table: ' . $e->getMessage());
        }
    }

    /**
     * 评论反垃圾检测
     * @param array $commentData 评论数据
     * @return array ['pass' => bool, 'error' => string]
     */
    public function antiSpamCheck($commentData) {
        $userId = $commentData['user_id'];
        $content = $commentData['content'];
        $ip = $commentData['ip_address'] ?? '';
        
        // 1. 频率限制检查
        $rateLimitEnabled = Config::get('comment_rate_limit_enabled', 1);
        if ($rateLimitEnabled) {
            $rateLimitSeconds = (int)Config::get('comment_rate_limit_seconds', 10);
            $lastComment = $this->db->fetch(
                "SELECT created_at FROM {$this->db->table($this->table)} WHERE user_id = ? ORDER BY created_at DESC LIMIT 1",
                [$userId]
            );
            if ($lastComment && (time() - $lastComment['created_at']) < $rateLimitSeconds) {
                $remaining = $rateLimitSeconds - (time() - $lastComment['created_at']);
                return ['pass' => false, 'error' => "评论太频繁了，请 {$remaining} 秒后再试"];
            }
        }
        
        // 2. 内容重复检测
        $dedupEnabled = Config::get('comment_dedup_enabled', 1);
        if ($dedupEnabled) {
            $contentMd5 = md5(trim($content));
            $duplicate = $this->db->fetch(
                "SELECT id FROM {$this->db->table($this->table)} WHERE user_id = ? AND MD5(content) = ? AND created_at > ? LIMIT 1",
                [$userId, $contentMd5, time() - 3600]
            );
            if ($duplicate) {
                return ['pass' => false, 'error' => '检测到重复内容，请勿重复提交相同评论'];
            }
        }
        
        // 3. 关键词过滤
        $keywordFilterEnabled = Config::get('comment_keyword_filter_enabled', 1);
        if ($keywordFilterEnabled) {
            $blockedKeywords = Config::get('comment_blocked_keywords', '');
            if (!empty($blockedKeywords)) {
                $keywords = explode(',', $blockedKeywords);
                foreach ($keywords as $kw) {
                    $kw = trim($kw);
                    if (!empty($kw) && mb_stripos($content, $kw) !== false) {
                        return ['pass' => false, 'error' => '评论包含违规关键词，无法提交'];
                    }
                }
            }
        }
        
        // 4. 链接检测
        $linkCheckEnabled = Config::get('comment_link_check_enabled', 1);
        if ($linkCheckEnabled) {
            $linkAction = Config::get('comment_link_action', 'moderate');
            $hasLink = preg_match('/https?:\/\/\S+|www\.\S+/i', $content) || 
                       preg_match('/[a-zA-Z0-9][-a-zA-Z0-9]*\.[a-zA-Z]{2,}/', $content);
            if ($hasLink && $linkAction === 'block') {
                return ['pass' => false, 'error' => '评论中包含链接，无法提交'];
            }
        }
        
        // 5. IP 频率限制
        $ipLimitEnabled = Config::get('comment_ip_rate_limit_enabled', 0);
        if ($ipLimitEnabled && !empty($ip)) {
            $ipLimitCount = (int)Config::get('comment_ip_rate_limit_count', 20);
            $ipLimitWindow = (int)Config::get('comment_ip_rate_limit_window', 3600);
            $ipCount = $this->db->fetch(
                "SELECT COUNT(*) as cnt FROM {$this->db->table($this->table)} WHERE ip_address = ? AND created_at > ?",
                [$ip, time() - $ipLimitWindow]
            );
            if ($ipCount && $ipCount['cnt'] >= $ipLimitCount) {
                return ['pass' => false, 'error' => '评论过于频繁，请稍后再试'];
            }
        }
        
        return ['pass' => true, 'error' => ''];
    }
    
    /**
     * 检查评论是否包含链接（用于审核模式）
     * @param string $content 评论内容
     * @return bool
     */
    public function hasLink($content) {
        return (bool)preg_match('/https?:\/\/\S+|www\.\S+/i', $content);
    }
    
    /**
     * 添加评论
     * @param array $commentData 评论数据
     * @return bool 添加结果
     */
    public function addComment($commentData) {
        try {
            // 触发评论添加前钩子
            $isReply = isset($commentData['parent_id']) && $commentData['parent_id'] > 0;
            if ($isReply) {
                Hook::trigger(Hook::COMMENT_REPLY_BEFORE, $commentData);
            } else {
                Hook::trigger(Hook::COMMENT_ADD_BEFORE, $commentData);
            }
            
            // 检查是否开启评论审核
            $commentModeration = Config::get('comment_moderation', 0); // 0-关闭，1-开启
            // 如果开启审核，默认状态为待审核(0)；否则为审核通过(1)
            $defaultStatus = $commentModeration ? 0 : 1;
            
            $data = [
                'article_id' => $commentData['article_id'],
                'user_id' => $commentData['user_id'],
                'content' => $commentData['content'],
                'status' => $defaultStatus,
                'created_at' => time()
            ];

            // 添加回复支持
            if (isset($commentData['parent_id']) && $commentData['parent_id'] > 0) {
                $data['parent_id'] = $commentData['parent_id'];
            } else {
                $data['parent_id'] = 0;
            }

            // 添加IP地址和浏览器信息
            if (isset($commentData['ip_address'])) {
                $data['ip_address'] = $commentData['ip_address'];
            }

            if (isset($commentData['user_agent'])) {
                $data['user_agent'] = $commentData['user_agent'];
            }
            
            $result = $this->db->insert($this->table, $data);
            
            if ($result) {
                // 获取完整的评论数据，包括ID
                $comment = $this->db->fetch("SELECT * FROM {$this->db->table($this->table)} WHERE id = ?", [$result]);
                
                // 触发评论添加后钩子
                if ($isReply) {
                    Hook::trigger(Hook::COMMENT_REPLY_AFTER, $comment);
                } else {
                    Hook::trigger(Hook::COMMENT_ADD_AFTER, $comment);
                }
                
                // 直接发送通知，不通过插件
                require_once APP_PATH . '/Models/NotificationModel.php';
                $notificationModel = new NotificationModel();
                
                // 获取文章信息
                $article = $this->db->fetch("SELECT * FROM {$this->db->table('article')} WHERE id = ?", [$comment['article_id']]);
                if ($article) {
                    // 如果评论者不是文章作者，则发送通知给文章作者
                    if ($article['user_id'] != $comment['user_id']) {
                        $notificationModel->createNotification([
                            'user_id' => $article['user_id'],
                            'type' => 'comments',
                            'title' => '新评论通知',
                            'content' => "您的文章《{$article['title']}》收到了新评论",
                            'is_read' => 0,
                            'created_at' => time(),
                            'read_at' => 0
                        ]);
                    }
                    
                    // 如果是回复评论，发送通知给被回复者
                    if ($isReply) {
                        $parentComment = $this->db->fetch("SELECT * FROM {$this->db->table($this->table)} WHERE id = ?", [$comment['parent_id']]);
                        if ($parentComment && $parentComment['user_id'] != $comment['user_id'] && $parentComment['user_id'] != $article['user_id']) {
                            $notificationModel->createNotification([
                                'user_id' => $parentComment['user_id'],
                                'type' => 'comments',
                                'title' => '评论回复通知',
                                'content' => "您在文章《{$article['title']}》的评论收到了回复",
                                'is_read' => 0,
                                'created_at' => time(),
                                'read_at' => 0
                            ]);
                        }
                    }
                }
            }
            
            return $result;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 获取文章的评论列表（优化版：一次性查询全部评论，PHP内存建树）
     * 解决原 N+1 递归查询问题：从 ~80次DB查询降至 1次
     * 
     * @param int $articleId 文章ID
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @param int $userId 用户ID
     * @param string $sort 排序方式: latest(最新), hottest(最热)
     * @return array 评论列表
     */
    public function getCommentsByArticleId($articleId, $page = 1, $pageSize = 10, $userId = 0, $sort = 'latest') {
        // 获取文章作者ID
        $articleSql = "SELECT user_id as article_author_id FROM " . $this->db->table('article') . " WHERE id = ?";
        $article = $this->db->fetch($articleSql, [$articleId]);
        $articleAuthorId = $article['article_author_id'] ?? 0;
        
        // 一次性获取文章的全部评论（含嵌套回复）
        $allComments = $this->fetchAllCommentsForArticle($articleId, $userId, $articleAuthorId);
        
        // 按 parent_id 分组
        $byParent = [];
        foreach ($allComments as $comment) {
            $pid = $comment['parent_id'] ?? 0;
            $byParent[$pid][] = $comment;
        }
        
        // 构建顶层评论树
        $tree = [];
        if (isset($byParent[0])) {
            foreach ($byParent[0] as $comment) {
                $comment['replies'] = $this->buildRepliesRecursive($comment['id'], $byParent);
                $tree[] = $comment;
            }
        }
        
        // 排序：hottest 按点赞数降序，latest 按创建时间降序
        // 但置顶评论始终排在最前面
        if ($sort === 'hottest') {
            usort($tree, function($a, $b) {
                // 置顶优先级最高
                if (($a['is_top'] ?? 0) != ($b['is_top'] ?? 0)) {
                    return ($b['is_top'] ?? 0) - ($a['is_top'] ?? 0);
                }
                // 同为置顶或同不为置顶时，按点赞数排序
                return ($b['like_count'] ?? 0) - ($a['like_count'] ?? 0);
            });
        } else {
            // latest 或默认：置顶评论优先，非置顶评论按时间倒序
            usort($tree, function($a, $b) {
                // 置顶优先级最高
                if (($a['is_top'] ?? 0) != ($b['is_top'] ?? 0)) {
                    return ($b['is_top'] ?? 0) - ($a['is_top'] ?? 0);
                }
                // 同为置顶或同不为置顶时，按时间倒序（新评论在前）
                return ($b['created_at'] ?? 0) - ($a['created_at'] ?? 0);
            });
        }
        
        // 分页
        $offset = ($page - 1) * $pageSize;
        $paginated = array_slice($tree, $offset, $pageSize);
        
        return $paginated;
    }

    /**
     * 一次性获取文章的全部评论（含嵌套回复、用户信息、点赞状态、父评论信息）
     * 
     * @param int $articleId 文章ID
     * @param int $userId 当前用户ID（用于点赞状态和可见性判断）
     * @param int $articleAuthorId 文章作者ID（用于 is_author 标记）
     * @return array 评论列表（已格式化时间）
     */
    private function fetchAllCommentsForArticle($articleId, $userId, $articleAuthorId) {
        try {
            $sql = "SELECT c.*, u.username, u.nickname, u.avatar, 
                           COALESCE(cl.user_liked, 0) as user_liked,
                           CASE WHEN c.user_id = ? THEN 1 ELSE 0 END as is_author,
                           p_u.id as parent_user_id, p_u.username as parent_username, p_u.nickname as parent_nickname
                    FROM " . $this->db->table($this->table) . " c 
                    LEFT JOIN " . $this->db->table('user') . " u ON c.user_id = u.id 
                    LEFT JOIN (
                        SELECT comment_id, 1 as user_liked 
                        FROM " . $this->db->table('comment_like') . " 
                        WHERE user_id = ?
                    ) cl ON c.id = cl.comment_id
                    LEFT JOIN " . $this->db->table($this->table) . " p_c ON c.parent_id = p_c.id
                    LEFT JOIN " . $this->db->table('user') . " p_u ON p_c.user_id = p_u.id
                    WHERE c.article_id = ? AND c.is_deleted = 0 AND (c.status = 1 OR (c.status = 0 AND c.user_id = ?))
                    ORDER BY c.is_top DESC, c.created_at ASC";
            
            $result = $this->db->fetchAll($sql, [$articleAuthorId, $userId, $articleId, $userId]);
        } catch (Exception $e) {
            // comment_like 表不存在时回退（仅影响点赞状态，不影响评论树）
            $sql = "SELECT c.*, u.username, u.nickname, u.avatar, 
                           0 as user_liked,
                           CASE WHEN c.user_id = ? THEN 1 ELSE 0 END as is_author,
                           p_u.id as parent_user_id, p_u.username as parent_username, p_u.nickname as parent_nickname
                    FROM " . $this->db->table($this->table) . " c 
                    LEFT JOIN " . $this->db->table('user') . " u ON c.user_id = u.id 
                    LEFT JOIN " . $this->db->table($this->table) . " p_c ON c.parent_id = p_c.id
                    LEFT JOIN " . $this->db->table('user') . " p_u ON p_c.user_id = p_u.id
                    WHERE c.article_id = ? AND c.is_deleted = 0 AND (c.status = 1 OR (c.status = 0 AND c.user_id = ?))
                    ORDER BY c.is_top DESC, c.created_at ASC";
            
            $result = $this->db->fetchAll($sql, [$articleAuthorId, $articleId, $userId]);
        }
        
        // 格式化时间戳
        foreach ($result as &$comment) {
            $comment['created_at'] = date('Y-m-d H:i:s', $comment['created_at']);
            if (!empty($comment['updated_at'])) {
                $comment['updated_at'] = date('Y-m-d H:i:s', $comment['updated_at']);
            }
        }
        unset($comment);
        
        return $result;
    }

    /**
     * 递归构建回复树（PHP内存操作，无DB查询）
     * 
     * @param int $parentId 父评论ID
     * @param array $byParent 按 parent_id 分组的评论列表（引用传递性能优化）
     * @return array 子回复列表（含嵌套 replies）
     */
    private function buildRepliesRecursive($parentId, &$byParent) {
        $replies = [];
        if (isset($byParent[$parentId])) {
            foreach ($byParent[$parentId] as $reply) {
                $reply['replies'] = $this->buildRepliesRecursive($reply['id'], $byParent);
                $replies[] = $reply;
            }
        }
        return $replies;
    }

    /**
     * 获取评论的回复列表（优化版：通过文章级缓存一次性构建树后查找）
     * 
     * @param int $commentId 评论ID
     * @param int $start 开始位置
     * @param int $limit 数量限制
     * @param int $userId 用户ID
     * @return array 回复列表
     */
    public function getRepliesByCommentId($commentId, $start = 0, $limit = 3, $userId = 0) {
        // 获取该评论所属的文章ID
        $commentSql = "SELECT id, article_id FROM " . $this->db->table($this->table) . " WHERE id = ?";
        $comment = $this->db->fetch($commentSql, [$commentId]);
        if (!$comment) {
            return [];
        }
        
        $articleId = $comment['article_id'];
        $articleAuthorId = 0;
        
        // 获取文章作者ID
        $articleSql = "SELECT user_id as article_author_id FROM " . $this->db->table('article') . " WHERE id = ?";
        $article = $this->db->fetch($articleSql, [$articleId]);
        $articleAuthorId = $article['article_author_id'] ?? 0;
        
        // 一次性获取文章的全部评论
        $allComments = $this->fetchAllCommentsForArticle($articleId, $userId, $articleAuthorId);
        
        // 按 parent_id 分组
        $byParent = [];
        foreach ($allComments as $c) {
            $pid = $c['parent_id'] ?? 0;
            $byParent[$pid][] = $c;
        }
        
        // 查找目标评论的直接回复并递归构建子树
        $replies = $this->buildRepliesRecursive($commentId, $byParent);
        
        // 按范围返回回复
        return array_slice($replies, $start, $limit);
    }

    /**
     * 获取文章的评论总数（包括回复）
     * @param int $articleId 文章ID
     * @return int 评论总数
     */
    public function getCommentCountByArticleId($articleId) {
        $sql = "SELECT COUNT(*) as count FROM " . $this->db->table($this->table) . " WHERE article_id = ? AND status = 1 AND is_deleted = 0";
        $result = $this->db->fetchAll($sql, [$articleId]);
        return $result[0]['count'] ?? 0;
    }

    /**
     * 获取文章的主评论总数
     * @param int $articleId 文章ID
     * @return int 主评论总数
     */
    public function getMainCommentCountByArticleId($articleId) {
        $sql = "SELECT COUNT(*) as count FROM " . $this->db->table($this->table) . " WHERE article_id = ? AND status = 1 AND parent_id = 0 AND is_deleted = 0";
        $result = $this->db->fetchAll($sql, [$articleId]);
        return $result[0]['count'] ?? 0;
    }

    /**
     * 获取所有评论（后台管理用）
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @param array $filters 筛选条件
     * @return array 评论列表
     */
    public function getComments($page = 1, $pageSize = 10, $filters = []) {
        $offset = ($page - 1) * $pageSize;
        $whereClause = [];
        $params = [];
        
        // 默认只获取未删除的评论
        if (!isset($filters['is_deleted']) || $filters['is_deleted'] === '') {
            $whereClause[] = "c.is_deleted = 0";
        } else if ($filters['is_deleted'] !== 'all') {
            $whereClause[] = "c.is_deleted = ?";
            $params[] = $filters['is_deleted'];
        }
        
        if (isset($filters['status']) && $filters['status'] !== '') {
            $whereClause[] = "c.status = ?";
            $params[] = $filters['status'];
        }
        
        if (isset($filters['article_id']) && $filters['article_id']) {
            $whereClause[] = "c.article_id = ?";
            $params[] = $filters['article_id'];
        }
        
        if (isset($filters['user_id']) && $filters['user_id']) {
            $whereClause[] = "c.user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        if (isset($filters['search']) && !empty($filters['search'])) {
            $whereClause[] = "c.content LIKE ?";
            $params[] = "%" . $filters['search'] . "%";
        }
        
        $where = $whereClause ? "WHERE " . implode(" AND ", $whereClause) : "";
        
        $sql = "SELECT c.*, u.username, u.nickname, a.title as article_title
                FROM " . $this->db->table($this->table) . " c
                LEFT JOIN " . $this->db->table('user') . " u ON c.user_id = u.id
                LEFT JOIN " . $this->db->table('article') . " a ON c.article_id = a.id
                {$where}
                ORDER BY c.is_top DESC, c.created_at DESC
                LIMIT ?, ?";

        // 后台表头排序：白名单校验（防 SQL 注入），置顶评论仍优先展示
        $sortWhiteList = ['id', 'created_at'];
        $orderDir = (strtolower($order) === 'asc') ? 'ASC' : 'DESC';
        if ($sort !== '' && in_array($sort, $sortWhiteList)) {
            $sql = str_replace("ORDER BY c.is_top DESC, c.created_at DESC",
                "ORDER BY c.is_top DESC, c.{$sort} {$orderDir}, c.created_at DESC", $sql);
        }
        
        $params[] = $offset;
        $params[] = $pageSize;
        
        $result = $this->db->fetchAll($sql, $params);
        
        // 转换时间格式
        foreach ($result as &$comment) {
            $comment['created_at'] = date('Y-m-d H:i:s', $comment['created_at']);
        }
        
        return $result;
    }

    /**
     * 获取评论总数（后台管理用）
     * @param array $filters 筛选条件
     * @return int 评论总数
     */
    public function getCommentCount($filters = []) {
        $whereClause = [];
        $params = [];
        
        // 默认只获取未删除的评论
        if (!isset($filters['is_deleted']) || $filters['is_deleted'] === '') {
            $whereClause[] = "is_deleted = 0";
        } else if ($filters['is_deleted'] !== 'all') {
            $whereClause[] = "is_deleted = ?";
            $params[] = $filters['is_deleted'];
        }
        
        if (isset($filters['status']) && $filters['status'] !== '') {
            $whereClause[] = "status = ?";
            $params[] = $filters['status'];
        }
        
        if (isset($filters['article_id']) && $filters['article_id']) {
            $whereClause[] = "article_id = ?";
            $params[] = $filters['article_id'];
        }
        
        if (isset($filters['user_id']) && $filters['user_id']) {
            $whereClause[] = "user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        if (isset($filters['search']) && !empty($filters['search'])) {
            $whereClause[] = "content LIKE ?";
            $params[] = "%" . $filters['search'] . "%";
        }
        
        $where = $whereClause ? "WHERE " . implode(" AND ", $whereClause) : "";
        
        $sql = "SELECT COUNT(*) as count FROM " . $this->db->table($this->table) . " {$where}";
        $result = $this->db->fetchAll($sql, $params);
        return $result[0]['count'] ?? 0;
    }

    /**
     * 更新评论状态
     * @param int $commentId 评论ID
     * @param int $status 状态
     * @return bool 更新结果
     */
    public function updateCommentStatus($commentId, $status) {
        return $this->db->update($this->table, ['status' => $status], ['id' => $commentId]);
    }

    /**
     * 删除评论（软删除）
     * @param int $commentId 评论 ID
     * @return bool 删除结果
     */
    public function deleteComment($commentId) {
        try {
            // 先检查评论是否存在
            $comment = $this->getCommentById($commentId);
            if (!$comment) {
                return false;
            }
            
            // 如果已经是删除状态，直接返回成功
            if ($comment['is_deleted'] == 1) {
                return true;
            }
            
            // 先软删除回复
            $replyResult = $this->db->update($this->table, ['is_deleted' => 1], ['parent_id' => $commentId]);
            // 再软删除主评论
            $mainResult = $this->db->update($this->table, ['is_deleted' => 1], ['id' => $commentId]);
            
            // 只要主评论删除成功就返回 true
            return $mainResult > 0;
        } catch (Exception $e) {
            error_log('deleteComment error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 更新评论
     * @param int $commentId 评论ID
     * @param array $data 更新数据
     * @return bool 更新结果
     */
    public function updateComment($commentId, $data) {
        return $this->db->update($this->table, $data, ['id' => $commentId]);
    }

    /**
     * 批量更新评论状态
     * @param array $commentIds 评论ID数组
     * @param int $status 状态
     * @return bool 更新结果
     */
    public function batchUpdateStatus($commentIds, $status) {
        if (empty($commentIds)) {
            return false;
        }
        $placeholders = implode(',', array_fill(0, count($commentIds), '?'));
        $sql = "UPDATE " . $this->db->table($this->table) . " SET status = ? WHERE id IN ({$placeholders})";
        $params = array_merge([$status], $commentIds);
        return $this->db->query($sql, $params);
    }

    /**
     * 批量删除评论（软删除）
     * @param array $commentIds 评论ID数组
     * @return bool 删除结果
     */
    public function batchDelete($commentIds) {
        if (empty($commentIds)) {
            return false;
        }
        
        // 先软删除所有相关的回复
        $placeholders = implode(',', array_fill(0, count($commentIds), '?'));
        $sql = "UPDATE " . $this->db->table($this->table) . " SET is_deleted = 1 WHERE parent_id IN ({$placeholders})";
        $this->db->query($sql, $commentIds);
        
        // 再软删除主评论
        $sql = "UPDATE " . $this->db->table($this->table) . " SET is_deleted = 1 WHERE id IN ({$placeholders})";
        return $this->db->query($sql, $commentIds);
    }
    
    /**
     * 恢复评论
     * @param int $commentId 评论ID
     * @return bool 恢复结果
     */
    public function restoreComment($commentId) {
        // 先恢复回复
        $this->db->update($this->table, ['is_deleted' => 0], ['parent_id' => $commentId]);
        // 再恢复主评论
        return $this->db->update($this->table, ['is_deleted' => 0], ['id' => $commentId]);
    }
    
    /**
     * 批量恢复评论
     * @param array $commentIds 评论ID数组
     * @return bool 恢复结果
     */
    public function batchRestore($commentIds) {
        if (empty($commentIds)) {
            return false;
        }
        
        // 先恢复所有相关的回复
        $placeholders = implode(',', array_fill(0, count($commentIds), '?'));
        $sql = "UPDATE " . $this->db->table($this->table) . " SET is_deleted = 0 WHERE parent_id IN ({$placeholders})";
        $this->db->query($sql, $commentIds);
        
        // 再恢复主评论
        $sql = "UPDATE " . $this->db->table($this->table) . " SET is_deleted = 0 WHERE id IN ({$placeholders})";
        return $this->db->query($sql, $commentIds);
    }
    
    /**
     * 彻底删除评论
     * @param int $commentId 评论ID
     * @return bool 删除结果
     */
    public function forceDeleteComment($commentId) {
        // 先删除回复
        $this->db->delete($this->table, ['parent_id' => $commentId]);
        // 再删除主评论
        return $this->db->delete($this->table, ['id' => $commentId]);
    }
    
    /**
     * 批量彻底删除评论
     * @param array $commentIds 评论ID数组
     * @return bool 删除结果
     */
    public function batchForceDelete($commentIds) {
        if (empty($commentIds)) {
            return false;
        }
        
        // 先删除所有相关的回复
        $placeholders = implode(',', array_fill(0, count($commentIds), '?'));
        $sql = "DELETE FROM " . $this->db->table($this->table) . " WHERE parent_id IN ({$placeholders})";
        $this->db->query($sql, $commentIds);
        
        // 再删除主评论
        $sql = "DELETE FROM " . $this->db->table($this->table) . " WHERE id IN ({$placeholders})";
        return $this->db->query($sql, $commentIds);
    }

    /**
     * 根据ID获取评论
     * @param int $commentId 评论ID
     * @return array 评论信息
     */
    public function getCommentById($commentId) {
        $sql = "SELECT c.*, u.username, u.nickname, u.avatar, a.title as article_title 
                FROM " . $this->db->table($this->table) . " c 
                LEFT JOIN " . $this->db->table('user') . " u ON c.user_id = u.id 
                LEFT JOIN " . $this->db->table('article') . " a ON c.article_id = a.id 
                WHERE c.id = ?";
        return $this->db->fetch($sql, [$commentId]) ?? null;
    }

    /**
     * 点赞评论
     * @param int $commentId 评论ID
     * @param int $userId 用户ID
     * @return bool 点赞结果
     */
    public function likeComment($commentId, $userId) {
        // 检查是否已经点赞
        if ($this->isCommentLiked($commentId, $userId)) {
            return true; // 已经点赞，直接返回成功
        }

        // 开启事务
        $pdo = $this->db->getPdo();
        $pdo->beginTransaction();

        try {
            // 添加点赞记录
            $likeData = [
                'comment_id' => $commentId,
                'user_id' => $userId,
                'created_at' => time()
            ];
            $this->db->insert('comment_like', $likeData);

            // 更新评论点赞数
            $this->db->update($this->table, ['like_count' => 'SQL:like_count + 1'], ['id' => $commentId]);

            // 发送评论点赞通知
            $comment = $this->getCommentById($commentId);
            if ($comment && $comment['user_id'] != $userId) {
                require_once APP_PATH . '/Services/NotificationService.php';
                NotificationService::notifyCommentLike($commentId, $userId);
            }

            // 提交事务
            $pdo->commit();
            return true;
        } catch (Exception $e) {
            // 回滚事务
            $pdo->rollback();
            error_log('Transaction failed in likeComment: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 取消点赞评论
     * @param int $commentId 评论ID
     * @param int $userId 用户ID
     * @return bool 取消点赞结果
     */
    public function unlikeComment($commentId, $userId) {
        // 检查是否已经点赞
        if (!$this->isCommentLiked($commentId, $userId)) {
            return true; // 没有点赞，直接返回成功
        }

        // 开启事务
        $pdo = $this->db->getPdo();
        $pdo->beginTransaction();

        try {
            // 删除点赞记录
            $this->db->delete('comment_like', ['comment_id' => $commentId, 'user_id' => $userId]);

            // 更新评论点赞数
            $this->db->update($this->table, ['like_count' => 'SQL:like_count - 1'], ['id' => $commentId]);

            // 提交事务
            $pdo->commit();
            return true;
        } catch (Exception $e) {
            // 回滚事务
            $pdo->rollback();
            error_log('Transaction failed in unlikeComment: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取用户的评论消息
     * @param int $userId 用户ID
     * @param int $offset 查询偏移量
     * @param int $pageSize 每页数量
     * @param string $search 搜索关键词
     * @return array 用户评论列表
     */
    public function getUserCommentMessages($userId, $offset = 0, $pageSize = 10, $search = '') {
        $whereClause = [];
        $params = [$userId, $userId];
        
        // 构建搜索条件
        if (!empty($search)) {
            $whereClause[] = "c.content LIKE ?";
            $params[] = "%" . $search . "%";
        }
        
        $where = $whereClause ? "AND " . implode(" AND ", $whereClause) : "";
        
        $sql = "SELECT c.*, u.username as user_username, u.nickname as user_nickname, u.avatar as user_avatar, a.title as article_title, a.id as article_id
                FROM " . $this->db->table($this->table) . " c 
                LEFT JOIN " . $this->db->table('user') . " u ON c.user_id = u.id 
                LEFT JOIN " . $this->db->table('article') . " a ON c.article_id = a.id 
                WHERE (a.user_id = ? OR c.parent_id IN (SELECT id FROM " . $this->db->table($this->table) . " sub_c WHERE sub_c.user_id = ?)) AND c.status = 1 {$where} 
                ORDER BY c.created_at DESC 
                LIMIT ?, ?";
        
        $params[] = $offset;
        $params[] = $pageSize;
        
        $result = $this->db->fetchAll($sql, $params);
        
        // 转换时间格式
        foreach ($result as &$comment) {
            $comment['created_at'] = date('Y-m-d H:i:s', $comment['created_at']);
            $comment['comment_content'] = $comment['content'];
        }
        
        return $result;
    }

    /**
     * 获取用户评论消息总数
     * @param int $userId 用户ID
     * @param string $search 搜索关键词
     * @return int 评论总数
     */
    public function getUserCommentMessagesCount($userId, $search = '') {
        $whereClause = [];
        $params = [$userId, $userId];
        
        // 构建搜索条件
        if (!empty($search)) {
            $whereClause[] = "c.content LIKE ?";
            $params[] = "%" . $search . "%";
        }
        
        $where = $whereClause ? "AND " . implode(" AND ", $whereClause) : "";
        
        $sql = "SELECT COUNT(*) as count FROM " . $this->db->table($this->table) . " c 
                LEFT JOIN " . $this->db->table('article') . " a ON c.article_id = a.id 
                WHERE (a.user_id = ? OR c.parent_id IN (SELECT id FROM " . $this->db->table($this->table) . " sub_c WHERE sub_c.user_id = ?)) AND c.status = 1 {$where}";
        
        $result = $this->db->fetchAll($sql, $params);
        return $result[0]['count'] ?? 0;
    }
    
    /**
     * 获取用户未读评论消息数量
     * @param int $userId 用户ID
     * @return int 未读评论数量
     */
    public function getUserUnreadCommentMessagesCount($userId) {
        // 获取用户最后阅读评论消息的时间
        require_once APP_PATH . '/Models/UserModel.php';
        $lastReadTime = UserModel::getLastReadCommentsTime($userId);
        
        $params = [$userId, $userId, $lastReadTime];
        
        $sql = "SELECT COUNT(*) as count FROM " . $this->db->table($this->table) . " c 
                LEFT JOIN " . $this->db->table('article') . " a ON c.article_id = a.id 
                WHERE (a.user_id = ? OR c.parent_id IN (SELECT id FROM " . $this->db->table($this->table) . " sub_c WHERE sub_c.user_id = ?)) 
                AND c.status = 1 
                AND c.created_at > ?";
        
        $result = $this->db->fetchAll($sql, $params);
        return $result[0]['count'] ?? 0;
    }

    /**
     * 检查用户是否已点赞评论
     * @param int $commentId 评论ID
     * @param int $userId 用户ID
     * @return bool 是否已点赞
     */
    public function isCommentLiked($commentId, $userId) {
        $sql = "SELECT COUNT(*) as count FROM " . $this->db->table('comment_like') . " WHERE comment_id = ? AND user_id = ?";
        $result = $this->db->fetchAll($sql, [$commentId, $userId]);
        return $result[0]['count'] > 0;
    }

    /**
     * 获取评论点赞数
     * @param int $commentId 评论ID
     * @return int 点赞数
     */
    public function getCommentLikeCount($commentId) {
        $sql = "SELECT like_count FROM " . $this->db->table($this->table) . " WHERE id = ?";
        $result = $this->db->fetchAll($sql, [$commentId]);
        return $result[0]['like_count'] ?? 0;
    }
    
    /**
     * 获取待审核评论数量
     * @return int 待审核评论数量
     */
    public function getPendingCommentCount() {
        $sql = "SELECT COUNT(*) as count FROM " . $this->db->table($this->table) . " WHERE status = 0";
        $result = $this->db->fetchAll($sql);
        return $result[0]['count'] ?? 0;
    }

    /**
     * 获取嵌套评论列表（后台管理用）
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @param array $filters 筛选条件
     * @return array 嵌套评论列表
     */
    public function getNestedComments($page = 1, $pageSize = 10, $filters = [], $sort = '', $order = 'desc') {
        // 先获取主评论（parent_id = 0）
        $offset = ($page - 1) * $pageSize;
        $whereClause = [];
        $params = [];
        
        // 默认只获取未删除的评论
        if (!isset($filters['is_deleted']) || $filters['is_deleted'] === '') {
            $whereClause[] = "c.is_deleted = 0";
        } else if ($filters['is_deleted'] !== 'all') {
            $whereClause[] = "c.is_deleted = ?";
            $params[] = $filters['is_deleted'];
        }
        
        if (isset($filters['status']) && $filters['status'] !== '') {
            $whereClause[] = "c.status = ?";
            $params[] = $filters['status'];
        }
        
        if (isset($filters['article_id']) && $filters['article_id']) {
            $whereClause[] = "c.article_id = ?";
            $params[] = $filters['article_id'];
        }
        
        if (isset($filters['user_id']) && $filters['user_id']) {
            $whereClause[] = "c.user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        if (isset($filters['search']) && !empty($filters['search'])) {
            $whereClause[] = "c.content LIKE ?";
            $params[] = "%" . $filters['search'] . "%";
        }
        
        $where = $whereClause ? "WHERE " . implode(" AND ", $whereClause) : "";
        
        // 只获取主评论（parent_id = 0）
        $where .= $where ? " AND c.parent_id = 0" : " WHERE c.parent_id = 0";
        
        $sql = "SELECT c.*, u.username, u.nickname, a.title as article_title
                FROM " . $this->db->table($this->table) . " c
                LEFT JOIN " . $this->db->table('user') . " u ON c.user_id = u.id
                LEFT JOIN " . $this->db->table('article') . " a ON c.article_id = a.id
                {$where}
                ORDER BY c.is_top DESC, c.created_at DESC
                LIMIT ?, ?";

        // 后台表头排序：白名单校验（防 SQL 注入），置顶评论仍优先展示
        $sortWhiteList = ['id', 'created_at'];
        $orderDir = (strtolower($order) === 'asc') ? 'ASC' : 'DESC';
        if ($sort !== '' && in_array($sort, $sortWhiteList)) {
            $sql = str_replace("ORDER BY c.is_top DESC, c.created_at DESC",
                "ORDER BY c.is_top DESC, c.{$sort} {$orderDir}, c.created_at DESC", $sql);
        }
        
        $params[] = $offset;
        $params[] = $pageSize;
        
        $result = $this->db->fetchAll($sql, $params);
        
        // 转换时间格式并递归获取嵌套回复
        foreach ($result as &$comment) {
            $comment['created_at'] = date('Y-m-d H:i:s', $comment['created_at']);
            // 获取回复数量
            $comment['reply_count'] = $this->getReplyCount($comment['id'], $filters);
            // 递归获取嵌套回复
            $comment['replies'] = $this->getRepliesRecursive($comment['id'], $filters);
        }
        
        return $result;
    }

    /**
     * 递归获取回复列表
     * @param int $parentId 父评论ID
     * @param array $filters 筛选条件
     * @return array 回复列表
     */
    private function getRepliesRecursive($parentId, $filters = []) {
        $whereClause = ["c.parent_id = ?", "c.is_deleted = 0"];
        $params = [$parentId];
        
        if (isset($filters['status']) && $filters['status'] !== '') {
            $whereClause[] = "c.status = ?";
            $params[] = $filters['status'];
        }
        
        if (isset($filters['search']) && !empty($filters['search'])) {
            $whereClause[] = "c.content LIKE ?";
            $params[] = "%" . $filters['search'] . "%";
        }
        
        $where = "WHERE " . implode(" AND ", $whereClause);
        
        $sql = "SELECT c.*, u.username, u.nickname, a.title as article_title,
                       pu.username as parent_username, pu.nickname as parent_nickname
                FROM " . $this->db->table($this->table) . " c 
                LEFT JOIN " . $this->db->table('user') . " u ON c.user_id = u.id 
                LEFT JOIN " . $this->db->table('article') . " a ON c.article_id = a.id 
                LEFT JOIN " . $this->db->table($this->table) . " pc ON c.parent_id = pc.id
                LEFT JOIN " . $this->db->table('user') . " pu ON pc.user_id = pu.id
                {$where} 
                ORDER BY c.is_top DESC, c.created_at ASC";
        
        $result = $this->db->fetchAll($sql, $params);
        
        foreach ($result as &$reply) {
            $reply['created_at'] = date('Y-m-d H:i:s', $reply['created_at']);
            $reply['reply_count'] = $this->getReplyCount($reply['id'], $filters);
            $reply['replies'] = $this->getRepliesRecursive($reply['id'], $filters);
        }
        
        return $result;
    }

    /**
     * 获取回复数量
     * @param int $parentId 父评论ID
     * @param array $filters 筛选条件
     * @return int 回复数量
     */
    private function getReplyCount($parentId, $filters = []) {
        $whereClause = ["parent_id = ?", "is_deleted = 0"];
        $params = [$parentId];
        
        if (isset($filters['status']) && $filters['status'] !== '') {
            $whereClause[] = "status = ?";
            $params[] = $filters['status'];
        }
        
        $where = "WHERE " . implode(" AND ", $whereClause);
        
        $sql = "SELECT COUNT(*) as count FROM " . $this->db->table($this->table) . " {$where}";
        $result = $this->db->fetchAll($sql, $params);
        return $result[0]['count'] ?? 0;
    }

    /**
     * 获取指定用户的评论总数（含回复）
     * @param int $userId 用户ID
     * @return int 评论总数
     */
    public function getUserCommentsCount($userId) {
        $sql = "SELECT COUNT(*) as count FROM " . $this->db->table($this->table) . " WHERE user_id = ? AND is_deleted = 0";
        $result = $this->db->fetch($sql, [$userId]);
        return $result ? (int)$result['count'] : 0;
    }
}

