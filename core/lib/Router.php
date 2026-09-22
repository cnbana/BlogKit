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
 * BlogKit 前台路由系统 (v2.0 重构版)
 * 
 * 将原有的 30+ 个 if/preg_match 分支重构为配置驱动的路由表，
 * 消除约 300 行重复代码和 12 行死代码。
 * 
 * 路由定义格式: [name, configKey, defaultPattern, idRegex|null, controller, method]
 */

class Router {
    private static $instance = null;
    private $controller = 'Home';
    private $method = 'index';
    private $params = [];
    
    /**
     * 路由定义表（配置驱动，统一管理所有前台路由）
     * 
     * - name: 路由标识（仅文档用途）
     * - configKey: 对应的 Config 键名，用于获取自定义重写规则
     * - defaultPattern: 默认 URL 模式，{id} 为参数占位符
     * - idRegex: 参数匹配正则，null 表示无参数
     * - controller/method: 目标控制器和方法
     */
    private $routeDefinitions = [
        // === Home 控制器（带 ID 参数） ===
        ['home_article',   'rewrite_article',   'article/{id}',   '([a-zA-Z0-9_-]+)', 'Home', 'article'],  // 同时支持 slug（含大写字母/下划线）和数字 ID
        ['home_category',  'rewrite_category',  'category/{id}',  '([\w\-]+)',    'Home', 'category'],
        ['home_tag',       'rewrite_tag',       'tag/{id}',       '(\d+)',        'Home', 'tag'],
        ['home_page',      'rewrite_page',      'page/{id}',      '([\w\-]+)',    'Home', 'page'],
        
        // === Home 控制器（无参数） ===
        ['home_tags',      'rewrite_tags',      'tags',           null,           'Home', 'tags'],
        ['home_search',    'rewrite_search',    'search',         null,           'Home', 'search'],
        ['home_archives',  'rewrite_archives',  'archives',       null,           'Home', 'archives'],
        
        // === RSS ===
        ['rss',            'rewrite_rss',       'rss',            null,           'RSS',  'feed'],
        
        // === Auth 控制器（无参数） ===
        ['auth_login',             'rewrite_login',             'login',            null, 'Auth', 'login'],
        ['auth_register',          'rewrite_register',          'register',         null, 'Auth', 'register'],
        ['auth_logout',            'rewrite_logout',            'logout',           null, 'Auth', 'logout'],
        ['auth_profile',           'rewrite_profile',           'profile',          null, 'Auth', 'profile'],
        ['auth_forgot_password',   'rewrite_forgot_password',   'forgot-password',  null, 'Auth', 'forgotPassword'],
        ['auth_reset_password',    'rewrite_reset_password',    'reset-password',   null, 'Auth', 'resetPassword'],
        
        // === Auth 控制器（带 ID 参数） ===
        ['auth_user',              'rewrite_user',              'user/{id}',        '(\d+)', 'Auth', 'user'],
        
        // === User 控制器（follow 相关） ===
        ['user_follow',            'rewrite_follow',            'follow/{id}',     '(\d+)', 'User', 'follow'],
        ['user_following',         'rewrite_following',         'following/{id}',  '(\d+)', 'User', 'following'],
        ['user_followers',         'rewrite_followers',         'followers/{id}',  '(\d+)', 'User', 'followers'],
        
        // === Auth 控制器（profile 子页面，无参数） ===
        ['profile_articles',       'rewrite_profile_articles',       'profile/articles',       null, 'Auth', 'profileArticles'],
        ['profile_comments',       'rewrite_profile_comments',       'profile/comments',       null, 'Auth', 'profileComments'],
        ['profile_settings',       'rewrite_profile_settings',       'profile/settings',       null, 'Auth', 'profileSettings'],
        ['profile_favorites',      'rewrite_profile_favorites',      'profile/favorites',      null, 'Auth', 'profileFavorites'],
        ['profile_likes',          'rewrite_profile_likes',          'profile/likes',          null, 'Auth', 'profileLikes'],
        ['profile_notifications',  'rewrite_profile_notifications',  'profile/notifications',  null, 'Auth', 'profileNotifications'],
        ['profile_history',        'rewrite_profile_history',        'profile/history',        null, 'Auth', 'profileHistory'],
        ['profile_following',      'rewrite_profile_following',      'profile/following',      null, 'Auth', 'profileFollowing'],
        ['profile_followers',      'rewrite_profile_followers',      'profile/followers',      null, 'Auth', 'profileFollowers'],
    ];

