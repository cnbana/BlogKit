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
 * BlogKit API 统一入口
 * 加载 bootstrap.php 处理公共初始化，然后执行 API 专属逻辑。
 */

require_once __DIR__ . '/core/bootstrap.php';

// ========================================
// API 功能开关检查
// ========================================
$apiEnabled = Config::get('api_enabled', 1);
if (!$apiEnabled) {
    header('Content-Type: application/json');
    echo json_encode(['code' => 403, 'message' => 'API功能已禁用']);
    exit;
}

// ========================================
// CORS 跨域配置（仅 API 需要）
// ========================================
$corsEnabled = Config::get('api_cors_enabled', 0);
if ($corsEnabled) {
    $corsOrigins = Config::get('api_cors_origins', '*');
    header('Access-Control-Allow-Origin: ' . $corsOrigins);
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Credentials: true');

    // 处理 OPTIONS 预检请求
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit;
    }
}

// API 端点需要更严格的 CSP：允许跨域脚本但禁止内联
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none';");

// ========================================
// 加载钩子系统
// ========================================
if (file_exists(CORE_PATH . '/lib/Hook.php')) {
    require_once CORE_PATH . '/lib/Hook.php';
}

// ========================================
// 加载 API 路由器
// ========================================
require_once CORE_PATH . '/lib/ApiRouter.php';
require_once APP_PATH . '/Controllers/Front/ApiController.php';



// ========================================
// 请求路径解析
// ========================================
$requestUri    = $_SERVER['REQUEST_URI'] ?? '';
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestPath   = parse_url($requestUri, PHP_URL_PATH);

if (empty($requestPath) && !empty($requestUri)) {
    $requestPath = $requestUri;
}

$requestPath = str_replace('/api.php', '', $requestPath);

// ========================================
// API 版本管理
// ========================================
$apiVersion  = 'v1';
$versionPath = $requestPath;

// 移除请求路径开头的 api.php（如果存在）
$versionPath = preg_replace('#^/?api\.php/?#i', '/', $versionPath);

// 处理路径中的版本号
if (preg_match('#^/(v\d+)(/.*)$#', $versionPath, $versionMatches)) {
    $apiVersion  = $versionMatches[1];
    $versionPath = $versionMatches[2];
} elseif (preg_match('#^/(v\d+)$#', $versionPath, $versionMatches)) {
    $apiVersion  = $versionMatches[1];
    $versionPath = '/';
} elseif (!empty($_SERVER['HTTP_ACCEPT_VERSION'])) {
    $headerVersion = trim($_SERVER['HTTP_ACCEPT_VERSION']);
    if (preg_match('/^v\d+$/', $headerVersion)) {
        $apiVersion = $headerVersion;
    }
}

if (empty($versionPath) || $versionPath === '') {
    $versionPath = '/';
}

$GLOBALS['api_version'] = $apiVersion;

// ========================================
// API 版本号验证
// ========================================
$availableVersions = json_decode(Config::get('api_available_versions', '["v1"]'), true) ?: ['v1'];
$enabledVersions = json_decode(Config::get('api_enabled_versions', '["v1"]'), true) ?: ['v1'];

// 确保启用的版本在可用版本范围内
$validVersions = array_intersect($enabledVersions, $availableVersions);
if (empty($validVersions)) {
    $validVersions = ['v1'];
}

// 如果没有指定版本号，使用默认版本
if (!preg_match('#^/(v\d+)(/.*)?$#', $requestPath) && empty($_SERVER['HTTP_ACCEPT_VERSION'])) {
    $apiVersion = Config::get('api_default_version', 'v1');
}

// 确保请求的版本在启用的版本列表中
if (!in_array($apiVersion, $validVersions)) {
    header('Content-Type: application/json');
    echo json_encode([
        'code' => 404,
        'message' => 'API版本无效',
        'data' => [
            'requested_version' => $apiVersion,
            'supported_versions' => $validVersions
        ]
    ]);
    exit;
}

// ========================================
// API 路由定义 - 按版本分组
// ========================================

