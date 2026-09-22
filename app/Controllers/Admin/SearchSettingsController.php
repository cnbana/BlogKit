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
 * 搜索设置控制器
 * 负责处理网站搜索设置相关的请求
 */
class SearchSettingsController
{
    /**
     * 搜索设置首页
     */
    public function index()
    {
        $db = Database::getInstance();
        $currentDomain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';

        // 获取系统配置
        $configs = $db->fetchAll("SELECT * FROM {$db->table('config')} ORDER BY name ASC");

        // 配置默认值
        $defaultConfig = require APP_PATH . '/Config/default.php';
        $defaultConfig['site_url'] = 'http://' . $currentDomain;
        $defaultConfig['cache_path'] = STORAGE_PATH . '/cache';

        // 将配置转换为关联数组
        $config = [];
        foreach ($configs as $item) {
            $config[$item['name']] = $item['value'];
        }

        $config = array_merge($defaultConfig, $config);

        // 设置当前页面变量用于侧边栏高亮
        $sub = 'search';
        $currentAction = 'search_settings';

        // 使用标准的 config.html 模板
        include ADMIN_PATH . '/templates/config.html';
    }

    /**
     * 保存搜索设置
     */
    public function save()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST['sub_page'] = 'search';

            try {
                $result = ConfigService::save($_POST, $_FILES);

                if (!$result['success']) {
                    $_SESSION['config_errors'] = $result['errors'];
                } else {
                    $_SESSION['config_success'] = '配置保存成功！';
                }
            } catch (PDOException $e) {
                error_log('SearchSettingsController config save DB: ' . $e->getMessage());
                $_SESSION['config_errors'] = ['database' => '保存配置时发生数据库错误'];
            } catch (Exception $e) {
                error_log('SearchSettingsController config save: ' . $e->getMessage());
                $_SESSION['config_errors'] = ['file' => '保存配置时发生文件错误'];
            }

            header('Location: admin.php?action=search_settings');
            exit;
        }
    }
}