    /**
     * Router constructor
     */
    public function __construct() {
        // 特殊处理：/index.php/profile?m=XXX 格式的URL
        $requestUri = $_SERVER['REQUEST_URI'];
        $isProfileRequest = false;
        $profilePattern = '/^\/index\.php\/profile(\?|$)/';
        if (preg_match($profilePattern, $requestUri)) {
            $isProfileRequest = true;
        } else {
            $profilePattern = '/^index\.php\/profile(\?|$)/';
            if (preg_match($profilePattern, $requestUri)) {
                $isProfileRequest = true;
            }
        }
        
        if ($isProfileRequest) {
            $this->controller = 'Auth';
            $this->method = 'profile';
            $this->params = [];
            return;
        }

        // 首先检查是否有传统的查询字符串参数 (?c=controller&m=method)
        // 注意：此处仅将 `id` 作为位置参数传入方法。
        // 其他查询参数（如 filter、page 等）保留在 $_GET 中，由方法自行读取。
        // 这样可以避免 HomeController::category/article/tag/page/user 等方法
        // 通过 `func_get_args() + end()` 取最后一个参数时，误把 filter 值当作 id。
        if (isset($_GET['c']) || isset($_GET['m'])) {
            if (isset($_GET['c'])) {
                $this->controller = ucfirst($_GET['c']);
            }
            if (isset($_GET['m'])) {
                $this->method = $_GET['m'];
            } else {
                $this->method = 'index';
            }
            $this->params = [];
            if (isset($_GET['id']) && $_GET['id'] !== '') {
                $this->params[] = $_GET['id'];
            }
            return;
        }
        $this->parseUrl();
    }
    
