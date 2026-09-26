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
 * 上传API控制器
 * 提供上传相关的API接口
 * 
 * 基础URL: /api.php/v1/upload
 */

class UploadApiController extends ApiController {
    
    /**
     * 上传图片
     * POST /api.php/v1/upload/image
     */
    public function image() {        $this->requireLogin();
        
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $this->error('图片上传失败', 400);
            return;
        }
        
        $file = $_FILES['image'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $fileType = $file['type'];
        $fileSize = $file['size'];
        $maxSize = 5 * 1024 * 1024;
        
        if (!in_array($fileType, $allowedTypes)) {
            $this->error('只支持JPG、PNG、GIF、WEBP格式', 400);
            return;
        }
        
        if ($fileSize > $maxSize) {
            $this->error('图片大小不能超过5MB', 400);
            return;
        }
        
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'image_' . time() . '_' . rand(1000, 9999) . '.' . $extension;
        $uploadPath = UPLOADS_PATH . '/images/' . date('Y/m/d') . '/';
        
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }
        
        $filepath = $uploadPath . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $url = '/uploads/images/' . date('Y/m/d') . '/' . $filename;
            // 触发上传完成钩子（绕过 Upload 类的上传点，云存储插件依赖此钩子做镜像同步）
            Hook::trigger(Hook::FILE_UPLOAD_AFTER, array('name' => $filename, 'path' => $filepath, 'relative_path' => $url, 'type' => 'image'));

            $this->success([
                'url' => $url,
                'filename' => $filename,
                'size' => $fileSize
            ], '上传成功');
        } else {
            $this->error('图片保存失败', 500);
        }
    }
    
    /**
     * 上传文件
     * POST /api.php/v1/upload/file
     */
    public function file() {        $this->requireLogin();
        
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $this->error('文件上传失败', 400);
            return;
        }
        
        $file = $_FILES['file'];
        $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/x-rar-compressed'];
        $fileType = $file['type'];
        $fileSize = $file['size'];
        $maxSize = 20 * 1024 * 1024;
        
        if (!in_array($fileType, $allowedTypes)) {
            $this->error('只支持PDF、Word、Excel、ZIP、RAR格式', 400);
            return;
        }
        
        if ($fileSize > $maxSize) {
            $this->error('文件大小不能超过20MB', 400);
            return;
        }
        
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'file_' . time() . '_' . rand(1000, 9999) . '.' . $extension;
        $uploadPath = UPLOADS_PATH . '/files/' . date('Y/m/d') . '/';
        
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }
        
        $filepath = $uploadPath . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $url = '/uploads/files/' . date('Y/m/d') . '/' . $filename;
            // 触发上传完成钩子（绕过 Upload 类的上传点，云存储插件依赖此钩子做镜像同步）
            Hook::trigger(Hook::FILE_UPLOAD_AFTER, array('name' => $filename, 'path' => $filepath, 'relative_path' => $url, 'type' => 'file'));

            $this->success([
                'url' => $url,
                'filename' => $filename,
                'size' => $fileSize
            ], '上传成功');
        } else {
            $this->error('文件保存失败', 500);
        }
    }
    
    /**
     * 上传头像
     * POST /api.php/v1/upload/avatar
     */
    public function avatar() {        $this->requireLogin();
        
        if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            $this->error('头像上传失败', 400);
            return;
        }
        
        $file = $_FILES['avatar'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $fileType = $file['type'];
        $fileSize = $file['size'];
        $maxSize = 2 * 1024 * 1024;
        
        if (!in_array($fileType, $allowedTypes)) {
            $this->error('只支持JPG、PNG、GIF、WEBP格式', 400);
            return;
        }
        
        if ($fileSize > $maxSize) {
            $this->error('头像大小不能超过2MB', 400);
            return;
        }
        
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'avatar_' . $this->currentUser['id'] . '_' . time() . '.' . $extension;
        $uploadPath = UPLOADS_PATH . '/avatars/';
        
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }
        
        $filepath = $uploadPath . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $url = '/uploads/avatars/' . $filename;
            // 触发上传完成钩子（绕过 Upload 类的上传点，云存储插件依赖此钩子做镜像同步）
            Hook::trigger(Hook::FILE_UPLOAD_AFTER, array('name' => $filename, 'path' => $filepath, 'relative_path' => $url, 'type' => 'avatar'));

            $userModel = new UserModel();
            $result = $userModel->updateUser($this->currentUser['id'], [
                'avatar' => $url
            ]);
            
            if ($result) {
                $this->success([
                    'url' => $url,
                    'filename' => $filename
                ], '头像上传成功');
            } else {
                $this->error('头像保存失败', 500);
            }
        } else {
            $this->error('头像保存失败', 500);
        }
    }
}
