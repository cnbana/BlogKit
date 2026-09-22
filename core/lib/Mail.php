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
 * 邮件发送工具类
 * 用于统一处理系统中的邮件发送功能
 */
class Mail {
    /**
     * 读取 SMTP 密码
     * 对数据库中加密后的字符串（ENC$... / RAW$...）进行解密
     * 同时兼容遗留的明文存储值（直接原样返回）
     *
     * @return string 明文密码
     */
    private static function getSmtpPassword() {
        $stored = Config::get('email_smtp_password', '');
        if ($stored === '' || $stored === null) {
            return '';
        }
        if (is_string($stored) && method_exists('Config', 'decryptSensitive')) {
            return Config::decryptSensitive($stored);
        }
        return (string)$stored;
    }

    /**
     * 发送邮件
     * @param string $to 收件人邮箱
     * @param string $subject 邮件主题
     * @param string $message 邮件内容
     * @param array $options 可选参数
     * @return bool 是否发送成功
     */
    public static function send($to, $subject, $message, $options = []) {
        // 获取邮箱配置
        $smtpHost = Config::get('email_smtp_host', '');
        $smtpPort = Config::get('email_smtp_port', 465);
        $smtpUsername = Config::get('email_smtp_username', '');
        $smtpPassword = self::getSmtpPassword();
        $smtpEncryption = Config::get('email_smtp_encryption', 'ssl');
        $fromAddress = Config::get('email_from_address', $smtpUsername);
        $fromName = Config::get('email_from_name', '网站名称');

        // 检查必要配置
        if (empty($smtpHost) || empty($smtpUsername) || empty($smtpPassword)) {
            return false;
        }

        // 设置邮件头
        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/html; charset=utf-8';
        $headers[] = "From: {$fromName} <{$fromAddress}>";
        $headers[] = "Reply-To: {$fromName} <{$fromAddress}>";
        $headers[] = "Subject: {$subject}";
        $headers[] = "X-Mailer: PHP " . phpversion();

        // 处理加密类型
        $encryption = strtolower($smtpEncryption);
        $smtpSecure = '';
        if ($encryption == 'ssl') {
            $smtpSecure = 'ssl';
        } elseif ($encryption == 'tls') {
            $smtpSecure = 'tls';
        }

        // 使用PHP内置的mail函数发送邮件
        // 注意：这种方式依赖于服务器的sendmail配置
        // 对于更可靠的邮件发送，建议使用SMTP协议直接发送

        // 这里我们使用mail函数发送邮件
        $success = mail($to, $subject, $message, implode("\r\n", $headers));

        return $success;
    }

    /**
     * 使用SMTP协议发送邮件
     * @param string $to 收件人邮箱
     * @param string $subject 邮件主题
     * @param string $message 邮件内容
     * @param array $options 可选参数
     * @return array|bool 返回成功状态和错误信息
     */
    public static function sendSmtp($to, $subject, $message, $options = []) {
        require_once CORE_PATH . '/lib/Log.php';
        Log::init();

        // 获取邮箱配置
        $smtpHost = Config::get('email_smtp_host', '');
        $smtpPort = Config::get('email_smtp_port', 465);
        $smtpUsername = Config::get('email_smtp_username', '');
        $smtpPassword = self::getSmtpPassword();   // 自动解密
        $smtpEncryption = Config::get('email_smtp_encryption', 'ssl');
        $fromAddress = Config::get('email_from_address', $smtpUsername);
        $fromName = Config::get('email_from_name', '网站名称');

        // 检查必要配置
        if (empty($smtpHost)) {
            return ['success' => false, 'message' => 'SMTP服务器地址不能为空'];
        }
        if (empty($smtpUsername)) {
            return ['success' => false, 'message' => 'SMTP用户名不能为空'];
        }
        if (empty($smtpPassword)) {
            return ['success' => false, 'message' => 'SMTP密码不能为空'];
        }

        // 处理加密类型
        $encryption = strtolower($smtpEncryption);
        $smtpSecure = '';
        if ($encryption == 'ssl') {
            $smtpSecure = 'ssl';
        } elseif ($encryption == 'tls') {
            $smtpSecure = 'tls';
        }
        
        // 创建SMTP连接
        $socket = self::createSmtpConnection($smtpHost, $smtpPort, $smtpSecure);
        if (!$socket) {
            return ['success' => false, 'message' => "无法连接到SMTP服务器 {$smtpHost}:{$smtpPort}"];
        }
        
        try {
            // 发送SMTP命令
            self::smtpCommand($socket, "EHLO {$smtpHost}");
            
            // 处理TLS加密
            if ($smtpSecure == 'tls') {
                self::smtpCommand($socket, "STARTTLS");
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                self::smtpCommand($socket, "EHLO {$smtpHost}");
            }
            
            // 登录认证
            self::smtpCommand($socket, "AUTH LOGIN");
            self::smtpCommand($socket, base64_encode($smtpUsername));
            self::smtpCommand($socket, base64_encode($smtpPassword));
            
            // 设置发件人
            self::smtpCommand($socket, "MAIL FROM: <{$fromAddress}>");
            
            // 设置收件人
            $toAddresses = explode(',', $to);
            foreach ($toAddresses as $address) {
                self::smtpCommand($socket, "RCPT TO: <" . trim($address) . ">");
            }
            
            // 开始邮件内容
            self::smtpCommand($socket, "DATA");
            
            // 构建邮件内容
            $email = "From: {$fromName} <{$fromAddress}>\r\n";
            $email .= "To: {$to}\r\n";
            $email .= "Subject: {$subject}\r\n";
            $email .= "MIME-Version: 1.0\r\n";
            $email .= "Content-Type: text/html; charset=utf-8\r\n\r\n";
            $email .= $message . "\r\n.\r\n";
            
            // 发送邮件内容
            fwrite($socket, $email);
            self::smtpResponse($socket);
            
            // 退出SMTP会话
            self::smtpCommand($socket, "QUIT");
            
            // 关闭连接
            fclose($socket);
            
            Log::info('邮件发送成功', 'email', ['to' => $to, 'subject' => $subject, 'from' => $fromAddress]);
            
            return ['success' => true, 'message' => '邮件发送成功'];
        } catch (Exception $e) {
            // 关闭连接
            fclose($socket);
            
            Log::error('邮件发送失败', 'email', ['to' => $to, 'subject' => $subject, 'error' => $e->getMessage()]);
            
            return ['success' => false, 'message' => '邮件发送失败，请稍后重试'];
        }
    }
    
