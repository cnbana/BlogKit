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
 * 验证码控制器
 * 处理验证码的生成和验证请求
 */
// 定义CORE_PATH常量（当直接访问该文件时）
if (!defined('CORE_PATH')) {
    define('CORE_PATH', dirname(__DIR__));
    define('ROOT_PATH', dirname(dirname(__DIR__)));
    
    // 加载必要的类文件
    
    // 初始化配置
    Config::init();
}

class CaptchaController {
    
    /**
     * 生成验证码图片
     */
    public function index() {
        // 引入验证码类
        
        // 从配置中获取验证码设置
        $config = $this->getConfig();
        
        // 检查是否有POST传递的动态配置
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $config = array_merge($config, $_POST);
        } else if (!empty($_GET)) {
            $config = array_merge($config, $_GET);
        }
        
        // 处理背景色格式转换
        if (isset($config['bg_color']) && is_string($config['bg_color'])) {
            // 将字符串格式的RGB值转换为数组
            $bgColor = explode(',', $config['bg_color']);
            if (count($bgColor) == 3) {
                $config['bg_color'] = array_map('intval', $bgColor);
            }
        }
        
        // 生成验证码图片
        Captcha::create($config);
    }
    
    /**
     * 验证验证码
     */
    public function verify() {
        // 引入验证码类
        
        // 获取用户输入的验证码
        $code = isset($_POST['code']) ? trim($_POST['code']) : '';
        
        // 从配置中获取验证码设置
        $config = $this->getConfig();
        
        // 验证验证码，实时验证不清除验证码
        $result = Captcha::check($code, $config, false);
        
        // 返回JSON响应
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $result,
            'message' => $result ? '验证码正确' : '验证码错误'
        ]);
        exit;
    }
    
    /**
     * 获取验证码配置
     * @return array 验证码配置数组
     */
    private function getConfig() {
        // 默认配置
        $defaultConfig = [
            'length' => rand(4, 6),
            'expire' => 300,
            'width' => 120,
            'height' => 40,
            'font_size' => 20,
            'bg_color' => [255, 255, 255],
            'line_color' => [200, 200, 200],
            'text_color' => [0, 0, 0],
            'noise_level' => 1,
            'line_count' => 3,
            'type' => 'mixed'
        ];
        
        // 从数据库中获取配置
        try {
            $configModel = new ConfigModel();
            $captchaConfig = $configModel->getConfigByPrefix('captcha');
            
            // 转换配置格式
            if (!empty($captchaConfig)) {
                foreach ($captchaConfig as $key => $value) {
                    // 移除前缀 captcha_
                    $configKey = str_replace('captcha_', '', $key);
                    
                    // 转换数值类型
                    if (is_numeric($value)) {
                        $value = (int)$value;
                    }
                    
                    // 转换布尔类型（处理字符串 '1'/'0' 和 'true'/'false'）
                    if (in_array(strtolower($value), ['1', 'true', 'yes'])) {
                        $value = true;
                    } elseif (in_array(strtolower($value), ['0', 'false', 'no'])) {
                        $value = false;
                    } elseif ($configKey === 'bg_color') {
                        // 特殊处理背景色：将字符串 "255,255,255" 转换为数组 [255, 255, 255]
                        $value = array_map('intval', explode(',', $value));
                    }
                    
                    // 直接赋值所有配置项，包括开关配置
                    $defaultConfig[$configKey] = $value;
                    
                    // 处理特殊配置项，确保兼容性
                    switch ($configKey) {
                        case 'show_lines':
                            // 如果关闭干扰线，将 line_count 设置为 0，确保兼容性
                            if (!$value) {
                                $defaultConfig['line_count'] = 0;
                            }
                            break;
                        case 'show_noise':
                            // 如果关闭噪点，将 noise_level 设置为 0，确保兼容性
                            if (!$value) {
                                $defaultConfig['noise_level'] = 0;
                            }
                            break;
                    }
                }
            }
        } catch (Exception $e) {
            // 配置加载失败，使用默认配置
        }
        
        // 确保长度在4-6之间
        if ($defaultConfig['length'] < 4 || $defaultConfig['length'] > 6) {
            $defaultConfig['length'] = rand(4, 6);
        }
        
        return $defaultConfig;
    }
}

// 直接访问该文件时，生成验证码图片
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    // 确保会话已启动
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    // 处理验证码生成请求
    $captchaController = new CaptchaController();
    
    // 获取请求的方法（默认index）
    $action = isset($_GET['action']) ? $_GET['action'] : 'index';
    
    // 执行对应方法
    if (method_exists($captchaController, $action)) {
        $captchaController->$action();
    } else {
        $captchaController->index();
    }
}
