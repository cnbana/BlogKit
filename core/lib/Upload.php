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
 * 统一文件上传处理类
 * 支持头像、文章图片、评论图片等多种文件类型的上传
 */
class Upload {
    
    // 上传配置
    private $config = [];
    
    // 上传错误信息
    private $error = '';
    
    // 上传成功后的文件信息
    private $fileInfo = [];
    
    /**
     * 构造函数
     * @param array $config 上传配置
     */
    public function __construct($config = []) {
        // 默认配置
        $defaultConfig = [
            'maxSize' => 2 * 1024 * 1024, // 默认2MB
            'allowedTypes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], // 默认允许的图片类型，包含webp
            'uploadDir' => '', // 上传目录，会根据文件类型自动生成
            'subDir' => true, // 是否按年/月创建子目录
            'fileName' => '', // 自定义文件名，为空则自动生成
            'prefix' => '', // 文件名前缀
            'fileType' => 'image', // 文件类型：avatar, article, comment, attachment
            'thumbnail' => false, // 是否生成缩略图
            'thumbnailSizes' => [
                'small' => ['width' => 150, 'height' => 150],
                'medium' => ['width' => 400, 'height' => 400],
                'large' => ['width' => 800, 'height' => 800],
            ],
        ];
        
        $this->config = array_merge($defaultConfig, $config);
        
        // 根据文件类型设置默认上传目录
        if (empty($this->config['uploadDir'])) {
            $this->setUploadDirByType($this->config['fileType']);
        }
    }
    
    /**
     * 根据文件类型设置上传目录
     * @param string $fileType 文件类型
     */
    private function setUploadDirByType($fileType) {
        // 确保Config类已初始化
        if (!class_exists('Config')) {
            require_once CORE_PATH . '/lib/Config.php';
            Config::load('system');
        }
        
        $rootDir = Config::get('path.uploads');
        
        // 如果没有获取到上传目录，使用默认值
        if (empty($rootDir)) {
            $rootDir = UPLOADS_PATH;
        }
        
        switch ($fileType) {
            case 'avatar':
                $this->config['uploadDir'] = $rootDir . '/avatars/';
                break;
            case 'article':
                $this->config['uploadDir'] = $rootDir . '/articles/';
                break;
            case 'comment':
                $this->config['uploadDir'] = $rootDir . '/comments/';
                break;
            case 'attachment':
                $this->config['uploadDir'] = $rootDir . '/attachments/';
                break;
            default:
                $this->config['uploadDir'] = $rootDir . '/images/';
        }
    }
    
    /**
     * 获取安全配置
     * @param string $key 配置键
     * @param mixed $default 默认值
     * @return mixed 配置值
     */
    private function getSecurityConfig($key, $default = null) {
        // 确保Config类已加载
        if (!class_exists('Config')) {
            require_once CORE_PATH . '/lib/Config.php';
            Config::init();
        }
        
        return Config::get('security_' . $key, $default);
    }
    
