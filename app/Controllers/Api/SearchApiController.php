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
 * 搜索API控制器
 * 提供搜索相关的API接口
 * 
 * 基础URL: /api.php/v1/search
 */

class SearchApiController extends ApiController {
    
    /**
     * 搜索内容
     * GET /api.php/v1/search
     * 
     * 查询参数：
     * - keyword: 搜索关键词（必填）
     * - type: 搜索类型（article-文章，user-用户，comment-评论，默认article）
     * - page: 页码（默认1）
     * - page_size: 每页数量（默认读取后台配置）
     */
    public function index() {        
        $keyword = $this->getStringParam('keyword');
        $type = $this->getStringParam('type', 'article');
        $page = $this->getIntParam('page', 1);
        $pageSize = $this->getPageSize();
        
        if (empty($keyword)) {
            $this->error('搜索关键词不能为空', 400);
            return;
        }
        
        switch ($type) {
            case 'article':
                $articleModel = new ArticleModel();
                $results = $articleModel->searchArticles($keyword, $page, $pageSize);
                $total = $articleModel->getSearchArticleCount($keyword);
                break;
                
            case 'user':
                $userModel = new UserModel();
                $results = $userModel->searchUsers($keyword, $page, $pageSize);
                $total = $userModel->getSearchUserCount($keyword);
                break;
                
            case 'comment':
                $commentModel = new CommentModel();
                $results = $commentModel->searchComments($keyword, $page, $pageSize);
                $total = $commentModel->getSearchCommentCount($keyword);
                break;
                
            default:
                $articleModel = new ArticleModel();
                $results = $articleModel->searchArticles($keyword, $page, $pageSize);
                $total = $articleModel->getSearchArticleCount($keyword);
                break;
        }
        
        $this->paginate($results, $total, $page, $pageSize, '搜索成功');
    }
}
