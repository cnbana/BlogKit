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


// 包含模型类

// 包含分页类

class AuthController {
    
    /**
     * 共享模板初始化：创建模板引擎、加载导航数据、设置消息配置
     * 消除 login/register/forgotPassword/resetPassword/profile 等方法中的重复代码
     * 
     * @return Template 初始化好的模板实例
     */
    private function initAuthTemplate() {
        $template = new Template();
        
        // 导航数据：分类（只获取显示在菜单中的分类）
        $categoryModel = new CategoryModel();
        $template->assign('categories', $categoryModel->getMenuCategories());
        
        // 导航数据：菜单页面
        $pageModel = new PageModel();
        $template->assign('pages', $pageModel->getMenuPages());
        
        // 消息配置
        $template->assign('message_enabled', Config::get('message_enabled', '1'));
        $template->assign('message_duration', Config::get('message_duration', '3'));
        
        return $template;
    }
    
    /**
     * 获取验证码配置
     * @return array ['captcha_enabled' => bool, 'captcha_xxx_enabled' => bool, ...]
     */
    private function getCaptchaConfig() {
        $configModel = new ConfigModel();
        $captchaConfig = $configModel->getConfigByPrefix('captcha');
        
        $captchaEnabled = isset($captchaConfig['captcha_enabled']) && $captchaConfig['captcha_enabled'] == '1';
        
        return [
            'captcha_enabled' => $captchaEnabled,
            'captcha_login_enabled' => $captchaEnabled && isset($captchaConfig['captcha_login_enabled']) && $captchaConfig['captcha_login_enabled'] == '1',
            'captcha_register_enabled' => $captchaEnabled && isset($captchaConfig['captcha_register_enabled']) && $captchaConfig['captcha_register_enabled'] == '1',
            'captcha_forgot_password_enabled' => $captchaEnabled && isset($captchaConfig['captcha_forgot_password_enabled']) && $captchaConfig['captcha_forgot_password_enabled'] == '1',
            'captcha_reset_enabled' => $captchaEnabled && isset($captchaConfig['captcha_reset_enabled']) && $captchaConfig['captcha_reset_enabled'] == '1',
        ];
    }

    /**
     * 获取登录相关配置（功能开关 / 尝试次数 / 锁定时间 / 登录方式）
     *
     * @return array{enabled:bool, attempt_limit:int, lock_time:int, allow_email:bool, allow_username:bool}
     */
    private function getLoginConfig() {
        $configModel = new ConfigModel();
        $loginConfig = $configModel->getConfigByPrefix('login');

        return [
            'enabled'         => !isset($loginConfig['login_enabled']) || (int)$loginConfig['login_enabled'] === 1,
            'attempt_limit'   => isset($loginConfig['login_attempt_limit']) ? (int)$loginConfig['login_attempt_limit'] : 5,
            'lock_time'       => isset($loginConfig['login_lock_time']) ? (int)$loginConfig['login_lock_time'] : 1800,
            'allow_email'     => !isset($loginConfig['login_allow_email']) || (int)$loginConfig['login_allow_email'] === 1,
            'allow_username'  => !isset($loginConfig['login_allow_username']) || (int)$loginConfig['login_allow_username'] === 1,
        ];
    }

    /**
     * 获取当前客户端 IP 的登录失败锁定信息（前台专用）
     *
     * @return array{locked:bool, remaining_minutes:int, lock_file:string, limit:int, lock_time:int, attempts:int}
     */
    private function getFrontLoginLockInfo() {
        $loginConfig = $this->getLoginConfig();
        $limit       = $loginConfig['attempt_limit'];
        $lockTime    = $loginConfig['lock_time'];

        $lockDir  = STORAGE_PATH . '/runtime';
        $clientIp = IpWhitelist::getRealIp();
        $lockFile = $lockDir . '/front_login_lock_' . md5($clientIp) . '.json';

        $result = [
            'locked'            => false,
            'remaining_minutes' => 0,
            'lock_file'         => $lockFile,
            'limit'             => $limit,
            'lock_time'         => $lockTime,
            'attempts'          => 0,
        ];

        if (!file_exists($lockFile)) {
            return $result;
        }

        $lockData = json_decode(file_get_contents($lockFile), true);
        if (!is_array($lockData) || !isset($lockData['attempts'], $lockData['first_attempt'])) {
            return $result;
        }

        $result['attempts'] = (int)$lockData['attempts'];
        $failureCount       = (int)$lockData['attempts'];
        $firstAttempt       = (int)$lockData['first_attempt'];

        // 已超过尝试次数且仍在锁定窗口内 → 视为锁定
        if ($failureCount >= $limit) {
            $lockRemaining = $firstAttempt + $lockTime - time();
            if ($lockRemaining > 0) {
                $result['locked']            = true;
                $result['remaining_minutes'] = (int)ceil($lockRemaining / 60);
            }
        }

        return $result;
    }

