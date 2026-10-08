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
 * BlogKit 模板过滤器组件
 * 
 * 从 Template.php 中提取的过滤器系统，包括：
 *   - 内置过滤器（upper, lower, truncate, date, time, escape, strip_tags）
 *   - 自定义过滤器注册与调用
 *   - 过滤器链应用
 * 
 * @package BlogKit
 * @since 2.1.0
 */
class TemplateFilter
{
    /**
     * @var array 自定义过滤器注册表
     */
    private $customFilters = [];

    /**
     * 注册自定义过滤器
     * 
     * @param string   $name     过滤器名称
     * @param callable $callback  过滤回调函数
     */
    public function register($name, callable $callback)
    {
        $this->customFilters[$name] = $callback;
    }

    /**
     * 获取所有注册的过滤器
     * 
     * @return array
     */
    public function getFilters()
    {
        return $this->customFilters;
    }

    /**
     * 应用过滤器链到值
     * 过滤器格式：filter1|filter2:arg1:arg2|filter3
     * 
     * @param mixed  $value   要过滤的值
     * @param string $filters 过滤器列表字符串
     * @return mixed 过滤后的值
     */
    public function apply($value, $filters)
    {
        if (empty($filters)) {
            return $value;
        }

        $filterList = explode('|', $filters);
        foreach ($filterList as $filter) {
            $filterParts = explode(':', $filter);
            $filterName  = trim($filterParts[0]);
            $filterArgs  = array_slice($filterParts, 1);

            // 值作为第一个参数
            array_unshift($filterArgs, $value);

            // 优先检查内置过滤器
            $method = 'filter_' . $filterName;
            if (method_exists($this, $method)) {
                $value = call_user_func_array([$this, $method], $filterArgs);
            }
            // 然后检查自定义注册过滤器
            elseif (isset($this->customFilters[$filterName])) {
                $value = call_user_func_array($this->customFilters[$filterName], $filterArgs);
            }
        }

        return $value;
    }

    // ==================== 内置过滤器 ====================

    /**
     * 转换为大写
     * @param string $value
     * @return string
     */
    private function filter_upper($value)
    {
        return strtoupper($value);
    }

    /**
     * 转换为小写
     * @param string $value
     * @return string
     */
    private function filter_lower($value)
    {
        return strtolower($value);
    }

    /**
     * 截断文本
     * @param string $value   输入值
     * @param int    $length  截断长度（默认100）
     * @param string $suffix  后缀（默认...）
     * @return string
     */
    private function filter_truncate($value, $length = 100, $suffix = '...')
    {
        if (mb_strlen($value) <= $length) {
            return $value;
        }
        return mb_substr($value, 0, $length - mb_strlen($suffix)) . $suffix;
    }

    /**
     * 格式化日期
     * @param string|int $value  输入值（时间戳或日期字符串）
     * @param string     $format 日期格式（默认Y-m-d）
     * @return string
     */
    private function filter_date($value, $format = 'Y-m-d')
    {
        $timestamp = is_numeric($value) ? $value : strtotime($value);
        return date($format, $timestamp);
    }

    /**
     * 格式化时间
     * @param string|int $value  输入值
     * @param string     $format 时间格式（默认H:i:s）
     * @return string
     */
    private function filter_time($value, $format = 'H:i:s')
    {
        $timestamp = is_numeric($value) ? $value : strtotime($value);
        return date($format, $timestamp);
    }

    /**
     * HTML 转义
     * @param string $value
     * @return string
     */
    private function filter_escape($value)
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * 去除 HTML 标签
     * @param string $value
     * @return string
     */
    private function filter_strip_tags($value) {
        return strip_tags($value);
    }

    /**
     * 中文相对时间（例如：3分钟前、2小时前、3天前）
     * @param string|int $value 时间戳或日期字符串
     * @return string
     */
    private function filter_relative($value) {
        $timestamp = is_numeric($value) ? $value : strtotime($value);
        if (!$timestamp) return $value;
        
        $diff = time() - $timestamp;
        
        if ($diff < 60) {
            return '刚刚';
        } elseif ($diff < 3600) {
            $minutes = floor($diff / 60);
            return $minutes . '分钟前';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . '小时前';
        } elseif ($diff < 2592000) {
            $days = floor($diff / 86400);
            return $days . '天前';
        } elseif ($diff < 31536000) {
            $months = floor($diff / 2592000);
            return $months . '个月前';
        } else {
            $years = floor($diff / 31536000);
            return $years . '年前';
        }
    }