// v1 版本的路由
if ($apiVersion === 'v1') {
    // 测试路由
    ApiRouter::route('/test', 'TestController', 'index', ['GET']);
    
    // 文章相关API
    ApiRouter::route('/articles', 'ArticleApiController', 'index', ['GET']);
    ApiRouter::route('/articles/{id}', 'ArticleApiController', 'show', ['GET']);
    ApiRouter::route('/articles', 'ArticleApiController', 'store', ['POST']);
    ApiRouter::route('/articles/{id}', 'ArticleApiController', 'update', ['PUT', 'PATCH']);
    ApiRouter::route('/articles/{id}', 'ArticleApiController', 'destroy', ['DELETE']);
    ApiRouter::route('/articles/{id}/like', 'ArticleApiController', 'like', ['POST']);
    ApiRouter::route('/articles/{id}/unlike', 'ArticleApiController', 'unlike', ['POST']);
    ApiRouter::route('/articles/{id}/favorite', 'ArticleApiController', 'favorite', ['POST']);
    ApiRouter::route('/articles/{id}/unfavorite', 'ArticleApiController', 'unfavorite', ['POST']);
    ApiRouter::route('/articles/{id}/top', 'ArticleApiController', 'topArticle', ['POST']);
    ApiRouter::route('/articles/top', 'ArticleApiController', 'getTopArticles', ['GET']);
    ApiRouter::route('/articles/search', 'ArticleApiController', 'search', ['GET']);
    
    // 评论相关API
    ApiRouter::route('/articles/{article_id}/comments', 'CommentApiController', 'index', ['GET']);
    ApiRouter::route('/articles/{article_id}/comments', 'CommentApiController', 'store', ['POST']);
    ApiRouter::route('/comments/{id}', 'CommentApiController', 'update', ['PUT', 'PATCH']);
    ApiRouter::route('/comments/{id}', 'CommentApiController', 'destroy', ['DELETE']);
    ApiRouter::route('/comments/{id}/like', 'CommentApiController', 'like', ['POST']);
    ApiRouter::route('/comments/{id}/unlike', 'CommentApiController', 'unlike', ['POST']);
    
    // 用户相关API
    ApiRouter::route('/users', 'UserApiController', 'index', ['GET']);
    ApiRouter::route('/users/{id}', 'UserApiController', 'show', ['GET']);
    ApiRouter::route('/users', 'UserApiController', 'store', ['POST']);
    ApiRouter::route('/users/{id}', 'UserApiController', 'update', ['PUT', 'PATCH']);
    ApiRouter::route('/users/{id}', 'UserApiController', 'destroy', ['DELETE']);
    ApiRouter::route('/users/{id}/follow', 'UserApiController', 'follow', ['POST']);
    ApiRouter::route('/users/{id}/unfollow', 'UserApiController', 'unfollow', ['POST']);
    ApiRouter::route('/users/{id}/followers', 'UserApiController', 'followers', ['GET']);
    ApiRouter::route('/users/{id}/following', 'UserApiController', 'following', ['GET']);
    ApiRouter::route('/users/me', 'UserApiController', 'me', ['GET']);
    ApiRouter::route('/users/me/favorites', 'UserApiController', 'favorites', ['GET']);
    ApiRouter::route('/users/me/likes', 'UserApiController', 'likes', ['GET']);
    ApiRouter::route('/users/me/history', 'UserApiController', 'history', ['GET']);
    
    // 分类相关API
    ApiRouter::route('/categories', 'CategoryApiController', 'index', ['GET']);
    ApiRouter::route('/categories/{id}', 'CategoryApiController', 'show', ['GET']);
    ApiRouter::route('/categories', 'CategoryApiController', 'store', ['POST']);
    ApiRouter::route('/categories/{id}', 'CategoryApiController', 'update', ['PUT', 'PATCH']);
    ApiRouter::route('/categories/{id}', 'CategoryApiController', 'destroy', ['DELETE']);
    
    // 标签相关API
    ApiRouter::route('/tags', 'TagApiController', 'index', ['GET']);
    ApiRouter::route('/tags/{id}', 'TagApiController', 'show', ['GET']);
    ApiRouter::route('/tags', 'TagApiController', 'store', ['POST']);
    ApiRouter::route('/tags/{id}', 'TagApiController', 'update', ['PUT', 'PATCH']);
    ApiRouter::route('/tags/{id}', 'TagApiController', 'destroy', ['DELETE']);
    
    // 页面相关API
    ApiRouter::route('/pages', 'PageApiController', 'index', ['GET']);
    ApiRouter::route('/pages/{id}', 'PageApiController', 'show', ['GET']);
    ApiRouter::route('/pages/{slug}', 'PageApiController', 'showBySlug', ['GET']);
    
    // 系统相关API
    ApiRouter::route('/system/config', 'SystemApiController', 'config', ['GET']);
    ApiRouter::route('/system/info', 'SystemApiController', 'info', ['GET']);
    ApiRouter::route('/system/stats', 'SystemApiController', 'stats', ['GET']);
    
    // 认证相关API
    ApiRouter::route('/auth/login', 'AuthApiController', 'login', ['POST']);
    ApiRouter::route('/auth/register', 'AuthApiController', 'register', ['POST']);
    ApiRouter::route('/auth/logout', 'AuthApiController', 'logout', ['POST']);
    ApiRouter::route('/auth/forgot-password', 'AuthApiController', 'forgotPassword', ['POST']);
    ApiRouter::route('/auth/reset-password', 'AuthApiController', 'resetPassword', ['POST']);
    ApiRouter::route('/auth/refresh-token', 'AuthApiController', 'refreshToken', ['POST']);
    
    // 上传相关API
    ApiRouter::route('/upload/image', 'UploadApiController', 'image', ['POST'], ['auth']);
    ApiRouter::route('/upload/file', 'UploadApiController', 'file', ['POST'], ['auth']);
    ApiRouter::route('/upload/avatar', 'UploadApiController', 'avatar', ['POST'], ['auth']);
    
    // 搜索相关API
    ApiRouter::route('/search', 'SearchApiController', 'index', ['GET']);
    
    // 通知相关API
    ApiRouter::route('/notifications', 'NotificationApiController', 'index', ['GET'], ['auth']);
    ApiRouter::route('/notifications/unread', 'NotificationApiController', 'unread', ['GET'], ['auth']);
    ApiRouter::route('/notifications/{id}/read', 'NotificationApiController', 'read', ['PUT'], ['auth']);
    ApiRouter::route('/notifications/read-all', 'NotificationApiController', 'readAll', ['PUT'], ['auth']);
}

