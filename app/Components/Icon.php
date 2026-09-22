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
 * 统一图标组件
 * 封装SVG图标，提供统一的调用接口
 * 
 * 使用方法：
 * <?php Icon::render('user'); ?>
 * <?php Icon::render('settings', 'lg', '#007bff'); ?>
 * <?php Icon::render('trash', 'sm', '', ['class' => 'delete-icon']); ?>
 */
class Icon {
    
    /**
     * 图标尺寸映射
     */
    const SIZES = [
        'xs' => '12px',
        'sm' => '14px',
        'md' => '16px',
        'lg' => '20px',
        'xl' => '24px',
        '2xl' => '32px'
    ];
    
    /**
     * 默认配置
     */
    const DEFAULT_SIZE = 'md';
    const DEFAULT_COLOR = 'currentColor';
    
    /**
     * 渲染图标
     * 
     * @param string $name 图标名称
     * @param string $size 图标尺寸 (xs/sm/md/lg/xl/2xl)
     * @param string $color 图标颜色
     * @param array $attributes 额外属性
     * @return string SVG图标HTML
     */
    public static function render($name, $size = null, $color = null, $attributes = []) {
        $size = $size ?: self::DEFAULT_SIZE;
        $color = $color ?: self::DEFAULT_COLOR;
        $svgSize = self::SIZES[$size] ?? self::SIZES[self::DEFAULT_SIZE];
        
        // 获取图标SVG内容
        $svgContent = self::getIcon($name);
        
        if (empty($svgContent)) {
            return '';
        }
        
        // 构建属性字符串
        $attrString = self::buildAttributes([
            'width' => $svgSize,
            'height' => $svgSize,
            'fill' => $color,
            'class' => 'icon icon-' . $name . (isset($attributes['class']) ? ' ' . $attributes['class'] : ''),
            'aria-hidden' => 'true'
        ]);
        
        return '<svg ' . $attrString . '>' . $svgContent . '</svg>';
    }
    
    /**
     * 获取图标SVG内容
     * 
     * @param string $name 图标名称
     * @return string
     */
    private static function getIcon($name) {
        $icons = self::getIcons();
        return isset($icons[$name]) ? $icons[$name] : '';
    }
    
    /**
     * 获取所有图标定义
     * 从svg_icons.php加载或内联定义
     * 
     * @return array
     */
    private static function getIcons() {
        static $icons = null;
        
        if ($icons === null) {
            $icons = [];
            
            // 尝试从svg_icons.php加载
            $svgIconsPath = ADMIN_PATH . '/templates/components/svg_icons.php';
            if (file_exists($svgIconsPath)) {
                ob_start();
                include $svgIconsPath;
                $output = ob_get_clean();
                
                // 解析svg_icons.php中的图标定义
                // 假设svg_icons.php中定义了$icons数组
                if (isset($GLOBALS['icons']) && is_array($GLOBALS['icons'])) {
                    $icons = $GLOBALS['icons'];
                }
            }
            
            // 默认图标（如果svg_icons.php未定义）
            if (empty($icons)) {
                $icons = self::getDefaultIcons();
            }
        }
        
        return $icons;
    }
    
    /**
     * 默认图标集合
     */
    private static function getDefaultIcons() {
        return [
            'user' => '<path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>',
            'settings' => '<path d="M19.43 12.98c.04-.32.07-.64.07-.98s-.03-.66-.07-.98l2.11-1.65c.19-.15.24-.42.12-.64l-2-3.46c-.12-.22-.39-.3-.61-.22l-2.49 1c-.52-.39-1.08-.7-1.66-.94l-.38-2.65c-.03-.24-.24-.42-.48-.42h-4c-.24 0-.45.18-.48.42l-.38 2.65c-.58.24-1.14.55-1.66.94l-2.49-1c-.22-.08-.49 0-.61.22l-2 3.46c-.12.22-.07.49.12.64l2.11 1.65c-.04.32-.07.64-.07.98s.03.66.07.98l-2.11 1.65c-.19.15-.24.42-.12.64l2 3.46c.12.22.39.3.61.22l2.49-1c.52.39 1.08.7 1.66.94l.38 2.65c.03.24.24.42.48.42h4c.24 0 .45-.18.48-.42l.38-2.65c.58-.24 1.14-.55 1.66-.94l2.49 1c.22.08.49 0 .61-.22l2-3.46c.12-.22.07-.49-.12-.64l-2.11-1.65zm-7.43 2.52c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z"/>',
            'trash' => '<path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>',
            'edit' => '<path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39 1.02-.39 1.41 0l2.34 2.34c.39.39.39 1.02 0 1.41l-2.34 2.34c-.39.39-1.02.39-1.41 0l-2.34-2.34c-.38-.4-.38-1.03 0-1.42z"/>',
            'plus' => '<path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>',
            'search' => '<path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.77l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>',
            'menu' => '<path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/>',
            'x' => '<path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12 19 6.41z"/>',
            'check' => '<path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>',
            'alert' => '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>',
            'info' => '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>',
            'home' => '<path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>',
            'file' => '<path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/>',
            'folder' => '<path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/>',
            'upload' => '<path d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM14 13v4h-4v-4H7l5-5 5 5h-3z"/>',
            'download' => '<path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/>',
            'eye' => '<path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>',
            'lock' => '<path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>'
        ];
    }
    
    /**
     * 构建属性字符串
     * 
     * @param array $attributes 属性数组
     * @return string
     */
    private static function buildAttributes($attributes) {
        $parts = [];
        foreach ($attributes as $key => $value) {
            $parts[] = $key . '="' . htmlspecialchars($value) . '"';
        }
        return implode(' ', $parts);
    }
    
    /**
     * 渲染图标按钮
     * 
     * @param string $name 图标名称
     * @param string $size 图标尺寸
     * @param string $color 图标颜色
     * @param array $options 选项（href, onclick, class等）
     * @return string
     */
    public static function button($name, $size = 'md', $color = '', $options = []) {
        $icon = self::render($name, $size, $color);
        $href = isset($options['href']) ? $options['href'] : '#';
        $onclick = isset($options['onclick']) ? ' onclick="' . htmlspecialchars($options['onclick']) . '"' : '';
        $class = 'icon-btn' . (isset($options['class']) ? ' ' . $options['class'] : '');
        $title = isset($options['title']) ? ' title="' . htmlspecialchars($options['title']) . '"' : '';
        
        return '<a href="' . htmlspecialchars($href) . '" class="' . $class . '"' . $onclick . $title . '>' . $icon . '</a>';
    }
}
