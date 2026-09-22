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
 * 统一验证码类
 * 支持字母数字混合验证码，提供会话存储和统一验证接口
 */
class Captcha {
    // 配置参数
    private $config = [
        'length' => 4, // 验证码长度
        'expire' => 300, // 过期时间（秒）
        'width' => 120, // 验证码宽度
        'height' => 40, // 验证码高度
        'font_size' => 20, // 字体大小
        'bg_color' => [255, 255, 255], // 背景色
        'line_color' => [200, 200, 200], // 干扰线颜色
        'text_color' => [0, 0, 0], // 文字颜色
        'noise_level' => 1, // 噪点级别（1-5）
        'line_count' => 3, // 干扰线数量
        'font_file' => '', // 字体文件路径
        'session_key' => 'captcha', // 会话存储键名
        'type' => 'mixed' // 验证码类型：mixed, number, letter
    ];
    
    // 可用字符集
    private $charSets = [
        'number' => '0123456789',
        'letter' => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
        'mixed' => '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'
    ];
    
    /**
     * 构造函数
     * @param array $config 配置参数
     */
    public function __construct($config = []) {
        // 合并配置
        $this->config = array_merge($this->config, $config);
        
        // 设置随机长度（4-6位）
        if ($this->config['length'] < 4 || $this->config['length'] > 6) {
            $this->config['length'] = rand(4, 6);
        }
        
        // 初始化会话
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        

    }
    
    /**
     * 生成验证码
     * @return string 验证码字符串
     */
    public function generate() {
        // 生成随机验证码
        $code = $this->createCode();
        
        // 存储验证码到会话
        $this->storeCode($code);
        
        return $code;
    }
    
    /**
     * 创建验证码字符串
     * @return string 验证码字符串
     */
    private function createCode() {
        $charset = $this->charSets[$this->config['type']];
        $code = '';
        $charsetLength = strlen($charset);
        
        for ($i = 0; $i < $this->config['length']; $i++) {
            $code .= $charset[rand(0, $charsetLength - 1)];
        }
        
        return $code;
    }
    
    /**
     * 存储验证码到会话
     * @param string $code 验证码字符串
     */
    private function storeCode($code) {
        $_SESSION[$this->config['session_key']] = [
            'code' => $code,
            'expire' => time() + $this->config['expire']
        ];
    }
    
    /**
     * 生成验证码图片
     */
    public function createImage() {
        // 清空输出缓冲区，防止之前框架层产生的任何输出污染图片数据
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // 生成验证码
        $code = $this->generate();
        
        // 创建画布
        $image = imagecreate($this->config['width'], $this->config['height']);
        if ($image === false) {
            header('HTTP/1.0 500 Internal Server Error');
            exit('Failed to create captcha image');
        }
        
        // 设置背景色
        $bgColor = imagecolorallocate($image, $this->config['bg_color'][0], $this->config['bg_color'][1], $this->config['bg_color'][2]);
        imagefill($image, 0, 0, $bgColor);
        
        // 添加噪点
        $this->addNoise($image);
        
        // 添加干扰线
        $this->addLines($image);
        
        // 绘制验证码文字
        $this->drawText($image, $code);
        
        // 输出图片（确保 Clean Output）
        header('Content-Type: image/png');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        imagepng($image);
        imagedestroy($image);
        exit;
    }
    
    /**
     * 添加噪点
     * @param resource $image 画布资源
     */
    private function addNoise($image) {
        // 检查是否启用噪点
        if (isset($this->config['show_noise']) && !$this->config['show_noise']) {
            return;
        }
        
        for ($i = 0; $i < $this->config['noise_level'] * 50; $i++) {
            $color = imagecolorallocate($image, rand(0, 255), rand(0, 255), rand(0, 255));
            imagesetpixel($image, rand(0, $this->config['width']), rand(0, $this->config['height']), $color);
        }
    }
    
    /**
     * 添加干扰线
     * @param resource $image 画布资源
     */
    private function addLines($image) {
        // 检查是否启用干扰线
        if (isset($this->config['show_lines']) && !$this->config['show_lines']) {
            return;
        }
        
        for ($i = 0; $i < $this->config['line_count']; $i++) {
            $color = imagecolorallocate($image, rand(0, 200), rand(0, 200), rand(0, 200));
            imageline($image, rand(0, $this->config['width']), rand(0, $this->config['height']), rand(0, $this->config['width']), rand(0, $this->config['height']), $color);
        }
    }
    
