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
 * 数据库迁移控制器
 * 从 ConfigController 拆分，独立管理数据库 schema 版本演进（原 sub=migrate）
 */
class MigrateController {

    public function index() {
        require_once CORE_PATH . '/lib/Migrate.php';

        // 处理 AJAX 请求
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['migrate_action'])) {
            header('Content-Type: application/json; charset=utf-8');
            $action = $_POST['migrate_action'];
            try {
                if ($action === 'preview') {
                    $report = Migrate::preview();
                    echo json_encode([
                        'success' => true,
                        'changed' => !empty($report['pending_sql']),
                        'report'  => Migrate::getReportSummary(),
                    ]);
                } elseif ($action === 'run') {
                    $report = Migrate::run();
                    echo json_encode([
                        'success' => count($report['errors']) === 0,
                        'changed' => !empty($report['executed_sql']),
                        'report'  => Migrate::getReportSummary(),
                    ]);
                } else {
                    echo json_encode(['success' => false, 'report' => '无效的操作类型: ' . $action]);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'report' => '迁移执行出错: ' . $e->getMessage()]);
            }
            exit;
        }

        // 页面初始状态
        $currentVersion = null;
        $targetVersion = '2.0.1';
        try {
            // 从 install.sql 中提取目标版本号（与 Migrate 类保持一致
            $sqlFile = CORE_PATH . '/config/install.sql';
            if (file_exists($sqlFile)) {
                $handle = fopen($sqlFile, 'r');
                // 只读前 20 行
                for ($i = 0; $i < 20 && !feof($handle); $i++) {
                    $line = fgets($handle);
                    if ($line === false) break;
                    // 匹配：-- 版本：x.x.x 或 -- 版本：x.x
                    if (preg_match('/^\s*--\s*版本[：:]\s*([0-9]+\.[0-9]+(?:\.[0-9]+)?)/iu', $line, $m)) {
                        $targetVersion = trim($m[1]);
                        break;
                    }
                }
                fclose($handle);
            }
            
            $db = Database::getInstance();
            $row = $db->fetch("SELECT `value` FROM `{$db->table('config')}` WHERE `name` = 'db_migration_version' LIMIT 1");
            $currentVersion = $row ? $row['value'] : null;
        } catch (Exception $e) {
            // 出错时使用默认版本
        }

        $migrateStatus = [
            'current_version' => $currentVersion,
            'target_version'  => $targetVersion ?? '2.0.1',
        ];

        // 渲染迁移管理页面
        $currentAction = 'config';
        $pageTitle = '数据库迁移';
        $sub = 'migrate';
        include ADMIN_PATH . '/templates/config.html';
    }
}
