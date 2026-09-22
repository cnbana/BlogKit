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


class SearchController {
    
    public function index() {
        $query = isset($_GET['query']) ? trim($_GET['query']) : '';
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        
        if (empty($query)) {
            echo json_encode([
                'success' => true,
                'data' => []
            ]);
            return;
        }
        
        $results = [];
        
        $db = Database::getInstance();
        
        $articles = $this->searchArticles($db, $query, $limit);
        if (!empty($articles)) {
            $results['articles'] = [
                'title' => '文章',
                'icon' => 'file-text',
                'items' => $articles
            ];
        }
        
        $users = $this->searchUsers($db, $query, $limit);
        if (!empty($users)) {
            $results['users'] = [
                'title' => '用户',
                'icon' => 'users',
                'items' => $users
            ];
        }
        
        $comments = $this->searchComments($db, $query, $limit);
        if (!empty($comments)) {
            $results['comments'] = [
                'title' => '评论',
                'icon' => 'message-square',
                'items' => $comments
            ];
        }
        
        $categories = $this->searchCategories($db, $query, $limit);
        if (!empty($categories)) {
            $results['categories'] = [
                'title' => '分类',
                'icon' => 'folder',
                'items' => $categories
            ];
        }
        
        $tags = $this->searchTags($db, $query, $limit);
        if (!empty($tags)) {
            $results['tags'] = [
                'title' => '标签',
                'icon' => 'tag',
                'items' => $tags
            ];
        }
        
        $configs = $this->searchConfigs($db, $query, $limit);
        if (!empty($configs)) {
            $results['configs'] = [
                'title' => '配置项',
                'icon' => 'settings',
                'items' => $configs
            ];
        }
        
        $commands = $this->getQuickCommands($query);
        if (!empty($commands)) {
            $results['commands'] = [
                'title' => '快捷操作',
                'icon' => 'settings',
                'items' => $commands
            ];
        }
        
        echo json_encode([
            'success' => true,
            'data' => $results
        ]);
    }
    
    private function searchArticles($db, $query, $limit) {
        try {
            $articles = $db->fetchAll("
                SELECT id, title, description, created_at 
                FROM {$db->table('article')} 
                WHERE title LIKE ? OR description LIKE ? OR content LIKE ?
                ORDER BY created_at DESC 
                LIMIT ?
            ", [
                "%{$query}%",
                "%{$query}%",
                "%{$query}%",
                (int)$limit
            ]);
        } catch (Exception $e) {
            return [];
        }
        
        $results = [];
        foreach ($articles as $article) {
            $results[] = [
                'id' => $article['id'],
                'title' => $article['title'],
                'subtitle' => $article['description'] ? mb_substr($article['description'], 0, 50) . '...' : '暂无描述',
                'type' => 'article',
                'url' => 'admin.php?action=article&sub=edit&id=' . $article['id'],
                'time' => date('Y-m-d', strtotime($article['created_at']))
            ];
        }
        
