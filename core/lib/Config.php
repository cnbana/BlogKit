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


class Config {
    private static $config = [];
    private static $instance = null;
    
    /**
     * 加载配置文件
     * @param string $name 配置文件名
     * @return array
     */
    public static function load($name) {
        $file = CORE_PATH . '/config/' . $name . '.php';
        if (file_exists($file)) {
            self::$config[$name] = require $file;
            return self::$config[$name];
        }
        return [];
    }
    
    /**
     * 从数据库加载配置
     */
    public static function loadFromDatabase() {
        // 确保数据库配置已加载
        if (!isset(self::$config['database'])) {
            self::load('database');
        }
        
        try {
            // 使用Database类来连接数据库，利用其字符集错误处理机制
            require_once CORE_PATH . '/lib/Database.php';
            $db = Database::getInstance();
            $pdo = $db->getPdo();
            
            // 获取配置表中的所有配置
            $config = self::get('database');
            $sql = "SELECT * FROM {$config['prefix']}config";
            $stmt = $pdo->query($sql);
            $configs = $stmt->fetchAll();
            
            // 将配置添加到内存中的配置数组
            foreach ($configs as $configItem) {
                $key = $configItem['name'];
                $value = $configItem['value'];
                $type = $configItem['type'];
                
                // 根据类型转换值
                switch ($type) {
                    case 'boolean':
                        // 确保能正确处理字符串 '0' 和 '1'
                        $value = in_array(strtolower($value), ['0', 'false', 'no']) ? false : (bool)$value;
                        break;
                    case 'integer':
                        $value = (int)$value;
                        break;
                    case 'array':
                        $value = json_decode($value, true);
                        break;
                }
                
                // 特殊处理调试配置，将下划线命名转换为点分隔
                if (strpos($key, 'debug_') === 0) {
                    $debugKey = 'debug.' . substr($key, 6);
                    self::set($debugKey, $value);
                }
                
                // 特殊处理缓存配置，将下划线命名转换为点分隔
                if (strpos($key, 'cache_') === 0) {
                    $cacheKey = 'cache.' . substr($key, 6);
                    self::set($cacheKey, $value);
                }

                // 通用别名映射：如果配置键中包含下划线，同时注册一份点语法版本
                // 例如 site_url → 也可通过 site.url 读取
                // 用于统一旧代码（site_url）与新代码（site.url）的访问方式
                if (strpos($key, '_') !== false && strpos($key, 'debug_') !== 0 && strpos($key, 'cache_') !== 0) {
                    $dotKey = str_replace('_', '.', $key);
                    self::set($dotKey, $value);
                }
                
                // 设置配置
                self::set($key, $value);
            }
            
            // 特殊处理URL重写配置
            self::loadRewriteConfig();
            
        } catch (PDOException $e) {
            // 数据库连接失败时使用默认配置
            error_log('Database connection failed when loading config: ' . $e->getMessage());
        }
    }
    
