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


class ThemeController {
    /**
     * 校验主题目录名合法性（防目录穿越攻击）
     *
     * 主题目录名只允许：字母、数字、下划线、连字符。
     * 拦截 ../、..\\、空字符等路径穿越载荷，防止 set_default/uninstall/settings
     * 等方法拼接路径时逃逸出 /themes 目录（uninstall 可递归删目录，风险最高）。
     * @param string $theme 主题目录名（来自 GET/POST 用户输入）
     * @return bool 是否合法
     */
    private function isValidThemeSlug($theme) {
        return is_string($theme) && $theme !== '' && preg_match('/^[a-zA-Z0-9_-]+$/', $theme) === 1;
    }

    /**
     * 校验 CSRF 令牌（用于卸载、启用等危险操作）
     *
     * 复用全局 Security 类的令牌校验（受后台 security_csrf_protection 开关控制），
     * 校验失败直接弹窗提示并终止。
     * @return void
     */
    private function requireCsrfToken() {
        $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
        if (!Security::validateCsrfToken($token)) {
            $this->showMessage('安全校验失败，请刷新页面后重试', 'admin.php?action=theme');
        }
    }

    /**
     * 扫描新主题
     */
    private function scanThemes() {
        $themesPath = ROOT_PATH . '/themes';
        $foundThemes = [];
        
        if (is_dir($themesPath)) {
            $dirs = scandir($themesPath);
            foreach ($dirs as $dir) {
                if ($dir != '.' && $dir != '..' && is_dir($themesPath . '/' . $dir)) {
                    // 获取主题详细信息
                    $themeInfo = $this->getThemeInfo($dir);
                    $foundThemes[] = $themeInfo;
                }
            }
        }
        
        $db = Database::getInstance();
        
        // 清理已删除的主题残留记录（目录已不存在但数据库中仍有记录）
        $dbThemes = $db->fetchAll("SELECT * FROM {$db->table('theme')}");
        foreach ($dbThemes as $dbTheme) {
            $themeDir = $themesPath . '/' . $dbTheme['slug'];
            if (!is_dir($themeDir)) {
                // 主题目录已被删除，清理数据库中的残留记录
                $db->delete('theme', array('id' => $dbTheme['id']));
            }
        }
        
        // 检查主题是否已在数据库中注册
        foreach ($foundThemes as $theme) {
            $slug = isset($theme['id']) ? $theme['id'] : $theme['slug'];
            
            // 使用唯一性约束防止重复：先检查是否存在，若不存在则尝试插入
            // 即使并发请求导致竞态条件，UNIQUE 约束也会阻止重复插入
            $existing = $db->fetch("SELECT * FROM {$db->table('theme')} WHERE slug = ?", [$slug]);
            if (!$existing) {
                try {
                    $db->insert('theme', array(
                        'name' => $theme['name'],
                        'slug' => $slug,
                        'description' => $theme['description'],
                        'version' => $theme['version'],
                        'status' => 0,
                        'settings' => '{}',
                        'created_at' => time(),
                        'installed_at' => time()
                    ));
                } catch (\Exception $e) {
                    // 插入失败（可能是 UNIQUE 约束冲突），静默忽略
                    // 下次扫描时会自动修正
                }
            }
        }
        
        // 检测并清理重复的 slug 记录（保留最早的一条）
        $allThemes = $db->fetchAll("SELECT * FROM {$db->table('theme')} ORDER BY id ASC");
        $seenSlugs = array();
        foreach ($allThemes as $row) {
            if (isset($seenSlugs[$row['slug']])) {
                // 已存在相同 slug 的记录，删除这条重复的
                $db->delete('theme', array('id' => $row['id']));
            } else {
                $seenSlugs[$row['slug']] = $row['id'];
            }
        }
    }
    
