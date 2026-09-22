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
 * 缓存控制器
 * 负责处理缓存设置和缓存清理相关的请求
 */
class CacheController {
    
    /**
     * 缓存设置首页
     */
    public function index() {
        $db = Database::getInstance();
        $currentDomain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
        
        $configs = $db->fetchAll("SELECT * FROM {$db->table('config')} ORDER BY name ASC");
        
        $defaultConfig = require APP_PATH . '/Config/default.php';
        $defaultConfig['site_url'] = 'http://' . $currentDomain;
        $defaultConfig['cache_path'] = STORAGE_PATH . '/cache';
        
        $config = [];
        foreach ($configs as $item) {
            $config[$item['name']] = $item['value'];
        }
        
        $config = array_merge($defaultConfig, $config);
        
        $sub = 'cache';
        $currentAction = 'cache';
        
        include ADMIN_PATH . '/templates/config.html';
    }
    
    /**
     * 清理缓存
     */
    public function clear() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $cleanType = isset($_POST['cache_clean_type']) ? $_POST['cache_clean_type'] : 'all';
            $cache = Cache::getInstance();
            $result = false;
            
            switch ($cleanType) {
                case 'all':
                    $result = $cache->clear();
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
                    $result = Cache::deleteByPattern('page_');
                    break;
                case 'data':
                    $result = Cache::deleteByPattern('db_');
                    break;
                case 'expired':
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
            
            header('Location: admin.php?action=cache');
            exit;
        } else {
            header('Location: admin.php?action=cache');
            exit;
        }
    }
    
    /**
     * 保存缓存配置
     */
    public function save() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST['sub_page'] = 'cache';
            
            try {
                $result = ConfigService::save($_POST, $_FILES);
                
                if (!$result['success']) {
                    $_SESSION['config_errors'] = $result['errors'];
                } else {
                    $_SESSION['config_success'] = '配置保存成功！';
                }
            } catch (PDOException $e) {
                error_log('CacheController config save DB: ' . $e->getMessage());
                $_SESSION['config_errors'] = ['database' => '保存配置时发生数据库错误'];
            } catch (Exception $e) {
                error_log('CacheController config save: ' . $e->getMessage());
                $_SESSION['config_errors'] = ['file' => '保存配置时发生文件错误'];
            }
            
            header('Location: admin.php?action=cache');
            exit;
        }
    }
}
