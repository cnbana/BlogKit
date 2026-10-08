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
    
    /**
     * 获取快捷操作（命令面板「快捷操作」分类）
     *
     * 与侧边栏菜单同源：直接读取当前管理员的菜单权限树动态生成，
     * 而非维护一份硬编码列表。收益：
     *   1. 功能剥离成插件后（如互动/通知/日志/API），核心菜单行随之增删，
     *      此处自动同步，不会搜出已不存在的页面或失效 URL；
     *   2. 插件启用/停用/卸载时菜单行状态由核心统一维护（停用置 0、
     *      卸载删行），本列表自动跟随，停用的插件页面搜不到；
     *   3. 按当前管理员角色的菜单授权过滤，无权限的页面搜不到；
     *   4. URL 永远与侧边栏一致（bk_permission.path），零二次维护。
     *
     * @param string $query 搜索关键词
     * @return array 匹配的快捷操作列表
     */
    private function getQuickCommands($query) {
        // 未登录后台会话时不返回任何快捷操作
        if (empty($_SESSION['admin']['id'])) {
            return [];
        }

        // 与侧边栏（components/sidebar_logic.html）同源取菜单树
        require_once APP_PATH . '/Models/PermissionModel.php';
        $menuTree = PermissionModel::getMenuPermissions($_SESSION['admin']['role']);

        // 递归收集叶子菜单项作为快捷操作
        $commands = [];
        $this->collectMenuCommands($menuTree, '', $commands);

        // 固定快捷动作：高频操作但不在菜单树中的入口（如列表页内的添加按钮）
        $commands[] = [
            'title'    => '添加文章',
            'subtitle' => '内容管理 / 文章管理',
            'url'      => 'admin.php?action=article&sub=add',
        ];

        // 关键词匹配：标题或副标题命中即返回
        $query = strtolower($query);
        $results = [];

        foreach ($commands as $cmd) {
            if (strpos(strtolower($cmd['title']), $query) !== false ||
                strpos(strtolower($cmd['subtitle']), $query) !== false) {
                $results[] = [
                    'title'    => $cmd['title'],
                    'subtitle' => $cmd['subtitle'],
                    'type'     => 'command',
                    'url'      => $cmd['url']
                ];
            }
        }

        return $results;
    }

    /**
     * 递归收集菜单树中的叶子菜单项（快捷操作候选）
     *
     * 仅收集「有跳转路径的叶子节点」；分组节点（无 path，如内容管理/
     * 系统设置等容器）只用于给子项生成「所属分组」副标题，自身不作为命令。
     *
     * @param array  $permissions 菜单权限节点列表
     * @param string $parentName  父级菜单名称（顶层节点为空串）
     * @param array  $commands    收集结果（引用累积）
     */
    private function collectMenuCommands($permissions, $parentName, &$commands) {
        foreach ($permissions as $menu) {
            $hasChildren = isset($menu['children']) && !empty($menu['children']);

            if ($hasChildren) {
                // 分组节点：深入子级，子项副标题挂到当前分组名下
                $this->collectMenuCommands($menu['children'], $menu['name'], $commands);
                continue;
            }

            // 叶子节点：无跳转路径的异常行跳过（防御）
            if (empty($menu['path']) || empty($menu['name'])) {
                continue;
            }

            $commands[] = [
                'title'    => $menu['name'],
                'subtitle' => $parentName !== '' ? ('位于「' . $parentName . '」') : '后台页面',
                'url'      => $menu['path'],
            ];
        }
    }
}
