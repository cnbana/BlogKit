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
 * 认证API控制器
 * 提供认证相关的API接口
 * 
 * 基础URL: /api.php/v1/auth
 */

class AuthApiController extends ApiController {
    
    /**
     * 用户登录
     * POST /api.php/v1/auth/login
     * 
     * 请求体：
     * - username: 用户名（必填）
     * - password: 密码（必填）
     */
    public function login() {        
        $this->validateRules([
            'username' => ['required' => true],
            'password' => ['required' => true]
        ]);
        
        $username = $this->getStringParam('username');
        $password = $this->getStringParam('password');
        
        $userModel = new UserModel();
        
        $user = $userModel->getUserByUsername($username);
        
        if (!$user) {
            $this->error('用户名或密码错误', 401);
            return;
        }
        
        if (!password_verify($password, $user['password'])) {
            $this->error('用户名或密码错误', 401);
            return;
        }
        
        if ($user['status'] != 1) {
            $this->error('用户已被禁用', 403);
            return;
        }
        
        $_SESSION['user'] = $user;
        $_SESSION['user_id'] = $user['id'];

        // 根据后台配置 api_token_expire 控制 Token 有效期（秒）
        $tokenExpire = (int)Config::get('api_token_expire', 3600);
        $token = md5($user['id'] . time() . mt_rand());
        $_SESSION['user_token'] = $token;
        $_SESSION['user_token_expire'] = time() + $tokenExpire;

        $this->success([
            'user_id' => $user['id'],
            'username' => $user['username'],
            'nickname' => $user['nickname'],
            'email' => $user['email'],
            'token' => $token,
            'token_expire' => $tokenExpire
        ], '登录成功');
    }
    
    /**
     * 用户注册
     * POST /api.php/v1/auth/register
     * 
     * 请求体：
     * - username: 用户名（必填）
     * - password: 密码（必填）
     * - email: 邮箱（必填）
     * - nickname: 昵称（可选）
     */
    public function register() {        
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
        
        $existingUser = $userModel->getUserByUsername($username);
        if ($existingUser) {
            $this->error('用户名已存在', 400);
            return;
        }
        
        $existingEmail = $userModel->getUserByEmail($email);
        if ($existingEmail) {
            $this->error('邮箱已被注册', 400);
            return;
        }
        
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $userData = [
            'username' => $username,
            'password' => $hashedPassword,
            'email' => $email,
            'nickname' => $nickname ?: $username,
            'role' => 1,
            'status' => 1
        ];
        
        $userId = $userModel->createUser($userData);
        
        if ($userId) {
            $this->success(['user_id' => $userId], '注册成功');
        } else {
            $this->error('注册失败');
        }
    }
    
    /**
     * 用户退出
     * POST /api.php/v1/auth/logout
     */
    public function logout() {        
        $this->requireLogin();
        
        unset($_SESSION['user']);
        unset($_SESSION['user_id']);
        
        $this->success(null, '退出成功');
    }
    
    /**
     * 忘记密码
     * POST /api.php/v1/auth/forgot-password
     * 
     * 请求体：
     * - email: 邮箱（必填）
     */
    public function forgotPassword() {        
        $this->validateRules([
            'email' => ['required' => true, 'type' => 'email']
        ]);
        
        $email = $this->getStringParam('email');
        
        $userModel = new UserModel();
        $user = $userModel->getUserByEmail($email);
        
        if (!$user) {
            $this->error('邮箱未注册', 400);
            return;
        }
        
        $resetToken = md5($user['id'] . time() . rand(1000, 9999));
        $resetExpire = time() + 86400;
        
        $userModel = new UserModel();
        $result = $userModel->updateUser($user['id'], [
            'reset_token' => $resetToken,
            'reset_token_expire' => $resetExpire
        ]);
        
        if ($result) {
            $this->success(null, '重置邮件已发送');
        } else {
            $this->error('发送失败');
        }
    }
    
    /**
     * 重置密码
     * POST /api.php/v1/auth/reset-password
     * 
     * 请求体：
     * - email: 邮箱（必填）
     * - token: 重置令牌（必填）
     * - password: 新密码（必填）
     */
    public function resetPassword() {        
        $this->validateRules([
            'email' => ['required' => true, 'type' => 'email'],
            'token' => ['required' => true],
            'password' => ['required' => true, 'min' => 6, 'max' => 50]
        ]);
        
        $email = $this->getStringParam('email');
        $token = $this->getStringParam('token');
        $password = $this->getStringParam('password');
        
        $userModel = new UserModel();
        $user = $userModel->getUserByEmail($email);
        
        if (!$user) {
            $this->error('邮箱未注册', 400);
            return;
        }
        
        if ($user['reset_token'] != $token) {
            $this->error('重置令牌无效', 400);
            return;
        }
        
        if ($user['reset_token_expire'] < time()) {
            $this->error('重置令牌已过期', 400);
            return;
        }
        
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $result = $userModel->updateUser($user['id'], [
            'password' => $hashedPassword,
            'reset_token' => null,
            'reset_token_expire' => null
        ]);
        
        if ($result) {
            $this->success(null, '密码重置成功');
        } else {
            $this->error('重置失败');
        }
    }
    
    /**
     * 刷新Token
     * POST /api.php/v1/auth/refresh-token
     */
    public function refreshToken() {
        $this->requireLogin();

        // 根据后台配置 api_token_expire 控制 Token 有效期（秒）
        $tokenExpire = (int)Config::get('api_token_expire', 3600);
        $token = md5($this->currentUser['id'] . time() . mt_rand());
        $_SESSION['user_token'] = $token;
        $_SESSION['user_token_expire'] = time() + $tokenExpire;

        $this->success([
            'user_id' => $this->currentUser['id'],
            'username' => $this->currentUser['username'],
            'token' => $token,
            'token_expire' => $tokenExpire
        ], 'Token刷新成功');
    }
}