    public function index() {
        // 扫描新主题
        $this->scanThemes();
        
        // 获取已安装的主题列表
        $db = Database::getInstance();
        $themes = $db->fetchAll("SELECT * FROM {$db->table('theme')} ORDER BY name ASC");
        
        // 为每个主题添加详细信息
        $themesPath = ROOT_PATH . '/themes';
        foreach ($themes as &$theme) {
            $themeInfo = $this->getThemeInfo($theme['slug']);
            $theme['screenshot'] = $themeInfo['screenshot'];
            // theme.html 会显示作者信息，但 theme 表没有该字段，需从 theme.json 补充
            $theme['author'] = isset($themeInfo['author']) ? $themeInfo['author'] : '未知';
        }
        // 重要：必须销毁引用，否则残留的 $theme 引用会污染后续 theme.html 中的
        // foreach ($themes as $theme) 遍历，导致所有卡片都显示成最后一个主题的数据
        unset($theme);
        
        // 获取当前默认主题
        $defaultTheme = $db->fetch("SELECT value FROM {$db->table('config')} WHERE name = 'default_theme'")['value'];
        
        // 显示主题列表
        include ADMIN_PATH . '/templates/theme.html';
    }
    
    /**
     * 设置默认主题
     */
    public function set_default() {
        $theme = isset($_POST['theme']) ? $_POST['theme'] : '';

        // 安全校验：主题目录名合法性 + CSRF 令牌（改主题是全局性操作，必须防跨站请求伪造）
        if (!$this->isValidThemeSlug($theme)) {
            $this->showMessage('非法的主题名称', 'admin.php?action=theme');
            return;
        }
        $this->requireCsrfToken();

        // 验证主题是否存在
        $themesPath = ROOT_PATH . '/themes';
        if (!is_dir($themesPath . '/' . $theme)) {
            $this->showMessage('主题不存在', 'admin.php?action=theme');
            return;
        }
        
        // 记录设置默认主题日志
        Log::init();
        Log::info('设置默认主题', 'theme', ['theme' => $theme]);
        
        // 更新默认主题设置
        $db = Database::getInstance();
        
        // 开始事务
        $db->beginTransaction();
        
        try {
            // 更新所有主题状态为禁用
            $db->query("UPDATE {$db->table('theme')} SET status = 0");
            
            // 更新选中的主题状态为启用
            $db->update('theme', 
                array('status' => 1), 
                array('slug' => $theme)
            );
            
            // 更新默认主题配置
            $db->update('config', 
                array('value' => $theme), 
                array('name' => 'default_theme')
            );
            
            // 提交事务
            $db->commit();
            
            $this->showMessage('主题设置成功', 'admin.php?action=theme');
        } catch (Exception $e) {
            // 回滚事务
            $db->rollback();
            $this->showMessage('主题设置失败', 'admin.php?action=theme');
        }
    }
    
    // 出于安全考虑，已移除上传主题功能：避免通过 ZIP 上传任意 PHP 文件造成代码执行风险
    // 主题部署方式：直接将主题目录放置到 /themes 目录下，系统会自动扫描识别

    /**
     * 卸载主题
     */
    public function uninstall() {
        $theme = isset($_POST['theme']) ? $_POST['theme'] : '';

        // 安全校验：主题目录名合法性 + CSRF 令牌（卸载会递归删除目录，是最高危操作）
        if (!$this->isValidThemeSlug($theme)) {
            $this->showMessage('非法的主题名称', 'admin.php?action=theme');
            return;
        }
        $this->requireCsrfToken();

        // 记录主题卸载日志
        Log::init();
        Log::info('卸载主题', 'theme', ['theme' => $theme]);

        // 验证主题是否存在
        $themesPath = ROOT_PATH . '/themes';
        $themeDir = $themesPath . '/' . $theme;
        if (!is_dir($themeDir)) {
            $this->showMessage('主题不存在', 'admin.php?action=theme');
            return;
        }
        
        // 检查是否为当前默认主题
        $db = Database::getInstance();
        $defaultTheme = $db->fetch("SELECT value FROM {$db->table('config')} WHERE name = 'default_theme'")['value'];
        if ($theme == $defaultTheme) {
            $this->showMessage('无法卸载当前默认主题', 'admin.php?action=theme');
            return;
        }
        
        // 开始事务
        $db->beginTransaction();
        
        try {
            // 注意顺序：先删目录、后删记录（同事务内）。
            // 若先删记录后删目录，目录删除失败时记录已不存在，下次扫描会把目录重新注册回来（主题复活）；
            // 反过来若删记录失败，事务回滚但目录已删，scanThemes 会自动清理残留记录，可自愈。
            if (!$this->deleteDirectory($themeDir)) {
                throw new Exception('删除主题目录失败');
            }
            
            // 从数据库中删除主题记录
            $db->delete('theme', array('slug' => $theme));
            
            // 提交事务
            $db->commit();
            
            $this->showMessage('主题卸载成功', 'admin.php?action=theme');
        } catch (Exception $e) {
            // 回滚事务
            $db->rollback();
            $this->showMessage('主题卸载失败', 'admin.php?action=theme');
        }
    }
    
