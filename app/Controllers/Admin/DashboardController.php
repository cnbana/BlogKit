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


class DashboardController {
    
    /**
     * 获取热门评论排行（按点赞数）
     * @param int $limit 数量
     * @return array 热门评论列表
     */
    private function getTopCommentsByLikes($limit = 5) {
        $db = Database::getInstance();
        $sql = "SELECT c.id, c.content, c.like_count, c.created_at, 
                       a.title as article_title, a.id as article_id
                FROM {$db->table('comments')} c
                LEFT JOIN {$db->table('article')} a ON c.article_id = a.id
                WHERE c.is_deleted = 0 AND c.status = 1 AND c.parent_id = 0
                ORDER BY c.like_count DESC
                LIMIT ?";
        return $db->fetchAll($sql, [$limit]);
    }
    
    /**
     * 获取热门收藏排行（被收藏最多的文章）
     * @param int $limit 数量
     * @return array 热门收藏文章列表
     */
    private function getTopArticlesByFavorites($limit = 5) {
        $db = Database::getInstance();
        $sql = "SELECT a.id, a.title, a.created_at, COUNT(f.article_id) as favorite_count
                FROM {$db->table('article')} a
                LEFT JOIN {$db->table('favorite')} f ON a.id = f.article_id
                WHERE a.is_deleted = 0
                GROUP BY a.id
                ORDER BY favorite_count DESC
                LIMIT ?";
        return $db->fetchAll($sql, [$limit]);
    }
    
    /**
     * 获取热门文章（按阅读量）
     * @param int $limit 数量
     * @return array 热门文章列表
     */
    private function getTopArticlesByViews($limit = 5) {
        $db = Database::getInstance();
        $sql = "SELECT id, title, view_count, created_at 
                FROM {$db->table('article')} 
                WHERE is_deleted = 0 
                ORDER BY view_count DESC 
                LIMIT ?";
        return $db->fetchAll($sql, [$limit]);
    }
    
    /**
     * 获取热门文章（按点赞量）
     * @param int $limit 数量
     * @return array 热门文章列表
     */
    private function getTopArticlesByLikes($limit = 5) {
        $db = Database::getInstance();
        $sql = "SELECT a.id, a.title, a.created_at, COUNT(l.id) as like_count
                FROM {$db->table('article')} a
                LEFT JOIN {$db->table('like')} l ON a.id = l.article_id
                WHERE a.is_deleted = 0
                GROUP BY a.id
                ORDER BY like_count DESC
                LIMIT ?";
        return $db->fetchAll($sql, [$limit]);
    }
    
    /**
     * 获取待处理事项统计
     * @return array 待处理事项
     */
    private function getPendingItems() {
        $db = Database::getInstance();
        $pending = [];
        
        // 待审核评论数
        $sql = "SELECT COUNT(*) as count FROM {$db->table('comments')} WHERE status = 0";
        $pending['pending_comments'] = (int)$db->fetch($sql)['count'];
        
        // 待审核文章数（草稿）
        $sql = "SELECT COUNT(*) as count FROM {$db->table('article')} WHERE status = 0 AND is_deleted = 0";
        $pending['pending_articles'] = (int)$db->fetch($sql)['count'];
        
        // 待审核用户数（如果有用户审核机制）
        $sql = "SELECT COUNT(*) as count FROM {$db->table('user')} WHERE status = 0";
        $pending['pending_users'] = (int)$db->fetch($sql)['count'];
        
        // 待审核友链申请数
        $sql = "SELECT COUNT(*) as count FROM {$db->table('friendlink')} WHERE status = 0";
        $pending['pending_friendlinks'] = (int)$db->fetch($sql)['count'];
        
        return $pending;
    }
    
    /**
     * 获取最新文章列表
     * @param int $limit 数量
     * @return array 最新文章
     */
    private function getRecentArticles($limit = 5) {
        $db = Database::getInstance();
        $sql = "SELECT id, title, status, created_at 
                FROM {$db->table('article')} 
                WHERE is_deleted = 0 
                ORDER BY created_at DESC 
                LIMIT ?";
        return $db->fetchAll($sql, [$limit]);
    }
    
    public function index() {
        // 获取统计数据
        $db = Database::getInstance();
        
        // 基本统计（含评论/页面总数与全站浏览总量，供统计卡下钻）
        $stats = [
            'articles' => $db->fetch("SELECT COUNT(*) as count FROM {$db->table('article')} WHERE is_deleted = 0")['count'],
            'categories' => $db->fetch("SELECT COUNT(*) as count FROM {$db->table('category')}")['count'],
            'tags' => $db->fetch("SELECT COUNT(*) as count FROM {$db->table('tag')}")['count'],
            'comments' => $db->fetch("SELECT COUNT(*) as count FROM {$db->table('comments')} WHERE is_deleted = 0")['count'],
            'users' => $db->fetch("SELECT COUNT(*) as count FROM {$db->table('user')} WHERE is_deleted = 0")['count'],
            'pages' => $db->fetch("SELECT COUNT(*) as count FROM {$db->table('page')}")['count'],
            // 全站浏览总量：所有未删除文章的浏览量之和（无文章时 COALESCE 返回 0）
            'total_views' => $db->fetch("SELECT COALESCE(SUM(view_count), 0) as count FROM {$db->table('article')} WHERE is_deleted = 0")['count'],
        ];
        
        // 获取热门文章
        $top_articles_views = $this->getTopArticlesByViews(5);
        $top_articles_likes = $this->getTopArticlesByLikes(5);
        
        // 获取热门评论排行
        $top_comments = $this->getTopCommentsByLikes(5);
        
        // 获取热门收藏排行
        $top_favorites = $this->getTopArticlesByFavorites(5);
        
        // 获取待处理事项
        $pending_items = $this->getPendingItems();
        $total_pending = $pending_items['pending_comments'] + $pending_items['pending_articles']
                       + $pending_items['pending_users'] + $pending_items['pending_friendlinks'];

        // 升级提醒数据（try/catch 包裹：升级提醒属锦上添花，官网不可达等任何异常都不阻断仪表盘渲染）
        // - 主系统新版本：复用 MarketController::checkNewVersion()（bk_config 10 分钟缓存，与侧栏红点同模式）
        // - 应用升级数：复用 MarketController::countUpdatableApps()（仅读市场列表缓存，不触发官网请求）
        $hasNewVersion = 0;
        $latestVersion = '';
        $appUpdates = 0;
        try {
            $market = new MarketController();
            list($latestVersion, $hasNewVersion) = $market->checkNewVersion();
            $appUpdates = $market->countUpdatableApps();
        } catch (Exception $e) {
            // 静默降级：取不到升级信息时仪表盘照常渲染，只是不显示两条提醒
        }
        
        // 获取最新文章列表
        $recent_articles = $this->getRecentArticles(5);

        // 获取最新评论（与左侧"最新文章"对称，右侧补充时间维度的互动流）
        $recent_comments = $this->getRecentComments(5);

        // 系统基本信息（原系统信息页精简合并而来）
        $system_basic = $this->getSystemBasicInfo();

        // 显示仪表盘
        include ADMIN_PATH . '/templates/dashboard.html';
    }

