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
 * BlogKit 模板URL生成组件
 * 
 * 从 Template.php 中提取的URL生成系统：
 *   - 文章/分类/标签/页面 URL
 *   - 登录/注册/登出/管理后台 URL
 *   - 伪静态重写规则支持
 *   - 带redirect参数的URL
 *   - 支持slug优先的分类URL
 * 
 * @package BlogKit
 * @since 2.2.0
 */
class TemplateUrl
{
    /**
     * @var Template 模板引擎主实例引用
     */
    private $template;

    /**
     * 构造函数
     * 
     * @param Template $template 模板引擎实例
     */
    public function __construct($template)
    {
        $this->template = $template;
    }

    /**
     * 生成URL
     * @param string $type URL类型（article/category/tag/page/login/register/logout/admin/dashboard/index/home）
     * @param mixed $item 数据项（可选）
     * @param string $paramName 参数名（可选）
     * @return string 生成的URL
     */
    public function generate($type, $item = null, $paramName = '')
    {
        $siteConfig = $this->template->getSiteConfig();
        $siteUrl = $siteConfig['url'];
        $rewriteEnabled = Config::get('rewrite.enabled', false);
        $indexPrefix = $rewriteEnabled ? '' : '/index.php';

        // === API 类型的特殊处理：API 入口是独立的 api.php 文件 ===
        // 正常 URL 类型会拼接成 /index.php/{type}，但 API 必须使用
        // /api.php/v1 作为前缀，因为 ApiRouter 只在 api.php 中注册
        if ($type === 'api') {
            return $siteUrl . '/api.php/v1';
        }

        // 第一步：根据类型获取数据上下文（用于填充占位符）
        $context = $this->buildContext($type, $item, $paramName);

        // 分类跳转 URL（保持向后兼容）
        if ($type === 'category' && !empty($context['_redirect_url'])) {
            return $context['_redirect_url'];
        }

        // 第二步：获取该类型对应的重写规则配置键
        $configKey = $this->typeToConfigKey($type);

        // 第三步：获取用户自定义规则（如 "post/{slug}.html"），没有则使用默认规则
        $defaultPattern = $this->typeToDefaultPattern($type);
        $pattern = $rewriteEnabled
            ? Config::get($configKey, $defaultPattern)
            : $defaultPattern;

        // 第四步：从模式中提取所有 {xxx} 占位符并替换为实际值
        $finalPath = $this->replacePlaceholders($pattern, $context, $type);

        // 第五步：组合成完整 URL
        $path = trim($finalPath, '/');
        $url = $rewriteEnabled
            ? ($path === '' ? $siteUrl : $siteUrl . '/' . $path)
            : ($path === '' ? $siteUrl . $indexPrefix : $siteUrl . $indexPrefix . '/' . $path);

        // 第六步：对于 login/register/logout 等，可能需要附加 redirect 参数
        if ($paramName === 'redirect' && in_array($type, ['login', 'register', 'logout'])
            && isset($_SERVER['REQUEST_URI'])) {
            $requestUri = preg_replace('/[?&]redirect=[^&]*/', '', $_SERVER['REQUEST_URI']);
            $requestUri = preg_replace('/\?$/', '', $requestUri);
            $currentUrl = $siteUrl . $requestUri;
            $url .= (strpos($url, '?') === false ? '?' : '&') . 'redirect=' . urlencode($currentUrl);
        }

        return $url;
    }

    /**
     * 将 URL 类型映射到后台配置键名
     */
    private function typeToConfigKey($type)
    {
        $map = [
            'article'               => 'rewrite_article',
            'category'              => 'rewrite_category',
            'tag'                   => 'rewrite_tag',
            'tags'                  => 'rewrite_tags',
            'archives'             => 'rewrite_archives',
            'page'                  => 'rewrite_page',
            'login'                 => 'rewrite_login',
            'register'              => 'rewrite_register',
            'logout'                => 'rewrite_logout',
            'profile'               => 'rewrite_profile',
            'rss'                   => 'rewrite_rss',
            'forgot_password'       => 'rewrite_forgot_password',
            'reset_password'        => 'rewrite_reset_password',
            'user'                  => 'rewrite_user',
            'follow'                => 'rewrite_follow',
            'following'             => 'rewrite_following',
            'followers'             => 'rewrite_followers',
            'profile_articles'      => 'rewrite_profile_articles',
            'profile_comments'      => 'rewrite_profile_comments',
            'profile_settings'      => 'rewrite_profile_settings',
            'profile_favorites'     => 'rewrite_profile_favorites',
            'profile_likes'         => 'rewrite_profile_likes',
            'profile_notifications' => 'rewrite_profile_notifications',
            'profile_history'       => 'rewrite_profile_history',
            'profile_following'      => 'rewrite_profile_following',
            'profile_followers'      => 'rewrite_profile_followers',
            'search'                => 'rewrite_search',
        ];
        return $map[$type] ?? 'rewrite_' . $type;
    }

