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
 * API Key模型
 * 实现API Key的创建、验证、过期和撤销功能
 */
class ApiKeyModel {
    
    /**
     * 创建API Key
     * @param string $name API Key名称
     * @param string $platform 平台/应用名称
     * @param string $ipWhitelist IP白名单
     * @param string $domainWhitelist 域名白名单
     * @param int $rateLimitCount 请求次数限制
     * @param int $rateLimitTime 限制时间窗口
     * @param string $rateLimitUnit 限流时间单位（second/minute/hour/day）
     * @param int|null $expireTime 过期时间（Unix时间戳，null表示永不过期）
     * @param string|null $description 描述
     * @param array|null $permissions 权限列表
     * @param int|null $createdBy 创建者ID
     * @param string|null $customApiKey 自定义API Key，为null时自动生成
     * @return array|false 包含明文API Key和API Key ID的数组，失败返回false
     */
    public static function create($name, $platform = '', $ipWhitelist = '', $domainWhitelist = '', $rateLimitCount = 100, $rateLimitTime = 60, $rateLimitUnit = 'minute', $expireTime = null, $description = null, $permissions = null, $createdBy = null, $customApiKey = null) {
        $db = Database::getInstance();
        
        // 生成唯一的API Key（明文 + bcrypt hash），并计算 fingerprint 用于快速查询
        list($plainKey, $hashedKey) = self::generateApiKey($customApiKey);
        $fingerprint = self::fingerprint($plainKey);
        
        // 插入API Key记录 —— 注意：rate_limit_unit 来自调用方（后台选择），不再硬编码
        $data = [
            'api_key' => $hashedKey,
            'key_fingerprint' => $fingerprint,
            'name' => $name,
            'platform' => $platform,
            'ip_whitelist' => $ipWhitelist,
            'domain_whitelist' => $domainWhitelist,
            'rate_limit_count' => $rateLimitCount,
            'rate_limit_time' => $rateLimitTime,
            'rate_limit_unit' => $rateLimitUnit,
            'expire_time' => $expireTime,
            'status' => 'active',
            'description' => $description,
            'permissions' => $permissions ? json_encode($permissions) : null,
            'created_by' => $createdBy,
            'created_at' => time(),
            'updated_at' => time()
        ];
        
        $insertId = $db->insert('api_keys', $data);
        
        if ($insertId) {
            return [
                'api_key' => $plainKey,
                'id' => $insertId
            ];
        }
        
        return false;
    }

    /**
     * 计算明文 API Key 的指纹（用于 validate 时快速定位记录，避免全表遍历）
     * 使用固定算法的 hash，使得相同明文 Key 产生相同指纹
     * @param string $plainKey 明文 API Key
     * @return string 32 位十六进制指纹
     */
    private static function fingerprint($plainKey) {
        return md5($plainKey);
    }
    
