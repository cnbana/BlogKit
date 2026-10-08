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
 * BlogKit API 统一入口（转发壳）
 *
 * 主系统本身不实现任何 API 逻辑，开放API套件由插件提供：
 *   1. 加载 bootstrap.php 完成公共初始化；
 *   2. 触发 SYSTEM_INIT 钩子（与其它入口一致）；
 *   3. 触发 api_dispatch 钩子——若 openapi 插件已启用，
 *      其监听回调会接管本次请求的全部处理（版本协商、
 *      路由分发、认证、CORS、响应输出），并以 exit 结束；
 *   4. 无任何插件接管时（未安装/未启用），返回 404 JSON 提示。
 */

require_once __DIR__ . '/core/bootstrap.php';

// 触发系统初始化钩子（class_exists 触发 autoload 加载 Hook 类）
if (class_exists('Hook')) {
    Hook::trigger(Hook::SYSTEM_INIT);
}

// API 分发接管检查：返回值数组非空即表示已有插件接管本次请求
// （即使回调返回 null，结果数组也会包含该元素，因此以数组判空为准）
if (class_exists('Hook')) {
    $results = Hook::triggerWithReturn('api_dispatch');
    if (!empty($results)) {
        exit;
    }
}

// 无插件接管：openapi 插件未安装或未启用
http_response_code(404);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'code' => 404,
    'message' => 'API 功能未启用：请安装并启用 openapi（开放API套件）插件'
], JSON_UNESCAPED_UNICODE);
exit;