    /**
     * 给出某类型的默认 URL 规则（当用户没有自定义时使用）
     */
    private function typeToDefaultPattern($type)
    {
        $defaults = [
            'article'               => 'article/{slug}',  // 有别名时用别名，否则回退到 ID
            'category'              => 'category/{id}',   // {id} 在分类有 slug 时会被 slug 替换（_use_slug_as_id）
            'tag'                   => 'tag/{id}',
            'tags'                  => 'tags',
            'archives'             => 'archives',
            'page'                  => 'page/{slug}',     // 有别名时用别名，否则回退到 ID
            'login'                 => 'login',
            'register'              => 'register',
            'logout'                => 'logout',
            'profile'               => 'profile',
            'rss'                   => 'rss',
            'forgot_password'       => 'forgot-password',
            'reset_password'        => 'reset-password',
            'user'                  => 'user/{id}',
            'follow'                => 'follow/{id}',
            'following'             => 'following/{id}',
            'followers'             => 'followers/{id}',
            'profile_articles'      => 'profile/articles',
            'profile_comments'      => 'profile/comments',
            'profile_settings'      => 'profile/settings',
            'profile_favorites'     => 'profile/favorites',
            'profile_likes'         => 'profile/likes',
            'profile_notifications' => 'profile/notifications',
            'profile_history'       => 'profile/history',
            'profile_following'     => 'profile/following',
            'profile_followers'     => 'profile/followers',
            'search'                => 'search',
        ];
        return $defaults[$type] ?? $type;
    }

