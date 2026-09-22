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
 * 分类API控制器
 * 提供分类相关的API接口
 * 
 * 基础URL: /api.php/v1/categories
 */

class CategoryApiController extends ApiController {
    
    /**
     * 获取分类列表
     * GET /api.php/v1/categories
     * 
     * 查询参数：
     * - page: 页码（默认1）
     * - page_size: 每页数量（默认读取后台配置）
     */
    public function index() {        
        $page = $this->getIntParam('page', 1);
        $pageSize = $this->getPageSize();
        
        $categoryModel = new CategoryModel();
        $categories = $categoryModel->getCategories($page, $pageSize);
        $total = $categoryModel->getCategoryCount();
        
        $this->paginate($categories, $total, $page, $pageSize, '获取成功');
    }
    
    /**
     * 获取分类详情
     * GET /api.php/v1/categories/{id}
     */
    public function show($id = 0) {
        if (is_array($id)) {
            $categoryId = isset($id['id']) ? intval($id['id']) : 0;
        } else {
            $categoryId = intval($id);
        }
        
        if ($categoryId <= 0) {
            $this->error('分类ID无效', 400);
            return;
        }
        
        $categoryModel = new CategoryModel();
        $category = $categoryModel->getCategoryById($categoryId);
        
        if (!$category) {
            $this->error('分类不存在', 404);
            return;
        }
        
        $this->success($category, '获取成功');
    }
    
    /**
     * 创建分类
     * POST /api.php/v1/categories
     * 
     * 请求体：
     * - name: 分类名称（必填）
     * - slug: 分类别名（可选）
     * - description: 描述（可选）
     * - parent_id: 父分类ID（可选）
     */
    public function store() {        $this->requireLogin();
        
        if (!$this->isAdmin) {
            $this->error('无权创建分类', 403);
            return;
        }
        
        $this->validateRules([
            'name' => ['required' => true, 'max' => 100]
        ]);
        
        $name = $this->getStringParam('name');
        $slug = $this->getStringParam('slug');
        $description = $this->getStringParam('description');
        $parentId = $this->getIntParam('parent_id', 0);
        
        $categoryModel = new CategoryModel();
        
        $categoryData = [
            'name' => $name,
            'slug' => $slug ?: $name,
            'description' => $description,
            'parent_id' => $parentId
        ];
        
        $categoryId = $categoryModel->createCategory($categoryData);
        
        if ($categoryId) {
            $this->success(['category_id' => $categoryId], '分类创建成功');
        } else {
            $this->error('分类创建失败');
        }
    }
    
    /**
     * 更新分类
     * PUT/PATCH /api.php/v1/categories/{id}
     */
    public function update($id = 0) {
        $this->requireLogin();
        
        if (!$this->isAdmin) {
            $this->error('无权修改分类', 403);
            return;
        }
        
        if (is_array($id)) {
            $categoryId = isset($id['id']) ? intval($id['id']) : 0;
        } else {
            $categoryId = intval($id);
        }
        
        if ($categoryId <= 0) {
            $this->error('分类ID无效', 400);
            return;
        }
        
        $categoryModel = new CategoryModel();
        $category = $categoryModel->getCategoryById($categoryId);
        
        if (!$category) {
            $this->error('分类不存在', 404);
            return;
        }
        
        $name = $this->getStringParam('name');
        $slug = $this->getStringParam('slug');
        $description = $this->getStringParam('description');
        $parentId = $this->getIntParam('parent_id');
        
        $categoryData = [];
        if (!empty($name)) {
            $categoryData['name'] = $name;
        }
        if (!empty($slug)) {
            $categoryData['slug'] = $slug;
        }
        if (!empty($description)) {
            $categoryData['description'] = $description;
        }
        if ($parentId !== null) {
            $categoryData['parent_id'] = $parentId;
        }
        
        $result = $categoryModel->updateCategory($categoryId, $categoryData);
        
        if ($result) {
            $this->success(null, '分类更新成功');
        } else {
            $this->error('分类更新失败');
        }
    }
    
    /**
     * 删除分类
     * DELETE /api.php/v1/categories/{id}
     */
    public function destroy($id = 0) {
        $this->requireLogin();
        
        if (!$this->isAdmin) {
            $this->error('无权删除分类', 403);
            return;
        }
        
        if (is_array($id)) {
            $categoryId = isset($id['id']) ? intval($id['id']) : 0;
        } else {
            $categoryId = intval($id);
        }
        
        if ($categoryId <= 0) {
            $this->error('分类ID无效', 400);
            return;
        }
        
        $categoryModel = new CategoryModel();
        $category = $categoryModel->getCategoryById($categoryId);
        
        if (!$category) {
            $this->error('分类不存在', 404);
            return;
        }
        
        if (!$this->isAdmin) {
            $this->error('无权删除分类', 403);
            return;
        }
        
        $result = $categoryModel->deleteCategory($categoryId);
        
        if ($result) {
            $this->success(null, '分类删除成功');
        } else {
            $this->error('分类删除失败');
        }
    }
}