    /**
     * 自定义相对时间（更精确的显示逻辑）
     * 规则：
     * <1分钟：XX秒前
     * 1min～60min：XX分钟前
     * 1h～当天 24 点：XX小时前
     * 跨天<48h：昨天 HH:MM
     * 2～7 天：X 天前
     * ＞7 天：M-D（5-12）
     * 跨年：YYYY-M-D（2025-03-10）
     * @param string|int $value 时间戳或日期字符串
     * @return string
     */
    private function filter_relative1($value) {
        $timestamp = is_numeric($value) ? $value : strtotime($value);
        if (!$timestamp) return $value;
        
        $now = time();
        $diff = $now - $timestamp;
        
        // 检查是否同一天
        $todayStart = strtotime(date('Y-m-d 00:00:00'));
        $yesterdayStart = $todayStart - 86400;
        $isSameDay = $timestamp >= $todayStart;
        $isYesterday = $timestamp >= $yesterdayStart && $timestamp < $todayStart;
        
        // 检查是否跨年
        $thisYear = date('Y', $now);
        $thatYear = date('Y', $timestamp);
        $isCrossYear = $thisYear !== $thatYear;
        
        if ($diff < 60) {
            // < 1分钟
            return $diff . '秒前';
        } elseif ($diff < 3600) {
            // 1分钟 ~ 60分钟
            $minutes = floor($diff / 60);
            return $minutes . '分钟前';
        } elseif ($isSameDay) {
            // 当天剩余时间
            $hours = floor($diff / 3600);
            return $hours . '小时前';
        } elseif ($isYesterday) {
            // 昨天
            return '昨天 ' . date('H:i', $timestamp);
        } else {
            $days = floor($diff / 86400);
            if ($days <= 7) {
                // 2 ~ 7天
                return $days . '天前';
            } elseif (!$isCrossYear) {
                // 当年，显示 M-D
                return date('m-d', $timestamp);
            } else {
                // 跨年，显示 YYYY-M-D
                return date('Y-m-d', $timestamp);
            }
        }
    }

    /**
     * 英文相对时间（例如：3 minutes ago, 2 hours ago）
     * @param string|int $value 时间戳或日期字符串
     * @return string
     */
    private function filter_relative_en($value) {
        $timestamp = is_numeric($value) ? $value : strtotime($value);
        if (!$timestamp) return $value;
        
        $diff = time() - $timestamp;
        
        if ($diff < 60) {
            return 'just now';
        } elseif ($diff < 3600) {
            $minutes = floor($diff / 60);
            return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 2592000) {
            $days = floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 31536000) {
            $months = floor($diff / 2592000);
            return $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
        } else {
            $years = floor($diff / 31536000);
            return $years . ' year' . ($years > 1 ? 's' : '') . ' ago';
        }
    }

    /**
     * 标准日期时间格式（Y-m-d H:i:s）
     * @param string|int $value 时间戳或日期字符串
     * @return string
     */
    private function filter_datetime($value) {
        $timestamp = is_numeric($value) ? $value : strtotime($value);
        return date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * 英文格式日期（June 4, 2024）
     * @param string|int $value 时间戳或日期字符串
     * @return string
     */
    private function filter_date_en($value) {
        $timestamp = is_numeric($value) ? $value : strtotime($value);
        return date('F j, Y', $timestamp);
    }

    /**
     * 英文格式日期时间（June 4, 2024 3:30 PM）
     * @param string|int $value 时间戳或日期字符串
     * @return string
     */
    private function filter_datetime_en($value) {
        $timestamp = is_numeric($value) ? $value : strtotime($value);
        return date('F j, Y g:i A', $timestamp);
    }

    /**
     * 12小时制时间（3:30 PM）
     * @param string|int $value 时间戳或日期字符串
     * @return string
     */
    private function filter_time_12h($value) {
        $timestamp = is_numeric($value) ? $value : strtotime($value);
        return date('g:i A', $timestamp);
    }

    /**
     * 确保输出 Unix 时间戳
     * @param string|int $value 时间戳或日期字符串
     * @return int
     */
    private function filter_timestamp($value) {
        return is_numeric($value) ? (int)$value : strtotime($value);
    }

    /**
     * ISO 8601 标准格式（2024-06-04T15:30:00+08:00）
     * @param string|int $value 时间戳或日期字符串
     * @return string
     */
    private function filter_iso8601($value) {
        $timestamp = is_numeric($value) ? $value : strtotime($value);
        return date('c', $timestamp);
    }
}