    /**
     * 获取主题详细信息
     * @param string $themeName 主题名称
     * @return array 主题信息
     */
    private function getThemeInfo($themeName) {
        $themePath = ROOT_PATH . '/themes/' . $themeName;
        $themeInfo = array(
            'name' => $themeName,
            'screenshot' => '',
            'version' => '1.0.0',
            'author' => '未知',
            'description' => '无描述'
        );
        
        // 检查是否存在 theme.json 配置文件
        $themeJson = $themePath . '/theme.json';
        if (file_exists($themeJson)) {
            $jsonContent = json_decode(file_get_contents($themeJson), true);
            if ($jsonContent) {
                $themeInfo['id'] = isset($jsonContent['id']) ? $jsonContent['id'] : $themeName;
                $themeInfo['name'] = isset($jsonContent['name']) ? $jsonContent['name'] : $themeName;
                $themeInfo['version'] = isset($jsonContent['version']) ? $jsonContent['version'] : '1.0.0';
                $themeInfo['author'] = isset($jsonContent['author']) ? $jsonContent['author'] : '未知';
                $themeInfo['description'] = isset($jsonContent['description']) ? $jsonContent['description'] : '无描述';
            }
        } else {
            // 如果没有 theme.json 文件，使用目录名作为 id
            $themeInfo['id'] = $themeName;
        }
        
        // 检查是否存在截图
        $screenshotPath = $themePath . '/screenshot.png';
        if (file_exists($screenshotPath)) {
            $themeInfo['screenshot'] = 'screenshot.png';
        } else {
            $screenshotPath = $themePath . '/screenshot.jpg';
            if (file_exists($screenshotPath)) {
                $themeInfo['screenshot'] = 'screenshot.jpg';
            }
        }
        
        $themeInfo['slug'] = isset($themeInfo['id']) ? $themeInfo['id'] : $themeName;
        return $themeInfo;
    }
    
    /**
     * 删除目录
     * @param string $dir 目录路径
     * @return bool 是否删除成功
     */
    private function deleteDirectory($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        
        return rmdir($dir);
    }
    
    /**
     * 显示消息并跳转
     * @param string $message 消息内容
     * @param string $redirect 跳转地址
     */
    private function showMessage($message, $redirect) {
        echo "<script>alert('{$message}'); window.location.href = '{$redirect}';</script>";
        exit;
    }
    
    /**
     * 显示主题设置页面
     */
    public function settings() {
        $theme = isset($_GET['theme']) ? $_GET['theme'] : '';

        // 安全校验：主题目录名合法性（settings 页虽只读，也要防止路径穿越报错）
        if (!$this->isValidThemeSlug($theme)) {
            $this->showMessage('非法的主题名称', 'admin.php?action=theme');
            return;
        }

        // 验证主题是否存在
        $themesPath = ROOT_PATH . '/themes';
        if (!is_dir($themesPath . '/' . $theme)) {
            $this->showMessage('主题不存在', 'admin.php?action=theme');
            return;
        }
        
        // 获取主题设置配置
        $settingsConfig = $this->getThemeSettings($theme);
        if (empty($settingsConfig)) {
            $this->showMessage('该主题没有设置选项', 'admin.php?action=theme');
            return;
        }
        
        // 获取已保存的设置值
        $settingsValues = $this->getThemeSettingsValues($theme);
        
        // 获取主题信息
        $themeInfo = $this->getThemeInfo($theme);
        
        // 显示设置页面
        include ADMIN_PATH . '/templates/theme_settings.html';
    }
    