    /**
     * 构建上下文数组：从多个数据源（$item / 当前循环项 / $data）中提取可用字段
     * 用于填充 {id}, {slug}, {name}, {year}, {month}, {day} 等占位符
     *
     * 注意：当调用方已经显式传入 $item 或处于循环中（有当前循环项）时，
     * 全局 $data[$type] 仅作为回退补充，并且 **绝不覆盖已有的 id/slug** ——
     * 否则会出现"渲染某个无 slug 页面的导航链接时，slug 被当前访问页面污染"的问题。
     */
    private function buildContext($type, $item, $paramName)
    {
        $loopItem = $this->template->getCurrentLoopItem();
        $data = $this->template->getData();
        $context = [];

        // —— 已解析值模式检测：当 paramName 看起来不是"字段名"而是"实际值"时，
        //    例如：纯数字 999、或字符串 slug-value（不在任何数据源中作为字段存在），
        //    这时应视 paramName 为 id/slug 的直接值，不再从 item/loopItem 提取字段，
        //    防止被无关上下文（如当前文章的 loopItem）的 slug 污染。
        $paramIsResolvedValue = false;
        if ($paramName !== '' && $paramName !== null && $paramName !== '0') {
            if (is_numeric($paramName)) {
                $paramIsResolvedValue = true;
            } elseif (is_string($paramName)) {
                // 判断 paramName 是否是某个数据源中的"字段名"。
                // 注意字段查找范围包括：
                //   - item / loopItem 的所有键（如 article 中的 id/slug/category_id 等）
                //   - data 的顶层键
                //   - data[$type] 的键（例如 data['category']['slug']）
                $hasFieldInItem = ($item && is_array($item)) && isset($item[$paramName]);
                $hasFieldInLoop = $loopItem && isset($loopItem[$paramName]);
                $hasFieldInDataTop = is_array($data) && array_key_exists($paramName, $data);
                $hasFieldInDataTyped = (is_array($data) && !empty($data[$type]) && is_array($data[$type])
                    && array_key_exists($paramName, $data[$type]));
                if (!$hasFieldInItem && !$hasFieldInLoop && !$hasFieldInDataTop && !$hasFieldInDataTyped) {
                    $paramIsResolvedValue = true;
                }
            }
        }

        // 1. 确定目标实体可用的字段白名单。
        //    当 $item 是"另一个实体"（例如文章列表中调用 {url:category:...}）
        //    时，$item 中的 id/slug 属于文章，不能被当作分类/标签的 id/slug。
        //    通过白名单只提取对目标类型有意义的字段。
        //    （在"已解析值"模式下跳过这一步，避免上下文被 loopItem 污染）
        $primaryFields = $this->getTypePrimaryFields($type);

        // 候选数据来源（优先级：显式 item > 循环项）
        $primaryCandidates = [];
        if (!$paramIsResolvedValue) {
            if ($item && is_array($item)) $primaryCandidates[] = $item;
            if ($loopItem) $primaryCandidates[] = $loopItem;
        }

        // 从"主数据源"中按白名单提取字段
        foreach ($primaryCandidates as $c) {
            foreach ($primaryFields as $field) {
                if (!isset($context[$field])
                    && isset($c[$field])
                    && $c[$field] !== ''
                    && $c[$field] !== null) {
                    $context[$field] = $c[$field];
                }
            }
        }

        // 2. 全局 data[$type] 仅作为回退，
        //    且只有当"主数据源都没提供"时才使用；
        //    当主数据源已经提供了 id 或 slug 时，绝不再从 data[$type] 里捡字段，
        //    防止循环渲染多个 page 时被当前访问页面污染。
        $hasPrimarySource = !empty($primaryCandidates);
        $fallbackCandidates = [];
        if (!$hasPrimarySource && !$paramIsResolvedValue) {
            if (!empty($data[$type])) $fallbackCandidates[] = $data[$type];
            if ($type === 'article' && !empty($data['article'])) $fallbackCandidates[] = $data['article'];
            if ($type === 'category' && !empty($data['category'])) $fallbackCandidates[] = $data['category'];
        }

        foreach ($fallbackCandidates as $c) {
            foreach ($primaryFields as $field) {
                if (!isset($context[$field])
                    && isset($c[$field])
                    && $c[$field] !== ''
                    && $c[$field] !== null) {
                    $context[$field] = $c[$field];
                }
            }
        }

        // 3. 按类型将"从 item 中读取到的别名字段"映射到 id / slug。
        //    已解析值模式下：直接把 paramName 当作 id/slug。
        if ($paramIsResolvedValue) {
            if (is_numeric($paramName)) {
                $context['id'] = $paramName + 0;
            } else {
                // 字符串型解析值（通常是 slug）—— 作为 slug 写入
                $context['slug'] = $paramName;
            }
        } elseif ($type === 'category') {
            if (!empty($context['category_id'])) {
                // 明确指定的分类 id（来自文章 item 的 category_id 外键）
                $context['id'] = $context['category_id'];
            }
            if (!empty($context['category_slug'])) {
                $context['slug'] = $context['category_slug'];
            }
            // 分类 redirect_url 兼容处理
            $redirectUrl = $this->resolveRedirectUrl($item);
            if ($redirectUrl) {
                $context['_redirect_url'] = $redirectUrl;
            }
            if (empty($context['id'])) {
                $context['id'] = $this->resolveId($type, $item, $paramName);
            }
            // 分类有 slug 时，{id} 会被替换为 slug（保持向后兼容）
            $slug = $this->resolveSlugForType($type, $context, $item);
            if ($slug) {
                $context['slug'] = $slug;
                $context['_use_slug_as_id'] = true;
            }
        } elseif ($type === 'tag') {
            if (!empty($context['tag_id'])) {
                $context['id'] = $context['tag_id'];
            }
            if (!empty($context['tag_slug'])) {
                $context['slug'] = $context['tag_slug'];
            }
            if (empty($context['id'])) {
                $context['id'] = $this->resolveId($type, $item, $paramName);
            }
            $slug = $this->resolveSlugForType($type, $context, $item);
            if ($slug) {
                $context['slug'] = $slug;
                $context['_use_slug_as_id'] = true;
            }
        } elseif ($type === 'article' || $type === 'page') {
            // 文章/页面类型：
            // - 如果 item 有 article_id / post_id（例如：历史记录、点赞的评论等），
            //   这是"外键引用"场景，item 的 id 不是目标实体的 id，
            //   需用 article_id / post_id 作为 id。
            // - 如果 item 就是文章/页面本身（例如文章列表），则 item.id 就是目标 id，
            //   保持不变。
            if (!empty($context['article_id'])) {
                $context['id'] = $context['article_id'];
            } elseif (!empty($context['post_id'])) {
                $context['id'] = $context['post_id'];
            }
            if (empty($context['id'])) {
                $context['id'] = $this->resolveId($type, $item, $paramName);
            }

            // —— slug 的可信度判断 ——
            // 只有当 item 真正是文章/页面实体时，item.slug 才被当作文章 slug。
            // 这里用"item.id == context.id"来判断：如果 context.id 被改写为
            // article_id/post_id（外键场景），说明 item 不是文章实体，
            // 此时不能把 item.slug 当作文章 slug，只能把 context.slug 视为有效
            //（它来自白名单中真正的 slug，如果存在的话）。
            $itemIsTargetEntity = true;
            if ($item && is_array($item) && isset($item['id'])) {
                $itemId = (string)$item['id'];
                $ctxId  = (string)$context['id'];
                // 若 context.id 来自外键（article_id/post_id）且与 item.id 不同，
                // 说明 item 本身不是目标实体（如历史记录），item.slug 不可信。
                if ($itemId !== $ctxId) {
                    $itemIsTargetEntity = false;
                }
            }
            // 外键字段（article_id / post_id）存在，本身就是强信号：item 不是文章实体
            if (!empty($context['article_id']) || !empty($context['post_id'])) {
                $itemIsTargetEntity = false;
            }
            // 如果 item 不是文章实体，从 context 中移除"从 item 误提取的 slug"，
            // 只保留 context.slug 实际上为空（即没有 article_slug 字段）的情况，
            // 最终使 URL 回退到基于 id 的形式。
            if (!$itemIsTargetEntity) {
                // 检查 context.slug 是否真的是"文章的 slug"。
                // article/page 类型没有独立的 article_slug 字段，slug 本身就是
                // 文章的 slug。当 item 不是文章实体时，我们不应当使用 item 带的
                // slug 作为文章 slug。
                //
                // 但如果上下文真正从 item 拿到 slug（例如文章列表里，item.id==context.id），
                // 上面的 $itemIsTargetEntity 会是 true。这里仅处理 false 的情况：
                // 清掉 context.slug，让 URL 回退到 id 形式。
                if (isset($context['slug'])) {
                    $slugFromItem = false;
                    if ($item && is_array($item) && isset($item['slug'])
                        && (string)$item['slug'] === (string)$context['slug']) {
                        $slugFromItem = true;
                    }
                    if ($slugFromItem) {
                        unset($context['slug']);
                    }
                }
            }
            $context['_has_slug'] = !empty($context['slug']);
        } elseif ($type === 'user' || $type === 'author') {
            // 用户类型：外键引用场景，item 的 user_id / author_id 才是用户 id
            if (!empty($context['user_id'])) {
                $context['id'] = $context['user_id'];
            } elseif (!empty($context['author_id'])) {
                $context['id'] = $context['author_id'];
            }
            if (empty($context['id'])) {
                $context['id'] = $this->resolveId($type, $item, $paramName);
            }
        } else {
            // 其他类型：确保有 id
            if (empty($context['id'])) {
                $context['id'] = $this->resolveId($type, $item, $paramName);
            }
        }

        // 4. 处理日期：从 created_at 中提取 year/month/day + hour/minute/second/monthnum
        if (!empty($context['created_at'])) {
            $timestamp = is_numeric($context['created_at'])
                ? (int)$context['created_at']
                : strtotime($context['created_at']);
            if ($timestamp) {
                $context['year']  = date('Y', $timestamp);
                $context['month'] = date('m', $timestamp);
                $context['day']   = date('d', $timestamp);
                // 扩展：时分秒，供更细粒度的 URL 占位符使用
                $context['hour']   = date('H', $timestamp);
                $context['minute'] = date('i', $timestamp);
                $context['second'] = date('s', $timestamp);
                // monthnum = 无前导零的月份（类似 WordPress）
                $context['monthnum'] = date('n', $timestamp);
            }
        }

        // 5. 处理 {name}：从 name/title/username 推断
        if (!isset($context['name'])) {
            if (!empty($context['username'])) $context['name'] = $context['username'];
            elseif (!empty($context['title'])) $context['name'] = $context['title'];
        }

        // 6. 处理 {page}：用于分页
        if ($paramName && is_numeric($paramName) && $paramName > 0) {
            $context['page'] = $paramName;
        }

        // 7. 别名互赋值：为更多占位符提供可用值（title / postname / 文章id / 分类 / 标签 / 作者 / 浏览量）
        // 7.1 title 与 name 互为别名
        if (!isset($context['title']) && isset($context['name'])) {
            $context['title'] = $context['name'];
        }
        // 7.2 postname 作为 slug 的别名（用于兼容类 WordPress 的 {postname} 占位符）
        if (!isset($context['postname']) && isset($context['slug'])) {
            $context['postname'] = $context['slug'];
        }
        // 7.3 article_id / post_id 作为 id 的别名
        if (!isset($context['article_id']) && isset($context['id'])) {
            $context['article_id'] = $context['id'];
        }
        if (!isset($context['post_id']) && isset($context['id'])) {
            $context['post_id'] = $context['id'];
        }
        // 7.4 category_id / cat_id 互为别名
        if (!isset($context['cat_id']) && isset($context['category_id'])) {
            $context['cat_id'] = $context['category_id'];
        }
        if (!isset($context['category_id']) && isset($context['cat_id'])) {
            $context['category_id'] = $context['cat_id'];
        }
        // 7.5 category_slug / cat_slug / category 互为别名（slug 方向）
        foreach (['category_slug', 'cat_slug', 'category'] as $src) {
            if (isset($context[$src])) {
                foreach (['category_slug', 'cat_slug', 'category'] as $dst) {
                    if (!isset($context[$dst])) {
                        $context[$dst] = $context[$src];
                    }
                }
                break;
            }
        }
        // 7.6 tag_slug / tag 互为别名
        if (!isset($context['tag']) && isset($context['tag_slug'])) {
            $context['tag'] = $context['tag_slug'];
        }
        if (!isset($context['tag_slug']) && isset($context['tag'])) {
            $context['tag_slug'] = $context['tag'];
        }
        // 7.7 author_id / user_id 互为别名
        if (!isset($context['author_id']) && isset($context['user_id'])) {
            $context['author_id'] = $context['user_id'];
        }
        if (!isset($context['user_id']) && isset($context['author_id'])) {
            $context['user_id'] = $context['author_id'];
        }
        // 7.8 username / author_slug / nickname / author 互为别名
        foreach (['username', 'author_slug', 'nickname', 'author'] as $src) {
            if (isset($context[$src])) {
                foreach (['username', 'author_slug', 'nickname', 'author'] as $dst) {
                    if (!isset($context[$dst])) {
                        $context[$dst] = $context[$src];
                    }
                }
                break;
            }
        }
        // 7.9 views / view_count 互为别名
        if (!isset($context['views']) && isset($context['view_count'])) {
            $context['views'] = $context['view_count'];
        }
        if (!isset($context['view_count']) && isset($context['views'])) {
            $context['view_count'] = $context['views'];
        }

        return $context;
    }

