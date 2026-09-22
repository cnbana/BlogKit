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
 * API路由器
 * 统一管理所有API接口
 * 
 * 使用方法：
 * 1. 定义API路由规则
 * 2. 自动路由到对应的控制器方法
 * 3. 统一的响应格式和错误处理
 */

class ApiRouter {
    
    public static $routes = [];
    private static $middlewares = [];
    private static $requiresAuth = [];
    
    /**
     * 注册API路由
     * @param string $path 路由路径
     * @param string $controller 控制器类名
     * @param string $method 控制器方法名
     * @param array $methods 允许的HTTP方法
     * @param array $middleware 中间件
     */
    public static function route($path, $controller, $method, $methods = ['GET', 'POST'], $middleware = []) {
        // 记录哪些路由需要认证
        if (in_array('auth', $middleware) || in_array('admin', $middleware)) {
            self::$requiresAuth[] = $path;
        }
        
        // 为每个HTTP方法分别注册路由
        foreach ($methods as $httpMethod) {
            $routeKey = $path . ':' . $httpMethod;
            self::$routes[$routeKey] = [
                'controller' => $controller,
                'method' => $method,
                'methods' => $methods,
                'middleware' => $middleware,
                'path' => $path,
                'http_method' => $httpMethod
            ];
        }
    }
    
