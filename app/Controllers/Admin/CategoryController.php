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


// 包含分类模型
if (!class_exists('CategoryModel')) {
}

// 包含文章模型用于获取文章数量
if (!class_exists('ArticleModel')) {
}

class CategoryController
{
    public function index()
    {
        $categoryModel = new CategoryModel();
        $categories = $categoryModel->getAllCategories();
        
        include ADMIN_PATH . '/templates/category.html';
    }
    
    public function add()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            // 处理添加分类的请求
            $categoryModel = new CategoryModel();
            // 处理关键词，自动将中文逗号和空格转换为英文逗号
            $keywords = isset($_POST['keywords']) ? $_POST['keywords'] : '';
            $keywords = str_replace(['，', ' ', '　'], ',', $keywords);
            $keywords = preg_replace('/,+/', ',', $keywords);
            $keywords = trim($keywords, ',');
            
            $data = [
                'name' => $_POST['name'],
                'parent_id' => $_POST['parent_id'],
                'order_by' => $_POST['order_by'],
                'description' => $_POST['description'],
                'keywords' => $keywords,
                'slug' => isset($_POST['slug']) ? $_POST['slug'] : '',
                'is_menu' => isset($_POST['is_menu']) ? 1 : 0,
                'redirect_url' => isset($_POST['redirect_url']) ? $_POST['redirect_url'] : ''
            ];
            
            // 添加数据验证
            if (empty($data['name'])) {
                echo '<script>alert("分类名称不能为空");window.location.href="admin.php?action=category&sub=add";</script>';
                exit;
            }
            // 验证排序值必须为非负整数
            if (!is_numeric($data['order_by']) || $data['order_by'] < 0 || $data['order_by'] != (int)$data['order_by']) {
                echo '<script>alert("排序值必须为非负整数");window.location.href="admin.php?action=category&sub=add";</script>';
                exit;
            }
            // 验证别名唯一性
            if (!empty($data['slug'])) {
                $existingCategory = $categoryModel->getCategoryBySlug($data['slug']);
                if ($existingCategory) {
                    echo '<script>alert("别名已存在，请使用其他别名");window.location.href="admin.php?action=category&sub=add";</script>';
                    exit;
                }
                
                // 检查别名是否与已有的分类 ID 冲突
                if (is_numeric($data['slug']) && $categoryModel->exists((int)$data['slug'])) {
                    echo '<script>alert("别名不能与已有的分类 ID 重复，请使用其他别名");window.location.href="admin.php?action=category&sub=add";</script>';
                    exit;
                }
            }
            