    /**
     * 记录一次前台登录失败（写入 IP 锁定文件）
     */
    private function recordFrontLoginFailure($username) {
        $loginConfig = $this->getLoginConfig();
        $lockTime    = $loginConfig['lock_time'];

        $lockDir = STORAGE_PATH . '/runtime';
        if (!is_dir($lockDir)) {
            @mkdir($lockDir, 0755, true);
        }

        $clientIp = IpWhitelist::getRealIp();
        $lockFile = $lockDir . '/front_login_lock_' . md5($clientIp) . '.json';

        $lockData = [
            'attempts'      => 1,
            'first_attempt' => time(),
            'username'      => $username,
        ];

        if (file_exists($lockFile)) {
            $existing = json_decode(file_get_contents($lockFile), true);
            if (is_array($existing)) {
                if (isset($existing['first_attempt']) && (time() - (int)$existing['first_attempt']) > $lockTime) {
                    // 锁定窗口已过期，重新计数
                    $lockData['attempts']      = 1;
                    $lockData['first_attempt'] = time();
                } else {
                    $lockData['attempts']      = (int)($existing['attempts'] ?? 0) + 1;
                    $lockData['first_attempt'] = (int)($existing['first_attempt'] ?? time());
                }
            }
        }

        @file_put_contents($lockFile, json_encode($lockData, JSON_UNESCAPED_UNICODE));
    }
    
    /**
     * 处理验证码验证逻辑
     * @param bool $captchaRequired 是否需要验证码
     * @param callable $onSuccess 验证成功后的回调
     * @param Template $template 模板实例
     */
    private function handleCaptchaVerify($captchaRequired, callable $onSuccess, $template) {
        if (!$captchaRequired) {
            $onSuccess($template);
            return;
        }
        
        $captchaCode = isset($_POST['captcha_code']) ? $_POST['captcha_code'] : '';
        
        if (empty($captchaCode)) {
            $template->assign('error', '请输入验证码');
        } elseif (!Captcha::check($captchaCode)) {
            $template->assign('error', '验证码错误');
        } else {
            $onSuccess($template);
        }
    }
    
    /**
     * 执行重定向跳转
     * @param string $redirectUrl 跳转地址（可为空）
     * @param string $defaultPath 默认跳转路径
     */
    private function doRedirect($redirectUrl = '', $defaultPath = '/index.php') {
        if (!empty($redirectUrl)) {
            if (strpos($redirectUrl, 'http') === false) {
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                header('Location: ' . $protocol . '://' . $host . $redirectUrl);
            } else {
                header('Location: ' . $redirectUrl);
            }
        } else {
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            header('Location: ' . $protocol . '://' . $host . $defaultPath);
        }
        exit;
    }
    
    private function parseSeoTemplate($template, $data = []) {
        $replacements = [
            '{site.name}' => Config::get('site_name', 'My Blog'),
            '{site.description}' => Config::get('site_description', ''),
            '{site.keywords}' => Config::get('site_keywords', ''),
            '{site.url}' => Config::get('site.url', ''),
        ];
        
        if (isset($data['user'])) {
            $replacements['{user.nickname}'] = $data['user']['nickname'] ?? '';
            $replacements['{user.username}'] = $data['user']['username'] ?? '';
        }
        
        if (isset($data['page_title'])) {
            $replacements['{page.title}'] = $data['page_title'];
        }
        
        return strtr($template, $replacements);
    }

    public function login() {
        $template    = $this->initAuthTemplate();
        $captcha     = $this->getCaptchaConfig();
        $loginConfig = $this->getLoginConfig();

        // 传递验证码配置
        $template->assign('captcha_enabled', $captcha['captcha_enabled']);
        $template->assign('captcha_login_enabled', $captcha['captcha_login_enabled']);

        // 预计算登录方式相关的文本与状态，避免在模板中写复杂表达式（模板引擎不支持 {elif/||/!xxx}）
        $allowEmail    = !empty($loginConfig['allow_email']);
        $allowUsername = !empty($loginConfig['allow_username']);
        $loginEnabled  = !empty($loginConfig['enabled']);

        // login_disabled：登录功能关闭，或两种登录方式都不允许
        $loginDisabled = (!$loginEnabled) || (!$allowEmail && !$allowUsername);
        $template->assign('login_disabled', $loginDisabled);

        // 用户名输入框 label / placeholder
        if ($allowEmail && $allowUsername) {
            $loginUsernameLabel       = '用户名/邮箱';
            $loginUsernamePlaceholder  = '请输入用户名或邮箱';
        } elseif ($allowEmail) {
            $loginUsernameLabel       = '邮箱';
            $loginUsernamePlaceholder  = '请输入邮箱';
        } else {
            $loginUsernameLabel       = '用户名';
            $loginUsernamePlaceholder  = '请输入用户名';
        }
        $template->assign('login_username_label', $loginUsernameLabel);
        $template->assign('login_username_placeholder', $loginUsernamePlaceholder);

        // 已登录用户直接跳转
        if (isset($_SESSION['user'])) {
            header('Location: index.php');
            exit;
        }

        // 登录功能总开关：关闭时直接拦截
        if (!$loginConfig['enabled']) {
            $template->assign('error', '登录功能已关闭，请联系管理员');
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // POST 前置检查：IP 是否被锁定
            $lockInfo = $this->getFrontLoginLockInfo();
            if ($lockInfo['locked']) {
                $template->assign('error', sprintf('登录失败次数过多，请 %d 分钟后再试', $lockInfo['remaining_minutes']));
            } else {
                // 进入验证码校验 → 通过后执行 processLogin（内部还会再做一次锁定/方式校验）
                $this->handleCaptchaVerify(
                    $captcha['captcha_login_enabled'],
                    [$this, 'processLogin'],
                    $template
                );
            }
        }

        // 传递 redirect 参数
        $redirectUrl = $_POST['redirect'] ?? $_GET['redirect'] ?? null;
        if ($redirectUrl) {
            $template->assign('redirect', $redirectUrl);
        }

        // SEO
        $template->assign('seo_title', $this->parseSeoTemplate('登录 - {site.name}'));
        $template->assign('seo_description', $this->parseSeoTemplate('登录到{site.name}'));
        $template->assign('seo_keywords', $this->parseSeoTemplate('登录,{site.name}'));

        $template->display('login');
    }
    
