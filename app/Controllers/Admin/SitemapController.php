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
 * 站点地图控制器
 * 从 ConfigController 拆分，独立负责站点地图生成（原 generateSitemap 方法）
 */
class SitemapController {

    /**
     * 生成站点地图（POST 请求，返回 JSON）
     *
     * 主要流程：
     * 1. 读取站点地图相关配置（sitemap_enabled / sitemap_filename / sitemap_include_*）
     * 2. 读取开启/关闭的内容类型，首页始终加入
     * 3. 按实体类型（文章/分类/页面/标签）查询数据库，根据 rewrite_enabled 决定 URL 格式
     * 4. 组装 sitemap XML
     * 5. 写入 ROOT_PATH / <sitemap_filename>
     */
    public function generate() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: admin.php?action=config&sub=seo');
            exit;
        }

        header('Content-Type: application/json');
        // 防止任何先前的输出（如 notice/warning）污染 JSON
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();

        try {
            $db = Database::getInstance();
            $dbConfig = Config::get('database');
            $prefix = $dbConfig['prefix'] ?? '';

            // —— 1. 读取所需配置（明确查询，不再依赖单一 LIKE） ——
            $rawConfigs = $db->fetchAll(
                "SELECT name, value FROM {$prefix}config WHERE name IN ("
                . "'sitemap_enabled','sitemap_filename',"
                . "'sitemap_include_home','sitemap_include_articles',"
                . "'sitemap_include_categories','sitemap_include_pages','sitemap_include_tags',"
                . "'rewrite_enabled','rewrite_article','rewrite_category',"
                . "'rewrite_page','rewrite_tag','site.url'"
                . ")"
            );

            $configs = [];
            foreach ($rawConfigs as $row) {
                if (isset($row['name'])) {
                    $configs[$row['name']] = $row['value'];
                }
            }

            // —— 2. 解析包含哪些类型（首页默认加入，可通过 sitemap_include_home 关闭） ——
            $includeHome = !isset($configs['sitemap_include_home']) || (int)$configs['sitemap_include_home'] === 1;
            $includeTypes = [];
            if (!empty($configs['sitemap_include_articles'])) $includeTypes[] = 'articles';
            if (!empty($configs['sitemap_include_categories'])) $includeTypes[] = 'categories';
            if (!empty($configs['sitemap_include_pages'])) $includeTypes[] = 'pages';
            if (!empty($configs['sitemap_include_tags'])) $includeTypes[] = 'tags';

            // 向后兼容：若用户数据库尚无各类型开关，默认全部启用
            if (empty($includeTypes)) {
                $includeTypes = ['articles', 'categories', 'pages', 'tags'];
            }

            // —— 3. 站点域名 / URL 前缀 ——
            $siteUrl = Config::get('site.url');
            if (empty($siteUrl)) {
                $siteUrl = $configs['site.url'] ?? '';
            }
            if (empty($siteUrl)) {
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
                $siteUrl = $protocol . '://' . $host;
            }
            $siteUrl = rtrim($siteUrl, '/');

            // —— 4. 伪静态启用状态 + 各类型 URL 模板 ——
            $rewriteEnabled = !empty($configs['rewrite_enabled']) ? (int)$configs['rewrite_enabled'] : 0;
            $rewriteArticle  = !empty($configs['rewrite_article'])  ? $configs['rewrite_article']  : 'article/{id}';
            $rewriteCategory = !empty($configs['rewrite_category']) ? $configs['rewrite_category'] : 'category/{id}';
            $rewritePage     = !empty($configs['rewrite_page'])     ? $configs['rewrite_page']     : 'page/{id}';
            $rewriteTag      = !empty($configs['rewrite_tag'])      ? $configs['rewrite_tag']      : 'tag/{id}';

            // —— 5. 构建 XML ——
            $sitemapContent = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            $sitemapContent .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

            // 首页
            if ($includeHome) {
                $sitemapContent .= "    <url>\n"
                    . "        <loc>" . htmlspecialchars($siteUrl . '/') . "</loc>\n"
                    . "        <changefreq>daily</changefreq>\n"
                    . "        <priority>1.0</priority>\n"
                    . "    </url>\n";
            }

            // —— 6. 循环写入各类实体 URL ——
            if (in_array('articles', $includeTypes, true)) {
                $articles = $db->fetchAll(
                    "SELECT id, updated_at FROM {$prefix}article WHERE status = 1 ORDER BY id DESC"
                );
                foreach ($articles as $article) {
                    $articleUrl = $rewriteEnabled
                        ? $siteUrl . '/' . str_replace('{id}', (int)$article['id'], $rewriteArticle)
                        : $siteUrl . '/index.php/article/' . (int)$article['id'];
                    $lastMod = !empty($article['updated_at']) ? date('Y-m-d', (int)$article['updated_at']) : date('Y-m-d');
                    $sitemapContent .= "    <url>\n"
                        . "        <loc>" . htmlspecialchars($articleUrl) . "</loc>\n"
                        . "        <lastmod>" . $lastMod . "</lastmod>\n"
                        . "        <changefreq>weekly</changefreq>\n"
                        . "        <priority>0.8</priority>\n"
                        . "    </url>\n";
                }
            }

            if (in_array('categories', $includeTypes, true)) {
                $categories = $db->fetchAll(
                    "SELECT id FROM {$prefix}category ORDER BY id DESC"
                );
                foreach ($categories as $category) {
                    $catUrl = $rewriteEnabled
                        ? $siteUrl . '/' . str_replace('{id}', (int)$category['id'], $rewriteCategory)
                        : $siteUrl . '/index.php/category/' . (int)$category['id'];
                    $sitemapContent .= "    <url>\n"
                        . "        <loc>" . htmlspecialchars($catUrl) . "</loc>\n"
                        . "        <changefreq>weekly</changefreq>\n"
                        . "        <priority>0.7</priority>\n"
                        . "    </url>\n";
                }
            }

            if (in_array('pages', $includeTypes, true)) {
                $pages = $db->fetchAll(
                    "SELECT id, updated_at FROM {$prefix}page WHERE status = 1 ORDER BY id DESC"
                );
                foreach ($pages as $page) {
                    $pageUrl = $rewriteEnabled
                        ? $siteUrl . '/' . str_replace('{id}', (int)$page['id'], $rewritePage)
                        : $siteUrl . '/index.php/page/' . (int)$page['id'];
                    $lastMod = !empty($page['updated_at']) ? date('Y-m-d', (int)$page['updated_at']) : date('Y-m-d');
                    $sitemapContent .= "    <url>\n"
                        . "        <loc>" . htmlspecialchars($pageUrl) . "</loc>\n"
                        . "        <lastmod>" . $lastMod . "</lastmod>\n"
                        . "        <changefreq>monthly</changefreq>\n"
                        . "        <priority>0.6</priority>\n"
                        . "    </url>\n";
                }
            }

            if (in_array('tags', $includeTypes, true)) {
                $tags = $db->fetchAll(
                    "SELECT id FROM {$prefix}tag ORDER BY id DESC"
                );
                foreach ($tags as $tag) {
                    $tagUrl = $rewriteEnabled
                        ? $siteUrl . '/' . str_replace('{id}', (int)$tag['id'], $rewriteTag)
                        : $siteUrl . '/index.php/tag/' . (int)$tag['id'];
                    $sitemapContent .= "    <url>\n"
                        . "        <loc>" . htmlspecialchars($tagUrl) . "</loc>\n"
                        . "        <changefreq>monthly</changefreq>\n"
                        . "        <priority>0.5</priority>\n"
                        . "    </url>\n";
                }
            }

            $sitemapContent .= '</urlset>';

            // 丢弃任何意外的输出，确保 JSON 干净
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            // —— 7. 写入文件 ——
            $sitemapFilename = !empty($configs['sitemap_filename']) ? trim($configs['sitemap_filename']) : 'sitemap.xml';
            $sitemapFilename = basename($sitemapFilename); // 防止目录穿越
            if ($sitemapFilename === '' || $sitemapFilename === '0') {
                $sitemapFilename = 'sitemap.xml';
            }
            $sitemapPath = rtrim(str_replace('\\', '/', ROOT_PATH), '/') . '/' . $sitemapFilename;

            $targetDir = dirname($sitemapPath);
            if (!is_dir($targetDir) || !is_writable($targetDir)) {
                echo json_encode([
                    'success' => false,
                    'message' => '站点地图生成失败，目录无写入权限（' . htmlspecialchars($targetDir) . '）',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $result = @file_put_contents($sitemapPath, $sitemapContent);
            if ($result !== false) {
                require_once CORE_PATH . '/lib/Log.php';
                Log::init();
                Log::info('手动生成站点地图', 'system', [
                    'filename' => $sitemapFilename,
                    'path'     => $sitemapPath,
                    'bytes'    => $result,
                ]);
                echo json_encode([
                    'success'  => true,
                    'message'  => '站点地图生成成功！',
                    'filename' => $sitemapFilename,
                    'url'      => $siteUrl . '/' . $sitemapFilename,
                    'path'     => $sitemapPath,
                ], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => '站点地图生成失败，无法写入文件（' . htmlspecialchars($sitemapPath) . '）',
                ], JSON_UNESCAPED_UNICODE);
            }
        } catch (Throwable $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            // 记录详细错误，便于排查
            error_log('[SitemapController::generate] ' . $e->getMessage() . ' (' . $e->getFile() . ':' . $e->getLine() . ')');
            echo json_encode([
                'success' => false,
                'message' => '站点地图生成失败：' . $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
}