        return $results;
    }
    
    private function searchUsers($db, $query, $limit) {
        try {
            $users = $db->fetchAll("
                SELECT id, username, nickname, email 
                FROM {$db->table('user')} 
                WHERE username LIKE ? OR nickname LIKE ? OR email LIKE ?
                ORDER BY created_at DESC 
                LIMIT ?
            ", [
                "%{$query}%",
                "%{$query}%",
                "%{$query}%",
                (int)$limit
            ]);
        } catch (Exception $e) {
            return [];
        }
        
        $results = [];
        foreach ($users as $user) {
            $results[] = [
                'id' => $user['id'],
                'title' => $user['nickname'] ?: $user['username'],
                'subtitle' => $user['email'],
                'type' => 'user',
                'url' => 'admin.php?action=user&sub=edit&id=' . $user['id']
            ];
        }
        
        return $results;
    }
    
    private function searchComments($db, $query, $limit) {
        try {
            $comments = $db->fetchAll("
                SELECT c.id, c.content, c.author_name, c.created_at, a.title as article_title
                FROM {$db->table('comment')} c
                LEFT JOIN {$db->table('article')} a ON c.article_id = a.id
                WHERE c.content LIKE ? OR c.author_name LIKE ? OR c.author_email LIKE ?
                ORDER BY c.created_at DESC 
                LIMIT ?
            ", [
                "%{$query}%",
                "%{$query}%",
                "%{$query}%",
                (int)$limit
            ]);
        } catch (Exception $e) {
            return [];
        }
        
        $results = [];
        foreach ($comments as $comment) {
            $results[] = [
                'id' => $comment['id'],
                'title' => $comment['author_name'],
                'subtitle' => mb_substr($comment['content'], 0, 50) . '...',
                'type' => 'comment',
                'url' => 'admin.php?action=comment&sub=edit&id=' . $comment['id'],
                'context' => $comment['article_title']
            ];
        }
        
        return $results;
    }
    
    private function searchCategories($db, $query, $limit) {
        try {
            $categories = $db->fetchAll("
                SELECT id, name, description 
                FROM {$db->table('category')} 
                WHERE name LIKE ? OR description LIKE ?
                ORDER BY sort_order ASC 
                LIMIT ?
            ", [
                "%{$query}%",
                "%{$query}%",
                (int)$limit
            ]);
        } catch (Exception $e) {
            return [];
        }
        
        $results = [];
        foreach ($categories as $category) {
            $results[] = [
                'id' => $category['id'],
                'title' => $category['name'],
                'subtitle' => $category['description'] ?: '暂无描述',
                'type' => 'category',
                'url' => 'admin.php?action=category&sub=edit&id=' . $category['id']
            ];
        }
        
        return $results;
    }
    
    private function searchTags($db, $query, $limit) {
        try {
            $tags = $db->fetchAll("
                SELECT id, name, count 
                FROM {$db->table('tag')} 
                WHERE name LIKE ?
                ORDER BY count DESC 
                LIMIT ?
            ", [
                "%{$query}%",
                (int)$limit
            ]);
        } catch (Exception $e) {
            return [];
        }
        
        $results = [];
        foreach ($tags as $tag) {
            $results[] = [
                'id' => $tag['id'],
                'title' => $tag['name'],
                'subtitle' => "{$tag['count']} 篇文章",
                'type' => 'tag',
                'url' => 'admin.php?action=article&tag=' . urlencode($tag['name'])
            ];
        }
        
        return $results;
    }
    
    private function searchConfigs($db, $query, $limit) {
        try {
            $configs = $db->fetchAll("
                SELECT id, name, value, description, type 
                FROM {$db->table('config')} 
                WHERE LOWER(name) LIKE LOWER(?) OR LOWER(description) LIKE LOWER(?) OR LOWER(value) LIKE LOWER(?)
                ORDER BY name ASC 
                LIMIT ?
            ", [
                "%{$query}%",
                "%{$query}%",
                "%{$query}%",
                (int)$limit
            ]);
        } catch (Exception $e) {
            return [];
        }
        
        $results = [];
        foreach ($configs as $config) {
            $value = $config['value'];
            if (strlen($value) > 30) {
                $value = mb_substr($value, 0, 30) . '...';
            }
            
            $results[] = [
                'id' => $config['id'],
                'title' => $config['description'] ?: $config['name'],
                'subtitle' => $config['name'] . ' - ' . $value,
                'value' => $value,
                'type' => 'config',
                'url' => 'admin.php?action=config'
            ];
        }
        
        return $results;
    }
    
    private function getQuickCommands($query) {
        $commands = [
            ['title' => '仪表盘', 'subtitle' => '查看数据概览', 'url' => 'admin.php?action=dashboard'],
            ['title' => '文章管理', 'subtitle' => '查看和管理文章', 'url' => 'admin.php?action=article'],
            ['title' => '添加文章', 'subtitle' => '创建新文章', 'url' => 'admin.php?action=article&sub=add'],
            ['title' => '分类管理', 'subtitle' => '管理文章分类', 'url' => 'admin.php?action=category'],
            ['title' => '标签管理', 'subtitle' => '管理文章标签', 'url' => 'admin.php?action=tag'],
            ['title' => '评论管理', 'subtitle' => '审核和管理评论', 'url' => 'admin.php?action=comment'],
            ['title' => '页面管理', 'subtitle' => '管理独立页面', 'url' => 'admin.php?action=page'],
            ['title' => '用户管理', 'subtitle' => '管理用户账户', 'url' => 'admin.php?action=user'],
            ['title' => '角色管理', 'subtitle' => '管理用户角色', 'url' => 'admin.php?action=role'],
            ['title' => '权限管理', 'subtitle' => '管理权限设置', 'url' => 'admin.php?action=permission'],
            ['title' => '主题管理', 'subtitle' => '管理网站主题', 'url' => 'admin.php?action=theme'],
            ['title' => '插件管理', 'subtitle' => '管理插件扩展', 'url' => 'admin.php?action=plugin'],
            ['title' => '媒体库', 'subtitle' => '管理上传文件', 'url' => 'admin.php?action=media'],
            ['title' => '友情链接', 'subtitle' => '管理友情链接', 'url' => 'admin.php?action=friendlink'],
            ['title' => '数据备份', 'subtitle' => '备份和恢复数据', 'url' => 'admin.php?action=backup'],
            ['title' => '操作日志', 'subtitle' => '查看系统日志', 'url' => 'admin.php?action=log'],
            ['title' => '日志设置', 'subtitle' => '配置日志选项', 'url' => 'admin.php?action=log&method=settings'],
            ['title' => 'API密钥', 'subtitle' => '管理API密钥', 'url' => 'admin.php?action=api_key'],
            ['title' => '系统通知', 'subtitle' => '管理系统通知', 'url' => 'admin.php?action=notification'],
            ['title' => '仪表盘', 'subtitle' => '查看系统健康状态与基本信息', 'url' => 'admin.php?action=dashboard'],
            ['title' => '数据库迁移', 'subtitle' => '执行数据库迁移', 'url' => 'admin.php?action=migrate'],
            
            ['title' => '系统设置', 'subtitle' => '配置系统参数', 'url' => 'admin.php?action=config'],
            ['title' => '基本设置', 'subtitle' => '网站基本信息', 'url' => 'admin.php?action=config&sub=basic'],
            ['title' => '功能开关', 'subtitle' => '启用/禁用功能', 'url' => 'admin.php?action=config&sub=feature'],
            ['title' => '用户设置', 'subtitle' => '用户相关配置', 'url' => 'admin.php?action=config&sub=user'],
            ['title' => '搜索设置', 'subtitle' => '搜索相关配置', 'url' => 'admin.php?action=search_settings'],
            ['title' => '注册设置', 'subtitle' => '用户注册配置', 'url' => 'admin.php?action=config&sub=register'],
            ['title' => '登录设置', 'subtitle' => '登录相关配置', 'url' => 'admin.php?action=config&sub=login'],
            ['title' => '验证码设置', 'subtitle' => '验证码配置', 'url' => 'admin.php?action=config&sub=captcha'],
            ['title' => '文章设置', 'subtitle' => '文章相关配置', 'url' => 'admin.php?action=config&sub=article'],
            ['title' => '评论设置', 'subtitle' => '评论相关配置', 'url' => 'admin.php?action=config&sub=comment'],
            ['title' => 'SEO设置', 'subtitle' => '搜索引擎优化', 'url' => 'admin.php?action=config&sub=seo'],
            ['title' => '伪静态设置', 'subtitle' => 'URL重写配置', 'url' => 'admin.php?action=config&sub=rewrite'],
            ['title' => '邮箱配置', 'subtitle' => '邮件发送设置', 'url' => 'admin.php?action=config&sub=email'],
            ['title' => 'API设置', 'subtitle' => 'API相关配置', 'url' => 'admin.php?action=config&sub=api'],
            ['title' => '安全设置', 'subtitle' => '安全防护配置', 'url' => 'admin.php?action=config&sub=security'],
            ['title' => 'IP白名单', 'subtitle' => 'IP访问控制', 'url' => 'admin.php?action=config&sub=ip_whitelist'],
            ['title' => '缓存设置', 'subtitle' => '缓存相关配置', 'url' => 'admin.php?action=config&sub=cache'],
            ['title' => '调试设置', 'subtitle' => '调试模式配置', 'url' => 'admin.php?action=config&sub=debug'],
        ];
        
        $query = strtolower($query);
        $results = [];
        
        foreach ($commands as $cmd) {
            if (strpos(strtolower($cmd['title']), $query) !== false ||
                strpos(strtolower($cmd['subtitle']), $query) !== false) {
                $results[] = [
                    'title' => $cmd['title'],
                    'subtitle' => $cmd['subtitle'],
                    'type' => 'command',
                    'url' => $cmd['url']
                ];
            }
        }
        
        return $results;
    }
}