    /**
     * 将 URL 模式中的 {xxx} 占位符替换为上下文中的实际值
     * 支持的占位符：{id}, {slug}, {name}, {page}, {year}, {month}, {day}
     */
    private function replacePlaceholders($pattern, $context, $type)
    {
        if (strpos($pattern, '{') === false) {
            return $pattern;  // 没有占位符，直接返回
        }

        // 提取所有 {xxx} 占位符
        if (!preg_match_all('/\{(\w+)\}/', $pattern, $matches)) {
            return $pattern;
        }
        $placeholders = $matches[1];

        $result = $pattern;
        foreach ($placeholders as $ph) {
            $value = null;

            if ($ph === 'id' && !empty($context['_use_slug_as_id'])) {
                // 分类的特殊处理：{id} 实际使用 slug
                $value = $context['slug'] ?? $context['id'] ?? null;
            } elseif ($ph === 'slug' && in_array($type, ['article', 'page'], true)) {
                // 文章/页面类型：{slug} 无值时回退到 id
                $value = !empty($context['slug']) ? $context['slug'] : $context['id'];
            } elseif (isset($context[$ph])) {
                $value = $context[$ph];
            }

            if ($value === null || $value === '' || $value === false) {
                // 无法解析的值：回退到数字 id（对于 id 类占位符）或保留原样
                if ($ph === 'id' && isset($context['id'])) {
                    $value = $context['id'];
                } elseif ($ph === 'page') {
                    // 没有页码时，移除 {page} 和前面的斜杠
                    $result = preg_replace('/\/?\{page\}/', '', $result);
                    continue;
                } else {
                    // 其他无法解析的占位符，移除该段避免 URL 出现 {xxx}
                    $result = preg_replace('/\/?\{' . preg_quote($ph, '/') . '\}/', '', $result);
                    continue;
                }
            }

            // URL 安全编码（但保留 / 字符，因为 slug 可能包含路径）
            $safeValue = is_numeric($value)
                ? (string)$value
                : rawurlencode($value);
            $result = str_replace('{' . $ph . '}', $safeValue, $result);
        }

        return trim($result, '/');
    }

