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


// 加载日志类

/**
 * 互动管理控制器
 * 负责管理用户的点赞和收藏行为
 */
class InteractionController {
    /**
     * 互动管理首页
     * 根据不同的子页面参数显示不同的管理内容
     */
    public function index() {
        // 100%写死默认就是all！无论什么情况，默认都是all！！！
        $subPage = 'all';
        
        // 只有当且仅当 $_GET['sub'] 明确等于 'like' 或 'favorite'，才改变
        if (isset($_GET['sub'])) {
            if ($_GET['sub'] === 'like' || $_GET['sub'] === 'favorite') {
                $subPage = $_GET['sub'];
            }
        }
        
        // 获取当前页码
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        // 每页条数（limit 白名单校验）
        $pageSize = ListQuery::pageSize();
        $allowedPageSizes = ListQuery::pageSizes();
        
        // 加载模型
        $likeModel = new LikeModel();
        $favoriteModel = new FavoriteModel();
        $articleModel = new ArticleModel();
        $userModel = new UserModel();
        
        $data = [];
        $total = 0;
        
        // 根据子页面处理不同的逻辑
        if ($subPage == 'all') {
            // 全部互动
            $data = $this->getAllInteractions($page, $pageSize, $total);
        } elseif ($subPage == 'like') {
            // 点赞管理
            $data = $this->getAllLikes($page, $pageSize, $total);
        } elseif ($subPage == 'favorite') {
            // 收藏管理
            $data = $this->getAllFavorites($page, $pageSize, $total);
        }
        
        // 计算总页数
        $totalPages = ceil($total / $pageSize);
        
        // 子页面配置
        $subPages = [
            'all' => '全部互动',
            'like' => '点赞管理',
            'favorite' => '收藏管理'
        ];
        
        // 100%确保$sub变量可用！强制就是all！
        $sub = $subPage;
        // 再次强制确保：如果不是like或favorite，否则绝对是all！
        if (!in_array($sub, ['like', 'favorite'])) {
            $sub = 'all';
        }
        
        // 定义批量删除相关变量 - 直接在控制器中定义，确保模板能正确使用
        if ($sub === 'favorite') {
            $batchAction = 'admin.php?action=interaction&method=batchDeleteLike';
            $deleteMethod = 'deleteFavorite';
            $showBatch = false;
        } elseif ($sub === 'like') {
            $batchAction = 'admin.php?action=interaction&method=batchDeleteLike';
            $deleteMethod = 'deleteLike';
            $showBatch = true;
        } else {
            $batchAction = 'admin.php?action=interaction&method=batchDeleteLike';
            $deleteMethod = '';
            $showBatch = true;
        }
        
        // 分页模板变量
        $limit = $pageSize;

        // 显示管理页面
        include ADMIN_PATH . '/templates/interaction.html';
    }
    
    /**
     * 获取所有互动记录（点赞+收藏）
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @param int &$total 总记录数
     * @return array 互动记录
     */
    private function getAllInteractions($page, $pageSize, &$total) {
        $db = Database::getInstance();
        $likeTable = $db->table('like');
        $favoriteTable = $db->table('favorite');
        $articleTable = $db->table('article');
        $userTable = $db->table('user');
        
        $offset = ($page - 1) * $pageSize;
        
        // 获取总记录数
        $totalSql = "SELECT 
            (SELECT COUNT(*) FROM `{$likeTable}` l 
                LEFT JOIN `{$articleTable}` a ON l.article_id = a.id
                WHERE (a.id IS NULL OR (a.status = 1 AND a.is_deleted = 0))
            ) + 
            (SELECT COUNT(*) FROM `{$favoriteTable}` f 
                LEFT JOIN `{$articleTable}` a ON f.article_id = a.id
                WHERE (a.id IS NULL OR (a.status = 1 AND a.is_deleted = 0))
            ) as count";
        $totalResult = $db->query($totalSql);
        $totalRow = $totalResult->fetch();
        $total = $totalRow['count'];
        
        // 联合查询获取所有记录
        // 注意：favorite 表没有 id 字段，使用 NULL 或者 CONCAT 来生成标识
        $sql = "SELECT * FROM (
            SELECT 
                'like' as type,
                l.id, 
                l.article_id, 
                l.user_id, 
                l.created_at,
                a.title,
                u.username,
                u.nickname
            FROM `{$likeTable}` l 
            LEFT JOIN `{$articleTable}` a ON l.article_id = a.id 
            LEFT JOIN `{$userTable}` u ON l.user_id = u.id 
            WHERE (a.id IS NULL OR (a.status = 1 AND a.is_deleted = 0))
            UNION ALL
            SELECT 
                'favorite' as type,
                f.id, 
                f.article_id, 
                f.user_id, 
                f.created_at,
                a.title,
                u.username,
                u.nickname
            FROM `{$favoriteTable}` f 
            LEFT JOIN `{$articleTable}` a ON f.article_id = a.id 
            LEFT JOIN `{$userTable}` u ON f.user_id = u.id 
            WHERE (a.id IS NULL OR (a.status = 1 AND a.is_deleted = 0))
        ) as combined 
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?";
        
