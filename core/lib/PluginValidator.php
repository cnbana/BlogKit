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
 * BlogKit 插件打包校验工具
 * 
 * 在插件发布前对插件结构和配置进行校验，确保符合 BlogKit 规范。
 * 
 * 使用方法：
 *   $validator = new PluginValidator('/path/to/plugins/myplugin');
 *   $result = $validator->validate();
 *   if ($result['valid']) { ... }
 * 
 * @package BlogKit
 * @since   1.0.0
 */

class PluginValidator
{
    /** @var string 插件目录路径 */
    private $pluginDir;

    /** @var array 校验结果 */
    private $errors = [];
    private $warnings = [];

    /**
     * 构造函数
     * @param string $pluginDir 插件目录绝对路径
     */
    public function __construct($pluginDir)
    {
        $this->pluginDir = rtrim($pluginDir, '/\\');
    }

    /**
     * 执行完整校验
     * @return array ['valid' => bool, 'errors' => [], 'warnings' => [], 'info' => []]
     */
    public function validate()
    {
        $this->errors = [];
        $this->warnings = [];

        $this->checkDirectoryExists();
        $this->checkPluginJson();
        $this->checkPluginPhp();
        $this->checkMainClass();
        $this->checkSlugConsistency();
        $this->checkSecurityIssues();
        $this->checkReadme();

        $info = $this->collectPluginInfo();

        return [
            'valid'      => empty($this->errors),
            'errors'     => $this->errors,
            'warnings'   => $this->warnings,
            'info'       => $info,
            'error_count'   => count($this->errors),
            'warning_count' => count($this->warnings),
        ];
    }

    /**
     * 检查目录是否存在
     */
    private function checkDirectoryExists()
    {
        if (!is_dir($this->pluginDir)) {
            $this->errors[] = '插件目录不存在: ' . $this->pluginDir;
        }
    }

    /**
     * 检查 plugin.json
     */
    private function checkPluginJson()
    {
        $jsonFile = $this->pluginDir . '/plugin.json';

        if (!file_exists($jsonFile)) {
            $this->errors[] = '缺少 plugin.json 文件（必需）';
            return;
        }

        $content = file_get_contents($jsonFile);
        $json = json_decode($content, true);

        if ($json === null) {
            $this->errors[] = 'plugin.json 不是有效的 JSON 格式: ' . json_last_error_msg();
            return;
        }

        // 必需字段检查
        $required = ['name', 'slug', 'version', 'description', 'author'];
        foreach ($required as $field) {
            if (empty($json[$field])) {
                $this->errors[] = "plugin.json 缺少必需字段: {$field}";
            }
        }

        // 版本号格式检查
        if (!empty($json['version'])) {
            if (!preg_match('/^\d+\.\d+\.\d+(-[a-zA-Z0-9.]+)?(\+[a-zA-Z0-9.]+)?$/', $json['version'])) {
                $this->errors[] = "版本号格式不正确: {$json['version']}（应为 SemVer，如 1.0.0）";
            }
        }

        // name 长度检查
        if (!empty($json['name']) && (mb_strlen($json['name']) < 2 || mb_strlen($json['name']) > 50)) {
            $this->warnings[] = "插件名称长度应为 2-50 个字符，当前: " . mb_strlen($json['name']);
        }

        // description 长度检查
        if (!empty($json['description']) && mb_strlen($json['description']) > 500) {
            $this->warnings[] = "插件描述过长（应 ≤500 字符），当前: " . mb_strlen($json['description']);
        }

        // URL 格式检查
        $urlFields = ['author_url', 'plugin_url'];
        foreach ($urlFields as $field) {
            if (!empty($json[$field]) && !filter_var($json[$field], FILTER_VALIDATE_URL)) {
                $this->warnings[] = "字段 {$field} 不是有效的 URL: {$json[$field]}";
            }
        }

        // default_settings 检查
        if (!empty($json['default_settings'])) {
            foreach ($json['default_settings'] as $key => $setting) {
                if (empty($setting['type'])) {
                    $this->errors[] = "设置项 {$key} 缺少 type 字段";
                }
                if (!isset($setting['value'])) {
                    $this->warnings[] = "设置项 {$key} 缺少 value 默认值";
                }
                if (empty($setting['label'])) {
                    $this->warnings[] = "设置项 {$key} 缺少 label 字段";
                }
            }
        }

        // dependencies 检查
        if (!empty($json['dependencies'])) {
            foreach ($json['dependencies'] as $depSlug => $depVersion) {
                if (!preg_match('/^[a-z0-9_-]+$/', $depSlug)) {
                    $this->warnings[] = "依赖项 slug 格式不正确: {$depSlug}";
                }
            }
        }

        // changelog 检查
        if (!empty($json['changelog'])) {
            foreach ($json['changelog'] as $index => $entry) {
                if (empty($entry['version'])) {
                    $this->warnings[] = "changelog 第 " . ($index + 1) . " 条缺少 version 字段";
                }
                if (empty($entry['date'])) {
                    $this->warnings[] = "changelog 第 " . ($index + 1) . " 条缺少 date 字段";
                }
            }
        }
    }

    /**
     * 检查 plugin.php 入口文件
     */
    private function checkPluginPhp()
    {
        $pluginFile = $this->pluginDir . '/plugin.php';

        if (!file_exists($pluginFile)) {
            $this->errors[] = '缺少 plugin.php 入口文件（必需）';
            return;
        }

        $content = file_get_contents($pluginFile);

        // 检查是否包含 require_once
        if (strpos($content, 'require') === false && strpos($content, 'include') === false) {
            $this->warnings[] = 'plugin.php 中未发现 require/include 语句，可能未引入主类文件';
        }
    }

