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
 * BlogKit UI Components
 * 统一的PHP UI组件库
 */

class ProgressBar {
    public static function render($percent, $options = []) {
        $color = $options['color'] ?? 'primary';
        $size = $options['size'] ?? 'md';
        $showLabel = $options['showLabel'] ?? true;
        $label = $options['label'] ?? '';
        
        return "<div class=\"progress progress-$size\">
            " . ($label ? "<div class=\"progress-label\">$label</div>" : "") . "
            <div class=\"progress-bar\">
                <div class=\"progress-fill bg-$color\" style=\"width: $percent%\"></div>
            </div>
            " . ($showLabel ? "<div class=\"progress-percent\">$percent%</div>" : "") . "
        </div>";
    }
}

class Tabs {
    public static function render($tabs, $activeTab = 0) {
        $html = '<div class="tabs">';
        $html .= '<div class="tabs-header">';
        foreach ($tabs as $index => $tab) {
            $active = $index == $activeTab ? ' active' : '';
            $html .= "<button class=\"tab-item$active\" data-tab=\"$index\">{$tab['label']}</button>";
        }
        $html .= '</div>';
        
        $html .= '<div class="tabs-content">';
        foreach ($tabs as $index => $tab) {
            $active = $index == $activeTab ? ' active' : '';
            $html .= "<div class=\"tab-panel$active\">{$tab['content']}</div>";
        }
        $html .= '</div></div>';
        
        return $html;
    }
}

class Accordion {
    public static function render($items, $openIndex = -1) {
        $html = '<div class="accordion">';
        foreach ($items as $index => $item) {
            $open = $index == $openIndex ? ' open' : '';
            $html .= "
                <div class=\"accordion-item$open\">
                    <button class=\"accordion-header\" data-index=\"$index\">
                        <span class=\"accordion-title\">{$item['title']}</span>
                        <span class=\"accordion-icon\">▼</span>
                    </button>
                    <div class=\"accordion-content\">
                        {$item['content']}
                    </div>
                </div>";
        }
        $html .= '</div>';
        return $html;
    }
}

class Card {
    public static function render($options = []) {
        $title = $options['title'] ?? '';
        $content = $options['content'] ?? '';
        $footer = $options['footer'] ?? '';
        $style = $options['style'] ?? '';
        $hover = $options['hover'] ? ' card-hover' : '';
        
        return "<div class=\"card $style$hover\">
            " . ($title ? "<div class=\"card-header\">$title</div>" : "") . "
            <div class=\"card-body\">$content</div>
            " . ($footer ? "<div class=\"card-footer\">$footer</div>" : "") . "
        </div>";
    }
}

class Badge {
    public static function render($text, $type = 'default', $options = []) {
        $size = $options['size'] ?? 'md';
        $pill = $options['pill'] ? ' badge-pill' : '';
        $href = $options['href'] ?? '';
        
        $badge = "<span class=\"badge badge-$type badge-$size$pill\">$text</span>";
        
        if ($href) {
            return "<a href=\"" . htmlspecialchars($href) . "\" class=\"badge-link\">$badge</a>";
        }
        return $badge;
    }
}

class Steps {
    public static function render($steps, $currentStep = 0) {
        $html = '<div class="steps">';
        foreach ($steps as $index => $step) {
            $active = $index === $currentStep ? ' active' : '';
            $completed = $index < $currentStep ? ' completed' : '';
            $disabled = $index > $currentStep ? ' disabled' : '';

            // 分步条内部结构：完成步骤显示对勾、未完成显示序号。
            // 注意：三元表达式不能放进 {$...} 字符串插值（PHP 不支持，会整体解析报错），
            // 必须先在外部求值，再以普通变量插入。
            $stepInner = $completed ? '<span class="step-check">✓</span>' : ($index + 1);
            $html .= "<div class=\"step$active$completed$disabled\">
                <div class=\"step-number\">{$stepInner}</div>
                <div class=\"step-label\">{$step['label']}</div>
                " . ($index < count($steps) - 1 ? "<div class=\"step-line\"></div>" : "") . "
            </div>";
        }
        $html .= '</div>';
        return $html;
    }
}

