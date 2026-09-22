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
 * 系统API控制器
 * 提供系统相关的API接口
 * 
 * 基础URL: /api.php/v1/system
 */

class SystemApiController extends ApiController {
    
    /**
     * 获取系统配置
     * GET /api.php/v1/system/config
     */
    public function config() {        
        $config = Config::getPublicConfig();
        
        $this->success($config, '获取成功');
    }
    
    /**
     * 获取系统信息
     * GET /api.php/v1/system/info
     */
    public function info() {        
        // 从 version.php 文件读取系统版本（唯一真相源）
        $versionData = require CORE_PATH . '/config/version.php';
        
        $systemInfo = [
            'version' => $versionData['version'],
            'name' => $versionData['name'],
            'description' => Config::get('site_description', 'A simple Blog System'),
            'php_version' => PHP_VERSION,
            'server_time' => date('Y-m-d H:i:s')
        ];
        
        $this->success($systemInfo, '获取成功');
    }
    
    /**
     * 获取系统统计
     * GET /api.php/v1/system/stats
     */
    public function stats() {        
        $db = Database::getInstance();
        
        $articleCount = $db->fetch("SELECT COUNT(*) as count FROM {$db->table('article')}");
        $userCount = $db->fetch("SELECT COUNT(*) as count FROM {$db->table('user')}");
        $commentCount = $db->fetch("SELECT COUNT(*) as count FROM {$db->table('comment')}");
        $categoryCount = $db->fetch("SELECT COUNT(*) as count FROM {$db->table('category')}");
        $tagCount = $db->fetch("SELECT COUNT(*) as count FROM {$db->table('tag')}");
        
        $stats = [
            'articles' => $articleCount['count'],
            'users' => $userCount['count'],
            'comments' => $commentCount['count'],
            'categories' => $categoryCount['count'],
            'tags' => $tagCount['count']
        ];
        
        $this->success($stats, '获取成功');
    }
}
