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
 * 文章服务层
 * 封装文章相关的业务逻辑和数据查询，供 HomeController/ProfileController 等调用
 */
class ArticleService {

    /**
     * 批量填充文章额外数据（标签、点赞数、收藏数、评论数）
     * 消除 N+1 查询问题，使用批量IN查询一次获取所有数据
     *
     * @param array $articles 文章数组（需包含 'id' 字段）
     * @return array 增强后的文章数组
     */
    public static function batchEnrichArticles($articles) {
        if (empty($articles)) {
            return $articles;
        }

        $ids = array_column($articles, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $db = Database::getInstance();
        $prefix = Config::get('database')['prefix'];

        // 批量获取标签
        $tagRows = $db->fetchAll(
            "SELECT at.article_id, t.id, t.name 
             FROM {$prefix}article_tag at 
             JOIN {$prefix}tag t ON at.tag_id = t.id 
             WHERE at.article_id IN ($placeholders)",
            $ids, true
        );
        $tagsMap = [];
        foreach ($tagRows as $row) {
            $tagsMap[$row['article_id']][] = ['id' => $row['id'], 'name' => $row['name']];
        }

        // 批量获取点赞数
        $likeRows = $db->fetchAll(
            "SELECT article_id, COUNT(*) as cnt FROM {$prefix}like 
             WHERE article_id IN ($placeholders) GROUP BY article_id",
            $ids, true
        );
        $likesMap = [];
        foreach ($likeRows as $row) {
            $likesMap[$row['article_id']] = (int)$row['cnt'];
        }

        // 批量获取收藏数
        $favRows = $db->fetchAll(
            "SELECT article_id, COUNT(*) as cnt FROM {$prefix}favorite 
             WHERE article_id IN ($placeholders) GROUP BY article_id",
            $ids, true
        );
        $favsMap = [];
        foreach ($favRows as $row) {
            $favsMap[$row['article_id']] = (int)$row['cnt'];
        }

        // 批量获取评论数
        $cmtRows = $db->fetchAll(
            "SELECT article_id, COUNT(*) as cnt FROM {$prefix}comments 
             WHERE article_id IN ($placeholders) AND status = 1 AND is_deleted = 0 
             GROUP BY article_id",
            $ids, true
        );
        $cmtsMap = [];
        foreach ($cmtRows as $row) {
            $cmtsMap[$row['article_id']] = (int)$row['cnt'];
        }

        // 赋值回文章
        foreach ($articles as $index => $article) {
            $aid = $article['id'];
            $articles[$index]['tags'] = isset($tagsMap[$aid]) ? $tagsMap[$aid] : [];
            $articles[$index]['like_count'] = isset($likesMap[$aid]) ? $likesMap[$aid] : 0;
            $articles[$index]['favorite_count'] = isset($favsMap[$aid]) ? $favsMap[$aid] : 0;
            $articles[$index]['comment_count'] = isset($cmtsMap[$aid]) ? $cmtsMap[$aid] : 0;
            if (!isset($articles[$index]['content'])) {
                $articles[$index]['content'] = '';
            }
        }

        return $articles;
    }

    /**
     * 获取文章列表数据（用于首页/分类/标签页）
     *
     * @param int $page 当前页码
     * @param int $perPage 每页数量
     * @param string $filter 排序方式 (latest/hottest/most_liked/most_faved)
     * @param array $filters 额外筛选条件
     * @return array ['articles' => [...], 'pagination' => Pagination]
     */
    public function getArticleListing($page, $perPage, $filter = 'latest', $filters = []) {
        $articleModel = new ArticleModel();

        $total = $articleModel->getArticleCount();
        $pagination = new Pagination($total, $perPage);
        $pagination->setCurrentPage($page);

        $queryFilters = array_merge(['top_type' => 0], $filters);
        $normalArticles = $articleModel->getArticles($pagination->getCurrentPage(), $perPage, false, $queryFilters, $filter);

        $topArticles = $articleModel->getTopArticles([1, 2], 0, 10);
        $articles = array_merge($topArticles, $normalArticles);
        $articles = self::batchEnrichArticles($articles);

        return [
            'articles' => $articles,
            'pagination' => $pagination,
        ];
    }

    /**
     * 获取分类文章列表
     *
     * @param mixed $categoryId 分类ID或slug
     * @param int $page 当前页码
     * @param int $perPage 每页数量
     * @param string $filter 排序方式
     * @return array ['category' => [...], 'articles' => [...], 'pagination' => Pagination] 或 null
     */
    public function getCategoryListing($categoryId, $page, $perPage, $filter = 'latest') {
        $articleModel = new ArticleModel();
        $categoryModel = new CategoryModel();

        if (is_numeric($categoryId)) {
            $category = $categoryModel->getCategoryById($categoryId);
        } else {
            $category = $categoryModel->getCategoryBySlug($categoryId);
        }

        if (!$category) {
            return null;
        }

        $total = $articleModel->getArticleCountByCategoryId($categoryId);
        $pagination = new Pagination($total, $perPage);
        $pagination->setCurrentPage($page);

        $normalArticles = $articleModel->getArticlesByCategoryId($categoryId, $pagination->getCurrentPage(), $perPage, $filter);
        $topArticles = $articleModel->getTopArticles([1, 3], $categoryId, 10);
        $articles = array_merge($topArticles, $normalArticles);
        $articles = self::batchEnrichArticles($articles);

        return [
            'category' => $category,
            'articles' => $articles,
            'pagination' => $pagination,
        ];
    }

    /**
     * 获取标签文章列表
     */
    public function getTagListing($tagId, $page, $perPage) {
        $articleModel = new ArticleModel();
        $tagModel = new TagModel();

        $tag = $tagModel->getTagById($tagId);
        if (!$tag) {
            return null;
        }

        $total = $articleModel->getArticleCountByTagId($tagId);
        $pagination = new Pagination($total, $perPage);
        $pagination->setCurrentPage($page);

        $articles = $articleModel->getArticlesByTagId($tagId, $pagination->getCurrentPage(), $perPage);
        $articles = self::batchEnrichArticles($articles);

        return [
            'tag' => $tag,
            'articles' => $articles,
            'pagination' => $pagination,
        ];
    }

    /**
     * 获取文章详情及关联数据
     *
     * @param int $id 文章ID
     * @param int|null $userId 当前用户ID（用于判断点赞/收藏状态）
     * @return array|null
     */
    public function getArticleDetail($id, $userId = null) {
        $articleModel = new ArticleModel();
        $article = $articleModel->getArticleById($id);

        if (!$article) {
            return null;
        }

        $tagModel = new TagModel();
        $favoriteModel = new FavoriteModel();
        $likeModel = new LikeModel();

        $articleTags = $tagModel->getTagsByArticleId($id);

        // 收藏/点赞数据
        $article['favorite_count'] = $favoriteModel->getFavoriteCount($id);
        $article['is_favorite'] = false;
        $article['like_count'] = $likeModel->getLikeCount($id);
        $article['is_liked'] = false;

        if ($userId) {
            $article['is_favorite'] = $favoriteModel->checkFavorite($userId, $id);
            $article['is_liked'] = $likeModel->checkLike($userId, $id);
        }

        // 上下篇
        $previousArticle = $articleModel->getPreviousArticle($id);
        $nextArticle = $articleModel->getNextArticle($id);

        // 相关文章
        $relatedArticles = $articleModel->getRelatedArticles($id, 5);
        $relatedArticles = self::batchEnrichArticles($relatedArticles);

        return [
            'article' => $article,
            'tags' => $articleTags,
            'previous_article' => $previousArticle,
            'next_article' => $nextArticle,
            'related_articles' => $relatedArticles,
        ];
    }

    /**
     * 获取侧边栏共用数据
     *
     * @return array
     */
    public function getSidebarData() {
        $categoryModel = new CategoryModel();
        $tagModel = new TagModel();
        $articleModel = new ArticleModel();
        $pageModel = new PageModel();
        $friendlinkModel = new FriendlinkModel();

        return [
            'categories' => $categoryModel->getMenuCategories(),
            'all_tags' => $tagModel->getAllTags(),
            'latest_articles' => $articleModel->getLatestArticles(5),
            'pages' => $pageModel->getMenuPages(),
            'friendlinks' => $friendlinkModel->getEnabledFriendlinks(),
        ];
    }
}
