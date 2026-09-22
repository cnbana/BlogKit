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
 * 文章API控制器
 * 提供文章相关的API接口
 * 
 * 基础URL: /api.php/v1/articles
 */

class ArticleApiController extends ApiController {
    
    /**
     * 获取文章列表
     * GET /api.php/v1/articles
     * 
     * 查询参数：
     * - page: 页码（默认1）
     * - page_size: 每页数量（默认读取后台 api_pagination_default 配置）
     * - category_id: 分类ID
     * - tag_id: 标签ID
     * - status: 状态（0-草稿，1-已发布）
     * - sort: 排序（latest-最新，popular-最热）
     * - search: 搜索关键词
     */
    public function index() {        
        $page = $this->getIntParam('page', 1);
        $pageSize = $this->getPageSize();
        $categoryId = $this->getIntParam('category_id', 0);
        $tagId = $this->getIntParam('tag_id', 0);
        $status = $this->getIntParam('status', 1);
        $sort = $this->getStringParam('sort', 'latest');
        $search = $this->getStringParam('search', '');
        
        $articleModel = new ArticleModel();
        
        $filters = [];
        if ($categoryId > 0) {
            $filters['category'] = $categoryId;
        }
        if ($tagId > 0) {
            $filters['tag'] = $tagId;
        }
        if ($status !== '') {
            $filters['status'] = $status;
        }
        if (!empty($search)) {
            $filters['search'] = $search;
        }
        
        $articles = $articleModel->getArticles($page, $pageSize, false, $filters);
        $total = $articleModel->getArticleCount(false, $filters);
        
        $this->paginate($articles, $total, $page, $pageSize, '获取成功');
    }
    
    /**
     * 获取单篇文章
     * GET /api.php/v1/articles/{id}
     */
    public function show($id = 0) {
        // Handle both scalar and array parameter (for call_user_func with associative array)
        if (is_array($id)) {
            $articleId = isset($id['id']) ? intval($id['id']) : 0;
        } else {
            $articleId = intval($id);
        }
        
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
        
        $this->success($article, '获取成功');
    }
    
    /**
     * 创建文章
     * POST /api.php/v1/articles
     * 
     * 请求体：
     * - title: 标题（必填）
     * - content: 内容（必填）
     * - category_id: 分类ID（必填）
     * - tags: 标签（逗号分隔）
     * - status: 状态（0-草稿，1-发布）
     * - excerpt: 摘要
     */
    public function store() {        $this->requireLogin();

        if (AuthController::isNewUserRestricted() && (int)Config::get('register_new_user_ban_post', 0)) {
            $this->error('新账号在限制期内暂不允许发布文章，请稍后再试', 403);
            return;
        }

        $this->validateRules([
            'title' => ['required' => true, 'max' => 60],
            'content' => ['required' => true],
            'category_id' => ['required' => true, 'type' => 'int']
        ]);
        
        $title = $this->getStringParam('title');
        $content = $this->getStringParam('content');
        $categoryId = $this->getIntParam('category_id');
        $tags = $this->getStringParam('tags');
        $status = $this->getIntParam('status', 0);
        $excerpt = $this->getStringParam('excerpt');
        
        // 额外验证：标题字符数（按UTF-8字符计，60字符）
        if (!empty($title) && mb_strlen(trim($title), 'UTF-8') > 60) {
            $this->error('标题最多 60 个字符（约 30 个汉字）', 400);
            return;
        }
        
        // 额外验证：摘要/描述字符数（按UTF-8字符计，200字符）
        if (!empty($excerpt) && mb_strlen(trim($excerpt), 'UTF-8') > 200) {
            $this->error('文章描述/摘要最多 200 个字符（约 100 个汉字）', 400);
            return;
        }
        
        
        $articleModel = new ArticleModel();
        $tagModel = new TagModel();
        
        $articleData = [
            'title' => $title,
            'content' => $content,
            'category_id' => $categoryId,
            'user_id' => $this->currentUser['id'],
            'status' => $status,
            'tags' => $tagModel->processTags($tags)
        ];
        
        if (!empty($excerpt)) {
            $articleData['excerpt'] = $excerpt;
        }
        
        $articleId = $articleModel->createArticle($articleData);
        
        if ($articleId) {
            $this->success(['article_id' => $articleId], '文章创建成功');
        } else {
            $this->error('文章创建失败');
        }
    }
    
