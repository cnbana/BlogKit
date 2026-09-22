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
 * 媒体文件服务
 *
 * 统一处理"自动生成的图片衍生文件"的识别与清理。
 * 图片处理插件在上传文章图片时会自动生成两类衍生文件：
 *   1. 尺寸变体：{原名}_large.{ext}、{原名}_medium.{ext}、{原名}_small.{ext}
 *   2. 格式转换副本：{原名}.webp（当原图为 jpg/png/gif 时）
 * 这些衍生文件与原图内容相同，在媒体库选择器、媒体管理列表中展示会造成
 * 大量重复项，因此列表展示时需要过滤，删除原图时需要级联清理。
 *
 * @package BlogKit
 * @since 2.1.0
 */
class MediaService {

    /**
     * 自动生成的尺寸变体后缀（与图片处理插件的命名规则保持一致）
     * @var array
     */
    private static $sizeVariantSuffixes = ['_large', '_medium', '_small'];

    /**
     * 可识别的图片扩展名
     * @var array
     */
    private static $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /**
     * 判断文件是否为自动生成的衍生文件（尺寸变体或 webp 格式转换副本）
     *
     * @param string $filePath 文件完整路径（或路径字符串，取 basename 判断）
     * @return bool true 表示是衍生文件，列表展示时应过滤
     */
    public static function isGeneratedVariant($filePath) {
        $filename = basename($filePath);
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        // 非图片文件不存在衍生文件一说
        if (!in_array($ext, self::$imageExts)) {
            return false;
        }

        $nameNoExt = pathinfo($filename, PATHINFO_FILENAME);

        // 1. 尺寸变体：{原名}_large/_medium/_small.{ext}
        foreach (self::$sizeVariantSuffixes as $suffix) {
            if (substr($nameNoExt, -strlen($suffix)) === $suffix) {
                return true;
            }
        }

        // 2. 格式转换副本：{原名}.webp，且同目录下存在 {原名}.jpg/jpeg/png/gif 原图
        if ($ext === 'webp') {
            $dir = dirname($filePath);
            foreach (['jpg', 'jpeg', 'png', 'gif'] as $origExt) {
                if (is_file($dir . '/' . $nameNoExt . '.' . $origExt)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 删除原文件及其自动生成的衍生文件
     *
     * @param string $fullPath 原文件的完整路径
     * @return array ['success' => bool, 'variants_deleted' => int] 删除结果与清理掉的衍生文件数量
     */
    public static function deleteWithVariants($fullPath) {
        $result = ['success' => false, 'variants_deleted' => 0];

        if (!is_file($fullPath)) {
            return $result;
        }

        // 先删除原文件本身
        if (!@unlink($fullPath)) {
            return $result;
        }
        $result['success'] = true;

        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        if (!in_array($ext, self::$imageExts)) {
            // 非图片文件没有衍生文件
            return $result;
        }

        $dir = dirname($fullPath);
        $nameNoExt = pathinfo($fullPath, PATHINFO_FILENAME);

        // 组装可能的衍生文件路径：
        //   - 同扩展名的尺寸变体：{原名}_large/_medium/_small.{ext}
        //   - webp 格式转换副本：{原名}.webp 及其尺寸变体
        $candidatePaths = [];
        foreach (self::$sizeVariantSuffixes as $suffix) {
            $candidatePaths[] = $dir . '/' . $nameNoExt . $suffix . '.' . $ext;
        }
        if ($ext !== 'webp') {
            $candidatePaths[] = $dir . '/' . $nameNoExt . '.webp';
            foreach (self::$sizeVariantSuffixes as $suffix) {
                $candidatePaths[] = $dir . '/' . $nameNoExt . $suffix . '.webp';
            }
        }

        // 逐一删除实际存在的衍生文件（不存在则静默跳过）
        foreach ($candidatePaths as $candidate) {
            if (is_file($candidate) && @unlink($candidate)) {
                $result['variants_deleted']++;
            }
        }

        return $result;
    }
}