    /**
     * 创建SMTP连接
     * @param string $host SMTP服务器地址
     * @param int $port SMTP服务器端口
     * @param string $secure 加密方式
     * @return resource|false SMTP连接资源
     */
    private static function createSmtpConnection($host, $port, $secure) {
        $protocol = 'tcp';
        if ($secure == 'ssl') {
            $protocol = 'ssl';
        }
        
        $socket = fsockopen("{$protocol}://{$host}", $port, $errno, $errstr, 30);
        if (!$socket) {
            return false;
        }
        
        // 读取服务器欢迎信息
        self::smtpResponse($socket);
        
        return $socket;
    }
    
    /**
     * 发送SMTP命令
     * @param resource $socket SMTP连接资源
     * @param string $command SMTP命令
     */
    private static function smtpCommand($socket, $command) {
        fwrite($socket, "{$command}\r\n");
        self::smtpResponse($socket);
    }
    
    /**
     * 读取SMTP响应
     * @param resource $socket SMTP连接资源
     * @return string SMTP响应
     * @throws Exception 如果SMTP响应错误
     */
    private static function smtpResponse($socket) {
        $response = '';
        while ($line = fgets($socket)) {
            $response .= $line;
            // 清理控制字符，只保留可见字符
            $cleanLine = preg_replace('/[^\x20-\x7E\r\n]/', '', $line);
            if (substr($cleanLine, 3, 1) == ' ') {
                break;
            }
        }
        
        // 清理响应中的控制字符，只保留可见字符
        $cleanResponse = preg_replace('/[^\x20-\x7E\r\n]/', '', $response);
        
        // 提取响应码，确保只获取前3个数字，忽略前导空白字符
        if (preg_match('/^\s*(\d{3})/', $cleanResponse, $matches) === 0) {
            throw new Exception("SMTP Error: Invalid response format - {$cleanResponse}");
        }
        
        $responseCode = $matches[1];
        
        // 220: SMTP服务器欢迎消息
        // 235: 认证成功
        // 250: 命令成功
        // 221: 连接关闭
        // 354: 开始邮件内容
        // 334: 等待认证信息
        if (!in_array($responseCode, ['220', '235', '250', '221', '354', '334'])) {
            throw new Exception("SMTP Error: {$cleanResponse}");
        }
        
        return $cleanResponse;
    }
    
