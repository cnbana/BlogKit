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
 * 配置控制器
 * 负责处理系统配置相关的请求，包括基本设置、邮箱配置、API设置等
 */
class ConfigController {
    /**
     * 配置管理首页
     * 根据不同的子页面参数显示不同的配置内容
     */
    public function index() {
        // 获取当前子页面，默认为基本设置
        $subPage = isset($_GET['sub']) ? $_GET['sub'] : 'basic';
        
        // 邮箱配置子菜单
        $emailSubPages = [
            'settings' => '邮箱设置',
            'email_template' => '邮件模板管理'
        ];
        
        // 获取当前邮箱配置的子页面
        $emailSubPage = isset($_GET['email_sub']) ? $_GET['email_sub'] : 'settings';

        // 获取当前域名
        $currentDomain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
        
        // 处理邮箱设置
        if ($subPage == 'email') {
            // 这里只处理邮箱设置，邮件模板管理已经移到独立模块
        } else {
            // 获取系统配置
                $db = Database::getInstance();
                $configs = $db->fetchAll("SELECT * FROM {$db->table('config')} ORDER BY name ASC");
                
                // 配置默认值 — 从权威文件加载（v2.1.0 单一来源）
                $defaultConfig = require APP_PATH . '/Config/default.php';
                $defaultConfig['site_url'] = 'http://' . $currentDomain;
                $defaultConfig['cache_path'] = STORAGE_PATH . '/cache';
                
                // 将配置转换为关联数组
                $config = [];
                foreach ($configs as $item) {
                    $config[$item['name']] = $item['value'];
                }
                
                // 合并默认配置，确保所有配置项都有值
                $config = array_merge($defaultConfig, $config);
            
            // 获取所有主题
            $themes = [];
            $themes_dir = __DIR__ . '/../../themes';
            if (is_dir($themes_dir)) {
                $dir_handle = opendir($themes_dir);
                while ($theme = readdir($dir_handle)) {
                    if ($theme != '.' && $theme != '..' && is_dir($themes_dir . '/' . $theme)) {
                        $themes[] = $theme;
                    }
                }
                closedir($dir_handle);
            }
        }
        
        // 子页面配置
        // （§4.4/§4.5 页面归并：register/login/ip_whitelist 子页已并入 user/security 页，条目移除）
        $subPages = [
            'basic' => '基本设置',
            'email' => '邮箱配置',
            'user' => '用户设置',
            'comment' => '评论设置',
            'captcha' => '验证码设置',
            'rewrite' => '伪静态设置',
            'seo' => 'SEO设置',
            'info' => '系统信息',
            'debug' => '调试设置',
            'cache' => '缓存设置',
            'migrate' => '数据库迁移',
        ];
        
        
        // 确保$sub变量可用（模板中使用）
        $sub = $subPage;
        
        // ============================================
        // 数据库迁移子页面 → 已迁移至 MigrateController
        // ============================================
        if ($subPage === 'migrate') {
            require_once APP_PATH . '/Controllers/Admin/MigrateController.php';
            $ctrl = new MigrateController();
            $ctrl->index();
            exit;
        }
        
        // 显示配置页面
        include ADMIN_PATH . '/templates/config.html';
    }
    
