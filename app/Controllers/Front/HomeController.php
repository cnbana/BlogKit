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

// 包含分页类

// 包含服务层

// 缓存类通过自动加载器加载

class HomeController {
    
    /**
     * 共享模板初始化：加载导航数据、设备检测、用户会话、消息配置
     * 减少 index/article/category/tag/page/search/tags 等方法的重复代码
     * 
     * @return Template 初始化好的模板实例
     */
    private function initHomeTemplate() {
        $template = new Template();
        
        // 设备检测
        $template->assign('device', $this->detectDevice());
        
        // 用户会话
        if (isset($_SESSION['user'])) {
            $template->assign('user', $_SESSION['user']);
        }
        
        // 消息配置
        $template->assign('message_enabled', Config::get('message_enabled', '1'));
        $template->assign('message_duration', Config::get('message_duration', '3'));
        
        // 成功消息（一次性）
        if (isset($_SESSION['success_message'])) {
            $template->assign('success', $_SESSION['success_message']);
            unset($_SESSION['success_message']);
        }
        
        return $template;
    }
    
    /**
     * 加载侧边栏数据到模板（分类、标签、最新文章、菜单、友链）
     * @param Template $template 模板实例
     */
    private function assignSidebarData($template) {
        $articleService = new ArticleService();
        $sidebar = $articleService->getSidebarData();
        foreach ($sidebar as $key => $value) {
            $template->assign($key, $value);
        }
    }
    
    /**
     * 检查并渲染缓存页面
     * @param string $cacheKey 缓存键
     * @param int $ttl 缓存过期时间（秒）
     * @return bool 是否命中缓存
     */
    private function tryCache($cacheKey, $ttl = 3600) {
        $cacheEnabled = Config::get('cache_enabled', '1');
        if ($cacheEnabled != 1 || isset($_SESSION['user']) || isset($_SESSION['success_message'])) {
            return false;
        }
        
        $cache = Cache::getInstance();
        $cachedContent = $cache->get($cacheKey);
        if ($cachedContent) {
            echo $cachedContent;
            return true;
        }
        return false;
    }
    
    /**
     * 缓存页面输出
     * @param string $cacheKey 缓存键
     * @param Template $template 模板实例
     * @param string $tplName 模板名称
     * @param int $ttl 缓存过期时间（秒）
     */
    private function cacheAndDisplay($cacheKey, $template, $tplName, $ttl = 3600) {
        $cacheEnabled = Config::get('cache_enabled', '1');
        if ($cacheEnabled == 1 && !isset($_SESSION['user']) && !isset($_SESSION['success_message'])) {
            ob_start();
            $template->display($tplName);
            $content = ob_get_clean();
            $cache = Cache::getInstance();
            $cache->set($cacheKey, $content, $ttl);
            echo $content;
        } else {
            $template->display($tplName);
        }
    }

    /**
     * 批量获取文章元数据（标签、点赞数、收藏数、评论数）
     * 替代 N+1 循环查询，将数十次查询合并为 4 次
     * @param array $articles 文章数组
     * @return array 增强后的文章数组
     */
    /**
     * 批量填充文章额外数据（委托至 ArticleService）
     * 保留此静态方法以兼容 ProfileController 等调用方
     */
    public static function batchEnrichArticles($articles) {
        return ArticleService::batchEnrichArticles($articles);
    }

    /**
     * 检测设备类型
     * @return array 包含设备类型信息的数组
     */
    private function detectDevice() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $isMobile = false;
        $isTablet = false;
        $isDesktop = true;
        $isApp = false;
        $isWechat = false;
        $isWechatMiniProgram = false;
        $isAlipay = false;
        $isBaidu = false;
        
        // 检测移动设备
        $mobileKeywords = ['Mobile', 'Android', 'iPhone', 'iPad', 'iPod', 'BlackBerry', 'Windows Phone'];
        foreach ($mobileKeywords as $keyword) {
            if (strpos($userAgent, $keyword) !== false) {
                $isMobile = true;
                $isDesktop = false;
                break;
            }
        }
        
        // 检测平板设备
        $tabletKeywords = ['iPad', 'Android.*Tablet'];
        foreach ($tabletKeywords as $keyword) {
            if (preg_match('/' . $keyword . '/i', $userAgent)) {
                $isTablet = true;
                $isMobile = false;
                break;
            }
        }
        
        // 检测APP端 (假设自定义User-Agent包含App标识)
        if (strpos($userAgent, 'BlogKitApp') !== false) {
            $isApp = true;
        }
        
        // 检测微信生态
        if (strpos($userAgent, 'MicroMessenger') !== false) {
            $isWechat = true;
            // 检测微信小程序
            if (strpos($userAgent, 'miniProgram') !== false) {
                $isWechatMiniProgram = true;
            }
        }
        
        // 检测支付宝小程序
        if (strpos($userAgent, 'AlipayClient') !== false) {
            $isAlipay = true;
        }
        
        // 检测百度小程序
        if (strpos($userAgent, 'swan') !== false) {
            $isBaidu = true;
        }
        
