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


// 包含页面模型

// 包含插件类
if (!class_exists('Plugin')) {
}

// 加载日志类

class PageController {
    private $pageModel;
    
    /**
     * 构造函数
     */
    public function __construct() {
        $this->pageModel = new PageModel();
    }
    
    /**
     * 页面列表
     */
    public function index() {
        // 获取分页参数（limit 白名单校验）
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $page = max(1, $page);
        $limit = ListQuery::pageSize();
        $allowedPageSizes = ListQuery::pageSizes();
        
        // 表头排序参数：白名单校验（模型内再做二次校验）
        list($sort, $order) = ListQuery::sort(['id', 'created_at', 'updated_at', 'title']);

        // 获取所有页面
        $pages = $this->pageModel->getAllPages($page, $limit, $sort, $order);
        $total = $this->pageModel->getPageCount();
        $totalPages = ceil($total / $limit);
        
        // 传递数据到模板
        $data = [
            'pages' => $pages,
            'title' => '页面管理',
            'currentAction' => 'page'
        ];
        
        // 渲染模板
        include ADMIN_PATH . '/templates/page.html';
    }
    
    /**
     * 添加页面
     */
    public function add() {
        // 触发页面添加页面加载前的钩子
        Plugin::triggerHook('page_add_before');
        
        // 检查是否提交了表单
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // 触发页面保存前的钩子
            Plugin::triggerHook('page_save_before', array('data' => $_POST));
            
            // 获取别名
            $slug = trim($_POST['slug']);
            
            // 验证别名唯一性
            if (!empty($slug)) {
                // 检查别名是否已存在
                $existingPage = $this->pageModel->getPageBySlug($slug);
                if ($existingPage) {
                    echo '<script>alert("别名已存在，请使用其他别名！");window.location.href="admin.php?action=page&method=add";</script>';
                    exit;
                }
                
                // 检查别名是否与已有的页面ID冲突
                if (is_numeric($slug) && $this->pageModel->exists((int)$slug)) {
                    echo '<script>alert("别名不能与已有的页面ID重复，请使用其他别名！");window.location.href="admin.php?action=page&method=add";</script>';
                    exit;
                }
            }
            
            // 处理关键词，自动将中文逗号和空格转换为英文逗号
            $keywords = isset($_POST['keywords']) ? $_POST['keywords'] : '';
            $keywords = str_replace(['，', ' ', '　'], ',', $keywords);
            $keywords = preg_replace('/,+/', ',', $keywords);
            $keywords = trim($keywords, ',');
            
            // 获取表单数据
            $data = [
                'title' => $_POST['title'],
                'content' => $_POST['content'],
                'description' => $_POST['description'] ?? '',
                'keywords' => $keywords,
                'slug' => $slug,
                'status' => $_POST['status'] ?? 1,
                'is_menu' => $_POST['is_menu'] ?? 0
            ];
            
            // 添加页面
            $result = $this->pageModel->createPage($data);
            
            if ($result) {
                Log::info('页面管理', '添加页面', '成功添加页面: ' . $data['title'], Log::CATEGORY_OPERATION);
                // 设置成功消息
                $_SESSION['success_message'] = '页面添加成功！';
                
                // 重定向到页面列表
                header('Location: admin.php?action=page');
                exit;
            } else {
                Log::error('页面管理', '添加页面', '页面添加失败: ' . $data['title'], Log::CATEGORY_OPERATION);
                // 设置错误消息
                $_SESSION['error_message'] = '页面添加失败，请检查表单数据！';
            }
        }
        
        // 渲染添加页面模板
        include ADMIN_PATH . '/templates/page_add.html';
        