    /**
     * 加载URL重写配置
     */
    private static function loadRewriteConfig() {
        // 获取URL重写相关配置
        $enabled = self::get('rewrite_enabled', 0);

        // 基础页面（有ID/参数的动态页面）
        $articleRule         = self::get('rewrite_article',                 'article/{id}');
        $categoryRule        = self::get('rewrite_category',                'category/{id}');
        $tagRule             = self::get('rewrite_tag',                     'tag/{id}');
        $tagsRule            = self::get('rewrite_tags',                    'tags');
        $pageRule            = self::get('rewrite_page',                    'page/{id}');
        $userRule            = self::get('rewrite_user',                    'user/{id}');
        $followRule          = self::get('rewrite_follow',                  'follow/{id}');
        $followingRule       = self::get('rewrite_following',               'following/{id}');
        $followersRule       = self::get('rewrite_followers',               'followers/{id}');

        // 固定页面（无参数）
        $loginRule           = self::get('rewrite_login',                   'login');
        $registerRule        = self::get('rewrite_register',                'register');
        $logoutRule          = self::get('rewrite_logout',                  'logout');
        $profileRule         = self::get('rewrite_profile',                 'profile');
        $searchRule          = self::get('rewrite_search',                  'search');
        $rssRule             = self::get('rewrite_rss',                     'rss');
        $forgotRule          = self::get('rewrite_forgot_password',         'forgot-password');
        $resetRule           = self::get('rewrite_reset_password',          'reset-password');

        // 个人中心子页面（无参数，固定路径）
        $profileArticlesRule    = self::get('rewrite_profile_articles',     'profile/articles');
        $profileCommentsRule    = self::get('rewrite_profile_comments',     'profile/comments');
        $profileSettingsRule    = self::get('rewrite_profile_settings',     'profile/settings');
        $profileFavoritesRule   = self::get('rewrite_profile_favorites',    'profile/favorites');
        $profileLikesRule       = self::get('rewrite_profile_likes',        'profile/likes');
        $profileNotifications   = self::get('rewrite_profile_notifications','profile/notifications');
        $profileHistory         = self::get('rewrite_profile_history',      'profile/history');
        $profileFollowing       = self::get('rewrite_profile_following',    'profile/following');
        $profileFollowers       = self::get('rewrite_profile_followers',    'profile/followers');

        // 总开关（TemplateUrl 和 Router 都通过此值判断是否启用伪静态）
        self::set('rewrite.enabled', (bool)$enabled);

        // URL 规则映射表：["用户填写的规则" => "内部路由"]
        // 用于 Router 类解析 URL（新的 resolveRoute 方法主要依赖自己的路由表，
        // 这里保留是为了向后兼容）
        self::set('rewrite.rules', [
            // 动态页面（带参数）
            $articleRule      => 'Home/article/:id',
            $categoryRule     => 'Home/category/:id',
            $tagRule          => 'Home/tag/:id',
            $tagsRule         => 'Home/tags',
            $pageRule         => 'Home/page/:id',
            $userRule         => 'Auth/user/:id',
            $followRule       => 'Auth/follow/:id',
            $followingRule    => 'Auth/following/:id',
            $followersRule    => 'Auth/followers/:id',

            // 固定页面（无参数）
            $loginRule        => 'Home/login',
            $registerRule     => 'Home/register',
            $logoutRule       => 'Home/logout',
            $profileRule      => 'Home/profile',
            $searchRule       => 'Home/search',
            $rssRule          => 'RSS/feed',
            $forgotRule       => 'Auth/forgot',
            $resetRule        => 'Auth/reset',

            // 个人中心子页面
            $profileArticlesRule    => 'Profile/articles',
            $profileCommentsRule    => 'Profile/comments',
            $profileSettingsRule    => 'Profile/settings',
            $profileFavoritesRule   => 'Profile/favorites',
            $profileLikesRule       => 'Profile/likes',
            $profileNotifications   => 'Profile/notifications',
            $profileHistory         => 'Profile/history',
            $profileFollowing       => 'Profile/following',
            $profileFollowers       => 'Profile/followers',
        ]);
    }
    
    /**
     * 获取配置值
     * @param string $key 配置键，支持点分隔（如：database.host）或下划线命名（如：captcha_enabled）
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function get($key, $default = null) {
        // 首先尝试直接获取（下划线命名）
        if (isset(self::$config[$key])) {
            return self::$config[$key];
        }
        
        // 然后尝试点分隔获取
        $keys = explode('.', $key);
        $value = self::$config;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
    
    /**
     * 设置配置值
     * @param string $key 配置键
     * @param mixed $value 配置值
     */
    public static function set($key, $value) {
        // 如果包含点分隔符，使用点分隔处理
        if (strpos($key, '.') !== false) {
            $keys = explode('.', $key);
            $config = &self::$config;
            
            foreach ($keys as $k) {
                if (!isset($config[$k]) || !is_array($config[$k])) {
                    $config[$k] = [];
                }
                $config = &$config[$k];
            }
            
            $config = $value;
        } else {
            // 否则直接设置（下划线命名）
            self::$config[$key] = $value;
        }
    }

    // ==================== SMTP 密码加解密工具 ====================