    /**
     * 解析URL需要的id值
     * 策略：有显式 $item 或循环项时，只从它们取 id，防止被全局数据污染；
     *       没有主数据源时，才回退到全局 $data / 路由参数推断。
     */
    private function resolveId($type, $item, $paramName)
    {
        $defaultIdField = ($type === 'tag') ? 'tag_id' : 'id';
        $loopItem = $this->template->getCurrentLoopItem();

        $hasPrimarySource = ($item && is_array($item)) || $loopItem;

        // —— 已解析值的快捷判断：如果 paramName 是纯数字（例如 999），
        //    则它是嵌套变量解析后的实际值，直接当作 ID 使用。
        //    此外，如果 paramName 既不是 item/loopItem/data 的键，也不是
        //    常见字段名（id/slug/category_id 等），也视为"已解析好的实际值"。
        if ($paramName !== '' && $paramName !== null && $paramName !== '0') {
            if (is_numeric($paramName)) {
                // 纯数字：paramName 本身就是 id 值
                return $paramName + 0;
            }
            // 字符串型 param（例如 'category_id'、'id'）：
            //   - 如果在 item/loopItem/data(顶层)/data[$type](嵌套) 中存在对应字段，则取字段值；
            //   - 否则视为已解析好的值（例如 slug 字符串）。
            if (is_string($paramName)) {
                $hasFieldInItem    = ($item && is_array($item)) && isset($item[$paramName]);
                $hasFieldInLoop    = $loopItem && isset($loopItem[$paramName]);
                $data              = $this->template->getData();
                $hasFieldInDataTop = is_array($data) && array_key_exists($paramName, $data);
                $hasFieldInDataTyped = (is_array($data) && !empty($data[$type]) && is_array($data[$type])
                    && array_key_exists($paramName, $data[$type]));

                if ($hasFieldInItem || $hasFieldInLoop || $hasFieldInDataTop || $hasFieldInDataTyped) {
                    // 存在对应字段 —— 走原有的 lookup 逻辑
                } else {
                    // paramName 是已解析好的值（例如 slug 字符串），
                    // 直接返回以便上层把它当作 id/slug 值使用。
                    return $paramName;
                }
            }
        }

        if ($paramName) {
            // 优先从提供的item中获取
            if ($item && is_array($item) && isset($item[$paramName])) {
                return $item[$paramName];
            } elseif ($loopItem && isset($loopItem[$paramName])) {
                return $loopItem[$paramName];
            } elseif ($hasPrimarySource) {
                // 主数据源未提供该字段时，不回退到全局数据
                return 0;
            }
            // 以下分支仅在"无主数据源"时才执行
            $data = $this->template->getData();
            if (isset($data[$paramName])) {
                return $data[$paramName];
            } elseif ($type === 'category' && !empty($data['category']['id'])) {
                // 侧边栏/详情页中分类链接：从全局分类实体中取 id（无 slug 时回退到 id 访问）
                return $data['category']['id'];
            } elseif ($type === 'tag' && !empty($data['tag']['tag_id'])) {
                return $data['tag']['tag_id'];
            } elseif ($type === 'tag' && !empty($data['tag']['id'])) {
                return $data['tag']['id'];
            } elseif ($type === 'category' && isset($data['article']['category_id'])) {
                return $data['article']['category_id'];
            } elseif ($paramName === 'id' && $item && is_array($item) && isset($item['id'])) {
                return $item['id'];
            } else {
                return $paramName;
            }
        } else {
            if ($loopItem && isset($loopItem[$defaultIdField])) {
                return $loopItem[$defaultIdField];
            } elseif ($item && is_array($item) && isset($item[$defaultIdField])) {
                return $item[$defaultIdField];
            } elseif ($hasPrimarySource) {
                return 0;
            }
            // 以下分支仅在"无主数据源"时才执行
            $data = $this->template->getData();
            if (isset($data[$defaultIdField])) {
                return $data[$defaultIdField];
            } elseif ($type === 'category' && !empty($data['category']['id'])) {
                return $data['category']['id'];
            } elseif ($type === 'tag' && !empty($data['tag']['tag_id'])) {
                return $data['tag']['tag_id'];
            } elseif ($type === 'tag' && !empty($data['tag']['id'])) {
                return $data['tag']['id'];
            } elseif ($type === 'category' && isset($data['article']['category_id'])) {
                return $data['article']['category_id'];
            } elseif ($type === 'article' && isset($data['article']['id'])) {
                return $data['article']['id'];
            }
        }

        return 0;
    }

