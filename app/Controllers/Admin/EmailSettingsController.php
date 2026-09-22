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
 * 邮箱配置控制器
 * 负责处理网站邮箱配置相关的请求
 */
class EmailSettingsController
{
    /**
     * 邮箱配置首页
     */
    public function index()
    {
        // 检查是否是邮件模板管理页面
        $emailSubPage = isset($_GET['email_sub']) ? $_GET['email_sub'] : 'settings';
        
        if ($emailSubPage === 'email_template') {
            // 跳转到邮件模板管理页面
            header('Location: admin.php?action=email_template');
            exit;
        }
        
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

        // 邮箱配置子菜单
        $emailSubPages = [
            'settings' => '邮箱设置',
            'email_template' => '邮件模板管理'
        ];

        $sub = 'email';
        $currentAction = 'email';

        // 使用标准的 config.html 模板
        include ADMIN_PATH . '/templates/config.html';
    }

    /**
     * 测试邮箱配置
     *
     * 流程：
     *   1. 先用表单值（经自动解密/加密）尝试发送测试邮件
     *   2. 发送成功后，把当前表单配置持久化到 bk_config，避免管理员再次点击"保存设置"
     *   3. 返回 JSON 结果供前端展示
     */
    public function test()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            header('Content-Type: application/json');

            $smtpHost = isset($_POST['email_smtp_host']) ? trim((string)$_POST['email_smtp_host']) : '';
            $smtpPort = isset($_POST['email_smtp_port']) ? (int)$_POST['email_smtp_port'] : 465;
            $smtpUsername = isset($_POST['email_smtp_username']) ? trim((string)$_POST['email_smtp_username']) : '';
            $smtpPassword = isset($_POST['email_smtp_password']) ? (string)$_POST['email_smtp_password'] : '';
            $smtpEncryption = isset($_POST['email_smtp_encryption']) ? trim((string)$_POST['email_smtp_encryption']) : 'ssl';
            $fromAddress = isset($_POST['email_from_address']) ? trim((string)$_POST['email_from_address']) : $smtpUsername;
            $fromName = isset($_POST['email_from_name']) ? trim((string)$_POST['email_from_name']) : '网站名称';
            $testEmail = isset($_POST['test_email']) ? trim((string)$_POST['test_email']) : '';

            // —— 前端约定：当管理员未修改密码输入框时，提交 "__KEEP__" 占位符
            //    此时应从数据库读取已加密的密文，解密后用于发送；
            //    若管理员手动输入了新密码，则直接作为明文使用
            $passwordForSending = $smtpPassword;
            if ($smtpPassword === '__KEEP__') {
                $storedPassword = Config::get('email_smtp_password', '');
                if (is_string($storedPassword) && strpos($storedPassword, 'ENC$') === 0) {
                    $passwordForSending = Config::decryptSensitive($storedPassword);
                } else {
                    $passwordForSending = (string)$storedPassword;
                }
            }

            if (empty($smtpHost) || empty($smtpUsername) || empty($testEmail)) {
                echo json_encode(['success' => false, 'message' => '请填写完整的邮箱配置和测试邮箱地址']);
                exit;
            }
            if ($passwordForSending === '' || $passwordForSending === null) {
                echo json_encode(['success' => false, 'message' => 'SMTP密码为空，请先在后台设置并保存密码']);
                exit;
            }

