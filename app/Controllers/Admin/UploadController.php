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
 * 编辑器上传控制器
 * 处理 Markdown / 富文本编辑器的图片和文件上传
 * 受后台登录态保护，外部无法直接访问
 */
class UploadController {

    /**
     * 返回编辑器配置（UEditor 格式）
     */
    public function config() {
        header('Content-Type: application/json; charset=utf-8');
        date_default_timezone_set('Asia/Shanghai');

        $config = [
            "imageActionName"         => "uploadimage",
            "imageFieldName"          => "upfile",
            "imageMaxSize"            => 5242880,
            "imageAllowFiles"         => [".png", ".jpg", ".jpeg", ".gif", ".bmp", ".webp"],
            "imageCompressEnable"     => true,
            "imageCompressBorder"     => 1600,
            "imageInsertAlign"        => "none",
            "imageUrlPrefix"          => "",
            "imagePathFormat"         => "/uploads/articles/{yyyy}/{mm}/{time}{rand:6}",

            "scrawlActionName"        => "uploadscrawl",
            "scrawlFieldName"         => "upfile",
            "scrawlPathFormat"        => "/uploads/articles/{yyyy}/{mm}/{time}{rand:6}",
            "scrawlMaxSize"           => 2048000,
            "scrawlUrlPrefix"         => "",
            "scrawlInsertAlign"       => "none",

            "snapscreenActionName"    => "uploadimage",
            "snapscreenPathFormat"    => "/uploads/articles/{yyyy}/{mm}/{time}{rand:6}",
            "snapscreenUrlPrefix"     => "",
            "snapscreenInsertAlign"   => "none",

            "catcherLocalDomain"      => ["127.0.0.1", "localhost", "img.baidu.com"],
            "catcherActionName"       => "catchimage",
            "catcherFieldName"        => "source",
            "catcherPathFormat"       => "/uploads/articles/{yyyy}/{mm}/{time}{rand:6}",
            "catcherUrlPrefix"        => "",
            "catcherMaxSize"          => 2048000,
            "catcherAllowFiles"       => [".png", ".jpg", ".jpeg", ".gif", ".bmp", ".webp"],

            "videoActionName"         => "uploadvideo",
            "videoFieldName"          => "upfile",
            "videoPathFormat"         => "/uploads/attachments/{yyyy}/{mm}/{time}{rand:6}",
            "videoUrlPrefix"          => "",
            "videoMaxSize"            => 102400000,
            "videoAllowFiles"         => [
                ".flv", ".swf", ".mkv", ".avi", ".rm", ".rmvb", ".mpeg", ".mpg",
                ".ogg", ".ogv", ".mov", ".wmv", ".mp4", ".webm", ".mp3", ".wav", ".mid"
            ],

            "fileActionName"          => "uploadfile",
            "fileFieldName"           => "upfile",
            "filePathFormat"          => "/uploads/attachments/{yyyy}/{mm}/{time}{rand:6}",
            "fileUrlPrefix"           => "",
            "fileMaxSize"             => 102400000,
            "fileAllowFiles"          => [
                ".png", ".jpg", ".jpeg", ".gif", ".bmp",
                ".flv", ".swf", ".mkv", ".avi", ".rm", ".rmvb", ".mpeg", ".mpg",
                ".ogg", ".ogv", ".mov", ".wmv", ".mp4", ".webm", ".mp3", ".wav", ".mid",
                ".rar", ".zip", ".tar", ".gz", ".7z", ".bz2", ".cab", ".iso",
                ".doc", ".docx", ".xls", ".xlsx", ".ppt", ".pptx", ".pdf", ".txt", ".md", ".xml"
            ],

            "imageManagerActionName"  => "listimage",
            "imageManagerListPath"    => "/uploads/articles/",
            "imageManagerListSize"    => 20,
            "imageManagerUrlPrefix"   => "",
            "imageManagerInsertAlign" => "none",
            "imageManagerAllowFiles"  => [".png", ".jpg", ".jpeg", ".gif", ".bmp", ".webp"],

            "fileManagerActionName"   => "listfile",
            "fileManagerListPath"     => "/uploads/attachments/",
            "fileManagerUrlPrefix"    => "",
            "fileManagerListSize"     => 20,
            "fileManagerAllowFiles"   => [
                ".png", ".jpg", ".jpeg", ".gif", ".bmp",
                ".flv", ".swf", ".mkv", ".avi", ".rm", ".rmvb", ".mpeg", ".mpg",
                ".ogg", ".ogv", ".mov", ".wmv", ".mp4", ".webm", ".mp3", ".wav", ".mid",
                ".rar", ".zip", ".tar", ".gz", ".7z", ".bz2", ".cab", ".iso",
                ".doc", ".docx", ".xls", ".xlsx", ".ppt", ".pptx", ".pdf", ".txt", ".md", ".xml"
            ]
        ];

        echo json_encode($config, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * 上传图片
     */
    public function uploadimage() {
        $this->handleUpload('article', 5 * 1024 * 1024,
            ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true);
    }

    /**
     * 上传文件
     */
    public function uploadfile() {
        $this->handleUpload('attachment', 10 * 1024 * 1024, [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/zip', 'application/rar',
            'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain', 'application/pdf'
        ], false);
    }

    /**
     * 上传视频
     */
    public function uploadvideo() {
        $this->handleUpload('attachment', 100 * 1024 * 1024, [
            'video/mp4', 'video/webm', 'video/ogg',
            'audio/mp3', 'audio/wav', 'audio/mid'
        ], false);
    }

    /**
     * 上传涂鸦
     */
    public function uploadscrawl() {
        $this->handleUpload('article', 2 * 1024 * 1024,
            ['image/png'], true);
    }

    /**
     * 处理所有文件上传
     */
    private function handleUpload($uploadType, $maxSize, $allowedTypes, $thumbnail) {
        error_reporting(0);
        ini_set('display_errors', 0);

        header('Content-Type: application/json; charset=utf-8');
        date_default_timezone_set('Asia/Shanghai');

        // 获取上传文件（兼容多种编辑器命名）
        $file = null;
        if (isset($_FILES['upfile']) && $_FILES['upfile']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['upfile'];
        } elseif (isset($_FILES['editormd-image-file'])) {
            $file = $_FILES['editormd-image-file'];
        } elseif (isset($_FILES['image'])) {
            $file = $_FILES['image'];
        }

        if ($file === null) {
            echo json_encode(['state' => '未选择上传文件', 'success' => 0, 'message' => '未选择上传文件']);
            exit;
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors = [
                UPLOAD_ERR_INI_SIZE   => '文件大小超过服务器限制',
                UPLOAD_ERR_FORM_SIZE  => '文件大小超过表单限制',
                UPLOAD_ERR_PARTIAL    => '文件只上传了一部分',
                UPLOAD_ERR_NO_FILE    => '没有文件被上传',
                UPLOAD_ERR_NO_TMP_DIR => '找不到临时文件夹',
                UPLOAD_ERR_CANT_WRITE => '无法写入磁盘',
                UPLOAD_ERR_EXTENSION  => '上传被PHP扩展中断'
            ];
            $msg = '文件上传失败：' . ($errors[$file['error']] ?? '未知错误');
            echo json_encode(['state' => $msg, 'success' => 0, 'message' => $msg]);
            exit;
        }

        $upload = new Upload([
            'fileType'     => $uploadType,
            'maxSize'      => $maxSize,
            'allowedTypes' => $allowedTypes,
            'thumbnail'    => $thumbnail
        ]);

        if ($upload->upload($file)) {
            $info       = $upload->getFileInfo();
            $ext        = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename   = basename($info['name']);
            $path       = str_replace('//', '/', $info['relative_path']);

            echo json_encode([
                'state'    => 'SUCCESS',
                'success'  => 1,
                'url'      => $path,
                'title'    => $filename,
                'original' => $file['name'],
                'type'     => '.' . $ext,
                'size'     => $file['size']
            ], JSON_UNESCAPED_UNICODE);
        } else {
            $msg = '文件上传失败：' . $upload->getError();
            echo json_encode(['state' => $msg, 'success' => 0, 'message' => $msg], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
}
