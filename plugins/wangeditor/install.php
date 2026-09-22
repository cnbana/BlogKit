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

if (!defined('IN_ADMIN')) die('Access Denied');
/**
 * WangEditor 插件安装脚本
 * 向 plugin 表注册插件记录（默认禁用，需在后台手动启用）
 *
 * 说明：本插件不创建任何业务数据表，无需数据库结构变更，
 *       因此不涉及 install.sql 更新。
 */

// 定义系统根目录常量
$root_dir = dirname(__FILE__) . '/../../';
if (!file_exists($root_dir . 'core/lib/Database.php')) {
    die('系统根目录未找到，请确保插件目录结构正确！');
}

define('ROOT_PATH', $root_dir);
define('CORE_PATH', ROOT_PATH . '/core');
chdir($root_dir);

// 加载配置和数据库类
require_once 'core/lib/Config.php';
require_once 'core/lib/Database.php';

Config::init();
$db = Database::getInstance();

// 插件信息（与 plugin.json 保持一致）
$plugin_info = [
    'name' => 'WangEditor',
    'slug' => 'wangeditor',
    'description' => '基于 wangEditor v5 的开源富文本编辑器插件，本地资源加载，支持图片/视频上传、代码高亮等',
    'version' => '5.1.23',
    'status' => 0, // 默认禁用
    'settings' => json_encode([]),
    'created_at' => time()
];

$plugin_table = $db->table('plugin');

// 检查插件是否已存在（避免重复注册）
$existing_plugin = $db->fetch("SELECT * FROM {$plugin_table} WHERE `slug` = ?", [$plugin_info['slug']]);

if ($existing_plugin) {
    // 已存在则更新描述信息
    $result = $db->update('plugin', [
        'name' => $plugin_info['name'],
        'description' => $plugin_info['description'],
        'version' => $plugin_info['version']
    ], ['id' => $existing_plugin['id']]);

    if ($result) {
        echo "插件信息更新成功！<br>";
        echo "插件ID: {$existing_plugin['id']}<br>";
        echo "插件状态: " . ($existing_plugin['status'] ? '已启用' : '已禁用') . "<br>";
    } else {
        echo "插件信息更新失败！<br>";
    }
} else {
    // 新插件，插入记录
    $plugin_id = $db->insert('plugin', $plugin_info);
    if ($plugin_id) {
        echo "插件安装成功！<br>";
        echo "插件ID: {$plugin_id}<br>";
        echo "插件状态: 已禁用（需要在后台手动启用）<br>";
    } else {
        echo "插件安装失败！<br>";
    }
}

echo "<a href='../../admin.php?action=plugin'>前往插件管理页面</a>";
