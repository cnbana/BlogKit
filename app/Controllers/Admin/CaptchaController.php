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
 * 验证码控制器：用于登录/找回密码/重置密码流程中的验证码生成与校验
 * 安全策略：
 *   1. 客户端 IP 因登录失败被锁定（login_attempt_limit）期间，直接拒绝一切验证码操作，
 *      防止攻击者绕过锁定继续刷验证码。
 */
class AdminCaptchaController {

    /**
     * 判断当前 IP 是否被登录锁定（复用 LoginController 的统一逻辑）
     */
    private static function isIpLocked() {
        if (!class_exists('LoginController')) {
            require_once APP_PATH . '/Controllers/Admin/LoginController.php';
        }
        if (!class_exists('IpWhitelist')) {
            require_once CORE_PATH . '/lib/IpWhitelist.php';
        }
        return LoginController::isIpLocked();
    }

    /**
     * 默认生成验证码图片
     */
    public function index() {
        $this->create();
    }

    /**
     * 保存验证码配置
     */
    public function save() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: admin.php?action=captcha');
            exit;
        }

        $configModel = new ConfigModel();

        $configs = [];

        $configs[] = ['name' => 'captcha_enabled', 'value' => isset($_POST['captcha_enabled']) && $_POST['captcha_enabled'] == '1' ? '1' : '0', 'description' => '验证码全局开关', 'type' => 'boolean'];
        $configs[] = ['name' => 'captcha_type', 'value' => $_POST['captcha_type'], 'description' => '默认验证码类型', 'type' => 'string'];
        $configs[] = ['name' => 'captcha_expire', 'value' => (int)$_POST['captcha_expire'], 'description' => '验证码过期时间（秒）', 'type' => 'integer'];
        $configs[] = ['name' => 'captcha_length', 'value' => (int)$_POST['captcha_length'], 'description' => '验证码显示长度（4-6位）', 'type' => 'integer'];

        $configs[] = ['name' => 'captcha_width', 'value' => (int)$_POST['captcha_width'], 'description' => '验证码宽度', 'type' => 'integer'];
        $configs[] = ['name' => 'captcha_height', 'value' => (int)$_POST['captcha_height'], 'description' => '验证码高度', 'type' => 'integer'];
        $configs[] = ['name' => 'captcha_font_size', 'value' => (int)$_POST['captcha_font_size'], 'description' => '验证码字体大小', 'type' => 'integer'];
        $configs[] = ['name' => 'captcha_show_lines', 'value' => isset($_POST['captcha_show_lines']) && $_POST['captcha_show_lines'] == '1' ? '1' : '0', 'description' => '是否显示干扰线', 'type' => 'boolean'];
        $configs[] = ['name' => 'captcha_show_noise', 'value' => isset($_POST['captcha_show_noise']) && $_POST['captcha_show_noise'] == '1' ? '1' : '0', 'description' => '是否显示噪点', 'type' => 'boolean'];
        $configs[] = ['name' => 'captcha_noise_level', 'value' => (int)$_POST['captcha_noise_level'], 'description' => '噪点级别（1-5）', 'type' => 'integer'];
        $configs[] = ['name' => 'captcha_line_count', 'value' => (int)$_POST['captcha_line_count'], 'description' => '干扰线数量', 'type' => 'integer'];
        $configs[] = ['name' => 'captcha_bg_color', 'value' => $_POST['captcha_bg_color'], 'description' => '验证码背景色（RGB）', 'type' => 'string'];

        $configs[] = ['name' => 'captcha_login_enabled', 'value' => isset($_POST['captcha_login_enabled']) && $_POST['captcha_login_enabled'] == '1' ? '1' : '0', 'description' => '登录页面是否启用验证码', 'type' => 'boolean'];
        $configs[] = ['name' => 'captcha_register_enabled', 'value' => isset($_POST['captcha_register_enabled']) && $_POST['captcha_register_enabled'] == '1' ? '1' : '0', 'description' => '注册页面是否启用验证码', 'type' => 'boolean'];
        $configs[] = ['name' => 'captcha_comment_enabled', 'value' => isset($_POST['captcha_comment_enabled']) && $_POST['captcha_comment_enabled'] == '1' ? '1' : '0', 'description' => '评论功能是否启用验证码', 'type' => 'boolean'];
        $configs[] = ['name' => 'captcha_reset_enabled', 'value' => isset($_POST['captcha_reset_enabled']) && $_POST['captcha_reset_enabled'] == '1' ? '1' : '0', 'description' => '密码重置是否启用验证码', 'type' => 'boolean'];
        $configs[] = ['name' => 'captcha_admin_login_enabled', 'value' => isset($_POST['captcha_admin_login_enabled']) && $_POST['captcha_admin_login_enabled'] == '1' ? '1' : '0', 'description' => '后台登录是否启用验证码', 'type' => 'boolean'];

        $result = $configModel->setBatchConfig($configs);

        if ($result) {
            Log::info('验证码设置', '保存配置', '成功保存验证码配置', Log::CATEGORY_OPERATION);
        } else {
            Log::error('验证码设置', '保存配置', '保存验证码配置失败', Log::CATEGORY_OPERATION);
        }

        header('Location: admin.php?action=captcha');
        exit;
    }

    /**
     * 测试验证码
     */
    public function test() {
        Captcha::create();
    }

    /**
     * AJAX 验证验证码（用于登录/注册页面实时验证）
     * 返回 JSON 格式结果
     */
    public function verify() {
        header('Content-Type: application/json');

        // IP 锁定时直接拒绝任何验证请求，防止绕过登录流程刷验证码
        $lockInfo = self::isIpLocked();
        if ($lockInfo['locked']) {
            if (!class_exists('Log')) {
                require_once CORE_PATH . '/lib/Log.php';
            }
            Log::init();
            Log::warning('验证码验证被锁定拦截', Log::CATEGORY_LOGIN, [
                'ip'                => IpWhitelist::getRealIp(),
                'remaining_minutes' => $lockInfo['remaining_minutes'],
            ]);
            http_response_code(429);
            echo json_encode([
                'success' => false,
                'message' => "登录失败次数过多，请{$lockInfo['remaining_minutes']}分钟后再试",
            ]);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => '请求方法不允许']);
            return;
        }

        $code = $_POST['code'] ?? '';

        if (empty($code)) {
            echo json_encode(['success' => false, 'message' => '验证码不能为空']);
            return;
        }

        $result = Captcha::check($code, [], false);

        echo json_encode([
            'success' => $result,
            'message' => $result ? '验证码正确' : '验证码错误',
        ]);
    }

    /**
     * 生成验证码（用于实时预览或登录页显示）
     */
    public function create() {
        // IP 锁定期间不生成新验证码（返回 1x1 空图，节省 CPU 且给前端错误信号）
        $lockInfo = self::isIpLocked();
        if ($lockInfo['locked']) {
            if (!class_exists('Log')) {
                require_once CORE_PATH . '/lib/Log.php';
            }
            Log::init();
            Log::warning('验证码生成被锁定拦截', Log::CATEGORY_LOGIN, [
                'ip'                => IpWhitelist::getRealIp(),
                'remaining_minutes' => $lockInfo['remaining_minutes'],
            ]);
            http_response_code(429);
            header('Content-Type: image/png');
            $img = @imagecreatetruecolor(1, 1);
            if ($img) {
                imagepng($img);
                imagedestroy($img);
            }
            return;
        }

        $config = [];
        if (isset($_GET['type'])) $config['type'] = $_GET['type'];
        if (isset($_GET['length'])) $config['length'] = (int)$_GET['length'];
        if (isset($_GET['width'])) $config['width'] = (int)$_GET['width'];
        if (isset($_GET['height'])) $config['height'] = (int)$_GET['height'];
        if (isset($_GET['font_size'])) $config['font_size'] = (int)$_GET['font_size'];
        if (isset($_GET['bg_color'])) {
            $bgColor = trim($_GET['bg_color']);
            if (!empty($bgColor)) {
                $rgb = explode(',', $bgColor);
                if (count($rgb) == 3) {
                    $config['bg_color'] = array_map('intval', $rgb);
                }
            }
        }
        if (isset($_GET['show_lines'])) $config['show_lines'] = (int)$_GET['show_lines'];
        if (isset($_GET['line_count'])) $config['line_count'] = (int)$_GET['line_count'];
        if (isset($_GET['show_noise'])) $config['show_noise'] = (int)$_GET['show_noise'];
        if (isset($_GET['noise_level'])) $config['noise_level'] = (int)$_GET['noise_level'];

        Captcha::create($config);
    }
}
