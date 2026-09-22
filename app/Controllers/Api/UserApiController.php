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
 * 用户API控制器
 * 提供用户相关的API接口
 * 
 * 基础URL: /api.php/v1/users
 */

class UserApiController extends ApiController {
    
    /**
     * 获取用户列表
     * GET /api.php/v1/users
     * 
     * 查询参数：
     * - page: 页码（默认1）
     * - page_size: 每页数量（默认读取后台配置）
     */
    public function index() {        
        $page = $this->getIntParam('page', 1);
        $pageSize = $this->getPageSize();
        
        $userModel = new UserModel();
        
        $users = $userModel->getUsers($page, $pageSize);
        $total = $userModel->getUserCount();
        
        $this->paginate($users, $total, $page, $pageSize, '获取成功');
    }
    
    /**
     * 获取用户详情
     * GET /api.php/v1/users/{id}
     */
    public function show($id = 0) {
        if (is_array($id)) {
            $userId = isset($id['id']) ? intval($id['id']) : 0;
        } else {
            $userId = intval($id);
        }
        
        if ($userId <= 0) {
            $this->error('用户ID无效', 400);
            return;
        }
        
        $userModel = new UserModel();
        $user = $userModel->getUserById($userId);
        
        if (!$user) {
            $this->error('用户不存在', 404);
            return;
        }
        
        $this->success($user, '获取成功');
    }
    
    /**
     * 注册用户
     * POST /api.php/v1/users
     * 
     * 请求体：
     * - username: 用户名（必填）
     * - password: 密码（必填）
     * - email: 邮箱（必填）
     * - nickname: 昵称（可选）
     */
    public function store() {        
        $this->validateRules([
            'username' => ['required' => true, 'min' => 3, 'max' => 50],
            'password' => ['required' => true, 'min' => 6, 'max' => 50],
            'email' => ['required' => true, 'type' => 'email']
        ]);
        
        $username = $this->getStringParam('username');
        $password = $this->getStringParam('password');
        $email = $this->getStringParam('email');
        $nickname = $this->getStringParam('nickname');
        
        $userModel = new UserModel();
        
        $userId = $userModel->createUser([
            'username' => $username,
            'password' => $password,
            'email' => $email,
            'nickname' => $nickname ?: $username
        ]);
        
        if ($userId) {
            $this->success(['user_id' => $userId], '注册成功');
        } else {
            $this->error('注册失败');
        }
    }
    
    /**
     * 更新用户信息
     * PUT/PATCH /api.php/v1/users/{id}
     */
    public function update() {        $this->requireLogin();
        
        $userId = $this->getIntParam('id', 0);
        
        if ($userId <= 0) {
            $this->error('用户ID无效', 400);
            return;
        }
        
        if ($userId != $this->currentUser['id']) {
            $this->error('无权修改此用户', 403);
            return;
        }
        
        $email = $this->getStringParam('email');
        $nickname = $this->getStringParam('nickname');
        
        $userModel = new UserModel();
        
        $userData = [];
        if (!empty($email)) {
            $userData['email'] = $email;
        }
        if (!empty($nickname)) {
            $userData['nickname'] = $nickname;
        }
        
        $result = $userModel->updateUser($userId, $userData);
        
        if ($result) {
            $this->success(null, '更新成功');
        } else {
            $this->error('更新失败');
        }
    }
    
    /**
     * 删除用户
     * DELETE /api.php/v1/users/{id}
     */
    public function destroy() {        $this->requireLogin();
        
        $userId = $this->getIntParam('id', 0);
        
        if ($userId <= 0) {
            $this->error('用户ID无效', 400);
            return;
        }
        
        if ($userId == $this->currentUser['id']) {
            $this->error('不能删除自己', 400);
            return;
        }
        
        if (!$this->isAdmin) {
            $this->error('无权删除用户', 403);
            return;
        }
        
        $userModel = new UserModel();
        $result = $userModel->deleteUser($userId);
        
        if ($result) {
            $this->success(null, '删除成功');
        } else {
            $this->error('删除失败');
        }
    }
    