    /**
     * 获取最新评论（含评论者昵称与文章标题，按创建时间倒序）
     * @param int $limit 数量
     * @return array 最新评论列表
     */
    private function getRecentComments($limit = 5) {
        $db = Database::getInstance();
        $sql = "SELECT c.id, c.article_id, c.content, c.created_at, c.status,
                       u.nickname, u.username,
                       a.title AS article_title
                FROM {$db->table('comments')} c
                LEFT JOIN {$db->table('user')} u ON c.user_id = u.id
                LEFT JOIN {$db->table('article')} a ON c.article_id = a.id
                WHERE c.is_deleted = 0
                ORDER BY c.created_at DESC
                LIMIT " . (int)$limit;
        return $db->fetchAll($sql);
    }

    /**
     * 获取系统基本信息（版本/环境/安装时间/运行时长，精简合并到仪表盘）
     * @return array 基本信息数组
     */
    private function getSystemBasicInfo() {
        $info = [];

        // 系统版本：统一从 version.php 读取（版本号唯一真相来源，同时提供官方信息/署名供仪表盘展示）
        $version = require CORE_PATH . '/config/version.php';
        $info['version'] = $version['version'];
        $info['name'] = $version['name'] ?? 'BlogKit';
        $info['official_site'] = $version['official_site'] ?? '#';
        $info['docs_url'] = $version['docs_url'] ?? '#';
        $info['feedback_url'] = $version['feedback_url'] ?? '#';
        $info['author'] = $version['author'] ?? 'BlogKit 开发团队';

        // 运行环境：错误显示开启视为开发环境
        $info['environment'] = Config::get('debug_error_display', '0') == '1' ? '开发环境' : '生产环境';

        // 系统环境：操作系统 / Web服务器 / PHP版本 / MySQL版本
        $info['os'] = php_uname('s') . ' ' . php_uname('r');
        $info['web_server'] = $_SERVER['SERVER_SOFTWARE'] ?? '未知';
        $info['php_version'] = PHP_VERSION;
        try {
            $db = Database::getInstance();
            $info['mysql_version'] = $db->fetch("SELECT VERSION() AS v")['v'] ?? '未知';
        } catch (\Throwable $e) {
            $info['mysql_version'] = '未知';
            error_log('DashboardController mysql version: ' . $e->getMessage());
        }

        // 安装时间（缺失时补写，保持与原系统信息页一致的行为）
        $installTime = $db->fetch("SELECT * FROM {$db->table('config')} WHERE name = 'install_time'");
        if ($installTime) {
            $installTimestamp = (int)$installTime['value'];
        } else {
            $installTimestamp = time();
            $db->insert('config', ['name' => 'install_time', 'value' => $installTimestamp, 'description' => '系统安装时间', 'type' => 'int']);
        }
        $info['install_time'] = date('Y-m-d H:i:s', $installTimestamp);

        // 运行时长：优先读取真实系统开机时长，失败则退化为距安装时间
        $uptimeSet = false;
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            try {
                if (class_exists('COM')) {
                    $wmi = new COM('WinMgmts:{impersonationLevel=impersonate}//./root/cimv2');
                    $os = $wmi->ExecQuery('SELECT LastBootUpTime FROM Win32_OperatingSystem');
                    foreach ($os as $item) {
                        $boot = strtotime(substr($item->LastBootUpTime, 0, 14));
                        if ($boot) {
                            $info['uptime'] = gmdate('Y年m月d天 H:i:s', time() - $boot);
                            $uptimeSet = true;
                        }
                        break;
                    }
                }
            } catch (\Throwable $e) {
                // COM 不可用时静默降级
            }
        } else {
            $uptimeFile = @file_get_contents('/proc/uptime');
            if ($uptimeFile !== false) {
                $uptimeSeconds = (float)$uptimeFile;
                if ($uptimeSeconds > 0) {
                    $info['uptime'] = gmdate('Y年m月d天 H:i:s', (int)$uptimeSeconds);
                    $uptimeSet = true;
                }
            }
        }
        if (!$uptimeSet) {
            $info['uptime'] = gmdate('d天H小时i分钟s秒', time() - $installTimestamp);
        }

        return $info;
    }
}