    /**
     * 发送密码重置邮件
     * @param string $to 收件人邮箱
     * @param string $username 用户名
     * @param string $resetLink 密码重置链接
     * @return bool 是否发送成功
     */
    public static function sendPasswordResetEmail($to, $username, $resetLink) {
        $subject = '密码重置请求';
        $message = "<html><body>";
        $message .= "<h2>密码重置请求</h2>";
        $message .= "<p>尊敬的 {$username}：</p>";
        $message .= "<p>您收到这封邮件是因为您请求重置密码。</p>";
        $message .= "<p>请点击以下链接重置您的密码：</p>";
        $message .= "<p><a href='{$resetLink}' target='_blank'>{$resetLink}</a></p>";
        $message .= "<p>如果您没有请求重置密码，请忽略此邮件。</p>";
        $message .= "<p>此链接将在24小时后过期。</p>";
        $message .= "<p>--<br>网站团队</p>";
        $message .= "</body></html>";
        
        $result = self::sendSmtp($to, $subject, $message);
        return is_array($result) ? $result['success'] : $result;
    }
    
    /**
     * 发送注册确认邮件
     * @param string $to 收件人邮箱
     * @param string $username 用户名
     * @param string $confirmLink 注册确认链接
     * @return bool 是否发送成功
     */
    public static function sendRegistrationEmail($to, $username, $confirmLink) {
        $subject = '注册确认';
        $message = "<html><body>";
        $message .= "<h2>注册确认</h2>";
        $message .= "<p>尊敬的 {$username}：</p>";
        $message .= "<p>感谢您注册我们的网站！</p>";
        $message .= "<p>请点击以下链接确认您的注册：</p>";
        $message .= "<p><a href='{$confirmLink}' target='_blank'>{$confirmLink}</a></p>";
        $message .= "<p>如果您没有注册我们的网站，请忽略此邮件。</p>";
        $message .= "<p>--<br>网站团队</p>";
        $message .= "</body></html>";
        
        $result = self::sendSmtp($to, $subject, $message);
        return is_array($result) ? $result['success'] : $result;
    }
    
    /**
     * 发送通知邮件
     * @param string $to 收件人邮箱
     * @param string $subject 邮件主题
     * @param string $content 邮件内容
     * @return bool 是否发送成功
     */
    public static function sendNotificationEmail($to, $subject, $content) {
        $message = "<html><body>";
        $message .= "<h2>{$subject}</h2>";
        $message .= "<p>{$content}</p>";
        $message .= "<p>--<br>网站团队</p>";
        $message .= "</body></html>";
        
        $result = self::sendSmtp($to, $subject, $message);
        return is_array($result) ? $result['success'] : $result;
    }
    
    /**
     * 使用模板发送邮件
     * @param string $to 收件人邮箱
     * @param string $templateType 模板类型
     * @param array $variables 模板变量
     * @return bool 是否发送成功
     */
    public static function sendWithTemplate($to, $templateType, $variables = []) {
        // 确保加载了EmailTemplate类
        if (!class_exists('EmailTemplate')) {
            require CORE_PATH . '/lib/EmailTemplate.php';
        }
        
        // 加载模板
        $template = EmailTemplate::loadTemplate($templateType);
        if (!$template) {
            return false;
        }
        
        // 渲染模板
        $rendered = EmailTemplate::render($template, $variables);
        
        // 发送邮件
        $result = self::sendSmtp($to, $rendered['subject'], $rendered['content']);
        return is_array($result) ? $result['success'] : $result;
    }
    
    /**
     * 批量发送邮件
     * @param array $recipients 收件人列表，格式：["email" => "变量数组"]
     * @param string $templateType 模板类型
     * @param array $commonVariables 公共变量
     * @return array 发送结果，格式：["email" => bool]
     */
    public static function batchSendWithTemplate($recipients, $templateType, $commonVariables = []) {
        $results = [];
        
        // 加载模板
        $template = EmailTemplate::loadTemplate($templateType);
        if (!$template) {
            // 所有发送都失败
            foreach ($recipients as $email => $variables) {
                $results[$email] = false;
            }
            return $results;
        }
        
        // 批量发送
        foreach ($recipients as $email => $variables) {
            // 合并公共变量和个人变量
            $allVariables = array_merge($commonVariables, $variables);
            
            // 渲染模板
            $rendered = EmailTemplate::render($template, $allVariables);
            
            // 发送邮件
            $result = self::sendSmtp($email, $rendered['subject'], $rendered['content']);
            $results[$email] = is_array($result) ? $result['success'] : $result;
        }
        
        return $results;
    }
    
    /**
     * 队列发送邮件（待实现）
     * @param string $to 收件人邮箱
     * @param string $templateType 模板类型
     * @param array $variables 模板变量
     * @return bool 是否加入队列成功
     */
    public static function queueSendWithTemplate($to, $templateType, $variables = []) {
        // TODO: 实现邮件队列功能
        // 目前先直接发送
        return self::sendWithTemplate($to, $templateType, $variables);
    }
}