    /**
     * 按类型返回"可安全从当前 item 中读取的字段白名单"。
     * 防止文章列表中调用 {url:category:...} 时把文章的 id/slug 当作分类的 id/slug。
     *
     * 关键原则：category / tag 的白名单中**不包含**裸的 `id` / `slug`，
     * 因为在 item = 文章的场景下，这些字段属于文章而非目标分类/标签。
     * 正确的 id/slug 来源分两种：
     *   - 外键引用场景（item 是文章）：从 category_id / category_slug 中取，
     *     并在 buildContext 的分类分支中转换为 id/slug；
     *   - item 本身就是目标实体（item 是分类）：通过 resolveSlugForType 的
     *     `item.id == context.id` 判断来获取 item.slug。
     */
    private function getTypePrimaryFields($type)
    {
        $common = ['created_at', 'created', 'date', 'publish_date',
                   'hour', 'minute', 'second',
                   'view_count', 'views'];

        if ($type === 'category') {
            // ⚠️ 不把裸 id/slug 放入白名单，避免文章列表场景被文章字段污染。
            // 分类实体自身的 id/slug 通过 buildContext 分支 + resolveSlugForType
            // 的 item.id==context.id 判定兜底获取。
            return array_merge($common, [
                'category_id', 'cat_id',           // 分类ID（来自文章item）
                'category_slug', 'cat_slug',       // 分类slug（来自文章item）
                'category_name', 'name', 'title',  // 分类名称
            ]);
        }
        if ($type === 'tag') {
            return array_merge($common, [
                'tag_id',                         // 标签ID（来自文章item）
                'tag_slug', 'tag',                // 标签slug
                'tag_name', 'name', 'title',      // 标签名称
            ]);
        }
        if ($type === 'article' || $type === 'page') {
            return array_merge($common, [
                'id', 'slug', 'postname', 'title', 'name',
                'article_id', 'post_id',
                'category_id', 'cat_id', 'category_slug', 'cat_slug', 'category_name',
                'tag_id', 'tag_slug', 'tag_name',
                'author', 'author_id', 'author_slug', 'user_id', 'username', 'nickname',
            ]);
        }
        if ($type === 'user' || $type === 'author') {
            return array_merge($common, [
                'id', 'user_id', 'author_id',
                'username', 'nickname', 'author', 'author_slug', 'name', 'slug',
            ]);
        }
        // 其他类型：保留旧行为，同时允许 id/slug/name 等基础字段
        return array_merge($common, [
            'id', 'slug', 'name', 'title', 'username', 'nickname',
            'user_id', 'author_id', 'author', 'author_slug',
            'post_id', 'article_id',
            'category_id', 'cat_id', 'category_slug', 'cat_slug', 'category_name', 'category',
            'tag_id', 'tag_slug', 'tag_name', 'tag',
            'postname',
        ]);
    }