    /**
     * 关注用户
     * POST /api.php/v1/users/{id}/follow
     */
    public function follow() {        $this->requireLogin();
        
        $targetUserId = $this->getIntParam('id', 0);
        
        if ($targetUserId <= 0) {
            $this->error('用户ID无效', 400);
            return;
        }
        
        if ($targetUserId == $this->currentUser['id']) {
            $this->error('不能关注自己', 400);
            return;
        }
        
        $userModel = new UserModel();
        $result = $userModel->addFollow($this->currentUser['id'], $targetUserId);
        
        if ($result) {
            $this->success(null, '关注成功');
        } else {
            $this->error('关注失败');
        }
    }
    
    /**
     * 取消关注
     * POST /api.php/v1/users/{id}/unfollow
     */
    public function unfollow() {        $this->requireLogin();
        
        $targetUserId = $this->getIntParam('id', 0);
        
        if ($targetUserId <= 0) {
            $this->error('用户ID无效', 400);
            return;
        }
        
        $userModel = new UserModel();
        $result = $userModel->removeFollow($this->currentUser['id'], $targetUserId);
        
        if ($result) {
            $this->success(null, '取消关注成功');
        } else {
            $this->error('取消关注失败');
        }
    }
    
    /**
     * 获取粉丝列表
     * GET /api.php/v1/users/{id}/followers
     */
    public function followers() {        $this->requireLogin();
        
        $userId = $this->getIntParam('id', 0);
        $page = $this->getIntParam('page', 1);
        $pageSize = $this->getPageSize();
        
        if ($userId <= 0) {
            $this->error('用户ID无效', 400);
            return;
        }
        
        $userModel = new UserModel();
        $followers = $userModel->getFollowers($userId, $page, $pageSize);
        $total = $userModel->getFollowerCount($userId);
        
        $this->paginate($followers, $total, $page, $pageSize, '获取成功');
    }
    
    /**
     * 获取关注列表
     * GET /api.php/v1/users/{id}/following
     */
    public function following() {        $this->requireLogin();
        
        $userId = $this->getIntParam('id', 0);
        $page = $this->getIntParam('page', 1);
        $pageSize = $this->getPageSize();
        
        if ($userId <= 0) {
            $this->error('用户ID无效', 400);
            return;
        }
        
        $userModel = new UserModel();
        $following = $userModel->getFollowing($userId, $page, $pageSize);
        $total = $userModel->getFollowingCount($userId);
        
        $this->paginate($following, $total, $page, $pageSize, '获取成功');
    }
    
    /**
     * 获取当前用户信息
     * GET /api.php/v1/users/me
     */
    public function me() {        $this->requireLogin();
        
        $this->success($this->currentUser, '获取成功');
    }
    
    /**
     * 获取我的收藏
     * GET /api.php/v1/users/me/favorites
     */
    public function favorites() {        $this->requireLogin();
        
        $page = $this->getIntParam('page', 1);
        $pageSize = $this->getPageSize();
        
        $favoriteModel = new FavoriteModel();
        $favorites = $favoriteModel->getUserFavorites($this->currentUser['id'], $page, $pageSize);
        $total = $favoriteModel->getUserFavoriteCount($this->currentUser['id']);
        
        $this->paginate($favorites, $total, $page, $pageSize, '获取成功');
    }
    
    /**
     * 获取我的点赞
     * GET /api.php/v1/users/me/likes
     */
    public function likes() {        $this->requireLogin();
        
        $page = $this->getIntParam('page', 1);
        $pageSize = $this->getPageSize();
        
        $likeModel = new LikeModel();
        $likes = $likeModel->getUserLikes($this->currentUser['id'], $page, $pageSize);
        $total = $likeModel->getUserLikeCount($this->currentUser['id']);
        
        $this->paginate($likes, $total, $page, $pageSize, '获取成功');
    }
    
    /**
     * 获取阅读历史
     * GET /api.php/v1/users/me/history
     */
    public function history() {        $this->requireLogin();
        
        $page = $this->getIntParam('page', 1);
        $pageSize = $this->getPageSize();
        
        $articleModel = new ArticleModel();
        $history = $articleModel->getUserReadHistory($this->currentUser['id'], $page, $pageSize);
        $total = $articleModel->getUserReadHistoryCount($this->currentUser['id']);
        
        $this->paginate($history, $total, $page, $pageSize, '获取成功');
    }
}
