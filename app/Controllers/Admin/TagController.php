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


// 包含模型类
if (!class_exists('TagModel')) {
}
// 加载日志类

class TagController {
    public function index() {
        $tagModel = new TagModel();
        $tags = $tagModel->getAllTags();
        
        // 转换时间格式
        foreach ($tags as &$tag) {
            $tag['created_at'] = date('Y-m-d H:i:s', $tag['created_at']);
        }
        
        // 按文章数量降序、名称升序排序
        usort($tags, function($a, $b) {
            if ($a['article_count'] == $b['article_count']) {
                return strcmp($a['name'], $b['name']);
            }
            return $b['article_count'] - $a['article_count'];
        });
        
        include ADMIN_PATH . '/templates/tag.html';
    }
    
    public function add() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $name = trim($_POST['name']);
            
            if (empty($name)) {
                echo "<script>alert('标签名称不能为空');window.history.back();</script>";
                exit;
            }
            
            $tagModel = new TagModel();
            if ($tagModel->createTag($name)) {
                Log::info('标签管理', '添加标签', '成功添加标签: ' . $name, Log::CATEGORY_OPERATION);
                echo "<script>alert('标签添加成功');window.location.href='admin.php?action=tag';</script>";
            } else {
                Log::error('标签管理', '添加标签', '添加标签失败: ' . $name, Log::CATEGORY_OPERATION);
                echo "<script>alert('标签添加失败');window.history.back();</script>";
            }
            exit;
        }
        
        include ADMIN_PATH . '/templates/tag_add.html';
    }
    
    public function edit() {
        $id = $_GET['id'] ?? 0;
        if (empty($id)) {
            echo "<script>alert('标签ID不能为空');window.location.href='admin.php?action=tag';</script>";
            exit;
        }
        
        $tagModel = new TagModel();
        $tag = $tagModel->getTagById($id);
        
        if (!$tag) {
            echo "<script>alert('标签不存在');window.location.href='admin.php?action=tag';</script>";
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $name = trim($_POST['name']);
            
            if (empty($name)) {
                echo "<script>alert('标签名称不能为空');window.history.back();</script>";
                exit;
            }
            
            if ($tagModel->updateTag($id, $name)) {
                Log::info('标签管理', '编辑标签', '成功编辑标签ID ' . $id . ': ' . $name, Log::CATEGORY_OPERATION);
                echo "<script>alert('标签更新成功');window.location.href='admin.php?action=tag';</script>";
            } else {
                Log::error('标签管理', '编辑标签', '编辑标签失败ID ' . $id, Log::CATEGORY_OPERATION);
                echo "<script>alert('标签更新失败');window.history.back();</script>";
            }
            exit;
        }
        
        include ADMIN_PATH . '/templates/tag_edit.html';
    }
    
    public function delete() {
        $id = $_GET['id'] ?? 0;
        if (empty($id)) {
            echo "<script>alert('标签ID不能为空');window.location.href='admin.php?action=tag';</script>";
            exit;
        }
        
        $tagModel = new TagModel();
        $tag = $tagModel->getTagById($id);
        if ($tagModel->deleteTag($id)) {
            Log::info('标签管理', '删除标签', '成功删除标签: ' . $tag['name'] . ' (ID: ' . $id . ')', Log::CATEGORY_OPERATION);
            echo "<script>alert('标签删除成功');window.location.href='admin.php?action=tag';</script>";
        } else {
            Log::error('标签管理', '删除标签', '删除标签失败ID ' . $id, Log::CATEGORY_OPERATION);
            echo "<script>alert('标签删除失败');window.location.href='admin.php?action=tag';</script>";
        }
        exit;
    }
}