// v2 版本的路由 - 预留位置
// if ($apiVersion === 'v2') {
//     // 这里添加v2版本的路由，使用不同的控制器
//     // ApiRouter::route('/test', 'V2\\TestController', 'index', ['GET']);
//     // ...
// }

// ========================================
// 触发钩子
// ========================================
if (class_exists('Hook')) {
    Hook::trigger(Hook::SYSTEM_INIT);
}

if (class_exists('Hook')) {
    Hook::trigger(Hook::API_REQUEST_BEFORE, [
        'path'   => $requestPath,
        'method' => $requestMethod,
        'params' => $_REQUEST
    ]);
}

// ========================================
// API 路由分发
// ========================================
// 先找到匹配的路由，检查是否需要认证
$requiresAuth = false;

// 先匹配路由，检查是否需要认证
$matchedRoute = null;

// 检查路由是否匹配
uksort(ApiRouter::$routes, function($a, $b) {
    $pathA = explode(':', $a)[0];
    $pathB = explode(':', $b)[0];
    return strlen($pathB) - strlen($pathA);
});

foreach (ApiRouter::$routes as $routeKey => $routeConfig) {
    if ($routeConfig['http_method'] !== $requestMethod) {
        continue;
    }
    
    $routePath = $routeConfig['path'];
    $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '[^/]+', $routePath);
    $pattern = '#^' . $pattern . '$#';
    
    if (preg_match($pattern, $versionPath)) {
        $matchedRoute = $routeConfig;
        $requiresAuth = ApiRouter::checkRequiresAuth($versionPath) || 
                        in_array('auth', $routeConfig['middleware']) || 
                        in_array('admin', $routeConfig['middleware']);
        break;
    }
}

// 认证检查 - 只在需要认证的路由上执行
if ($requiresAuth) {
    $authType      = Config::get('api_auth_type', 'session');
    $authenticated = false;
    $currentUser   = null;

    switch ($authType) {
        case 'session':
            $authenticated = (isset($_SESSION['user']) && !empty($_SESSION['user'])) || 
                             (isset($_SESSION['admin']) && !empty($_SESSION['admin']));
            break;
        case 'api_key':
            $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_SERVER['HTTP_API_KEY'] ?? $_GET['api_key'] ?? null;
            if (!empty($apiKey)) {
                require_once APP_PATH . '/Models/ApiKeyModel.php';
                // 传入当前请求路径，使 ApiKeyModel 能基于 permissions 字段做路径级权限校验
                $result = ApiKeyModel::validate($apiKey, $requestPath);
                $authenticated = isset($result['valid']) && $result['valid'] === true;
                // 若权限校验失败（如 key 合法但路径不允许），返回具体错误信息给调用方
                if (!$authenticated && isset($result['error'])) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode([
                        'code' => 403,
                        'message' => $result['error']
                    ], JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }
            break;
        default:
            $authenticated = false;
            break;
    }

    if (!$authenticated) {
        header('Content-Type: application/json');
        echo json_encode(['code' => 401, 'message' => '认证失败，请检查认证信息']);
        exit;
    }
}

ApiRouter::dispatch($versionPath, $requestMethod);

// ========================================
// 触发响应后钩子
// ========================================
if (class_exists('Hook')) {
    Hook::trigger(Hook::API_RESPONSE_AFTER, [
        'path'   => $requestPath,
        'method' => $requestMethod
    ]);
}

if (class_exists('Hook')) {
    Hook::trigger(Hook::SYSTEM_SHUTDOWN);
}