    /**
     * 绘制验证码文字
     * @param resource $image 画布资源
     * @param string $code 验证码字符串
     */
    private function drawText($image, $code) {
        $length = strlen($code);
        $codeWidth = $this->config['width'] / $length;
        
        for ($i = 0; $i < $length; $i++) {
            // 随机字体大小
            $fontSize = rand($this->config['font_size'] - 5, $this->config['font_size'] + 5);
            
            // 随机文字颜色
            $textColor = imagecolorallocate($image, rand(0, 150), rand(0, 150), rand(0, 150));
            
            // 固定角度，不旋转文字
            $angle = 0;
            
            // 计算文字位置（考虑字体大小，避免被边框遮住）
            $x = $i * $codeWidth + rand(5, 10);
            // 调整Y坐标，确保文字垂直居中且不被底部边框遮住
            $y = $this->config['height'] / 2 + $fontSize / 3 + rand(-5, 5);
            
            // 绘制文字
            if (!empty($this->config['font_file']) && file_exists($this->config['font_file'])) {
                imagettftext($image, $fontSize, $angle, $x, $y, $textColor, $this->config['font_file'], $code[$i]);
            } else {
                // GD 内置字体只支持 1-5 号，映射：fontSize < 16 用3，否则用5
                $gdFont = ($fontSize < 16) ? 3 : 5;
                imagestring($image, $gdFont, $x, $y - $fontSize, $code[$i], $textColor);
            }
        }
    }
    
    /**
     * 验证验证码
     * @param string $code 用户输入的验证码
     * @param bool $clearAfterVerify 是否在验证后清除验证码（默认true）
     * @return bool 是否验证通过
     */
    public function verify($code, $clearAfterVerify = true) {
        // 检查会话中是否有验证码
        if (!isset($_SESSION[$this->config['session_key']])) {
            return false;
        }
        
        $captcha = $_SESSION[$this->config['session_key']];
        
        // 检查验证码是否过期
        if (time() > $captcha['expire']) {
            $this->clear();
            return false;
        }
        
        // 检查验证码是否匹配
        $result = strtolower($code) === strtolower($captcha['code']);
        
        // 验证后清除验证码
        if ($clearAfterVerify) {
            $this->clear();
        }
        
        return $result;
    }
    
    /**
     * 清除验证码
     */
    public function clear() {
        if (isset($_SESSION[$this->config['session_key']])) {
            unset($_SESSION[$this->config['session_key']]);
        }
    }
    
    /**
     * 获取当前验证码配置
     * @return array 配置数组
     */
    public function getConfig() {
        return $this->config;
    }
    
    /**
     * 设置验证码配置
     * @param array $config 配置数组
     */
    public function setConfig($config) {
        $this->config = array_merge($this->config, $config);
        return $this;
    }
    
    /**
     * 静态方法：生成验证码图片
     * @param array $config 配置数组
     */
    public static function create($config = []) {
        $captcha = new self($config);
        $captcha->createImage();
    }
    
    /**
     * 静态方法：验证验证码
     * @param string $code 用户输入的验证码
     * @param array $config 配置数组
     * @param bool $clearAfterVerify 是否在验证后清除验证码（默认true）
     * @return bool 是否验证通过
     */
    public static function check($code, $config = [], $clearAfterVerify = true) {
        $captcha = new self($config);
        return $captcha->verify($code, $clearAfterVerify);
    }
    
    /**
     * 获取验证码配置选项
     * @return array 配置选项
     */
    public static function getConfigOptions() {
        return [
            'types' => [
                'mixed' => '字母数字混合',
                'number' => '纯数字',
                'letter' => '纯字母'
            ],
            'length' => [4, 5, 6],
            'noise_level' => [1 => '低', 2 => '中', 3 => '高', 4 => '很高', 5 => '极高'],
            'line_count' => [1, 2, 3, 4, 5]
        ];
    }
}
