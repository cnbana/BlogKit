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
 * BlogKit 变量和标签系统扩展
 * 提供额外的模板变量和标签
 * 
 * 使用方法：
 * 1. 在模板中直接使用扩展变量：{user_ip}, {user_agent}, {server_time}等
 * 2. 使用扩展标签：{date format="Y-m-d"}, {math expression="1+1"}等
 */

class TemplateExtension {
    
    /**
     * 注册所有扩展变量和标签
     * @param Template $template 模板对象
     */
    public static function registerAll($template) {
        self::registerVariables($template);
        self::registerTags($template);
        self::registerFilters($template);
        self::registerFunctions($template);
    }
    
    /**
     * 注册扩展变量
     * @param Template $template 模板对象
     */
    private static function registerVariables($template) {
        // 用户相关变量
        $template->assign('user_ip', self::getUserIP());
        $template->assign('user_agent', isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '');
        $template->assign('user_browser', self::getUserBrowser());
        $template->assign('user_os', self::getUserOS());
        $template->assign('user_device', self::getUserDevice());
        $template->assign('is_mobile', self::isMobile());
        $template->assign('is_tablet', self::isTablet());
        $template->assign('is_desktop', self::isDesktop());
        
        // 服务器相关变量
        $template->assign('server_time', time());
        $template->assign('server_date', date('Y-m-d'));
        $template->assign('server_datetime', date('Y-m-d H:i:s'));
        $template->assign('server_timezone', date_default_timezone_get());
        $template->assign('server_software', isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : '');
        $template->assign('server_name', isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '');
        $template->assign('php_version', PHP_VERSION);
        
        // 请求相关变量
        $template->assign('request_uri', isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '');
        $template->assign('request_method', isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '');
        $template->assign('request_protocol', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
        $template->assign('is_https', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
        $template->assign('is_ajax', self::isAjax());
        $template->assign('is_post', isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST');
        $template->assign('is_get', isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'GET');
        
        // 页面相关变量
        $template->assign('page_load_time', microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']);
        $template->assign('memory_usage', memory_get_usage(true));
        $template->assign('memory_usage_formatted', self::formatBytes(memory_get_usage(true)));
        $template->assign('memory_peak', memory_get_peak_usage(true));
        $template->assign('memory_peak_formatted', self::formatBytes(memory_get_peak_usage(true)));
        
        // 环境相关变量
        $template->assign('is_development', self::isDevelopment());
        $template->assign('is_production', self::isProduction());
        $template->assign('is_testing', self::isTesting());
        
        // 系统相关变量（版本号从 version.php 唯一来源读取）
        $version = require CORE_PATH . '/config/version.php';
        $template->assign('system_name', $version['name']);
        $template->assign('system_version', $version['version']);
        $template->assign('system_url', Config::get('site.url', ''));
        
        // 统计相关变量
        $template->assign('online_users', self::getOnlineUsers());
        $template->assign('total_visits', self::getTotalVisits());
        $template->assign('today_visits', self::getTodayVisits());
    }
    
    /**
     * 注册扩展标签
     * @param Template $template 模板对象
     */
    private static function registerTags($template) {
        // 日期时间标签
        $template->registerFunction('date', function($format = 'Y-m-d H:i:s', $timestamp = null) {
            if ($timestamp === null) {
                $timestamp = time();
            }
            return date($format, $timestamp);
        });
        
        // 时间标签
        $template->registerFunction('time', function($format = 'H:i:s', $timestamp = null) {
            if ($timestamp === null) {
                $timestamp = time();
            }
            return date($format, $timestamp);
        });
        
        // 相对时间标签
        $template->registerFunction('time_ago', function($timestamp) {
            return self::timeAgo($timestamp);
        });
        
        // 数学计算标签
        $template->registerFunction('math', function($expression) {
            return self::evaluateMath($expression);
        });
        
        // 随机数标签
        $template->registerFunction('random', function($min = 0, $max = 100) {
            return mt_rand($min, $max);
        });
        
        // 字符串截取标签
        $template->registerFunction('truncate', function($text, $length = 100, $suffix = '...') {
            return mb_strlen($text) > $length ? mb_substr($text, 0, $length) . $suffix : $text;
        });
        
        // 字符串截取（单词）
        $template->registerFunction('truncate_words', function($text, $words = 10, $suffix = '...') {
            $wordArray = explode(' ', $text, $words + 1);
            if (count($wordArray) > $words) {
                array_pop($wordArray);
                return implode(' ', $wordArray) . $suffix;
            }
            return $text;
        });
        
        // 格式化数字标签
        $template->registerFunction('number_format', function($number, $decimals = 0) {
            return number_format($number, $decimals);
        });

        // 中文风格数字格式化（千/万/亿）
        $template->registerFunction('cn_number', function($params) use ($template) {
            $number = 0;
            $decimals = 1;
            if (is_array($params)) {
                // 命名参数形式：{func:cn_number value="12345" decimals="1"}
                if (isset($params['value'])) {
                    $number = $params['value'];
                } elseif (isset($params['number'])) {
                    $number = $params['number'];
                } elseif (isset($params['count'])) {
                    $number = $params['count'];
                }
                if (isset($params['decimals'])) {
                    $decimals = (int)$params['decimals'];
                }
            } else {
                $number = $params;
            }

            // 支持从模板变量中取值（例如 value="{following_count}"）
            if (is_string($number) && preg_match('/\{([a-zA-Z0-9_.]+)\}/', $number, $varMatches)) {
                $varValue = $template->getDataValue($varMatches[1]);
                if ($varValue !== null) {
                    $number = $varValue;
                }
            }

            return self::formatCnNumber($number, $decimals);
        });

        // 格式化文件大小标签
        $template->registerFunction('file_size', function($bytes) {
            return self::formatBytes($bytes);
        });
        
        // 转义HTML标签
        $template->registerFunction('escape', function($text) {
            return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        });
        
        // 去除HTML标签
        $template->registerFunction('strip_tags', function($text, $allowable_tags = '') {
            return strip_tags($text, $allowable_tags);
        });
        
        // 生成随机字符串标签
        $template->registerFunction('random_string', function($length = 10) {
            return self::generateRandomString($length);
        });
        
        // 生成UUID标签
        $template->registerFunction('uuid', function() {
            return self::generateUUID();
        });
        
        // 插件钩子标签
        $template->registerFunction('plugin_hook', function($params) {
            $name = isset($params['name']) ? $params['name'] : '';
            if (class_exists('Hook') && $name) {
                return Hook::trigger($name);
            }
            return '';
        });
        
        // MD5哈希标签
        $template->registerFunction('md5', function($text) {
            return md5($text);
        });
        
        // SHA1哈希标签
        $template->registerFunction('sha1', function($text) {
            return sha1($text);
        });
        
        // Base64编码标签
        $template->registerFunction('base64_encode', function($text) {
            return base64_encode($text);
        });
        
        // Base64解码标签
        $template->registerFunction('base64_decode', function($text) {
            return base64_decode($text);
        });
        
        // JSON编码标签
        $template->registerFunction('json_encode', function($data) {
            return json_encode($data, JSON_UNESCAPED_UNICODE);
        });
        
        // JSON解码标签
        $template->registerFunction('json_decode', function($json) {
            return json_decode($json, true);
        });
        
        // URL编码标签
        $template->registerFunction('urlencode', function($text) {
            return urlencode($text);
        });
        
        // URL解码标签
        $template->registerFunction('urldecode', function($text) {
            return urldecode($text);
        });
        
        // 首字母大写标签
        $template->registerFunction('ucfirst', function($text) {
            return ucfirst($text);
        });
        
        // 每个单词首字母大写标签
        $template->registerFunction('ucwords', function($text) {
            return ucwords($text);
        });
        
        // 转换为大写标签
        $template->registerFunction('strtoupper', function($text) {
            return strtoupper($text);
        });
        
        // 转换为小写标签
        $template->registerFunction('strtolower', function($text) {
            return strtolower($text);
        });
        
        // 反转字符串标签
        $template->registerFunction('strrev', function($text) {
            return strrev($text);
        });
        
        // 字符串长度标签
        $template->registerFunction('strlen', function($text) {
            return strlen($text);
        });
        
        // 字符串单词数标签
        $template->registerFunction('str_word_count', function($text) {
            return str_word_count($text);
        });
        
        // 数组长度标签
        $template->registerFunction('count', function($array) {
            return is_array($array) ? count($array) : 0;
        });
        
        // 数组求和标签
        $template->registerFunction('array_sum', function($array) {
            return is_array($array) ? array_sum($array) : 0;
        });
        
        // 数组平均值标签
        $template->registerFunction('array_avg', function($array) {
            return is_array($array) && count($array) > 0 ? array_sum($array) / count($array) : 0;
        });
        
        // 数组最大值标签
        $template->registerFunction('array_max', function($array) {
            return is_array($array) && count($array) > 0 ? max($array) : 0;
        });
        
        // 数组最小值标签
        $template->registerFunction('array_min', function($array) {
            return is_array($array) && count($array) > 0 ? min($array) : 0;
        });
        
        // 检查数组是否包含值标签
        $template->registerFunction('in_array', function($value, $array) {
            return is_array($array) ? in_array($value, $array) : false;
        });
        
        // 检查数组键是否存在标签
        $template->registerFunction('array_key_exists', function($key, $array) {
            return is_array($array) ? array_key_exists($key, $array) : false;
        });
        
        // 数组排序标签
        $template->registerFunction('array_sort', function($array, $order = 'asc') {
            if (!is_array($array)) {
                return [];
            }
            if ($order === 'desc') {
                rsort($array);
            } else {
                sort($array);
            }
            return $array;
        });
        
        // 数组反转标签
        $template->registerFunction('array_reverse', function($array) {
            return is_array($array) ? array_reverse($array) : [];
        });
        
        // 数组去重标签
        $template->registerFunction('array_unique', function($array) {
            return is_array($array) ? array_unique($array) : [];
        });
        
        // 数组合并标签
        $template->registerFunction('array_merge', function(...$arrays) {
            return call_user_func_array('array_merge', $arrays);
        });
        
        // 数组切片标签
        $template->registerFunction('array_slice', function($array, $offset, $length = null) {
            return is_array($array) ? array_slice($array, $offset, $length) : [];
        });
        
        // 数组连接标签
        $template->registerFunction('array_join', function($array, $separator = ',') {
            return is_array($array) ? implode($separator, $array) : '';
        });
        
        // 字符串分割标签
        $template->registerFunction('str_split', function($string, $separator = ',') {
            return explode($separator, $string);
        });
        
        // 正则替换标签
        $template->registerFunction('preg_replace', function($pattern, $replacement, $subject) {
            return preg_replace($pattern, $replacement, $subject);
        });
        
        // 正则匹配标签
        $template->registerFunction('preg_match', function($pattern, $subject) {
            return preg_match($pattern, $subject) ? true : false;
        });
        
        // 字符串替换标签
        $template->registerFunction('str_replace', function($search, $replace, $subject) {
            return str_replace($search, $replace, $subject);
        });
        
        // 字符串查找标签
        $template->registerFunction('strpos', function($haystack, $needle, $offset = 0) {
            return strpos($haystack, $needle, $offset);
        });
        
        // 字符串查找（不区分大小写）标签
        $template->registerFunction('stripos', function($haystack, $needle, $offset = 0) {
            return stripos($haystack, $needle, $offset);
        });
        
        // 子字符串标签
        $template->registerFunction('substr', function($string, $start, $length = null) {
            return $length !== null ? substr($string, $start, $length) : substr($string, $start);
        });
        
        // 条件标签
        $template->registerFunction('if', function($condition, $trueValue, $falseValue = '') {
            return $condition ? $trueValue : $falseValue;
        });
        
        // 空值默认标签
        $template->registerFunction('default', function($value, $default = '') {
            return !empty($value) ? $value : $default;
        });
        
        // 范围检查标签
        $template->registerFunction('in_range', function($value, $min, $max) {
            return $value >= $min && $value <= $max;
        });
        
        // 四舍五入标签
        $template->registerFunction('round', function($number, $precision = 0) {
            return round($number, $precision);
        });
        
        // 向上取整标签
        $template->registerFunction('ceil', function($number) {
            return ceil($number);
        });
        
        // 向下取整标签
        $template->registerFunction('floor', function($number) {
            return floor($number);
        });
        
        // 绝对值标签
        $template->registerFunction('abs', function($number) {
            return abs($number);
        });
        
        // 幂运算标签
        $template->registerFunction('pow', function($base, $exp) {
            return pow($base, $exp);
        });
        
        // 平方根标签
        $template->registerFunction('sqrt', function($number) {
            return sqrt($number);
        });
        
        // 取余标签
        $template->registerFunction('mod', function($dividend, $divisor) {
            return $dividend % $divisor;
        });
        
        // 最小值标签
        $template->registerFunction('min', function(...$numbers) {
            return min($numbers);
        });
        
        // 最大值标签
        $template->registerFunction('max', function(...$numbers) {
            return max($numbers);
        });
        
        // 求和标签
        $template->registerFunction('sum', function(...$numbers) {
            return array_sum($numbers);
        });
        
        // 平均值标签
        $template->registerFunction('avg', function(...$numbers) {
            return count($numbers) > 0 ? array_sum($numbers) / count($numbers) : 0;
        });
        
        // 百分比标签
        $template->registerFunction('percent', function($part, $total, $decimals = 2) {
            return $total > 0 ? round(($part / $total) * 100, $decimals) : 0;
        });
        
        // 进度条HTML标签
        $template->registerFunction('progress_bar', function($current, $total, $width = '100%', $height = '20px') {
            $percent = $total > 0 ? ($current / $total) * 100 : 0;
            return sprintf(
                '<div style="width:%s;height:%s;background:#e0e0e0;border-radius:3px;overflow:hidden;">' .
                '<div style="width:%.2f%%;height:100%%;background:#4CAF50;"></div></div>',
                $width, $height, $percent
            );
        });
        
        // 星级评分HTML标签
        $template->registerFunction('star_rating', function($rating, $max = 5, $size = '16px') {
            $stars = '';
            for ($i = 1; $i <= $max; $i++) {
                if ($i <= $rating) {
                    $stars .= '<span style="color:#FFD700;font-size:' . $size . ';">★</span>';
                } elseif ($i - 0.5 <= $rating) {
                    $stars .= '<span style="color:#FFD700;font-size:' . $size . ';">★</span>';
                } else {
                    $stars .= '<span style="color:#CCCCCC;font-size:' . $size . ';">★</span>';
                }
            }
            return $stars;
        });
        
        // 徽章HTML标签
        $template->registerFunction('badge', function($text, $type = 'primary') {
            $colors = [
                'primary' => '#007bff',
                'success' => '#28a745',
                'info' => '#17a2b8',
                'warning' => '#ffc107',
                'danger' => '#dc3545',
                'secondary' => '#6c757d'
            ];
            $color = isset($colors[$type]) ? $colors[$type] : $colors['primary'];
            return sprintf(
                '<span style="display:inline-block;padding:0.25em 0.6em;font-size:75%%;font-weight:700;line-height:1;text-align:center;white-space:nowrap;vertical-align:baseline;border-radius:0.25rem;background-color:%s;color:#fff;">%s</span>',
                $color, htmlspecialchars($text)
            );
        });
        
        // 标签HTML标签
        $template->registerFunction('tag', function($text, $type = 'primary') {
            $colors = [
                'primary' => '#007bff',
                'success' => '#28a745',
                'info' => '#17a2b8',
                'warning' => '#ffc107',
                'danger' => '#dc3545',
                'secondary' => '#6c757d'
            ];
            $color = isset($colors[$type]) ? $colors[$type] : $colors['primary'];
            return sprintf(
                '<span style="display:inline-block;padding:0.25em 0.6em;font-size:75%%;font-weight:700;line-height:1;text-align:center;white-space:nowrap;vertical-align:baseline;border-radius:0.25rem;background-color:%s;color:#fff;">%s</span>',
                $color, htmlspecialchars($text)
            );
        });
        
        // 图标HTML标签（使用Unicode字符）
        $template->registerFunction('icon', function($name, $size = '16px') {
            $icons = [
                'home' => '🏠',
                'user' => '👤',
                'settings' => '⚙️',
                'search' => '🔍',
                'edit' => '✏️',
                'delete' => '🗑️',
                'add' => '➕',
                'remove' => '➖',
                'check' => '✅',
                'close' => '❌',
                'star' => '⭐',
                'heart' => '❤️',
                'comment' => '💬',
                'share' => '📤',
                'download' => '📥',
                'upload' => '📤',
                'email' => '📧',
                'phone' => '📱',
                'location' => '📍',
                'calendar' => '📅',
                'clock' => '🕐',
                'warning' => '⚠️',
                'info' => 'ℹ️',
                'success' => '✅',
                'error' => '❌',
                'question' => '❓',
                'arrow-left' => '◀️',
                'arrow-right' => '▶️',
                'arrow-up' => '⬆️',
                'arrow-down' => '⬇️',
                'menu' => '☰',
                'list' => '📋',
                'grid' => '▦',
                'folder' => '📁',
                'file' => '📄',
                'image' => '🖼️',
                'video' => '🎬',
                'audio' => '🎵',
                'link' => '🔗',
                'lock' => '🔒',
                'unlock' => '🔓',
                'eye' => '👁️',
                'eye-off' => '🙈',
                'bell' => '🔔',
                'bookmark' => '🔖',
                'print' => '🖨️',
                'refresh' => '🔄',
                'zoom-in' => '🔍',
                'zoom-out' => '🔎'
            ];
            $icon = isset($icons[$name]) ? $icons[$name] : '❓';
            return sprintf('<span style="font-size:%s;">%s</span>', $size, $icon);
        });
    }
    
    /**
     * 注册扩展过滤器
     * @param Template $template 模板对象
     */
    private static function registerFilters($template) {
        // 时间差过滤器
        $template->registerFilter('time_ago', function($value) {
            return self::timeAgo($value);
        });
        
        // 文件大小格式化过滤器
        $template->registerFilter('file_size', function($value) {
            return self::formatBytes($value);
        });
        
        // 数字格式化过滤器
        $template->registerFilter('number_format', function($value, $decimals = 0) {
            return number_format($value, $decimals);
        });

        // 中文风格数字格式化过滤器（千/万/亿）
        $template->registerFilter('cn_number', function($value, $decimals = 1) {
            return self::formatCnNumber($value, $decimals);
        });
        
        // 百分比过滤器
        $template->registerFilter('percent', function($value, $total, $decimals = 2) {
            return $total > 0 ? round(($value / $total) * 100, $decimals) : 0;
        });
        
        // 截断过滤器
        $template->registerFilter('truncate', function($value, $length = 100, $suffix = '...') {
            return mb_strlen($value) > $length ? mb_substr($value, 0, $length) . $suffix : $value;
        });
        
        // 去除HTML过滤器
        $template->registerFilter('strip_tags', function($value) {
            return strip_tags($value);
        });
        
        // 转义HTML过滤器
        $template->registerFilter('escape', function($value) {
            return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        });
        
        // URL编码过滤器
        $template->registerFilter('urlencode', function($value) {
            return urlencode($value);
        });
        
        // 大写过滤器
        $template->registerFilter('upper', function($value) {
            return strtoupper($value);
        });
        
        // 小写过滤器
        $template->registerFilter('lower', function($value) {
            return strtolower($value);
        });
        
        // 首字母大写过滤器
        $template->registerFilter('ucfirst', function($value) {
            return ucfirst($value);
        });
        
        // 每个单词首字母大写过滤器
        $template->registerFilter('ucwords', function($value) {
            return ucwords($value);
        });
        
        // 日期格式化过滤器
        $template->registerFilter('date', function($value, $format = 'Y-m-d') {
            $timestamp = is_numeric($value) ? $value : strtotime($value);
            return date($format, $timestamp);
        });
        
        // 时间格式化过滤器
        $template->registerFilter('time', function($value, $format = 'H:i:s') {
            $timestamp = is_numeric($value) ? $value : strtotime($value);
            return date($format, $timestamp);
        });
        
        // 安全Markdown解析过滤器
        $template->registerFilter('markdown_parse', function($value) {
            return self::parseMarkdown($value);
        });
    }
    
    /**
     * 安全解析Markdown格式
     * 仅支持粗体、斜体、链接等基本格式，过滤所有HTML标签
     * @param string $text 原始文本
     * @return string 解析后的HTML
     */
    public static function parseMarkdown($text) {
        // 允许的安全HTML标签
        $allowedTags = '<strong><em><a><br><p><ul><ol><li>';
        
        // 使用strip_tags过滤不安全的HTML标签，保留安全标签
        $text = strip_tags($text, $allowedTags);
        
        // 解析粗体 **text**
        $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
        
        // 解析斜体 *text*
        $text = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $text);
        
        // 解析链接 [text](url)
        $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>', $text);
        
        // 处理换行
        $text = nl2br($text);
        
        return $text;
    }
    
    /**
     * 注册扩展函数
     * @param Template $template 模板对象
     */
    private static function registerFunctions($template) {
        // 检查权限函数
        $template->registerFunction('can', function($permission) {
            if (!isset($_SESSION['admin'])) {
                return false;
            }
            require_once APP_PATH . '/Models/RoleModel.php';
            return RoleModel::checkUserPermission($_SESSION['admin']['id'], $permission);
        });
        
        // 检查角色函数
        $template->registerFunction('is_role', function($role) {
            if (!isset($_SESSION['admin'])) {
                return false;
            }
            return $_SESSION['admin']['role'] == $role;
        });
        
        // 检查是否管理员函数
        $template->registerFunction('is_admin', function() {
            if (!isset($_SESSION['admin'])) {
                return false;
            }
            return $_SESSION['admin']['role'] == 1;
        });
        
        // 检查是否登录函数
        $template->registerFunction('is_logged_in', function() {
            return isset($_SESSION['user']);
        });
        
        // 检查是否为访客函数
        $template->registerFunction('is_guest', function() {
            return !isset($_SESSION['user']);
        });
        
        // 获取当前用户ID函数
        $template->registerFunction('current_user_id', function() {
            return isset($_SESSION['user']) ? $_SESSION['user']['id'] : 0;
        });
        
        // 获取当前用户名函数
        $template->registerFunction('current_username', function() {
            return isset($_SESSION['user']) ? $_SESSION['user']['username'] : '';
        });
        
        // 获取当前用户昵称函数
        $template->registerFunction('current_nickname', function() {
            return isset($_SESSION['user']) ? $_SESSION['user']['nickname'] : '';
        });
        
        // 获取当前用户头像函数
        $template->registerFunction('current_avatar', function() {
            return isset($_SESSION['user']) ? $_SESSION['user']['avatar'] : '';
        });
        
        // 获取当前用户邮箱函数
        $template->registerFunction('current_email', function() {
            return isset($_SESSION['user']) ? $_SESSION['user']['email'] : '';
        });
        
        // URL生成函数
        $template->registerFunction('url', function($route, $params = []) {
            $siteUrl = Config::get('site.url', '');
            if (empty($siteUrl)) {
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $siteUrl = "$protocol://$host";
            }
            
            // 移除末尾的斜杠
            $siteUrl = rtrim($siteUrl, '/');
            
            // 构建URL
            $url = $siteUrl . '/' . $route;
            
            // 添加查询参数
            if (!empty($params)) {
                $url .= '?' . http_build_query($params);
            }
            
            return $url;
        });
        
        // 站点URL函数
        $template->registerFunction('site_url', function() {
            return Config::get('site.url', '');
        });
    }
    
    /**
     * 辅助方法：获取用户IP
     */
    private static function getUserIP() {
        $ip = '';
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }
    
    /**
     * 辅助方法：获取用户浏览器
     */
    private static function getUserBrowser() {
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        
        if (strpos($userAgent, 'Firefox') !== false) {
            return 'Firefox';
        } elseif (strpos($userAgent, 'Chrome') !== false) {
            return 'Chrome';
        } elseif (strpos($userAgent, 'Safari') !== false) {
            return 'Safari';
        } elseif (strpos($userAgent, 'Edge') !== false) {
            return 'Edge';
        } elseif (strpos($userAgent, 'Opera') !== false || strpos($userAgent, 'OPR') !== false) {
            return 'Opera';
        } elseif (strpos($userAgent, 'MSIE') !== false || strpos($userAgent, 'Trident') !== false) {
            return 'Internet Explorer';
        }
        
        return 'Unknown';
    }
    
    /**
     * 辅助方法：获取用户操作系统
     */
    private static function getUserOS() {
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        
        if (strpos($userAgent, 'Windows') !== false) {
            return 'Windows';
        } elseif (strpos($userAgent, 'Mac') !== false) {
            return 'MacOS';
        } elseif (strpos($userAgent, 'Linux') !== false) {
            return 'Linux';
        } elseif (strpos($userAgent, 'Android') !== false) {
            return 'Android';
        } elseif (strpos($userAgent, 'iOS') !== false || strpos($userAgent, 'iPhone') !== false || strpos($userAgent, 'iPad') !== false) {
            return 'iOS';
        }
        
        return 'Unknown';
    }
    
    /**
     * 辅助方法：获取用户设备类型
     */
    private static function getUserDevice() {
        if (self::isMobile()) {
            return 'mobile';
        } elseif (self::isTablet()) {
            return 'tablet';
        } else {
            return 'desktop';
        }
    }
    
    /**
     * 辅助方法：检查是否为移动设备
     */
    private static function isMobile() {
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $mobileAgents = ['Android', 'iPhone', 'iPod', 'BlackBerry', 'Windows Phone'];
        
        foreach ($mobileAgents as $agent) {
            if (strpos($userAgent, $agent) !== false) {
                return strpos($userAgent, 'iPad') === false;
            }
        }
        
        return false;
    }
    
    /**
     * 辅助方法：检查是否为平板设备
     */
    private static function isTablet() {
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        return strpos($userAgent, 'iPad') !== false;
    }
    
    /**
     * 辅助方法：检查是否为桌面设备
     */
    private static function isDesktop() {
        return !self::isMobile() && !self::isTablet();
    }
    
    /**
     * 辅助方法：检查是否为AJAX请求
     */
    private static function isAjax() {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * 辅助方法：检查是否为开发环境
     */
    private static function isDevelopment() {
        return Config::get('app.env', 'production') === 'development';
    }
    
    /**
     * 辅助方法：检查是否为生产环境
     */
    private static function isProduction() {
        return Config::get('app.env', 'production') === 'production';
    }
    
    /**
     * 辅助方法：检查是否为测试环境
     */
    private static function isTesting() {
        return Config::get('app.env', 'production') === 'testing';
    }
    
    /**
     * 辅助方法：格式化字节大小
     */
    private static function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * 辅助方法：中文风格数字格式化（千/万/亿）
     * 规则：
     *   1 ~ 999       → 原样显示（1、999）
     *   1000 ~ 9999   → 1.0 千 / 1 千
     *   10000 ~ 99999999 → 1.0 万、12.3 万
     *   ≥ 100000000       → 1.0 亿、1.2 亿
     * 
     * @param int|float|string $number 数字
     * @param int $decimals 小数位数，默认 1（例如 1.5 万）
     * @return string 格式化后的字符串
     */
    private static function formatCnNumber($number, $decimals = 1) {
        $number = (float)$number;

        // 负数：保留负号后按正数处理
        $sign = '';
        if ($number < 0) {
            $sign = '-';
            $number = abs($number);
        }

        if ($number < 1000) {
            // 1 ~ 999：原样显示
            $result = (string)(int)$number;
        } elseif ($number < 10000) {
            // 1000 ~ 9999 → X.X 千
            $value = $number / 1000;
            // 刚好是整数时不加小数（例如 1 千 而非 1.0 千）
            if (floor($value) == $value) {
                $result = (int)$value . ' 千';
            } else {
                $result = number_format($value, $decimals, '.', '') . ' 千';
            }
        } elseif ($number < 100000000) {
            // 10000 ~ 99999999 → X.X 万
            $value = $number / 10000;
            if (floor($value) == $value) {
                $result = (int)$value . ' 万';
            } else {
                $result = number_format($value, $decimals, '.', '') . ' 万';
            }
        } else {
            // ≥ 100000000 → X.X 亿
            $value = $number / 100000000;
            if (floor($value) == $value) {
                $result = (int)$value . ' 亿';
            } else {
                $result = number_format($value, $decimals, '.', '') . ' 亿';
            }
        }

        return $sign . $result;
    }
    
    /**
     * 辅助方法：相对时间
     */
    private static function timeAgo($timestamp) {
        $diff = time() - $timestamp;
        
        if ($diff < 60) {
            return '刚刚';
        } elseif ($diff < 3600) {
            return floor($diff / 60) . '分钟前';
        } elseif ($diff < 86400) {
            return floor($diff / 3600) . '小时前';
        } elseif ($diff < 2592000) {
            return floor($diff / 86400) . '天前';
        } elseif ($diff < 31536000) {
            return floor($diff / 2592000) . '个月前';
        } else {
            return floor($diff / 31536000) . '年前';
        }
    }
    
    /**
     * 安全数学表达式计算器（不使用 eval()，避免远程代码执行风险）
     * 支持: 加减乘除、取模、括号、小数、负数
     */
    private static function evaluateMath($expression) {
        try {
            // 安全过滤：只保留允许的字符
            $expression = preg_replace('/[^0-9+\-*\/().%\s]/', '', $expression);
            
            // 安全检查：表达式不能太长（防止资源耗尽）
            if (strlen($expression) > 200) {
                return 0;
            }
            
            return self::safeCalculate($expression);
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * 安全的数学计算（递归下降解析器，零 eval 依赖）
     */
    private static function safeCalculate($expression) {
        $expression = str_replace(' ', '', $expression);
        if ($expression === '') {
            return 0;
        }
        
        return self::parseAddSub($expression);
    }
    
    private static function parseAddSub(&$expression) {
        $left = self::parseMulDivMod($expression);
        
        while (strlen($expression) > 0) {
            $op = $expression[0];
            if ($op !== '+' && $op !== '-') {
                break;
            }
            $expression = substr($expression, 1);
            $right = self::parseMulDivMod($expression);
            
            if ($op === '+') {
                $left += $right;
            } else {
                $left -= $right;
            }
        }
        
        return $left;
    }
    
    private static function parseMulDivMod(&$expression) {
        $left = self::parseUnary($expression);
        
        while (strlen($expression) > 0) {
            $op = $expression[0];
            if ($op !== '*' && $op !== '/' && $op !== '%') {
                break;
            }
            $expression = substr($expression, 1);
            $right = self::parseUnary($expression);
            
            if ($op === '*') {
                $left *= $right;
            } elseif ($op === '/') {
                if ($right == 0) {
                    return 0; // 除零保护
                }
                $left /= $right;
            } else {
                if ($right == 0) {
                    return 0; // 取模零保护
                }
                $left %= $right;
            }
        }
        
        return $left;
    }
    
    private static function parseUnary(&$expression) {
        // 处理负号
        if (strlen($expression) > 0 && $expression[0] === '-') {
            $expression = substr($expression, 1);
            return -self::parseUnary($expression);
        }
        
        return self::parseAtom($expression);
    }
    
    private static function parseAtom(&$expression) {
        // 括号表达式
        if (strlen($expression) > 0 && $expression[0] === '(') {
            $expression = substr($expression, 1); // 去掉 (
            $value = self::parseAddSub($expression);
            if (strlen($expression) > 0 && $expression[0] === ')') {
                $expression = substr($expression, 1); // 去掉 )
            }
            return $value;
        }
        
        // 数字解析（支持整数和小数）
        $number = '';
        while (strlen($expression) > 0) {
            $char = $expression[0];
            if (ctype_digit($char) || $char === '.') {
                $number .= $char;
                $expression = substr($expression, 1);
            } else {
                break;
            }
        }
        
        if ($number === '') {
            // 遇到未知字符，跳过
            if (strlen($expression) > 0) {
                $expression = substr($expression, 1);
            }
            return 0;
        }
        
        return floatval($number);
    }
    
    /**
     * 辅助方法：生成随机字符串
     */
    private static function generateRandomString($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $string = '';
        for ($i = 0; $i < $length; $i++) {
            $string .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $string;
    }
    
    /**
     * 辅助方法：生成UUID
     */
    private static function generateUUID() {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
    
    /**
     * 辅助方法：获取在线用户数
     */
    private static function getOnlineUsers() {
        // 这里应该从缓存或数据库获取真实数据
        // 暂时返回模拟数据
        return mt_rand(1, 100);
    }
    
    /**
     * 辅助方法：获取总访问量
     */
    private static function getTotalVisits() {
        // 这里应该从数据库获取真实数据
        // 暂时返回模拟数据
        return mt_rand(1000, 100000);
    }
    
    /**
     * 辅助方法：获取今日访问量
     */
    private static function getTodayVisits() {
        // 这里应该从数据库获取真实数据
        // 暂时返回模拟数据
        return mt_rand(10, 1000);
    }
}