        return [
            'is_mobile' => $isMobile,
            'is_tablet' => $isTablet,
            'is_desktop' => $isDesktop,
            'is_app' => $isApp,
            'is_wechat' => $isWechat,
            'is_wechat_mini_program' => $isWechatMiniProgram,
            'is_alipay' => $isAlipay,
            'is_baidu' => $isBaidu
        ];
    }
    
    private function parseSeoTemplate($template, $data = []) {
        // 准备替换数据
        $replacements = [
            '{site.name}' => Config::get('site_name', 'My Blog'),
            '{site.description}' => Config::get('site_description', ''),
            '{site.keywords}' => Config::get('site_keywords', ''),
            '{site.url}' => Config::get('site.url', ''),
            // 通用时间占位符（可用于归档页等任何页面）
            '{year}' => date('Y'),
            '{month}' => date('m'),
            '{day}' => date('d'),
        ];
        
        // 添加文章相关的占位符
        if (isset($data['article'])) {
            $replacements['{article.title}'] = $data['article']['title'] ?? '';
            $articleSummary = $data['article']['excerpt'] ?? $data['article']['description'] ?? '';
            $replacements['{article.summary}'] = !empty($articleSummary) ? $articleSummary : Config::get('site_description', '');
            $articleDesc = $data['article']['description'] ?? $data['article']['excerpt'] ?? '';
            $replacements['{article.description}'] = !empty($articleDesc) ? $articleDesc : Config::get('site_description', '');
            $replacements['{article.category}'] = $data['article']['category_name'] ?? '';
            $replacements['{article.tags}'] = isset($data['article_tags']) ? implode(',', array_column($data['article_tags'], 'name')) : '';
        }
        
        // 添加分类相关的占位符
        if (isset($data['category'])) {
            $replacements['{category.name}'] = $data['category']['name'] ?? '';
            $categoryDesc = $data['category']['description'] ?? '';
            $replacements['{category.description}'] = !empty($categoryDesc) ? $categoryDesc : Config::get('site_description', '');
            $categoryKeywords = $data['category']['keywords'] ?? '';
            $replacements['{category.keywords}'] = !empty($categoryKeywords) ? $categoryKeywords : Config::get('site_keywords', '');
        }
        
        // 添加标签相关的占位符
        if (isset($data['tag'])) {
            $replacements['{tag.name}'] = $data['tag']['name'] ?? '';
        }
        
        // 添加页面相关的占位符
        if (isset($data['page'])) {
            $replacements['{page.title}'] = $data['page']['title'] ?? '';
            $pageDesc = $data['page']['description'] ?? '';
            $replacements['{page.description}'] = !empty($pageDesc) ? $pageDesc : Config::get('site_description', '');
            $pageKeywords = $data['page']['keywords'] ?? '';
            $replacements['{page.keywords}'] = !empty($pageKeywords) ? $pageKeywords : Config::get('site_keywords', '');
        }
        
        // 添加搜索相关的占位符
        if (isset($data['search'])) {
            $replacements['{search.keyword}'] = $data['search']['keyword'] ?? '';
        }
        
        // 添加用户相关的占位符
        if (isset($data['user'])) {
            $replacements['{user.nickname}'] = $data['user']['nickname'] ?? '';
            $replacements['{user.username}'] = $data['user']['username'] ?? '';
        }
        
        // 执行替换
        return strtr($template, $replacements);
    }

    public function index()
    {
        // 检查缓存是否启用
        $cacheEnabled = Config::get('cache_enabled', '1');
        $cacheKey = 'page_home_' . (isset($_GET['page']) ? $_GET['page'] : '1') . '_' . (isset($_GET['filter']) ? $_GET['filter'] : 'latest');
        
        // 如果缓存启用，尝试从缓存中获取
        if ($cacheEnabled == 1 && !isset($_SESSION['user']) && !isset($_SESSION['success_message'])) {
            $cache = Cache::getInstance();
            $cachedContent = $cache->get($cacheKey);
            
            if ($cachedContent) {
                echo $cachedContent;
                return;
            }
        }
        
        // 初始化模板引擎
        $template = new Template();
        
        // 检测设备类型并传递给模板
        $deviceInfo = $this->detectDevice();
        $template->assign('device', $deviceInfo);
        
        // 分配用户会话数据到模板
        if (isset($_SESSION['user'])) {
            $template->assign('user', $_SESSION['user']);
        }
        
        // 检查是否有成功提示消息
        if (isset($_SESSION['success_message'])) {
            $template->assign('success', $_SESSION['success_message']);
            // 只显示一次，之后清除
            unset($_SESSION['success_message']);
        }
        
        // 传递消息配置到模板
        $template->assign('message_enabled', Config::get('message_enabled', '1'));
        $template->assign('message_duration', Config::get('message_duration', '3'));
        
        // 使用模型类获取真实文章数据
        $articleModel = new ArticleModel();
        $categoryModel = new CategoryModel();
        $tagModel = new TagModel();
        $pageModel = new PageModel();
        $likeModel = new LikeModel();
        $favoriteModel = new FavoriteModel();
        $commentModel = new CommentModel();
        
        // 获取每页文章数配置
        $articles_per_page = Config::get('pagination_count', 10);
        
        // 获取文章总数
        $total_articles = $articleModel->getArticleCount();
        
        // 初始化分页类
        $pagination = new Pagination($total_articles, $articles_per_page);
        
        // 获取筛选参数
        $filter = isset($_GET['filter']) ? $_GET['filter'] : 'latest';
        
        // 获取当前页的文章（排除置顶文章）
        $filters = ['top_type' => 0];
        $normalArticles = $articleModel->getArticles($pagination->getCurrentPage(), $articles_per_page, false, $filters, $filter);
        
        // 获取置顶文章（全局置顶和首页置顶）
        $topArticles = $articleModel->getTopArticles([1, 2], 0, 10);
        
        // 合并文章列表：置顶文章在前，普通文章在后
        $articles = array_merge($topArticles, $normalArticles);
        
        // 获取只显示在菜单中的分类
        $categories = $categoryModel->getMenuCategories();
        
        // 获取所有标签
        $tags = $tagModel->getAllTags();
        
        // 获取菜单页面
        $pages = $pageModel->getMenuPages();
        
        // 获取友情链接
        $friendlinkModel = new FriendlinkModel();
        $friendlinks = $friendlinkModel->getEnabledFriendlinks();
        
        // 获取最新文章（侧边栏用）
        $latestArticles = $articleModel->getLatestArticles(5);
        
        // 批量增强文章数据（替代 N+1 循环查询）
        $articles = ArticleService::batchEnrichArticles($articles);
        
        // 准备面包屑数据
        $breadcrumb = [];
        
        // 传递SEO设置到模板
        $template->assign('seo_title', $this->parseSeoTemplate(Config::get('home_seo_title', '{site.name}')));
        $template->assign('seo_description', $this->parseSeoTemplate(Config::get('home_seo_description', '{site.description}')));
        $template->assign('seo_keywords', $this->parseSeoTemplate(Config::get('home_seo_keywords', '{site.keywords}')));
        
        // 传递数据到模板
        $template->assign('articles', $articles);
        $template->assign('categories', $categories);
        $template->assign('all_tags', $tags);
        $template->assign('pages', $pages);
        $template->assign('latest_articles', $latestArticles);
        $template->assign('friendlinks', $friendlinks);
        $template->assign('pagination', $pagination);
        $template->assign('pagination_info', $pagination->getPaginationInfo());
        $template->assign('breadcrumb', $breadcrumb);
        
        // 如果缓存启用且没有用户会话和成功消息，保存到缓存
        if ($cacheEnabled == 1 && !isset($_SESSION['user']) && !isset($_SESSION['success_message'])) {
            ob_start();
            $template->display('index');
            $content = ob_get_clean();
            
            $cache = Cache::getInstance();
            $cache->set($cacheKey, $content, 3600); // 缓存1小时
            
            echo $content;
        } else {
            // 直接渲染
            $template->display('index');
        }
    }
    
    public function article() {
        // 支持多参数（如 {year}/{month}/{slug} 规则），取最后一个非空参数作为文章标识
        $args = func_get_args();
        $id = null;
        // 过滤空参数，取最后一个有效参数作为文章标识符（ID 或 slug）
        $filteredArgs = array_filter($args, function($v) { return $v !== null && $v !== ''; });
        if (!empty($filteredArgs)) {
            $id = end($filteredArgs);
        }
        // 如果没有传入任何参数，尝试从 URL 查询字符串获取
        if ($id === null || $id === '') {
            $id = $_GET['id'] ?? null;
        }
        // 如果仍然没有 id，返回 404
        if ($id === null || $id === '') {
            $this->notFound();
            return;
        }
        // 检查缓存是否启用
        $cacheEnabled = Config::get('cache_enabled', '1');
        $cacheKey = 'page_article_' . $id;
        
        // 如果缓存启用，尝试从缓存中获取
        if ($cacheEnabled == 1 && !isset($_SESSION['user']) && !isset($_SESSION['success_message'])) {
            $cache = Cache::getInstance();
            $cachedContent = $cache->get($cacheKey);
            
            if ($cachedContent) {
                echo $cachedContent;
                return;
            }
        }
        
        // 初始化模板引擎
        $template = new Template();
        
        // 检测设备类型并传递给模板
        $deviceInfo = $this->detectDevice();
        $template->assign('device', $deviceInfo);
        
        // 分配用户会话数据到模板
        if (isset($_SESSION['user'])) {
            $template->assign('user', $_SESSION['user']);
        }
        
        // 使用模型类获取文章详情和侧边栏数据
        $articleModel = new ArticleModel();
        $tagModel = new TagModel();
        $categoryModel = new CategoryModel();
        $pageModel = new PageModel();
        $favoriteModel = new FavoriteModel();
        $likeModel = new LikeModel();
        $readHistoryModel = new ReadHistoryModel();
        $commentModel = new CommentModel();
        
        // 支持 slug 和 ID 查询：数字参数优先作为 ID，非数字作为 slug
        // （避免纯数字 slug 与文章 ID 冲突）
        if (is_numeric($id)) {
            // 数字参数 → 优先作为 ID 查询（ID 查询更常用且更快）
            $article = $articleModel->getArticleById((int)$id);
            if (!$article) {
                // ID 查询失败 → 尝试作为 slug 查询（兼容可能存在的纯数字 slug 数据）
                $article = $articleModel->getArticleBySlug($id);
            }
        } else {
            // 非数字参数 → 直接作为 slug 查询
            $article = $articleModel->getArticleBySlug($id);
        }
        // 更新缓存 key 使用实际的文章 ID
        if ($article) {
            $id = $article['id'];
            $cacheKey = 'page_article_' . $id;
        }
        
        // 检查文章是否存在
        if (!$article) {
            $this->notFound();
            return;
        }
        
        $articleTags = $tagModel->getTagsByArticleId($id);
        
        // 获取收藏相关数据
        $article['favorite_count'] = $favoriteModel->getFavoriteCount($id);
        $article['is_favorite'] = false;
        if (isset($_SESSION['user'])) {
            $article['is_favorite'] = $favoriteModel->checkFavorite($_SESSION['user']['id'], $id);
        }
        
        // 获取点赞相关数据
        $article['like_count'] = $likeModel->getLikeCount($id);
        $article['is_liked'] = false;
        if (isset($_SESSION['user'])) {
            $article['is_liked'] = $likeModel->checkLike($_SESSION['user']['id'], $id);
            // 添加阅读历史记录
            $readHistoryModel->addReadHistory($_SESSION['user']['id'], $id);
        }
        
        // 获取上一篇和下一篇文章
        $previousArticle = $articleModel->getPreviousArticle($id);
        $nextArticle = $articleModel->getNextArticle($id);
        
        // 获取侧边栏数据
        $categories = $categoryModel->getMenuCategories();
        $allTags = $tagModel->getAllTags();
        $latestArticles = $articleModel->getLatestArticles(5);
        
        // 获取相关文章
        $relatedArticles = $articleModel->getRelatedArticles($id, 5);
        
        // 批量增强相关文章数据（替代 N+1 循环查询）
        $relatedArticles = ArticleService::batchEnrichArticles($relatedArticles);
        
        // 准备面包屑数据
        $breadcrumb = [];
        
        // 添加分类到面包屑
        if (!empty($article['category_id'])) {
            // 获取分类的完整信息，包括slug
            $categoryInfo = $categoryModel->getCategoryById($article['category_id']);
            $breadcrumb[] = [
                'name' => $article['category_name'],
                'url' => $template->generateUrl('category', $categoryInfo)
            ];
        }
        
        // 添加文章到面包屑（没有链接）
        $breadcrumb[] = [
            'name' => '内容'
        ];
        
        // 检查是否有成功提示消息
        if (isset($_SESSION['success_message'])) {
            $template->assign('success', $_SESSION['success_message']);
            // 只显示一次，之后清除
            unset($_SESSION['success_message']);
        }
        
        // 传递消息配置到模板
        $template->assign('message_enabled', Config::get('message_enabled', '1'));
        $template->assign('message_duration', Config::get('message_duration', '3'));
        
        // 获取菜单页面
        $pages = $pageModel->getMenuPages();
        
        // 获取友情链接
        $friendlinkModel = new FriendlinkModel();
        $friendlinks = $friendlinkModel->getEnabledFriendlinks();
        
        // 生成文章的meta标签内容
        $metaDescription = $article['excerpt'];
        $metaKeywords = '';
        foreach ($articleTags as $tag) {
            $metaKeywords .= $tag['name'] . ',';
        }
        $metaKeywords = rtrim($metaKeywords, ',');
        $canonicalUrl = $template->generateUrl('article', ['id' => $id]);
        
        // 传递SEO设置到模板
        $seoData = [
            'article' => $article,
            'article_tags' => $articleTags
        ];
        $template->assign('seo_title', $this->parseSeoTemplate(Config::get('article_seo_title', '{article.title} - {article.category} - {site.name}'), $seoData));
        $template->assign('seo_description', $this->parseSeoTemplate(Config::get('article_seo_description', '{article.summary}'), $seoData));
        $template->assign('seo_keywords', $this->parseSeoTemplate(Config::get('article_seo_keywords', '{article.tags}'), $seoData));
        
        // 使用钩子过滤文章内容
        if (class_exists('Hook')) {
            $article['content'] = Hook::filter('article_content_render', $article['content']);
        }
        
        // 传递数据到模板
        $currentUserId = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;
        $template->assign('current_user_id', $currentUserId);
        $template->assign('article', $article);
        $template->assign('tags', $articleTags); // 当前文章的标签
        $template->assign('categories', $categories);
        $template->assign('all_tags', $allTags); // 所有标签，用于侧边栏
        $template->assign('latest_articles', $latestArticles);
        $template->assign('pages', $pages);
        $template->assign('friendlinks', $friendlinks);
        $template->assign('breadcrumb', $breadcrumb);
        $template->assign('previous_article', $previousArticle);
        $template->assign('next_article', $nextArticle);
        $template->assign('related_articles', $relatedArticles);
        
        // 获取文章评论
        $userId = isset($_SESSION['user']) ? $_SESSION['user']['id'] : 0;
        $commentsPerPage = (int)Config::get('pagination_article_comments_count', Config::get('pagination_count', 10));
        $comments = $commentModel->getCommentsByArticleId($id, 1, $commentsPerPage, $userId);
        $commentCount = $commentModel->getCommentCountByArticleId($id);
        
        // 传递评论数据到模板
        $template->assign('comments', $comments);
        $template->assign('comment_count', $commentCount);
        
        // 传递meta标签数据到模板
        $template->assign('meta_description', $metaDescription);
        $template->assign('meta_keywords', $metaKeywords);
        $template->assign('canonical_url', $canonicalUrl);
        
        // 如果缓存启用且没有用户会话和成功消息，保存到缓存
        if ($cacheEnabled == 1 && !isset($_SESSION['user']) && !isset($_SESSION['success_message'])) {
            ob_start();
            $template->display('article');
            $content = ob_get_clean();
            
            $cache = Cache::getInstance();
            $cache->set($cacheKey, $content, 7200); // 缓存2小时
            
            echo $content;
        } else {
            // 直接渲染
            $template->display('article');
        }
    }
    
    public function category() {
        // 支持多参数（如 cat/{category}/{page} 规则），取最后一个有效参数作为分类标识
        $args = func_get_args();
        $id = null;
        $filteredArgs = array_filter($args, function($v) { return $v !== null && $v !== ''; });
        if (!empty($filteredArgs)) {
            $id = end($filteredArgs);
        }
        if ($id === null || $id === '') {
            $id = $_GET['id'] ?? null;
        }
        if ($id === null || $id === '') {
            $this->notFound();
            return;
        }
        // 检查缓存是否启用
        $cacheEnabled = Config::get('cache_enabled', '1');
        $cacheKey = 'page_category_' . $id . '_' . (isset($_GET['page']) ? $_GET['page'] : '1') . '_' . (isset($_GET['filter']) ? $_GET['filter'] : 'latest');
        
        // 如果缓存启用，尝试从缓存中获取
        if ($cacheEnabled == 1 && !isset($_SESSION['user']) && !isset($_SESSION['success_message'])) {
            $cache = Cache::getInstance();
            $cachedContent = $cache->get($cacheKey);
            
            if ($cachedContent) {
                echo $cachedContent;
                return;
            }
        }
        
        // 初始化模板引擎
        $template = new Template();
        
        // 检测设备类型并传递给模板
        $deviceInfo = $this->detectDevice();
        $template->assign('device', $deviceInfo);
        
        // 分配用户会话数据到模板
        if (isset($_SESSION['user'])) {
            $template->assign('user', $_SESSION['user']);
        }
        
        // 使用模型类获取分类文章
        $articleModel = new ArticleModel();
        $categoryModel = new CategoryModel();
        $tagModel = new TagModel();
        $pageModel = new PageModel();
        $likeModel = new LikeModel();
        $favoriteModel = new FavoriteModel();
        
        // 优先尝试通过 slug 查找
        $category = $categoryModel->getCategoryBySlug($id);
        
        // 如果找不到，再尝试通过 ID 查找
        if (!$category && is_numeric($id)) {
            $category = $categoryModel->getCategoryById($id);
        }
        
        // 检查分类是否存在
        if (!$category) {
            $this->notFound();
            return;
        }
        
        // 使用分类的实际ID（无论传入的是ID还是slug）
        $categoryId = $category['id'];
        
        // 获取每页文章数配置
        $articles_per_page = Config::get('pagination_count', 10);
        
        // 获取分类文章总数
        $total_articles = $articleModel->getArticleCountByCategoryId($categoryId);
        
        // 初始化分页类
        $pagination = new Pagination($total_articles, $articles_per_page);
        
        // 获取筛选参数
        $filter = isset($_GET['filter']) ? $_GET['filter'] : 'latest';
        
        // 获取分类文章列表（排除置顶文章）
        $normalArticles = $articleModel->getArticlesByCategoryId($categoryId, $pagination->getCurrentPage(), $articles_per_page, $filter);
        
        // 获取置顶文章（全局置顶和分类置顶）
        $topArticles = $articleModel->getTopArticles([1, 3], $categoryId, 10);
        
        // 合并文章列表：置顶文章在前，普通文章在后
        $articles = array_merge($topArticles, $normalArticles);
        $categories = $categoryModel->getMenuCategories();
        $allTags = $tagModel->getAllTags();
        $latestArticles = $articleModel->getLatestArticles(5);
        
        // 批量增强文章数据（替代 N+1 循环查询）
        $articles = ArticleService::batchEnrichArticles($articles);
        
        // 准备面包屑数据
        $breadcrumb = [];
        
        // 添加分类到面包屑（没有链接）
        $breadcrumb[] = [
            'name' => $category['name']
        ];
        
        // 检查是否有成功提示消息
        if (isset($_SESSION['success_message'])) {
            $template->assign('success', $_SESSION['success_message']);
            // 只显示一次，之后清除
            unset($_SESSION['success_message']);
        }
        
        // 传递消息配置到模板
        $template->assign('message_enabled', Config::get('message_enabled', '1'));
        $template->assign('message_duration', Config::get('message_duration', '3'));
        
        // 获取菜单页面
        $pages = $pageModel->getMenuPages();
        
        // 获取友情链接
        $friendlinkModel = new FriendlinkModel();
        $friendlinks = $friendlinkModel->getEnabledFriendlinks();
        
        // 传递SEO设置到模板
        $categorySeoTitle = Config::get('category_seo_title', '');
        $categorySeoDesc = Config::get('category_seo_description', '');
        $categorySeoKeywords = Config::get('category_seo_keywords', '');
        
        if (empty($categorySeoTitle)) {
            $categorySeoTitle = $category['name'] . ' - ' . Config::get('site_name', 'My Blog');
        }
        
        if (empty($categorySeoDesc)) {
            $categorySeoDesc = !empty($category['description']) ? $category['description'] : Config::get('site_description', '');
        }
        
        if (empty($categorySeoKeywords)) {
            $categorySeoKeywords = !empty($category['keywords']) ? $category['keywords'] : Config::get('site_keywords', '');
        }
        
        $seoData = ['category' => $category];
        $template->assign('seo_title', $this->parseSeoTemplate($categorySeoTitle, $seoData));
        $template->assign('seo_description', $this->parseSeoTemplate($categorySeoDesc, $seoData));
        $template->assign('seo_keywords', $this->parseSeoTemplate($categorySeoKeywords, $seoData));
        
        // 传递数据到模板
        $template->assign('category', $category);
        $template->assign('articles', $articles);
        $template->assign('categories', $categories);
        $template->assign('all_tags', $allTags);
        $template->assign('latest_articles', $latestArticles);
        $template->assign('pages', $pages);
        $template->assign('friendlinks', $friendlinks);
        $template->assign('pagination', $pagination);
        $template->assign('pagination_info', $pagination->getPaginationInfo());
        $template->assign('breadcrumb', $breadcrumb);
        
        // 如果缓存启用且没有用户会话和成功消息，保存到缓存
        if ($cacheEnabled == 1 && !isset($_SESSION['user']) && !isset($_SESSION['success_message'])) {
            ob_start();
            $template->display('category');
            $content = ob_get_clean();
            
            $cache = Cache::getInstance();
            $cache->set($cacheKey, $content, 3600); // 缓存1小时
            
            echo $content;
        } else {
            // 直接渲染
            $template->display('category');
        }
    }
    
    public function tag() {
        // 支持多参数（如 tag/{tag}/{page} 规则），取最后一个有效参数作为标签标识
        $args = func_get_args();
        $id = null;
        $filteredArgs = array_filter($args, function($v) { return $v !== null && $v !== ''; });
        if (!empty($filteredArgs)) {
            $id = end($filteredArgs);
        }
        if ($id === null || $id === '') {
            $id = $_GET['id'] ?? null;
        }
        if ($id === null || $id === '') {
            $this->notFound();
            return;
        }
        // 检查缓存是否启用
        $cacheEnabled = Config::get('cache_enabled', '1');
        $cacheKey = 'page_tag_' . $id . '_' . (isset($_GET['page']) ? $_GET['page'] : '1');
        
        // 如果缓存启用，尝试从缓存中获取
        if ($cacheEnabled == 1 && !isset($_SESSION['user']) && !isset($_SESSION['success_message'])) {
            $cache = Cache::getInstance();
            $cachedContent = $cache->get($cacheKey);
            
            if ($cachedContent) {
                echo $cachedContent;
                return;
            }
        }
        
        // 初始化模板引擎
        $template = new Template();
        
        // 检测设备类型并传递给模板
        $deviceInfo = $this->detectDevice();
        $template->assign('device', $deviceInfo);
        
        // 分配用户会话数据到模板
        if (isset($_SESSION['user'])) {
            $template->assign('user', $_SESSION['user']);
        }
        
        // 使用模型类获取标签文章
        $articleModel = new ArticleModel();
        $categoryModel = new CategoryModel();
        $tagModel = new TagModel();
        $pageModel = new PageModel();
        $likeModel = new LikeModel();
        $favoriteModel = new FavoriteModel();
        
        $tag = $tagModel->getTagById($id);
        
        // 检查标签是否存在
        if (!$tag) {
            $this->notFound();
            return;
        }
        
        // 获取每页文章数配置
        $articles_per_page = Config::get('pagination_tag_count', Config::get('pagination_count', 10));
        
        // 获取标签文章总数
        $total_articles = $articleModel->getArticleCountByTagId($id);
        
        // 初始化分页类
        $pagination = new Pagination($total_articles, $articles_per_page);
        
        // 获取标签文章列表
        $articles = $articleModel->getArticlesByTagId($id, $pagination->getCurrentPage(), $articles_per_page);
        $categories = $categoryModel->getMenuCategories();
        $allTags = $tagModel->getAllTags();
        $latestArticles = $articleModel->getLatestArticles(5);
        
        // 批量增强文章数据（替代 N+1 循环查询）
        $articles = ArticleService::batchEnrichArticles($articles);
        
        // 准备面包屑数据
        $breadcrumb = [];
        
        // 添加标签列表到面包屑
        $breadcrumb[] = [
            'name' => '标签列表',
            'url' => $template->generateUrl('tags')
        ];
        
        // 添加标签到面包屑（没有链接）
        $breadcrumb[] = [
            'name' => $tag['name']
        ];
        
        // 检查是否有成功提示消息
        if (isset($_SESSION['success_message'])) {
            $template->assign('success', $_SESSION['success_message']);
            // 只显示一次，之后清除
            unset($_SESSION['success_message']);
        }
        
        // 传递消息配置到模板
        $template->assign('message_enabled', Config::get('message_enabled', '1'));
        $template->assign('message_duration', Config::get('message_duration', '3'));
        
        // 获取菜单页面
        $pages = $pageModel->getMenuPages();
        
        // 获取友情链接
        $friendlinkModel = new FriendlinkModel();
        $friendlinks = $friendlinkModel->getEnabledFriendlinks();
        
        // 传递SEO设置到模板
        $seoData = ['tag' => $tag];
        $template->assign('seo_title', $this->parseSeoTemplate(Config::get('tag_seo_title', '{tag.name} - {site.name}'), $seoData));
        $template->assign('seo_description', $this->parseSeoTemplate(Config::get('tag_seo_description', '{site.description}'), $seoData));
        $template->assign('seo_keywords', $this->parseSeoTemplate(Config::get('tag_seo_keywords', '{tag.name} {site.keywords}'), $seoData));
        
        // 传递数据到模板
        $template->assign('tag', $tag);
        $template->assign('articles', $articles);
        $template->assign('categories', $categories);
        $template->assign('all_tags', $allTags);
        $template->assign('latest_articles', $latestArticles);
        $template->assign('pages', $pages);
        $template->assign('friendlinks', $friendlinks);
        $template->assign('pagination', $pagination);
        $template->assign('pagination_info', $pagination->getPaginationInfo());
        $template->assign('breadcrumb', $breadcrumb);
        
        // 如果缓存启用且没有用户会话和成功消息，保存到缓存
        if ($cacheEnabled == 1 && !isset($_SESSION['user']) && !isset($_SESSION['success_message'])) {
            ob_start();
            $template->display('tag');
            $content = ob_get_clean();
            
            $cache = Cache::getInstance();
            $cache->set($cacheKey, $content, 3600); // 缓存1小时
            
            echo $content;
        } else {
            // 直接渲染
            $template->display('tag');
        }
    }
    
    public function page() {
        // 支持多参数（如 page/{slug} 规则），取最后一个有效参数作为页面标识
        $args = func_get_args();
        $id = null;
        $filteredArgs = array_filter($args, function($v) { return $v !== null && $v !== ''; });
        if (!empty($filteredArgs)) {
            $id = end($filteredArgs);
        }
        if ($id === null || $id === '') {
            $id = $_GET['id'] ?? null;
        }
        if ($id === null || $id === '') {
            $this->notFound();
            return;
        }
        // 检查缓存是否启用
        $cacheEnabled = Config::get('cache_enabled', '1');
        $cacheKey = 'page_page_' . $id;
        
        // 如果缓存启用，尝试从缓存中获取
        if ($cacheEnabled == 1 && !isset($_SESSION['user']) && !isset($_SESSION['success_message'])) {
            $cache = Cache::getInstance();
            $cachedContent = $cache->get($cacheKey);
            
            if ($cachedContent) {
                echo $cachedContent;
                return;
            }
        }
        
        // 初始化模板引擎
        $template = new Template();
        
        // 检测设备类型并传递给模板
        $deviceInfo = $this->detectDevice();
        $template->assign('device', $deviceInfo);
        
        // 分配用户会话数据到模板
        if (isset($_SESSION['user'])) {
            $template->assign('user', $_SESSION['user']);
        }
        
        // 使用模型类获取页面数据和侧边栏数据
        $pageModel = new PageModel();
        $categoryModel = new CategoryModel();
        $tagModel = new TagModel();
        $articleModel = new ArticleModel();
        
        // 优先尝试通过别名获取页面
        $page = $pageModel->getPageBySlug($id);
        
        // 如果通过别名没找到，再尝试通过ID获取
        if (!$page && is_numeric($id)) {
            $page = $pageModel->getPageById($id);
        }
        
        // 如果页面不存在，返回404
        if (!$page) {
            $this->notFound();
            return;
        }
        
        // 获取侧边栏数据
        $categories = $categoryModel->getMenuCategories();
        $allTags = $tagModel->getAllTags();
        $latestArticles = $articleModel->getLatestArticles(5);
        
        // 准备面包屑数据
        $breadcrumb = [];
        
        // 添加页面到面包屑（没有链接）
        $breadcrumb[] = [
            'name' => $page['title']
        ];
        
        // 检查是否有成功提示消息
        if (isset($_SESSION['success_message'])) {
            $template->assign('success', $_SESSION['success_message']);
            // 只显示一次，之后清除
            unset($_SESSION['success_message']);
        }
        
        // 传递消息配置到模板
        $template->assign('message_enabled', Config::get('message_enabled', '1'));
        $template->assign('message_duration', Config::get('message_duration', '3'));
        
        // 获取菜单页面
        $pages = $pageModel->getMenuPages();
        
        // 获取友情链接
        $friendlinkModel = new FriendlinkModel();
        $friendlinks = $friendlinkModel->getEnabledFriendlinks();
        
        // 传递SEO设置到模板
        $pageSeoTitle = Config::get('page_seo_title', '');
        $pageSeoDesc = Config::get('page_seo_description', '');
        $pageSeoKeywords = Config::get('page_seo_keywords', '');
        
        if (empty($pageSeoTitle)) {
            $pageSeoTitle = $page['title'] . ' - ' . Config::get('site_name', 'My Blog');
        }
        
        if (empty($pageSeoDesc)) {
            $pageSeoDesc = !empty($page['description']) ? $page['description'] : Config::get('site_description', '');
        }
        
        if (empty($pageSeoKeywords)) {
            $pageSeoKeywords = !empty($page['keywords']) ? $page['keywords'] : Config::get('site_keywords', '');
        }
        
        $seoData = ['page' => $page];
        $template->assign('seo_title', $this->parseSeoTemplate($pageSeoTitle, $seoData));
        $template->assign('seo_description', $this->parseSeoTemplate($pageSeoDesc, $seoData));
        $template->assign('seo_keywords', $this->parseSeoTemplate($pageSeoKeywords, $seoData));
        
        // 使用钩子过滤页面内容
        if (class_exists('Hook')) {
            $page['content'] = Hook::filter('page_content_render', $page['content']);
        }
        
        // 传递数据到模板
        $template->assign('page', $page);
        $template->assign('categories', $categories);
        $template->assign('all_tags', $allTags);
        $template->assign('latest_articles', $latestArticles);
        $template->assign('pages', $pages);
        $template->assign('friendlinks', $friendlinks);
        $template->assign('breadcrumb', $breadcrumb);
        
        // 如果缓存启用且没有用户会话和成功消息，保存到缓存
        if ($cacheEnabled == 1 && !isset($_SESSION['user']) && !isset($_SESSION['success_message'])) {
            ob_start();
            $template->display('page');
            $content = ob_get_clean();
            
            $cache = Cache::getInstance();
            $cache->set($cacheKey, $content, 10800); // 缓存3小时
            
            echo $content;
        } else {
            // 直接渲染
            $template->display('page');
        }
    }
    
    public function search() {
        $template = $this->initHomeTemplate();
        
        $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
        $sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'relevance';
        
        // 验证排序参数白名单
        $validSorts = ['relevance', 'newest', 'oldest', 'most-viewed', 'most-liked', 'most-favorited', 'most-commented'];
        if (!in_array($sort, $validSorts)) {
            $sort = 'relevance';
        }
        
        // ==================== 搜索内容限制开始 ====================
        $searchError = $this->validateSearchKeyword($keyword);
        if ($searchError !== null) {
            $this->assignSearchError($template, $searchError, $keyword);
            $template->display('search');
            return;
        }
        // ==================== 搜索内容限制结束 ====================
        
        if (empty($keyword)) {
            header('Location: /');
            exit;
        }
        
        $articleModel = new ArticleModel();
        $articles_per_page = Config::get('pagination_search_count', Config::get('pagination_count', 10));
        $total_articles = $articleModel->getSearchArticleCount($keyword);
        $pagination = new Pagination($total_articles, $articles_per_page);
        $articles = $articleModel->searchArticles($keyword, $pagination->getCurrentPage(), $articles_per_page, $sort);
        $articles = ArticleService::batchEnrichArticles($articles);
        
        // 侧边栏数据
        $this->assignSidebarData($template);
        
        // 面包屑
        $breadcrumb[] = ['name' => '搜索结果', 'url' => '#'];
        
        // SEO
        $seoData = ['search' => ['keyword' => $keyword]];
        $template->assign('seo_title', $this->parseSeoTemplate(Config::get('search_seo_title', '搜索: {search.keyword} - {site.name}'), $seoData));
        $template->assign('seo_description', $this->parseSeoTemplate(Config::get('search_seo_description', '搜索结果: {search.keyword} - {site.description}'), $seoData));
        $template->assign('seo_keywords', $this->parseSeoTemplate(Config::get('search_seo_keywords', '{search.keyword} {site.keywords}'), $seoData));
        
        // 排序选项
        $sortOptions = [
            ['value' => 'relevance', 'name' => '相关性', 'selected' => $sort == 'relevance'],
            ['value' => 'newest', 'name' => '最新', 'selected' => $sort == 'newest'],
            ['value' => 'oldest', 'name' => '最早', 'selected' => $sort == 'oldest'],
            ['value' => 'most-viewed', 'name' => '浏览最多', 'selected' => $sort == 'most-viewed'],
            ['value' => 'most-liked', 'name' => '点赞最多', 'selected' => $sort == 'most-liked'],
            ['value' => 'most-favorited', 'name' => '收藏最多', 'selected' => $sort == 'most-favorited'],
            ['value' => 'most-commented', 'name' => '评论最多', 'selected' => $sort == 'most-commented']
        ];
        
        $template->assign('keyword', $keyword);
        $template->assign('sort', $sort);
        $template->assign('sort_options', $sortOptions);
        $template->assign('search', ['keyword' => $keyword]);
        $template->assign('articles', $articles);
        $template->assign('total_articles', $total_articles);
        $template->assign('has_search_results', $total_articles > 0);
        $template->assign('pagination', $pagination);
        $template->assign('pagination_info', $pagination->getPaginationInfo());
        $template->assign('breadcrumb', $breadcrumb);
        
        $template->display('search');
    }
    
    public function tags() {
        $cacheKey = 'page_tags';
        if ($this->tryCache($cacheKey)) {
            return;
        }
        
        $template = $this->initHomeTemplate();
        
        $tagModel = new TagModel();
        $template->assign('tags', $tagModel->getAllTags());
        
        // 侧边栏数据
        $this->assignSidebarData($template);
        
        // 面包屑
        $breadcrumb[] = ['name' => '标签列表'];
        
        // SEO
        $template->assign('seo_title', $this->parseSeoTemplate('标签列表 - {site.name}'));
        $template->assign('seo_description', $this->parseSeoTemplate('所有标签列表 - {site.description}'));
        $template->assign('seo_keywords', $this->parseSeoTemplate('标签,标签列表,{site.keywords}'));
        $template->assign('breadcrumb', $breadcrumb);
        
        $this->cacheAndDisplay($cacheKey, $template, 'tags');
    }
    
    // 处理收藏功能的AJAX请求
    public function favorite() {
        // 设置响应头为JSON格式
        header('Content-Type: application/json');
        
        // 检查用户是否登录
        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => false, 'message' => '请先登录']);
            exit;
        }

        // 新用户限制检查
        if (AuthController::isNewUserRestricted() && Config::get('register_new_user_ban_favorite', 0)) {
            echo json_encode(['success' => false, 'message' => '新用户注册期间暂不允许收藏，请稍后再试']);
            exit;
        }
        
        // 获取文章ID
        $articleId = isset($_POST['article_id']) ? intval($_POST['article_id']) : 0;
        if (!$articleId) {
            echo json_encode(['success' => false, 'message' => '文章ID无效']);
            exit;
        }
        
        // 获取操作类型
        $action = isset($_POST['action']) ? trim($_POST['action']) : 'add';
        
        // 处理收藏操作
        $favoriteModel = new FavoriteModel();
        $userId = $_SESSION['user']['id'];
        
        try {
            if ($action === 'add') {
                $result = $favoriteModel->addFavorite($userId, $articleId);
                $message = '收藏成功';
                // 收藏成功后，发送通知给文章作者
                if ($result && class_exists('NotificationService')) {
                    NotificationService::notifyArticleFavorite($articleId, $userId);
                }
            } else {
                $result = $favoriteModel->removeFavorite($userId, $articleId);
                $message = '取消收藏成功';
            }
            
            if ($result) {
                // 获取更新后的收藏数
                $favoriteCount = $favoriteModel->getFavoriteCount($articleId);
                echo json_encode([
                    'success' => true,
                    'message' => $message,
                    'favorite_count' => $favoriteCount,
                    'action' => $action
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => '操作失败']);
            }
        } catch (Exception $e) {
            if (class_exists('Log')) { Log::error('Favorite error', 'home', ['message' => $e->getMessage()]); }
            else { error_log('Favorite error: ' . $e->getMessage()); }
            echo json_encode(['success' => false, 'message' => '服务器繁忙，请稍后重试']);
        }
        exit;
    }
    
    // 处理点赞功能的AJAX请求
    public function like() {
        // 设置响应头为JSON格式
        header('Content-Type: application/json');
        
        // 检查用户是否登录
        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => false, 'message' => '请先登录']);
            exit;
        }

        // 新用户限制检查
        if (AuthController::isNewUserRestricted() && Config::get('register_new_user_ban_like', 0)) {
            echo json_encode(['success' => false, 'message' => '新用户注册期间暂不允许点赞，请稍后再试']);
            exit;
        }
        
        // 获取文章ID
        $articleId = isset($_POST['article_id']) ? intval($_POST['article_id']) : 0;
        if (!$articleId) {
            echo json_encode(['success' => false, 'message' => '文章ID无效']);
            exit;
        }
        
        // 获取操作类型
        $action = isset($_POST['action']) ? trim($_POST['action']) : 'add';
        
        // 处理点赞操作
        $likeModel = new LikeModel();
        $userId = $_SESSION['user']['id'];
        
        try {
            if ($action === 'add') {
                $result = $likeModel->addLike($userId, $articleId);
                $message = '点赞成功';
                // 点赞成功后，发送通知给文章作者
                if ($result && class_exists('NotificationService')) {
                    NotificationService::notifyArticleLike($articleId, $userId);
                }
            } else {
                $result = $likeModel->removeLike($userId, $articleId);
                $message = '取消点赞成功';
            }

            if ($result) {
                // 获取更新后的点赞数
                $likeCount = $likeModel->getLikeCount($articleId);
                echo json_encode([
                    'success' => true,
                    'message' => $message,
                    'like_count' => $likeCount,
                    'action' => $action
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => '操作失败']);
            }
        } catch (Exception $e) {
            if (class_exists('Log')) { Log::error('Like error', 'home', ['message' => $e->getMessage()]); }
            else { error_log('Like error: ' . $e->getMessage()); }
            echo json_encode(['success' => false, 'message' => '服务器繁忙，请稍后重试']);
        }
        exit;
    }

