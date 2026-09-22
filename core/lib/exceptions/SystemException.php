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
 * 系统异常
 * HTTP 500 Internal Server Error
 * 自动记录错误日志（不含敏感信息）
 */
class SystemException extends AppException
{
    public function __construct(string $message = '服务器繁忙，请稍后重试', \Throwable $previous = null)
    {
        parent::__construct($message, 500, HTTP_INTERNAL_ERROR, [], $previous);
        
        // 自动记录错误日志
        if (class_exists('Log')) {
            $logMessage = $previous ? $previous->getMessage() : $message;
            Log::error($logMessage, Log::CATEGORY_SYSTEM, [
                'file' => $previous ? $previous->getFile() : '',
                'line' => $previous ? $previous->getLine() : '',
            ]);
        }
    }
}
