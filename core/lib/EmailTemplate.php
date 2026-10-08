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
 * 邮件模板渲染类
 * 用于渲染邮件模板，替换变量
 */
class EmailTemplate {
    /**
     * 渲染模板
     * @param array $template 模板信息
     * @param array $variables 变量数据
     * @return array 渲染后的邮件信息（包含subject和content）
     */
    public static function render($template, $variables = []) {
        if (!is_array($template) || !isset($template['subject']) || !isset($template['content'])) {
            return [
                'subject' => '',
                'content' => ''
            ];
        }
        
        return [
            'subject' => self::replaceVariables($template['subject'], $variables),
            'content' => self::replaceVariables($template['content'], $variables)
        ];
    }
    
    /**
     * 替换模板变量
     * @param string $content 模板内容
     * @param array $variables 变量数据
     * @return string 替换后的内容
     */
    public static function replaceVariables($content, $variables = []) {
        if (empty($variables) || !is_array($variables)) {
            return $content;
        }
        
        // 替换变量
        foreach ($variables as $key => $value) {
            $content = str_replace('{' . $key . '}', $value, $content);
        }
        
        return $content;
    }
    
    /**
     * 加载模板
     * @param string $type 模板类型
     * @return array|null 模板信息
     */
    public static function loadTemplate($type) {
        // 确保加载了EmailTemplateModel类
        if (!class_exists('EmailTemplateModel')) {
            require APP_PATH . '/Models/EmailTemplateModel.php';
        }
        
        $templateModel = new EmailTemplateModel();
        $template = $templateModel->getTemplateByType($type);
        
        if ($template) {
            return $template;
        }
        
        // 如果没有找到模板，返回默认模板
        return self::getDefaultTemplate($type);
    }
    
    /**
     * 获取默认模板
     * @param string $type 模板类型
     * @return array|null 默认模板
     */
    public static function getDefaultTemplate($type) {
        $defaultTemplates = [
            'registration' => [
                'name' => '注册确认',
                'type' => 'registration',
                'subject' => '欢迎注册 {site_name}',
                'content' => '<html><body>
                    <h2>欢迎注册 {site_name}！</h2>
                    <p>尊敬的 {username}：</p>
                    <p>感谢您注册我们的网站！</p>
                    <p>请点击以下链接确认您的注册：</p>
                    <p><a href="{confirm_link}" target="_blank">{confirm_link}</a></p>
                    <p>如果您没有注册我们的网站，请忽略此邮件。</p>
                    <p>--<br>{site_name} 团队</p>
                    </body></html>',
                'is_default' => 1,
                'status' => 1
            ],
            'password_reset' => [
                'name' => '密码重置',
                'type' => 'password_reset',
                'subject' => '{site_name} 密码重置请求',
                'content' => '<html><body>
                    <h2>密码重置请求</h2>
                    <p>尊敬的 {username}：</p>
                    <p>您收到这封邮件是因为您请求重置密码。</p>
                    <p>请点击以下链接重置您的密码：</p>
                    <p><a href="{reset_link}" target="_blank">{reset_link}</a></p>
                    <p>如果您没有请求重置密码，请忽略此邮件。</p>
                    <p>此链接将在24小时后过期。</p>
                    <p>--<br>{site_name} 团队</p>
                    </body></html>',
                'is_default' => 1,
                'status' => 1
            ],
            'comment_notify' => [
                'name' => '评论通知',
                'type' => 'comment_notify',
                'subject' => '您的文章收到了新评论',
                'content' => '<html><body>
                    <h2>您的文章收到了新评论</h2>
                    <p>尊敬的 {username}：</p>
                    <p>您的文章 <a href="{article_link}" target="_blank">{article_title}</a> 收到了新评论。</p>
                    <p><strong>评论内容：</strong></p>
                    <p>{comment_content}</p>
                    <p>--<br>{site_name} 团队</p>
                    </body></html>',
                'is_default' => 1,
                'status' => 1
            ],
            'article_approve' => [
                'name' => '文章审核通知',
                'type' => 'article_approve',
                'subject' => '您的文章已审核',
                'content' => '<html><body>
                    <h2>您的文章已审核</h2>
                    <p>尊敬的 {username}：</p>
                    <p>您的文章 <a href="{article_link}" target="_blank">{article_title}</a> 已审核。</p>
                    <p><strong>审核结果：</strong>{status_text}</p>
                    <p><strong>审核意见：</strong>{remark}</p>
                    <p>--<br>{site_name} 团队</p>
                    </body></html>',
                'is_default' => 1,
                'status' => 1
            ],
            'admin_notify' => [
                'name' => '管理员通知',
                'type' => 'admin_notify',
                'subject' => '{site_name} 管理员通知',
                'content' => '<html><body>
                    <h2>管理员通知</h2>
                    <p>尊敬的管理员：</p>
                    <p>{message_content}</p>
                    <p>--<br>{site_name} 系统</p>
                    </body></html>',
                'is_default' => 1,
                'status' => 1
            ],
            'user_notify' => [
                'name' => '用户通知',
                'type' => 'user_notify',
                'subject' => '{site_name} 通知',
                'content' => '<html><body>
                    <h2>系统通知</h2>
                    <p>尊敬的 {username}：</p>
                    <p>{message_content}</p>
                    <p>--<br>{site_name} 团队</p>
                    </body></html>',
                'is_default' => 1,
                'status' => 1
            ],
            'email_verification' => [
                'name' => '邮箱验证',
                'type' => 'email_verification',
                'subject' => '邮箱验证 - {site_name}',
                'content' => '<html><body>
                    <h2>邮箱验证</h2>
                    <p>尊敬的 {username}：</p>
                    <p>您正在尝试修改邮箱地址，您的验证码是：</p>
                    <p style="font-size: 24px; font-weight: bold; padding: 20px; background-color: #f5f5f5; margin: 20px 0;">{verification_code}</p>
                    <p>此验证码将在30分钟后过期，请及时使用。</p>
                    <p>如果您没有操作，请忽略此邮件。</p>
                    <p>--<br>{site_name} 团队</p>
                    </body></html>',
                'is_default' => 1,
                'status' => 1
            ]
        ];
        
        return isset($defaultTemplates[$type]) ? $defaultTemplates[$type] : null;
    }
    