    /**
     * 更新文章
     * PUT/PATCH /api.php/v1/articles/{id}
     */
    public function update($id = 0) {
        $this->requireLogin();

        if (AuthController::isNewUserRestricted() && (int)Config::get('register_new_user_ban_post', 0)) {
            $this->error('新账号在限制期内暂不允许编辑文章，请稍后再试', 403);
            return;
        }
        
        if (is_array($id)) {
            $articleId = isset($id['id']) ? intval($id['id']) : 0;
        } else {
            $articleId = intval($id);
        }
        
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
        
        if ($article['user_id'] != $this->currentUser['id']) {
            $this->error('无权修改此文章', 403);
            return;
        }
        
        $title = $this->getStringParam('title');
        $content = $this->getStringParam('content');
        $categoryId = $this->getIntParam('category_id');
        $tags = $this->getStringParam('tags');
        $status = $this->getIntParam('status');
        $excerpt = $this->getStringParam('excerpt');
        
        // 额外验证：标题字符数（按UTF-8字符计，60字符）
        if (!empty($title) && mb_strlen(trim($title), 'UTF-8') > 60) {
            $this->error('标题最多 60 个字符（约 30 个汉字）', 400);
            return;
        }
        
        // 额外验证：摘要/描述字符数（按UTF-8字符计，200字符）
        if (!empty($excerpt) && mb_strlen(trim($excerpt), 'UTF-8') > 200) {
            $this->error('文章描述/摘要最多 200 个字符（约 100 个汉字）', 400);
            return;
        }
        
        $articleData = [];
        if (!empty($title)) {
            $articleData['title'] = $title;
        }
        if (!empty($content)) {
            $articleData['content'] = $content;
        }
        if (!empty($categoryId)) {
            $articleData['category_id'] = $categoryId;
        }
        if (!empty($tags)) {
            $tagModel = new TagModel();
            $articleData['tags'] = $tagModel->processTags($tags);
        }
        if ($status !== null) {
            $articleData['status'] = $status;
        }
        if (!empty($excerpt)) {
            $articleData['excerpt'] = $excerpt;
        }
        
        $result = $articleModel->updateArticle($articleId, $articleData);
        
        if ($result) {
            $this->success(null, '文章更新成功');
        } else {
            $this->error('文章更新失败');
        }
    }
    
    /**
     * 删除文章
     * DELETE /api.php/v1/articles/{id}
     */
    public function destroy($id = 0) {
        $this->requireLogin();
        
        if (is_array($id)) {
            $articleId = isset($id['id']) ? intval($id['id']) : 0;
        } else {
            $articleId = intval($id);
        }
        
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
        
        if ($article['user_id'] != $this->currentUser['id']) {
            $this->error('无权删除此文章', 403);
            return;
        }
        
        $result = $articleModel->deleteArticle($articleId);
        
        if ($result) {
            $this->success(null, '文章删除成功');
        } else {
            $this->error('文章删除失败');
        }
    }
    
    /**
     * 点赞文章
     * POST /api.php/v1/articles/{id}/like
     */
    public function like($id = 0) {
        $this->requireLogin();
        
        if (is_array($id)) {
            $articleId = isset($id['id']) ? intval($id['id']) : 0;
        } else {
            $articleId = intval($id);
        }
        
        if ($articleId <= 0) {
            $this->error('文章ID无效', 400);
            return;
        }
        
        $likeModel = new LikeModel();
        
        $result = $likeModel->addLike($this->currentUser['id'], $articleId);
        
        if ($result) {
            // 点赞成功后，发送通知给文章作者
            NotificationService::notifyArticleLike($articleId, $this->currentUser['id']);
            
            $likeCount = $likeModel->getLikeCount($articleId);
            $this->success(['like_count' => $likeCount], '点赞成功');
        } else {
            $this->error('点赞失败');
        }
    }
    
    /**
     * 取消点赞文章
     * POST /api.php/v1/articles/{id}/unlike
     */
    public function unlike($id = 0) {
        $this->requireLogin();
        
        if (is_array($id)) {
            $articleId = isset($id['id']) ? intval($id['id']) : 0;
        } else {
            $articleId = intval($id);
        }
        
        if ($articleId <= 0) {
            $this->error('文章ID无效', 400);
            return;
        }
        
        $likeModel = new LikeModel();
        
        $result = $likeModel->removeLike($this->currentUser['id'], $articleId);
        
        if ($result) {
            $likeCount = $likeModel->getLikeCount($articleId);
            $this->success(['like_count' => $likeCount], '取消点赞成功');
        } else {
            $this->error('取消点赞失败');
        }
    }
    