            try {
                $subject = '邮箱配置测试邮件';
                $message = "<html><body>";
                $message .= "<h2>邮箱配置测试成功！</h2>";
                $message .= "<p>这是一封测试邮件，用于验证您的邮箱配置是否正确。</p>";
                $message .= "<p>如果您收到这封邮件，说明您的邮箱配置已经成功，可以正常发送邮件。</p>";
                $message .= "<p>当前已自动保存这些配置，您无需再次点击\"保存设置\"。</p>";
                $message .= "<p>--<br>网站团队</p>";
                $message .= "</body></html>";

                // —— 临时覆盖 Config::* 运行时值，供 Mail 读取
                //    （这些值在本次请求结束后就失效）
                Config::set('email_smtp_host', $smtpHost);
                Config::set('email_smtp_port', $smtpPort);
                Config::set('email_smtp_username', $smtpUsername);
                Config::set('email_smtp_encryption', $smtpEncryption);
                Config::set('email_from_address', $fromAddress);
                Config::set('email_from_name', $fromName);
                // 对于 password：这里必须是明文才能被 SMTP 使用；save() 流程里会自动加密入库
                Config::set('email_smtp_password', $passwordForSending);

                $result = Mail::sendSmtp($testEmail, $subject, $message);

                if (is_array($result)) {
                    $sendOk = !empty($result['success']);
                } else {
                    $sendOk = (bool)$result;
                }

                if ($sendOk) {
                    // —— 发送成功后，把本次提交的配置持久化到数据库
                    //    注意：若 password 字段为 "__KEEP__"，则保留数据库原值（由 collectEmailConfig 处理）
                    $persistPost = [
                        'email_smtp_host' => $smtpHost,
                        'email_smtp_port' => $smtpPort,
                        'email_smtp_username' => $smtpUsername,
                        'email_smtp_encryption' => $smtpEncryption,
                        'email_from_address' => $fromAddress,
                        'email_from_name' => $fromName,
                        // 若本次未修改密码，则再次传入占位符，ConfigService 会自动回读数据库原值
                        'email_smtp_password' => $smtpPassword === '__KEEP__' ? '__KEEP__' : $passwordForSending,
                    ];

                    try {
                        $persistErrors = [];
                        $persistData = [];
                        // 手动调用 collect + save 流程（不依赖完整的表单提交）
                        self::persistEmailOnly($persistPost, $persistData, $persistErrors);
                    } catch (Exception $e) {
                        error_log('EmailSettingsController::test persist: ' . $e->getMessage());
                    }

                    echo json_encode(['success' => true, 'message' => '邮件发送成功，配置已自动保存']);
                    exit;
                }

                $msg = is_array($result) && !empty($result['message']) ? $result['message'] : '邮件发送失败，请检查您的配置';
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            } catch (Exception $e) {
                if (class_exists('Log')) { Log::error('Mail test error', 'admin', ['message' => $e->getMessage()]); }
                else { error_log('Mail test error: ' . $e->getMessage()); }
                echo json_encode(['success' => false, 'message' => '发送失败，请稍后重试']);
                exit;
            }
        }
    }

    /**
     * 只持久化"邮箱相关配置项"
     * 复用 ConfigService 的收集+保存逻辑，确保加密/校验/入库与主"保存设置"流程一致。
     */
    private static function persistEmailOnly(array $postData, &$outConfigData, &$outErrors) {
        if (!class_exists('ConfigService')) {
            require_once APP_PATH . '/Services/ConfigService.php';
        }
        // ConfigService::save() 负责 upsert 到 bk_config，我们这里直接调用
        $result = ConfigService::save($postData, []);
        $outConfigData = [];
        $outErrors = $result['errors'] ?? [];
        return $result;
    }

    /**
     * 保存邮箱配置
     */
    public function save()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST['sub_page'] = 'email';

            try {
                $result = ConfigService::save($_POST, $_FILES);

                if (!$result['success']) {
                    $_SESSION['config_errors'] = $result['errors'];
                } else {
                    $_SESSION['config_success'] = '配置保存成功！';
                }
            } catch (PDOException $e) {
                error_log('EmailSettingsController config save DB: ' . $e->getMessage());
                $_SESSION['config_errors'] = ['database' => '保存配置时发生数据库错误'];
            } catch (Exception $e) {
                error_log('EmailSettingsController config save: ' . $e->getMessage());
                $_SESSION['config_errors'] = ['file' => '保存配置时发生文件错误'];
            }

            header('Location: admin.php?action=email');
            exit;
        }
    }
}
