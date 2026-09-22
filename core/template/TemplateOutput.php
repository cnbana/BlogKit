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
 * BlogKit 模板输出辅助组件
 * 
 * 从 Template.php 中提取的输出相关功能：
 *   - 面包屑导航渲染
 *   - 分页信息/导航渲染
 *   - 调试信息渲染
 *   - CSRF字段生成
 * 
 * @package BlogKit
 * @since 2.2.0
 */
class TemplateOutput
{
    /**
     * @var Template 模板引擎主实例引用
     */
    private $template;

    /**
     * 构造函数
     * 
     * @param Template $template 模板引擎实例
     */
    public function __construct($template)
    {
        $this->template = $template;
    }

    /**
     * 渲染面包屑导航
     * @return string 面包屑HTML
     */
    public function renderBreadcrumb()
    {
        $siteConfig = $this->template->getSiteConfig();
        // 多语言已从核心移除，这里直接用硬编码中文
        // 若启用多语言插件，插件可通过 Template::getInstance()->assign('breadcrumb_home', 'xxx') 注入覆盖
        $homeText = $this->template->getData()['breadcrumb_home'] ?? '首页';
        if (empty($homeText)) $homeText = '首页';
        
        $html = '<nav class="breadcrumb"><a href="' . $siteConfig['url'] . '">' . $homeText . '</a>';
        
        $breadcrumb = $this->template->getBreadcrumb();
        if (!empty($breadcrumb)) {
            foreach ($breadcrumb as $item) {
                $html .= '<span class="separator">/</span>';
                if (isset($item['url'])) {
                    $html .= '<a href="' . $item['url'] . '">' . htmlspecialchars($item['name']) . '</a>';
                } else {
                    $html .= '<span class="current">' . htmlspecialchars($item['name']) . '</span>';
                }
            }
        }
        
        $html .= '</nav>';
        return $html;
    }

    /**
     * 渲染分页信息
     * @return string 分页信息HTML
     */
    public function renderPaginationInfo()
    {
        $pagination = $this->template->getPagination();
        if (!$pagination) {
            return '';
        }
        
        $html = '<div class="pagination-info">';
        $html .= '共 ' . $pagination->getTotal() . ' 条记录，';
        $html .= '第 ' . $pagination->getCurrentPage() . ' / ' . $pagination->getTotalPages() . ' 页';
        $html .= '</div>';
        
        return $html;
    }

    /**
     * 渲染分页
     * @return string 分页HTML
     */
    public function renderPagination()
    {
        $pagination = $this->template->getPagination();
        if (!$pagination) {
            return '';
        }
        
        return $pagination->createLinks();
    }

    /**
     * 渲染调试信息
     * @return string 调试信息的HTML
     */
    public function renderDebugInfo()
    {
        $errorLog = $this->template->getErrorLog();
        
        if (!$this->template->getDebugMode() || empty($errorLog)) {
            return '';
        }
        
        $html = '<div class="template-debug" style="background: #f8f9fa; border: 1px solid #dee2e6; padding: 10px; margin: 10px 0; font-family: monospace; font-size: 12px;">';
        $html .= '<h4>Template Debug Information</h4>';
        $html .= '<table style="width: 100%; border-collapse: collapse;">';
        $html .= '<tr><th style="text-align: left; border-bottom: 1px solid #dee2e6; padding: 5px;">Time</th><th style="text-align: left; border-bottom: 1px solid #dee2e6; padding: 5px;">Level</th><th style="text-align: left; border-bottom: 1px solid #dee2e6; padding: 5px;">Template</th><th style="text-align: left; border-bottom: 1px solid #dee2e6; padding: 5px;">Message</th></tr>';
        
        foreach ($errorLog as $error) {
            $levelClass = '';
            switch ($error['level']) {
                case 'error':
                    $levelClass = 'color: #dc3545;';
                    break;
                case 'warning':
                    $levelClass = 'color: #ffc107;';
                    break;
                case 'info':
                    $levelClass = 'color: #17a2b8;';
                    break;
            }
            
            $html .= '<tr>';
            $html .= '<td style="padding: 5px; border-bottom: 1px solid #dee2e6;">' . $error['time'] . '</td>';
            $html .= '<td style="padding: 5px; border-bottom: 1px solid #dee2e6; ' . $levelClass . '">' . $error['level'] . '</td>';
            $html .= '<td style="padding: 5px; border-bottom: 1px solid #dee2e6;">' . $error['template'] . '</td>';
            $html .= '<td style="padding: 5px; border-bottom: 1px solid #dee2e6;">' . htmlspecialchars($error['message']) . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</table>';
        $html .= '</div>';
        
        return $html;
    }

    /**
     * 获取CSRF令牌字段
     * @return string CSRF令牌HTML输入字段
     */
    public function getCsrfField()
    {
        if (class_exists('Security')) {
            return Security::getCsrfField();
        }
        return '';
    }
}