    /**
     * 收藏文章
     * POST /api.php/v1/articles/{id}/favorite
     */
    public function favorite($id = 0) {
        $this->requireLogin();
        
        if (is_array($id)) {
            $articleId = isset($id['id']) ? intval($id['id']) : 0;
        } else {
            $articleId = intval($id);
        }
        
        if ($articleId <= 0) {
            $this->error('文章ID无效', 400);
            return;
        }
        
        $favoriteModel = new FavoriteModel();
        
        $result = $favoriteModel->addFavorite($this->currentUser['id'], $articleId);
        
        if ($result) {
            // 收藏成功后，发送通知给文章作者
            NotificationService::notifyArticleFavorite($articleId, $this->currentUser['id']);
            
            $favoriteCount = $favoriteModel->getFavoriteCount($articleId);
            $this->success(['favorite_count' => $favoriteCount], '收藏成功');
        } else {
            $this->error('收藏失败');
        }
    }
    
    /**
     * 取消收藏文章
     * POST /api.php/v1/articles/{id}/unfavorite
     */
    public function unfavorite($id = 0) {
        $this->requireLogin();
        
        if (is_array($id)) {
            $articleId = isset($id['id']) ? intval($id['id']) : 0;
        } else {
            $articleId = intval($id);
        }
        
        if ($articleId <= 0) {
            $this->error('文章ID无效', 400);
            return;
        }
        
        $favoriteModel = new FavoriteModel();
        
        $result = $favoriteModel->removeFavorite($this->currentUser['id'], $articleId);
        
        if ($result) {
            $favoriteCount = $favoriteModel->getFavoriteCount($articleId);
            $this->success(['favorite_count' => $favoriteCount], '取消收藏成功');
        } else {
            $this->error('取消收藏失败');
        }
    }
    
    /**
     * 搜索文章
     * GET /api.php/v1/articles/search
     * 
     * 查询参数：
     * - keyword: 搜索关键词（必填）
     * - page: 页码（默认1）
     * - page_size: 每页数量（默认读取后台 api_pagination_default 配置）
     */
    public function search() {        
        $keyword = $this->getStringParam('keyword');
        
        if (empty($keyword)) {
            $this->error('搜索关键词不能为空', 400);
            return;
        }
        
        $page = $this->getIntParam('page', 1);
        $pageSize = $this->getPageSize();
        
        $articleModel = new ArticleModel();
        
        $articles = $articleModel->searchArticles($keyword, $page, $pageSize);
        $total = $articleModel->getSearchArticleCount($keyword);
        
        $this->paginate($articles, $total, $page, $pageSize, '搜索成功');
    }
    
    /**
     * 设置文章置顶状态
     * POST /api.php/v1/articles/{id}/top
     * 
     * 请求体：
     * - top_type: 置顶类型（0-取消置顶，1-全局置顶，2-首页置顶，3-分类置顶）
     */
    public function topArticle($id = 0) {
        $this->requireLogin();
        
        if (is_array($id)) {
            $articleId = isset($id['id']) ? intval($id['id']) : 0;
        } else {
            $articleId = intval($id);
        }
        
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
        
        // 检查权限：只有文章作者或管理员可以设置置顶
        if ($article['user_id'] != $this->currentUser['id']) {
            // 检查是否为管理员
            $userModel = new UserModel();
            $userRole = $userModel->getUserRole($this->currentUser['id']);
            
            if ($userRole != 'admin') {
                $this->error('无权设置此文章置顶', 403);
                return;
            }
        }
        
        $topType = $this->getIntParam('top_type', 0);
        
        if (!in_array($topType, [0, 1, 2, 3])) {
            $this->error('无效的置顶类型', 400);
            return;
        }
        
        $result = $articleModel->setArticleTop($articleId, $topType);
        
        if ($result) {
            $this->success(['top_type' => $topType], '置顶设置成功');
        } else {
            $this->error('置顶设置失败');
        }
    }
    
    /**
     * 获取置顶文章
     * GET /api.php/v1/articles/top
     * 
     * 查询参数：
     * - top_types: 置顶类型（逗号分隔，如：1,2）
     * - category_id: 分类ID（可选，用于分类置顶）
     * - limit: 限制数量（默认10）
     */
    public function getTopArticles() {
        $topTypes = $this->getStringParam('top_types', '1,2,3');
        $categoryId = $this->getIntParam('category_id', 0);
        $limit = $this->getIntParam('limit', 10);
        
        $topTypeArray = array_map('intval', explode(',', $topTypes));
        
        $articleModel = new ArticleModel();
        
        $articles = $articleModel->getTopArticles($topTypeArray, $categoryId, $limit);
        
        $this->success($articles, '获取置顶文章成功');
    }
}