    /**
     * 检查路由是否需要认证
     * @param string $requestPath 请求路径
     * @return bool
     */
    public static function checkRequiresAuth($requestPath) {
        // 先匹配精确路径
        if (in_array($requestPath, self::$requiresAuth)) {
            return true;
        }
        
        // 检查带参数的路由
        foreach (self::$requiresAuth as $authPath) {
            // 将路径中的 {param} 替换为正则匹配
            $pattern = preg_replace('#\{[^/]+\}#', '[^/]+', $authPath);
            $pattern = '#^' . $pattern . '$#';
            
            if (preg_match($pattern, $requestPath)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 注册中间件
     * @param string $name 中间件名称
     * @param callable $callback 中间件回调
     */
    public static function middleware($name, $callback) {
        self::$middlewares[$name] = $callback;
    }
    
    /**
     * 执行中间件
     * @param array $middleware 中间件数组
     * @return bool 是否通过
     */
    private static function executeMiddleware($middleware) {
        foreach ($middleware as $name) {
            if (isset(self::$middlewares[$name])) {
                $result = call_user_func(self::$middlewares[$name]);
                if ($result === false) {
                    return false;
                }
            }
        }
        return true;
    }
    
    /**
     * 路由请求
     * @param string $requestPath 请求路径
     * @param string $requestMethod 请求方法
     */
    public static function dispatch($requestPath, $requestMethod) {
        // 记录请求开始时间
        $GLOBALS['apiStartTime'] = microtime(true);
        
        // 触发API请求前钩子
        if (class_exists('Hook')) {
            Hook::trigger(Hook::API_REQUEST_BEFORE, [
                'path' => $requestPath,
                'method' => $requestMethod,
                'params' => $_REQUEST
            ]);
        }
        
        // 查找匹配的路由
        $matchedRoute = null;
        $pathParams = [];
        
        // 按路由长度排序，确保短路由优先匹配
        uksort(self::$routes, function($a, $b) {
            $pathA = explode(':', $a)[0];
            $pathB = explode(':', $b)[0];
            return strlen($pathB) - strlen($pathA); // 长路径优先匹配
        });
        
        foreach (self::$routes as $routeKey => $routeConfig) {
            // 只检查当前请求方法的路由
            if ($routeConfig['http_method'] !== $requestMethod) {
                continue;
            }
            
            // 获取实际路由路径
            $routePath = $routeConfig['path'];
            
            // 检查路径是否匹配（支持参数）
            $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '([^/]+)', $routePath);
            $pattern = '#^' . $pattern . '$#';
            
            if (preg_match($pattern, $requestPath, $matches)) {
                // 提取路径参数
                array_shift($matches);
                preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $routePath, $paramNames);
                $paramNames = $paramNames[1];
                
                foreach ($paramNames as $index => $paramName) {
                    $pathParams[$paramName] = $matches[$index] ?? null;
                }
                
                $matchedRoute = $routeConfig;
                break;
            }
        }
        
        if (!$matchedRoute) {
            self::sendError('API接口不存在', 404);
            return;
        }
        
        // 执行中间件
        if (!self::executeMiddleware($matchedRoute['middleware'])) {
            return;
        }
        
        // 实例化控制器
        $controllerClass = $matchedRoute['controller'];
        $controllerMethod = $matchedRoute['method'];
        
        if (!class_exists($controllerClass)) {
            return;
        }
        
        $controller = new $controllerClass();
        
        if (!method_exists($controller, $controllerMethod)) {
            self::sendError('控制器方法不存在：' . $controllerMethod, 500);
            return;
        }
        
        // 调用控制器方法
        try {
            $result = call_user_func([$controller, $controllerMethod], $pathParams);
            
            // 触发API响应前钩子
            if (class_exists('Hook')) {
                Hook::trigger(Hook::API_RESPONSE_BEFORE, [
                    'path' => $requestPath,
                    'method' => $requestMethod,
                    'result' => $result
                ]);
            }
            
            // 触发API响应后钩子
            if (class_exists('Hook')) {
                Hook::trigger(Hook::API_RESPONSE_AFTER, [
                    'path' => $requestPath,
                    'method' => $requestMethod,
                    'result' => $result
                ]);
            }
            
        } catch (Exception $e) {
            // 触发API错误钩子（日志记录完整异常，但不返回给客户端）
            if (class_exists('Hook')) {
                Hook::trigger(Hook::API_ERROR, [
                    'path' => $requestPath,
                    'method' => $requestMethod,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            
            // 生产环境返回脱敏后的通用错误消息
            $message = Config::get('debug.enabled', false)
                ? '服务器内部错误：' . $e->getMessage()
                : '服务器繁忙，请稍后重试';
            self::sendError($message, HTTP_INTERNAL_ERROR);
            return;
        }
    }
    
    /**
     * 发送成功响应
     * @param mixed $data 响应数据
     * @param string $message 成功消息
     * @param int $code 状态码
     */
    public static function success($data = null, $message = '操作成功', $code = 200) {
        self::sendResponse([
            'code' => $code,
            'message' => $message,
            'data' => $data,
            'timestamp' => time()
        ]);
    }
    
    /**
     * 发送错误响应
     * @param string $message 错误消息
     * @param int $code 状态码
     * @param mixed $errors 错误详情
     */
    public static function sendError($message = '操作失败', $code = 500, $errors = null) {
        $response = [
            'code' => $code,
            'message' => $message,
            'timestamp' => time()
        ];
        
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        
        self::sendResponse($response);
    }
    
    /**
     * 发送响应
     * @param array $data 响应数据
     */
    private static function sendResponse($data) {
        // 根据后台 api_response_format 选择 JSON / XML
        $format = Config::get('api_response_format', 'json');
        $isXml = ($format === 'xml');

        // 也允许通过请求参数覆盖后台默认值
        if (isset($_GET['format'])) {
            $reqFormat = strtolower(trim($_GET['format']));
            if ($reqFormat === 'xml' || $reqFormat === 'json') {
                $isXml = ($reqFormat === 'xml');
            }
        }

        // HTTP 基础头
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, Accept-Version');
        header('X-API-Version: ' . self::getApiVersion());

        // 在响应数据中附加版本信息
        $data['api_version'] = self::getApiVersion();

        if ($isXml) {
            // —— XML 响应
            header('Content-Type: application/xml; charset=utf-8');
            echo self::arrayToXml($data);
        } else {
            // —— 默认 JSON 响应
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($data, JSON_UNESCAPED_UNICODE);
        }
        
        // 记录API请求日志
        if (class_exists('Log')) {
            require_once CORE_PATH . '/lib/Log.php';
            Log::init();
            
            // 获取请求路径和方法
            $requestPath = $_SERVER['REQUEST_URI'] ?? '';
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            
            // 计算响应时间
            $endTime = microtime(true);
            $executionTime = round(($endTime - $GLOBALS['apiStartTime']) * 1000, 2);
            
            // 获取请求数据
            $requestData = [];
            if ($requestMethod === 'POST' || $requestMethod === 'PUT') {
                $requestData = $_POST;
            } else {
                $requestData = $_GET;
            }
            
            // 记录API日志
            Log::api($requestMethod, $requestPath, $data['code'], $executionTime, $requestData, $data);
        }
        
        exit;
    }

    /**
     * 将数组转换为 XML 字符串（用于 api_response_format = 'xml' 场景）
     * @param mixed $data 数组/标量数据
     * @param string $rootElement 根节点名
     * @return string XML 字符串
     */
    private static function arrayToXml($data, $rootElement = 'response') {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $xml .= '<' . $rootElement . '>' . PHP_EOL;
        $xml .= self::buildXmlChildren($data, 1);
        $xml .= '</' . $rootElement . '>' . PHP_EOL;
        return $xml;
    }

    /**
     * 递归构建 XML 子节点
     * @param mixed $data
     * @param int $indent 缩进层级
     * @return string
     */
    private static function buildXmlChildren($data, $indent = 1) {
        $pad = str_repeat('    ', $indent);
        $result = '';

        if (is_array($data)) {
            $isList = array_keys($data) === range(0, count($data) - 1);
            foreach ($data as $key => $value) {
                // 如果是数字索引数组，使用 'item' 作为标签
                $tag = $isList ? 'item' : preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)$key);
                if (is_array($value)) {
                    $result .= $pad . '<' . $tag . '>' . PHP_EOL;
                    $result .= self::buildXmlChildren($value, $indent + 1);
                    $result .= $pad . '</' . $tag . '>' . PHP_EOL;
                } elseif ($value === null) {
                    $result .= $pad . '<' . $tag . ' null="true"/>' . PHP_EOL;
                } else {
                    $result .= $pad . '<' . $tag . '>' . htmlspecialchars((string)$value, ENT_XML1, 'UTF-8') . '</' . $tag . '>' . PHP_EOL;
                }
            }
        } else {
            $result .= $pad . htmlspecialchars((string)$data, ENT_XML1, 'UTF-8') . PHP_EOL;
        }
        return $result;
    }

    /**
     * 获取当前API版本
     * @return string 版本号，如 'v1', 'v2'
     */
    public static function getApiVersion() {
        return isset($GLOBALS['api_version']) ? $GLOBALS['api_version'] : 'v1';
    }

    /**
     * 获取所有支持的路由（带版本信息）
     * @return array
     */
    public static function getRoutes() {
        return self::$routes;
    }
    
    /**
     * 获取路由文档
     * @return array
     */
    public static function getRouteDocs() {
        $docs = [];
        $processedRoutes = [];
        
        foreach (self::$routes as $routeConfig) {
            // 避免重复的路由文档
            $routeKey = $routeConfig['path'] . '|' . implode(',', $routeConfig['methods']);
            if (isset($processedRoutes[$routeKey])) {
                continue;
            }
            
            $docs[] = [
                'path' => $routeConfig['path'],
                'controller' => $routeConfig['controller'],
                'method' => $routeConfig['method'],
                'methods' => $routeConfig['methods'],
                'middleware' => $routeConfig['middleware']
            ];
            
            $processedRoutes[$routeKey] = true;
        }
        
        return $docs;
    }
}

// 预定义中间件
ApiRouter::middleware('auth', function() {
    if (!isset($_SESSION['user'])) {
        ApiRouter::sendError('请先登录', 401);
        return false;
    }
    return true;
});

ApiRouter::middleware('admin', function() {
    if (!isset($_SESSION['admin'])) {
        ApiRouter::sendError('需要管理员权限', 403);
        return false;
    }
    return true;
});

ApiRouter::middleware('cors', function() {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit;
    }
    return true;
});

ApiRouter::middleware('rate_limit', function() {
    // —— 检查限流开关：后台 security / api_rate_limit_enabled
    if (!Config::get('api_rate_limit_enabled', 0)) {
        return true;
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = 'api_rate_limit_' . $ip;

    // 从后台配置读取：请求次数上限 + 时间窗口（秒）
    $limit  = (int)Config::get('api_rate_limit_count', 100);
    $window = (int)Config::get('api_rate_limit_time', 60);

    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 1, 'time' => time()];
    } else {
        if (time() - $_SESSION[$key]['time'] > $window) {
            $_SESSION[$key] = ['count' => 1, 'time' => time()];
        } elseif ($_SESSION[$key]['count'] >= $limit) {
            ApiRouter::sendError('请求过于频繁，请稍后重试', 429);
            return false;
        } else {
            $_SESSION[$key]['count']++;
        }
    }
    
    return true;
});