public function user() {
        // 支持多参数（如 user/{id} 规则），取最后一个有效参数作为用户标识
        $args = func_get_args();
        $id = null;
        $filteredArgs = array_filter($args, function($v) { return $v !== null && $v !== ''; });
        if (!empty($filteredArgs)) {
            $id = end($filteredArgs);
        }
        if ($id === null || $id === '') {
            $id = $_GET['id'] ?? null;
        }
        if ($id === null || $id === '') {
            $this->notFound();
            return;
        }
        // 初始化模板引擎
        $template = new Template();
        
        // 检测设备类型并传递给模板
        $deviceInfo = $this->detectDevice();
        $template->assign('device', $deviceInfo);
        
        // 分配用户会话数据到模板
        if (isset($_SESSION['user'])) {
            $session_user = $_SESSION['user'];
        } else {
            $session_user = [];
        }
        
        // 使用模型类获取用户数据
        $articleModel = new ArticleModel();
        $categoryModel = new CategoryModel();
        $tagModel = new TagModel();
        $pageModel = new PageModel();
        $likeModel = new LikeModel();
        $favoriteModel = new FavoriteModel();
        
        // 从数据库获取真实用户数据
        $userModel = new UserModel();
        $user = $userModel->getUserById($id);
        
        if (!$user) {
            $this->notFound();
            return;
        }
        
        // 移除敏感字段，避免泄露到前端模板
        unset($user['password'], $user['email']);
        
        // 格式化时间戳为可读日期
        $user['created_at_formatted'] = date('Y-m-d H:i:s', $user['created_at'] ?? time());
        
        // 获取用户文章列表
        $articles_per_page = Config::get('pagination_count', 10);
        $total_articles = $articleModel->getArticleCountByUserId($id);
        $pagination = new Pagination($total_articles, $articles_per_page);
        $articles = $articleModel->getArticlesByUserId($id, $pagination->getCurrentPage(), $articles_per_page);
    
    // 批量增强文章数据（替代 N+1 循环查询）
    $articles = ArticleService::batchEnrichArticles($articles);
    
    // 获取侧边栏数据
    $categories = $categoryModel->getMenuCategories();
    $allTags = $tagModel->getAllTags();
    $latestArticles = $articleModel->getLatestArticles(5);
    
    // 准备面包屑数据
    $breadcrumb = [];
    $breadcrumb[] = [
        'name' => $user['nickname'] . '的主页'
    ];
    
    // 传递消息配置到模板
    $template->assign('message_enabled', Config::get('message_enabled', '1'));
    $template->assign('message_duration', Config::get('message_duration', '3'));
    
    // 获取菜单页面
    $pages = $pageModel->getMenuPages();
    
    // 获取友情链接
    $friendlinkModel = new FriendlinkModel();
    $friendlinks = $friendlinkModel->getEnabledFriendlinks();
    
    // 传递SEO设置到模板
        $seoData = ['user' => $user];
        $template->assign('seo_title', $this->parseSeoTemplate(Config::get('user_seo_title', '{user.nickname} - {site.name}'), $seoData));
        $template->assign('seo_description', $this->parseSeoTemplate(Config::get('user_seo_description', '{user.nickname}的主页'), $seoData));
        $template->assign('seo_keywords', $this->parseSeoTemplate(Config::get('user_seo_keywords', '{user.nickname} {site.keywords}'), $seoData));
    
    // 传递数据到模板
    $template->assign('user', $user); // 用于SEO模板标签，保持与模板中的{user.nickname}一致
    $template->assign('session_user', $session_user); // 会话用户信息
    $template->assign('articles', $articles);
    $template->assign('categories', $categories);
    $template->assign('all_tags', $allTags);
    $template->assign('latest_articles', $latestArticles);
    $template->assign('pages', $pages);
    $template->assign('friendlinks', $friendlinks);
    $template->assign('pagination', $pagination);
    $template->assign('pagination_info', $pagination->getPaginationInfo());
    $template->assign('breadcrumb', $breadcrumb);
    
    // 渲染用户主页模板
    $template->display('user');
}

