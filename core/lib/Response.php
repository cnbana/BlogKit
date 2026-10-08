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
 * 统一响应类
 * 提供统一的响应格式和方法
 */
class Response {
    
    /**
     * 成功响应（JSON格式）
     * 
     * @param string $message 提示消息
     * @param array $data 返回数据
     * @param int $code HTTP状态码
     */
    public static function success($message = '', $data = [], $code = 200) {
        header('Content-Type: application/json');
        http_response_code($code);
        
        $response = [
            'status' => 'success',
            'message' => $message,
            'data' => $data,
            'timestamp' => time()
        ];
        
        echo json_encode($response);
        exit;
    }
    
    /**
     * 错误响应（JSON格式）
     * 
     * @param string $message 错误消息
     * @param int $code HTTP状态码
     * @param array $data 额外数据
     */
    public static function error($message = '', $code = 400, $data = []) {
        header('Content-Type: application/json');
        http_response_code($code);
        
        $response = [
            'status' => 'error',
            'message' => $message,
            'code' => $code,
            'data' => $data,
            'timestamp' => time()
        ];
        
        echo json_encode($response);
        exit;
    }
    
    /**
     * 重定向响应
     * 
     * @param string $url 目标URL
     * @param string $message 提示消息（可选）
     * @param int $code HTTP状态码
     */
    public static function redirect($url, $message = '', $code = 302) {
        // 如果有消息，存储到Session中
        if ($message) {
            if (strpos($message, '错误') !== false || strpos($message, '失败') !== false) {
                $_SESSION['error_message'] = $message;
            } else {
                $_SESSION['success_message'] = $message;
            }
        }
        
        header('Location: ' . $url, true, $code);
        exit;
    }
    
    /**
     * 渲染视图
     * 
     * @param string $template 模板路径
     * @param array $data 模板变量
     */
    public static function view($template, $data = []) {
        extract($data);
        include $template;
        exit;
    }
    
    /**
     * 渲染JSONP响应
     * 
     * @param string $callback 回调函数名
     * @param mixed $data 返回数据
     */
    public static function jsonp($callback, $data) {
        header('Content-Type: application/javascript');
        
        // 安全过滤回调函数名
        $callback = preg_replace('/[^a-zA-Z0-9_\[\]]/', '', $callback);
        
        echo "{$callback}(" . json_encode($data) . ");";
        exit;
    }
    
    /**
     * 输出文件下载
     * 
     * @param string $filePath 文件路径
     * @param string $fileName 下载文件名（可选）
     */
    public static function download($filePath, $fileName = null) {
        if (!file_exists($filePath)) {
            self::error('文件不存在', 404);
        }
        
        if ($fileName === null) {
            $fileName = basename($filePath);
        }
        
        // 获取文件MIME类型（优先 fileinfo 扩展；未安装时按扩展名兜底，
        // 保证 BlogKit 在未安装任何可选扩展的环境下仍可下载文件——零依赖设计要求）
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($filePath);
        } else {
            $mimeType = self::mimeByExtension($filePath);
        }
        
        // 设置响应头
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . urlencode($fileName) . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        
        // 输出文件内容
        readfile($filePath);
        exit;
    }

    /**
     * 按文件扩展名推断 MIME 类型（fileinfo 扩展缺失时的兜底，覆盖站内常见类型）
     *
     * @param string $filePath 文件路径
     * @return string MIME 类型（无法识别时返回通用二进制流，浏览器会触发下载而非内联）
     */
    private static function mimeByExtension($filePath) {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $map = [
            // 文本/代码
            'txt'  => 'text/plain', 'md' => 'text/markdown', 'csv' => 'text/csv',
            'html' => 'text/html', 'htm' => 'text/html', 'css' => 'text/css',
            'js'   => 'application/javascript', 'json' => 'application/json', 'xml' => 'application/xml',
            // 文档
            'pdf'  => 'application/pdf',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls'  => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt'  => 'application/vnd.ms-powerpoint',
            'zip'  => 'application/zip', 'gz' => 'application/gzip', 'tar' => 'application/x-tar', 'rar' => 'application/vnd.rar',
            // 图片
            'png'  => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif', 'webp' => 'image/webp', 'svg' => 'image/svg+xml',
            'bmp'  => 'image/bmp', 'ico' => 'image/x-icon',
            // 音视频
            'mp3'  => 'audio/mpeg', 'wav' => 'audio/wav',
            'mp4'  => 'video/mp4', 'webm' => 'video/webm',
            // 字体
            'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf',
        ];
        return isset($map[$ext]) ? $map[$ext] : 'application/octet-stream';
    }
    
    /**
     * 输出纯文本响应
     *
     * @param string $content 文本内容
     * @param int $code HTTP状态码
     */
    public static function text($content, $code = 200) {
        header('Content-Type: text/plain');
        http_response_code($code);
        echo $content;
        exit;
    }
    
    /**
     * 输出HTML响应
     * 
     * @param string $content HTML内容
     * @param int $code HTTP状态码
     */
    public static function html($content, $code = 200) {
        header('Content-Type: text/html');
        http_response_code($code);
        echo $content;
        exit;
    }
    
    /**
     * 设置响应头
     * 
     * @param string $key 头名称
     * @param string $value 头值
     */
    public static function header($key, $value) {
        header("{$key}: {$value}");
    }
    
    /**
     * 设置跨域响应头
     * 
     * @param string $origin 允许的源（可选，默认为*）
     */
    public static function allowCors($origin = '*') {
        header("Access-Control-Allow-Origin: {$origin}");
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Allow-Credentials: true');
    }
    
    /**
     * 处理OPTIONS请求（CORS预检请求）
     */
    public static function handleOptions() {
        self::allowCors();
        http_response_code(200);
        exit;
    }
    
    /**
     * 设置缓存控制头
     * 
     * @param int $seconds 缓存秒数
     */
    public static function cache($seconds) {
        header("Cache-Control: public, max-age={$seconds}");
        header("Expires: " . gmdate('D, d M Y H:i:s', time() + $seconds) . ' GMT');
    }
    
    /**
     * 禁止缓存
     */
    public static function noCache() {
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
    
    /**
     * 分页响应
     * 
     * @param array $data 数据列表
     * @param int $total 总条数
     * @param int $page 当前页
     * @param int $limit 每页数量
     * @param string $message 提示消息
     */
    public static function paginate($data, $total, $page, $limit, $message = '') {
        $totalPages = ceil($total / $limit);
        
        $response = [
            'status' => 'success',
            'message' => $message,
            'data' => $data,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1
            ],
            'timestamp' => time()
        ];
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}