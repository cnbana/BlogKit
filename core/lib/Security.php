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
 * 安全工具类
 * 提供XSS过滤、CSRF防护、输入验证等安全功能
 */
class Security {
    /**
     * 获取安全配置
     * @param string $key 配置键
     * @param mixed $default 默认值
     * @return mixed 配置值
     */
    public static function getSecurityConfig($key, $default = null) {
        // 确保Config类已加载
        if (!class_exists('Config')) {
            require_once CORE_PATH . '/lib/Config.php';
            Config::init();
        }
        
        return Config::get('security_' . $key, $default);
    }
    
    /**
     * 生成CSRF令牌
     * @return string CSRF令牌
     */
    public static function generateCsrfToken() {
        // 确保会话已启动（不依赖外部调用顺序）
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // 令牌在有效期内复用：同一页面渲染多个表单时（如主题/插件管理页的
        // "设为默认"+"卸载"等多个 POST 表单），若每次调用都重新生成并覆盖
        // 会话令牌，会导致先渲染的表单令牌失效，提交时报"安全校验失败"。
        $lifetime = defined('CSRF_TOKEN_LIFETIME') ? CSRF_TOKEN_LIFETIME : 7200;
        if (isset($_SESSION['csrf_token'], $_SESSION['csrf_token_time'])
            && (time() - $_SESSION['csrf_token_time']) < $lifetime) {
            return $_SESSION['csrf_token'];
        }

        // 生成随机令牌（优先使用PHP的CSPRNG，保证安全性）
        if (function_exists('random_bytes')) {
            $token = bin2hex(random_bytes(32));
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            $token = bin2hex(openssl_random_pseudo_bytes(32));
        } else {
            // 兜底：使用较安全的伪随机生成方式
            $token = bin2hex(uniqid(mt_rand(), true));
        }

        // 存储到会话中
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_token_time'] = time();

        return $token;
    }
    
    /**
     * 验证CSRF令牌
     * @param string $token 要验证的令牌
     * @param int $expiration 令牌过期时间（秒），默认使用 CSRF_TOKEN_LIFETIME 常量
     * @return bool 验证是否通过
     */
    public static function validateCsrfToken($token, $expiration = null) {
        // —— 检查 CSRF 防护开关（后台 security_csrf_protection 配置项）——
        // 若管理员在后台将"CSRF防护"设为关闭，则跳过令牌验证，直接返回 true。
        // 注意：默认值为 1（开启），避免数据库中缺配置时系统误判为关闭。
        if (!self::getSecurityConfig('csrf_protection', 1)) {
            return true;
        }

        // 使用默认过期时间（与系统常量对齐）
        if ($expiration === null) {
            $expiration = defined('CSRF_TOKEN_LIFETIME') ? CSRF_TOKEN_LIFETIME : 7200;
        }

        // 传入token不能为空（类型和值的双重检查）
        if (empty($token) || !is_string($token)) {
            return false;
        }

        // 确保session已启动
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // 检查会话中是否存在token（客户端请求可能来自已过期的session）
        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
            return false;
        }

        // 使用 hash_equals 进行安全比较（防止时序攻击）
        if (!hash_equals($_SESSION['csrf_token'], $token)) {
            return false;
        }

        // 检查令牌是否过期
        if (time() - $_SESSION['csrf_token_time'] > $expiration) {
            return false;
        }