    public function register() {
        $template = $this->initAuthTemplate();
        $captcha = $this->getCaptchaConfig();

        // 读取后台注册设置的配置项（用于模板提示信息与长度限制）
        $regConfig = $this->getRegisterConfig();
        $template->assign('reg_config', $regConfig);

        $template->assign('captcha_enabled', $captcha['captcha_enabled']);
        $template->assign('captcha_register_enabled', $captcha['captcha_register_enabled']);

        // 已登录用户重定向
        if (isset($_SESSION['user'])) {
            header('Location: index.php');
            exit;
        }

        // 检查注册功能是否启用
        if (!Config::get('user_registration')) {
            $template->assign('error', '注册功能已关闭，请联系管理员');
            $template->assign('register_enabled', false);
            $template->display('register');
            exit;
        }

        $template->assign('register_enabled', true);

        // 处理注册请求
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->handleCaptchaVerify(
                $captcha['captcha_register_enabled'],
                [$this, 'processRegister'],
                $template
            );
        }

        // redirect 参数
        $redirectUrl = $_POST['redirect'] ?? $_GET['redirect'] ?? null;
        if ($redirectUrl) {
            $template->assign('redirect', $redirectUrl);
        }

        // SEO
        $template->assign('seo_title', $this->parseSeoTemplate('注册 - {site.name}'));
        $template->assign('seo_description', $this->parseSeoTemplate('注册成为{site.name}的会员'));
        $template->assign('seo_keywords', $this->parseSeoTemplate('注册,{site.name}'));