            $result = $categoryModel->addCategory($data);
            if ($result) {
                // 记录分类添加日志
                Log::init();
                Log::info('添加分类', 'operation', ['category_name' => $_POST['name'], 'category_id' => $result]);
                
                echo '<script>alert("分类添加成功");window.location.href="admin.php?action=category";</script>';
            } else {
                echo '<script>alert("分类添加失败");window.location.href="admin.php?action=category&sub=add";</script>';
            }
        } else {
            // 显示添加分类表单
            $categoryModel = new CategoryModel();
            $categories = $categoryModel->getCategoriesList();
            include ADMIN_PATH . '/templates/category_add.html';
        }
    }
    
    public function edit()
    {
        $id = $_GET['id'];
        $categoryModel = new CategoryModel();
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            // 处理编辑分类的请求
            // 处理关键词，自动将中文逗号和空格转换为英文逗号
            $keywords = isset($_POST['keywords']) ? $_POST['keywords'] : '';
            $keywords = str_replace(['，', ' ', '　'], ',', $keywords);
            $keywords = preg_replace('/,+/', ',', $keywords);
            $keywords = trim($keywords, ',');
            
            $data = [
                'name' => $_POST['name'],
                'parent_id' => $_POST['parent_id'],
                'order_by' => $_POST['order_by'],
                'description' => $_POST['description'],
                'keywords' => $keywords,
                'slug' => isset($_POST['slug']) ? $_POST['slug'] : '',
                'is_menu' => isset($_POST['is_menu']) ? 1 : 0,
                'redirect_url' => isset($_POST['redirect_url']) ? $_POST['redirect_url'] : ''
            ];
            
            // 添加数据验证
            if (empty($data['name'])) {
                echo '<script>alert("分类名称不能为空");window.location.href="admin.php?action=category&sub=edit&id=' . $id . '";</script>';
                exit;
            }
            // 验证排序值必须为非负整数
            if (!is_numeric($data['order_by']) || $data['order_by'] < 0 || $data['order_by'] != (int)$data['order_by']) {
                echo '<script>alert("排序值必须为非负整数");window.location.href="admin.php?action=category&sub=edit&id=' . $id . '";</script>';
                exit;
            }
            
            // 防止将分类设置为自己的子分类
            if ($data['parent_id'] == $id) {
                echo '<script>alert("不能将分类设置为自己的子分类");window.location.href="admin.php?action=category&sub=edit&id=' . $id . '";</script>';
                exit;
            }
            // 验证别名唯一性（排除当前分类）
            if (!empty($data['slug'])) {
                $existingCategory = $categoryModel->getCategoryBySlug($data['slug']);
                if ($existingCategory && $existingCategory['id'] != $id) {
                    echo '<script>alert("别名已存在，请使用其他别名");window.location.href="admin.php?action=category&sub=edit&id=' . $id . '";</script>';
                    exit;
                }
                
                // 检查别名是否与其他分类的 ID 冲突（排除当前分类的 ID）
                if (is_numeric($data['slug']) && (int)$data['slug'] != $id && $categoryModel->exists((int)$data['slug'])) {
                    echo '<script>alert("别名不能与已有的分类 ID 重复，请使用其他别名");window.location.href="admin.php?action=category&sub=edit&id=' . $id . '";</script>';
                    exit;
                }
            }
            
            $result = $categoryModel->updateCategory($id, $data);
            if ($result) {
                // 记录分类更新日志
                Log::init();
                Log::info('更新分类', 'operation', ['category_id' => $id, 'category_name' => $_POST['name']]);
                
                echo '<script>alert("分类编辑成功");window.location.href="admin.php?action=category";</script>';
            } else {
                echo '<script>alert("分类编辑失败");window.location.href="admin.php?action=category&sub=edit&id=' . $id . '";</script>';
            }
        } else {
            // 显示编辑分类表单
            $category = $categoryModel->getCategoryById($id);
            if (!$category) {
                echo '<script>alert("分类不存在");window.location.href="admin.php?action=category";</script>';
                exit;
            }
            
            $categories = $categoryModel->getCategoriesList();
            include ADMIN_PATH . '/templates/category_edit.html';
        }
    }
    
    public function delete()
    {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if (!$id) {
            echo '<script>alert("参数错误");window.location.href="admin.php?action=category";</script>';
            exit;
        }
        
        $categoryModel = new CategoryModel();
        $category = $categoryModel->getCategoryById($id);
        if (!$category) {
            echo '<script>alert("分类不存在");window.location.href="admin.php?action=category";</script>';
            exit;
        }
        
        // 检查是否有子分类
        $childCategories = $categoryModel->getChildCategories($id);
        if (!empty($childCategories)) {
            echo '<script>alert("该分类下存在子分类，无法删除");window.location.href="admin.php?action=category";</script>';
            exit;
        }
        
        // 检查是否有文章
        $articleModel = new ArticleModel();
        $articleCount = $articleModel->getArticleCountByCategoryId($id);
        if ($articleCount > 0) {
            echo '<script>alert("该分类下存在文章，无法删除");window.location.href="admin.php?action=category";</script>';
            exit;
        }
        
        $result = $categoryModel->deleteCategory($id);
        if ($result) {
            echo '<script>alert("分类删除成功");window.location.href="admin.php?action=category";</script>';
        } else {
            echo '<script>alert("分类删除失败");window.location.href="admin.php?action=category";</script>';
        }
        exit;
    }
    
    public function updateOrder()
    {
        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            echo '<script>alert("非法请求");window.location.href="admin.php?action=category";</script>';
            exit;
        }
        
        $orderData = isset($_POST['order_by']) ? $_POST['order_by'] : [];
        if (empty($orderData)) {
            echo '<script>alert("排序数据为空");window.location.href="admin.php?action=category";</script>';
            exit;
        }
        // 验证所有排序值必须为非负整数
        foreach ($orderData as $id => $order) {
            if (!is_numeric($order) || $order < 0 || $order != (int)$order) {
                echo '<script>alert("排序值必须为非负整数");window.location.href="admin.php?action=category";</script>';
                exit;
            }
        }
        
        $categoryModel = new CategoryModel();
        foreach ($orderData as $id => $order) {
            $categoryModel->updateCategoryOrder($id, $order);
        }
        
        echo '<script>alert("排序更新成功");window.location.href="admin.php?action=category";</script>';
        exit;
    }
}
