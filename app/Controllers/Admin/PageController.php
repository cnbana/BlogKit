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
                    respondWithToast("别名已存在，请使用其他别名！", "admin.php?action=page&method=add");
                    exit;
                }
                
                // 检查别名是否与已有的页面ID冲突
                if (is_numeric($slug) && $this->pageModel->exists((int)$slug)) {
                    respondWithToast("别名不能与已有的页面ID重复，请使用其他别名！", "admin.php?action=page&method=add");
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
                Log::info('页面管理 - 添加页面：成功添加页面: ' . $data['title'], Log::CATEGORY_OPERATION);
                // 设置成功消息
                $_SESSION['success_message'] = '页面添加成功！';
                
                // 重定向到页面列表
                header('Location: admin.php?action=page');
                exit;
            } else {
                Log::error('页面管理 - 添加页面：页面添加失败: ' . $data['title'], Log::CATEGORY_OPERATION);
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
                    respondWithToast("别名已存在，请使用其他别名！", "admin.php?action=page&method=edit&id=" . $id);
                    exit;
                }
                
                // 检查别名是否与其他页面的ID冲突
                if (is_numeric($slug) && (int)$slug != $id && $this->pageModel->exists((int)$slug)) {
                    respondWithToast("别名不能与已有的页面ID重复，请使用其他别名！", "admin.php?action=page&method=edit&id=" . $id);
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
                Log::info('页面管理 - 编辑页面：成功编辑页面: ' . $data['title'] . ' (ID: ' . $id . ')', Log::CATEGORY_OPERATION);
                // 设置成功消息
                $_SESSION['success_message'] = '页面更新成功！';
                
                // 重定向到页面列表
                header('Location: admin.php?action=page');
                exit;
            } else {
                Log::error('页面管理 - 编辑页面：页面更新失败: ID ' . $id, Log::CATEGORY_OPERATION);
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
            Log::info('页面管理 - 删除页面：成功删除页面: ' . $page['title'] . ' (ID: ' . $id . ')', Log::CATEGORY_OPERATION);
            // 设置成功消息
            $_SESSION['success_message'] = '页面删除成功！';
        } else {
            Log::error('页面管理 - 删除页面：页面删除失败: ID ' . $id, Log::CATEGORY_OPERATION);
            // 设置错误消息
            $_SESSION['error_message'] = '页面删除失败！';
        }
        
        // 重定向到页面列表
        header('Location: admin.php?action=page');
        exit;
    }

    /**
     * 批量修改页面状态（批量发布 / 批量设为草稿）
     *
     * 复用 PageModel::updatePageStatus() 只更新 status 字段（同时刷新 updated_at），
     * 状态值仅接受 1（已发布）/ 0（草稿）。消息机制沿用本控制器 delete() 的
     * $_SESSION['success_message'] / $_SESSION['error_message'] + 重定向（由 flash 渲染 Toast）。
     */
    public function batchStatus() {
        // 批量操作仅接受 POST 提交（前端 submitBatchForm 统一以 POST + csrf_token 提交）
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = '非法请求！';
            header('Location: admin.php?action=page');
            exit;
        }

        // 检查 CSRF 令牌，防止跨站伪造的批量操作请求
        $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
        if (!Security::validateCsrfToken($token)) {
            $_SESSION['error_message'] = '非法请求！';
            header('Location: admin.php?action=page');
            exit;
        }

        $ids = isset($_POST['ids']) ? $_POST['ids'] : [];
        if (empty($ids)) {
            $_SESSION['error_message'] = '请选择要操作的页面！';
            header('Location: admin.php?action=page');
            exit;
        }

        $status = isset($_POST['status']) ? (int)$_POST['status'] : 1;
        // 状态值仅允许 1（发布）/ 0（草稿），越界值一律按发布处理
        if (!in_array($status, [0, 1], true)) {
            $status = 1;
        }
        $statusText = $status == 1 ? '已发布' : '草稿';

        $successCount = 0; // 成功更新状态的页面数量
        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id > 0 && $this->pageModel->updatePageStatus($id, $status)) {
                $successCount++;
            }
        }

        // 记录批量状态修改日志（沿用本控制器的日志风格：模块 + 动作 + 说明 + 分类）
        if ($successCount > 0) {
            Log::info('页面管理 - 批量修改状态：成功批量将 ' . $successCount . ' 个页面状态设为「' . $statusText . '」', Log::CATEGORY_OPERATION);
            $_SESSION['success_message'] = '成功将 ' . $successCount . ' 个页面设为' . $statusText . '！';
        } else {
            Log::error('页面管理 - 批量修改状态：批量修改页面状态失败', Log::CATEGORY_OPERATION);
            $_SESSION['error_message'] = '页面状态更新失败！';
        }

        // 重定向到页面列表
        header('Location: admin.php?action=page');
        exit;
    }

    /**
     * 批量删除页面
     *
     * 与单条 delete() 语义完全一致：bk_page 表没有软删字段，均为硬删除，
     * 且单条删除没有关联保护检查，因此批量直接逐条调用 PageModel::deletePage()。
     * 循环内不做任何跳转，全部执行完后统一提示成功删除的数量。
     */
    public function batchDelete() {
        // 批量删除仅接受 POST 提交（前端 submitBatchForm 统一以 POST + csrf_token 提交）
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = '非法请求！';
            header('Location: admin.php?action=page');
            exit;
        }

        // 检查 CSRF 令牌，防止跨站伪造的批量删除请求
        $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
        if (!Security::validateCsrfToken($token)) {
            $_SESSION['error_message'] = '非法请求！';
            header('Location: admin.php?action=page');
            exit;
        }

        $ids = isset($_POST['ids']) ? $_POST['ids'] : [];
        if (empty($ids)) {
            $_SESSION['error_message'] = '请选择要删除的页面！';
            header('Location: admin.php?action=page');
            exit;
        }

        $successCount = 0; // 成功删除的页面数量
        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id > 0 && $this->pageModel->deletePage($id)) {
                $successCount++;
            }
        }

        // 记录批量删除日志（沿用本控制器的日志风格：模块 + 动作 + 说明 + 分类）
        if ($successCount > 0) {
            Log::info('页面管理 - 批量删除页面：成功批量删除页面 ' . $successCount . ' 个', Log::CATEGORY_OPERATION);
            $_SESSION['success_message'] = '成功删除 ' . $successCount . ' 个页面！';
        } else {
            Log::error('页面管理 - 批量删除页面：批量删除页面失败', Log::CATEGORY_OPERATION);
            $_SESSION['error_message'] = '页面删除失败！';
        }

        // 重定向到页面列表
        header('Location: admin.php?action=page');
        exit;
    }
}