    /**
     * 保存主题设置
     */
    public function save_settings() {
        $theme = isset($_POST['theme']) ? $_POST['theme'] : '';
        $settings = $_POST['settings'] ?? [];

        // 安全校验：主题目录名合法性 + CSRF 令牌
        if (!$this->isValidThemeSlug($theme)) {
            $this->showMessage('非法的主题名称', 'admin.php?action=theme');
            return;
        }
        $this->requireCsrfToken();

        // 验证主题是否存在
        $themesPath = ROOT_PATH . '/themes';
        if (!is_dir($themesPath . '/' . $theme)) {
            $this->showMessage('主题不存在', 'admin.php?action=theme');
            return;
        }

        // 获取主题设置配置，确保所有设置项都被处理
        $settingsConfig = $this->getThemeSettings($theme);
        
        // 处理设置值，确保复选框类型的设置即使未提交也能正确保存
        $processedSettings = [];
        foreach ($settingsConfig as $setting) {
            $key = $setting['key'];
            $type = $setting['type'] ?? 'text';
            
            // 对于复选框类型，如果未提交，则设置为0
            if ($type === 'checkbox') {
                $processedSettings[$key] = isset($settings[$key]) ? 1 : 0;
            } else {
                // 对于其他类型，使用提交的值或默认值
                $processedSettings[$key] = $settings[$key] ?? $setting['default'] ?? '';
            }
        }
        
        // 保存设置到数据库
        $db = Database::getInstance();
        $success = true;
        
        try {
            // 打印调试信息
            error_log('Theme: ' . $theme);
            error_log('Settings: ' . json_encode($processedSettings));
            
            // 将设置保存到主题表中
            $result = $db->update('theme', 
                array('settings' => json_encode($processedSettings)), 
                array('slug' => $theme)
            );
            
            error_log('Update result: ' . $result);
            
            // 清除配置缓存，确保下次加载时使用新的配置
            if (class_exists('Config')) {
                // 重置Config类的静态变量
                $reflection = new ReflectionClass('Config');
                $configProperty = $reflection->getProperty('config');
                $configProperty->setAccessible(true);
                $configProperty->setValue(null, []);
            }
            
            if ($result !== false) {
                $this->showMessage('主题设置保存成功', 'admin.php?action=theme&sub=settings&theme=' . $theme);
            } else {
                $this->showMessage('主题设置保存失败', 'admin.php?action=theme&sub=settings&theme=' . $theme);
            }
        } catch (Exception $e) {
            error_log('Exception: ' . $e->getMessage());
            $this->showMessage('主题设置保存失败', 'admin.php?action=theme&sub=settings&theme=' . $theme);
        }
    }
    
    /**
     * 获取主题设置配置
     * @param string $theme 主题名称
     * @return array 设置配置
     */
    private function getThemeSettings($theme) {
        $themePath = ROOT_PATH . '/themes/' . $theme . '/theme.json';
        if (file_exists($themePath)) {
            $jsonContent = json_decode(file_get_contents($themePath), true);
            if ($jsonContent && isset($jsonContent['settings'])) {
                return $jsonContent['settings'];
            }
        }
        return [];
    }

    // 出于安全考虑已彻底移除主题预览功能（preview 方法 + theme_preview.html 模板）：
    // 1. 原实现未做目录穿越校验，theme 参数直接拼接路径
    // 2. 前台通过 ?theme_preview= 参数即可未登录切换全站主题，存在滥用风险
    // 如需体验主题效果，可在测试环境修改 default_theme 配置后直接访问前台

