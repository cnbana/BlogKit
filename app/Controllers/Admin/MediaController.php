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
 * 媒体管理控制器
 * 负责处理媒体文件的管理操作
 */
class MediaController {
    /**
     * 媒体管理首页
     */
    public function index() {
        // 获取查询参数
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $type = isset($_GET['type']) ? $_GET['type'] : 'all';
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        // 月份筛选（格式 YYYY-MM，取自文件修改时间；非法格式视为不过滤）
        $month = isset($_GET['month']) ? trim($_GET['month']) : '';
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = '';
        }
        // 每页条数（limit 白名单校验）
        $perPage = ListQuery::pageSize();
        $allowedPageSizes = ListQuery::pageSizes();

        // 计算偏移量
        $offset = ($page - 1) * $perPage;

        // 获取媒体文件列表
        $mediaFiles = $this->getMediaFiles($type, $search, $offset, $perPage, $month);
        $totalFiles = $this->getTotalFiles($type, $search, $month);
        $totalPages = ceil($totalFiles / $perPage);

        // 获取文件统计信息
        $fileStats = $this->getFileStatistics();

        // 获取可选月份列表（按当前类型目录，倒序排列，供筛选下拉框使用）
        $availableMonths = $this->getAvailableMonths($type);

        // 准备分页数据
        $pagination = [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'per_page' => $perPage,
            'total_items' => $totalFiles,
            'base_url' => 'admin.php?action=media&type=' . urlencode($type) . '&search=' . urlencode($search) . '&month=' . urlencode($month)
        ];
        
