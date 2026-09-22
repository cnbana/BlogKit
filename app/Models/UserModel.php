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


class UserModel {
    const STATUS_DISABLED = 0;
    const STATUS_NORMAL = 1;
    private $db;
    private $prefix;
    
    public function __construct() {
        $config = Config::get('database');
        $this->db = Database::getInstance();
        $this->prefix = $config['prefix'];
    }
    

    
    /**
     * 获取用户列表（实例方法，用于API）
     * @param int $page 页码
     * @param int $limit 每页数量
     * @return array
     */
    public function getUsers($page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT * FROM {$this->prefix}user WHERE 1 = 1 AND is_deleted = 0";
        $params = [];
        
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * 获取用户总数（实例方法，用于API）
     * @return int
     */
    public function getApiUserCount() {
        $sql = "SELECT COUNT(*) as count FROM {$this->prefix}user WHERE 1 = 1 AND is_deleted = 0";
        $result = $this->db->fetch($sql, []);
        return $result['count'] ?? 0;
    }
    
    // 获取所有用户（静态方法，用于后台管理）
    public static function getAllUsers($page = 1, $limit = 10, $filters = [], $sort = '', $order = 'desc') {
        return self::getAllUsersWithRoles($page, $limit, $filters, $sort, $order);
    }
    
    /**
     * 获取所有用户（带角色信息，使用JOIN查询避免N+1问题）
     * 
     * @param int $page 页码
     * @param int $limit 每页数量
     * @param array $filters 筛选条件
     * @return array ['total' => 总数, 'page' => 当前页, 'limit' => 每页数量, 'data' => 用户列表]
     */
    public static function getAllUsersWithRoles($page = 1, $limit = 10, $filters = [], $sort = '', $order = 'desc') {
        $config = Config::get('database');
        $prefix = $config['prefix'];
        $db = Database::getInstance();
        
        // 使用JOIN查询一次性获取用户和角色信息，避免N+1查询问题
        $sql = "SELECT u.*, r.name as role_name
                FROM {$prefix}user u
                LEFT JOIN {$prefix}role r ON u.role = r.id
                WHERE u.is_deleted = 0";
        
        $totalSql = "SELECT COUNT(*) as total FROM {$prefix}user u WHERE u.is_deleted = 0";
        $params = [];
        
        // 搜索筛选
        if (!empty($filters['search'])) {
            $searchCondition = " AND (u.username LIKE ? OR u.nickname LIKE ? OR u.email LIKE ?)";
            $sql .= $searchCondition;
            $totalSql .= $searchCondition;
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        // 角色筛选
        if (isset($filters['role']) && $filters['role'] != '') {
            $roleCondition = " AND u.role = ?";
            $sql .= $roleCondition;
            $totalSql .= $roleCondition;
            $params[] = $filters['role'];
        }
        
        // 状态筛选
        if (isset($filters['status']) && $filters['status'] !== '') {
            $statusCondition = " AND u.status = ?";
            $sql .= $statusCondition;
            $totalSql .= $statusCondition;
            $params[] = $filters['status'];
        }
        
        // 注册时间范围筛选
        if (!empty($filters['created_at_start'])) {
            $timeCondition = " AND u.created_at >= ?";
            $sql .= $timeCondition;
            $totalSql .= $timeCondition;
            $params[] = strtotime($filters['created_at_start']);
        }
        if (!empty($filters['created_at_end'])) {
            $timeCondition = " AND u.created_at <= ?";
            $sql .= $timeCondition;
            $totalSql .= $timeCondition;
            $params[] = strtotime($filters['created_at_end'] . ' 23:59:59');
        }
        
        // 最后登录时间范围筛选
        if (!empty($filters['last_login_start'])) {
            $timeCondition = " AND u.last_login_at >= ?";
            $sql .= $timeCondition;
            $totalSql .= $timeCondition;
            $params[] = strtotime($filters['last_login_start']);
        }
        if (!empty($filters['last_login_end'])) {
            $timeCondition = " AND u.last_login_at <= ?";
            $sql .= $timeCondition;
            $totalSql .= $timeCondition;
            $params[] = strtotime($filters['last_login_end'] . ' 23:59:59');
        }
        
        // 计算偏移量
        $offset = ($page - 1) * $limit;

        // 排序：白名单校验排序字段（防 SQL 注入），默认按注册时间倒序
        $sortWhiteList = ['id', 'created_at', 'last_login_at'];
        $orderDir = (strtolower($order) === 'asc') ? 'ASC' : 'DESC';
        if ($sort !== '' && in_array($sort, $sortWhiteList)) {
            $sql .= " ORDER BY u.{$sort} {$orderDir}, u.created_at DESC LIMIT ? OFFSET ?";
        } else {
            $sql .= " ORDER BY u.created_at DESC LIMIT ? OFFSET ?";
        }
        $limitParams = $params;
        $limitParams[] = $limit;
        $limitParams[] = $offset;
        
        // 获取总数
        $totalResult = $db->fetch($totalSql, $params);
        $total = $totalResult['total'] ?? 0;
        
        // 获取分页数据（使用JOIN查询的结果）
        $data = $db->fetchAll($sql, $limitParams);
        
        return [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'data' => $data
        ];
    }
    
    // 根据ID获取用户
    public static function getUserById($id) {
        $config = Config::get('database');
        $prefix = $config['prefix'];
        $db = Database::getInstance();
        $sql = "SELECT * FROM {$prefix}user WHERE id = ?";
        return $db->fetch($sql, [$id]);
    }
    
    // 获取用户状态名称
    public static function getStatusName($status) {
        switch ($status) {
            case 0:
                return '禁用';
            case 1:
                return '正常';
            default:
                return '未知';
        }
    }
    
    /**
     * 检查用户是否被禁用
     * @param int $userId 用户ID
     * @return bool
     */
    public static function isUserDisabled($userId) {
        // 检查账号状态
        $user = self::getUserById($userId);
        if ($user && $user['status'] === self::STATUS_DISABLED) {
            return true;
        }
        
        return false;
    }
    
    // 根据用户名获取用户
    public static function getUserByUsername($username) {
        $config = Config::get('database');
        $prefix = $config['prefix'];
        $db = Database::getInstance();
        $sql = "SELECT * FROM {$prefix}user WHERE username = ?";
        return $db->fetch($sql, [$username]);
    }
    
    // 根据邮箱获取用户
    public static function getUserByEmail($email) {
        $config = Config::get('database');
        $prefix = $config['prefix'];
        $db = Database::getInstance();
        $sql = "SELECT * FROM {$prefix}user WHERE email = ?";
        return $db->fetch($sql, [$email]);
    }
    
    // 创建用户
    public static function createUser($data) {
        // 验证必要字段
        if (empty($data['username']) || empty($data['password']) || empty($data['role'])) {
            return false;
        }
        
        // 检查用户名是否已存在
        if (self::getUserByUsername($data['username'])) {
            return false;
        }
        
        // 先确保数据库字段存在
        self::checkAndCreateNewFields();
        
        // 准备数据
        $timestamp = time();
        $userData = [
            'username' => $data['username'],
            'password' => password_hash($data['password'], PASSWORD_DEFAULT),
            'email' => $data['email'] ?? '',
            'nickname' => $data['nickname'] ?? $data['username'],
            'role' => $data['role'],
            'status' => $data['status'] ?? 1,
            'notification_settings' => json_encode([
                'types' => [
                    'comments' => 1,
                    'follows' => 1,
                    'system' => 1,
                    'articles' => 1,
                    'messages' => 1,
                    'reviews' => 1,
                    'article_likes' => 1,
                    'comment_likes' => 1,
                    'favorites' => 1
                ]
            ]),
            'created_at' => $timestamp,
            'updated_at' => $timestamp
        ];
        
        // 记录注册信息
        require_once CORE_PATH . '/lib/UserAgent.php';
        
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $uaInfo = UserAgent::parse($userAgent);
        
        $userData['register_ip'] = UserAgent::getIP();
        $userData['register_user_agent'] = $userAgent;
        $userData['register_referer'] = UserAgent::getReferer();
        $userData['register_device_type'] = $uaInfo['device_type'];
        $userData['register_os'] = $uaInfo['os'];
        $userData['register_browser'] = $uaInfo['browser'];
        $userData['register_browser_version'] = $uaInfo['browser_version'];
        
        // 插入用户
        $db = Database::getInstance();
        $targetUserId = $db->insert('user', $userData);
        
        return $targetUserId;
    }
    
    // 更新用户
    public static function updateUser($id, $data) {
        // 验证必要字段
        if (empty($id)) {
            return false;
        }

        // 确保数据库字段存在（老用户表可能缺少 privacy_settings 等新列）
        self::checkAndCreateNewFields();

        // 获取当前用户信息
        $currentUser = self::getUserById($id);
        if (!$currentUser) {
            return false;
        }
        
        // 如果是ID为1的系统初始管理员，禁止修改角色
        if ($currentUser['id'] == 1 && isset($data['role']) && $data['role'] != 1) {
            return false;
        }
        
        // 不允许把角色改成游客（role=2），但允许已有的游客保持游客角色
        if (isset($data['role']) && $data['role'] == 2 && $currentUser['role'] != 2) {
            return false;
        }
        
        // 检查用户名是否已被其他用户使用
        if (isset($data['username']) && $data['username'] != $currentUser['username']) {
            $user = self::getUserByUsername($data['username']);
            if ($user && $user['id'] != $id) {
                return false;
            }
        }
        
        // 检查邮箱是否已被其他用户使用
        if (isset($data['email']) && $data['email'] != $currentUser['email']) {
            $user = self::getUserByEmail($data['email']);
            if ($user && $user['id'] != $id) {
                return false;
            }
        }
        
        // 准备数据
        $timestamp = time();
        $userData = [
            'updated_at' => $timestamp
        ];
        
        // 只更新提供的字段
        if (isset($data['username'])) {
            $userData['username'] = $data['username'];
        }
        if (isset($data['email'])) {
            $userData['email'] = $data['email'];
        }
        if (isset($data['nickname'])) {
            $userData['nickname'] = $data['nickname'];
        }
        if (isset($data['bio'])) {
            $userData['bio'] = $data['bio'];
        }
        if (isset($data['website'])) {
            $userData['website'] = $data['website'];
        }
        if (isset($data['role'])) {
            // 确保角色值是整数类型
            $userData['role'] = (int)$data['role'];
        }
        if (isset($data['status'])) {
            $userData['status'] = $data['status'];
        }
        
        // 如果提供了最后登录时间，则更新
        if (isset($data['last_login_at'])) {
            $userData['last_login_at'] = $data['last_login_at'];
        }
        
        // 如果提供了新密码，则更新密码
        if (!empty($data['password'])) {
            $userData['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        // 处理通知相关字段
        if (isset($data['notification_settings'])) {
            $userData['notification_settings'] = $data['notification_settings'];
        }

        // 处理隐私设置（JSON 字符串）
        if (isset($data['privacy_settings'])) {
            $userData['privacy_settings'] = $data['privacy_settings'];
        }

        // 处理邮箱验证相关字段
        if (isset($data['pending_email'])) {
            $userData['pending_email'] = $data['pending_email'];
        }
        if (isset($data['email_verification_code'])) {
            $userData['email_verification_code'] = $data['email_verification_code'];
        }
        if (isset($data['email_verification_expire'])) {
            $userData['email_verification_expire'] = $data['email_verification_expire'];
        }
        
        // 更新用户
        $db = Database::getInstance();
        $result = $db->update('user', $userData, ['id' => $id]);
        
        return $result;
    }
    

    
    /**
     * 用户登录验证
     * @param string $username 用户名或邮箱
     * @param string $password 密码
     * @return array|bool 验证通过的用户信息或false
     */
    public function login($username, $password) {
        // 根据用户名或邮箱查找用户
        $user = self::getUserByUsername($username);
        if (!$user && filter_var($username, FILTER_VALIDATE_EMAIL)) {
            $user = self::getUserByEmail($username);
        }
        
        // 验证用户是否存在
        if (!$user) {
            return false;
        }
        
        // 验证密码是否正确
        if (!password_verify($password, $user['password'])) {
            return false;
        }
        
        // 验证用户状态是否正常
        if ($user['status'] != self::STATUS_NORMAL || $user['is_deleted'] == 1) {
            return false;
        }
        
        return $user;
    }
    
    /**
     * 用户注册
     * @param string $username 用户名
     * @param string $email 邮箱
     * @param string $password 密码
     * @return int|bool 注册成功返回用户ID，失败返回false
     */
    public function register($username, $email, $password) {
        $data = [
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'role' => 3 // 默认角色为普通用户
        ];

        return self::createUser($data);
    }

    /**
     * 增强版注册方法：写入注册 IP / UA / 设备类型等元信息，
     * 并根据 $forceEmailVerify 决定是否将新用户初始状态置为待邮箱验证。
     * @param string $username 用户名
     * @param string $email 邮箱
     * @param string $password 密码（原始密码，将在 createUser 内哈希）
     * @param string $ip 注册时客户端 IP
     * @param string $userAgent 注册时 User-Agent
     * @param int $forceEmailVerify 是否启用强制邮箱验证（0/1）
     * @return int|false 成功返回用户 id，失败返回 false
     */
    public function registerWithMeta($username, $email, $password, $ip = '', $userAgent = '', $forceEmailVerify = 0) {
        // 解析简易设备/浏览器信息，仅用于后台审计与设备限制统计
        $deviceType = 'other';
        $os = '';
        $browser = '';
        if (!empty($userAgent)) {
            $uaLower = strtolower($userAgent);
            if (strpos($uaLower, 'iphone') !== false || strpos($uaLower, 'android') !== false || strpos($uaLower, 'mobile') !== false) {
                $deviceType = 'mobile';
            } elseif (strpos($uaLower, 'ipad') !== false || strpos($uaLower, 'tablet') !== false) {
                $deviceType = 'tablet';
            } else {
                $deviceType = 'desktop';
            }
            if (preg_match('/windows nt/i', $userAgent)) $os = 'Windows';
            elseif (preg_match('/mac os x/i', $userAgent)) $os = 'macOS';
            elseif (preg_match('/android/i', $userAgent)) $os = 'Android';
            elseif (preg_match('/iphone|ipad|ios/i', $userAgent)) $os = 'iOS';
            elseif (preg_match('/linux/i', $userAgent)) $os = 'Linux';

            if (preg_match('/chrome/i', $userAgent)) $browser = 'Chrome';
            elseif (preg_match('/safari/i', $userAgent)) $browser = 'Safari';
            elseif (preg_match('/firefox/i', $userAgent)) $browser = 'Firefox';
            elseif (preg_match('/edge/i', $userAgent)) $browser = 'Edge';
        }

        // 生成设备指纹（IP + UA 的 MD5），用于设备注册频率限制
        $deviceFingerprint = '';
        if (!empty($ip) && !empty($userAgent)) {
            $deviceFingerprint = md5($ip . '|' . $userAgent);
        }

        $data = [
            'username'                => $username,
            'email'                   => $email,
            'password'                => $password,
            'role'                    => 3,
            'register_ip'             => $ip,
            'register_user_agent'     => $userAgent,
            'register_device_type'    => $deviceType,
            'register_os'             => $os,
            'register_browser'        => $browser,
            'register_device_fingerprint' => $deviceFingerprint,
        ];

        // 若启用强制邮箱验证，则默认设置一个尚未验证的标志（此处将 status 保留为正常，
        // 但在 AuthController 中不自动登录，提示用户查收邮件）。如后续需要严格的"未验证不能登录"，
        // 可在此处将 status 设为自定义值并配合登录逻辑判断。
        $userId = self::createUser($data);
        if (!$userId) return false;
        return $userId;
    }

    /**
     * 统计指定 IP 在最近 $minutes 分钟内已注册的用户数量（用于 IP 注册频率限制）。
     * 依赖 user 表存在 register_ip 与 created_at 字段。
     * @param string $ip 客户端 IP
     * @param int $minutes 时间窗口（分钟）
     * @return int
     */
    public function countRecentRegistrationsByIp($ip, $minutes) {
        if (empty($ip) || (int)$minutes <= 0) return 0;
        $config = Config::get('database');
        $prefix = $config['prefix'];
        $db = Database::getInstance();
        $since = time() - ((int)$minutes * 60);

        // 兼容老数据：若 register_ip 字段尚未存在于表中，则退化到按时间窗口统计总数，
        // 避免查询失败。使用 "SHOW COLUMNS" 会有性能损耗，这里改用 SELECT 1 包裹探测。
        try {
            $sql = "SELECT COUNT(*) AS count FROM {$prefix}user WHERE register_ip = ? AND created_at >= ? AND is_deleted = 0";
            $result = $db->fetch($sql, [$ip, $since]);
            return (int)($result['count'] ?? 0);
        } catch (Exception $e) {
            // register_ip 字段尚未存在于表中，跳过限制
            return 0;
        }
    }

    /**
     * 统计指定设备指纹在最近 $minutes 分钟内已注册的用户数量（用于设备注册频率限制）。
     * 依赖 user 表存在 register_device_fingerprint 与 created_at 字段。
     * 老系统若缺少 register_device_fingerprint 字段，会静默降级为 0，避免阻断注册流程。
     * @param string $fingerprint 设备指纹（md5 值）
     * @param int $minutes 时间窗口（分钟）
     * @return int
     */
    public function countRecentRegistrationsByDevice($fingerprint, $minutes) {
        if (empty($fingerprint) || (int)$minutes <= 0) return 0;
        $config = Config::get('database');
        $prefix = $config['prefix'];
        $db = Database::getInstance();
        $since = time() - ((int)$minutes * 60);

        try {
            $sql = "SELECT COUNT(*) AS count FROM {$prefix}user WHERE register_device_fingerprint = ? AND created_at >= ? AND is_deleted = 0";
            $result = $db->fetch($sql, [$fingerprint, $since]);
            return (int)($result['count'] ?? 0);
        } catch (Exception $e) {
            // register_device_fingerprint 字段尚未存在于表中（老系统），跳过限制
            return 0;
        }
    }

    // 获取用户总数（静态方法，用于后台管理）
    public static function getUserCount() {
        $config = Config::get('database');
        $prefix = $config['prefix'];
        $db = Database::getInstance();
        $sql = "SELECT COUNT(*) as count FROM {$prefix}user WHERE 1 = 1 AND is_deleted = 0";
        $result = $db->fetch($sql, []);
        return $result['count'] ?? 0;
    }
    


    

    
    /**
     * 保存用户头像
     * @param int $userId 用户ID
     * @param array $file 上传的文件信息
     * @return string|bool 头像URL或false
     */
    public function saveUserAvatar($userId, $file) {
        // 使用统一的Upload类上传头像
        require_once CORE_PATH . '/lib/Upload.php';
        $avatarUrl = Upload::uploadAvatar($file, $userId);
        
        if ($avatarUrl) {
            // 更新数据库中的头像URL
            $db = Database::getInstance();
            $result = $db->update('user', ['avatar' => $avatarUrl], ['id' => $userId]);
            
            if ($result > 0) {
                return $avatarUrl;
            }
        }
        
        return false;
    }
    
    /**
     * 检查并创建 last_read_comments_time 字段
     */
    private static function checkAndCreateLastReadCommentsTimeField() {
        $config = Config::get('database');
        $prefix = $config['prefix'];
        $db = Database::getInstance();
        
        try {
            // 检查字段是否存在
            $sql = "SHOW COLUMNS FROM {$prefix}user LIKE 'last_read_comments_time'";
            $result = $db->fetch($sql, []);
            
            if (!$result) {
                // 字段不存在，添加字段
                $addSql = "ALTER TABLE {$prefix}user ADD COLUMN last_read_comments_time INT(11) NOT NULL DEFAULT '0' COMMENT '最后阅读评论消息的时间'";
                $db->query($addSql, []);
            }
        } catch (Exception $e) {
            // 忽略错误，继续执行
        }
    }
    
    /**
     * 获取用户最后阅读评论消息的时间
     * @param int $userId 用户ID
     * @return int 最后阅读时间（时间戳）
     */
    public static function getLastReadCommentsTime($userId) {
        // 检查并创建字段
        self::checkAndCreateLastReadCommentsTimeField();
        
        $config = Config::get('database');
        $prefix = $config['prefix'];
        $db = Database::getInstance();
        $sql = "SELECT last_read_comments_time FROM {$prefix}user WHERE id = ?";
        $result = $db->fetch($sql, [$userId]);
        return $result['last_read_comments_time'] ?? 0;
    }
    
    /**
     * 更新用户最后阅读评论消息的时间
     * @param int $userId 用户ID
     * @param int $time 时间戳，默认为当前时间
     * @return bool
     */
    public static function updateLastReadCommentsTime($userId, $time = null) {
        // 检查并创建字段
        self::checkAndCreateLastReadCommentsTimeField();
        
        $time = $time ?? time();
        $db = Database::getInstance();
        return $db->update('user', ['last_read_comments_time' => $time], ['id' => $userId]);
    }
    
    /**
     * 获取用户通知设置
     * @param int $userId 用户ID
     * @return array 用户通知设置
     */
    public static function getUserNotificationSettings($userId) {
        $user = self::getUserById($userId);
        if (!$user) {
            return [];
        }
        
        // 解析通知设置JSON
        $settings = json_decode($user['notification_settings'] ?? '{}', true);
        if (!is_array($settings)) {
            $settings = [];
        }
        
        // 设置默认值
        $defaultSettings = [
            'types' => [
                'comments' => 1,
                'follows' => 1,
                'system' => 1,
                'articles' => 1,
                'messages' => 1,
                'reviews' => 1,
                'article_likes' => 1,
                'comment_likes' => 1,
                'favorites' => 1
            ]
        ];
        
        // 合并默认设置和用户设置
        $finalSettings = $defaultSettings;
        if (isset($settings['types']) && is_array($settings['types'])) {
            foreach ($settings['types'] as $type => $value) {
                $finalSettings['types'][$type] = (int)$value;
            }
        }
        
        return $finalSettings;
    }
    
    /**
     * 检查用户是否开启了某种类型的通知
     * @param int $userId 用户ID
     * @param string $notificationType 通知类型
     * @return bool 是否开启
     */
    public static function isNotificationTypeEnabled($userId, $notificationType) {
        $settings = self::getUserNotificationSettings($userId);
        return isset($settings['types'][$notificationType]) && $settings['types'][$notificationType] == 1;
    }
    
    /**
     * 生成邮箱验证码
     * @return string 6位数字验证码
     */
    public static function generateEmailVerificationCode() {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * 发送邮箱验证码
     * @param int $userId 用户ID
     * @param string $email 目标邮箱
     * @return bool 发送是否成功
     */
    public static function sendEmailVerificationCode($userId, $email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        
        // 检查邮箱是否已被其他用户使用
        $user = self::getUserByEmail($email);
        if ($user && $user['id'] != $userId) {
            return false;
        }
        
        // 生成验证码
        $code = self::generateEmailVerificationCode();
        $expireTime = time() + 1800; // 30分钟内有效
        
        // 保存验证码到数据库
        $result = self::updateUser($userId, [
            'pending_email' => $email,
            'email_verification_code' => $code,
            'email_verification_expire' => $expireTime
        ]);
        
        if (!$result) {
            return false;
        }
        
        // 获取用户信息
        $currentUser = self::getUserById($userId);
        $username = $currentUser['nickname'] ?: $currentUser['username'];
        
        // 使用模板发送验证邮件
        $variables = [
            'username' => $username,
            'verification_code' => $code,
            'site_name' => Config::get('site_name', 'BlogKit'),
            'site_url' => Config::get('site_url', 'http://localhost')
        ];
        
        $result = Mail::sendWithTemplate($email, 'email_verification', $variables);
        
        return $result;
    }
    
    /**
     * 验证邮箱验证码
     * @param int $userId 用户ID
     * @param string $code 验证码
     * @return bool 验证是否成功
     */
    public static function verifyEmailCode($userId, $code) {
        $user = self::getUserById($userId);
        if (!$user) {
            return false;
        }
        
        // 检查验证码是否过期
        if (empty($user['email_verification_expire']) || $user['email_verification_expire'] < time()) {
            return false;
        }
        
        // 验证验证码是否正确
        if ($user['email_verification_code'] !== $code) {
            return false;
        }
        
        // 验证码正确，更新用户邮箱
        $result = self::updateUser($userId, [
            'email' => $user['pending_email'],
            'pending_email' => null,
            'email_verification_code' => null,
            'email_verification_expire' => null
        ]);
        
        return $result > 0;
    }
    
    /**
     * 检查并创建新字段
     */
    public static function checkAndCreateNewFields() {
        $config = Config::get('database');
        $prefix = $config['prefix'];
        $db = Database::getInstance();
        
        try {
            $fields = [
                'bio', 
                'website', 
                'pending_email', 
                'email_verification_code', 
                'email_verification_expire',
                'register_ip',
                'register_user_agent',
                'register_referer',
                'register_device_type',
                'register_os',
                'register_browser',
                'register_browser_version',
                'privacy_settings',
            ];

            foreach ($fields as $field) {
                $sql = "SHOW COLUMNS FROM {$prefix}user LIKE '{$field}'";
                $result = $db->fetch($sql, []);

                if (!$result) {
                    switch ($field) {
                        case 'bio':
                            $addSql = "ALTER TABLE {$prefix}user ADD COLUMN bio TEXT COMMENT '个人简介'";
                            break;
                        case 'website':
                            $addSql = "ALTER TABLE {$prefix}user ADD COLUMN website VARCHAR(255) DEFAULT NULL COMMENT '个人网站'";
                            break;
                        case 'pending_email':
                            $addSql = "ALTER TABLE {$prefix}user ADD COLUMN pending_email VARCHAR(100) DEFAULT NULL COMMENT '待验证的新邮箱'";
                            break;
                        case 'email_verification_code':
                            $addSql = "ALTER TABLE {$prefix}user ADD COLUMN email_verification_code VARCHAR(10) DEFAULT NULL COMMENT '邮箱验证码'";
                            break;
                        case 'email_verification_expire':
                            $addSql = "ALTER TABLE {$prefix}user ADD COLUMN email_verification_expire INT(11) DEFAULT NULL COMMENT '邮箱验证码过期时间'";
                            break;
                        case 'register_ip':
                            $addSql = "ALTER TABLE {$prefix}user ADD COLUMN register_ip VARCHAR(45) DEFAULT NULL COMMENT '注册IP地址'";
                            break;
                        case 'register_user_agent':
                            $addSql = "ALTER TABLE {$prefix}user ADD COLUMN register_user_agent TEXT DEFAULT NULL COMMENT '注册User-Agent'";
                            break;
                        case 'register_referer':
                            $addSql = "ALTER TABLE {$prefix}user ADD COLUMN register_referer VARCHAR(255) DEFAULT NULL COMMENT '注册来源'";
                            break;
                        case 'register_device_type':
                            $addSql = "ALTER TABLE {$prefix}user ADD COLUMN register_device_type VARCHAR(20) DEFAULT NULL COMMENT '注册设备类型'";
                            break;
                        case 'register_os':
                            $addSql = "ALTER TABLE {$prefix}user ADD COLUMN register_os VARCHAR(50) DEFAULT NULL COMMENT '注册操作系统'";
                            break;
                        case 'register_browser':
                            $addSql = "ALTER TABLE {$prefix}user ADD COLUMN register_browser VARCHAR(50) DEFAULT NULL COMMENT '注册浏览器'";
                            break;
                        case 'register_browser_version':
                            $addSql = "ALTER TABLE {$prefix}user ADD COLUMN register_browser_version VARCHAR(20) DEFAULT NULL COMMENT '注册浏览器版本'";
                            break;
                        case 'privacy_settings':
                            $addSql = "ALTER TABLE {$prefix}user ADD COLUMN privacy_settings TEXT DEFAULT NULL COMMENT '隐私设置（JSON格式，包含关注/粉丝/点赞/收藏的可见范围）'";
                            break;
                    }
                    if (isset($addSql)) {
                        $db->query($addSql, []);
                    }
                }
            }
        } catch (Exception $e) {
            // 忽略错误，继续执行
        }
    }
    
    public static function batchUpdateRole($userIds, $roleId) {
        if (empty($userIds) || empty($roleId)) {
            return 0;
        }
        
        $config = Config::get('database');
        $prefix = $config['prefix'];
        $db = Database::getInstance();
        $timestamp = time();
        $count = 0;
        
        foreach ($userIds as $userId) {
            $userId = intval($userId);
            if (!$userId) continue;
            
            $user = self::getUserById($userId);
            if (!$user || $user['id'] == 1) continue;
            
            $result = $db->update('user', [
                'role' => $roleId,
                'updated_at' => $timestamp
            ], ['id' => $userId]);
            
            if ($result > 0) {
                $count++;
            }
        }
        
        return $count;
    }
    
    public static function getAllUsersForExport($filters = []) {
        $config = Config::get('database');
        $prefix = $config['prefix'];
        $db = Database::getInstance();
        
        $sql = "SELECT id, username, nickname, email, role, status, created_at, last_login_at FROM {$prefix}user WHERE 1 = 1 AND is_deleted = 0";
        $params = [];
        
        if (!empty($filters['search'])) {
            $sql .= " AND (username LIKE ? OR nickname LIKE ? OR email LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (isset($filters['role']) && $filters['role'] != '') {
            $sql .= " AND role = ?";
            $params[] = $filters['role'];
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        return $db->fetchAll($sql, $params);
    }
    
    private static function sendEmail($to, $subject, $body) {
        $siteName = Config::get('site_name', 'BlogKit');
        $siteUrl = Config::get('site_url', 'http://localhost');
        
        $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{$subject}</title>
</head>
<body>
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h2 style="color: #333;">{$subject}</h2>
        <div style="background: #f9f9f9; padding: 20px; border-radius: 8px; margin-top: 20px;">
            <p style="color: #666; line-height: 1.6;">{$body}</p>
        </div>
        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; color: #999; font-size: 12px;">
            <p>此邮件由 {$siteName} 自动发送，请勿回复。</p>
            <p>如非本人操作，请忽略此邮件。</p>
        </div>
    </div>
</body>
</html>
HTML;
        
        return Mail::send($to, $subject, $htmlBody);
    }
    

}