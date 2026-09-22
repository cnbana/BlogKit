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
 * 调试工具类，用于收集和管理调试数据
 */
class Debug {
    /**
     * 单例实例
     */
    private static $instance = null;
    
    /**
     * 调试数据数组
     */
    private $data = [
        'start_time' => 0,
        'end_time' => 0,
        'memory_usage' => 0,
        'peak_memory_usage' => 0,
        'request' => [],
        'server' => [],
        'session' => [],
        'errors' => [],
        'exceptions' => [],
        'custom_data' => []
    ];
    
    /**
     * 是否已初始化
     */
    private static $initialized = false;
    
    /**
     * 是否禁用调试面板输出
     */
    private static $disablePanel = false;
    
    /**
     * 构造函数，初始化调试数据
     */
    private function __construct() {
        $this->data['start_time'] = microtime(true);
        $this->data['memory_usage'] = memory_get_usage();
        $this->data['peak_memory_usage'] = memory_get_peak_usage();
        
        // 收集请求信息
        $this->collectRequestInfo();
        
        // 收集服务器信息
        $this->collectServerInfo();
        
        // 收集会话信息
        $this->collectSessionInfo();
    }
    
    /**
     * 获取单例实例
     * @return Debug
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 初始化调试工具
     */
    public static function init() {
        if (!self::$initialized) {
            // 注册错误处理
            set_error_handler([__CLASS__, 'handleError']);
            
            // 注册异常处理
            set_exception_handler([__CLASS__, 'handleException']);
            
            // 注册脚本结束处理
            register_shutdown_function([__CLASS__, 'shutdown']);
            
            self::$initialized = true;
        }
        return self::getInstance();
    }
    
    /**
     * 禁用调试面板输出
     */
    public static function disablePanel() {
        self::$disablePanel = true;
    }
    
    /**
     * 收集请求信息
     */
    private function collectRequestInfo() {
        $this->data['request'] = [
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'protocol' => $_SERVER['SERVER_PROTOCOL'] ?? '',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'get' => $_GET,
            'post' => self::filterSensitiveData($_POST),
            'files' => $_FILES,
            'cookies' => self::filterSensitiveData($_COOKIE)
        ];
    }
    
    /**
     * 收集服务器信息
     */
    private function collectServerInfo() {
        $this->data['server'] = [
            'php_version' => PHP_VERSION,
            'php_sapi' => PHP_SAPI,
            'os' => PHP_OS,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? '',
            'document_root' => isset($_SERVER['DOCUMENT_ROOT']) ? '[DOCUMENT_ROOT]' : '',
            'timezone' => date_default_timezone_get(),
            'max_execution_time' => ini_get('max_execution_time'),
            'memory_limit' => ini_get('memory_limit')
        ];
    }
    