        // 加载模板
        include ADMIN_PATH . '/templates/media.html';
    }
    
    /**
     * 获取媒体文件列表
     */
    private function getMediaFiles($type, $search, $offset, $limit, $month = '') {
        $files = [];
        $uploadDir = UPLOADS_PATH . '/';
        
        // 定义文件类型映射（articles 为 UEditor 文章图片上传目录，需包含否则媒体管理看不到文件）
        $typeDirs = [
            'image' => 'images',
            'file' => 'files',
            'avatar' => 'avatars',
            'logo' => 'logo',
            'cover' => 'cover',
            'attachment' => 'attachments',
            'article' => 'articles'
        ];
        
        // 确定要扫描的目录
        $dirs = [];
        if ($type == 'all') {
            $dirs = array_values($typeDirs);
        } elseif (isset($typeDirs[$type])) {
            $dirs = [$typeDirs[$type]];
        }
        
        // 扫描目录
        foreach ($dirs as $dir) {
            $fullDir = $uploadDir . $dir;
            if (is_dir($fullDir)) {
                $this->scanDirectory($fullDir, $dir, $files, $search, $month);
            }
        }

        // 排序
        $sort = isset($_GET['sort']) ? $_GET['sort'] : 'date_desc';
        switch ($sort) {
            case 'name_asc':
                usort($files, function($a, $b) {
                    return strcmp($a['name'], $b['name']);
                });
                break;
            case 'name_desc':
                usort($files, function($a, $b) {
                    return strcmp($b['name'], $a['name']);
                });
                break;
            case 'size_asc':
                usort($files, function($a, $b) {
                    return $a['size'] - $b['size'];
                });
                break;
            case 'size_desc':
                usort($files, function($a, $b) {
                    return $b['size'] - $a['size'];
                });
                break;
            case 'date_asc':
                usort($files, function($a, $b) {
                    return $a['modified'] - $b['modified'];
                });
                break;
            case 'date_desc':
                usort($files, function($a, $b) {
                    return $b['modified'] - $a['modified'];
                });
                break;
            case 'type_asc':
                usort($files, function($a, $b) {
                    return strcmp($a['type'], $b['type']);
                });
                break;
        }
        
        // 分页
        return array_slice($files, $offset, $limit);
    }
    
    /**
     * 扫描目录
     */
    private function scanDirectory($dir, $baseDir, &$files, $search, $month = '') {
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                // 递归扫描子目录
                $this->scanDirectory($path, $baseDir, $files, $search, $month);
            } else {
                // 过滤图片处理插件自动生成的衍生文件（_large/_medium/_small 尺寸变体、webp 转换副本），避免列表出现大量重复图片
                if (MediaService::isGeneratedVariant($path)) {
                    continue;
                }

                // 检查是否符合搜索条件
                if (!empty($search) && strpos(strtolower($item), strtolower($search)) === false) {
                    continue;
                }

                // 月份筛选：按文件修改时间的年月（YYYY-MM）比对
                if ($month !== '' && date('Y-m', filemtime($path)) !== $month) {
                    continue;
                }
                
                // 获取文件信息
                $fileInfo = [
                    'name' => $item,
                    'path' => str_replace(ROOT_PATH, '', $path),
                    'url' => str_replace(ROOT_PATH, '', $path),
                    'size' => filesize($path),
                    'size_formatted' => $this->format_bytes(filesize($path)),
                    'modified' => filemtime($path),
                    'modified_formatted' => $this->format_date(filemtime($path)),
                    'type' => $this->getFileType($item)
                ];
                
                $files[] = $fileInfo;
            }
        }
    }
    
    /**
     * 获取文件类型
     */
    private function getFileType($filename) {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $docExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx'];
        $archiveExts = ['zip', 'rar', '7z'];
        
        if (in_array($ext, $imageExts)) {
            return 'image';
        } elseif (in_array($ext, $docExts)) {
            return 'document';
        } elseif (in_array($ext, $archiveExts)) {
            return 'archive';
        } else {
            return 'other';
        }
    }
    
    /**
     * 格式化文件大小
     */
    private function format_bytes($bytes) {
        if ($bytes === 0) return '0 Bytes';
        $k = 1024;
        $sizes = ['Bytes', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes) / log($k));
        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }
    
    /**
     * 格式化日期
     */
    private function format_date($timestamp) {
        return date('Y-m-d H:i:s', $timestamp);
    }
    
    /**
     * 获取文件总数
     */
    private function getTotalFiles($type, $search, $month = '') {
        $files = [];
        $uploadDir = UPLOADS_PATH . '/';
        
        // 定义文件类型映射（articles 为 UEditor 文章图片上传目录，需包含否则媒体管理看不到文件）
        $typeDirs = [
            'image' => 'images',
            'file' => 'files',
            'avatar' => 'avatars',
            'logo' => 'logo',
            'cover' => 'cover',
            'attachment' => 'attachments',
            'article' => 'articles'
        ];
        
        // 确定要扫描的目录
        $dirs = [];
        if ($type == 'all') {
            $dirs = array_values($typeDirs);
        } elseif (isset($typeDirs[$type])) {
            $dirs = [$typeDirs[$type]];
        }
        
        // 扫描目录
        foreach ($dirs as $dir) {
            $fullDir = $uploadDir . $dir;
            if (is_dir($fullDir)) {
                $this->scanDirectory($fullDir, $dir, $files, $search, $month);
            }
        }

        return count($files);
    }

    /**
     * 获取可选月份列表（YYYY-MM，按时间倒序）
     * 供筛选下拉框使用；月份取自文件修改时间，与上传目录结构无关
     */
    private function getAvailableMonths($type) {
        // 复用列表扫描逻辑（不传月份/搜索，收集全部文件）
        $files = [];
        $uploadDir = UPLOADS_PATH . '/';

        $typeDirs = [
            'image' => 'images',
            'file' => 'files',
            'avatar' => 'avatars',
            'logo' => 'logo',
            'cover' => 'cover',
            'attachment' => 'attachments',
            'article' => 'articles'
        ];

        $dirs = [];
        if ($type == 'all') {
            $dirs = array_values($typeDirs);
        } elseif (isset($typeDirs[$type])) {
            $dirs = [$typeDirs[$type]];
        }

        foreach ($dirs as $dir) {
            $fullDir = $uploadDir . $dir;
            if (is_dir($fullDir)) {
                $this->scanDirectory($fullDir, $dir, $files, '', '');
            }
        }

        // 提取文件修改时间的年月并去重，倒序排列（最近的月份在最前）
        $months = [];
        foreach ($files as $file) {
            $months[date('Y-m', $file['modified'])] = true;
        }
        $months = array_keys($months);
        rsort($months);
        return $months;
    }
    
    /**
     * 删除媒体文件
     */
    public function delete() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $path = isset($_POST['path']) ? $_POST['path'] : '';
            
            // 验证路径
            if (empty($path) || strpos($path, '/uploads/') !== 0) {
                $_SESSION['error_message'] = '无效的文件路径';
                header('Location: admin.php?action=media');
                exit;
            }
            
            // 构建完整路径
            $fullPath = ROOT_PATH . $path;
            
            // 检查文件是否存在
            if (file_exists($fullPath)) {
                // 删除文件，并级联清理其自动生成的衍生文件（尺寸变体、webp 转换副本）
                $deleteResult = MediaService::deleteWithVariants($fullPath);
                if ($deleteResult['success']) {
                    $variantTip = $deleteResult['variants_deleted'] > 0
                        ? '，同时清理了 ' . $deleteResult['variants_deleted'] . ' 个衍生文件'
                        : '';
                    $_SESSION['success_message'] = '文件删除成功' . $variantTip;
                } else {
                    $_SESSION['error_message'] = '文件删除失败';
                }
            } else {
                $_SESSION['error_message'] = '文件不存在';
            }
            
            header('Location: admin.php?action=media');
            exit;
        }
    }
    
    /**
     * 批量删除媒体文件
     */
    public function batch_delete() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $paths = isset($_POST['paths']) ? $_POST['paths'] : [];
            
            if (empty($paths)) {
                $_SESSION['error_message'] = '请选择要删除的文件';
                header('Location: admin.php?action=media');
                exit;
            }
            
            $successCount = 0;
            $errorCount = 0;
            
            foreach ($paths as $path) {
                // 验证路径
                if (empty($path) || strpos($path, '/uploads/') !== 0) {
                    $errorCount++;
                    continue;
                }
                
                // 构建完整路径
                $fullPath = ROOT_PATH . $path;
                
                // 检查文件是否存在
                if (file_exists($fullPath)) {
                    // 删除文件，并级联清理其自动生成的衍生文件（尺寸变体、webp 转换副本）
                    $deleteResult = MediaService::deleteWithVariants($fullPath);
                    if ($deleteResult['success']) {
                        $successCount++;
                    } else {
                        $errorCount++;
                    }
                } else {
                    $errorCount++;
                }
            }
            
            if ($successCount > 0) {
                $_SESSION['success_message'] = '成功删除 ' . $successCount . ' 个文件';
            }
            if ($errorCount > 0) {
                $_SESSION['error_message'] = '有 ' . $errorCount . ' 个文件删除失败';
            }
            
            header('Location: admin.php?action=media');
            exit;
        }
    }
    

    
    /**
     * 重命名文件
     */
    public function rename() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $path = isset($_POST['path']) ? $_POST['path'] : '';
            $newName = isset($_POST['new_name']) ? trim($_POST['new_name']) : '';
            
            // 验证路径
            if (empty($path) || strpos($path, '/uploads/') !== 0) {
                $_SESSION['error_message'] = '无效的文件路径';
                header('Location: admin.php?action=media');
                exit;
            }
            
            // 验证新文件名
            if (empty($newName)) {
                $_SESSION['error_message'] = '新文件名不能为空';
                header('Location: admin.php?action=media');
                exit;
            }
            
            // 构建完整路径
            $fullPath = ROOT_PATH . $path;
            $directory = dirname($fullPath);
            $newPath = $directory . '/' . $newName;
            
            // 检查文件是否存在
            if (file_exists($fullPath)) {
                // 检查新文件名是否已存在
                if (file_exists($newPath)) {
                    $_SESSION['error_message'] = '文件名已存在';
                } else {
                    // 重命名文件
                    if (rename($fullPath, $newPath)) {
                        $_SESSION['success_message'] = '文件重命名成功';
                    } else {
                        $_SESSION['error_message'] = '文件重命名失败';
                    }
                }
            } else {
                $_SESSION['error_message'] = '文件不存在';
            }
            
            header('Location: admin.php?action=media');
            exit;
        }
    }
    
    /**
     * 获取文件统计信息
     */
    private function getFileStatistics() {
        $uploadDir = UPLOADS_PATH . '/';
        $stats = [
            'total_files' => 0,
            'total_size' => 0,
            'total_size_formatted' => '',
            'type_counts' => [
                'image' => 0,
                'document' => 0,
                'archive' => 0,
                'other' => 0
            ]
        ];
        
        // 扫描所有目录（与 getMediaFiles 的类型映射保持一致，包含文章图片目录 articles）
        $typeDirs = [
            'images',
            'files',
            'avatars',
            'logo',
            'cover',
            'attachments',
            'articles'
        ];
        
        foreach ($typeDirs as $dir) {
            $fullDir = $uploadDir . $dir;
            if (is_dir($fullDir)) {
                $this->scanDirectoryForStats($fullDir, $stats);
            }
        }
        
        // 格式化总大小
        $stats['total_size_formatted'] = $this->format_bytes($stats['total_size']);
        
        return $stats;
    }
    
    /**
     * 扫描目录获取统计信息
     */
    private function scanDirectoryForStats($dir, &$stats) {
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }
            
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                // 递归扫描子目录
                $this->scanDirectoryForStats($path, $stats);
            } else {
                // 统计文件
                $stats['total_files']++;
                $fileSize = filesize($path);
                $stats['total_size'] += $fileSize;
                
                // 统计文件类型
                $fileType = $this->getFileType($item);
                $stats['type_counts'][$fileType]++;
            }
        }
    }

}