        $result = $db->query($sql, [$pageSize, $offset]);
        $interactions = $result->fetchAll();
        
        // 转换时间戳为可读格式
        foreach ($interactions as &$interaction) {
            $interaction['created_at'] = date('Y-m-d H:i:s', $interaction['created_at']);
        }
        
        return $interactions;
    }
    
    /**
     * 获取所有点赞记录
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @param int &$total 总记录数
     * @return array 点赞记录
     */
    private function getAllLikes($page, $pageSize, &$total) {
        $db = Database::getInstance();
        $likeTable = $db->table('like');
        $articleTable = $db->table('article');
        $userTable = $db->table('user');
        
        $offset = ($page - 1) * $pageSize;
        
        // 获取总记录数（含已删除文章上的点赞，便于后台清理）
        $totalSql = "SELECT COUNT(*) as count FROM `{$likeTable}` l
            LEFT JOIN `{$articleTable}` a ON l.article_id = a.id
            WHERE (a.id IS NULL OR (a.status = 1 AND a.is_deleted = 0))";
        $totalResult = $db->query($totalSql);
        $totalRow = $totalResult->fetch();
        $total = $totalRow['count'];
        
        // 获取点赞记录
        $sql = "SELECT 
                    l.*, 
                    a.id as article_id, 
                    a.title, 
                    u.id as user_id, 
                    u.username, 
                    u.nickname 
                FROM 
                    `{$likeTable}` l 
                LEFT JOIN 
                    `{$articleTable}` a ON l.article_id = a.id 
                LEFT JOIN 
                    `{$userTable}` u ON l.user_id = u.id 
                WHERE 
                    (a.id IS NULL OR (a.status = 1 AND a.is_deleted = 0))
                ORDER BY 
                    l.created_at DESC 
                LIMIT ? OFFSET ?";
        
        $result = $db->query($sql, [$pageSize, $offset]);
        $likes = $result->fetchAll();
        
        // 转换时间戳为可读格式
        foreach ($likes as &$like) {
            $like['created_at'] = date('Y-m-d H:i:s', $like['created_at']);
        }
        
        return $likes;
    }
    
    /**
     * 获取所有收藏记录
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @param int &$total 总记录数
     * @return array 收藏记录
     */
    private function getAllFavorites($page, $pageSize, &$total) {
        $db = Database::getInstance();
        $favoriteTable = $db->table('favorite');
        $articleTable = $db->table('article');
        $userTable = $db->table('user');
        
        $offset = ($page - 1) * $pageSize;
        
        // 获取总记录数
        $totalSql = "SELECT COUNT(*) as count FROM `{$favoriteTable}` f
            LEFT JOIN `{$articleTable}` a ON f.article_id = a.id
            WHERE (a.id IS NULL OR (a.status = 1 AND a.is_deleted = 0))";
        $totalResult = $db->query($totalSql);
        $totalRow = $totalResult->fetch();
        $total = $totalRow['count'];
        
        // 获取收藏记录 - 明确指定字段，避免列名冲突
        $sql = "SELECT 
                    f.id, 
                    f.user_id, 
                    f.article_id, 
                    f.created_at, 
                    a.title, 
                    u.username, 
                    u.nickname 
                FROM 
                    `{$favoriteTable}` f 
                LEFT JOIN 
                    `{$articleTable}` a ON f.article_id = a.id 
                LEFT JOIN 
                    `{$userTable}` u ON f.user_id = u.id 
                WHERE 
                    (a.id IS NULL OR (a.status = 1 AND a.is_deleted = 0)) 
                ORDER BY 
                    f.created_at DESC 
                LIMIT ? OFFSET ?";
        
        $result = $db->query($sql, [$pageSize, $offset]);
        $favorites = $result->fetchAll();
        
        // 转换时间戳为可读格式
        foreach ($favorites as &$favorite) {
            $favorite['created_at'] = date('Y-m-d H:i:s', $favorite['created_at']);
        }
        
        return $favorites;
    }
    
    /**
     * 删除点赞记录
     */
    public function deleteLike() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $likeId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            
            if ($likeId > 0) {
                $db = Database::getInstance();
                $prefix = Config::get('database.prefix');
                
                $sql = "DELETE FROM `{$prefix}like` WHERE id = ?";
                $result = $db->query($sql, [$likeId]);
                
                if ($result) {
                    Log::info('互动管理', '删除点赞记录', '成功删除点赞记录ID: ' . $likeId, Log::CATEGORY_OPERATION);
                    $_SESSION['success_message'] = '点赞记录删除成功';
                } else {
                    Log::error('互动管理', '删除点赞记录', '删除点赞记录失败: ID ' . $likeId, Log::CATEGORY_OPERATION);
                    $_SESSION['error_message'] = '点赞记录删除失败';
                }
            } else {
                Log::warning('互动管理', '删除点赞记录', '无效的点赞记录ID', Log::CATEGORY_OPERATION);
                $_SESSION['error_message'] = '无效的点赞记录ID';
            }
        }
        
        // 重定向回点赞管理页面
        header('Location: admin.php?action=interaction&sub=like');
        exit;
    }
    
    /**
     * 删除收藏记录
     */
    public function deleteFavorite() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $db = Database::getInstance();
            $prefix = Config::get('database.prefix');
            
            // 优先使用ID删除
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            if ($id > 0) {
                $sql = "DELETE FROM {$prefix}favorite WHERE id = ?";
                $result = $db->query($sql, [$id]);
                
                if ($result) {
                    Log::info('互动管理', '删除收藏记录', '成功删除收藏记录 - ID: ' . $id, Log::CATEGORY_OPERATION);
                    $_SESSION['success_message'] = '收藏记录删除成功';
                } else {
                    Log::error('互动管理', '删除收藏记录', '删除收藏记录失败 - ID: ' . $id, Log::CATEGORY_OPERATION);
                    $_SESSION['error_message'] = '收藏记录删除失败';
                }
            } else {
                // 兼容旧方式，使用user_id和article_id删除
                $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
                $articleId = isset($_POST['article_id']) ? (int)$_POST['article_id'] : 0;
                
                if ($userId > 0 && $articleId > 0) {
                    $sql = "DELETE FROM {$prefix}favorite WHERE user_id = ? AND article_id = ?";
                    $result = $db->query($sql, [$userId, $articleId]);
                    
                    if ($result) {
                        Log::info('互动管理', '删除收藏记录', '成功删除收藏记录 - 用户ID: ' . $userId . ', 文章ID: ' . $articleId, Log::CATEGORY_OPERATION);
                        $_SESSION['success_message'] = '收藏记录删除成功';
                    } else {
                        Log::error('互动管理', '删除收藏记录', '删除收藏记录失败 - 用户ID: ' . $userId . ', 文章ID: ' . $articleId, Log::CATEGORY_OPERATION);
                        $_SESSION['error_message'] = '收藏记录删除失败';
                    }
                } else {
                    Log::warning('互动管理', '删除收藏记录', '无效的参数', Log::CATEGORY_OPERATION);
                    $_SESSION['error_message'] = '无效的参数';
                }
            }
        }
        
        // 重定向回收藏管理页面
        header('Location: admin.php?action=interaction&sub=favorite');
        exit;
    }
    
    /**
     * 批量删除点赞记录
     */
    public function batchDeleteLike() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $likeIds = isset($_POST['ids']) ? $_POST['ids'] : [];
            
            if (!empty($likeIds)) {
                $db = Database::getInstance();
                $prefix = Config::get('database.prefix');
                
                // 使用IN语句批量删除
                $placeholders = rtrim(str_repeat('?,', count($likeIds)), ',');
                $sql = "DELETE FROM `{$prefix}like` WHERE id IN ($placeholders)";
                $result = $db->query($sql, $likeIds);
                
                if ($result) {
                    Log::info('互动管理', '批量删除点赞记录', '成功批量删除点赞记录: ' . count($likeIds) . ' 条', Log::CATEGORY_OPERATION);
                    $_SESSION['success_message'] = '批量删除点赞记录成功';
                } else {
                    Log::error('互动管理', '批量删除点赞记录', '批量删除点赞记录失败', Log::CATEGORY_OPERATION);
                    $_SESSION['error_message'] = '批量删除点赞记录失败';
                }
            } else {
                Log::warning('互动管理', '批量删除点赞记录', '请选择要删除的点赞记录', Log::CATEGORY_OPERATION);
                $_SESSION['error_message'] = '请选择要删除的点赞记录';
            }
        }
        
        // 重定向回点赞管理页面
        header('Location: admin.php?action=interaction&sub=like');
        exit;
    }
    
    /**
     * 批量删除收藏记录
     */
    public function batchDeleteFavorite() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $favoriteIds = isset($_POST['ids']) ? $_POST['ids'] : [];
            
            if (!empty($favoriteIds)) {
                $db = Database::getInstance();
                $prefix = Config::get('database.prefix');
                
                // 使用IN语句批量删除
                $placeholders = rtrim(str_repeat('?,', count($favoriteIds)), ',');
                $sql = "DELETE FROM {$prefix}favorite WHERE id IN ($placeholders)";
                $result = $db->query($sql, $favoriteIds);
                
                if ($result) {
                    Log::info('互动管理', '批量删除收藏记录', '成功批量删除收藏记录: ' . count($favoriteIds) . ' 条', Log::CATEGORY_OPERATION);
                    $_SESSION['success_message'] = '批量删除收藏记录成功';
                } else {
                    Log::error('互动管理', '批量删除收藏记录', '批量删除收藏记录失败', Log::CATEGORY_OPERATION);
                    $_SESSION['error_message'] = '批量删除收藏记录失败';
                }
            } else {
                Log::warning('互动管理', '批量删除收藏记录', '请选择要删除的收藏记录', Log::CATEGORY_OPERATION);
                $_SESSION['error_message'] = '请选择要删除的收藏记录';
            }
        }
        
        // 重定向回收藏管理页面
        header('Location: admin.php?action=interaction&sub=favorite');
        exit;
    }
}
