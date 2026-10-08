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


class LoginController {

    /**
     * "记住我"自动登录验证（入口文件调用）
     * 检查 cookie 中的 admin_remember_token，验证通过后自动建立后台登录会话
     *
     * @global array $_SESSION
     * @global array $_COOKIE
     */
    public static function validateRememberToken() {
        // 已有后台登录会话，跳过
        if (isset($_SESSION['admin'])) {
            return;
        }

        // 没有 admin_remember_token cookie，跳过
        if (empty($_COOKIE['admin_remember_token'])) {
            return;
        }

        $token = $_COOKIE['admin_remember_token'];

        // 载入模型并验证令牌
        require_once APP_PATH . '/Models/LoginTokenModel.php';
        $loginTokenModel = new LoginTokenModel();
        $user = $loginTokenModel->validateToken($token);

        if (!empty($user)) {
            // 验证成功：检查用户是否有后台访问权限
            require_once APP_PATH . '/Models/RoleModel.php';
            if (RoleModel::userHasAdminAccess($user)) {
                // 建立后台登录会话
                $_SESSION['admin'] = $user;

                // 更新最后登录时间
                require_once APP_PATH . '/Models/UserModel.php';
                UserModel::updateUser($user['id'], ['last_login_at' => time()]);
                return;
            }
        }

        // 验证失败或无权限：清理无效 cookie
        $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        setcookie('admin_remember_token', '', time() - 3600, '/', '', $isHttps, true);
    }

    /**
     * 判断当前客户端 IP 是否因登录失败次数过多而被锁定
     * 统一入口：所有登录相关页面（login / forgot-password / reset-password）、
     * 验证码接口（captcha）、登录 POST 提交均应先调用本方法做前置拦截。
     *
     * @return array{locked:bool, remaining_minutes:int, lock_file:string, limit:int, lock_time:int}
     */
    public static function isIpLocked() {
        $configModel = new ConfigModel();
        $loginConfig = $configModel->getConfigByPrefix('login');
        $loginAttemptLimit = isset($loginConfig['login_attempt_limit']) ? (int)$loginConfig['login_attempt_limit'] : 5;
        $loginLockTime     = isset($loginConfig['login_lock_time'])     ? (int)$loginConfig['login_lock_time']     : 1800;

        $lockDir  = STORAGE_PATH . '/runtime';
        $clientIp = IpWhitelist::getRealIp();
        $lockFile = $lockDir . '/login_lock_' . md5($clientIp) . '.json';

        $result = [
            'locked'            => false,
            'remaining_minutes' => 0,
            'lock_file'         => $lockFile,
            'limit'             => $loginAttemptLimit,
            'lock_time'         => $loginLockTime,
        ];

        if (!file_exists($lockFile)) {
            return $result;
        }

        $lockData = json_decode(file_get_contents($lockFile), true);
        if (!is_array($lockData) || !isset($lockData['attempts'], $lockData['first_attempt'])) {
            return $result;
        }

        $failureCount = (int)$lockData['attempts'];
        $firstAttempt = (int)$lockData['first_attempt'];

        if ($failureCount >= $loginAttemptLimit) {
            $lockRemaining = $firstAttempt + $loginLockTime - time();
            if ($lockRemaining > 0) {
                $result['locked']            = true;
                $result['remaining_minutes'] = (int)ceil($lockRemaining / 60);
            }
        }

        return $result;
    }