    /**
     * 导出主题设置
     */
    public function export_settings() {
        $theme = $_GET['theme'];
        
        // 验证主题是否存在
        $themesPath = ROOT_PATH . '/themes';
        if (!is_dir($themesPath . '/' . $theme)) {
            $this->showMessage('主题不存在', 'admin.php?action=theme');
            return;
        }
        
        // 获取主题设置值
        $settings = $this->getThemeSettingsValues($theme);
        
        // 导出为JSON文件
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename=' . $theme . '-settings.json');
        echo json_encode($settings, JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * 导入主题设置
     */
    public function import_settings() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $theme = $_POST['theme'];
            
            // 验证主题是否存在
            $themesPath = ROOT_PATH . '/themes';
            if (!is_dir($themesPath . '/' . $theme)) {
                $this->showMessage('主题不存在', 'admin.php?action=theme');
                return;
            }
            
            if (isset($_FILES['settings_file']) && $_FILES['settings_file']['error'] == UPLOAD_ERR_OK) {
                $file = $_FILES['settings_file'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                
                if ($ext != 'json') {
                    $this->showMessage('只支持JSON格式的设置文件', 'admin.php?action=theme&sub=settings&theme=' . $theme);
                    return;
                }
                
                $settings = json_decode(file_get_contents($file['tmp_name']), true);
                if (!$settings) {
                    $this->showMessage('设置文件格式错误', 'admin.php?action=theme&sub=settings&theme=' . $theme);
                    return;
                }
                
                // 保存设置到数据库
                $db = Database::getInstance();
                $result = $db->update('theme', 
                    array('settings' => json_encode($settings)), 
                    array('slug' => $theme)
                );
                
                if ($result !== false) {
                    $this->showMessage('主题设置导入成功', 'admin.php?action=theme&sub=settings&theme=' . $theme);
                } else {
                    $this->showMessage('主题设置导入失败', 'admin.php?action=theme&sub=settings&theme=' . $theme);
                }
            } else {
                $this->showMessage('文件上传失败', 'admin.php?action=theme&sub=settings&theme=' . $theme);
            }
        } else {
            $theme = $_GET['theme'];
            include ADMIN_PATH . '/templates/theme_import_settings.html';
        }
    }
    
    /**
     * 获取主题设置的当前值
     * @param string $theme 主题名称
     * @return array 设置值
     */
    private function getThemeSettingsValues($theme) {
        $db = Database::getInstance();
        $settings = [];
        
        // 从 theme.json 读取默认值（作为基准）
        // theme.json 位于主题根目录（/themes/{theme}/theme.json），而非 templates 子目录
        $defaultSettings = [];
        $themeJsonPath = ROOT_PATH . '/themes/' . $theme . '/theme.json';
        if (file_exists($themeJsonPath)) {
            $jsonContent = json_decode(file_get_contents($themeJsonPath), true);
            if ($jsonContent && isset($jsonContent['settings'])) {
                foreach ($jsonContent['settings'] as $setting) {
                    $defaultSettings[$setting['key']] = $setting['default'] ?? '';
                }
            }
        }
        
        // 从主题表中获取已保存的设置
        $themeInfo = $db->fetch("SELECT settings FROM {$db->table('theme')} WHERE slug = ?", [$theme]);
        if ($themeInfo && !empty($themeInfo['settings'])) {
            $decodedSettings = json_decode($themeInfo['settings'], true);
            // 只有当解码后的数据不为空数组时才使用（避免 {} 导致的值丢失）
            if (is_array($decodedSettings) && !empty($decodedSettings)) {
                $settings = $decodedSettings;
            }
        }
        
        // 兼容旧的设置存储方式
        if (empty($settings)) {
            $results = $db->fetchAll("SELECT name, value FROM {$db->table('config')} WHERE name LIKE ?", ["theme_{$theme}_%"]);
            foreach ($results as $result) {
                $key = str_replace("theme_{$theme}_", '', $result['name']);
                $settings[$key] = $result['value'];
            }
        }
        
        // 最后合并：数据库中的值覆盖默认值，默认值保证所有字段都有初始值
        return array_merge($defaultSettings, $settings);
    }
    
    /**
     * 处理子操作
     */
    public function handleSubAction() {
        if (isset($_GET['sub'])) {
            $subAction = $_GET['sub'];
            if (method_exists($this, $subAction)) {
                $this->$subAction();
            } else {
                $this->showMessage('操作不存在', 'admin.php?action=theme');
            }
        } else {
            $this->index();
        }
    }
}