    /**
     * 为目标类型解析 slug：
     * - 当 item 不是目标类型（例如在文章循环中解析分类链接），
     *   就不能把 item 的 slug 当作目标类型的 slug。
     * - 若 context 中已经有目标类型的 slug（例如 category_slug），直接用。
     * - 否则仅在 item 真正是目标类型时（item 的字段等于目标字段）才使用 item.slug。
     */
    private function resolveSlugForType($type, $context, $item)
    {
        // 1. context 中已经提供的目标类型 slug（通过白名单机制从 item 或全局 data 写入）
        if (!empty($context['slug'])) {
            return $context['slug'];
        }
        if ($type === 'category' && !empty($context['category_slug'])) {
            return $context['category_slug'];
        }
        if ($type === 'tag' && !empty($context['tag_slug'])) {
            return $context['tag_slug'];
        }

        // 2. item 本身就是目标类型的实体时，item.slug 才合法。
        //    通过"item.id == context.id（已经 resolveId 后的结果）"来判断：
        //    如果 id 是从 item.id 来的，说明 item 就是目标实体，slug 可直接用。
        if ($item && is_array($item) && !empty($item['slug'])) {
            $itemId = isset($item['id']) ? (string)$item['id'] : '';
            $contextId = isset($context['id']) ? (string)$context['id'] : '';
            if ($itemId !== '' && $itemId === $contextId) {
                return $item['slug'];
            }
        }

        // 3. 回退：只有在不在循环中时，才使用全局 data[$type].slug
        $loopItem = $this->template->getCurrentLoopItem();
        if (!$loopItem) {
            $data = $this->template->getData();
            if (!empty($data[$type]['slug'])) {
                return $data[$type]['slug'];
            }
        }
        return null;
    }

