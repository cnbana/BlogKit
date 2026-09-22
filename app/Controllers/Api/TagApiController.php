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
 * 标签API控制器
 * 提供标签相关的API接口
 * 
 * 基础URL: /api.php/v1/tags
 */

class TagApiController extends ApiController {
    
    /**
     * 获取标签列表
     * GET /api.php/v1/tags
     * 
     * 查询参数：
     * - page: 页码（默认1）
     * - page_size: 每页数量（默认读取后台配置）
     */
    public function index() {        
        $page = $this->getIntParam('page', 1);
        $pageSize = $this->getPageSize();
        
        $tagModel = new TagModel();
        $tags = $tagModel->getTags($page, $pageSize);
        $total = $tagModel->getTagCount();
        
        $this->paginate($tags, $total, $page, $pageSize, '获取成功');
    }
    
    /**
     * 获取标签详情
     * GET /api.php/v1/tags/{id}
     */
    public function show($id = 0) {
        if (is_array($id)) {
            $tagId = isset($id['id']) ? intval($id['id']) : 0;
        } else {
            $tagId = intval($id);
        }
        
        if ($tagId <= 0) {
            $this->error('标签ID无效', 400);
            return;
        }
        
        $tagModel = new TagModel();
        $tag = $tagModel->getTagById($tagId);
        
        if (!$tag) {
            $this->error('标签不存在', 404);
            return;
        }
        
        $this->success($tag, '获取成功');
    }
    
    /**
     * 创建标签
     * POST /api.php/v1/tags
     * 
     * 请求体：
     * - name: 标签名称（必填）
     * - slug: 标签别名（可选）
     * - description: 描述（可选）
     */
    public function store() {        $this->requireLogin();
        
        if (!$this->isAdmin) {
            $this->error('无权创建标签', 403);
            return;
        }
        
        $this->validateRules([
            'name' => ['required' => true, 'max' => 100]
        ]);
        
        $name = $this->getStringParam('name');
        $slug = $this->getStringParam('slug');
        $description = $this->getStringParam('description');
        
        $tagModel = new TagModel();
        
        $tagData = [
            'name' => $name,
            'slug' => $slug ?: $name,
            'description' => $description
        ];
        
        $tagId = $tagModel->createTag($tagData);
        
        if ($tagId) {
            $this->success(['tag_id' => $tagId], '标签创建成功');
        } else {
            $this->error('标签创建失败');
        }
    }
    
    /**
     * 更新标签
     * PUT/PATCH /api.php/v1/tags/{id}
     */
    public function update($id = 0) {
        $this->requireLogin();
        
        if (!$this->isAdmin) {
            $this->error('无权修改标签', 403);
            return;
        }
        
        if (is_array($id)) {
            $tagId = isset($id['id']) ? intval($id['id']) : 0;
        } else {
            $tagId = intval($id);
        }
        
        if ($tagId <= 0) {
            $this->error('标签ID无效', 400);
            return;
        }
        
        $tagModel = new TagModel();
        $tag = $tagModel->getTagById($tagId);
        
        if (!$tag) {
            $this->error('标签不存在', 404);
            return;
        }
        
        $name = $this->getStringParam('name');
        $slug = $this->getStringParam('slug');
        $description = $this->getStringParam('description');
        
        $tagData = [];
        if (!empty($name)) {
            $tagData['name'] = $name;
        }
        if (!empty($slug)) {
            $tagData['slug'] = $slug;
        }
        if (!empty($description)) {
            $tagData['description'] = $description;
        }
        
        $result = $tagModel->updateTag($tagId, $tagData);
        
        if ($result) {
            $this->success(null, '标签更新成功');
        } else {
            $this->error('标签更新失败');
        }
    }
    
    /**
     * 删除标签
     * DELETE /api.php/v1/tags/{id}
     */
    public function destroy($id = 0) {
        $this->requireLogin();
        
        if (!$this->isAdmin) {
            $this->error('无权删除标签', 403);
            return;
        }
        
        if (is_array($id)) {
            $tagId = isset($id['id']) ? intval($id['id']) : 0;
        } else {
            $tagId = intval($id);
        }
        
        if ($tagId <= 0) {
            $this->error('标签ID无效', 400);
            return;
        }
        
        $tagModel = new TagModel();
        $tag = $tagModel->getTagById($tagId);
        
        if (!$tag) {
            $this->error('标签不存在', 404);
            return;
        }
        
        $result = $tagModel->deleteTag($tagId);
        
        if ($result) {
            $this->success(null, '标签删除成功');
        } else {
            $this->error('标签删除失败');
        }
    }
}