    /**
     * 上传文件
     * @param array $file 上传的文件信息（$_FILES数组中的单个文件）
     * @return bool 是否上传成功
     */
    public function upload($file) {
        // 检查文件上传功能是否启用
        if (!$this->getSecurityConfig('file_upload_enabled', 1)) {
            $this->error = '文件上传功能已被禁用';
            return false;
        }
        
        // 检查文件是否有效
        if (!isset($file)) {
            $this->error = '文件上传失败：无效的文件信息';
            return false;
        }
        
        // 检查上传错误
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => '文件大小超过了php.ini中的upload_max_filesize设置',
                UPLOAD_ERR_FORM_SIZE => '文件大小超过了HTML表单中的MAX_FILE_SIZE设置',
                UPLOAD_ERR_PARTIAL => '文件只上传了一部分',
                UPLOAD_ERR_NO_FILE => '没有文件被上传',
                UPLOAD_ERR_NO_TMP_DIR => '找不到临时文件夹',
                UPLOAD_ERR_CANT_WRITE => '无法将文件写入磁盘',
                UPLOAD_ERR_EXTENSION => '文件上传被PHP扩展中断'
            ];
            $errorCode = $file['error'];
            $this->error = '文件上传失败：' . ($errorMessages[$errorCode] ?? '未知错误');
            return false;
        }
        
        // 检查文件大小
        $maxSize = $this->getSecurityConfig('file_upload_max_size', 5242880);
        if ($file['size'] > $maxSize) {
            $this->error = '文件上传失败：文件大小超过限制 (' . $file['size'] . ' bytes)，最大允许：' . $maxSize . ' bytes';
            return false;
        }
        
        // 检查文件扩展名
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $forbiddenExts = $this->getSecurityConfig('file_upload_forbidden_exts', 'php,php3,php4,php5,phtml,exe,dll,asp,aspx,jsp,js,html,htm,shtml,cgi,pl');
        $forbiddenExtsArray = array_map('trim', explode(',', $forbiddenExts));
        if (in_array($fileExt, $forbiddenExtsArray)) {
            $this->error = '文件上传失败：不允许的文件扩展名 (' . $fileExt . ')';
            return false;
        }
        
        // 优先检查文件内容类型（不依赖用户提供的Content-Type）
        $allowedImageMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $imageInfo = getimagesize($file['tmp_name']);
        
        if ($imageInfo) {
            // 是图片文件，验证图片MIME类型
            if (!in_array($imageInfo['mime'], $allowedImageMimes)) {
                $this->error = '文件上传失败：不允许的图片类型 (' . $imageInfo['mime'] . ')';
                return false;
            }
        } else {
            // 非图片文件，检查允许的类型
            $allowedTypes = $this->getSecurityConfig('file_upload_allowed_types', 'image/jpeg,image/png,image/gif,image/webp');
            $allowedTypesArray = array_map('trim', explode(',', $allowedTypes));
            
            // 检查用户提供的Content-Type（仅作参考）
            if (!in_array($file['type'], $allowedTypesArray)) {
                $this->error = '文件上传失败：不允许的文件类型 (' . $file['type'] . ')，允许的类型：' . $allowedTypes;
                return false;
            }
        }
        
        // 创建上传目录
        $uploadDir = $this->getUploadDir();
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                $this->error = '文件上传失败：无法创建上传目录 (' . $uploadDir . ')';
                return false;
            }
        }
        
        // 检查目录权限
        if (!is_writable($uploadDir)) {
            $this->error = '文件上传失败：上传目录不可写 (' . $uploadDir . ')';
            return false;
        }
        
        // 生成文件名
        $fileName = $this->generateFileName($file);
        $filePath = $uploadDir . $fileName;
        
        // 保存文件
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            $this->error = '文件上传失败：保存文件失败 (临时文件: ' . $file['tmp_name'] . ', 目标路径: ' . $filePath . ')';
            
            // 记录上传失败日志
            require_once CORE_PATH . '/lib/Log.php';
            Log::init();
            Log::error('文件上传失败', 'upload', [
                'error' => $this->error,
                'file_name' => $file['name'],
                'file_size' => $file['size'],
                'file_type' => $this->config['fileType']
            ]);
            
            return false;
        }
        
        // 检查文件是否成功保存
        if (!file_exists($filePath)) {
            $this->error = '文件上传失败：保存后的文件不存在';
            return false;
        }
        
        // 设置文件权限，确保只有读取权限，没有执行权限
        chmod($filePath, 0644);
        
        // 触发上传完成事件：图片压缩/WebP/缩略图等后处理交由插件监听执行
        //（imageopt 插件；无插件监听时为空操作，行为与关闭图片优化时一致）。
        // 事件在文件落盘后、生成文件信息前触发，保证云存储等下游
        // 消费者拿到的是已优化的文件集
        Hook::trigger(Hook::FILE_UPLOAD_FINISHED, [
            'file_path' => $filePath,
            'file_type' => $file['type'],
            'thumbnail' => $this->config['thumbnail'],
        ]);
        
        // 生成文件信息
        $this->fileInfo = $this->generateFileInfo($fileName);
        
        // 记录上传成功日志
        require_once CORE_PATH . '/lib/Log.php';
        Log::init();
        Log::info('文件上传成功', 'upload', [
            'file_name' => $fileName,
            'file_type' => $this->config['fileType'],
            'file_size' => $file['size'],
            'file_path' => $this->fileInfo['path']
        ]);

        // 触发上传完成钩子（云存储类插件依赖此钩子做镜像同步；零插件监听时无开销）
        Hook::trigger(Hook::FILE_UPLOAD_AFTER, $this->getFileInfo());

        return true;
    }
    
    /**
     * 获取上传目录
     * @return string 上传目录路径
     */
    private function getUploadDir() {
        $uploadDir = $this->config['uploadDir'];
        
        // 如果开启子目录，则按年/月创建
        if ($this->config['subDir']) {
            $uploadDir .= date('Y/m/');
        }
        
        return $uploadDir;
    }
    
    /**
     * 生成文件名
     * @param array $file 上传的文件信息
     * @return string 生成的文件名
     */
    private function generateFileName($file) {
        // 如果有自定义文件名，则使用自定义文件名
        if (!empty($this->config['fileName'])) {
            $fileExt = pathinfo($file['name'], PATHINFO_EXTENSION);
            return $this->config['prefix'] . $this->config['fileName'] . '.' . $fileExt;
        }
        
        // 自动生成文件名（使用 random_int() 替代 mt_rand() 以提高随机性安全性）
        $fileExt = pathinfo($file['name'], PATHINFO_EXTENSION);
        $timestamp = time();
        $random = random_int(1000, 9999);
        
        return $this->config['prefix'] . $timestamp . '_' . $random . '.' . $fileExt;
    }
    
    /**
     * 生成文件信息
     * 统一存储相对路径（以 / 开头，基于站点根目录），避免拼接域名导致的协议/路径问题
     * 例如：/uploads/avatars/2026/06/1_1781007561_8843.jpg
     * @param string $fileName 文件名
     * @return array 文件信息数组
     */
    private function generateFileInfo($fileName) {
        $uploadDir = $this->getUploadDir();
        
        // 生成完整的服务器路径
        $fullPath = $uploadDir . $fileName;
        
        // 生成相对于站点根目录的相对路径（始终以 / 开头）
        $relativePath = str_replace(ROOT_PATH, '', $fullPath);
        
        // 规范化 Windows 反斜杠为斜杠，并确保以 / 开头
        $relativePath = str_replace('\\', '/', $relativePath);
        if (strpos($relativePath, '/') !== 0) {
            $relativePath = '/' . $relativePath;
        }
        
        // 规范化多余的连续斜杠为单个斜杠
        $relativePath = preg_replace('#/+#', '/', $relativePath);
        
        // url 字段与 relative_path 保持一致（仅存相对路径），
        // 由调用方在需要完整 URL 时自行拼接域名，避免协议/主机配置不一致问题
        $fileUrl = $relativePath;
        
        return [
            'name' => $fileName,
            'url' => $fileUrl,
            'path' => $fullPath,
            'relative_path' => $relativePath,
            'size' => filesize($fullPath),
            'upload_time' => time(),
            'type' => $this->config['fileType']
        ];
    }
    
    /**
     * 获取上传成功后的文件信息
     * @return array 文件信息数组
     */
    public function getFileInfo() {
        return $this->fileInfo;
    }
    
    /**
     * 获取错误信息
     * @return string 错误信息
     */
    public function getError() {
        return $this->error;
    }
    
    /**
     * 获取文件URL
     * @return string 文件URL
     */
    public function getFileUrl() {
        return $this->fileInfo['url'] ?? '';
    }
    
    /**
     * 静态方法：上传头像
     * @param array $file 上传的文件信息
     * @param int $userId 用户ID
     * @return string|bool 头像URL或false
     */
    public static function uploadAvatar($file, $userId) {
        $upload = new self([
            'fileType' => 'avatar',
            'maxSize' => 2 * 1024 * 1024, // 2MB
            'allowedTypes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            'prefix' => $userId . '_',
            'fileName' => '', // 自动生成
            'thumbnail' => false // 头像不需要缩略图
        ]);
        
        if ($upload->upload($file)) {
            return $upload->getFileUrl();
        }
        
        return false;
    }
    
    /**
     * 静态方法：上传文章图片
     * @param array $file 上传的文件信息
     * @return string|bool 图片URL或false
     */
    public static function uploadArticleImage($file) {
        $upload = new self([
            'fileType' => 'article',
            'maxSize' => 5 * 1024 * 1024, // 5MB
            'allowedTypes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            'thumbnail' => true // 生成缩略图
        ]);
        
        if ($upload->upload($file)) {
            return $upload->getFileUrl();
        }
        
        return false;
    }
    
    /**
     * 静态方法：上传评论图片
     * @param array $file 上传的文件信息
     * @return string|bool 图片URL或false
     */
    public static function uploadCommentImage($file) {
        $upload = new self([
            'fileType' => 'comment',
            'maxSize' => 3 * 1024 * 1024, // 3MB
            'allowedTypes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            'thumbnail' => true // 生成缩略图
        ]);
        
        if ($upload->upload($file)) {
            return $upload->getFileUrl();
        }
        
        return false;
    }
    
    /**
     * 静态方法：上传附件
     * @param array $file 上传的文件信息
     * @return string|bool 附件URL或false
     */
    public static function uploadAttachment($file) {
        $upload = new self([
            'fileType' => 'attachment',
            'maxSize' => 10 * 1024 * 1024, // 10MB
            'allowedTypes' => [
                'image/jpeg', 'image/png', 'image/gif',
                'application/zip', 'application/rar',
                'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'text/plain', 'application/pdf'
            ],
            'thumbnail' => false // 附件不需要缩略图
        ]);
        
        if ($upload->upload($file)) {
            return $upload->getFileUrl();
        }
        
        return false;
    }
}
