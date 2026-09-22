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


class LoginTokenModel {
    private $db;
    private $prefix;

    public function __construct() {
        $config = Config::get('database');
        $this->db = Database::getInstance();
        $this->prefix = $config['prefix'];
    }

    // 创建新令牌，返回明文 token 字符串（用于写入 cookie）
    public function createToken($userId, $days = 7) {
        $token = bin2hex(random_bytes(16));
        $tokenHash = password_hash($token, PASSWORD_DEFAULT);
        $expiresAt = time() + ($days * 86400);
        $createdAt = time();

        $sql = "INSERT INTO {$this->prefix}login_tokens (user_id, token, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?, ?)";
        $this->db->query($sql, [$userId, $token, $tokenHash, $expiresAt, $createdAt]);

        return $token;
    }

    // 验证令牌是否有效，成功返回用户信息，否则返回 null
    public function validateToken($token) {
        if (empty($token)) {
            return null;
        }

        $sql = "SELECT * FROM {$this->prefix}login_tokens WHERE token = ? LIMIT 1";
        $result = $this->db->query($sql, [$token]);
        $tokenRow = $result->fetch();

        if (!$tokenRow) {
            return null;
        }

        if ($tokenRow['expires_at'] < time()) {
            return null;
        }

        if (!password_verify($token, $tokenRow['token_hash'])) {
            return null;
        }

        $sql = "SELECT * FROM {$this->prefix}user WHERE id = ? LIMIT 1";
        $result = $this->db->query($sql, [$tokenRow['user_id']]);
        $user = $result->fetch();

        return $user ?: null;
    }

    // 删除指定令牌（用于用户退出登录时清理）
    public function deleteToken($token) {
        if (empty($token)) {
            return true;
        }

        $sql = "DELETE FROM {$this->prefix}login_tokens WHERE token = ?";
        return $this->db->query($sql, [$token]);
    }

    // 清理所有过期的令牌
    public function cleanExpiredTokens() {
        $sql = "DELETE FROM {$this->prefix}login_tokens WHERE expires_at < ?";
        return $this->db->query($sql, [time()]);
    }
}