public function notFound() {
    // 设置404响应头
    header('HTTP/1.1 404 Not Found');
    
    $template = $this->initHomeTemplate();
    $this->assignSidebarData($template);
    
    // SEO
    $template->assign('seo_title', $this->parseSeoTemplate(Config::get('404_seo_title', '404 - 页面未找到')));
    $template->assign('seo_description', $this->parseSeoTemplate(Config::get('404_seo_description', '您访问的页面不存在或已被删除')));
    $template->assign('seo_keywords', $this->parseSeoTemplate(Config::get('404_seo_keywords', '404,页面未找到')));
    
    $template->display('404');
}

/**
     * 文章归档页面：按年月分组显示所有已发布文章
     */
    public function archives() {
        // 检查缓存
        $cacheEnabled = Config::get('cache_enabled', '1');
        $cacheKey = 'page_archives';
        
        if ($cacheEnabled == 1 && !isset($_SESSION['user']) && !isset($_SESSION['success_message'])) {
            $cache = Cache::getInstance();
            $cachedContent = $cache->get($cacheKey);
            if ($cachedContent) { echo $cachedContent; return; }
        }
        
        $template = new Template();
        $template->assign('device', $this->detectDevice());
        if (isset($_SESSION['user'])) { $template->assign('user', $_SESSION['user']); }
        
        $articleModel = new ArticleModel();
        $categoryModel = new CategoryModel();
        $tagModel = new TagModel();
        $pageModel = new PageModel();
        
        // 获取所有已发布文章（按创建时间倒序）
        $db = Database::getInstance();
        $prefix = Config::get('database')['prefix'];
        $articles = $db->fetchAll(
            "SELECT a.id, a.title, a.created_at, c.name as category_name, c.slug as category_slug
             FROM {$prefix}article a
             LEFT JOIN {$prefix}category c ON a.category_id = c.id
             WHERE a.status = 1 AND a.is_deleted = 0
             ORDER BY a.created_at DESC",
            [], true
        );
        
        // 按年月分组
        $archives = [];
        foreach ($articles as $article) {
            $year = date('Y', $article['created_at']);
            $month = date('m', $article['created_at']);
            if (!isset($archives[$year])) { $archives[$year] = []; }
            if (!isset($archives[$year][$month])) { $archives[$year][$month] = []; }
            $archives[$year][$month][] = $article;
        }
        
        // 侧边栏数据
        $categories = $categoryModel->getMenuCategories();
        $allTags = $tagModel->getAllTags();
        $latestArticles = $articleModel->getLatestArticles(5);
        $pages = $pageModel->getMenuPages();
        
        // 获取友情链接（侧边栏友情链接模块需要）
        $friendlinkModel = new FriendlinkModel();
        $friendlinks = $friendlinkModel->getEnabledFriendlinks();
        
        $template->assign('archives', $archives);
        $template->assign('total_articles', count($articles));
        $template->assign('categories', $categories);
        $template->assign('all_tags', $allTags);
        $template->assign('latest_articles', $latestArticles);
        $template->assign('pages', $pages);
        $template->assign('friendlinks', $friendlinks);
        $template->assign('seo_title', $this->parseSeoTemplate(Config::get('archives_seo_title', '文章归档 - {site.name}')));
        $template->assign('seo_description', $this->parseSeoTemplate(Config::get('archives_seo_description', '按时间线浏览所有文章')));
        $template->assign('breadcrumb', [['name' => '文章归档']]);
        $template->assign('message_enabled', Config::get('message_enabled', '1'));
        $template->assign('message_duration', Config::get('message_duration', '3'));
        
        if ($cacheEnabled == 1 && !isset($_SESSION['user']) && !isset($_SESSION['success_message'])) {
            ob_start();
            $template->display('archives');
            $content = ob_get_clean();
            $cache = Cache::getInstance();
            $cache->set($cacheKey, $content, 3600);
            echo $content;
        } else {
            $template->display('archives');
        }
    }
    
    /**
     * 验证搜索关键词的合法性
     * @param string $keyword
     * @return array|null 错误信息数组或null（验证通过）
     */
    private function validateSearchKeyword($keyword) {
        // 获取搜索配置
        $minLength = Config::get('search_min_length', 1);
        $maxLength = Config::get('search_max_length', 100);
        $enableBlacklist = Config::get('search_enable_blacklist', '1');
        $enableCodeFilter = Config::get('search_enable_code_filter', '1');
        
        // 1. 长度验证
        $length = mb_strlen($keyword, 'UTF-8');
        if ($length < $minLength) {
            return [
                'code' => 'TOO_SHORT',
                'message' => "搜索关键词太短，请至少输入 {$minLength} 个字符",
                'keyword' => $keyword
            ];
        }
        if ($length > $maxLength) {
            return [
                'code' => 'TOO_LONG',
                'message' => "搜索关键词太长，请限制在 {$maxLength} 个字符以内",
                'keyword' => mb_substr($keyword, 0, $maxLength, 'UTF-8')
            ];
        }
        
        // 2. 空内容验证（仅空白字符）
        if (preg_match('/^\s*$/u', $keyword)) {
            return [
                'code' => 'EMPTY',
                'message' => '请输入有效的搜索内容',
                'keyword' => ''
            ];
        }
        
        // 3. 黑名单验证
        if ($enableBlacklist == '1') {
            $blacklistCheck = $this->checkSearchBlacklist($keyword);
            if ($blacklistCheck !== null) {
                return $blacklistCheck;
            }
        }
        
        // 4. 代码检测验证
        if ($enableCodeFilter == '1') {
            $codeCheck = $this->checkSearchCodePattern($keyword);
            if ($codeCheck !== null) {
                return $codeCheck;
            }
        }
        
        // 验证通过
        return null;
    }
    
    /**
     * 检查搜索关键词是否在黑名单中
     * @param string $keyword
     * @return array|null
     */
    private function checkSearchBlacklist($keyword) {
        $blacklist = Config::get('search_blacklist', '');
        if (empty($blacklist)) {
            return null;
        }
        
        $keywords = explode("\n", $blacklist);
        foreach ($keywords as $banned) {
            $banned = trim($banned);
            if (!empty($banned) && stripos($keyword, $banned) !== false) {
                return [
                    'code' => 'BLACKLISTED',
                    'message' => '搜索关键词包含受限内容，请尝试其他关键词',
                    'keyword' => $keyword
                ];
            }
        }
        
        return null;
    }
    
    /**
     * 检测搜索关键词是否包含代码模式
     * @param string $keyword
     * @return array|null
     */
    private function checkSearchCodePattern($keyword) {
        $patterns = [
            // SQL注入特征
            '/\b(SELECT|INSERT|UPDATE|DELETE|DROP|UNION|ALTER|TRUNCATE|EXEC|OR\s+1\s*=\s*1)\b/i',
            // 脚本标签
            '/<script\b[^>]*>/i',
            '/javascript:/i',
            // XSS特征
            '/on\w+\s*=/i',
            // PHP代码
            '/<\?php/i',
            '/<\?=/i',
            // 命令注入
            '/\b(system|exec|shell_exec|passthru|popen|proc_open)\s*\(/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $keyword)) {
                return [
                    'code' => 'CODE_PATTERN',
                    'message' => '搜索内容可能包含恶意代码，请重新输入',
                    'keyword' => preg_replace('/[^\p{L}\p{N}\s]/u', '', $keyword)
                ];
            }
        }
        
        return null;
    }
    
    /**
     * 为模板分配搜索错误数据
     * @param Template $template
     * @param array $error
     * @param string $originalKeyword
     */
    private function assignSearchError($template, $error, $originalKeyword) {
        $sortOptions = [
            ['value' => 'relevance', 'name' => '相关性', 'selected' => true],
            ['value' => 'newest', 'name' => '最新', 'selected' => false],
            ['value' => 'oldest', 'name' => '最早', 'selected' => false],
            ['value' => 'most-viewed', 'name' => '浏览最多', 'selected' => false],
            ['value' => 'most-liked', 'name' => '点赞最多', 'selected' => false],
            ['value' => 'most-favorited', 'name' => '收藏最多', 'selected' => false],
            ['value' => 'most-commented', 'name' => '评论最多', 'selected' => false]
        ];
        
        // 侧边栏数据
        $this->assignSidebarData($template);
        
        $template->assign('keyword', $originalKeyword);
        $template->assign('sort', 'relevance');
        $template->assign('sort_options', $sortOptions);
        $template->assign('articles', []);
        $template->assign('total_articles', 0);
        $template->assign('has_search_results', false);
        $template->assign('pagination', null);
        $template->assign('pagination_info', null);
        $template->assign('breadcrumb', [['name' => '搜索结果', 'url' => '#']]);
        $template->assign('search_error', $error);
        
        // SEO
        $seoData = ['search' => ['keyword' => $originalKeyword]];
        $template->assign('seo_title', $this->parseSeoTemplate('搜索错误 - {site.name}'));
        $template->assign('seo_description', $this->parseSeoTemplate('搜索遇到问题 - {site.description}'));
    }
}