    /**
     * 预览模板
     * @param array $template 模板信息
     * @param array $variables 变量数据
     * @return array 预览结果
     */
    public static function previewTemplate($template, $variables = []) {
        // 如果没有提供变量，使用默认测试变量
        if (empty($variables)) {
            $variables = self::getTestVariables($template['type']);
        }
        
        return self::render($template, $variables);
    }
    
    /**
     * 获取测试变量
     * @param string $type 模板类型
     * @return array 测试变量
     */
    private static function getTestVariables($type) {
        $siteUrl = Config::get('site_url', 'http://localhost');
        $siteName = Config::get('site_name', 'BlogKit');
        
        $commonVariables = [
            'site_name' => $siteName,
            'site_url' => $siteUrl,
            'username' => 'test_user',
            'email' => 'test@example.com'
        ];
        
        $typeVariables = [
            'registration' => [
                'confirm_link' => $siteUrl . '/confirm?token=test_token',
                'register_time' => date('Y-m-d H:i:s')
            ],
            'password_reset' => [
                'reset_link' => $siteUrl . '/reset?token=test_token',
                'request_time' => date('Y-m-d H:i:s')
            ],
            'comment_notify' => [
                'article_title' => '测试文章标题',
                'article_link' => $siteUrl . '/article/1',
                'comment_content' => '这是一条测试评论内容',
                'comment_time' => date('Y-m-d H:i:s')
            ],
            'article_approve' => [
                'article_title' => '测试文章标题',
                'article_link' => $siteUrl . '/article/1',
                'status_text' => '已通过',
                'remark' => '文章内容优质，审核通过',
                'approve_time' => date('Y-m-d H:i:s')
            ],
            'admin_notify' => [
                'message_content' => '这是一条测试管理员通知',
                'notify_time' => date('Y-m-d H:i:s')
            ],
            'user_notify' => [
                'message_content' => '这是一条测试用户通知',
                'notify_time' => date('Y-m-d H:i:s')
            ],
            'email_verification' => [
                'verification_code' => '123456',
                'verification_time' => date('Y-m-d H:i:s')
            ]
        ];
        
        $variables = $commonVariables;
        if (isset($typeVariables[$type])) {
            $variables = array_merge($variables, $typeVariables[$type]);
        }
        
        return $variables;
    }
}