        return true;
    }
    
    /**
     * 获取CSRF令牌输入字段
     * @return string HTML输入字段
     */
    public static function getCsrfField() {
        // 当后台关闭 CSRF 防护时，返回空字符串，不在模板中渲染隐藏字段
        if (!self::getSecurityConfig('csrf_protection', 1)) {
            return '';
        }
        $token = self::generateCsrfToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '" />';
    }
    
    /**
     * 过滤XSS攻击
     * @param string $input 输入字符串
     * @param int $flags 过滤标志
     * @param string $encoding 编码方式
     * @return string 过滤后的字符串
     */
    public static function filterXss($input, $flags = ENT_QUOTES | ENT_SUBSTITUTE, $encoding = 'UTF-8') {
        // 检查XSS防护是否启用
        if (!self::getSecurityConfig('xss_protection', 1)) {
            return $input;
        }
        
        if (is_string($input)) {
            return htmlspecialchars($input, $flags, $encoding);
        } elseif (is_array($input)) {
            return array_map([__CLASS__, 'filterXss'], $input);
        } elseif (is_object($input)) {
            foreach ($input as $key => $value) {
                $input->$key = self::filterXss($value, $flags, $encoding);
            }
            return $input;
        }
        return $input;
    }
    
    /**
     * 过滤SQL注入 - 已弃用
     * @deprecated 此方法不安全，请使用PDO预处理语句
     * @param string $input 输入字符串
     * @return string 原始输入
     */
    public static function filterSql($input) {
        trigger_error('Security::filterSql() is deprecated. Use PDO prepared statements instead.', E_USER_DEPRECATED);
        return $input;
    }
    
    /**
     * 验证邮箱格式
     * @param string $email 邮箱地址
     * @return bool 是否有效
     */
    public static function isValidEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * 验证URL格式
     * @param string $url URL地址
     * @return bool 是否有效
     */
    public static function isValidUrl($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * 验证IP地址格式
     * @param string $ip IP地址
     * @return bool 是否有效
     */
    public static function isValidIp($ip) {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }
    
    /**
     * 验证整数
     * @param mixed $value 要验证的值
     * @param int $min 最小值
     * @param int $max 最大值
     * @return bool 是否有效
     */
    public static function isValidInt($value, $min = null, $max = null) {
        if (!filter_var($value, FILTER_VALIDATE_INT)) {
            return false;
        }
        
        $intValue = (int)$value;
        
        if ($min !== null && $intValue < $min) {
            return false;
        }
        
        if ($max !== null && $intValue > $max) {
            return false;
        }
        
        return true;
    }
    
    /**
     * 验证浮点数
     * @param mixed $value 要验证的值
     * @param float $min 最小值
     * @param float $max 最大值
     * @return bool 是否有效
     */
    public static function isValidFloat($value, $min = null, $max = null) {
        if (!filter_var($value, FILTER_VALIDATE_FLOAT)) {
            return false;
        }
        
        $floatValue = (float)$value;
        
        if ($min !== null && $floatValue < $min) {
            return false;
        }
        
        if ($max !== null && $floatValue > $max) {
            return false;
        }
        
        return true;
    }
    
    /**
     * 验证字符串长度
     * @param string $value 要验证的字符串
     * @param int $min 最小长度
     * @param int $max 最大长度
     * @return bool 是否有效
     */
    public static function isValidLength($value, $min = null, $max = null) {
        if (!is_string($value)) {
            return false;
        }
        
        $length = strlen($value);
        
        if ($min !== null && $length < $min) {
            return false;
        }
        
        if ($max !== null && $length > $max) {
            return false;
        }
        
        return true;
    }
    
    /**
     * 清理用户输入
     * @param mixed $input 输入数据
     * @return mixed 清理后的数据
     */
    public static function sanitizeInput($input) {
        if (is_string($input)) {
            // 移除多余的空格
            $input = trim($input);
            // 过滤XSS
            $input = self::filterXss($input);
        } elseif (is_array($input)) {
            foreach ($input as $key => $value) {
                $input[$key] = self::sanitizeInput($value);
            }
        }
        
        return $input;
    }
    
    /**
     * 获取客户端IP地址
     * @return string 客户端IP地址
     */
    public static function getClientIp() {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) && !empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // 处理多个IP地址的情况
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                
                if (self::isValidIp($ip)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * 检查是否为AJAX请求
     * @return bool 是否为AJAX请求
     */
    public static function isAjaxRequest() {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    }
    
    /**
     * 检查是否为POST请求
     * @return bool 是否为POST请求
     */
    public static function isPostRequest() {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }
    
    /**
     * 检查是否为GET请求
     * @return bool 是否为GET请求
     */
    public static function isGetRequest() {
        return $_SERVER['REQUEST_METHOD'] === 'GET';
    }
    
    /**
     * 重定向到安全的URL
     * @param string $url 重定向URL
     * @param int $statusCode HTTP状态码
     */
    public static function redirect($url, $statusCode = 302) {
        // 验证URL
        if (!filter_var($url, FILTER_VALIDATE_URL) && strpos($url, '/') === 0) {
            // 相对路径，添加基础URL
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $url = $protocol . '://' . $host . $url;
        }
        
        // 发送重定向头
        header('Location: ' . $url, true, $statusCode);
        exit;
    }
    
    /**
     * 生成安全的随机字符串
     * @param int $length 字符串长度
     * @return string 随机字符串
     */
    public static function generateRandomString($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * 验证密码强度
     * @param string $password 密码
     * @return array [isValid, message]
     */
    public static function validatePasswordStrength($password) {
        $minLength = 6;
        
        if (strlen($password) < $minLength) {
            return [false, '密码长度不能少于' . $minLength . '个字符'];
        }
        
        // 检查是否包含数字
        if (!preg_match('/[0-9]/', $password)) {
            return [false, '密码必须包含至少一个数字'];
        }
        
        // 检查是否包含字母
        if (!preg_match('/[a-zA-Z]/', $password)) {
            return [false, '密码必须包含至少一个字母'];
        }
        
        return [true, '密码强度符合要求'];
    }
    
    /**
     * 哈希密码
     * @param string $password 原始密码
     * @return string 哈希后的密码
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    /**
     * 验证密码
     * @param string $password 原始密码
     * @param string $hash 哈希后的密码
     * @return bool 验证是否通过
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
}