    /**
     * 解析分类跳转链接
     */
    private function resolveRedirectUrl($item)
    {
        $loopItem = $this->template->getCurrentLoopItem();
        
        // 优先使用当前循环项的redirect_url（用于侧边栏/导航等分类列表）
        if ($loopItem && isset($loopItem['redirect_url']) && !empty($loopItem['redirect_url'])) {
            return $loopItem['redirect_url'];
        }
        // 其次使用明确传入的item的redirect_url
        elseif ($item && is_array($item) && isset($item['redirect_url']) && !empty($item['redirect_url'])) {
            return $item['redirect_url'];
        }
        // 最后：只有在不在循环中时，才使用$data['category']['redirect_url']
        elseif (!$loopItem) {
            $data = $this->template->getData();
            if (isset($data['category']['redirect_url']) && !empty($data['category']['redirect_url'])) {
                return $data['category']['redirect_url'];
            }
        }
        return null;
    }

    /**
     * 解析分类slug（保留原签名以兼容其他可能直接调用它的地方）
     */
    private function resolveSlug($item)
    {
        $loopItem = $this->template->getCurrentLoopItem();

        // 先判断：当前 loopItem 本身是否就是分类/标签实体？
        // 只有"loopItem 的 id 与目标类型的 id 一致"时，slug 才可信。
        if ($loopItem && !empty($loopItem['slug'])
            && !empty($loopItem['id'])
            && !isset($loopItem['category_id'])   // 非文章
            && !isset($loopItem['article_id'])) { // 非评论/历史等
            return $loopItem['slug'];
        }
        // 明确 item 的 slug 可信（item 就是分类/标签实体自身）
        elseif ($item && is_array($item) && !empty($item['slug'])
            && !isset($item['category_id'])
            && !isset($item['article_id'])) {
            return $item['slug'];
        }
        elseif (!$loopItem) {
            $data = $this->template->getData();
            if (isset($data['category']['slug']) && !empty($data['category']['slug'])) {
                return $data['category']['slug'];
            }
        }
        return null;
    }

    /**
     * 构建带redirect参数的重定向URL
     */
    private function buildRedirectUrl($siteUrl, $url, $indexPrefix, $type, $configKey, $rewriteEnabled, $rewriteRules, $paramName)
    {
        if ($rewriteEnabled && isset($rewriteRules[$type])) {
            $url .= '/' . Config::get($configKey, $type);
        } else {
            $url .= $indexPrefix . '/' . $type;
        }
        
        if ($paramName === 'redirect' && isset($_SERVER['REQUEST_URI'])) {
            $requestUri = $_SERVER['REQUEST_URI'];
            $requestUri = preg_replace('/[?&]redirect=[^&]*/', '', $requestUri);
            $requestUri = preg_replace('/\?$/', '', $requestUri);
            $currentUrl = $siteUrl . $requestUri;
            $url .= '?redirect=' . urlencode($currentUrl);
        }
        
        return $url;
    }
}