    /**
     * 统一的路由解析方法
     * 用路由表替代原有的 30+ if/preg_match 分支
     * 
     * @param string $url 待匹配的 URL 路径
     * @param bool $useRewrite 是否尝试自定义重写规则（主入口为 true，applyRoutingRules 中为 true）
     * @return bool 是否成功匹配
     */
    private function resolveRoute($url, $useRewrite = true) {
        $rewriteEnabled = $useRewrite && Config::get('rewrite.enabled');
        
        // 额外处理：非重写模式下的 feed 别名（匹配 /feed 和 /rss）
        if (preg_match('/^(rss|feed)$/', $url)) {
            $this->controller = 'RSS';
            $this->method = 'feed';
            $this->params = [];
            return true;
        }
        
        // 占位符名称 → 正则表达式映射表
        // 用户在后台填写规则时可以使用 {id}, {slug}, {name}, {page}, {year}, {month}, {day} 等
        $placeholderRegexMap = [
            'article_id'    => '(\d+)',                    // 文章ID（纯数字，同 id）
            'author'        => '([a-zA-Z0-9_\-\x{4e00}-\x{9fa5}]+)', // 作者昵称/用户名（支持中文）
            'author_id'     => '(\d+)',                    // 作者ID（纯数字）
            'author_slug'   => '([a-z0-9\-]+)',            // 作者别名（Slug 格式）
            'cat_id'        => '(\d+)',                    // 分类ID（纯数字，同 category_id）
            'cat_slug'      => '([a-z0-9\-]+)',            // 分类别名（Slug 格式，同 category_slug）
            'category'      => '([a-z0-9\-]+)',            // 分类别名（Slug 格式）
            'category_id'   => '(\d+)',                    // 分类ID（纯数字）
            'category_name' => '([^\/]+)',                 // 分类名称（非斜杠任意字符）
            'category_slug' => '([a-z0-9\-]+)',            // 分类别名（Slug 格式）
            'day'           => '(\d{1,2})',                // 1-2 位日期（如：5, 05）
            'hour'          => '(\d{2})',                  // 小时（2 位数字，如：09）
            'id'            => '(\d+)',                    // 纯数字（如：123）
            'minute'        => '(\d{2})',                  // 分钟（2 位数字，如：05）
            'month'         => '(\d{1,2})',                // 1-2 位月份（如：6, 06）
            'monthnum'      => '(\d{1,2})',                // 月份数字（同 month）
            'name'          => '([\w\-]+)',                // 名称（字母/数字/下划线/短横线，如：Web_Dev）
            'nickname'      => '([^\/]+)',                 // 昵称（非斜杠任意字符）
            'page'          => '(\d+)',                    // 页码（纯数字）
            'post_id'       => '(\d+)',                    // 文章ID（纯数字，同 id）
            'postname'      => '([a-z0-9\-]+)',            // 文章别名（Slug 格式，同 slug）
            'second'        => '(\d{2})',                  // 秒（2 位数字，如：30）
            'slug'          => '([a-z0-9\-]+)',            // Slug（小写字母/数字/短横线，如：hello-world）
            'tag'           => '([a-z0-9\-]+)',            // 标签别名（Slug 格式）
            'tag_id'        => '(\d+)',                    // 标签ID（纯数字）
            'tag_name'      => '([^\/]+)',                 // 标签名称（非斜杠任意字符）
            'tag_slug'      => '([a-z0-9\-]+)',            // 标签别名（Slug 格式）
            'title'         => '([^\/]+)',                 // 文章/页面标题（非斜杠任意字符）
            'user_id'       => '(\d+)',                    // 用户ID（纯数字，同 author_id）
            'username'      => '([a-zA-Z0-9_]+)',          // 用户名（字母/数字/下划线）
            'view_count'    => '(\d+)',                    // 浏览量（纯数字，同 views）
            'views'         => '(\d+)',                    // 浏览量（纯数字）
            'year'          => '(\d{4})',                  // 4 位年份（如：2026）
        ];

        foreach ($this->routeDefinitions as $def) {
            list($name, $configKey, $defaultPattern, $idRegex, $controller, $method) = $def;

            // 获取实际使用的模式（优先使用用户在后台设置的自定义重写规则）
            $routePattern = $defaultPattern;
            if ($rewriteEnabled) {
                $customPattern = Config::get($configKey);
                if ($customPattern) {
                    $routePattern = $customPattern;
                }
            }

            // 1. 从模式中提取所有 {xxx} 占位符（如 {id}, {slug}, {year}/{month}）
            $hasPlaceholders = preg_match_all('/\{(\w+)\}/', $routePattern, $phMatches);
            $placeholders = $hasPlaceholders ? $phMatches[1] : [];

            // 2. 构建正则：转义斜杠，然后逐个替换占位符
            $pattern = str_replace('/', '\/', $routePattern);

            if (!empty($placeholders)) {
                // 有占位符 → 需要解析参数
                foreach ($placeholders as $phName) {
                    if ($phName === 'id' && $idRegex !== null) {
                        // 向后兼容：{id} 优先使用路由定义中的 $idRegex
                        // 这样分类路由的 ([\w\-]+) 可以继续匹配 slug 风格
                        $pattern = str_replace('{id}', $idRegex, $pattern);
                    } elseif (isset($placeholderRegexMap[$phName])) {
                        // 使用预定义的正则
                        $pattern = str_replace('{' . $phName . '}', $placeholderRegexMap[$phName], $pattern);
                    } else {
                        // 未知占位符：通用匹配（除斜杠外的任意字符）
                        $pattern = str_replace('{' . $phName . '}', '([^\/]+)', $pattern);
                    }
                }

                // 匹配并收集所有参数
                if (preg_match('/^' . $pattern . '$/', $url, $matches)) {
                    array_shift($matches);          // 去掉完整匹配
                    $this->controller = $controller;
                    $this->method = $method;
                    $this->params = $matches;        // 所有捕获的参数（可能多个）
                    return true;
                }
            } else {
                // 无占位符 → 精确匹配（如 login, register, search 等固定页面）
                if (preg_match('/^' . $pattern . '$/', $url)) {
                    $this->controller = $controller;
                    $this->method = $method;
                    $this->params = [];
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * 解析URL
     */
    private function parseUrl() {
        $url = $_SERVER['REQUEST_URI'];
        
        // 移除网站根路径
        $siteUrl = parse_url(Config::get('site.url'));
        if (isset($siteUrl['path']) && $siteUrl['path'] != '/') {
            $url = str_replace($siteUrl['path'], '', $url);
        }
        
        // 检查是否是传统动态URL格式 (index.php?c=Controller&m=method&id=value)
        // 或 /index.php/controller?method=getList 格式
        if (strpos($url, 'index.php') !== false && strpos($url, '?') !== false) {
            $queryString = substr($url, strpos($url, '?') + 1);
            parse_str($queryString, $queryParams);
            
            if (strpos($url, 'index.php/') === 0 || strpos($url, '/index.php/') === 0) {
                // 格式为 /index.php/controller?method=getList 或 index.php/controller?method=getList
                if (strpos($url, '/index.php/') === 0) {
                    $controllerPart = substr($url, strlen('/index.php/'));
                } else {
                    $controllerPart = substr($url, strlen('index.php/'));
                }
                $controllerPart = strtok($controllerPart, '?');
                
                $this->applyRoutingRules($controllerPart, $queryParams);
                return;
            } else if (isset($queryParams['c'])) {
                // 格式为 index.php?c=controller&m=method
                $this->controller = ucfirst($queryParams['c']);
            }
            
            if (isset($queryParams['method'])) {
                $this->method = $queryParams['method'];
                unset($queryParams['method']);
            } else if (isset($queryParams['m'])) {
                $this->method = $queryParams['m'];
                unset($queryParams['m']);
            } else {
                $this->method = 'index';
            }
            
            if (!isset($this->controller)) {
                $this->controller = 'Home';
                $this->method = 'index';
                $this->params = [];
                return;
            }
            
            $this->params = [];
            unset($queryParams['c']);
            if (!empty($queryParams)) {
                if (isset($queryParams['id'])) {
                    $this->params[] = $queryParams['id'];
                    unset($queryParams['id']);
                }
                foreach ($queryParams as $param) {
                    $this->params[] = $param;
                }
            }
            return;
        }
        
        // 移除查询字符串
        $url = strtok($url, '?');
        
        // 移除前面的斜杠
        $url = trim($url, '/');
        
        // 处理带index.php前缀的URL格式 (如 /index.php/article/1)
        if (strpos($url, 'index.php/') === 0) {
            $url = substr($url, strlen('index.php/'));
        }
        
        // 特殊处理：直接访问index.php时，使用默认控制器
        if ($url == 'index.php') {
            $this->controller = 'Home';
            $this->method = 'index';
            $this->params = [];
            return;
        }
        
        // ========== 统一路由匹配（替代原有的 30+ if/preg_match 分支） ==========
        if ($this->resolveRoute($url, true)) {
            return;
        }
        
        // 特殊处理：/profile 始终可用，防止被自定义重写规则覆盖
        if (preg_match('/^profile$/', $url)) {
            $this->controller = 'Auth';
            $this->method = 'profile';
            $this->params = [];
            return;
        }
        
        // 处理URL重写（保留旧方法以兼容）
        if (Config::get('rewrite.enabled')) {
            $url = $this->applyRewriteRules($url);
        }
        
        // 解析参数
        $parts = explode('/', $url);
        
        // 获取控制器
        if (!empty($parts[0])) {
            $this->controller = ucfirst($parts[0]);
            unset($parts[0]);
            $parts = array_values($parts);
        }
        
        // 获取方法
        if (!empty($parts[0])) {
            $this->method = $parts[0];
            unset($parts[0]);
            $parts = array_values($parts);
        }
        
        // 获取剩余参数
        $this->params = $parts;
    }
    
    /**
     * 为带index.php前缀的URL应用路由规则
     * @param string $controllerPart 控制器部分
     * @param array $queryParams 查询参数
     */
    private function applyRoutingRules($controllerPart, $queryParams) {
        // ========== 统一路由匹配 ==========
        if ($this->resolveRoute($controllerPart, true)) {
            return;
        }
        
        // 特殊处理：/profile 始终可用，防止被自定义重写规则覆盖
        if (preg_match('/^profile$/', $controllerPart)) {
            $this->controller = 'Auth';
            $this->method = 'profile';
            $this->params = [];
            return;
        }
        
        // 处理URL重写（保留旧方法以兼容）
        if (Config::get('rewrite.enabled')) {
            $controllerPart = $this->applyRewriteRules($controllerPart);
        }
        
        // 解析参数
        $parts = explode('/', $controllerPart);
        
        if (!empty($parts[0])) {
            $this->controller = ucfirst($parts[0]);
            unset($parts[0]);
            $parts = array_values($parts);
        }
        
        if (!empty($parts[0])) {
            $this->method = $parts[0];
            unset($parts[0]);
            $parts = array_values($parts);
        } else {
            if (isset($queryParams['method'])) {
                $this->method = $queryParams['method'];
                unset($queryParams['method']);
            } else if (isset($queryParams['m'])) {
                $this->method = $queryParams['m'];
                unset($queryParams['m']);
            } else {
                $this->method = 'index';
            }
        }
        
        $this->params = $parts;
        
        unset($queryParams['c']);
        if (!empty($queryParams)) {
            if (isset($queryParams['id'])) {
                array_unshift($this->params, $queryParams['id']);
                unset($queryParams['id']);
            }
            foreach ($queryParams as $param) {
                $this->params[] = $param;
            }
        }
    }

    /**
     * 应用URL重写规则
     * @param string $url 原始URL
     * @return string 重写后的URL
     */
    private function applyRewriteRules($url) {
        $rules = Config::get('rewrite.rules');
        
        foreach ($rules as $pattern => $target) {
            $patternRegex = str_replace('/', '\/', $pattern);
            $patternRegex = preg_replace('/:(\w+)/', '(\\w+)', $patternRegex);
            $patternRegex = '/^' . $patternRegex . '$/';
            
            if (preg_match($patternRegex, $url, $matches)) {
                array_shift($matches);
                
                $params = [];
                preg_match_all('/:(\w+)/', $target, $paramNames);
                foreach ($paramNames[1] as $index => $name) {
                    if (isset($matches[$index])) {
                        $params[':' . $name] = $matches[$index];
                    }
                }
                
                return str_replace(array_keys($params), array_values($params), $target);
            }
        }
        
        return $url;
    }
    
    /**
     * 分发请求
     */
    public function dispatch() {
        $controllerFile = ROOT_PATH . '/app/Controllers/Front/' . $this->controller . 'Controller.php';
        
        if (file_exists($controllerFile)) {
            require_once $controllerFile;
            $controllerClass = $this->controller . 'Controller';
            $controller = new $controllerClass();
            
            if (method_exists($controller, $this->method)) {
                call_user_func_array([$controller, $this->method], $this->params);
            } else {
                $this->show404();
            }
        } else {
            $this->show404();
        }
    }
    
    /**
     * 显示404页面
     */
    private function show404() {
        require_once ROOT_PATH . '/app/Controllers/Front/HomeController.php';
        $homeController = new HomeController();
        $homeController->notFound();
        exit;
    }
    
    /**
     * 获取单例实例（用于插件系统服务容器）
     * @return Router
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
