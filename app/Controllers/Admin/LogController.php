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
 * 日志管理控制器
 * 负责处理系统日志的查看、管理和清理等操作
 */
class LogController {
    /**
     * 日志管理首页
     * 显示日志列表，支持过滤和搜索
     */
    public function index() {
        // 检查权限
        $currentUser = $_SESSION['admin'];
        if (!RoleModel::checkUserPermission($currentUser['id'], 'log_manage')) {
            echo '没有权限访问日志管理页面';
            return;
        }
        
        // 获取过滤参数
        $filters = [
            'category' => isset($_GET['category']) ? $_GET['category'] : '',
            'level' => isset($_GET['level']) ? $_GET['level'] : '',
            'keyword' => isset($_GET['keyword']) ? $_GET['keyword'] : '',
            'start_date' => isset($_GET['start_date']) ? $_GET['start_date'] : '',
            'end_date' => isset($_GET['end_date']) ? $_GET['end_date'] : ''
        ];
        
        // 处理日期过滤
        $timeFilters = [];
        if (!empty($filters['start_date'])) {
            $timeFilters['start_time'] = strtotime($filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $timeFilters['end_time'] = strtotime($filters['end_date'] . ' 23:59:59');
        }
        
        // 处理级别过滤
        if (!empty($filters['level'])) {
            $timeFilters['level'] = $filters['level'];
        }
        
        // 处理分类过滤
        if (!empty($filters['category'])) {
            $timeFilters['category'] = $filters['category'];
        }
        
        // 处理关键词过滤
        if (!empty($filters['keyword'])) {
            $timeFilters['keyword'] = $filters['keyword'];
        }
        
        // 获取分页参数（limit 白名单校验）
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = ListQuery::pageSize();
        $allowedPageSizes = ListQuery::pageSizes();
        
        // 获取日志列表
        Log::init();
        $logResult = Log::getLogs($timeFilters, $page, $limit);
        $logs = $logResult['logs'];
        $total = $logResult['total'];
        
        // 获取日志统计信息
        $stats = Log::getStats();
        
        // 获取所有分类
        $categories = Log::getCategories();
        
        // 获取所有级别
        $levels = Log::getLevels();
        
        // 计算分页信息
        $totalPages = ceil($total / $limit);
        
        // 渲染模板
        include ADMIN_PATH . '/templates/log.html';
    }
    
    /**
     * 清理过期日志
     */
    public function clean() {
        // 检查权限
        $currentUser = $_SESSION['admin'];
        if (!RoleModel::checkUserPermission($currentUser['id'], 'log_manage')) {
            echo '没有权限执行此操作';
            return;
        }
        
        try {
            Log::init();
            $result = Log::cleanExpiredLogs();
            
            // 记录操作日志
            if ($result) {
                Log::info('日志管理', '清理过期日志', '成功清理过期日志', Log::CATEGORY_OPERATION);
            } else {
                Log::error('日志管理', '清理过期日志', '清理过期日志失败', Log::CATEGORY_OPERATION);
            }
            
            // 显示成功信息
            $_SESSION['log_success'] = '过期日志清理成功！';
        } catch (Exception $e) {
            // 记录错误日志（完整信息）
            Log::error('日志管理', '清理过期日志', '清理日志时发生错误：' . $e->getMessage(), Log::CATEGORY_OPERATION);
            // 用户提示不暴露细节
            $_SESSION['log_error'] = '清理日志时发生错误，请稍后重试';
        }
        
        // 跳回日志管理页面
        header('Location: admin.php?action=log');
        exit;
    }
    
    /**
     * 清理全部日志
     */
    public function cleanAll() {
        // 检查权限
        $currentUser = $_SESSION['admin'];
        if (!RoleModel::checkUserPermission($currentUser['id'], 'log_manage')) {
            echo '没有权限执行此操作';
            return;
        }
        
        try {
            Log::init();
            
            // 清理所有日志文件
            $logDir = ROOT_PATH . '/' . Log::$config['path'];
            $fileCount = 0;
            if (is_dir($logDir)) {
                $files = glob($logDir . '/*.log*');
                $fileCount = count($files);
                foreach ($files as $file) {
                    unlink($file);
                }
            }
            
            // 清理数据库中的所有日志
            $dbCount = 0;
            if (Log::$config['database']) {
                try {
                    $db = Database::getInstance();
                    $result = $db->query("TRUNCATE TABLE {$db->table('log')}");
                    $dbCount = 1; // 表示数据库清理操作执行
                } catch (Exception $e) {
                    // 忽略数据库错误
                }
            }
            
            // 记录操作日志
            Log::info('日志管理', '清理全部日志', '成功清理全部日志（文件: ' . $fileCount . ', 数据库: ' . ($dbCount ? '是' : '否') . '）', Log::CATEGORY_OPERATION);
            
            // 显示成功信息
            $_SESSION['log_success'] = '全部日志清理成功！';
        } catch (Exception $e) {
            // 记录错误日志（完整信息）
            Log::error('日志管理', '清理全部日志', '清理全部日志时发生错误：' . $e->getMessage(), Log::CATEGORY_OPERATION);
            // 用户提示不暴露细节
            $_SESSION['log_error'] = '清理全部日志时发生错误，请稍后重试';
        }
        
        // 跳回日志管理页面
        header('Location: admin.php?action=log');
        exit;
    }
    
    /**
     * 获取日志统计信息（AJAX）
     */
    public function stats() {
        // 检查权限
        $currentUser = $_SESSION['admin'];
        if (!RoleModel::checkUserPermission($currentUser['id'], 'log_manage')) {
            echo json_encode(['error' => '没有权限访问此接口']);
            return;
        }
        
        try {
            Log::init();
            $stats = Log::getStats();
            
            echo json_encode(['success' => true, 'data' => $stats]);
        } catch (Exception $e) {
            Log::error('Log stats error', 'admin', ['message' => $e->getMessage()]);
            echo json_encode(['success' => false, 'error' => '服务器繁忙，请稍后重试']);
        }
        
        exit;
    }
    
    /**
     * 生成日志报告
     */
    public function report() {
        // 检查权限
        $currentUser = $_SESSION['admin'];
        if (!RoleModel::checkUserPermission($currentUser['id'], 'log_manage')) {
            echo '没有权限执行此操作';
            return;
        }
        
        // 获取报告参数
        $params = [
            'type' => isset($_GET['report_type']) ? $_GET['report_type'] : 'summary',
            'start_date' => isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-7 days')),
            'end_date' => isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d'),
            'categories' => isset($_GET['categories']) ? $_GET['categories'] : [],
            'levels' => isset($_GET['levels']) ? $_GET['levels'] : [],
            'format' => isset($_GET['format']) ? $_GET['format'] : 'html',
            'limit' => isset($_GET['limit']) ? (int)$_GET['limit'] : 100
        ];
        
        // 确保limit在合理范围内
        $params['limit'] = max(1, min(10000, $params['limit']));
        
        // 处理时间参数
        $params['start_time'] = strtotime($params['start_date']);
        $params['end_time'] = strtotime($params['end_date'] . ' 23:59:59');
        
        // 获取日志列表
        Log::init();
        
        // 生成报告
        $report = Log::generateReport($params);
        
        // 记录操作日志
        Log::info('日志管理', '生成日志报告', '成功生成日志报告（类型: ' . $params['type'] . ', 格式: ' . $params['format'] . '）', Log::CATEGORY_OPERATION);
        
        // 导出报告
        switch ($params['format']) {
            case 'csv':
                $this->exportCSV($report, $params);
                break;
            case 'html':
            default:
                $this->exportHTML($report, $params);
                break;
        }
    }
    
    /**
     * 导出为CSV格式
     */
    private function exportCSV($report, $params) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=log_report_' . date('Ymd') . '.csv');
        
        $output = fopen('php://output', 'w');
        
        // 写入表头
        fputcsv($output, ['日期', '时间', '级别', '分类', '消息', 'IP', '用户', '请求URI']);
        
        // 处理全部报告类型
        $logs = [];
        if ($params['type'] == 'all' && isset($report['logs'])) {
            $logs = $report['logs'];
        } elseif (isset($report['logs'])) {
            $logs = $report['logs'];
        }
        
        // 写入数据
        if (!empty($logs)) {
            $displayLimit = $params['limit'];
            $limitedLogs = array_slice($logs, 0, $displayLimit);
            foreach ($limitedLogs as $log) {
                fputcsv($output, [
                    date('Y-m-d', $log['time']),
                    date('H:i:s', $log['time']),
                    $log['level_name'],
                    $log['category'],
                    $log['message'],
                    isset($log['ip']) ? $log['ip'] : '',
                    isset($log['user_id']) ? ($log['user_id'] > 0 ? $log['user_id'] : '系统') : '系统',
                    isset($log['request_uri']) ? $log['request_uri'] : ''
                ]);
            }
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * 导出为HTML格式
     */
    private function exportHTML($report, $params) {
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename=log_report_' . date('Ymd') . '.html');
        
        // 生成HTML报告
        $html = '<!DOCTYPE html>';
        $html .= '<html lang="zh-CN">';
        $html .= '<head>';
        $html .= '<meta charset="UTF-8">';
        $html .= '<title>日志报告 - ' . date('Y-m-d') . '</title>';
        $html .= '<style>';
        $html .= 'body { font-family: Arial, sans-serif; margin: 20px; }';
        $html .= 'h1, h2 { color: #333; }';
        $html .= '.summary { background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; }';
        $html .= '.stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }';
        $html .= '.stat-card { background-color: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }';
        $html .= '.stat-value { font-size: 24px; font-weight: bold; color: #007bff; }';
        $html .= '.stat-label { font-size: 14px; color: #666; }';
        $html .= 'table { width: 100%; border-collapse: collapse; margin: 20px 0; }';
        $html .= 'th, td { padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6; }';
        $html .= 'th { background-color: #f8f9fa; font-weight: bold; }';
        $html .= '.log-level-debug { background-color: #d1ecf1; color: #0c5460; padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; }';
        $html .= '.log-level-info { background-color: #d4edda; color: #155724; padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; }';
        $html .= '.log-level-warning { background-color: #fff3cd; color: #856404; padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; }';
        $html .= '.log-level-error { background-color: #f8d7da; color: #721c24; padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; }';
        $html .= '.log-level-security { background-color: #f5c6cb; color: #721c24; padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; }';
        $html .= '.section { margin-bottom: 30px; }';
        $html .= '.chart-container { margin: 20px 0; }';
        $html .= '</style>';
        $html .= '</head>';
        $html .= '<body>';
        
        // 报告标题
        $html .= '<h1>日志报告</h1>';
        $html .= '<p>生成时间: ' . date('Y-m-d H:i:s') . '</p>';
        $html .= '<p>报告类型: ' . $this->getReportTypeName($params['type']) . '</p>';
        $html .= '<p>时间范围: ' . $params['start_date'] . ' 至 ' . $params['end_date'] . '</p>';
        
        // 处理全部报告类型
        if ($params['type'] == 'all' && isset($report['summary'])) {
            // 摘要部分
            if (isset($report['summary']['stats'])) {
                $html .= '<div class="summary">';
                $html .= '<h2>执行摘要</h2>';
                $html .= '<div class="stats">';
                $html .= '<div class="stat-card">';
                $html .= '<div class="stat-value">' . $report['summary']['stats']['total'] . '</div>';
                $html .= '<div class="stat-label">总日志数</div>';
                $html .= '</div>';
                $html .= '<div class="stat-card">';
                $html .= '<div class="stat-value">' . (isset($report['summary']['stats']['errors']) ? $report['summary']['stats']['errors'] : 0) . '</div>';
                $html .= '<div class="stat-label">错误数</div>';
                $html .= '</div>';
                $html .= '<div class="stat-card">';
                $html .= '<div class="stat-value">' . (isset($report['summary']['stats']['warnings']) ? $report['summary']['stats']['warnings'] : 0) . '</div>';
                $html .= '<div class="stat-label">警告数</div>';
                $html .= '</div>';
                $html .= '<div class="stat-card">';
                $html .= '<div class="stat-value">' . (isset($report['summary']['stats']['by_category']) ? count($report['summary']['stats']['by_category']) : 0) . '</div>';
                $html .= '<div class="stat-label">分类数</div>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';
            }
            
            // 摘要报告内容
            $html .= '<div class="section">';
            $html .= '<h2>摘要报告</h2>';
            
            // 级别分布
            if (isset($report['summary']['stats']['by_level'])) {
                $html .= '<h3>级别分布</h3>';
                $html .= '<table>';
                $html .= '<thead>';
                $html .= '<tr><th>级别</th><th>数量</th><th>占比</th></tr>';
                $html .= '</thead>';
                $html .= '<tbody>';
                foreach ($report['summary']['stats']['by_level'] as $level => $count) {
                    $percentage = $report['summary']['stats']['total'] > 0 ? round(($count / $report['summary']['stats']['total']) * 100, 2) : 0;
                    $html .= '<tr>';
                    $html .= '<td><span class="log-level-' . $level . '">' . $level . '</span></td>';
                    $html .= '<td>' . $count . '</td>';
                    $html .= '<td>' . $percentage . '%</td>';
                    $html .= '</tr>';
                }
                $html .= '</tbody>';
                $html .= '</table>';
            }
            
            // 分类分布
            if (isset($report['summary']['stats']['by_category'])) {
                $html .= '<h3>分类分布</h3>';
                $html .= '<table>';
                $html .= '<thead>';
                $html .= '<tr><th>分类</th><th>数量</th><th>占比</th></tr>';
                $html .= '</thead>';
                $html .= '<tbody>';
                foreach ($report['summary']['stats']['by_category'] as $category => $count) {
                    $percentage = $report['summary']['stats']['total'] > 0 ? round(($count / $report['summary']['stats']['total']) * 100, 2) : 0;
                    $html .= '<tr>';
                    $html .= '<td>' . $category . '</td>';
                    $html .= '<td>' . $count . '</td>';
                    $html .= '<td>' . $percentage . '%</td>';
                    $html .= '</tr>';
                }
                $html .= '</tbody>';
                $html .= '</table>';
            }
            
            // 时间趋势
            if (isset($report['summary']['stats']['by_day'])) {
                $html .= '<h3>时间趋势</h3>';
                $html .= '<table>';
                $html .= '<thead>';
                $html .= '<tr><th>日期</th><th>日志数量</th></tr>';
                $html .= '</thead>';
                $html .= '<tbody>';
                foreach ($report['summary']['stats']['by_day'] as $day => $count) {
                    $html .= '<tr>';
                    $html .= '<td>' . $day . '</td>';
                    $html .= '<td>' . $count . '</td>';
                    $html .= '</tr>';
                }
                $html .= '</tbody>';
                $html .= '</table>';
            }
            $html .= '</div>';
            
            // 分类统计报告
            if (isset($report['category'])) {
                $html .= '<div class="section">';
                $html .= '<h2>分类统计报告</h2>';
                if (isset($report['category']['stats']['by_category'])) {
                    $html .= '<h3>分类分布</h3>';
                    $html .= '<table>';
                    $html .= '<thead>';
                    $html .= '<tr><th>分类</th><th>数量</th><th>占比</th></tr>';
                    $html .= '</thead>';
                    $html .= '<tbody>';
                    foreach ($report['category']['stats']['by_category'] as $category => $count) {
                        $percentage = $report['category']['stats']['total'] > 0 ? round(($count / $report['category']['stats']['total']) * 100, 2) : 0;
                        $html .= '<tr>';
                        $html .= '<td>' . $category . '</td>';
                        $html .= '<td>' . $count . '</td>';
                        $html .= '<td>' . $percentage . '%</td>';
                        $html .= '</tr>';
                    }
                    $html .= '</tbody>';
                    $html .= '</table>';
                }
                $html .= '</div>';
            }
            
            // 级别分布报告
            if (isset($report['level'])) {
                $html .= '<div class="section">';
                $html .= '<h2>级别分布报告</h2>';
                if (isset($report['level']['stats']['by_level'])) {
                    $html .= '<h3>级别分布</h3>';
                    $html .= '<table>';
                    $html .= '<thead>';
                    $html .= '<tr><th>级别</th><th>数量</th><th>占比</th></tr>';
                    $html .= '</thead>';
                    $html .= '<tbody>';
                    foreach ($report['level']['stats']['by_level'] as $level => $count) {
                        $percentage = $report['level']['stats']['total'] > 0 ? round(($count / $report['level']['stats']['total']) * 100, 2) : 0;
                        $html .= '<tr>';
                        $html .= '<td><span class="log-level-' . $level . '">' . $level . '</span></td>';
                        $html .= '<td>' . $count . '</td>';
                        $html .= '<td>' . $percentage . '%</td>';
                        $html .= '</tr>';
                    }
                    $html .= '</tbody>';
                    $html .= '</table>';
                }
                $html .= '</div>';
            }
            
            // 用户操作报告
            if (isset($report['user'])) {
                $html .= '<div class="section">';
                $html .= '<h2>用户操作报告</h2>';
                if (isset($report['user']['stats']['by_user'])) {
                    $html .= '<h3>用户操作分布</h3>';
                    $html .= '<table>';
                    $html .= '<thead>';
                    $html .= '<tr><th>用户</th><th>操作次数</th><th>占比</th></tr>';
                    $html .= '</thead>';
                    $html .= '<tbody>';
                    foreach ($report['user']['stats']['by_user'] as $user => $count) {
                        $percentage = $report['user']['stats']['total'] > 0 ? round(($count / $report['user']['stats']['total']) * 100, 2) : 0;
                        $html .= '<tr>';
                        $html .= '<td>' . ($user == 'system' ? '系统' : $user) . '</td>';
                        $html .= '<td>' . $count . '</td>';
                        $html .= '<td>' . $percentage . '%</td>';
                        $html .= '</tr>';
                    }
                    $html .= '</tbody>';
                    $html .= '</table>';
                }
                $html .= '</div>';
            }
            
            // 错误分析报告
            if (isset($report['error'])) {
                $html .= '<div class="section">';
                $html .= '<h2>错误分析报告</h2>';
                if (isset($report['error']['stats'])) {
                    $html .= '<h3>错误统计</h3>';
                    $html .= '<table>';
                    $html .= '<thead>';
                    $html .= '<tr><th>级别</th><th>数量</th><th>占比</th></tr>';
                    $html .= '</thead>';
                    $html .= '<tbody>';
                    foreach ($report['error']['stats']['by_level'] as $level => $count) {
                        $percentage = $report['error']['stats']['total'] > 0 ? round(($count / $report['error']['stats']['total']) * 100, 2) : 0;
                        $html .= '<tr>';
                        $html .= '<td><span class="log-level-' . $level . '">' . $level . '</span></td>';
                        $html .= '<td>' . $count . '</td>';
                        $html .= '<td>' . $percentage . '%</td>';
                        $html .= '</tr>';
                    }
                    $html .= '</tbody>';
                    $html .= '</table>';
                }
                $html .= '</div>';
            }
            
            // 日志详情
            if (isset($report['logs']) && !empty($report['logs'])) {
                $html .= '<div class="section">';
                $html .= '<h2>日志详情</h2>';
                $html .= '<table>';
                $html .= '<thead>';
                $html .= '<tr><th>时间</th><th>级别</th><th>分类</th><th>消息</th><th>IP</th><th>用户</th></tr>';
                $html .= '</thead>';
                $html .= '<tbody>';
                $displayLimit = $params['limit'];
                foreach (array_slice($report['logs'], 0, $displayLimit) as $log) {
                    $html .= '<tr>';
                    $html .= '<td>' . date('Y-m-d H:i:s', $log['time']) . '</td>';
                    $html .= '<td><span class="log-level-' . $log['level_name'] . '">' . $log['level_name'] . '</span></td>';
                    $html .= '<td>' . $log['category'] . '</td>';
                    $html .= '<td>' . htmlspecialchars($log['message']) . '</td>';
                    $html .= '<td>' . (isset($log['ip']) ? $log['ip'] : '') . '</td>';
                    $html .= '<td>' . (isset($log['user_id']) ? ($log['user_id'] > 0 ? $log['user_id'] : '系统') : '系统') . '</td>';
                    $html .= '</tr>';
                }
                if (count($report['logs']) > $displayLimit) {
                    $html .= '<tr><td colspan="6">仅显示前' . $displayLimit . '条日志，共' . count($report['logs']) . '条</td></tr>';
                }
                $html .= '</tbody>';
                $html .= '</table>';
                $html .= '</div>';
            }
        } else {
            // 处理其他报告类型
            // 摘要部分
            if (isset($report['stats'])) {
                $html .= '<div class="summary">';
                $html .= '<h2>执行摘要</h2>';
                $html .= '<div class="stats">';
                $html .= '<div class="stat-card">';
                $html .= '<div class="stat-value">' . $report['stats']['total'] . '</div>';
                $html .= '<div class="stat-label">总日志数</div>';
                $html .= '</div>';
                $html .= '<div class="stat-card">';
                $html .= '<div class="stat-value">' . (isset($report['stats']['errors']) ? $report['stats']['errors'] : 0) . '</div>';
                $html .= '<div class="stat-label">错误数</div>';
                $html .= '</div>';
                $html .= '<div class="stat-card">';
                $html .= '<div class="stat-value">' . (isset($report['stats']['warnings']) ? $report['stats']['warnings'] : 0) . '</div>';
                $html .= '<div class="stat-label">警告数</div>';
                $html .= '</div>';
                $html .= '<div class="stat-card">';
                $html .= '<div class="stat-value">' . (isset($report['stats']['by_category']) ? count($report['stats']['by_category']) : 0) . '</div>';
                $html .= '<div class="stat-label">分类数</div>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';
            }
            
            // 详细数据部分
            $html .= '<div class="section">';
            $html .= '<h2>详细数据</h2>';
            
            // 级别分布
            if (isset($report['stats']['by_level'])) {
                $html .= '<h3>级别分布</h3>';
                $html .= '<table>';
                $html .= '<thead>';
                $html .= '<tr><th>级别</th><th>数量</th><th>占比</th></tr>';
                $html .= '</thead>';
                $html .= '<tbody>';
                foreach ($report['stats']['by_level'] as $level => $count) {
                    $percentage = $report['stats']['total'] > 0 ? round(($count / $report['stats']['total']) * 100, 2) : 0;
                    $html .= '<tr>';
                    $html .= '<td><span class="log-level-' . $level . '">' . $level . '</span></td>';
                    $html .= '<td>' . $count . '</td>';
                    $html .= '<td>' . $percentage . '%</td>';
                    $html .= '</tr>';
                }
                $html .= '</tbody>';
                $html .= '</table>';
            }
            
            // 分类分布
            if (isset($report['stats']['by_category'])) {
                $html .= '<h3>分类分布</h3>';
                $html .= '<table>';
                $html .= '<thead>';
                $html .= '<tr><th>分类</th><th>数量</th><th>占比</th></tr>';
                $html .= '</thead>';
                $html .= '<tbody>';
                foreach ($report['stats']['by_category'] as $category => $count) {
                    $percentage = $report['stats']['total'] > 0 ? round(($count / $report['stats']['total']) * 100, 2) : 0;
                    $html .= '<tr>';
                    $html .= '<td>' . $category . '</td>';
                    $html .= '<td>' . $count . '</td>';
                    $html .= '<td>' . $percentage . '%</td>';
                    $html .= '</tr>';
                }
                $html .= '</tbody>';
                $html .= '</table>';
            }
            
            // 时间趋势
            if (isset($report['stats']['by_day'])) {
                $html .= '<h3>时间趋势</h3>';
                $html .= '<table>';
                $html .= '<thead>';
                $html .= '<tr><th>日期</th><th>日志数量</th></tr>';
                $html .= '</thead>';
                $html .= '<tbody>';
                foreach ($report['stats']['by_day'] as $day => $count) {
                    $html .= '<tr>';
                    $html .= '<td>' . $day . '</td>';
                    $html .= '<td>' . $count . '</td>';
                    $html .= '</tr>';
                }
                $html .= '</tbody>';
                $html .= '</table>';
            }
            
            // 日志详情
            if (isset($report['logs']) && !empty($report['logs'])) {
                $html .= '<h3>日志详情</h3>';
                $html .= '<table>';
                $html .= '<thead>';
                $html .= '<tr><th>时间</th><th>级别</th><th>分类</th><th>消息</th><th>IP</th><th>用户</th></tr>';
                $html .= '</thead>';
                $html .= '<tbody>';
                $displayLimit = $params['limit'];
                foreach (array_slice($report['logs'], 0, $displayLimit) as $log) {
                    $html .= '<tr>';
                    $html .= '<td>' . date('Y-m-d H:i:s', $log['time']) . '</td>';
                    $html .= '<td><span class="log-level-' . $log['level_name'] . '">' . $log['level_name'] . '</span></td>';
                    $html .= '<td>' . $log['category'] . '</td>';
                    $html .= '<td>' . htmlspecialchars($log['message']) . '</td>';
                    $html .= '<td>' . (isset($log['ip']) ? $log['ip'] : '') . '</td>';
                    $html .= '<td>' . (isset($log['user_id']) ? ($log['user_id'] > 0 ? $log['user_id'] : '系统') : '系统') . '</td>';
                    $html .= '</tr>';
                }
                if (count($report['logs']) > $displayLimit) {
                    $html .= '<tr><td colspan="6">仅显示前' . $displayLimit . '条日志，共' . count($report['logs']) . '条</td></tr>';
                }
                $html .= '</tbody>';
                $html .= '</table>';
            }
        }
        
        $html .= '</div>';
        $html .= '</body>';
        $html .= '</html>';
        
        echo $html;
        exit;
    }
    
    /**
     * 获取报告类型的中文名称
     */
    private function getReportTypeName($type) {
        $types = [
            'summary' => '摘要报告',
            'category' => '分类统计报告',
            'level' => '级别分布报告',
            'user' => '用户操作报告',
            'error' => '错误分析报告',
            'all' => '全部报告'
        ];
        return isset($types[$type]) ? $types[$type] : $type;
    }
    
    /**
     * 显示日志设置页面
     */
    public function settings() {
        // 检查权限
        $currentUser = $_SESSION['admin'];
        if (!RoleModel::checkUserPermission($currentUser['id'], 'log_manage')) {
            echo '没有权限访问日志设置页面';
            return;
        }
        
        // 加载日志配置
        Log::init();
        $settings = Log::$config;
        
        // 渲染设置页面
        include ADMIN_PATH . '/templates/log_settings.html';
    }
    
    /**
     * 保存日志设置
     */
    public function saveSettings() {
        // 检查权限
        $currentUser = $_SESSION['admin'];
        if (!RoleModel::checkUserPermission($currentUser['id'], 'log_manage')) {
            echo '没有权限修改日志设置';
            return;
        }
        
        try {
            // 加载日志配置
            Log::init();
            
            // 获取表单提交的设置
            $newSettings = [
                'enabled' => isset($_POST['enabled']) ? (bool)$_POST['enabled'] : false,
                'storage' => isset($_POST['storage']) ? $_POST['storage'] : 'file',
                'log_level' => isset($_POST['log_level']) ? (int)$_POST['log_level'] : 1,
                'log_rotation' => isset($_POST['log_rotation']) ? (int)$_POST['log_rotation'] : 7,
                'log_retention' => isset($_POST['log_retention']) ? (int)$_POST['log_retention'] : 7,
                'log_file_size' => isset($_POST['log_file_size']) ? (int)$_POST['log_file_size'] : 10,
                'log_path' => Log::PATH_REL,
                'log_format' => isset($_POST['log_format']) ? $_POST['log_format'] : 'text',
                'enabled_categories' => isset($_POST['enabled_categories']) ? $_POST['enabled_categories'] : []
            ];
            
            // 处理日志分类配置
            if (isset($_POST['enabled_categories']) && is_array($_POST['enabled_categories'])) {
                $newSettings['enabled_categories'] = $_POST['enabled_categories'];
            } else {
                $newSettings['enabled_categories'] = [];
            }
            
            // 保存设置
            $result = Log::saveSettings($newSettings);
            
            // 记录操作日志
            if ($result) {
                Log::info('日志管理', '保存日志设置', '成功保存日志设置', Log::CATEGORY_OPERATION);
            } else {
                Log::error('日志管理', '保存日志设置', '保存日志设置失败', Log::CATEGORY_OPERATION);
            }
            
            // 显示成功信息
            $_SESSION['log_success'] = '日志设置保存成功！';
        } catch (Exception $e) {
            // 记录错误日志（完整信息）
            Log::error('日志管理', '保存日志设置', '保存日志设置时发生错误：' . $e->getMessage(), Log::CATEGORY_OPERATION);
            // 用户提示不暴露细节
            $_SESSION['log_error'] = '保存日志设置时发生错误，请稍后重试';
        }
        
        // 跳回设置页面
        header('Location: admin.php?action=log&method=settings');
        exit;
    }
    
    /**
     * 恢复默认设置
     */
    public function resetSettings() {
        // 检查权限
        $currentUser = $_SESSION['admin'];
        if (!RoleModel::checkUserPermission($currentUser['id'], 'log_manage')) {
            echo '没有权限修改日志设置';
            return;
        }
        
        try {
            // 加载日志配置
            Log::init();
            
            // 恢复默认设置
            $result = Log::resetSettings();
            
            // 记录操作日志
            if ($result) {
                Log::info('日志管理', '恢复默认设置', '成功恢复日志默认设置', Log::CATEGORY_OPERATION);
            } else {
                Log::error('日志管理', '恢复默认设置', '恢复日志默认设置失败', Log::CATEGORY_OPERATION);
            }
            
            // 显示成功信息
            $_SESSION['log_success'] = '日志设置已恢复默认值！';
        } catch (Exception $e) {
            // 记录错误日志（完整信息）
            Log::error('日志管理', '恢复默认设置', '恢复默认设置时发生错误：' . $e->getMessage(), Log::CATEGORY_OPERATION);
            // 用户提示不暴露细节
            $_SESSION['log_error'] = '恢复默认设置时发生错误，请稍后重试';
        }
        
        // 跳回设置页面
        header('Location: admin.php?action=log&method=settings');
        exit;
    }
}