    /**
     * 记录一次登录失败：累加尝试次数，首次失败时记录时间戳。
     * 成功登录时由调用方删除对应的 lock 文件。
     */
    private static function recordLoginFailure($username) {
        $configModel = new ConfigModel();
        $loginConfig = $configModel->getConfigByPrefix('login');
        $loginAttemptLimit = isset($loginConfig['login_attempt_limit']) ? (int)$loginConfig['login_attempt_limit'] : 5;
        $loginLockTime     = isset($loginConfig['login_lock_time'])     ? (int)$loginConfig['login_lock_time']     : 1800;

        $lockDir  = STORAGE_PATH . '/runtime';
        if (!is_dir($lockDir)) {
            @mkdir($lockDir, 0755, true);
        }

        $clientIp = IpWhitelist::getRealIp();
        $lockFile = $lockDir . '/login_lock_' . md5($clientIp) . '.json';

        $lockData = [
            'attempts'      => 1,
            'first_attempt' => time(),
            'username'      => $username,
        ];

        if (file_exists($lockFile)) {
            $existing = json_decode(file_get_contents($lockFile), true);
            if (is_array($existing)) {
                if (isset($existing['first_attempt']) && (time() - (int)$existing['first_attempt']) > $loginLockTime) {
                    // 上次锁定窗口已过期，清零重新计数
                    $lockData['attempts']      = 1;
                    $lockData['first_attempt'] = time();
                } else {
                    $lockData['attempts']      = (int)($existing['attempts'] ?? 0) + 1;
                    $lockData['first_attempt'] = (int)($existing['first_attempt'] ?? time());
                }
            }
        }

        @file_put_contents($lockFile, json_encode($lockData, JSON_UNESCAPED_UNICODE));

        Log::warning('用户登录失败', Log::CATEGORY_LOGIN, [
            'username'        => $username,
            'ip'              => $clientIp,
            'attempts'        => $lockData['attempts'],
            'limit'           => $loginAttemptLimit,
            'remaining_tries' => max(0, $loginAttemptLimit - $lockData['attempts']),
        ]);
    }