    /**
     * 检查主类文件
     */
    private function checkMainClass()
    {
        $slug = $this->getSlug();
        if (!$slug) return;

        // 类名: PascalCase(slug) + Plugin
        $parts = explode('_', $slug);
        $pascalCase = implode('', array_map('ucfirst', $parts));
        $className = $pascalCase . 'Plugin';
        $classFile = $this->pluginDir . '/' . $className . '.php';

        if (!file_exists($classFile)) {
            $this->errors[] = "缺少插件主类文件: {$className}.php";
            return;
        }

        $content = file_get_contents($classFile);

        // 检查类定义
        if (strpos($content, "class {$className}") === false) {
            $this->errors[] = "主类文件中未找到类定义: class {$className}";
        }

        // 检查是否继承 PluginBase
        if (strpos($content, 'extends PluginBase') === false) {
            $this->errors[] = "插件主类 {$className} 必须继承 PluginBase";
        }

        // 安全检查
        if (strpos($content, 'eval(') !== false) {
            $this->errors[] = "安全风险: 主类文件中包含 eval() 调用";
        }
        if (strpos($content, 'exec(') !== false || strpos($content, 'shell_exec(') !== false || strpos($content, 'system(') !== false) {
            $this->warnings[] = "安全风险: 主类文件中包含系统命令执行函数";
        }
    }

    /**
     * 检查 slug 一致性
     */
    private function checkSlugConsistency()
    {
        $jsonFile = $this->pluginDir . '/plugin.json';
        if (!file_exists($jsonFile)) return;

        $json = json_decode(file_get_contents($jsonFile), true);
        if (!$json || empty($json['slug'])) return;

        $dirSlug = basename($this->pluginDir);

        if ($json['slug'] !== $dirSlug) {
            $this->errors[] = "Slug 不一致: plugin.json 中为 '{$json['slug']}'，但目录名为 '{$dirSlug}'";
        }

        // slug 格式检查
        if (!preg_match('/^[a-z0-9_-]{2,50}$/', $json['slug'])) {
            $this->errors[] = "Slug 格式无效: '{$json['slug']}'（仅允许小写字母、数字、下划线、连字符，2-50 字符）";
        }
    }

    /**
     * 安全检查
     */
    private function checkSecurityIssues()
    {
        $files = $this->scanPhpFiles($this->pluginDir);

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $filename = basename($file);

            // 检查 $_GET / $_POST / $_REQUEST 直接使用
            if (strpos($content, '$_GET') !== false || strpos($content, '$_POST') !== false) {
                $this->warnings[] = "文件 {$filename} 直接使用 \$_GET/\$_POST，建议使用过滤或验证";
            }

            // 检查 SQL 拼接风险
            if (preg_match('/["\']\s*\.\s*\$/', $content)) {
                $this->warnings[] = "文件 {$filename} 可能存在 SQL 字符串拼接，建议使用参数化查询";
            }
        }
    }

    /**
     * 检查 README
     */
    private function checkReadme()
    {
        $readmePath = $this->pluginDir . '/README.md';

        if (!file_exists($readmePath)) {
            $this->warnings[] = '建议添加 README.md 文件，包含插件说明和安装指南';
        }
    }

    /**
     * 收集插件信息
     * @return array
     */
    private function collectPluginInfo()
    {
        $info = ['files' => 0, 'size' => 0];

        $jsonFile = $this->pluginDir . '/plugin.json';
        if (file_exists($jsonFile)) {
            $json = json_decode(file_get_contents($jsonFile), true);
            if ($json) {
                $info['name'] = $json['name'] ?? '';
                $info['slug'] = $json['slug'] ?? '';
                $info['version'] = $json['version'] ?? '';
                $info['author'] = $json['author'] ?? '';
            }
        }

        // 统计文件数和大小
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->pluginDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $info['files']++;
                $info['size'] += $file->getSize();
            }
        }

        $info['size_formatted'] = $this->formatSize($info['size']);
        return $info;
    }

    /**
     * 扫描目录下所有 PHP 文件
     */
    private function scanPhpFiles($dir)
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        return $files;
    }

    /**
     * 获取 slug
     */
    private function getSlug()
    {
        $jsonFile = $this->pluginDir . '/plugin.json';
        if (file_exists($jsonFile)) {
            $json = json_decode(file_get_contents($jsonFile), true);
            return $json['slug'] ?? null;
        }
        return null;
    }

    /**
     * 格式化文件大小
     */
    private function formatSize($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * 命令行友好的摘要输出
     * @return string
     */
    public function getSummary()
    {
        $result = $this->validate();
        $lines = [];

        $lines[] = "=== BlogKit 插件校验 ===";
        $lines[] = "插件目录: {$this->pluginDir}";
        $lines[] = "";

        if ($result['valid']) {
            $lines[] = "✅ 校验通过！";
        } else {
            $lines[] = "❌ 校验失败！发现 {$result['error_count']} 个错误，{$result['warning_count']} 个警告。";
        }

        $lines[] = "";

        if (!empty($result['info']['name'])) {
            $lines[] = "名称: {$result['info']['name']}";
            $lines[] = "版本: {$result['info']['version']}";
            $lines[] = "文件: {$result['info']['files']} 个 ({$result['info']['size_formatted']})";
            $lines[] = "";
        }

        foreach ($result['errors'] as $error) {
            $lines[] = "  ✗ ERROR: {$error}";
        }

        foreach ($result['warnings'] as $warning) {
            $lines[] = "  ⚠ WARNING: {$warning}";
        }

        if (empty($result['errors']) && empty($result['warnings'])) {
            $lines[] = "  没有发现问题。插件符合 BlogKit 规范。";
        }

        return implode("\n", $lines);
    }
}