    /**
     * 保存配置
     * 委托给 ConfigService 处理所有验证和持久化逻辑
     */
    public function save() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {

            try {
                $result = ConfigService::save($_POST, $_FILES);

                if (!$result['success']) {
                    $_SESSION['config_errors'] = $result['errors'];
                } else {
                    $_SESSION['config_success'] = '配置保存成功！';
                }
            } catch (PDOException $e) {
                error_log('ConfigController config save DB: ' . $e->getMessage());
                $_SESSION['config_errors'] = ['database' => '保存配置时发生数据库错误'];
            } catch (Exception $e) {
                error_log('ConfigController config save: ' . $e->getMessage());
                $_SESSION['config_errors'] = ['file' => '保存配置时发生文件错误'];
            }

            $redirectUrl = 'admin.php?action=config';
            if (isset($_POST['sub_page'])) {
                $redirectUrl .= '&sub=' . $_POST['sub_page'];
            }
            header('Location: ' . $redirectUrl);
            exit;
        }
    }
    
    /**
     * 生成站点地图
     */
    /**
     * 生成站点地图 → 已迁移至 SitemapController::generate()
     * 此方法仅为向后兼容保留，直接委托给新控制器
     */
    public function generateSitemap() {
        require_once APP_PATH . '/Controllers/Admin/SitemapController.php';
        $ctrl = new SitemapController();
        $ctrl->generate();
    }
    
    /**
     * 测试邮箱配置
     */
    public function testEmail() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            // 设置响应头为 JSON 格式
            header('Content-Type: application/json');
            
            // 获取邮箱配置
            $smtpHost = $_POST['email_smtp_host'] ?? '';
            $smtpPort = $_POST['email_smtp_port'] ?? 465;
            $smtpUsername = $_POST['email_smtp_username'] ?? '';
            $smtpPassword = $_POST['email_smtp_password'] ?? '';
            $smtpEncryption = $_POST['email_smtp_encryption'] ?? 'ssl';
            $fromAddress = $_POST['email_from_address'] ?? $smtpUsername;
            $fromName = $_POST['email_from_name'] ?? '网站名称';
            $testEmail = $_POST['test_email'] ?? $smtpUsername;
            
            // 检查必要配置
            if (empty($smtpHost) || empty($smtpUsername) || empty($smtpPassword) || empty($testEmail)) {
                echo json_encode(['success' => false, 'message' => '请填写完整的邮箱配置和测试邮箱地址']);
                exit;
            }
            
            try {
                // 使用Mail类发送测试邮件
                $subject = '邮箱配置测试邮件';
                $message = "<html><body>";
                $message .= "<h2>邮箱配置测试成功！</h2>";
                $message .= "<p>这是一封测试邮件，用于验证您的邮箱配置是否正确。</p>";
                $message .= "<p>如果您收到这封邮件，说明您的邮箱配置已经成功，可以正常发送邮件。</p>";
                $message .= "<p>--<br>网站团队</p>";
                $message .= "</body></html>";
                
                // 临时设置配置
                Config::set('email_smtp_host', $smtpHost);
                Config::set('email_smtp_port', $smtpPort);
                Config::set('email_smtp_username', $smtpUsername);
                Config::set('email_smtp_password', $smtpPassword);
                Config::set('email_smtp_encryption', $smtpEncryption);
                Config::set('email_from_address', $fromAddress);
                Config::set('email_from_name', $fromName);
                
                // 发送邮件
                $result = Mail::sendSmtp($testEmail, $subject, $message);
                
                if (is_array($result)) {
                    // 新的返回格式
                    echo json_encode($result);
                } else {
                    // 兼容旧的返回格式
                    if ($result) {
                        echo json_encode(['success' => true, 'message' => '邮件发送成功，请检查您的邮箱']);
                    } else {
                        echo json_encode(['success' => false, 'message' => '邮件发送失败，请检查您的配置']);
                    }
                }
            } catch (Exception $e) {
                if (class_exists('Log')) { Log::error('Mail test error', 'admin', ['message' => $e->getMessage()]); }
                else { error_log('Mail test error: ' . $e->getMessage()); }
                echo json_encode(['success' => false, 'message' => '发送失败，请稍后重试']);
            }
            exit;
        }
    }
    
    /**
     * 清理缓存
     */
    public function clearCache() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            // 缓存类通过自动加载器加载
            
            // 获取清理类型
            $cleanType = isset($_POST['cache_clean_type']) ? $_POST['cache_clean_type'] : 'all';
            $cache = Cache::getInstance();
            $result = false;
            
            switch ($cleanType) {
                case 'all':
                    // 清理所有缓存（含模板编译缓存）
                    $result = $cache->clear();
                    // 同时清理模板编译缓存目录
                    $templateCacheDir = STORAGE_PATH . '/cache/templates';
                    if (is_dir($templateCacheDir)) {
                        $templateFiles = glob($templateCacheDir . '/*.php');
                        if ($templateFiles) {
                            foreach ($templateFiles as $file) {
                                @unlink($file);
                            }
                        }
                    }
                    break;
                case 'page':
                    // 清理页面缓存
                    $result = Cache::deleteByPattern('page_');
                    break;
                case 'data':
                    // 清理数据缓存
                    $result = Cache::deleteByPattern('db_');
                    break;
                case 'expired':
                    // 清理过期缓存（通过遍历删除过期文件）
                    $cachePath = Config::get('cache.path', STORAGE_PATH . '/cache');
                    if (is_dir($cachePath)) {
                        $files = glob($cachePath . '/*.cache');
                        foreach ($files as $file) {
                            if (file_exists($file)) {
                                $data = unserialize(@file_get_contents($file));
                                if (isset($data['expire']) && time() > $data['expire']) {
                                    unlink($file);
                                }
                            }
                        }
                        $result = true;
                    }
                    break;
            }
            
            if ($result) {
                $_SESSION['config_success'] = '缓存清理成功！';
            } else {
                $_SESSION['config_errors'] = ['cache' => '缓存清理失败！'];
            }
            
            // 重定向回缓存设置页面
            header('Location: admin.php?action=config&sub=cache');
            exit;
        } else {
            // 非法请求
            header('Location: admin.php?action=config&sub=cache');
            exit;
        }
    }
}