    /**
     * 验证API Key
     * @param string $apiKey API Key
     * @param string $path 请求路径（用于权限验证，不传则跳过权限检查）
     * @return array 验证成功返回 ['valid' => true, 'data' => ...]，失败返回 ['valid' => false, 'error' => ...]
     */
    public static function validate($apiKey, $path = null) {
        $db = Database::getInstance();

        // === 第一步：用指纹快速定位候选记录（避免全表遍历 + N次 password_verify）===
        $fingerprint = self::fingerprint($apiKey);
        $candidate = $db->fetch(
            "SELECT * FROM {$db->table('api_keys')} WHERE key_fingerprint = ? LIMIT 1",
            [$fingerprint]
        );

        // === 第二步：对定位到的候选记录做一次 bcrypt password_verify 校验 ===
        // （指纹是 md5，理论上不会碰撞，但仍需通过 bcrypt 正式确认）
        $apiKeyData = null;
        if ($candidate && password_verify($apiKey, $candidate['api_key'])) {
            $apiKeyData = $candidate;
        }

        // === 第三步：兼容已存在但没有 key_fingerprint 的老 Key（全表扫描兜底）===
        if (!$apiKeyData) {
            $allKeys = $db->fetchAll("SELECT * FROM {$db->table('api_keys')} WHERE key_fingerprint IS NULL OR key_fingerprint = ''");
            foreach ($allKeys as $keyData) {
                if (password_verify($apiKey, $keyData['api_key'])) {
                    $apiKeyData = $keyData;
                    // 顺便补上 fingerprint，下次请求走快速路径
                    $db->update(
                        'api_keys',
                        ['key_fingerprint' => $fingerprint, 'updated_at' => time()],
                        ['id' => $keyData['id']]
                    );
                    break;
                }
            }
        }

        if (!$apiKeyData) {
            return ['valid' => false, 'error' => '无效的API Key'];
        }

        // 检查API Key状态
        if ($apiKeyData['status'] === 'inactive') {
            return ['valid' => false, 'error' => 'API Key已被撤销'];
        }
        if ($apiKeyData['status'] === 'expired') {
            return ['valid' => false, 'error' => 'API Key已过期'];
        }

        // 检查API Key是否过期
        if ($apiKeyData['expire_time'] && $apiKeyData['expire_time'] < time()) {
            $db->update('api_keys', ['status' => 'expired', 'updated_at' => time()], ['id' => $apiKeyData['id']]);
            return ['valid' => false, 'error' => 'API Key已过期'];
        }

        // IP白名单验证
        if (!empty($apiKeyData['ip_whitelist'])) {
            $allowedIPs = array_map('trim', explode(',', $apiKeyData['ip_whitelist']));
            $clientIP = $_SERVER['REMOTE_ADDR'];
            if (!in_array($clientIP, $allowedIPs)) {
                return ['valid' => false, 'error' => 'IP地址不在白名单中'];
            }
        }

        // 域名白名单验证
        if (!empty($apiKeyData['domain_whitelist'])) {
            $allowedDomains = array_map('trim', explode(',', $apiKeyData['domain_whitelist']));
            $referer = $_SERVER['HTTP_REFERER'] ?? '';
            if (!empty($referer)) {
                $parsedReferer = parse_url($referer);
                $clientDomain = $parsedReferer['host'] ?? '';
                if (!in_array($clientDomain, $allowedDomains)) {
                    return ['valid' => false, 'error' => '域名不在白名单中'];
                }
            }
        }

        // 权限验证（只有当路径和 permissions 都有值时才生效）
        if ($path && !empty($apiKeyData['permissions'])) {
            $permissions = json_decode($apiKeyData['permissions'], true);
            if (is_array($permissions) && !in_array($path, $permissions)) {
                return ['valid' => false, 'error' => 'API Key没有访问该资源的权限'];
            }
        }

        // 请求频率限制验证
        if ($apiKeyData['rate_limit_count'] > 0 && $apiKeyData['rate_limit_time'] > 0) {
            $currentTime = time();
            $timeWindow = 0;
            switch ($apiKeyData['rate_limit_unit']) {
                case 'second':
                    $timeWindow = $currentTime;
                    break;
                case 'minute':
                    $timeWindow = floor($currentTime / 60);
                    break;
                case 'hour':
                    $timeWindow = floor($currentTime / 3600);
                    break;
                case 'day':
                    $timeWindow = floor($currentTime / 86400);
                    break;
                default:
                    $timeWindow = floor($currentTime / 60);
            }

            $rateLimitKey = 'api_rate_limit_' . $apiKeyData['id'] . '_' . $timeWindow;
            $rateLimitFile = CORE_PATH . '/tmp/' . $rateLimitKey;

            if (!is_dir(CORE_PATH . '/tmp')) {
                mkdir(CORE_PATH . '/tmp', 0755, true);
            }

            $requestCount = 0;
            if (file_exists($rateLimitFile)) {
                $requestCount = (int)file_get_contents($rateLimitFile);
            }

            if ($requestCount >= $apiKeyData['rate_limit_count']) {
                return ['valid' => false, 'error' => 'API Key请求频率超过限制'];
            }

            $requestCount++;
            file_put_contents($rateLimitFile, $requestCount);
        }

        // 更新最后使用时间和总请求次数
        $db->update('api_keys', [
            'last_used_at' => time(),
            'total_requests' => (int)$apiKeyData['total_requests'] + 1,
            'updated_at' => time()
        ], ['id' => $apiKeyData['id']]);

        return ['valid' => true, 'data' => $apiKeyData];
    }
    
    /**
     * 生成API Key
     * @param string|null $customKey 自定义API Key，为null时自动生成
     * @return array 包含明文API Key和加密API Key的数组
     */
    private static function generateApiKey($customKey = null) {
        // 如果提供了自定义Key，则使用它，否则自动生成
        if ($customKey) {
            $plainKey = $customKey;
        } else {
            // 生成32字节的随机字符串，然后转换为十六进制
            $plainKey = bin2hex(random_bytes(32));
        }
        // 使用bcrypt加密存储
        $hashedKey = password_hash($plainKey, PASSWORD_BCRYPT);
        return [$plainKey, $hashedKey];
    }
    
    /**
     * 获取所有API Key列表（管理员用）
     * @return array API Key列表
     */
    public static function getAllApiKeys() {
        $db = Database::getInstance();
        
        return $db->fetchAll("SELECT * FROM {$db->table('api_keys')} ORDER BY created_at DESC");
    }
    
