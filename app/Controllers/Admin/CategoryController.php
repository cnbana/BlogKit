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
                respondWithToast("分类名称不能为空", "admin.php?action=category&sub=add");
                exit;
            }
            // 验证排序值必须为非负整数
            if (!is_numeric($data['order_by']) || $data['order_by'] < 0 || $data['order_by'] != (int)$data['order_by']) {
                respondWithToast("排序值必须为非负整数", "admin.php?action=category&sub=add");
                exit;
            }
            // 验证别名唯一性
            if (!empty($data['slug'])) {
                $existingCategory = $categoryModel->getCategoryBySlug($data['slug']);
                if ($existingCategory) {
                    respondWithToast("别名已存在，请使用其他别名", "admin.php?action=category&sub=add");
                    exit;
                }
                
                // 检查别名是否与已有的分类 ID 冲突
                if (is_numeric($data['slug']) && $categoryModel->exists((int)$data['slug'])) {
                    respondWithToast("别名不能与已有的分类 ID 重复，请使用其他别名", "admin.php?action=category&sub=add");
                    exit;
                }
            }
            
            $result = $categoryModel->addCategory($data);
            if ($result) {
                // 记录分类添加日志
                Log::init();
                Log::info('添加分类', 'operation', ['category_name' => $_POST['name'], 'category_id' => $result]);
                
                respondWithToast("分类添加成功", "admin.php?action=category");
            } else {
                respondWithToast("分类添加失败", "admin.php?action=category&sub=add");
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
                respondWithToast("分类名称不能为空", "admin.php?action=category&sub=edit&id=" . $id);
                exit;
            }
            // 验证排序值必须为非负整数
            if (!is_numeric($data['order_by']) || $data['order_by'] < 0 || $data['order_by'] != (int)$data['order_by']) {
                respondWithToast("排序值必须为非负整数", "admin.php?action=category&sub=edit&id=" . $id);
                exit;
            }
            
            // 防止将分类设置为自己的子分类
            if ($data['parent_id'] == $id) {
                respondWithToast("不能将分类设置为自己的子分类", "admin.php?action=category&sub=edit&id=" . $id);
                exit;
            }
            // 验证别名唯一性（排除当前分类）
            if (!empty($data['slug'])) {
                $existingCategory = $categoryModel->getCategoryBySlug($data['slug']);
                if ($existingCategory && $existingCategory['id'] != $id) {
                    respondWithToast("别名已存在，请使用其他别名", "admin.php?action=category&sub=edit&id=" . $id);
                    exit;
                }
                
                // 检查别名是否与其他分类的 ID 冲突（排除当前分类的 ID）
                if (is_numeric($data['slug']) && (int)$data['slug'] != $id && $categoryModel->exists((int)$data['slug'])) {
                    respondWithToast("别名不能与已有的分类 ID 重复，请使用其他别名", "admin.php?action=category&sub=edit&id=" . $id);
                    exit;
                }
            }
            
            $result = $categoryModel->updateCategory($id, $data);
            if ($result) {
                // 记录分类更新日志
                Log::init();
                Log::info('更新分类', 'operation', ['category_id' => $id, 'category_name' => $_POST['name']]);
                
                respondWithToast("分类编辑成功", "admin.php?action=category");
            } else {
                respondWithToast("分类编辑失败", "admin.php?action=category&sub=edit&id=" . $id);
            }
        } else {
            // 显示编辑分类表单
            $category = $categoryModel->getCategoryById($id);
            if (!$category) {
                respondWithToast("分类不存在", "admin.php?action=category");
                exit;
            }
            
            $categories = $categoryModel->getCategoriesList();
            include ADMIN_PATH . '/templates/category_edit.html';
        }
    }
    
    public function delete()
    {
        // 行内删除链接操作：统一走闪存 + 重定向（PRG），无中间提示页，
        // 回到列表页后由 flash_render 以统一 Toast 弹出结果
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if (!$id) {
            respondFlash(false, "参数错误", "admin.php?action=category");
        }

        $categoryModel = new CategoryModel();
        $category = $categoryModel->getCategoryById($id);
        if (!$category) {
            respondFlash(false, "分类不存在", "admin.php?action=category");
        }

        // 检查是否有子分类
        $childCategories = $categoryModel->getChildCategories($id);
        if (!empty($childCategories)) {
            respondFlash(false, "该分类下存在子分类，无法删除", "admin.php?action=category");
        }

        // 检查是否有文章
        $articleModel = new ArticleModel();
        $articleCount = $articleModel->getArticleCountByCategoryId($id);
        if ($articleCount > 0) {
            respondFlash(false, "该分类下存在文章，无法删除", "admin.php?action=category");
        }

        $result = $categoryModel->deleteCategory($id);
        respondFlash($result, $result ? "分类删除成功" : "分类删除失败", "admin.php?action=category");
    }

    /**
     * 批量删除分类
     *
     * 保护逻辑与单条 delete() 完全一致，逐条检查、逐条执行（循环内不做任何跳转）：
     * 1. 分类不存在 -> 跳过；
     * 2. 存在子分类 -> 跳过（避免删除后子分类悬空）；
     * 3. 分类下存在文章 -> 跳过（避免文章失去归属）；
     * 4. 以上检查通过才调用 deleteCategory()（模型内部还有一次子分类/文章兜底校验）。
     * 全部执行完后在返回消息中分别说明成功删除与被跳过的数量。
     */
    public function batchDelete()
    {
        // 批量删除仅接受 POST 提交（前端 submitBatchForm 统一以 POST + csrf_token 提交）
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respondFlash(false, "非法请求", "admin.php?action=category");
        }

        // 检查 CSRF 令牌，防止跨站伪造的批量删除请求
        $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
        if (!Security::validateCsrfToken($token)) {
            respondFlash(false, "非法请求", "admin.php?action=category");
        }

        $ids = isset($_POST['ids']) ? $_POST['ids'] : [];
        if (empty($ids)) {
            respondFlash(false, "请选择要删除的分类", "admin.php?action=category");
        }

        $categoryModel = new CategoryModel();
        $articleModel = new ArticleModel();
        $successCount = 0; // 成功删除的分类数量
        $skipCount = 0;    // 因关联保护（子分类/文章）或记录不存在而被跳过的数量

        foreach ($ids as $id) {
            $id = (int)$id;
            if (!$id) {
                $skipCount++;
                continue;
            }

            // 保护检查一：分类不存在时跳过（与单条 delete() 行为一致）
            $category = $categoryModel->getCategoryById($id);
            if (!$category) {
                $skipCount++;
                continue;
            }

            // 保护检查二：存在子分类的分类不允许删除（与单条 delete() 行为一致）
            if (!empty($categoryModel->getChildCategories($id))) {
                $skipCount++;
                continue;
            }

            // 保护检查三：分类下存在文章时不允许删除（与单条 delete() 行为一致）
            if ($articleModel->getArticleCountByCategoryId($id) > 0) {
                $skipCount++;
                continue;
            }

            // 通过全部保护检查后执行删除；模型层兜底校验失败同样计入跳过数
            if ($categoryModel->deleteCategory($id)) {
                $successCount++;
            } else {
                $skipCount++;
            }
        }

        // 记录批量删除日志（沿用本控制器的日志风格：消息 + 类型 + 上下文数组）
        Log::init();
        Log::info('批量删除分类', 'operation', [
            'category_ids' => $ids,
            'success_count' => $successCount,
            'skip_count' => $skipCount
        ]);

        // 汇总消息：成功与跳过数量分开说明，便于用户了解哪些分类因关联保护未删除
        if ($successCount > 0 && $skipCount > 0) {
            respondFlash(true, "成功删除 {$successCount} 个分类，跳过 {$skipCount} 个（存在子分类、文章或已不存在）", "admin.php?action=category");
        } elseif ($successCount > 0) {
            respondFlash(true, "成功删除 {$successCount} 个分类", "admin.php?action=category");
        } else {
            respondFlash(false, "没有分类被删除（所选分类存在子分类、文章或已不存在）", "admin.php?action=category");
        }
    }
    
    /**
     * 输出保存排序的结果并结束请求
     *
     * AJAX 请求（前端「保存排序」按钮附带 ajax=1 参数）返回 JSON，
     * 由前端 showToast 在当前页原地提示；普通表单提交回退为闪存 + 重定向
     */
    private function orderResult($success, $message, $isAjax)
    {
        if ($isAjax) {
            // JSON 响应：消息文本经 json_encode 转义并保留中文
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => $success, 'message' => $message], JSON_UNESCAPED_UNICODE);
        } else {
            respondFlash($success, $message, "admin.php?action=category");
        }
        exit;
    }

    public function updateOrder()
    {
        // 是否为前端 AJAX 请求（分类列表页「保存排序」按钮提交时附带 ajax=1 参数）
        $isAjax = isset($_POST['ajax']);

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            $this->orderResult(false, "非法请求", $isAjax);
        }

        $orderData = isset($_POST['order_by']) ? $_POST['order_by'] : [];
        if (empty($orderData)) {
            $this->orderResult(false, "排序数据为空", $isAjax);
        }
        // 验证所有排序值必须为非负整数
        foreach ($orderData as $id => $order) {
            if (!is_numeric($order) || $order < 0 || $order != (int)$order) {
                $this->orderResult(false, "排序值必须为非负整数", $isAjax);
            }
        }

        $categoryModel = new CategoryModel();
        foreach ($orderData as $id => $order) {
            $categoryModel->updateCategoryOrder($id, $order);
        }

        $this->orderResult(true, "排序更新成功", $isAjax);
    }
}