        $template->display('register');
    }

    /**
     * 聚合所有 register_* 配置项，统一默认值，方便模板与逻辑共用。
     * @return array
     */
    private function getRegisterConfig() {
        return [
            'username_min_length'       => (int)Config::get('register_username_min_length', 3),
            'username_max_length'       => (int)Config::get('register_username_max_length', 20),
            'username_ban_pure_number'  => (int)Config::get('register_username_ban_pure_number', 0),
            'username_ban_simple_string'=> (int)Config::get('register_username_ban_simple_string', 0),
            'username_ban_keywords'     => Config::get('register_username_ban_keywords', ''),
            'password_min_length'       => (int)Config::get('register_password_min_length', 6),
            'password_require_uppercase'=> (int)Config::get('register_password_require_uppercase', 0),
            'password_require_lowercase'=> (int)Config::get('register_password_require_lowercase', 0),
            'password_require_number'   => (int)Config::get('register_password_require_number', 0),
            'password_require_special'  => (int)Config::get('register_password_require_special', 0),
            'ip_limit_time'             => (int)Config::get('register_ip_limit_time', 60),    // 分钟
            'ip_limit_count'            => (int)Config::get('register_ip_limit_count', 5),
            'force_email_verify'        => (int)Config::get('register_force_email_verify', 0),
            'new_user_restrict_hours'   => (int)Config::get('register_new_user_restrict_hours', 24),
            'enable_honeypot'           => (int)Config::get('register_enable_honeypot', 0),
            'enable_device_limit'       => (int)Config::get('register_enable_device_limit', 0),
            'privacy_policy_url'        => Config::get('privacy_policy_url', ''),
            'terms_of_service_url'      => Config::get('terms_of_service_url', ''),
        ];
    }
    
    public function forgotPassword() {
        $template = $this->initAuthTemplate();
        $captcha = $this->getCaptchaConfig();
        
        $template->assign('captcha_enabled', $captcha['captcha_enabled']);
        $template->assign('captcha_forgot_password_enabled', $captcha['captcha_forgot_password_enabled']);
        
        // SEO
        $template->assign('seo_title', $this->parseSeoTemplate('忘记密码 - {site.name}'));
        $template->assign('seo_description', $this->parseSeoTemplate('找回{site.name}的密码'));
        $template->assign('seo_keywords', $this->parseSeoTemplate('忘记密码,找回密码,{site.name}'));
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->handleCaptchaVerify(
                $captcha['captcha_forgot_password_enabled'],
                [$this, 'processForgotPassword'],
                $template
            );
        }
        
        $template->display('forgot-password');
    }
    
    public function resetPassword() {
        $template = $this->initAuthTemplate();
        $captcha = $this->getCaptchaConfig();

        // 传递验证码配置给模板，控制验证码输入框的显示
        $template->assign('captcha_enabled', $captcha['captcha_enabled']);
        $template->assign('captcha_reset_enabled', $captcha['captcha_reset_enabled']);

        // 设置SEO
        $template->assign('seo_title', $this->parseSeoTemplate('重置密码 - {site.name}'));
        $template->assign('seo_description', $this->parseSeoTemplate('重置{site.name}的密码'));
        $template->assign('seo_keywords', $this->parseSeoTemplate('重置密码,{site.name}'));

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $email = isset($_POST['email']) ? trim($_POST['email']) : '';
            $token = isset($_POST['token']) ? trim($_POST['token']) : '';
            $password = isset($_POST['password']) ? $_POST['password'] : '';
            $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

            if (empty($email) || empty($token) || empty($password) || empty($confirmPassword)) {
                $template->assign('email', $email);
                $template->assign('token', $token);
                $template->assign('error', '请填写完整信息');
            } elseif ($password != $confirmPassword) {
                $template->assign('email', $email);
                $template->assign('token', $token);
                $template->assign('error', '两次输入的密码不一致');
            } elseif (strlen($password) < 6) {
                $template->assign('email', $email);
                $template->assign('token', $token);
                $template->assign('error', '密码长度不能少于6位');
            } else {
                // 验证码校验（仅在启用时执行）
                $this->handleCaptchaVerify(
                    $captcha['captcha_reset_enabled'],
                    [$this, 'processResetPassword'],
                    $template
                );
            }
        }

        $email = isset($_GET['email']) ? $_GET['email'] : '';
        $token = isset($_GET['token']) ? $_GET['token'] : '';

        if (!empty($email) && !empty($token)) {
            $template->assign('email', $email);
            $template->assign('token', $token);
        }

        $template->display('reset-password');
    }
    
    private function processForgotPassword($template) {
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        
        if (empty($email)) {
            $error = '请输入邮箱地址';
            $template->assign('error', $error);
            return;
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = '邮箱格式不正确';
            $template->assign('error', $error);
            return;
        }
        
        $userModel = new UserModel();
        $user = $userModel->getUserByEmail($email);
        
        if (!$user) {
            $error = '该邮箱未注册';
            $template->assign('error', $error);
            return;
        }
        
        $smtpHost = Config::get('email_smtp_host', '');
        $smtpUsername = Config::get('email_smtp_username', '');
        
        if (empty($smtpHost) || empty($smtpUsername)) {
            $error = '系统未配置邮件SMTP，请联系管理员配置邮件服务';
            $template->assign('error', $error);
            return;
        }
        
        $resetToken = md5($user['id'] . time() . rand(1000, 9999));
        $resetExpire = time() + 86400;
        
        $userModel = new UserModel();
        $result = $userModel->updateUser($user['id'], [
            'reset_token' => $resetToken,
            'reset_token_expire' => $resetExpire
        ]);
        
        if ($result) {
            $siteUrl = Config::get('site.url', '');
            if (empty($siteUrl)) {
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $siteUrl = "$protocol://$host";
            }
            
            $resetLink = $siteUrl . '/index.php/reset-password?email=' . urlencode($email) . '&token=' . $resetToken;
            
            $mailResult = Mail::sendPasswordResetEmail($email, $user['username'], $resetLink);
            
            if ($mailResult) {
                $success = '重置邮件已发送，请检查您的邮箱';
                $template->assign('success', $success);
            } else {
                $error = '邮件发送失败，请稍后重试';
                $template->assign('error', $error);
            }
        } else {
            $error = '发送失败，请稍后重试';
            $template->assign('error', $error);
        }
    }
    
    private function processResetPassword($template) {
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $token = isset($_POST['token']) ? trim($_POST['token']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        
        if (empty($email) || empty($token) || empty($password)) {
            $error = '请填写完整信息';
            $template->assign('error', $error);
            return;
        }
        
        if (strlen($password) < 6) {
            $error = '密码长度不能少于6位';
            $template->assign('error', $error);
            return;
        }
        
        $userModel = new UserModel();
        $user = $userModel->getUserByEmail($email);
        
        if (!$user) {
            $error = '邮箱未注册';
            $template->assign('error', $error);
            return;
        }
        
        if ($user['reset_token'] != $token) {
            $error = '重置令牌无效';
            $template->assign('error', $error);
            return;
        }
        
        if ($user['reset_token_expire'] < time()) {
            $error = '重置令牌已过期';
            $template->assign('error', $error);
            return;
        }
        
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $result = $userModel->updateUser($user['id'], [
            'password' => $hashedPassword,
            'reset_token' => null,
            'reset_token_expire' => null
        ]);
        
        if ($result) {
            $success = '密码重置成功，请使用新密码登录';
            $template->assign('success', $success);
        } else {
            $error = '密码重置失败，请稍后重试';
            $template->assign('error', $error);
        }
    }
    
    public function logout() {
        // 退出登录：清除会话
        unset($_SESSION['user']);
        $_SESSION['success_message'] = '退出登录成功！';

        // 清除"记住我"cookie 和数据库中的令牌
        if (!empty($_COOKIE['remember_token'])) {
            require_once APP_PATH . '/Models/LoginTokenModel.php';
            $loginTokenModel = new LoginTokenModel();
            $loginTokenModel->deleteToken($_COOKIE['remember_token']);
            // 清除 cookie
            $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
            setcookie('remember_token', '', time() - 3600, '/', '', $isHttps, true);
        }

        // 重定向
        $redirect = $_GET['redirect'] ?? '';
        $this->doRedirect($redirect, '/index.php');
    }
    
    /**
     * 个人中心（委托至 ProfileController）
     */
    public function profile() {
        $controller = new ProfileController();
        $controller->profile();
    }

    /**
     * 显示用户公开资料（委托至 ProfileController）
     */
    public function user($userId = null) {
        $controller = new ProfileController();
        $controller->user($userId);
    }

    /**
     * 处理关注/取消关注的 AJAX 请求（委托至 ProfileController）
     */
    public function followAjax() {
        $controller = new ProfileController();
        $controller->followAjax();
    }

    /**
     * 处理关注/取消关注请求（委托至 ProfileController）
     */
    public function follow() {
        $controller = new ProfileController();
        $controller->follow();
    }

    /**
     * 显示用户关注列表（委托至 ProfileController）
     */
    public function following() {
        $controller = new ProfileController();
        $controller->following();
    }

    /**
     * 显示用户粉丝列表（委托至 ProfileController）
     */
    public function followers() {
        $controller = new ProfileController();
        $controller->followers();
    }

    /**
     * 处理登录请求（前置校验由 login() 方法负责：登录功能开关 / IP 锁定 / 验证码）
     * 本方法还会再次检查登录方式（邮箱 / 用户名）并补充失败计数与登录成功清理逻辑。
     */
    private function processLogin($template) {
        $loginConfig = $this->getLoginConfig();

        // 登录功能总开关（防御性再判一次）
        if (!$loginConfig['enabled']) {
            $template->assign('error', '登录功能已关闭，请联系管理员');
            return;
        }

        // IP 被锁定（防御性再判一次，避免绕过前置检查直接调用）
        $lockInfo = $this->getFrontLoginLockInfo();
        if ($lockInfo['locked']) {
            $template->assign('error', sprintf('登录失败次数过多，请 %d 分钟后再试', $lockInfo['remaining_minutes']));
            return;
        }

        // 获取表单数据
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $remember = isset($_POST['remember']) ? (int)$_POST['remember'] : 0;
        $redirect = isset($_POST['redirect']) ? $_POST['redirect'] : '';

        // 验证表单数据
        if (empty($username) || empty($password)) {
            $template->assign('error', '请输入用户名和密码');
            return;
        }

        // 根据输入判断登录方式，并按后台开关拦截
        $isEmail = filter_var($username, FILTER_VALIDATE_EMAIL);
        if ($isEmail && !$loginConfig['allow_email']) {
            $template->assign('error', '当前系统不允许使用邮箱登录');
            return;
        }
        if (!$isEmail && !$loginConfig['allow_username']) {
            $template->assign('error', '当前系统不允许使用用户名登录');
            return;
        }

        // 验证用户名和密码
        $userModel = new UserModel();
        $user = $userModel->login($username, $password);

        if (!$user) {
            $this->recordFrontLoginFailure($username);
            $template->assign('error', '用户名或密码错误');
            return;
        }

        // 登录成功，清理该 IP 的锁定文件
        if (!empty($lockInfo['lock_file']) && file_exists($lockInfo['lock_file'])) {
            @unlink($lockInfo['lock_file']);
        }

        // 设置会话
        $_SESSION['user'] = $user;

        // 更新用户最后登录时间
        UserModel::updateUser($user['id'], ['last_login_at' => time()]);

        // ========== 记住我：创建持久登录令牌 ==========
        if ($remember === 1) {
            require_once APP_PATH . '/Models/LoginTokenModel.php';
            $loginTokenModel = new LoginTokenModel();
            $token = $loginTokenModel->createToken($user['id'], 7);
            if ($token !== false) {
                $cookieExpire = time() + (7 * 86400);
                $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
                setcookie('remember_token', $token, $cookieExpire, '/', '', $isHttps, true);
            }
            // 清理过期令牌（延迟执行，不影响响应速度）
            @$loginTokenModel->cleanExpiredTokens();
        }
        // ========== 记住我 END ==========

        // 触发通知自动清理
        $notificationModel = new NotificationModel();
        $notificationModel->cleanExpiredNotifications($user['id']);

        // 设置登录成功提示
        $_SESSION['success_message'] = '登录成功！';
        $this->doRedirect($redirect, '/index.php');
    }

    /**
     * "记住我"自动登录验证：入口文件调用
     * 检查 cookie 中的 remember_token，验证通过后自动设置会话
     *
     * @global array $_SESSION
     * @global array $_COOKIE
     */
    public static function validateRememberToken() {
        // 已有登录会话，跳过
        if (isset($_SESSION['user'])) {
            return;
        }

        // 没有 remember_token cookie，跳过
        if (empty($_COOKIE['remember_token'])) {
            return;
        }

        $token = $_COOKIE['remember_token'];

        // 载入模型并验证令牌
        require_once APP_PATH . '/Models/LoginTokenModel.php';
        $loginTokenModel = new LoginTokenModel();
        $user = $loginTokenModel->validateToken($token);

        if (!empty($user)) {
            // 验证成功：建立登录会话
            $_SESSION['user'] = $user;

            // 更新最后登录时间
            require_once APP_PATH . '/Models/UserModel.php';
            UserModel::updateUser($user['id'], ['last_login_at' => time()]);
        } else {
            // 验证失败：清理无效 cookie
            $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
            setcookie('remember_token', '', time() - 3600, '/', '', $isHttps, true);
        }
    }
    
    /**
     * 处理注册请求
     * 基于后台注册设置配置项（用户名长度/密码强度/IP限制/邮箱验证等）执行完整校验。
     */
    private function processRegister($template) {
        $reg = $this->getRegisterConfig();

        // 获取表单数据
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        $redirect = isset($_POST['redirect']) ? $_POST['redirect'] : '';

        // ====== 蜜罐防护（Honeypot）：隐藏字段被填写说明是机器人 ======
        // register_enable_honeypot=1 时启用此功能
        if (!empty($reg['enable_honeypot']) && !empty($_POST['website'])) {
            $template->assign('error', '注册失败，请稍后重试');
            return;
        }

        // ====== 设备注册限制：基于 User-Agent + IP 生成设备指纹 ======
        // register_enable_device_limit=1 时启用此功能，限制同一设备注册频率
        if (!empty($reg['enable_device_limit'])) {
            $clientIp = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
            $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
            if (!empty($clientIp) && !empty($userAgent)) {
                $deviceFingerprint = md5($clientIp . '|' . $userAgent);
                $userModel = new UserModel();
                $count = $userModel->countRecentRegistrationsByDevice(
                    $deviceFingerprint,
                    $reg['ip_limit_time']
                );
                if ($count >= 1) {
                    $template->assign('error', "同一设备在 {$reg['ip_limit_time']} 分钟内仅能注册一次，请稍后重试");
                    return;
                }
            }
        }

        // 基本必填校验
        if (empty($username) || empty($email) || empty($password) || empty($confirmPassword)) {
            $template->assign('error', '请填写完整信息');
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $template->assign('error', '邮箱格式不正确');
            return;
        }

        // ====== 用户名校验（基于配置） ======
        $usernameLen = mb_strlen($username, 'UTF-8');
        if ($usernameLen < $reg['username_min_length']) {
            $template->assign('error', "用户名长度不能少于 {$reg['username_min_length']} 个字符");
            return;
        }
        if ($usernameLen > $reg['username_max_length']) {
            $template->assign('error', "用户名长度不能超过 {$reg['username_max_length']} 个字符");
            return;
        }

        // 字符集校验：仅允许 汉字 + 英文字母 + 数字 + _ - .
        // 不允许空格、其他标点与乱符号
        if (!preg_match('/^[\p{Han}a-zA-Z0-9_\-\.]+$/u', $username)) {
            $template->assign('error', '用户名仅允许汉字、字母、数字以及 _ - . 这三个符号，不允许空格或其他字符');
            return;
        }

        // 违禁词校验：从 register_username_ban_keywords 读取，支持中英文逗号分隔
        $banKeywords = Config::get('register_username_ban_keywords', '');
        if (!empty($banKeywords)) {
            $banList = preg_split('/[,，]/u', $banKeywords, -1, PREG_SPLIT_NO_EMPTY);
            $banList = array_map('trim', $banList);
            $banList = array_filter($banList, 'strlen');
            if (!empty($banList)) {
                $matched = '';
                foreach ($banList as $keyword) {
                    if (mb_stripos($username, $keyword) !== false) {
                        $matched = $keyword;
                        break;
                    }
                }
                if ($matched !== '') {
                    $template->assign('error', "用户名中包含不允许使用的词：{$matched}");
                    return;
                }
            }
        }

        if ($reg['username_ban_pure_number'] && preg_match('/^\d+$/u', $username)) {
            $template->assign('error', '用户名不允许为纯数字');
            return;
        }
        if ($reg['username_ban_simple_string']) {
            // 禁止简单字符串：全同一字符 / 连续递增/递减
            $isSimple = false;
            if (preg_match('/^(.)\1+$/u', $username)) {
                $isSimple = true;
            } else {
                // 检查递增/递减，如 "1234" "abcd" "4321"
                $chars = preg_split('//u', $username, -1, PREG_SPLIT_NO_EMPTY);
                if (count($chars) >= 3) {
                    $ascending = true;
                    $descending = true;
                    for ($i = 1; $i < count($chars); $i++) {
                        if (ord($chars[$i]) - ord($chars[$i - 1]) !== 1) $ascending = false;
                        if (ord($chars[$i - 1]) - ord($chars[$i]) !== 1) $descending = false;
                    }
                    if ($ascending || $descending) $isSimple = true;
                }
            }
            if ($isSimple) {
                $template->assign('error', '用户名过于简单，请更换');
                return;
            }
        }

        // ====== 密码校验（基于配置） ======
        if (strlen($password) < $reg['password_min_length']) {
            $template->assign('error', "密码长度不能少于 {$reg['password_min_length']} 位");
            return;
        }
        if ($reg['password_require_uppercase'] && !preg_match('/[A-Z]/', $password)) {
            $template->assign('error', '密码必须包含至少一个大写字母');
            return;
        }
        if ($reg['password_require_lowercase'] && !preg_match('/[a-z]/', $password)) {
            $template->assign('error', '密码必须包含至少一个小写字母');
            return;
        }
        if ($reg['password_require_number'] && !preg_match('/\d/', $password)) {
            $template->assign('error', '密码必须包含至少一个数字');
            return;
        }
        if ($reg['password_require_special'] && !preg_match('/[^A-Za-z0-9]/', $password)) {
            $template->assign('error', '密码必须包含至少一个特殊字符');
            return;
        }
        if ($password !== $confirmPassword) {
            $template->assign('error', '两次输入的密码不一致');
            return;
        }

        // ====== IP 注册频率限制 ======
        $clientIp = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
        if ($reg['ip_limit_time'] > 0 && $reg['ip_limit_count'] > 0 && !empty($clientIp)) {
            $userModel = new UserModel();
            $count = $userModel->countRecentRegistrationsByIp(
                $clientIp,
                $reg['ip_limit_time']
            );
            if ($count >= $reg['ip_limit_count']) {
                $template->assign('error', "同一IP在 {$reg['ip_limit_time']} 分钟内最多注册 {$reg['ip_limit_count']} 个账号");
                return;
            }
        }

        // ====== 检查用户名 / 邮箱重复 ======
        $userModel = new UserModel();
        if ($userModel->getUserByUsername($username)) {
            $template->assign('error', '用户名已存在');
            return;
        }
        if ($userModel->getUserByEmail($email)) {
            $template->assign('error', '邮箱已被注册');
            return;
        }

        // ====== 创建用户，附带 IP/UA 信息用于后续分析 ======
        $userId = $userModel->registerWithMeta(
            $username,
            $email,
            $password,
            $clientIp,
            isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            $reg['force_email_verify']
        );

        if (!$userId) {
            $template->assign('error', '注册失败，请稍后重试');
            return;
        }

        // ====== 发送注册邮件（欢迎邮件 / 邮箱验证邮件）======
        // 邮件发送作为"非阻塞"的辅助操作，失败不影响注册主流程
        try {
            $userEmail = trim($email);
            if ($userEmail !== '' && filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
                // 1. 优先从数据库读取管理员配置的 registration 模板（启用状态）
                $templateContent = null;
                if (class_exists('EmailTemplateModel')) {
                    $tmplModel = new EmailTemplateModel();
                    $tpl = $tmplModel->getTemplateByType('registration');
                    if (!empty($tpl) && !empty($tpl['subject']) && !empty($tpl['content'])) {
                        $templateContent = ['subject' => $tpl['subject'], 'content' => $tpl['content']];
                    }
                }
                // 2. 没有数据库模板时 fallback 到类内硬编码的默认模板
                if ($templateContent === null) {
                    $fallback = EmailTemplate::getDefaultTemplate('registration');
                    if (!empty($fallback) && is_array($fallback)
                        && !empty($fallback['subject']) && !empty($fallback['content'])) {
                        $templateContent = ['subject' => $fallback['subject'], 'content' => $fallback['content']];
                    }
                }

                $siteName = Config::get('site_name', '网站');
                $siteUrl = rtrim(Config::get('site_url', ''), '/');
                if ($reg['force_email_verify']) {
                    $verifyToken = substr(md5($userId . '_' . $userEmail . '_' . time()), 0, 16);
                } else {
                    $verifyToken = '';
                }
                $verificationLink = $verifyToken
                    ? ($siteUrl . '/index.php?c=Auth&m=verifyEmail&token=' . $verifyToken)
                    : '';

                $replacements = [
                    '{username}' => $username,
                    '{email}' => $userEmail,
                    '{site_name}' => $siteName,
                    '{site.url}' => $siteUrl,
                    '{site_url}' => $siteUrl,
                    '{verification_link}' => $verificationLink,
                    '{date}' => date('Y-m-d H:i:s'),
                ];

                if ($templateContent !== null) {
                    $subject = strtr($templateContent['subject'], $replacements);
                    $body = strtr($templateContent['content'], $replacements);
                    Mail::sendSmtp($userEmail, $subject, $body);
                } else {
                    // fallback：手工组装一封简单的欢迎邮件
                    $subject = $reg['force_email_verify']
                        ? "欢迎注册 {$siteName}，请验证您的邮箱"
                        : "欢迎注册 {$siteName}";
                    $body = '<html><body>';
                    $body .= "<h2>欢迎注册 {$siteName}！</h2>";
                    $body .= "<p>尊敬的 {$username}：</p>";
                    if ($reg['force_email_verify']) {
                        $body .= "<p>请点击下方链接验证您的邮箱：</p>";
                        $body .= "<p><a href=\"{$verificationLink}\">{$verificationLink}</a></p>";
                    } else {
                        $body .= "<p>您已成功注册，现在可以登录后发表评论和文章。</p>";
                    }
                    $body .= "<p>—— {$siteName} 团队</p>";
                    $body .= '</body></html>';
                    Mail::sendSmtp($userEmail, $subject, $body);
                }
            }
        } catch (Exception $e) {
            // 邮件发送失败不能影响主流程，这里只做静默日志
            error_log('AuthController::processRegister send email failed: ' . $e->getMessage());
        }

        // 若启用了强制邮箱验证，则不自动登录，提示用户去邮箱验证
        if ($reg['force_email_verify']) {
            $_SESSION['success_message'] = '注册成功！请查收邮件完成邮箱验证后登录。';
            $this->doRedirect($redirect, '/index.php?c=Auth&m=login');
            return;
        }

        // 正常流程：自动登录
        $user = $userModel->getUserById($userId);
        $_SESSION['user'] = $user;
        $_SESSION['success_message'] = '注册成功！欢迎加入。';
        $this->doRedirect($redirect, '/index.php');
    }

    /**
     * 检查当前登录用户是否仍处于"新用户限制期"。
     * 返回 true 表示仍在限制期内，具体是否禁止某项操作由调用方判断。
     * @return bool
     */
    public static function isNewUserRestricted() {
        if (!isset($_SESSION['user']['id']) || !isset($_SESSION['user']['created_at'])) {
            return false;
        }
        $hours = (int)Config::get('register_new_user_restrict_hours', 24);
        if ($hours <= 0) return false;
        $threshold = time() - ($hours * 3600);
        return (int)$_SESSION['user']['created_at'] > $threshold;
    }
    
    /**
     * 检查用户名是否存在
     */
    public function checkUsername() {
        if (isset($_GET['username'])) {
            $username = trim($_GET['username']);

            // 先检查违禁词：用户名中是否包含不允许使用的词
            $banKeywords = Config::get('register_username_ban_keywords', '');
            if (!empty($banKeywords)) {
                $banList = preg_split('/[,，]/u', $banKeywords, -1, PREG_SPLIT_NO_EMPTY);
                $banList = array_map('trim', $banList);
                $banList = array_filter($banList);
                if (!empty($banList)) {
                    foreach ($banList as $keyword) {
                        if (mb_stripos($username, $keyword) !== false) {
                            header('Content-Type: application/json');
                            echo json_encode(['exists' => false, 'message' => '用户名中包含不允许使用的词：' . $keyword, 'banned' => true]);
                            exit;
                        }
                    }
                }
            }

            $userModel = new UserModel();
            $user = $userModel->getUserByUsername($username);

            header('Content-Type: application/json');
            if ($user) {
                echo json_encode(['exists' => true, 'message' => '用户名已存在']);
            } else {
                echo json_encode(['exists' => false, 'message' => '用户名可用']);
            }
        } else {
            header('Content-Type: application/json');
            echo json_encode(['error' => '缺少用户名参数']);
        }
        exit;
    }
    
    /**
     * 检查邮箱是否存在
     */
    public function checkEmail() {
        if (isset($_GET['email'])) {
            $email = trim($_GET['email']);
            $userModel = new UserModel();
            $user = $userModel->getUserByEmail($email);
            
            header('Content-Type: application/json');
            if ($user) {
                echo json_encode(['exists' => true, 'message' => '邮箱已被注册']);
            } else {
                echo json_encode(['exists' => false, 'message' => '邮箱可用']);
            }
        } else {
            header('Content-Type: application/json');
            echo json_encode(['error' => '缺少邮箱参数']);
        }
        exit;
    }
}
