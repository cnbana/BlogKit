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


// 包含分页类和模型
if (!class_exists('Pagination')) {
}

if (!class_exists('ArticleModel')) {
}

if (!class_exists('CategoryModel')) {
}

if (!class_exists('TagModel')) {
}

if (!class_exists('Plugin')) {
}

class ArticleController {
    
    /**
     * 从文章内容中提取第一张图片
     * @param string $content 文章内容
     * @return string 第一张图片的URL，如果没有图片则返回空字符串
     */
    /**
     * 从文章内容中提取第一张图片
     * 支持格式：HTML <img> 标签、Markdown ![]() 语法
     * 优先级：自定义封面 → HTML img → Markdown img → 默认封面
     * @param string $content 文章内容
     * @return string 第一张图片的URL，如果没有图片则返回空字符串
     */
    private function extractFirstImage($content) {
        if (empty($content)) {
            return '';
        }

        // 1. 匹配 HTML <img> 标签中的 src 属性
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches)) {
            $url = trim($matches[1]);
            if ($this->isValidImageUrl($url)) {
                return $url;
            }
        }

        // 2. 匹配 Markdown 图片格式 ![alt](url) 或 ![alt](url "title")
        if (preg_match('/!\[.*?\]\(\s*([^\s\)"\']+)(?:\s+"[^"]*")?\s*\)/i', $content, $matches)) {
            $url = trim($matches[1]);
            if ($this->isValidImageUrl($url)) {
                return $url;
            }
        }

        return '';
    }

    /**
     * 验证是否为有效的图片URL（过滤常见非图片链接）
     * @param string $url
     * @return bool
     */
    private function isValidImageUrl($url) {
        if (empty($url)) {
            return false;
        }
        // 排除SVG/XML/空白/占位符/Base64数据过长的情况
        $urlLower = strtolower($url);
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        // 常见图片扩展名白名单
        $validExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'ico'];
        if (!empty($ext) && !in_array($ext, $validExtensions)) {
            return false;
        }

        // Base64 data URI 也接受（但只保留小尺寸的）
        if (strpos($urlLower, 'data:image/') === 0) {
            return strlen($url) < 102400; // 小于100KB的base64图片
        }

        return true;
    }
    
    public function index() {
        // 获取分页参数
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

        // 每页条数：统一走 ListQuery 助手（URL limit 参数白名单校验，回退系统配置）
        $pageSize = ListQuery::pageSize();
        $allowedPageSizes = ListQuery::pageSizes();

        // 表头排序参数：sort 为字段名，order 为升降序，白名单校验防止 SQL 注入
        list($sort, $order) = ListQuery::sort(['id', 'created_at', 'updated_at', 'view_count']);

        // 获取筛选参数
        $filters = [];
        if (isset($_GET['category']) && !empty($_GET['category'])) {
            $filters['category'] = (int)$_GET['category'];
        }
        if (isset($_GET['status']) && $_GET['status'] !== '') {
            $filters['status'] = (int)$_GET['status'];
        }
        if (isset($_GET['top_type']) && $_GET['top_type'] !== '') {
            $filters['top_type'] = (int)$_GET['top_type'];
        }
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $filters['search'] = trim($_GET['search']);
        }
        // 时间范围筛选
        if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
            $filters['date_from'] = strtotime($_GET['date_from'] . ' 00:00:00');
        }
        if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
            $filters['date_to'] = strtotime($_GET['date_to'] . ' 23:59:59');
        }
        
        // 实例化模型
        $articleModel = new ArticleModel();
        $categoryModel = new CategoryModel();
        
        // 获取文章列表（包含草稿），传入表头排序参数
        $articles = $articleModel->getArticles($page, $pageSize, true, $filters, 'latest', $sort, $order);
        $total_articles = $articleModel->getArticleCount(true, $filters);
        
        // 获取分类列表（用于筛选）
        $categories = $categoryModel->getAllCategories();
        
        // 初始化分页类
        $pagination = new Pagination($total_articles, $pageSize, '', 'page');
        
        // 时间格式已经在模型中转换过，不需要重复转换
        
        // 将分页数据传递给模板
        $pagination_html = $pagination->createLinks();
        $pagination_info = "第{$pagination->getCurrentPage()}页，共{$pagination->getTotalPages()}页，总{$pagination->getTotal()}条记录";
        
        // 计算总页数（用于公共分页组件）
        $totalPages = ceil($total_articles / $pageSize);
        
        // 显示文章列表
        include ADMIN_PATH . '/templates/article.html';
    }
    
    public function add() {
        // 实例化模型
        $categoryModel = new CategoryModel();
        $tagModel = new TagModel();
        
        // 获取所有分类
        $categories = $categoryModel->getAllCategories();
        
        // 触发文章添加页面加载前的钩子
        Plugin::triggerHook('article_add_before');
        
        
        // 处理表单提交
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // 新用户限制检查
            if (AuthController::isNewUserRestricted() && (int)Config::get('register_new_user_ban_post', 0)) {
                $error = '新账号在限制期内暂不允许发布文章，请稍后再试';
            } else {
            // 触发文章保存前的钩子
            Plugin::triggerHook('article_save_before', array('data' => $_POST));
            
            // 验证表单数据
            if (empty($_POST['title']) || empty($_POST['content']) || empty($_POST['category_id'])) {
                $error = '标题、内容和分类不能为空';
            } elseif (mb_strlen(trim($_POST['title']), 'UTF-8') > 60) {
                $error = '标题最多 60 个字符（约 30 个汉字）';
            } elseif (isset($_POST['description']) && mb_strlen(trim($_POST['description']), 'UTF-8') > 200) {
                $error = '文章描述最多 200 个字符（约 100 个汉字）';
            } elseif (isset($_POST['slug']) && !empty(trim($_POST['slug'])) && !preg_match('/^[a-zA-Z0-9_-]+$/', trim($_POST['slug']))) {
                $error = '文章别名只能包含字母、数字、短横线(-)和下划线(_)';
            } elseif (isset($_POST['slug']) && !empty(trim($_POST['slug'])) && ctype_digit(trim($_POST['slug']))) {
                $error = '文章别名不能是纯数字，以避免与文章 ID 冲突';
            } elseif (isset($_POST['slug']) && !empty(trim($_POST['slug'])) && $articleModel->isSlugExists(trim($_POST['slug']), 0)) {
                $error = '该文章别名已被其他文章使用，请修改为其他别名';
            } else {
                // 处理用户输入的标签
                $tags = [];
                if (isset($_POST['tags']) && !empty($_POST['tags'])) {
                    $tags = $tagModel->processTags($_POST['tags']);
                }
                
                // 处理封面图（支持：移除封面图 / 本地上传 / 从文章中选择）
                $coverImage = '';

                // 1) 如果勾选了"移除封面图" → 直接设为空
                if (isset($_POST['remove_cover_image']) && $_POST['remove_cover_image'] == '1') {
                    $coverImage = '';
                }
                // 2) 否则如果上传了新文件 → 使用上传的图片
                elseif (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] == 0) {
                    $uploadDir = UPLOADS_PATH . '/cover/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    $fileName = uniqid() . '.' . pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION);
                    $targetPath = $uploadDir . $fileName;
                    if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $targetPath)) {
                        $coverImage = '/uploads/cover/' . $fileName;
                    }
                }
                // 3) 否则如果有"从文章中选择的图片URL" → 使用该URL
                elseif (isset($_POST['cover_image_url']) && !empty($_POST['cover_image_url'])) {
                    $coverImage = trim($_POST['cover_image_url']);
                }
                // 4) 新建文章时：不自动提取首图（站长需手动设置），保持 cover_image 为空
                
                // 准备文章数据
                $articleData = [
                    'title' => $_POST['title'],
                    'content' => $_POST['content'],
                    'description' => isset($_POST['description']) ? $_POST['description'] : '',
                    'cover_image' => $coverImage,
                    'category_id' => $_POST['category_id'],
                    'user_id' => $_SESSION['admin']['id'], // 当前登录用户ID
                    'status' => isset($_POST['status']) ? (int)$_POST['status'] : 0,
                    'top_type' => isset($_POST['top_type']) ? (int)$_POST['top_type'] : 0,
                    'slug' => isset($_POST['slug']) ? trim($_POST['slug']) : '',
                    'tags' => $tags
                ];
                
                // 实例化文章模型
                $articleModel = new ArticleModel();
                
                // 创建文章
                $articleId = $articleModel->createArticle($articleData);
                
                if ($articleId) {
                    // 记录文章创建日志
                    Log::init();
                    Log::info('创建文章', 'operation', ['article_id' => $articleId, 'title' => $_POST['title']]);
                    
                    // 文章创建成功，跳转到文章列表页
                    header('Location: admin.php?action=article');
                    exit;
                } else {
                    $error = '文章创建失败';
                }
            }
            }
        }
        
        // 显示添加文章表单
        include ADMIN_PATH . '/templates/article_add.html';
        
        // 触发文章添加页面渲染后的钩子
        Plugin::triggerHook('article_add_after');
        
    }
    
    public function edit() {
        // 获取文章ID
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if (!$id) {
            // 文章ID无效，跳转到文章列表页
            header('Location: admin.php?action=article');
            exit;
        }
        
        // 实例化模型
        $articleModel = new ArticleModel();
        $categoryModel = new CategoryModel();
        $tagModel = new TagModel();
        
        // 获取文章详情
        $article = $articleModel->getArticleDetailById($id);
        
        if (!$article) {
            // 文章不存在，跳转到文章列表页
            header('Location: admin.php?action=article');
            exit;
        }
        
        // 获取所有分类
        $categories = $categoryModel->getAllCategories();
        
        // 获取文章已选择的标签
        $selectedTags = $tagModel->getTagsByArticleId($id);
        // 将标签转换为逗号分隔的字符串（不带空格）
        $selectedTagsString = implode(',', array_column($selectedTags, 'name'));
        
        // 触发文章编辑页面加载前的钩子
        Plugin::triggerHook('article_edit_before', array('article_id' => $id));
        
        
        // 处理表单提交
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // 新用户限制检查
            if (AuthController::isNewUserRestricted() && (int)Config::get('register_new_user_ban_post', 0)) {
                $error = '新账号在限制期内暂不允许编辑文章，请稍后再试';
            } else {
            // 验证表单数据
            if (empty($_POST['title']) || empty($_POST['content']) || empty($_POST['category_id'])) {
                $error = '标题、内容和分类不能为空';
            } elseif (mb_strlen(trim($_POST['title']), 'UTF-8') > 60) {
                $error = '标题最多 60 个字符（约 30 个汉字）';
            } elseif (isset($_POST['description']) && mb_strlen(trim($_POST['description']), 'UTF-8') > 200) {
                $error = '文章描述最多 200 个字符（约 100 个汉字）';
            } elseif (isset($_POST['slug']) && !empty(trim($_POST['slug'])) && !preg_match('/^[a-zA-Z0-9_-]+$/', trim($_POST['slug']))) {
                $error = '文章别名只能包含字母、数字、短横线(-)和下划线(_)';
            } elseif (isset($_POST['slug']) && !empty(trim($_POST['slug'])) && ctype_digit(trim($_POST['slug']))) {
                $error = '文章别名不能是纯数字，以避免与文章 ID 冲突';
            } elseif (isset($_POST['slug']) && !empty(trim($_POST['slug'])) && $articleModel->isSlugExists(trim($_POST['slug']), $article['id'] ?? 0)) {
                $error = '该文章别名已被其他文章使用，请修改为其他别名';
            } else {
                // 处理用户输入的标签
                $tags = [];
                if (isset($_POST['tags']) && !empty($_POST['tags'])) {
                    $tags = $tagModel->processTags($_POST['tags']);
                }
                
                // 处理封面图（支持：移除封面图 / 本地上传 / 从文章中选择）
                $coverImage = $article['cover_image'] ?? '';

                // 1) 如果勾选了"移除封面图" → 直接设为空
                if (isset($_POST['remove_cover_image']) && $_POST['remove_cover_image'] == '1') {
                    $coverImage = '';
                }
                // 2) 否则如果上传了新文件 → 使用上传的图片
                elseif (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] == 0) {
                    $uploadDir = UPLOADS_PATH . '/cover/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    $fileName = uniqid() . '.' . pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION);
                    $targetPath = $uploadDir . $fileName;
                    if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $targetPath)) {
                        $coverImage = '/uploads/cover/' . $fileName;
                    }
                }
                // 3) 否则如果有"从文章中选择的图片URL" → 使用该URL
                elseif (isset($_POST['cover_image_url']) && !empty($_POST['cover_image_url'])) {
                    $coverImage = trim($_POST['cover_image_url']);
                }
                // 4) 编辑文章时：不自动提取首图，保留原 cover_image 不变（站长需手动修改）
                
                // 准备文章数据
                $articleData = [
                    'title' => $_POST['title'],
                    'content' => $_POST['content'],
                    'description' => isset($_POST['description']) ? $_POST['description'] : '',
                    'cover_image' => $coverImage,
                    'category_id' => $_POST['category_id'],
                    'status' => isset($_POST['status']) ? (int)$_POST['status'] : 0,
                    'top_type' => isset($_POST['top_type']) ? (int)$_POST['top_type'] : 0,
                    'slug' => isset($_POST['slug']) ? trim($_POST['slug']) : '',
                    'tags' => $tags
                ];
                
                // 更新文章
                $result = $articleModel->updateArticle($id, $articleData);
                
                if ($result) {
                    // 记录文章更新日志
                    Log::init();
                    Log::info('更新文章', 'operation', ['article_id' => $id, 'title' => $_POST['title']]);
                    
                    // 文章更新成功，跳转到文章列表页
                    header('Location: admin.php?action=article');
                    exit;
                } else {
                    $error = '文章更新失败';
                }
            }
            }
        }
        
        // 显示编辑文章表单
        include ADMIN_PATH . '/templates/article_edit.html';
        
        // 触发文章编辑页面渲染后的钩子
        Plugin::triggerHook('article_edit_after', array('article_id' => $id));
        
    }
    
    // 删除文章（移到回收站）
    public function delete() {
        // 获取文章ID
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if (!$id) {
            // 文章ID无效，跳转到文章列表页
            header('Location: admin.php?action=article');
            exit;
        }
        
        // 实例化文章模型
        $articleModel = new ArticleModel();
        
        // 获取文章标题用于日志
        $article = $articleModel->getArticleDetailById($id);
        $title = $article['title'] ?? '未知标题';
        
        // 删除文章（移到回收站）
        $result = $articleModel->deleteArticle($id);
        
        // 记录文章删除日志
        Log::init();
        Log::info('删除文章', 'operation', ['article_id' => $id, 'title' => $title]);
        
        // 跳转到文章列表页
        header('Location: admin.php?action=article');
        exit;
    }
    
    // 回收站页面
    public function recycle() {
        // 获取分页参数（limit 白名单校验）
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $pageSize = ListQuery::pageSize();
        $allowedPageSizes = ListQuery::pageSizes();
        
        // 获取筛选参数
        $filters = [];
        if (isset($_GET['category']) && !empty($_GET['category'])) {
            $filters['category'] = (int)$_GET['category'];
        }
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $filters['search'] = trim($_GET['search']);
        }
        
        // 实例化模型
        $articleModel = new ArticleModel();
        $categoryModel = new CategoryModel();
        
        // 获取回收站文章列表
        $articles = $articleModel->getRecycledArticles($page, $pageSize, $filters);
        $total_articles = $articleModel->getRecycledArticleCount($filters);
        
        // 获取分类列表（用于筛选）
        $categories = $categoryModel->getAllCategories();
        
        // 初始化分页类
        $pagination = new Pagination($total_articles, $pageSize, '', 'page');
        
        // 将分页数据传递给模板
        $pagination_html = $pagination->createLinks();
        $pagination_info = "第{$pagination->getCurrentPage()}页，共{$pagination->getTotalPages()}页，总{$pagination->getTotal()}条记录";
        
        // 计算总页数（用于公共分页组件）
        $totalPages = ceil($total_articles / $pageSize);
        
        // 显示回收站文章列表
        include ADMIN_PATH . '/templates/article_recycle.html';
    }
    
    // 恢复文章
    public function restore() {
        // 获取文章ID
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if (!$id) {
            // 文章ID无效，跳转到回收站页面
            header('Location: admin.php?action=article&method=recycle');
            exit;
        }
        
        // 实例化文章模型
        $articleModel = new ArticleModel();
        
        // 获取文章标题用于日志
        $article = $articleModel->getArticleDetailById($id);
        $title = $article['title'] ?? '未知标题';
        
        // 恢复文章
        $result = $articleModel->restoreArticle($id);
        
        // 记录文章恢复日志
        Log::init();
        Log::info('恢复文章', 'operation', ['article_id' => $id, 'title' => $title]);
        
        // 跳转到回收站页面
        header('Location: admin.php?action=article&method=recycle');
        exit;
    }
    
    // 永久删除文章
    public function permanentlyDelete() {
        // 获取文章ID
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if (!$id) {
            // 文章ID无效，跳转到回收站页面
            header('Location: admin.php?action=article&method=recycle');
            exit;
        }
        
        // 实例化文章模型
        $articleModel = new ArticleModel();
        
        // 获取文章标题用于日志
        $article = $articleModel->getArticleDetailById($id);
        $title = $article['title'] ?? '未知标题';
        
        // 永久删除文章
        $result = $articleModel->permanentlyDeleteArticle($id);
        
        // 记录文章永久删除日志
        Log::init();
        Log::info('永久删除文章', 'operation', ['article_id' => $id, 'title' => $title]);
        
        // 跳转到回收站页面
        header('Location: admin.php?action=article&method=recycle');
        exit;
    }
    
    // 设置文章置顶状态
    public function top() {
        // 加载Security类
        
        // 检查CSRF令牌
        $token = isset($_GET['csrf_token']) ? $_GET['csrf_token'] : (isset($_GET['token']) ? $_GET['token'] : '');
        if (!Security::validateCsrfToken($token)) {
            // CSRF验证失败，跳转到文章列表页
            header('Location: admin.php?action=article');
            exit;
        }
        
        // 获取文章ID和置顶类型
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $topType = isset($_GET['top_type']) ? (int)$_GET['top_type'] : 0;
        
        if (!$id) {
            // 文章ID无效，跳转到文章列表页
            header('Location: admin.php?action=article');
            exit;
        }
        
        // 实例化文章模型
        $articleModel = new ArticleModel();
        
        // 获取文章标题用于日志
        $article = $articleModel->getArticleDetailById($id);
        $title = $article['title'] ?? '未知标题';
        
        // 设置文章置顶状态
        $result = $articleModel->setArticleTop($id, $topType);
        
        // 记录文章置顶设置日志
        Log::init();
        $topText = $topType == 1 ? '置顶' : ($topType == 0 ? '取消置顶' : '设置为置顶');
        Log::info('设置文章置顶', 'operation', ['article_id' => $id, 'title' => $title, 'top_type' => $topType, 'top_text' => $topText]);
        
        // 设置反馈信息
        if ($result) {
            $this->showMessage('置顶设置成功', 'admin.php?action=article');
        } else {
            $this->showMessage('置顶设置失败', 'admin.php?action=article');
        }
        
        // 跳转到文章列表页
        header('Location: admin.php?action=article');
        exit;
    }
    
    // 批量设置置顶状态
    public function batchTop() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // 加载Security类
            
            // 检查CSRF令牌
            $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : (isset($_POST['token']) ? $_POST['token'] : '');
            if (!Security::validateCsrfToken($token)) {
                // CSRF验证失败，跳转到文章列表页
                header('Location: admin.php?action=article');
                exit;
            }
            
            $ids = isset($_POST['ids']) ? $_POST['ids'] : [];
            $topType = isset($_POST['top_type']) ? (int)$_POST['top_type'] : 0;
            
            if (!empty($ids)) {
                $articleModel = new ArticleModel();
                $successCount = 0;
                foreach ($ids as $id) {
                    if ($articleModel->setArticleTop((int)$id, $topType)) {
                        $successCount++;
                    }
                }
                
                // 记录批量置顶设置日志
                Log::init();
                $topText = $topType == 1 ? '置顶' : ($topType == 0 ? '取消置顶' : '设置为置顶');
                Log::info('批量设置文章置顶', 'operation', ['article_ids' => $ids, 'success_count' => $successCount, 'top_type' => $topType, 'top_text' => $topText]);
                
                // 设置反馈信息
                if ($successCount > 0) {
                    $this->showMessage("成功设置 $successCount 篇文章的置顶状态", 'admin.php?action=article');
                } else {
                    $this->showMessage('置顶设置失败', 'admin.php?action=article');
                }
            } else {
                $this->showMessage('请选择要操作的文章', 'admin.php?action=article');
            }
        }
        
        // 跳转到文章列表页
        header('Location: admin.php?action=article');
        exit;
    }
    
    // 批量删除文章
    public function batchDelete() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // 加载Security类
            
            // 检查CSRF令牌
            $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : (isset($_POST['token']) ? $_POST['token'] : '');
            if (!Security::validateCsrfToken($token)) {
                // CSRF验证失败，跳转到文章列表页
                header('Location: admin.php?action=article');
                exit;
            }
            
            $ids = isset($_POST['ids']) ? $_POST['ids'] : [];
            
            if (!empty($ids)) {
                $articleModel = new ArticleModel();
                $successCount = 0;
                foreach ($ids as $id) {
                    if ($articleModel->deleteArticle((int)$id)) {
                        $successCount++;
                    }
                }
                
                // 记录批量删除日志
                Log::init();
                Log::info('批量删除文章', 'operation', ['article_ids' => $ids, 'success_count' => $successCount]);
                
                // 设置反馈信息
                if ($successCount > 0) {
                    $this->showMessage("成功删除 $successCount 篇文章", 'admin.php?action=article');
                } else {
                    $this->showMessage('删除失败', 'admin.php?action=article');
                }
            } else {
                $this->showMessage('请选择要操作的文章', 'admin.php?action=article');
            }
        }
        
        // 跳转到文章列表页
        header('Location: admin.php?action=article');
        exit;
    }
    
    // 批量修改文章状态
    public function batchStatus() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // 加载Security类
            
            // 检查CSRF令牌
            $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : (isset($_POST['token']) ? $_POST['token'] : '');
            if (!Security::validateCsrfToken($token)) {
                // CSRF验证失败，跳转到文章列表页
                header('Location: admin.php?action=article');
                exit;
            }
            
            $ids = isset($_POST['ids']) ? $_POST['ids'] : [];
            $status = isset($_POST['status']) ? (int)$_POST['status'] : 0;
            
            if (!empty($ids)) {
                $articleModel = new ArticleModel();
                $successCount = 0;
                foreach ($ids as $id) {
                    $result = $articleModel->updateArticle((int)$id, ['status' => $status]);
                    if ($result) {
                        $successCount++;
                    }
                }
                
                // 记录批量状态修改日志
                Log::init();
                $statusText = $status == 1 ? '已发布' : '草稿';
                Log::info('批量修改文章状态', 'operation', ['article_ids' => $ids, 'success_count' => $successCount, 'status' => $status, 'status_text' => $statusText]);
                
                // 设置反馈信息
                if ($successCount > 0) {
                    $this->showMessage("成功将 $successCount 篇文章设置为$statusText", 'admin.php?action=article');
                } else {
                    $this->showMessage('状态修改失败', 'admin.php?action=article');
                }
            } else {
                $this->showMessage('请选择要操作的文章', 'admin.php?action=article');
            }
        }
        
        // 跳转到文章列表页
        header('Location: admin.php?action=article');
        exit;
    }
    
    // 批量移动文章分类
    public function batchMove() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // 加载Security类
            
            // 检查CSRF令牌
            $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : (isset($_POST['token']) ? $_POST['token'] : '');
            if (!Security::validateCsrfToken($token)) {
                // CSRF验证失败，跳转到文章列表页
                header('Location: admin.php?action=article');
                exit;
            }
            
            $ids = isset($_POST['ids']) ? $_POST['ids'] : [];
            $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
            
            if (!empty($ids) && $categoryId > 0) {
                $articleModel = new ArticleModel();
                $successCount = 0;
                foreach ($ids as $id) {
                    $result = $articleModel->updateArticle((int)$id, ['category_id' => $categoryId]);
                    if ($result) {
                        $successCount++;
                    }
                }
                
                // 记录批量移动分类日志
                Log::init();
                Log::info('批量移动文章分类', 'operation', ['article_ids' => $ids, 'success_count' => $successCount, 'category_id' => $categoryId]);
                
                // 设置反馈信息
                if ($successCount > 0) {
                    $this->showMessage("成功移动 $successCount 篇文章到新分类", 'admin.php?action=article');
                } else {
                    $this->showMessage('移动失败', 'admin.php?action=article');
                }
            } else {
                $this->showMessage('请选择要操作的文章和目标分类', 'admin.php?action=article');
            }
        }
        
        // 跳转到文章列表页
        header('Location: admin.php?action=article');
        exit;
    }
    
    /**
     * 批量修改标签
     */
    public function batchTags() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            
            // CSRF检查
            $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : (isset($_POST['token']) ? $_POST['token'] : '');
            if (!Security::validateCsrfToken($token)) {
                header('Location: admin.php?action=article');
                exit;
            }
            
            $ids = isset($_POST['ids']) ? $_POST['ids'] : [];
            $tagsStr = isset($_POST['tags']) ? trim($_POST['tags']) : '';
            $mode = isset($_POST['tag_mode']) ? $_POST['tag_mode'] : 'replace';
            
            if (empty($ids)) {
                $this->showMessage('请选择要操作的文章', 'admin.php?action=article');
            }
            
            $tagModel = new TagModel();
            $tags = !empty($tagsStr) ? $tagModel->processTags($tagsStr) : [];
            $articleModel = new ArticleModel();
            $successCount = 0;
            
            foreach ($ids as $id) {
                $aid = (int)$id;
                try {
                    if ($mode === 'add') {
                        // 追加模式：保留现有标签，添加新标签
                        $existingTags = $tagModel->getTagsByArticleId($aid);
                        $existingIds = array_column($existingTags, 'id');
                        $newIds = array_diff($tags, $existingIds);
                        if (!empty($newIds)) {
                            $allTags = array_merge($existingIds, $newIds);
                            $articleModel->updateArticle($aid, ['tags' => $allTags]);
                            $successCount++;
                        }
                    } else {
                        // 替换模式（默认）
                        $articleModel->updateArticle($aid, ['tags' => $tags]);
                        $successCount++;
                    }
                } catch (Exception $e) {
                    // 单篇失败不影响其他文章
                }
            }
            
            // 记录日志
            Log::init();
            Log::info('批量修改标签', 'operation', [
                'article_ids' => $ids,
                'tags' => $tagsStr,
                'mode' => $mode,
                'success_count' => $successCount
            ]);
            
            $this->showMessage("成功为 $successCount 篇文章修改标签", 'admin.php?action=article');
        }
        
        header('Location: admin.php?action=article');
        exit;
    }
    
    /**
     * 媒体库图片列表接口（JSON）
     * 供添加/编辑文章页的"从媒体库选择图片作为封面图"弹窗通过 AJAX 实时加载。
     * 每次打开弹窗都重新扫描 uploads 目录，因此能立刻看到编辑器刚上传的图片，
     * 无需刷新整个页面。
     */
    public function mediaList() {
        header('Content-Type: application/json; charset=utf-8');

        $images = [];
        // 需要扫描的 uploads 子目录（articles 为 UEditor 文章图片上传目录，需包含否则扫描不到图片）
        $scanDirs = ['images', 'files', 'avatars', 'logo', 'cover', 'attachments', 'articles'];
        $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        foreach ($scanDirs as $scanDir) {
            $fullScanPath = UPLOADS_PATH . '/' . $scanDir;
            if (!is_dir($fullScanPath)) continue;
            // 递归扫描所有子目录（如 articles/2026/09/），单层 scandir 无法覆盖按日期组织的多级目录
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($fullScanPath, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $fileInfo) {
                if (!$fileInfo->isFile()) continue;
                $ext = strtolower($fileInfo->getExtension());
                if (!in_array($ext, $imageExts)) continue;
                // 过滤图片处理插件自动生成的衍生文件（_large/_medium/_small 尺寸变体、webp 转换副本），避免列表出现大量重复图片
                if (MediaService::isGeneratedVariant($fileInfo->getPathname())) continue;
                // 计算相对于 uploads 目录的相对路径，生成可访问的 URL（兼容 Windows 反斜杠）
                $relativePath = ltrim(str_replace(UPLOADS_PATH, '', $fileInfo->getPathname()), '/\\');
                $images[] = [
                    'name' => $fileInfo->getFilename(),
                    'url' => '/uploads/' . str_replace('\\', '/', $relativePath),
                    'mtime' => $fileInfo->getMTime(),
                    'size_formatted' => round($fileInfo->getSize() / 1024, 1) . ' KB'
                ];
            }
        }

        // 按修改时间倒序排列（最新的在前）
        usort($images, function ($a, $b) {
            return $b['mtime'] - $a['mtime'];
        });

        echo json_encode([
            'code' => 0,
            'message' => 'ok',
            'data' => ['images' => $images]
        ]);
        exit;
    }

    /**
     * 显示消息并跳转
     * @param string $message 消息内容
     * @param string $redirect 跳转地址
     * @param string $messageType 消息类型：success, error, warning, info
     */
    private function showMessage($message, $redirect, $messageType = 'success') {
        // 使用URL参数传递消息，由前端的Toast系统显示
        $redirectUrl = $redirect . (strpos($redirect, '?') === false ? '?' : '&') . 'message=' . urlencode($message) . '&message_type=' . $messageType;
        header('Location: ' . $redirectUrl);
        exit;
    }
}
