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
 * BlogKit WebP 辅助函数
 * 
 * 用于模板中输出 <picture> 标签，自动检测 WebP 副本是否存在并生成相应 HTML
 */
class WebPHelper
{
    /**
     * 生成 <picture> 标签，浏览器支持 WebP 时自动加载 .webp 版本
     * 
     * @param string $imagePath 原始图片相对路径，如 /uploads/articles/2025/01/xxx.jpg
     * @param string $alt       图片 alt 属性
     * @param string $class     额外的 CSS 类名
     * @param bool   $lazy      是否启用懒加载
     * @return string           完整的 <picture> HTML
     */
    public static function picture($imagePath, $alt = '', $class = '', $lazy = true)
    {
        if (empty($imagePath)) {
            return '';
        }

        $pathInfo = pathinfo($imagePath);
        $extension = strtolower($pathInfo['extension'] ?? '');

        // GIF 和 WebP 本身不生成 WebP 副本
        $webpExtensions = ['jpg', 'jpeg', 'png'];
        $webpPath = '';

        if (in_array($extension, $webpExtensions)) {
            $webpPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.webp';
            // 检查 WebP 文件是否存在
            if (defined('ROOT_PATH')) {
                $fullPath = ROOT_PATH . $webpPath;
            } else {
                $fullPath = $_SERVER['DOCUMENT_ROOT'] . $webpPath;
            }
            if (!file_exists($fullPath)) {
                $webpPath = ''; // WebP 不存在，不使用
            }
        }

        $loadingAttr = $lazy ? ' loading="lazy"' : '';
        $classAttr = $class ? ' class="' . htmlspecialchars($class) . '"' : '';
        $altAttr = ' alt="' . htmlspecialchars($alt) . '"';

        // SVG 占位图 - 浅灰色背景（在光模式下）/深灰色背景（在暗模式下）
        // 使用 CSS 变量让它能适应主题
        $placeholder = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"%3E%3Crect width="1" height="1" fill="%23f3f4f6"%3E%3C/rect%3E%3C/svg%3E';

        if ($webpPath) {
            return '<picture>'
                . '<source data-srcset="' . htmlspecialchars($webpPath) . '" type="image/webp">'
                . '<img src="' . $placeholder . '" data-src="' . htmlspecialchars($imagePath) . '"' . $altAttr . $classAttr . $loadingAttr . '>'
                . '</picture>';
        }

        return '<img src="' . $placeholder . '" data-src="' . htmlspecialchars($imagePath) . '"' . $altAttr . $classAttr . $loadingAttr . '>';
    }

    /**
     * 将 HTML 内容中的 <img> 标签自动转换为 <picture> 标签（支持 WebP）
     * 适用于文章正文等包含多张图片的 HTML 内容
     *
     * @param string $html HTML 内容
     * @return string      处理后的 HTML
     */
    public static function processContent($html)
    {
        if (empty($html)) {
            return $html;
        }

        return preg_replace_callback(
            '/<img\s+([^>]*?)\bsrc=["\']([^"\']+\.(?:jpg|jpeg|png))["\']([^>]*?)>/i',
            function ($matches) {
                $before = $matches[1];  // src 之前的属性
                $imagePath = $matches[2];
                $after = $matches[3];   // src 之后的属性

                $pathInfo = pathinfo($imagePath);
                $webpPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.webp';

                // 检查 WebP 文件是否存在
                if (defined('ROOT_PATH')) {
                    $fullPath = ROOT_PATH . $webpPath;
                } else {
                    $fullPath = $_SERVER['DOCUMENT_ROOT'] . $webpPath;
                }

                if (!file_exists($fullPath)) {
                    // WebP 不存在，保留原标签
                    return $matches[0];
                }

                // 生成 <picture> 标签，保留原有属性
                return '<picture>'
                    . '<source srcset="' . htmlspecialchars($webpPath) . '" type="image/webp">'
                    . '<img ' . $before . ' src="' . htmlspecialchars($imagePath) . '" ' . $after . '>'
                    . '</picture>';
            },
            $html
        );
    }
}
