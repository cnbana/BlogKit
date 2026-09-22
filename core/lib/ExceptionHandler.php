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
 * 统一异常处理器
 * 负责捕获和处理应用程序中所有未捕获的异常
 */
class ExceptionHandler {
    
    /**
     * 处理异常
     * 
     * @param Throwable $e 异常对象（支持 Exception 和 Error）
     */
    public static function handle(Throwable $e) {
        // 记录错误日志
        self::logException($e);
        
        // 根据环境输出不同响应
        $debugMode = Config::get('debug.enabled', false);
        
        if ($debugMode) {
            // 开发环境：输出详细错误信息
            self::handleDebug($e);
        } else {
            // 生产环境：输出友好错误页面
            self::handleProduction($e);
        }
        
        exit;
    }
    
    /**
     * 记录异常日志
     * 
     * @param Throwable $e 异常对象
     */
    private static function logException(Throwable $e) {
        $logData = [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'code' => $e->getCode()
        ];
        
        if (class_exists('Log')) {
            Log::error($e->getMessage(), 'exception', $logData);
        } else {
            error_log('Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
    }
    
    /**
     * 安全地设置 header
     * 
     * @param string $header Header 内容
     * @param bool $replace 是否替换已存在的 header
     * @param int $code HTTP 状态码
     */
    private static function safeHeader(string $header, bool $replace = true, int $code = 0) {
        if (!headers_sent()) {
            if ($code > 0) {
                header($header, $replace, $code);
            } else {
                header($header, $replace);
            }
        }
    }
    
    /**
     * 安全地设置 HTTP 状态码
     * 
     * @param int $code HTTP 状态码
     */
    private static function safeResponseCode(int $code) {
        if (!headers_sent()) {
            http_response_code($code);
        }
    }
    
    /**
     * 安全地清空输出缓冲
     */
    private static function safeCleanBuffer() {
        while (ob_get_level() > 0) {
            if (!@ob_end_clean()) {
                break;
            }
        }
    }
    
    /**
     * 处理开发环境异常
     * 
     * @param Throwable $e 异常对象
     */
    private static function handleDebug(Throwable $e) {
        // 安全清空输出缓冲，避免之前的 HTML 输出影响
        self::safeCleanBuffer();
        
        // 安全设置 header
        self::safeHeader('Content-Type: application/json');
        self::safeResponseCode(500);
        
        $response = [
            'status' => 'error',
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'code' => $e->getCode(),
            'trace' => $e->getTrace()
        ];
        
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * 处理生产环境异常
     * 
     * @param Throwable $e 异常对象
     */
    private static function handleProduction(Throwable $e) {
        // 安全清空输出缓冲
        self::safeCleanBuffer();
        
        // 判断是否为 AJAX 请求
        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        
        if ($isAjax) {
            // AJAX 请求：返回 JSON 错误响应
            self::safeHeader('Content-Type: application/json');
            self::safeResponseCode(500);
            echo json_encode([
                'status' => 'error',
                'message' => '服务器内部错误，请稍后重试'
            ]);
        } else {
            // 普通请求：显示友好错误页面
            self::safeHeader('HTTP/1.0 500 Internal Server Error');
            $errorPage = defined('ADMIN_PATH') 
                ? (ADMIN_PATH . '/templates/errors/500.html') 
                : null;
            
            if ($errorPage && file_exists($errorPage)) {
                include $errorPage;
            } else {
                echo '<html><body><h1>500 Internal Server Error</h1><p>服务器内部错误，请稍后重试</p></body></html>';
            }
        }
    }
    
    /**
     * 处理 404 错误
     */
    public static function handle404() {
        // 安全清空输出缓冲
        self::safeCleanBuffer();
        
        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        
        if ($isAjax) {
            self::safeHeader('Content-Type: application/json');
            self::safeResponseCode(404);
            echo json_encode([
                'status' => 'error',
                'message' => '请求的资源不存在'
            ]);
        } else {
            self::safeHeader('HTTP/1.0 404 Not Found');
            $errorPage = defined('ADMIN_PATH') 
                ? (ADMIN_PATH . '/templates/errors/404.html') 
                : null;
            
            if ($errorPage && file_exists($errorPage)) {
                include $errorPage;
            } else {
                echo '<html><body><h1>404 Not Found</h1><p>请求的页面不存在</p></body></html>';
            }
        }
        
        exit;
    }
    
    /**
     * 处理 403 错误
     */
    public static function handle403() {
        // 安全清空输出缓冲
        self::safeCleanBuffer();
        
        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        
        if ($isAjax) {
            self::safeHeader('Content-Type: application/json');
            self::safeResponseCode(403);
            echo json_encode([
                'status' => 'error',
                'message' => '没有访问权限'
            ]);
        } else {
            self::safeHeader('HTTP/1.0 403 Forbidden');
            $errorPage = defined('ADMIN_PATH') 
                ? (ADMIN_PATH . '/templates/errors/403.html') 
                : null;
            
            if ($errorPage && file_exists($errorPage)) {
                include $errorPage;
            } else {
                echo '<html><body><h1>403 Forbidden</h1><p>您没有访问此页面的权限</p></body></html>';
            }
        }
        
        exit;
    }
    
    /**
     * 处理 400 错误
     * 
     * @param string $message 错误消息
     */
    public static function handle400($message = '请求参数错误') {
        // 安全清空输出缓冲
        self::safeCleanBuffer();
        
        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        
        if ($isAjax) {
            self::safeHeader('Content-Type: application/json');
            self::safeResponseCode(400);
            echo json_encode([
                'status' => 'error',
                'message' => $message
            ]);
        } else {
            self::safeHeader('HTTP/1.0 400 Bad Request');
            echo '<html><body><h1>400 Bad Request</h1><p>' . htmlspecialchars($message) . '</p></body></html>';
        }
        
        exit;
    }
    
    /**
     * 注册异常处理器
     */
    public static function register() {
        set_exception_handler(['ExceptionHandler', 'handle']);
        
        // 注册错误处理（将错误转换为异常）
        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            if (!(error_reporting() & $errno)) {
                return;
            }
            
            if (strpos($errstr, 'write failed: Bad file descriptor') !== false) {
                return;
            }
            
            if (strpos($errstr, 'Failed to write session data') !== false) {
                return;
            }
            
            // 如果已经有输出了，对于 NOTICE/WARNING 级别，直接记录日志而不抛异常
            if (headers_sent() && in_array($errno, [E_NOTICE, E_WARNING, E_DEPRECATED, E_STRICT])) {
                error_log("PHP Error [$errno]: $errstr in $errfile:$errline");
                return;
            }
            
            throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
        });
    }
}