    /**
     * 渲染「登录被锁定」提示页：极简纯文字，不包含任何输入框 / 验证码 / 表单，
     * 防止锁定期间用户仍然能提交请求。
     */
    private static function renderLockoutPage($remainingMinutes) {
        $minutes = (int)$remainingMinutes;
        if ($minutes < 1) {
            $minutes = 1;
        }

        $siteName = Config::get('site_name', 'BlogKit');

        http_response_code(429); // Too Many Requests
        header('Content-Type: text/html; charset=utf-8');
        header('Retry-After: ' . ($minutes * 60));
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>访问受限 - <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC",
                         "Hiragino Sans GB", "Microsoft YaHei", sans-serif;
            background: #f5f5f7;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .box {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            padding: 40px 30px;
            max-width: 420px;
            width: 100%;
            text-align: center;
        }
        .icon {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: #fff3cd;
            color: #856404;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 20px;
        }
        h1 {
            font-size: 20px;
            color: #1d1d1f;
            margin-bottom: 12px;
        }
        p {
            color: #424245;
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 8px;
        }
        .tip {
            color: #86868b;
            font-size: 13px;
            margin-top: 16px;
        }
    </style>
</head>
<body>
    <div class="box">
        <div class="icon">!</div>
        <h1>访问受限</h1>
        <p>登录失败次数过多，请<?php echo $minutes; ?>分钟后再试。</p>
        <p class="tip">如需立即访问，请联系管理员。</p>
    </div>
</body>
</html>
<?php
        exit;
    }

    public function index() {
        // ========= 前置：IP 锁定检查（GET 展示 / POST 提交共用）=========
        $lockInfo = self::isIpLocked();
        if ($lockInfo['locked']) {
            // 记录一次拦截日志，但不再做任何密码校验 / session 写入
            Log::init();
            Log::warning('登录尝试被锁定拦截', Log::CATEGORY_LOGIN, [
                'ip'                => IpWhitelist::getRealIp(),
                'remaining_minutes' => $lockInfo['remaining_minutes'],
                'request_method'    => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            ]);
            // 渲染极简锁定提示页，不包含任何表单/输入框/验证码
            self::renderLockoutPage($lockInfo['remaining_minutes']);
            exit;
        }

        if (isset($_SESSION['admin'])) {
            header('Location: admin.php?action=dashboard');
            exit;
        }

        $error = '';

        // 获取验证码和登录配置
        $configModel = new ConfigModel();
        $captchaConfig = $configModel->getConfigByPrefix('captcha');
        $loginConfig = $configModel->getConfigByPrefix('login');

        // 检查后台登录页面是否启用验证码
        $captchaEnabled = isset($captchaConfig['captcha_enabled']) && $captchaConfig['captcha_enabled'] == '1';
        $captchaAdminLoginEnabled = isset($captchaConfig['captcha_admin_login_enabled']) && $captchaConfig['captcha_admin_login_enabled'] == '1';

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            // ========= 提交阶段（上面已保证未被锁定）=========
            if ($captchaEnabled && $captchaAdminLoginEnabled) {
                $captchaCode = isset($_POST['captcha_code']) ? $_POST['captcha_code'] : '';
                if (empty($captchaCode)) {
                    $error = '请输入验证码';
                } elseif (!Captcha::check($captchaCode)) {
                    // 验证码错误也记录一次失败（避免脚本无限刷验证码）
                    Log::init();
                    self::recordLoginFailure(isset($_POST['username']) ? $_POST['username'] : '');
                    $error = '验证码错误';
                } else {
                    $error = $this->verifyLogin($_POST['username'], $_POST['password']);
                }
            } else {
                $error = $this->verifyLogin($_POST['username'], $_POST['password']);
            }
        }

        // 显示登录表单
        include ADMIN_PATH . '/templates/login.html';
    }
    
    /**
     * 验证登录
     * @param string $username 用户名
     * @param string $password 密码
     * @return string 错误信息，空字符串表示成功
     */
    private function verifyLogin($username, $password) {
        Log::init();

        // ===== 前置：IP 锁定检查（作为双层保护，即使有人绕过 index() 的上层检查时仍生效）
        $lockInfo = self::isIpLocked();
        if ($lockInfo['locked']) {
            Log::warning('登录尝试被锁定拦截', Log::CATEGORY_LOGIN, [
                'username'          => $username,
                'ip'              => IpWhitelist::getRealIp(),
                'remaining_minutes' => $lockInfo['remaining_minutes'],
            ]);
            return "登录失败次数过多，请{$lockInfo['remaining_minutes']}分钟后再试";
        }

        // 检查是否勾选"记住我"
        $remember = isset($_POST['remember']) ? (int)$_POST['remember'] : 0;

        $configModel = new ConfigModel();
        $loginConfig = $configModel->getConfigByPrefix('login');
        $loginAttemptLimit = isset($loginConfig['login_attempt_limit']) ? (int)$loginConfig['login_attempt_limit'] : 5;
        $loginLockTime = isset($loginConfig['login_lock_time']) ? (int)$loginConfig['login_lock_time'] : 1800;

        $clientIp = IpWhitelist::getRealIp();
        $lockFile = $lockInfo['lock_file'];

        $db = Database::getInstance();
        $sql = "SELECT * FROM {$db->table('user')} WHERE username = ? LIMIT 1";
        $user = $db->fetch($sql, [$username]);

        $error = '';
        if (!$user || !password_verify($password, $user['password'])) {
            $error = '用户名或密码错误';
        } elseif ((int)$user['status'] !== 1) {
            $error = '账号已被禁用';
        } elseif (!RoleModel::userHasAdminAccess($user)) {
            $error = '该账号没有后台访问权限';
        }

        if (empty($error)) {
            if (file_exists($lockFile)) {
                @unlink($lockFile);
            }

            $context = [
                'user_id'    => $user['id'],
                'username'   => $username,
                'ip'         => $clientIp,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ];
            Log::info('用户登录成功', Log::CATEGORY_LOGIN, $context);

            UserModel::updateUser($user['id'], ['last_login_at' => time()]);

            session_regenerate_id(true);

            $_SESSION['admin'] = $user;

            // ========== 记住我：创建持久登录令牌 ==========
            if ($remember === 1) {
                require_once APP_PATH . '/Models/LoginTokenModel.php';
                $loginTokenModel = new LoginTokenModel();
                $token = $loginTokenModel->createToken($user['id'], 7);
                if ($token !== false) {
                    $cookieExpire = time() + (7 * 86400);
                    $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
                    setcookie('admin_remember_token', $token, $cookieExpire, '/', '', $isHttps, true);
                }
                // 清理过期令牌
                @$loginTokenModel->cleanExpiredTokens();
            }
            // ========== 记住我 END ==========

            header('Location: admin.php?action=dashboard');
            exit;
        }

        // ===== 登录失败：统一记录失败次数（包括账号被禁用 / 无权限都视为失败，防止攻击者通过失败次数也会累加）
        self::recordLoginFailure($username);

        // 计算剩余尝试次数（本次记录之后再读一次锁定状态，决定返回文案
        $afterLock = self::isIpLocked();
        if ($afterLock['locked']) {
            return "登录失败次数过多，请{$afterLock['remaining_minutes']}分钟后再试";
        }

        // 计算剩余尝试次数（实际剩余 = 总限制 - 本次失败后累计次数，从锁文件读取累计次数）
        $currentAttempts = 0;
        if (file_exists($lockFile)) {
            $ld = json_decode(@file_get_contents($lockFile), true);
            if (is_array($ld)) {
                $currentAttempts = (int)($ld['attempts'] ?? 0);
            }
        }
        $remainingAttempts = max(0, $loginAttemptLimit - $currentAttempts);

        return "{$error}（还剩{$remainingAttempts}次尝试机会）";
    }
    
    public function logout() {
        Log::init();

        $userId = isset($_SESSION['admin']['id']) ? $_SESSION['admin']['id'] : 0;
        $username = isset($_SESSION['admin']['username']) ? $_SESSION['admin']['username'] : '';

        Log::info('用户退出登录', Log::CATEGORY_LOGIN, ['user_id' => $userId, 'username' => $username]);

        // 清除"记住我"cookie 和数据库中的令牌
        if (!empty($_COOKIE['admin_remember_token'])) {
            require_once APP_PATH . '/Models/LoginTokenModel.php';
            $loginTokenModel = new LoginTokenModel();
            $loginTokenModel->deleteToken($_COOKIE['admin_remember_token']);
            $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
            setcookie('admin_remember_token', '', time() - 3600, '/', '', $isHttps, true);
        }

        unset($_SESSION['admin']);
        session_destroy();
        header('Location: admin.php?action=login');
        exit;
    }
    
    public function forgotPassword() {
        // ===== 前置：IP 锁定检查（GET/POST 都拦截，锁定期间不允许尝试找回密码）
        $lockInfo = self::isIpLocked();
        if ($lockInfo['locked']) {
            Log::init();
            Log::warning('找回密码尝试被锁定拦截', Log::CATEGORY_LOGIN, [
                'ip'                => IpWhitelist::getRealIp(),
                'remaining_minutes' => $lockInfo['remaining_minutes'],
                'request_method'    => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            ]);
            self::renderLockoutPage($lockInfo['remaining_minutes']);
            exit;
        }

        $error = '';
        $success = '';

        $configModel = new ConfigModel();
        $captchaConfig = $configModel->getConfigByPrefix('captcha');

        $captchaEnabled = isset($captchaConfig['captcha_enabled']) && $captchaConfig['captcha_enabled'] == '1';
        $captchaForgotPasswordEnabled = isset($captchaConfig['captcha_forgot_password_enabled']) && $captchaConfig['captcha_forgot_password_enabled'] == '1';

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $email = isset($_POST['email']) ? trim($_POST['email']) : '';

            if (empty($email)) {
                $error = '请输入邮箱地址';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = '邮箱格式不正确';
            } else {

                if ($captchaEnabled && $captchaForgotPasswordEnabled) {
                    $captchaCode = isset($_POST['captcha_code']) ? $_POST['captcha_code'] : '';
                    if (empty($captchaCode)) {
                        $error = '请输入验证码';
                    } elseif (!Captcha::check($captchaCode)) {
                        Log::init();
                        self::recordLoginFailure($email);
                        $error = '验证码错误';
                    } else {
                        $result = $this->processForgotPassword($email);
                        if (empty($result)) {
                            $success = '重置邮件已发送，请检查您的邮箱';
                        } else {
                            // 找回过程的任何失败也视为攻击（避免穷举）
                            Log::init();
                            self::recordLoginFailure($email);
                            $error = $result;
                        }
                    }
                } else {
                    $result = $this->processForgotPassword($email);
                    if (empty($result)) {
                        $success = '重置邮件已发送，请检查您的邮箱';
                    } else {
                        Log::init();
                        self::recordLoginFailure($email);
                        $error = $result;
                    }
                }
            }
        }

        include ADMIN_PATH . '/templates/forgot-password.html';
    }
    
    private function processForgotPassword($email) {
        $userModel = new UserModel();
        $user = $userModel->getUserByEmail($email);
        
        if (!$user) {
            return '该邮箱未注册';
        } elseif ((int)$user['status'] !== 1) {
            return '该账号已被禁用';
        } elseif (!RoleModel::userHasAdminAccess($user)) {
            return '该邮箱没有后台登录权限';
        } else {
            $smtpHost = Config::get('email_smtp_host', '');
            $smtpUsername = Config::get('email_smtp_username', '');
            
            if (empty($smtpHost) || empty($smtpUsername)) {
                return '系统未配置邮件SMTP，请联系管理员配置邮件服务';
            } else {
                $resetToken = md5($user['id'] . time() . rand(1000, 9999));
                $resetExpire = time() + 86400;
                
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
                    
                    $resetLink = $siteUrl . '/admin.php?action=reset-password&email=' . urlencode($email) . '&token=' . $resetToken;
                    
                    $mailResult = Mail::sendPasswordResetEmail($email, $user['username'], $resetLink);
                    
                    if ($mailResult) {
                        return '';
                    } else {
                        return '邮件发送失败，请稍后重试';
                    }
                } else {
                    return '发送失败，请稍后重试';
                }
            }
        }
    }
    
    public function resetPassword() {
        // ===== 前置：IP 锁定检查（锁定期间不能重置密码）
        $lockInfo = self::isIpLocked();
        if ($lockInfo['locked']) {
            Log::init();
            Log::warning('重置密码尝试被锁定拦截', Log::CATEGORY_LOGIN, [
                'ip'                => IpWhitelist::getRealIp(),
                'remaining_minutes' => $lockInfo['remaining_minutes'],
                'request_method'    => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            ]);
            self::renderLockoutPage($lockInfo['remaining_minutes']);
            exit;
        }

        $error = '';
        $success = '';
        $info = '';

        $configModel = new ConfigModel();
        $captchaConfig = $configModel->getConfigByPrefix('captcha');

        $captchaEnabled = isset($captchaConfig['captcha_enabled']) && $captchaConfig['captcha_enabled'] == '1';
        $captchaResetEnabled = isset($captchaConfig['captcha_reset_enabled']) && $captchaConfig['captcha_reset_enabled'] == '1';

        $email = isset($_GET['email']) ? $_GET['email'] : '';
        $token = isset($_GET['token']) ? $_GET['token'] : '';

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $email = isset($_POST['email']) ? trim($_POST['email']) : '';
            $token = isset($_POST['token']) ? trim($_POST['token']) : '';
            $password = isset($_POST['password']) ? $_POST['password'] : '';
            $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

            if (empty($email) || empty($token) || empty($password) || empty($confirmPassword)) {
                $error = '请填写完整信息';
            } elseif ($password != $confirmPassword) {
                $error = '两次输入的密码不一致';
            } elseif (strlen($password) < 6) {
                $error = '密码长度不能少于6位';
            } else {

                if ($captchaEnabled && $captchaResetEnabled) {
                    $captchaCode = isset($_POST['captcha_code']) ? $_POST['captcha_code'] : '';
                    if (empty($captchaCode)) {
                        $error = '请输入验证码';
                    } elseif (!Captcha::check($captchaCode)) {
                        Log::init();
                        self::recordLoginFailure($email);
                        $error = '验证码错误';
                    } else {
                        $result = $this->processResetPassword($email, $token, $password);
                        if (empty($result)) {
                            $success = '密码重置成功，请使用新密码登录';
                        } else {
                            Log::init();
                            self::recordLoginFailure($email);
                            $error = $result;
                        }
                    }
                } else {
                    $result = $this->processResetPassword($email, $token, $password);
                    if (empty($result)) {
                        $success = '密码重置成功，请使用新密码登录';
                    } else {
                        Log::init();
                        self::recordLoginFailure($email);
                        $error = $result;
                    }
                }
            }
        }

        if (!empty($email) && !empty($token)) {
            $info = '请输入新密码';
        }

        include ADMIN_PATH . '/templates/reset-password.html';
    }
    
    private function processResetPassword($email, $token, $password) {
        $userModel = new UserModel();
        $user = $userModel->getUserByEmail($email);
        
        if (!$user) {
            return '邮箱未注册';
        } elseif ((int)$user['status'] !== 1) {
            return '该账号已被禁用';
        } elseif (!RoleModel::userHasAdminAccess($user)) {
            return '该邮箱没有后台登录权限';
        } elseif (empty($user['reset_token']) || $user['reset_token'] != $token) {
            return '重置令牌无效';
        } elseif (empty($user['reset_token_expire']) || $user['reset_token_expire'] < time()) {
            return '重置令牌已过期';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            $result = $userModel->updateUser($user['id'], [
                'password' => $hashedPassword,
                'reset_token' => null,
                'reset_token_expire' => null
            ]);
            
            if ($result) {
                return '';
            } else {
                return '密码重置失败，请稍后重试';
            }
        }
    }
}