    /**
     * 获取API Key详情
     * @param int $id API Key ID
     * @return array|null API Key详情，不存在返回null
     */
    public static function getById($id) {
        $db = Database::getInstance();
        
        return $db->fetch("SELECT * FROM {$db->table('api_keys')} WHERE id = :id", [
            'id' => $id
        ]);
    }
    
    /**
     * 撤销API Key
     * @param int $id API Key ID
     * @return bool 成功返回true，失败返回false
     */
    public static function revoke($id) {
        $db = Database::getInstance();
        
        $result = $db->update('api_keys', [
            'status' => 'inactive',
            'updated_at' => time()
        ], ['id' => $id]);
        
        return $result > 0;
    }
    
    /**
     * 激活API Key
     * @param int $id API Key ID
     * @return bool 成功返回true，失败返回false
     */
    public static function activate($id) {
        $db = Database::getInstance();
        
        $result = $db->update('api_keys', [
            'status' => 'active',
            'updated_at' => time()
        ], ['id' => $id]);
        
        return $result > 0;
    }
    
    /**
     * 更新API Key
     * @param int $id API Key ID
     * @param string $name API Key名称
     * @param string $platform 平台/应用名称
     * @param string $ipWhitelist IP白名单
     * @param string $domainWhitelist 域名白名单
     * @param int $rateLimitCount 请求次数限制
     * @param int $rateLimitTime 限制时间窗口
     * @param string $rateLimitUnit 限流时间单位
     * @param int|null $expireTime 过期时间（Unix时间戳，null表示永不过期）
     * @param string|null $description 描述
     * @param array|null $permissions 权限列表
     * @return bool 成功返回true，失败返回false
     */
    public static function update($id, $name, $platform = '', $ipWhitelist = '', $domainWhitelist = '', $rateLimitCount = 100, $rateLimitTime = 60, $rateLimitUnit = 'minute', $expireTime = null, $description = null, $permissions = null) {
        $db = Database::getInstance();
        
        $data = [
            'name' => $name,
            'platform' => $platform,
            'ip_whitelist' => $ipWhitelist,
            'domain_whitelist' => $domainWhitelist,
            'rate_limit_count' => $rateLimitCount,
            'rate_limit_time' => $rateLimitTime,
            'rate_limit_unit' => $rateLimitUnit,
            'expire_time' => $expireTime,
            'description' => $description,
            'permissions' => $permissions ? json_encode($permissions) : null,
            'updated_at' => time()
        ];
        
        // 如果设置了过期时间，检查是否已过期
        if ($expireTime && $expireTime < time()) {
            $data['status'] = 'expired';
        }
        
        $result = $db->update('api_keys', $data, ['id' => $id]);
        
        return $result > 0;
    }
    
    /**
     * 删除API Key
     * @param int $id API Key ID
     * @return bool 成功返回true，失败返回false
     */
    public static function delete($id) {
        $db = Database::getInstance();
        
        $result = $db->delete('api_keys', ['id' => $id]);
        
        return $result > 0;
    }
    
    /**
     * 批量删除API Key
     * @param array $ids API Key ID数组
     * @return int 成功删除的数量
     */
    public static function batchDelete($ids) {
        if (empty($ids)) {
            return 0;
        }
        
        $db = Database::getInstance();
        
        $result = $db->delete('api_keys', ['id IN ?' => $ids]);
        
        return $result;
    }
    
    /**
     * 批量激活API Key
     * @param array $ids API Key ID数组
     * @return int 成功激活的数量
     */
    public static function batchActivate($ids) {
        if (empty($ids)) {
            return 0;
        }
        
        $db = Database::getInstance();
        
        $result = $db->update('api_keys', [
            'status' => 'active',
            'updated_at' => time()
        ], ['id IN ?' => $ids]);
        
        return $result;
    }
    
    /**
     * 批量撤销API Key
     * @param array $ids API Key ID数组
     * @return int 成功撤销的数量
     */
    public static function batchRevoke($ids) {
        if (empty($ids)) {
            return 0;
        }
        
        $db = Database::getInstance();
        
        $result = $db->update('api_keys', [
            'status' => 'inactive',
            'updated_at' => time()
        ], ['id IN ?' => $ids]);
        
        return $result;
    }
    
    /**
     * 清理过期的API Key
     * @return int 清理的数量
     */
    public static function cleanupExpired() {
        $db = Database::getInstance();
        
        $result = $db->update('api_keys', [
            'status' => 'expired',
            'updated_at' => time()
        ], [
            'status' => 'active',
            'expire_time < ?' => time()
        ]);
        
        return $result;
    }
    

}
