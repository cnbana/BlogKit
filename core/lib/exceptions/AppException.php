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
 * BlogKit 异常体系基类
 * 
 * 所有业务异常继承此类，统一管理错误码和HTTP状态码映射。
 */
class AppException extends \Exception
{
    /** @var int HTTP状态码 */
    protected $httpCode = HTTP_INTERNAL_ERROR;

    /** @var array 额外数据 */
    protected $data = [];

    /**
     * @param string $message 错误信息
     * @param int $code 业务错误码
     * @param int $httpCode HTTP状态码
     * @param array $data 额外数据
     * @param \Throwable|null $previous 前一异常
     */
    public function __construct(
        string $message = '服务器内部错误',
        int $code = 0,
        int $httpCode = HTTP_INTERNAL_ERROR,
        array $data = [],
        \Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->httpCode = $httpCode;
        $this->data = $data;
    }

    /**
     * 获取HTTP状态码
     */
    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    /**
     * 获取额外数据
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * 转为数组（用于API响应）
     */
    public function toArray(): array
    {
        return [
            'code' => $this->getCode(),
            'message' => $this->getMessage(),
            'data' => $this->data,
        ];
    }
}
