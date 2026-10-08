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


// 包含模型类

class RSSController {
    public function feed() {
        // 检查RSS订阅是否启用
        if (!Config::get('rss_enabled', 1)) {
            header('HTTP/1.0 404 Not Found');
            exit;
        }

        // 设置响应头为XML格式
        header('Content-Type: application/rss+xml; charset=utf-8');

        // 读取RSS配置项
        $rssItemCount = (int)Config::get('rss_item_count', 10);
        $rssCacheDuration = (int)Config::get('rss_cache_duration', 3600);
        $rssFeedType = Config::get('rss_feed_type', 'excerpt');
        
        // 尝试从缓存获取
        $cacheFile = STORAGE_PATH . '/cache/rss_feed.xml';
        
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $rssCacheDuration)) {
            // 缓存有效，直接输出缓存内容
            readfile($cacheFile);
            exit;
        }
        
        // 使用模型类获取文章数据
        $articleModel = new ArticleModel();
        
        // 获取最新文章（数量由配置决定）
        $articles = $articleModel->getLatestArticles($rssItemCount);
        
        // 批量获取所有文章的标签（消除 N+1 查询）
        if (!empty($articles)) {
            $ids = array_column($articles, 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $db = Database::getInstance();
            $prefix = Config::get('database')['prefix'];
            
            $tagRows = $db->fetchAll(
                "SELECT at.article_id, t.id, t.name 
                 FROM {$prefix}article_tag at 
                 JOIN {$prefix}tag t ON at.tag_id = t.id 
                 WHERE at.article_id IN ($placeholders)",
                $ids, true
            );
            $tagsMap = [];
            foreach ($tagRows as $row) {
                $tagsMap[$row['article_id']][] = ['id' => $row['id'], 'name' => $row['name']];
            }
            
            // 批量获取所有涉及分类的详细信息
            $categoryIds = array_unique(array_column($articles, 'category_id'));
            $catPlaceholders = implode(',', array_fill(0, count($categoryIds), '?'));
            $categoryRows = $db->fetchAll(
                "SELECT * FROM {$prefix}category WHERE id IN ($catPlaceholders)",
                $categoryIds, true
            );
            $categoriesMap = [];
            foreach ($categoryRows as $row) {
                $categoriesMap[$row['id']] = $row;
            }
        }
        
        // 获取网站信息
        $siteName = Config::get('site_name', 'My Blog');
        $siteDescription = Config::get('site_description', '');
        $siteUrl = $this->getSiteUrl();
        
        // 创建RSS 2.0 XML
        $xml = '<?xml version="1.0" encoding="UTF-8" ?>
';
        $xml .= '<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/">
';
        $xml .= '<channel>
';
        $xml .= '<title>' . htmlspecialchars($siteName) . '</title>
';
        $xml .= '<link>' . htmlspecialchars($siteUrl) . '</link>
';
        $xml .= '<description>' . htmlspecialchars($siteDescription) . '</description>
';
        $xml .= '<language>' . htmlspecialchars(Config::get('rss_language', 'zh-CN')) . '</language>
';
        $xml .= '<pubDate>' . date('r') . '</pubDate>
';
        $xml .= '<lastBuildDate>' . date('r') . '</lastBuildDate>
';
        $versionInfo = include CORE_PATH . '/config/version.php';
        $xml .= '<generator>' . htmlspecialchars($versionInfo['name'] . ' ' . $versionInfo['version']) . '</generator>
';
        
        // 添加文章项
        foreach ($articles as $article) {
            // 使用批量预取的分类和标签数据
            $category = isset($categoriesMap[$article['category_id']]) ? $categoriesMap[$article['category_id']] : null;
            $tags = isset($tagsMap[$article['id']]) ? $tagsMap[$article['id']] : [];
            
            // 生成文章链接
            $articleUrl = $this->getArticleUrl($article);
            
            // 准备文章描述
            if ($rssFeedType === 'full') {
                $description = '<![CDATA[' . $article['content'] . ']]>';
            } else {
                $description = !empty($article['excerpt']) ? $article['excerpt'] : strip_tags($article['content']);
                $description = substr($description, 0, 500); // 限制描述长度
                $description = htmlspecialchars($description);
            }
            
            // 准备发布日期
            $pubDate = date('r', $article['created_at']);
            
            $xml .= '<item>
';
            $xml .= '<title>' . htmlspecialchars($article['title']) . '</title>
';
            $xml .= '<link>' . htmlspecialchars($articleUrl) . '</link>
';
            $xml .= '<description>' . $description . '</description>
';
            $xml .= '<pubDate>' . $pubDate . '</pubDate>
';
            $xml .= '<dc:creator>' . htmlspecialchars($article['author']) . '</dc:creator>
';
            
            // 添加分类
            if ($category) {
                $categoryUrl = $this->getCategoryUrl($category);
                $xml .= '<category domain="' . htmlspecialchars($categoryUrl) . '">' . htmlspecialchars($category['name']) . '</category>
';
            }
            
            // 添加标签
            foreach ($tags as $tag) {
                $tagUrl = $this->getTagUrl($tag);
                $xml .= '<category domain="' . htmlspecialchars($tagUrl) . '">' . htmlspecialchars($tag['name']) . '</category>
';
            }
            
            $xml .= '<guid isPermaLink="true">' . htmlspecialchars($articleUrl) . '</guid>
';
            $xml .= '</item>
';
        }
        
        $xml .= '</channel>
';
        $xml .= '</rss>';
        
        // 确保缓存目录存在
        if (!is_dir(STORAGE_PATH . '/cache')) {
            mkdir(STORAGE_PATH . '/cache', 0755, true);
        }
        
        // 写入缓存
        file_put_contents($cacheFile, $xml);
        
        // 输出XML
        echo $xml;
        exit;
    }
    
    private function getSiteUrl() {
        // 自动检测协议
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        
        // 自动检测域名
        $domain = $_SERVER['HTTP_HOST'];
        
        return $protocol . '://' . $domain;
    }
    
    private function getArticleUrl($article) {
        $siteUrl = $this->getSiteUrl();
        
        // 检查伪静态设置
        $rewriteEnabled = Config::get('rewrite_enabled', '0');
        
        if ($rewriteEnabled == '1') {
            // 使用伪静态URL
            $articleUrl = $siteUrl . '/article/' . $article['id'] . '-' . $article['slug'] . '.html';
        } else {
            // 使用动态URL
            $articleUrl = $siteUrl . '/index.php?action=home&method=article&id=' . $article['id'];
        }
        
        return $articleUrl;
    }
    
    private function getCategoryUrl($category) {
        $siteUrl = $this->getSiteUrl();
        
        // 检查伪静态设置
        $rewriteEnabled = Config::get('rewrite_enabled', '0');
        
        if ($rewriteEnabled == '1') {
            // 使用伪静态URL
            $categoryUrl = $siteUrl . '/category/' . $category['id'] . '-' . $category['slug'] . '.html';
        } else {
            // 使用动态URL
            $categoryUrl = $siteUrl . '/index.php?action=home&method=category&id=' . $category['id'];
        }
        
        return $categoryUrl;
    }
    
    private function getTagUrl($tag) {
        $siteUrl = $this->getSiteUrl();
        
        // 检查伪静态设置
        $rewriteEnabled = Config::get('rewrite_enabled', '0');
        
        if ($rewriteEnabled == '1') {
            // 使用伪静态URL
            $tagUrl = $siteUrl . '/tag/' . $tag['id'] . '-' . $tag['slug'] . '.html';
        } else {
            // 使用动态URL
            $tagUrl = $siteUrl . '/index.php?action=home&method=tag&id=' . $tag['id'];
        }
        
        return $tagUrl;
    }
}