        // 触发页面添加页面渲染后的钩子
        Plugin::triggerHook('page_add_after');
    }
    
    /**
     * 编辑页面
     */
    public function edit() {
        // 获取页面ID
        $id = $_GET['id'];
        
        // 获取页面信息
        $page = $this->pageModel->getPageById($id);
        
        if (!$page) {
            // 设置错误消息
            $_SESSION['error_message'] = '页面不存在！';
            
            // 重定向到页面列表
            header('Location: admin.php?action=page');
            exit;
        }
        
        // 触发页面编辑页面加载前的钩子
        Plugin::triggerHook('page_edit_before', array('page_id' => $id));
        
        // 检查是否提交了表单
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // 触发页面保存前的钩子
            Plugin::triggerHook('page_save_before', array('data' => $_POST));
            
            // 获取别名
            $slug = trim($_POST['slug']);
            
            // 验证别名唯一性
            if (!empty($slug)) {
                // 检查别名是否已存在（排除当前页面）
                $existingPage = $this->pageModel->getPageBySlug($slug);
                if ($existingPage && $existingPage['id'] != $id) {
                    echo '<script>alert("别名已存在，请使用其他别名！");window.location.href="admin.php?action=page&method=edit&id=' . $id . '";</script>';
                    exit;
                }
                
                // 检查别名是否与其他页面的ID冲突
                if (is_numeric($slug) && (int)$slug != $id && $this->pageModel->exists((int)$slug)) {
                    echo '<script>alert("别名不能与已有的页面ID重复，请使用其他别名！");window.location.href="admin.php?action=page&method=edit&id=' . $id . '";</script>';
                    exit;
                }
            }
            
            // 处理关键词，自动将中文逗号和空格转换为英文逗号
            $keywords = isset($_POST['keywords']) ? $_POST['keywords'] : '';
            $keywords = str_replace(['，', ' ', '　'], ',', $keywords);
            $keywords = preg_replace('/,+/', ',', $keywords);
            $keywords = trim($keywords, ',');
            
            // 获取表单数据
            $data = [
                'title' => $_POST['title'],
                'content' => $_POST['content'],
                'description' => $_POST['description'] ?? '',
                'keywords' => $keywords,
                'slug' => $slug,
                'status' => $_POST['status'] ?? 1,
                'is_menu' => $_POST['is_menu'] ?? 0
            ];
            
            // 更新页面
            $result = $this->pageModel->updatePage($id, $data);
            
            if ($result) {
                Log::info('页面管理', '编辑页面', '成功编辑页面: ' . $data['title'] . ' (ID: ' . $id . ')', Log::CATEGORY_OPERATION);
                // 设置成功消息
                $_SESSION['success_message'] = '页面更新成功！';
                
                // 重定向到页面列表
                header('Location: admin.php?action=page');
                exit;
            } else {
                Log::error('页面管理', '编辑页面', '页面更新失败: ID ' . $id, Log::CATEGORY_OPERATION);
                // 设置错误消息
                $_SESSION['error_message'] = '页面更新失败，请检查表单数据！';
            }
        }
        
        // 渲染编辑页面模板
        include ADMIN_PATH . '/templates/page_edit.html';
        
        // 触发页面编辑页面渲染后的钩子
        Plugin::triggerHook('page_edit_after', array('page_id' => $id));
    }
    
    /**
     * 删除页面
     */
    public function delete() {
        // 获取页面ID
        $id = $_GET['id'];
        
        // 获取页面信息
        $page = $this->pageModel->getPageById($id);
        
        if (!$page) {
            // 设置错误消息
            $_SESSION['error_message'] = '页面不存在！';
            
            // 重定向到页面列表
            header('Location: admin.php?action=page');
            exit;
        }
        
        // 删除页面
        $page = $this->pageModel->getPageById($id);
        $result = $this->pageModel->deletePage($id);
        
        if ($result) {
            Log::info('页面管理', '删除页面', '成功删除页面: ' . $page['title'] . ' (ID: ' . $id . ')', Log::CATEGORY_OPERATION);
            // 设置成功消息
            $_SESSION['success_message'] = '页面删除成功！';
        } else {
            Log::error('页面管理', '删除页面', '页面删除失败: ID ' . $id, Log::CATEGORY_OPERATION);
            // 设置错误消息
            $_SESSION['error_message'] = '页面删除失败！';
        }
        
        // 重定向到页面列表
        header('Location: admin.php?action=page');
        exit;
    }
}