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
 * API设置控制器
 * 负责处理API设置和接口列表相关的请求
 */
class ApiConfigController {
    
    /**
     * API设置首页
     */
    public function index() {
        $db = Database::getInstance();
        $currentDomain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
        
        // 获取系统配置
        $configs = $db->fetchAll("SELECT * FROM {$db->table('config')} ORDER BY name ASC");
        
        // 配置默认值
        $defaultConfig = require APP_PATH . '/Config/default.php';
        $defaultConfig['site_url'] = 'http://' . $currentDomain;
        $defaultConfig['cache_path'] = STORAGE_PATH . '/cache';
        
        // 将配置转换为关联数组
        $config = [];
        foreach ($configs as $item) {
            $config[$item['name']] = $item['value'];
        }
        
        // 合并默认配置
        $config = array_merge($defaultConfig, $config);
        
        // API子页面变量 - 与 ConfigController 保持一致
        $apiSubPages = [
            'settings' => 'API设置',
            'list' => 'API接口列表'
        ];
        
        // 获取子页面参数
        $apiSubPage = isset($_GET['sub']) ? $_GET['sub'] : 'settings';
        
        // 如果是API接口列表页面，解析路由
        if ($apiSubPage == 'list') {
            $apiRoutes = [];
            $apiRoutesFile = __DIR__ . '/../../../api.php';
            
            if (file_exists($apiRoutesFile)) {
                $fileContent = file_get_contents($apiRoutesFile);
                
                preg_match_all("/ApiRouter::route\(\s*'([^']+)'\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*,\s*\[(.*?)\]\s*(?:,\s*\[(.*?)\])?\s*\)/", $fileContent, $matches, PREG_SET_ORDER);
                
                $groupedRoutes = [];
                foreach ($matches as $match) {
                    $verbs = [];
                    if (!empty($match[4])) {
                        preg_match_all("/'([^']+)'/", $match[4], $verbMatches);
                        $verbs = $verbMatches[1];
                    }
                    
                    $middleware = [];
                    if (!empty($match[5])) {
                        preg_match_all("/'([^']+)'/", $match[5], $middlewareMatches);
                        $middleware = $middlewareMatches[1];
                    }
                    
                    $path = $match[1];
                    $controller = $match[2];
                    $action = $match[3];
                    
                    if (!isset($groupedRoutes[$path])) {
                        $groupedRoutes[$path] = [
                            'path' => $path,
                            'controller' => $controller,
                            'action' => $action,
                            'verbs' => $verbs,
                            'middleware' => $middleware
                        ];
                    } else {
                        $groupedRoutes[$path]['verbs'] = array_unique(array_merge($groupedRoutes[$path]['verbs'], $verbs));
                        $groupedRoutes[$path]['middleware'] = array_unique(array_merge($groupedRoutes[$path]['middleware'], $middleware));
                    }
                }
                
                $apiRouteNames = [
                    '/test' => '测试接口',
                    '/articles' => '文章列表/创建文章',
                    '/articles/{id}' => '文章详情/更新文章/删除文章',
                    '/articles/{id}/like' => '点赞文章',
                    '/articles/{id}/unlike' => '取消点赞',
                    '/articles/{id}/favorite' => '收藏文章',
                    '/articles/{id}/unfavorite' => '取消收藏',
                    '/articles/search' => '搜索文章',
                    '/articles/top' => '获取置顶文章列表',
                    '/articles/{id}/top' => '置顶/取消置顶文章',
                    '/articles/{article_id}/comments' => '获取/创建文章评论',
                    '/comments/{id}' => '更新/删除评论',
                    '/comments/{id}/like' => '点赞评论',
                    '/comments/{id}/unlike' => '取消点赞评论',
                    '/users' => '用户列表/创建用户',
                    '/users/{id}' => '用户详情/更新用户/删除用户',
                    '/users/{id}/follow' => '关注用户',
                    '/users/{id}/unfollow' => '取消关注',
                    '/users/{id}/followers' => '获取用户粉丝',
                    '/users/{id}/following' => '获取用户关注',
                    '/users/me' => '当前用户信息',
                    '/users/me/favorites' => '当前用户收藏',
                    '/users/me/likes' => '当前用户点赞',
                    '/users/me/history' => '当前用户历史',
                    '/categories' => '分类列表/创建分类',
                    '/categories/{id}' => '分类详情/更新分类/删除分类',
                    '/tags' => '标签列表/创建标签',
                    '/tags/{id}' => '标签详情/更新标签/删除标签',
                    '/pages' => '页面列表/创建页面',
                    '/pages/{id}' => '页面详情',
                    '/pages/{slug}' => '按别名获取页面',
                    '/system/config' => '系统配置',
                    '/system/info' => '系统信息',
                    '/system/stats' => '系统统计',
                    '/auth/login' => '用户登录',
                    '/auth/register' => '用户注册',
                    '/auth/logout' => '用户登出',
                    '/auth/forgot-password' => '忘记密码',
                    '/auth/reset-password' => '重置密码',
                    '/auth/refresh-token' => '刷新令牌',
                    '/upload/image' => '上传图片',
                    '/upload/file' => '上传文件',
                    '/upload/avatar' => '上传头像',
                    '/search' => '全局搜索',
                    '/notifications' => '通知列表',
                    '/notifications/unread' => '获取未读通知',
                    '/notifications/{id}/read' => '标记通知已读',
                    '/notifications/read-all' => '全部标记已读'
                ];
                
                foreach ($groupedRoutes as $route) {
                    $auth = '否';
                    $path = $route['path'];
                    $verbs = $route['verbs'];
                    $middleware = $route['middleware'];
                    
                    if (in_array('auth', $middleware)) {
                        $auth = '是';
                    } elseif (strpos($path, '/auth/') === 0) {
                        $auth = '否';
                    } elseif (count(array_intersect($verbs, ['POST', 'PUT', 'DELETE', 'PATCH'])) > 0) {
                        $auth = '是';
                    } elseif (strpos($path, '/users/me') === 0 || strpos($path, '/users/') !== false && (strpos($path, '/follow') !== false || strpos($path, '/followers') !== false || strpos($path, '/following') !== false)) {
                        $auth = '是';
                    } elseif (strpos($path, '/like') !== false || strpos($path, '/unlike') !== false || strpos($path, '/favorite') !== false || strpos($path, '/unfavorite') !== false) {
                        $auth = '是';
                    } elseif (strpos($path, '/upload') === 0 || strpos($path, '/notifications') === 0) {
                        $auth = '是';
                    }
                    
                    $name = $apiRouteNames[$path] ?? ucfirst(str_replace('ApiController', '', $route['controller'])) . '::' . ucfirst($route['action']);
                    
                    $apiRoutes[] = [
                        'name' => $name,
                        'path' => $route['path'],
                        'controller' => $route['controller'],
                        'verbs' => $route['verbs'],
                        'auth' => $auth
                    ];
                }
            }
        }
        
        // 设置当前页面变量用于侧边栏高亮 - 与 CacheController 保持一致
        $sub = 'api';
        $currentAction = 'api';
        
        // 使用标准的 config.html 模板
        include ADMIN_PATH . '/templates/config.html';
    }
    
    /**
     * 保存API配置
     */
    public function save() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST['sub_page'] = 'api';
            
            try {
                $result = ConfigService::save($_POST, $_FILES);
                
                if (!$result['success']) {
                    $_SESSION['config_errors'] = $result['errors'];
                } else {
                    $_SESSION['config_success'] = '配置保存成功！';
                }
            } catch (PDOException $e) {
                error_log('ApiConfigController config save DB: ' . $e->getMessage());
                $_SESSION['config_errors'] = ['database' => '保存配置时发生数据库错误'];
            } catch (Exception $e) {
                error_log('ApiConfigController config save: ' . $e->getMessage());
                $_SESSION['config_errors'] = ['file' => '保存配置时发生文件错误'];
            }
            
            header('Location: admin.php?action=api');
            exit;
        }
    }
}
