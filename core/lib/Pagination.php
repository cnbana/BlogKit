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
 * 分页类
 * 用于处理文章列表、分类列表等数据的分页显示
 */
class Pagination {
    private $total;         // 总记录数
    private $page_size;     // 每页显示记录数
    private $current_page;  // 当前页码
    private $total_pages;   // 总页数
    private $base_url;      // 基础URL
    private $url_param;     // URL参数名称，默认为page
    
    /**
     * 构造函数
     * @param int $total 总记录数
     * @param int $page_size 每页显示记录数
     * @param string $base_url 基础URL
     * @param string $url_param URL参数名称
     */
    public function __construct($total, $page_size = 10, $base_url = '', $url_param = 'page') {
        $this->total = $total;
        $this->page_size = $page_size;
        
        // 如果没有提供base_url，则自动获取当前页面的URL
        if (empty($base_url)) {
            $base_url = $this->getCurrentUrl();
        }
        
        $this->base_url = $base_url;
        $this->url_param = $url_param;
        
        // 计算总页数
        $this->total_pages = ceil($this->total / $this->page_size);
        
        // 获取当前页码
        $this->current_page = $this->calculateCurrentPage();
        
        // 确保当前页码在有效范围内
        $this->current_page = max(1, min($this->current_page, $this->total_pages));
    }
    
    /**
     * 计算当前页码（从GET参数中获取）
     * @return int 当前页码
     */
    private function calculateCurrentPage() {
        // 从GET参数中获取当前页码
        if (isset($_GET[$this->url_param]) && is_numeric($_GET[$this->url_param])) {
            return intval($_GET[$this->url_param]);
        }
        
        // 默认第一页
        return 1;
    }
    
    /**
     * 获取偏移量
     * @return int 数据库查询的offset值
     */
    public function getOffset() {
        return ($this->current_page - 1) * $this->page_size;
    }
    
    /**
     * 获取每页显示记录数
     * @return int 每页显示记录数
     */
    public function getPageSize() {
        return $this->page_size;
    }
    
    /**
     * 获取当前页码
     * @return int 当前页码
     */
    public function getCurrentPage() {
        return $this->current_page;
    }
    
    /**
     * 获取总页数
     * @return int 总页数
     */
    public function getTotalPages() {
        return $this->total_pages;
    }
    
    /**
     * 获取总记录数
     * @return int 总记录数
     */
    public function getTotal() {
        return $this->total;
    }
    
    /**
     * 获取当前页面的URL
     * @return string 当前页面的完整URL（不包含页码参数）
     */
    private function getCurrentUrl() {
        // 获取协议
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        
        // 获取主机名
        $host = $_SERVER['HTTP_HOST'];
        
        // 获取路径
        $path = $_SERVER['REQUEST_URI'];
        
        // 去除查询字符串中的页码参数
        if (strpos($path, '?') !== false) {
            list($path_part, $query_part) = explode('?', $path, 2);
            parse_str($query_part, $params);
            
            // 移除页码参数
            if (isset($params[$this->url_param])) {
                unset($params[$this->url_param]);
            }
            
            // 重新构建URL
            $path = $path_part;
            if (!empty($params)) {
                $path .= '?' . http_build_query($params);
            }
        }
        
        return $protocol . '://' . $host . $path;
    }
    
    /**
     * 获取上一页页码
     * @return int|null 上一页页码，如果当前是第一页则返回null
     */
    public function getPrevPage() {
        return $this->current_page > 1 ? $this->current_page - 1 : null;
    }
    
    /**
     * 获取下一页页码
     * @return int|null 下一页页码，如果当前是最后一页则返回null
     */
    public function getNextPage() {
        return $this->current_page < $this->total_pages ? $this->current_page + 1 : null;
    }
    
    /**
     * 生成分页导航 HTML
     * @param int $show_pages 显示的页码数量（奇数）
     * @return string 分页导航 HTML
     */
    public function createLinks($show_pages = 5) {
        if ($this->total_pages <= 1) {
            return '';
        }
        
        $html = '<div class="pagination">';
        
        // 上一页链接
        $prev_page = $this->getPrevPage();
        if ($prev_page) {
            $html .= '<a href="' . $this->createUrl($prev_page) . '" class="page-link prev">上一页</a>';
        } else {
            $html .= '<span class="page-link disabled">上一页</span>';
        }
        
        // 计算显示的页码范围
        $half = floor($show_pages / 2);
        $start = max(1, $this->current_page - $half);
        $end = min($this->total_pages, $start + $show_pages - 1);
        
        // 调整起始页码，确保显示足够数量的页码
        if ($end - $start + 1 < $show_pages) {
            $start = max(1, $end - $show_pages + 1);
        }
        
        // 首页链接
        if ($start > 1) {
            $html .= '<a href="' . $this->createUrl(1) . '" class="page-link">1</a>';
            if ($start > 2) {
                $html .= '<span class="ellipsis">...</span>';
            }
        }
        
        // 中间页码链接
        for ($i = $start; $i <= $end; $i++) {
            if ($i == $this->current_page) {
                $html .= '<span class="page-link active">' . $i . '</span>';
            } else {
                $html .= '<a href="' . $this->createUrl($i) . '" class="page-link">' . $i . '</a>';
            }
        }
        
        // 末页链接
        if ($end < $this->total_pages) {
            if ($end < $this->total_pages - 1) {
                $html .= '<span class="ellipsis">...</span>';
            }
            $html .= '<a href="' . $this->createUrl($this->total_pages) . '" class="page-link">' . $this->total_pages . '</a>';
        }
        
        // 下一页链接
        $next_page = $this->getNextPage();
        if ($next_page) {
            $html .= '<a href="' . $this->createUrl($next_page) . '" class="page-link next">下一页</a>';
        } else {
            $html .= '<span class="page-link disabled">下一页</span>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * 创建分页URL
     * @param int $page 页码
     * @return string 分页URL
     */
    private function createUrl($page) {
        $url = $this->base_url;
        
        // 如果URL中已经包含查询参数
        if (strpos($url, '?') !== false) {
            // 替换现有的页码参数
            if (preg_match('/' . $this->url_param . '=\d+/', $url)) {
                $url = preg_replace('/' . $this->url_param . '=\d+/', $this->url_param . '=' . $page, $url);
            } else {
                $url .= '&' . $this->url_param . '=' . $page;
            }
        } else {
            // 添加页码参数
            $url .= '?' . $this->url_param . '=' . $page;
        }
        
        return $url;
    }
    
    /**
     * 获取分页信息数组，用于模板渲染
     * @return array 分页信息数组
     */
    public function getPaginationInfo() {
        return [
            'total' => $this->total,
            'page_size' => $this->page_size,
            'current_page' => $this->current_page,
            'total_pages' => $this->total_pages,
            'prev_page' => $this->getPrevPage(),
            'next_page' => $this->getNextPage(),
            'offset' => $this->getOffset(),
            'has_prev' => $this->getPrevPage() !== null,
            'has_next' => $this->getNextPage() !== null,
        ];
    }
}