class Tree {
    public static function render($nodes, $level = 0) {
        $indent = $level > 0 ? ' style="padding-left: ' . ($level * 20) . 'px"' : '';
        $html = '<ul class="tree"' . $indent . '>';
        foreach ($nodes as $node) {
            $hasChildren = !empty($node['children']);
            $checked = $node['checked'] ?? false;
            $disabled = $node['disabled'] ?? false;

            // 三元与 ?? 表达式不能放进 {$...} 字符串插值（PHP 不支持，会整体解析报错），
            // 必须先在外部求值，再以普通变量插入。
            $toggle = $hasChildren ? '▶' : '';
            $checkbox = '';
            if (!empty($node['checkbox'])) {
                $checkbox = "<input type=\"checkbox\" " . ($checked ? 'checked' : '') . ($disabled ? 'disabled' : '') . ">";
            }
            $icon = $node['icon'] ?? '📁';

            $html .= "<li class=\"tree-node\">
                <div class=\"tree-item\" data-id=\"{$node['id']}\">
                    <span class=\"tree-toggle\">{$toggle}</span>
                    {$checkbox}
                    <span class=\"tree-icon\">{$icon}</span>
                    <span class=\"tree-label\">{$node['label']}</span>
                </div>";
            
            if ($hasChildren) {
                $html .= self::render($node['children'], $level + 1);
            }
            $html .= '</li>';
        }
        $html .= '</ul>';
        return $html;
    }
}

class Skeleton {
    public static function text($lines = 3) {
        $html = '<div class="skeleton">';
        for ($i = 0; $i < $lines; $i++) {
            $width = $i === 0 ? '70%' : ($i === $lines - 1 ? '50%' : '100%');
            $html .= "<div class=\"skeleton-line\" style=\"width: $width\"></div>";
        }
        $html .= '</div>';
        return $html;
    }
    
    public static function card() {
        return '<div class="skeleton-card">
            <div class="skeleton-header">
                <div class="skeleton-avatar"></div>
                <div class="skeleton-info">
                    <div class="skeleton-line" style="width: 60%"></div>
                    <div class="skeleton-line" style="width: 40%"></div>
                </div>
            </div>
            <div class="skeleton-body">
                <div class="skeleton-line"></div>
                <div class="skeleton-line"></div>
                <div class="skeleton-line" style="width: 80%"></div>
            </div>
        </div>';
    }
    
    public static function table($rows = 3, $columns = 4) {
        $html = '<div class="skeleton-table">';
        for ($i = 0; $i < $rows; $i++) {
            $html .= '<div class="skeleton-row">';
            for ($j = 0; $j < $columns; $j++) {
                $width = ($j % 2 === 0) ? '60%' : '80%';
                $html .= "<div class=\"skeleton-cell\" style=\"width: $width\"></div>";
            }
            $html .= '</div>';
        }
        $html .= '</div>';
        return $html;
    }
}

class ListComponent {
    public static function render($items, $options = []) {
        $style = $options['style'] ?? '';
        $ordered = $options['ordered'] ?? false;
        $tag = $ordered ? 'ol' : 'ul';
        
        $html = "<$tag class=\"list list-$style\">";
        foreach ($items as $item) {
            $icon = $item['icon'] ?? '';
            $badge = $item['badge'] ? Badge::render($item['badge']['text'], $item['badge']['type'] ?? 'default') : '';
            $html .= "<li class=\"list-item\">" . ($icon ? "<span class=\"list-icon\">$icon</span>" : "") . "<span class=\"list-text\">{$item['content']}</span>$badge</li>";
        }
        $html .= "</$tag>";
        return $html;
    }
}

class Gauge {
    public static function render($value, $max = 100, $options = []) {
        $percentage = min(($value / $max) * 100, 100);
        $angle = ($percentage / 100) * 180 - 90;
        $label = $options['label'] ?? '';
        
        return "<div class=\"gauge\">
            <svg viewBox=\"0 0 200 120\" class=\"gauge-svg\">
                <path class=\"gauge-arc\" d=\"M 20 110 A 80 80 0 0 1 180 110\" />
                <path class=\"gauge-fill\" d=\"M 20 110 A 80 80 0 0 1 " . self::getPoint($percentage) . "\" />
                <line class=\"gauge-pointer\" x1=\"100\" y1=\"110\" x2=\"100\" y2=\"30\" 
                       transform=\"rotate($angle, 100, 110)\" />
                <circle class=\"gauge-center\" cx=\"100\" cy=\"110\" r=\"6\" />
            </svg>
            <div class=\"gauge-value\">$value</div>
            <div class=\"gauge-label\">$label</div>
        </div>";
    }
    
    private static function getPoint($percentage) {
        $angle = ($percentage / 100) * 180 - 90;
        $radian = deg2rad($angle);
        $x = 100 + 80 * cos($radian);
        $y = 110 + 80 * sin($radian);
        return "$x $y";
    }
}
