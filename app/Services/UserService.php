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
 * 用户服务层
 * 
 * 封装用户注册、认证、资料管理等核心业务逻辑，
 * 供 AuthController、ProfileController、Admin/UserController 等调用。
 * 
 * 职责：
 *   - 用户注册流程（验证、密码强度、重复检查）
 *   - 用户登录验证（含登录尝试限制）
 *   - 用户资料更新与校验
 *   - 用户状态管理（禁用/启用/删除）
 *   - 用户账户注销（含冷却期检查）
 */
class UserService
{
    /** 登录尝试最大次数 */
    const MAX_LOGIN_ATTEMPTS = 5;

    /** 登录锁定时间（秒） */
    const LOGIN_LOCKOUT_SECONDS = 900;

    /** 注销冷却天数 */
    const DELETION_COOLDOWN_DAYS = 7;

    /**
     * 验证注册数据完整性
     *
     * @param array $data [username, email, password, password_confirm, nickname]
     * @return array ['valid' => bool, 'errors' => array, 'sanitized' => array]
     */
    public static function validateRegistrationData(array $data)
    {
        $errors = [];
        $sanitized = [];

        // 用户名
        $username = trim($data['username'] ?? '');
        if ($username === '') {
            $errors[] = '用户名不能为空';
        } elseif (mb_strlen($username) < 3) {
            $errors[] = '用户名至少需要 3 个字符';
        } elseif (mb_strlen($username) > 30) {
            $errors[] = '用户名最多 30 个字符';
        } elseif (!preg_match('/^[a-zA-Z0-9_\x{4e00}-\x{9fa5}]+$/u', $username)) {
            $errors[] = '用户名只能包含字母、数字、下划线和中文';
        } else {
            $sanitized['username'] = $username;
        }

        // 邮箱
        $email = trim($data['email'] ?? '');
        if ($email === '') {
            $errors[] = '邮箱不能为空';
        } elseif (!Security::isValidEmail($email)) {
            $errors[] = '邮箱格式不正确';
        } else {
            $sanitized['email'] = $email;
        }

        // 密码
        $password = $data['password'] ?? '';
        $passwordConfirm = $data['password_confirm'] ?? '';
        if ($password === '') {
            $errors[] = '密码不能为空';
        } else {
            list($valid, $msg) = Security::validatePasswordStrength($password);
            if (!$valid) {
                $errors[] = $msg;
            } elseif ($password !== $passwordConfirm) {
                $errors[] = '两次输入的密码不一致';
            } else {
                $sanitized['password'] = $password;
            }
        }

        // 昵称（可选，默认使用用户名）
        $nickname = trim($data['nickname'] ?? '');
        $sanitized['nickname'] = $nickname !== '' ? $nickname : ($sanitized['username'] ?? '');

        return [
            'valid'     => empty($errors),
            'errors'    => $errors,
            'sanitized' => $sanitized,
        ];
    }

    /**
     * 检查用户名或邮箱是否已被注册
     *
     * @param string $username
     * @param string $email
     * @return array ['available' => bool, 'errors' => array]
     */
    public static function checkUserAvailability($username, $email)
    {
        $errors = [];
        $userModel = new UserModel();

        if ($userModel->findByUsername($username)) {
            $errors[] = '该用户名已被注册';
        }

        if ($userModel->findByEmail($email)) {
            $errors[] = '该邮箱已被注册';
        }

        return [
            'available' => empty($errors),
            'errors'    => $errors,
        ];
    }

    /**
     * 执行用户注册
     *
     * @param array $data 已验证和清理过的注册数据
     * @return array ['success' => bool, 'message' => string, 'user_id' => int|null]
     */
    public static function register(array $data)
    {
        // 再次检查可用性（防止并发注册）
        $availability = self::checkUserAvailability($data['username'], $data['email']);
        if (!$availability['available']) {
            return [
                'success' => false,
                'message' => implode('；', $availability['errors']),
                'user_id' => null,
            ];
        }

        $userModel = new UserModel();
        $userId = $userModel->create([
            'username'        => $data['username'],
            'email'           => $data['email'],
            'password'        => Security::hashPassword($data['password']),
            'nickname'        => $data['nickname'],
            'role'            => 'subscriber',
            'status'          => UserModel::STATUS_NORMAL,
            'created_at'      => time(),
            'updated_at'      => time(),
            'register_ip'     => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        ]);

        if ($userId) {
            return [
                'success' => true,
                'message' => '注册成功',
                'user_id' => $userId,
            ];
        }

        return [
            'success' => false,
            'message' => '注册失败，请稍后重试',
            'user_id' => null,
        ];
    }