    /**
     * 收集会话信息
     */
    private function collectSessionInfo() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            // 过滤 session 中的敏感字段（user.password_hash 等）
            $session = $_SESSION;
            if (isset($session['user']) && is_array($session['user'])) {
                $session['user'] = array_diff_key(
                    $session['user'],
                    array_flip(['password', 'password_hash', 'passwd', 'token', 'api_token'])
                );
            }
            if (isset($session['admin']) && is_array($session['admin'])) {
                $session['admin'] = array_diff_key(
                    $session['admin'],
                    array_flip(['password', 'password_hash', 'passwd', 'token', 'api_token'])
                );
            }
            $this->data['session'] = $session;
        } else {
            $this->data['session'] = [];
        }
    }
    
    /**
     * 错误处理函数
     * @param int $errno 错误编号
     * @param string $errstr 错误信息
     * @param string $errfile 错误文件
     * @param int $errline 错误行号
     * @return bool
     */
    public static function handleError($errno, $errstr, $errfile, $errline) {
        // 根据错误级别转换为可读名称
        $errorLevels = [
            E_ERROR => 'ERROR',
            E_WARNING => 'WARNING',
            E_PARSE => 'PARSE',
            E_NOTICE => 'NOTICE',
            E_CORE_ERROR => 'CORE_ERROR',
            E_CORE_WARNING => 'CORE_WARNING',
            E_COMPILE_ERROR => 'COMPILE_ERROR',
            E_COMPILE_WARNING => 'COMPILE_WARNING',
            E_USER_ERROR => 'USER_ERROR',
            E_USER_WARNING => 'USER_WARNING',
            E_USER_NOTICE => 'USER_NOTICE',
            E_STRICT => 'STRICT',
            E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
            E_DEPRECATED => 'DEPRECATED',
            E_USER_DEPRECATED => 'USER_DEPRECATED'
        ];
        
        $level = isset($errorLevels[$errno]) ? $errorLevels[$errno] : 'UNKNOWN';
        
        // 收集错误信息
        $debug = self::getInstance();
        $debug->data['errors'][] = [
            'level' => $level,
            'message' => $errstr,
            'file' => $errfile,
            'line' => $errline,
            'timestamp' => microtime(true)
        ];
        

        
        // 如果是致命错误，继续默认处理
        return !(error_reporting() & $errno);
    }
    
    /**
     * 异常处理函数（兼容 \Throwable 和 \Exception）
     * @param \Throwable $exception 异常对象
     */
    public static function handleException($exception) {
        // 收集异常信息
        $debug = self::getInstance();
        $debug->data['exceptions'][] = [
            'type' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTrace(),
            'timestamp' => microtime(true)
        ];
        

        
        // 显示友好的错误页面
        $debug->displayErrorPage($exception);
    }
    
    /**
     * 脚本结束处理函数
     */
    public static function shutdown() {
        $debug = self::getInstance();
        $debug->data['end_time'] = microtime(true);
        $debug->data['memory_usage'] = memory_get_usage();
        $debug->data['peak_memory_usage'] = memory_get_peak_usage();
        
        // 检查是否有致命错误
        $error = error_get_last();
        if ($error !== null) {
            $debug::handleError($error['type'], $error['message'], $error['file'], $error['line']);
        }
        
        // 输出调试面板
        $debug->renderDebugPanel();
    }
    
    /**
     * 显示错误页面（兼容 \Throwable）
     * @param \Throwable $exception 异常对象
     */
    public function displayErrorPage($exception) {
        // 检查是否启用了调试模式
        $debugEnabled = Config::get('debug.enabled', false);
        $errorDisplay = Config::get('debug.error_display', $debugEnabled);
        
        // 检测是否为 API 请求
        $isApiRequest = defined('IS_API_REQUEST') && IS_API_REQUEST;
        
        // 清空输出缓冲区
        if (ob_get_level()) {
            ob_clean();
        }
        
        // 根据调试模式选择错误页面
        if ($errorDisplay) {
            // 开发模式，显示详细错误信息
            if ($isApiRequest) {
                $this->renderApiError($exception, true);
            } else {
                $this->renderDebugErrorPage($exception);
            }
        } else {
            // 生产模式，显示友好错误页面（脱敏处理）
            if ($isApiRequest) {
                $this->renderApiError($exception, false);
            } else {
                $this->renderFriendlyErrorPage();
            }
        }
        
        exit;
    }
    
    /**
     * 渲染 API 错误响应
     * @param \Throwable $exception 异常对象
     * @param bool $debug 是否调试模式
     */
    private function renderApiError($exception, $debug = false) {
        // 根据异常类型确定 HTTP 状态码
        $httpCode = HTTP_INTERNAL_ERROR;
        if (method_exists($exception, 'getHttpCode')) {
            $httpCode = $exception->getHttpCode();
        }
        
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        
        $response = [
            'code' => $exception->getCode() ?: $httpCode,
            'message' => $debug ? $exception->getMessage() : self::sanitizeErrorMessage($exception->getMessage()),
        ];
        
        if ($debug) {
            $response['debug'] = [
                'type' => get_class($exception),
                'file' => self::sanitizeFilePath($exception->getFile()),
                'line' => $exception->getLine(),
            ];
        }
        
        if (method_exists($exception, 'getData') && !empty($exception->getData())) {
            $response['data'] = $exception->getData();
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * 脱敏错误消息（生产环境）
     * 移除文件路径、数据库连接信息等敏感内容
     * 
     * @param string $message 原始错误消息
     * @return string 脱敏后的消息
     */
    public static function sanitizeErrorMessage($message) {
        // 如果消息包含文件路径，替换为通用提示
        if (preg_match('#[/\\\\](www|htdocs|public_html|var[/\\\\]www)#i', $message) 
            || preg_match('#\.php(:\d+)?#i', $message)) {
            return '服务器繁忙，请稍后重试';
        }
        
        // 如果消息包含数据库连接信息
        if (preg_match('#(mysql|database|host|connection|SQLSTATE)#i', $message)) {
            return '服务器繁忙，请稍后重试';
        }
        
        // 通用脱敏
        $sanitized = $message;
        $sanitized = preg_replace('#[/\\\\][a-zA-Z0-9_\-/\\\\]+\.php(:\d+)?#', '[内部文件]', $sanitized);
        $sanitized = preg_replace('#\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b#', '[IP地址]', $sanitized);
        
        return $sanitized;
    }
    
    /**
     * 脱敏文件路径
     * @param string $path 文件路径
     * @return string 脱敏后的路径
     */
    public static function sanitizeFilePath($path) {
        if (defined('ROOT_PATH')) {
            $path = str_replace(ROOT_PATH, '[ROOT]', $path);
        }
        return $path;
    }
    
    /**
     * 过滤敏感字段（密码、token、secret等）
     * 用于日志记录前处理
     * 
     * @param array $data 原始数据
     * @return array 过滤后的数据
     */
    public static function filterSensitiveData(array $data): array {
        static $sensitiveKeys = [
            'password', 'passwd', 'pwd', 'pass',
            'token', 'csrf_token', 'api_key', 'api_secret',
            'secret', 'private_key', 'authorization',
            'access_token', 'refresh_token',
        ];
        
        $filtered = [];
        foreach ($data as $key => $value) {
            $lowerKey = strtolower($key);
            if (in_array($lowerKey, $sensitiveKeys, true)) {
                $filtered[$key] = '[FILTERED]';
            } elseif (is_array($value)) {
                $filtered[$key] = self::filterSensitiveData($value);
            } else {
                $filtered[$key] = $value;
            }
        }
        return $filtered;
    }
    
    /**
     * 渲染调试错误页面
     * @param Exception $exception 异常对象
     */
    private function renderDebugErrorPage($exception) {
        $trace = $exception->getTraceAsString();
        
        echo '<!DOCTYPE html>';
        echo '<html lang="zh-CN">';
        echo '<head>';
        echo '<meta charset="UTF-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        echo '<title>错误信息 - BlogKit</title>';
        echo '<style>';
        echo 'body { font-family: "Courier New", monospace; background-color: #f5f5f5; margin: 0; padding: 20px; color: #333; }';
        echo '.error-container { max-width: 1200px; margin: 0 auto; background-color: #fff; border: 1px solid #ddd; border-radius: 5px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }';
        echo '.error-header { background-color: #dc3545; color: white; padding: 20px; border-radius: 5px 5px 0 0; }';
        echo '.error-content { padding: 20px; }';
        echo '.error-title { font-size: 24px; margin: 0 0 10px 0; }';
        echo '.error-info { margin-bottom: 20px; }';
        echo '.error-info p { margin: 5px 0; }';
        echo '.error-file { background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; }';
        echo '.error-file h3 { margin: 0 0 10px 0; color: #495057; }';
        echo '.error-file pre { margin: 0; white-space: pre-wrap; word-wrap: break-word; }';
        echo '.error-trace { background-color: #f8f9fa; padding: 15px; border-radius: 5px; }';
        echo '.error-trace h3 { margin: 0 0 10px 0; color: #495057; }';
        echo '.error-trace pre { margin: 0; white-space: pre-wrap; word-wrap: break-word; font-size: 12px; }';
        echo '</style>';
        echo '</head>';
        echo '<body>';
        echo '<div class="error-container">';
        echo '<div class="error-header">';
        echo '<h1 class="error-title">发生错误</h1>';
        echo '</div>';
        echo '<div class="error-content">';
        echo '<div class="error-info">';
        echo '<p><strong>类型:</strong> ' . get_class($exception) . '</p>';
        echo '<p><strong>消息:</strong> ' . htmlspecialchars($exception->getMessage()) . '</p>';
        echo '<p><strong>文件:</strong> ' . $exception->getFile() . '</p>';
        echo '<p><strong>行号:</strong> ' . $exception->getLine() . '</p>';
        echo '<p><strong>时间:</strong> ' . date('Y-m-d H:i:s') . '</p>';
        echo '</div>';
        echo '<div class="error-trace">';
        echo '<h3>堆栈跟踪:</h3>';
        echo '<pre>' . htmlspecialchars($trace) . '</pre>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</body>';
        echo '</html>';
    }
    
    /**
     * 渲染友好的错误页面
     */
    private function renderFriendlyErrorPage() {
        echo '<!DOCTYPE html>';
        echo '<html lang="zh-CN">';
        echo '<head>';
        echo '<meta charset="UTF-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        echo '<title>出错了 - BlogKit</title>';
        echo '<style>';
        echo 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background-color: #f5f5f5; margin: 0; padding: 0; color: #333; text-align: center; }';
        echo '.error-container { max-width: 600px; margin: 100px auto; padding: 40px; background-color: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }';
        echo '.error-icon { font-size: 64px; margin-bottom: 20px; }';
        echo '.error-title { font-size: 24px; margin-bottom: 15px; color: #212529; }';
        echo '.error-message { font-size: 16px; margin-bottom: 30px; color: #6c757d; }';
        echo '.btn { display: inline-block; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px; transition: background-color 0.3s; }';
        echo '.btn:hover { background-color: #0056b3; }';
        echo '</style>';
        echo '</head>';
        echo '<body>';
        echo '<div class="error-container">';
        echo '<div class="error-icon">⚠️</div>';
        echo '<h1 class="error-title">抱歉，服务器遇到了问题</h1>';
        echo '<p class="error-message">我们的技术团队已经收到通知，正在努力修复这个问题。</p>';
        echo '<a href="' . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/') . '" class="btn">返回上一页</a>';
        echo '</div>';
        echo '</body>';
        echo '</html>';
    }
    
    /**
     * 渲染调试面板
     */
    public function renderDebugPanel() {
        // 检查是否禁用了调试面板输出
        if (self::$disablePanel) {
            return;
        }
        
        // 检查是否启用了调试面板
        if (!Config::get('debug.panel_enabled', false)) {
            return;
        }
        
        // 计算执行时间和内存使用
        $executionTime = round((microtime(true) - $this->data['start_time']) * 1000, 2);
        $memoryUsage = round($this->data['memory_usage'] / 1024 / 1024, 2);
        $peakMemoryUsage = round($this->data['peak_memory_usage'] / 1024 / 1024, 2);
        
        // 获取SQL查询日志
        $queryLog = Database::getQueryLog();
        $totalQueries = count($queryLog);
        $totalQueryTime = array_sum(array_column($queryLog, 'execution_time'));
        
        // 渲染调试面板HTML
        include CORE_PATH . '/template/debug_panel.html';
    }
    
    /**
     * 添加自定义调试数据
     * @param string $key 数据键名
     * @param mixed $value 数据值
     */
    public function addData($key, $value) {
        $this->data['custom_data'][$key] = $value;
    }
    
    /**
     * 获取调试数据
     * @param string|null $key 数据键名，为null则返回所有数据
     * @return mixed
     */
    public function getData($key = null) {
        if ($key === null) {
            return $this->data;
        }
        return isset($this->data[$key]) ? $this->data[$key] : null;
    }
    
    /**
     * 获取执行时间（毫秒）
     * @return float
     */
    public function getExecutionTime() {
        return round((microtime(true) - $this->data['start_time']) * 1000, 2);
    }
    
    /**
     * 获取内存使用情况（MB）
     * @return float
     */
    public function getMemoryUsage() {
        return round(memory_get_usage() / 1024 / 1024, 2);
    }
    
    /**
     * 获取峰值内存使用情况（MB）
     * @return float
     */
    public function getPeakMemoryUsage() {
        return round(memory_get_peak_usage() / 1024 / 1024, 2);
    }
}
