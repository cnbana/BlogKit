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
 * BlogKit 模板编译缓存组件
 * 
 * 从 Template.php 中提取的模板编译与缓存系统：
 *   - 初始化缓存目录
 *   - 检查模板是否已编译
 *   - 读取/保存编译缓存
 *   - 清空编译缓存
 * 
 * @package BlogKit
 * @since 2.1.0
 */
class TemplateCompiler
{
    /**
     * @var bool 是否启用模板缓存
     */
    private $enabled = false;

    /**
     * @var string 缓存目录路径
     */
    private $cacheDir = '';

    /**
     * 构造函数
     * 
     * @param bool $enabled 是否启用缓存
     */
    public function __construct($enabled = true)
    {
        $this->enabled = $enabled;
        
        if ($this->enabled) {
            $this->cacheDir = STORAGE_PATH . '/cache/templates/';
            
            if (!is_dir($this->cacheDir)) {
                @mkdir($this->cacheDir, 0755, true);
            }
        }
    }

    /**
     * 检查缓存是否启用
     * 
     * @return bool
     */
    public function isEnabled()
    {
        return $this->enabled;
    }

    /**
     * 获取缓存目录
     * 
     * @return string
     */
    public function getCacheDir()
    {
        return $this->cacheDir;
    }

    /**
     * 检查模板是否已编译且缓存未过期
     * 
     * @param string $templateFile 模板文件路径
     * @return bool
     */
    public function isCompiled($templateFile)
    {
        if (!$this->enabled) {
            return false;
        }

        $cacheFile = $this->getCachePath($templateFile);
        if (!file_exists($cacheFile)) {
            return false;
        }

        // 缓存时间的比较：缓存必须比模板文件更新
        $templateMtime = filemtime($templateFile);
        $cacheMtime    = filemtime($cacheFile);

        return $cacheMtime >= $templateMtime;
    }

    /**
     * 获取编译后的模板缓存内容
     * 
     * @param string $templateFile 模板文件路径
     * @return string|false 编译后的内容，失败返回 false
     */
    public function getCompiled($templateFile)
    {
        if (!$this->enabled) {
            return false;
        }

        $cacheFile = $this->getCachePath($templateFile);
        if (!file_exists($cacheFile)) {
            return false;
        }

        return file_get_contents($cacheFile);
    }

    /**
     * 保存编译后的模板到缓存
     * 
     * @param string $templateFile 模板文件路径
     * @param string $content      编译后的内容
     * @return bool
     */
    public function saveCompiled($templateFile, $content)
    {
        if (!$this->enabled) {
            return false;
        }

        $cacheFile = $this->getCachePath($templateFile);
        return file_put_contents($cacheFile, $content) !== false;
    }

    /**
     * 生成缓存文件路径
     * 
     * @param string $templateFile 模板文件路径
     * @return string
     */
    public function getCachePath($templateFile)
    {
        $filename = md5($templateFile) . '.php';
        return $this->cacheDir . $filename;
    }

    /**
     * 清空所有模板编译缓存
     * 
     * @return bool
     */
    public function clear()
    {
        if (!$this->enabled || !is_dir($this->cacheDir)) {
            return false;
        }

        $files = glob($this->cacheDir . '*.php');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        return true;
    }
}
