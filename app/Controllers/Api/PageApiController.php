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
 * 页面API控制器
 * 提供页面相关的API接口
 * 
 * 基础URL: /api.php/v1/pages
 */

class PageApiController extends ApiController {
    
    /**
     * 获取页面列表
     * GET /api.php/v1/pages
     * 
     * 查询参数：
     * - page: 页码（默认1）
     * - page_size: 每页数量（默认读取后台配置）
     */
    public function index() {        
        $page = $this->getIntParam('page', 1);
        $pageSize = $this->getPageSize();
        
        $pageModel = new PageModel();
        $pages = $pageModel->getPages($page, $pageSize);
        $total = $pageModel->getPageCount();
        
        $this->paginate($pages, $total, $page, $pageSize, '获取成功');
    }
    
    /**
     * 获取页面详情
     * GET /api.php/v1/pages/{id}
     */
    public function show($id = 0) {
        if (is_array($id)) {
            $pageId = isset($id['id']) ? intval($id['id']) : 0;
        } else {
            $pageId = intval($id);
        }
        
        if ($pageId <= 0) {
            $this->error('页面ID无效', 400);
            return;
        }
        
        $pageModel = new PageModel();
        $page = $pageModel->getPageById($pageId);
        
        if (!$page) {
            $this->error('页面不存在', 404);
            return;
        }
        
        $this->success($page, '获取成功');
    }
    
    /**
     * 创建页面
     * POST /api.php/v1/pages
     * 
     * 请求体：
     * - title: 标题（必填）
     * - content: 内容（必填）
     * - slug: 别名（可选）
     * - status: 状态（0-草稿，1-发布）
     */
    public function store() {        $this->requireLogin();
        
        if (!$this->isAdmin) {
            $this->error('无权创建页面', 403);
            return;
        }
        
        $this->validateRules([
            'title' => ['required' => true, 'max' => 200],
            'content' => ['required' => true]
        ]);
        
        $title = $this->getStringParam('title');
        $content = $this->getStringParam('content');
        $slug = $this->getStringParam('slug');
        $status = $this->getIntParam('status', 0);
        
        $pageModel = new PageModel();
        
        $pageData = [
            'title' => $title,
            'content' => $content,
            'slug' => $slug ?: $title,
            'status' => $status,
            'user_id' => $this->currentUser['id']
        ];
        
        $pageId = $pageModel->createPage($pageData);
        
        if ($pageId) {
            $this->success(['page_id' => $pageId], '页面创建成功');
        } else {
            $this->error('页面创建失败');
        }
    }
    
    /**
     * 更新页面
     * PUT/PATCH /api.php/v1/pages/{id}
     */
    public function update($id = 0) {
        $this->requireLogin();
        
        if (!$this->isAdmin) {
            $this->error('无权修改页面', 403);
            return;
        }
        
        if (is_array($id)) {
            $pageId = isset($id['id']) ? intval($id['id']) : 0;
        } else {
            $pageId = intval($id);
        }
        
        if ($pageId <= 0) {
            $this->error('页面ID无效', 400);
            return;
        }
        
        $pageModel = new PageModel();
        $page = $pageModel->getPageById($pageId);
        
        if (!$page) {
            $this->error('页面不存在', 404);
            return;
        }
        
        $title = $this->getStringParam('title');
        $content = $this->getStringParam('content');
        $slug = $this->getStringParam('slug');
        $status = $this->getIntParam('status');
        
        $pageData = [];
        if (!empty($title)) {
            $pageData['title'] = $title;
        }
        if (!empty($content)) {
            $pageData['content'] = $content;
        }
        if (!empty($slug)) {
            $pageData['slug'] = $slug;
        }
        if ($status !== null) {
            $pageData['status'] = $status;
        }
        
        $result = $pageModel->updatePage($pageId, $pageData);
        
        if ($result) {
            $this->success(null, '页面更新成功');
        } else {
            $this->error('页面更新失败');
        }
    }
    
    /**
     * 删除页面
     * DELETE /api.php/v1/pages/{id}
     */
    public function destroy($id = 0) {
        $this->requireLogin();
        
        if (!$this->isAdmin) {
            $this->error('无权删除页面', 403);
            return;
        }
        
        if (is_array($id)) {
            $pageId = isset($id['id']) ? intval($id['id']) : 0;
        } else {
            $pageId = intval($id);
        }
        
        if ($pageId <= 0) {
            $this->error('页面ID无效', 400);
            return;
        }
        
        $pageModel = new PageModel();
        $page = $pageModel->getPageById($pageId);
        
        if (!$page) {
            $this->error('页面不存在', 404);
            return;
        }
        
        $result = $pageModel->deletePage($pageId);
        
        if ($result) {
            $this->success(null, '页面删除成功');
        } else {
            $this->error('页面删除失败');
        }
    }
}