    /**
     * 对敏感字符串（例如 SMTP 密码）进行可逆加密后返回可存储字符串
     * 格式：ENC$base64(iv)$base64(ciphertext)
     * 若 PHP 版本不支持 openssl，将返回原值（带 RAW$ 前缀表示未加密）
     *
     * @param string $plaintext
     * @return string 可直接写入 bk_config 的字符串
     */
    public static function encryptSensitive($plaintext) {
        if ($plaintext === '' || $plaintext === null) {
            return '';
        }
        // 已经加密过的值不重复加密
        if (is_string($plaintext) && strpos($plaintext, 'ENC$') === 0) {
            return $plaintext;
        }
        if (!function_exists('openssl_encrypt') || !in_array('AES-256-CBC', openssl_get_cipher_methods())) {
            return 'RAW$' . $plaintext;
        }
        $key = self::getEncryptionKey();
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-CBC'));
        $ciphertext = openssl_encrypt(
            (string)$plaintext,
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
        if ($ciphertext === false) {
            return 'RAW$' . $plaintext;
        }
        return 'ENC$' . base64_encode($iv) . '$' . base64_encode($ciphertext);
    }

    /**
     * 解密 encryptSensitive() 产出的字符串
     * 对没有加密前缀的原值直接返回（保证向后兼容）
     *
     * @param string $stored
     * @return string 明文
     */
    public static function decryptSensitive($stored) {
        if ($stored === null || $stored === '' || !is_string($stored)) {
            return '';
        }
        if (strpos($stored, 'ENC$') === 0) {
            $parts = explode('$', $stored, 3);
            if (count($parts) < 3) {
                return '';
            }
            list(, $ivB64, $ctB64) = $parts;
            if (!function_exists('openssl_decrypt')) {
                return '';
            }
            $key = self::getEncryptionKey();
            $plaintext = openssl_decrypt(
                base64_decode($ctB64),
                'AES-256-CBC',
                $key,
                OPENSSL_RAW_DATA,
                base64_decode($ivB64)
            );
            return $plaintext === false ? '' : $plaintext;
        }
        if (strpos($stored, 'RAW$') === 0) {
            return substr($stored, 4);
        }
        // 向后兼容：数据库中遗留的明文密码直接返回
        return $stored;
    }

    /**
     * 获取加密密钥
     * 优先从 database 配置中读取 db_password（安装时已经设置，全局唯一），
     * 其次使用系统自带的 site_name，确保每台服务器的密钥不同。
     *
     * @return string 32 字节密钥
     */
    private static function getEncryptionKey() {
        static $cachedKey = null;
        if ($cachedKey !== null) {
            return $cachedKey;
        }
        $seed = '';
        if (isset(self::$config['database']['password'])) {
            $seed .= self::$config['database']['password'];
        }
        if (isset(self::$config['site_name'])) {
            $seed .= self::$config['site_name'];
        }
        if ($seed === '') {
            $seed = '__fallback_blogkit_key__';
        }
        $cachedKey = substr(hash('sha256', $seed, true), 0, 32);
        return $cachedKey;
    }
    
    /**
     * 初始化配置
     */
    public static function init() {
        self::load('database');
        self::load('system');
        self::loadFromDatabase();
    }
    
    /**
     * 获取所有配置项
     * @return array 所有配置项
     */
    public static function getAll() {
        return self::$config;
    }
    
    /**
     * 获取公开配置项（用于API）
     * @return array 公开配置项
     */
    public static function getPublicConfig() {
        $publicConfig = [
            'site_name' => self::get('site_name', 'BlogKit'),
            'site_url' => self::get('site_url', ''),
            'site_description' => self::get('site_description', ''),
            'site_keywords' => self::get('site_keywords', ''),
            'pagination_count' => self::get('pagination_count', 10),
            'comment_enabled' => self::get('comment_enabled', '1'),
            'like_enabled' => self::get('like_enabled', '1'),
            'favorite_enabled' => self::get('favorite_enabled', '1'),
            'follow_enabled' => self::get('follow_enabled', '1'),
            'read_history_enabled' => self::get('read_history_enabled', '1'),
            'message_enabled' => self::get('message_enabled', '1'),
            'message_duration' => self::get('message_duration', '3'),
            'register_enabled' => self::get('register_enabled', '1'),
            'login_enabled' => self::get('login_enabled', '1'),
            'api_enabled' => self::get('api_enabled', '0'),
            'rewrite_enabled' => self::get('rewrite.enabled', false),
        ];
        
        return $publicConfig;
    }
    
    /**
     * 获取单例实例（用于插件系统服务容器）
     * @return Config
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 删除配置项（实例方法，用于插件系统）
     * 同时从内存和数据库中删除
     * @param string $key 配置键名
     * @return $this
     */
    public function delete($key) {
        // 从内存配置中移除
        if (strpos($key, '.') !== false) {
            $keys = explode('.', $key);
            $config = &self::$config;
            $lastKey = array_pop($keys);
            foreach ($keys as $k) {
                if (!isset($config[$k]) || !is_array($config[$k])) {
                    return $this;
                }
                $config = &$config[$k];
            }
            unset($config[$lastKey]);
        } else {
            unset(self::$config[$key]);
        }
        
        // 尝试从数据库删除
        try {
            $db = Database::getInstance();
            $db->query("DELETE FROM {$db->table('config')} WHERE name = ?", [$key]);
        } catch (\Exception $e) {
            // 数据库不可用时静默失败
        }
        
        return $this;
    }
}
