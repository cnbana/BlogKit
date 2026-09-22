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

return [
    // 站点基本信息
    'site' => [
        'name' => 'BlogKit',
        'description' => 'A simple Blog System',
        'url' => '', // 实际运行时会自动获取当前域名
        'charset' => 'utf-8',
    ],
    
    // URL重写设置
    'rewrite' => [
        'enabled' => true,
        'rules' => [
            'article/:id' => 'Home/article/:id',
            'category/:id' => 'Home/category/:id',
            'tag/:id' => 'Home/tag/:id',
            'page/:id' => 'Home/page/:id',
            'search' => 'Home/search',
            'login' => 'Auth/login',
            'register' => 'Auth/register',
            'logout' => 'Auth/logout',
            'forgot-password' => 'Auth/forgotPassword',
            'reset-password' => 'Auth/resetPassword',
            'profile' => 'Auth/profile',
            'profile/articles' => 'Auth/profile/articles',
            'profile/comments' => 'Auth/profile/comments',
            'profile/settings' => 'Auth/profile/settings',
            'profile/favorites' => 'Auth/profile/favorites',
            'profile/likes' => 'Auth/profile/likes',
            'profile/notifications' => 'Auth/profile/notifications',
            'profile/history' => 'Auth/profile/history',
            'profile/following' => 'Auth/profile/following',
            'profile/followers' => 'Auth/profile/followers',
            'user/:id' => 'User/index/:id',
            'follow/:id' => 'User/follow/:id',
            'following/:id' => 'User/following/:id',
            'followers/:id' => 'User/followers/:id',
        ],
    ],
    
    // 主题设置
    'theme' => [
        'default' => 'default',
        'cache' => true,
    ],
    
    // 插件设置
    'plugin' => [
        'enabled' => true,
    ],
    
    // 系统路径
    'path' => [
        'root' => dirname(dirname(dirname(__FILE__))),
        'core' => dirname(dirname(__FILE__)),
        'themes' => dirname(dirname(__FILE__)) . '/themes',
        'plugins' => dirname(dirname(__FILE__)) . '/plugins',
        'uploads' => dirname(dirname(__FILE__)) . '/uploads',
    ],
    
    // 调试设置
    'debug' => [
        'enabled' => true, // 是否启用调试模式（生产环境请改为 false）
        'panel_enabled' => true, // 是否显示调试面板
        'error_display' => 'development', // 错误显示模式（development:开发模式，production:生产模式）
    ],
    
    // 缓存设置（运行时从数据库加载）
    'cache' => [],
];