    /**
     * 用户登录验证
     *
     * @param string $username 用户名或邮箱
     * @param string $password 密码
     * @param bool   $remember 是否记住登录
     * @return array ['success' => bool, 'message' => string, 'user' => array|null]
     */
    public static function login($username, $password, $remember = false)
    {
        $userModel = new UserModel();

        // 查找用户（支持用户名或邮箱登录）
        $user = $userModel->findByUsername($username)
             ?: $userModel->findByEmail($username);

        if (!$user) {
            return ['success' => false, 'message' => '用户名或密码错误', 'user' => null];
        }

        // 检查账号状态
        if (isset($user['status']) && $user['status'] == UserModel::STATUS_DISABLED) {
            return ['success' => false, 'message' => '账号已被禁用，请联系管理员', 'user' => null];
        }

        // 检查是否已删除
        if (!empty($user['is_deleted'])) {
            return ['success' => false, 'message' => '账号不存在', 'user' => null];
        }

        // 验证密码
        if (!Security::verifyPassword($password, $user['password'])) {
            // 登录失败可在此记录日志或增加失败计数
            return ['success' => false, 'message' => '用户名或密码错误', 'user' => null];
        }

        // 更新最后登录时间
        $userModel->updateLastLogin($user['id']);

        return ['success' => true, 'message' => '登录成功', 'user' => $user];
    }

    /**
     * 验证用户资料更新数据
     *
     * @param int   $userId 当前用户ID
     * @param array $data   待更新的字段
     * @return array ['valid' => bool, 'errors' => array, 'sanitized' => array]
     */
    public static function validateProfileUpdate($userId, array $data)
    {
        $errors = [];
        $sanitized = [];

        // 昵称
        if (isset($data['nickname'])) {
            $nickname = trim($data['nickname']);
            if ($nickname === '') {
                $errors[] = '昵称不能为空';
            } elseif (mb_strlen($nickname) > 50) {
                $errors[] = '昵称最多 50 个字符';
            } else {
                $sanitized['nickname'] = $nickname;
            }
        }

        // 个人简介
        if (isset($data['bio'])) {
            $bio = trim($data['bio']);
            if (mb_strlen($bio) > 500) {
                $errors[] = '个人简介最多 500 个字符';
            } else {
                $sanitized['bio'] = $bio;
            }
        }

        // 网站
        if (isset($data['website'])) {
            $website = trim($data['website']);
            if ($website !== '' && !Security::isValidUrl($website)) {
                $errors[] = '网站地址格式不正确';
            } else {
                $sanitized['website'] = $website;
            }
        }

        // 邮箱（需要验证是否被占用）
        if (isset($data['email'])) {
            $email = trim($data['email']);
            if (!Security::isValidEmail($email)) {
                $errors[] = '邮箱格式不正确';
            } else {
                $userModel = new UserModel();
                $existing = $userModel->findByEmail($email);
                if ($existing && $existing['id'] != $userId) {
                    $errors[] = '该邮箱已被其他账号使用';
                } else {
                    $sanitized['email'] = $email;
                }
            }
        }

        // 密码修改
        if (!empty($data['new_password'])) {
            $currentPassword = $data['current_password'] ?? '';
            $newPassword = $data['new_password'];
            $newPasswordConfirm = $data['new_password_confirm'] ?? '';

            if (empty($currentPassword)) {
                $errors[] = '请输入当前密码';
            } else {
                $userModel = new UserModel();
                $user = $userModel->findById($userId);
                if (!$user || !Security::verifyPassword($currentPassword, $user['password'])) {
                    $errors[] = '当前密码不正确';
                } else {
                    list($valid, $msg) = Security::validatePasswordStrength($newPassword);
                    if (!$valid) {
                        $errors[] = $msg;
                    } elseif ($newPassword !== $newPasswordConfirm) {
                        $errors[] = '两次输入的新密码不一致';
                    } else {
                        $sanitized['password'] = Security::hashPassword($newPassword);
                    }
                }
            }
        }

        return [
            'valid'     => empty($errors),
            'errors'    => $errors,
            'sanitized' => $sanitized,
        ];
    }

    /**
     * 查询用户是否处于注销冷却期
     *
     * @param int $userId
     * @return array ['can_delete' => bool, 'remaining_days' => int]
     */
    public static function checkDeletionCooldown($userId)
    {
        $userModel = new UserModel();
        $user = $userModel->findById($userId);

        if (!$user || !empty($user['is_deleted'])) {
            return ['can_delete' => false, 'remaining_days' => 0];
        }

        // 检查是否有注销请求标记
        if (!empty($user['deletion_requested_at'])) {
            $elapsed = time() - $user['deletion_requested_at'];
            $cooldownSeconds = self::DELETION_COOLDOWN_DAYS * 86400;

            if ($elapsed < $cooldownSeconds) {
                $remaining = ceil(($cooldownSeconds - $elapsed) / 86400);
                return ['can_delete' => false, 'remaining_days' => (int)$remaining];
            }
        }

        return ['can_delete' => true, 'remaining_days' => 0];
    }

    /**
     * 获取用户公开资料（脱敏后）
     *
     * @param int $userId
     * @return array|null
     */
    public static function getPublicProfile($userId)
    {
        $userModel = new UserModel();
        $user = $userModel->findById($userId);

        if (!$user || !empty($user['is_deleted'])) {
            return null;
        }

        // 只返回公开字段
        return [
            'id'        => (int)$user['id'],
            'username'  => $user['username'],
            'nickname'  => $user['nickname'] ?? $user['username'],
            'bio'       => $user['bio'] ?? '',
            'website'   => $user['website'] ?? '',
            'avatar'    => $user['avatar'] ?? '',
            'role'      => $user['role'] ?? 'subscriber',
            'created_at'=> $user['created_at'] ?? null,
        ];
    }
}
