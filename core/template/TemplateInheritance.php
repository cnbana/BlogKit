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
 * BlogKit 模板继承系统组件
 * 
 * 从 Template.php 中提取的模板继承机制：
 *   - {extend file="xxx"} 父模板继承
 *   - {block name="xxx"} 块定义与嵌套
 *   - {/block} 块闭合
 *   - 块优先级、递归替换
 * 
 * @package BlogKit
 * @since 2.2.0
 */
class TemplateInheritance
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
     * 解析模板继承
     * @param string $content 模板内容
     * @param string $currentFile 当前模板文件路径
     * @return string 解析后的模板内容
     */
    public function parseInheritance($content, $currentFile)
    {
        // 查找继承标签 {extend file="xxx"}
        if (preg_match('/\{extend\s+file="([^"]+)"\s*\}/', $content, $matches)) {
            $extendFile = $matches[1];
            // 加载父模板原始内容（不进行解析）
            $parentContent = $this->template->loadTemplate($extendFile, true);
            
            // 提取当前模板的所有块定义（支持嵌套块和优先级）
            $this->extractBlocks($content);
            
            // 替换父模板中的块内容（支持嵌套块）
            $resultContent = $this->replaceBlocks($parentContent);
            
            return $resultContent;
        }
        
        // 如果没有继承标签，直接解析模板中的块
        return $this->parseBlocks($content);
    }

    /**
     * 提取模板中的所有块定义，支持嵌套块和优先级
     * @param string $content 模板内容
     */
    public function extractBlocks($content)
    {
        $blocks = $this->template->blocks();
        $debugMode = $this->template->getDebugMode();

        if ($debugMode) {
            echo "<!-- extractBlocks called with content: \n" . htmlspecialchars($content) . " -->\n";
        }
        
        $offset = 0;
        while (($startPos = strpos($content, '{block', $offset)) !== false) {
            // 查找块定义的结束位置
            $endDefPos = strpos($content, '}', $startPos);
            if ($endDefPos === false) {
                break;
            }
            
            // 提取块定义
            $blockDef = substr($content, $startPos, $endDefPos - $startPos + 1);
            
            // 解析块名称和优先级
            preg_match('/\{block\s+name="([^"]+)"\s*(?:priority="([0-9]+)")?\s*\}/', $blockDef, $defMatches);
            if (empty($defMatches)) {
                $offset = $endDefPos + 1;
                continue;
            }
            
            $blockName = $defMatches[1];
            $priority = isset($defMatches[2]) ? (int)$defMatches[2] : 0;
            
            // 查找块的结束标签 {/block}
            $blockContentStart = $endDefPos + 1;
            $blockLevel = 1;
            $currentPos = $blockContentStart;
            $blockContentEnd = $blockContentStart;
            
            // 递归查找匹配的结束标签，处理嵌套块
            while ($currentPos < strlen($content)) {
                $nextStartPos = strpos($content, '{block', $currentPos);
                $nextEndPos = strpos($content, '{/block}', $currentPos);
                
                if ($nextStartPos === false && $nextEndPos === false) {
                    break;
                }
                
                $foundStart = $nextStartPos !== false;
                $foundEnd = $nextEndPos !== false;
                
                if ($foundStart && (!$foundEnd || $nextStartPos < $nextEndPos)) {
                    $blockLevel++;
                    $currentPos = $nextStartPos + 6;
                } else {
                    $blockLevel--;
                    if ($blockLevel == 0) {
                        $blockContentEnd = $nextEndPos;
                        break;
                    }
                    $currentPos = $nextEndPos + 8;
                }
            }
            
            // 提取块内容（包括嵌套块）
            $blockContent = substr($content, $blockContentStart, $blockContentEnd - $blockContentStart);
            
            // 先存储块内容，考虑优先级
            if (!isset($blocks[$blockName]) || $priority > $blocks[$blockName]['priority']) {
                $blocks[$blockName] = [
                    'content' => $blockContent,
                    'priority' => $priority
                ];
            } elseif ($priority == $blocks[$blockName]['priority']) {
                $blocks[$blockName]['content'] = $blockContent;
            }
            
            // 递归处理嵌套块
            $this->template->setBlocks($blocks);
            $this->extractBlocks($blockContent);
            $blocks = $this->template->blocks();
            
            // 更新偏移量
            $offset = $blockContentEnd + 8;
        }
        
        $this->template->setBlocks($blocks);
    }

    /**
     * 递归替换父模板中的块内容，支持嵌套块
     * @param string $content 父模板内容
     * @param array $processedBlocks 已处理的块名称数组
     * @return string 替换后的模板内容
     */
    public function replaceBlocks($content, $processedBlocks = [])
    {
        $blocks = $this->template->blocks();
        $result = '';
        $offset = 0;
        
        while (($startPos = strpos($content, '{block', $offset)) !== false) {
            $result .= substr($content, $offset, $startPos - $offset);
            
            $endDefPos = strpos($content, '}', $startPos);
            if ($endDefPos === false) {
                $result .= substr($content, $startPos);
                break;
            }
            
            $blockDef = substr($content, $startPos, $endDefPos - $startPos + 1);
            
            preg_match('/\{block\s+name="([^"]+)"\s*(?:priority="([0-9]+)")?\s*\}/', $blockDef, $defMatches);
            if (empty($defMatches)) {
                $result .= $blockDef;
                $offset = $endDefPos + 1;
                continue;
            }
            
            $blockName = $defMatches[1];
            
            // 如果已经处理过，跳过
            if (in_array($blockName, $processedBlocks)) {
                $blockLevel = 1;
                $currentPos = $endDefPos + 1;
                $blockEndPos = $currentPos;
                
                while ($currentPos < strlen($content)) {
                    $nextStartPos = strpos($content, '{block', $currentPos);
                    $nextEndPos = strpos($content, '{/block}', $currentPos);
                    
                    if ($nextStartPos === false && $nextEndPos === false) break;
                    
                    if ($nextStartPos !== false && ($nextEndPos === false || $nextStartPos < $nextEndPos)) {
                        $blockLevel++;
                        $currentPos = $nextStartPos + 6;
                    } else {
                        $blockLevel--;
                        if ($blockLevel == 0) {
                            $blockEndPos = $nextEndPos + 8;
                            break;
                        }
                        $currentPos = $nextEndPos + 8;
                    }
                }
                
                $offset = $blockEndPos;
                continue;
            }
            
            $processedBlocks[] = $blockName;
            
            // 查找匹配的结束标签
            $blockLevel = 1;
            $currentPos = $endDefPos + 1;
            $blockEndPos = $currentPos;
            
            while ($currentPos < strlen($content)) {
                $nextStartPos = strpos($content, '{block', $currentPos);
                $nextEndPos = strpos($content, '{/block}', $currentPos);
                
                if ($nextStartPos === false && $nextEndPos === false) break;
                
                if ($nextStartPos !== false && ($nextEndPos === false || $nextStartPos < $nextEndPos)) {
                    $blockLevel++;
                    $currentPos = $nextStartPos + 6;
                } else {
                    $blockLevel--;
                    if ($blockLevel == 0) {
                        $blockEndPos = $nextEndPos + 8;
                        break;
                    }
                    $currentPos = $nextEndPos + 8;
                }
            }
            
            if ($blockLevel != 0) {
                $result .= $blockDef;
                $offset = $endDefPos + 1;
                continue;
            }
            
            // 提取块内容
            $blockContent = substr($content, $endDefPos + 1, $blockEndPos - $endDefPos - 8);
            
            // 替换块内容
            if (isset($blocks[$blockName])) {
                $newContent = $blocks[$blockName]['content'];
                $newContent = $this->replaceBlocks($newContent, $processedBlocks);
            } else {
                $newContent = $this->replaceBlocks($blockContent, $processedBlocks);
            }
            
            $result .= $newContent;
            $offset = $blockEndPos;
        }
        
        $result .= substr($content, $offset);
        
        return $result;
    }

    /**
     * 解析模板块
     * @param string $content 模板内容
     * @return string 解析后的模板内容
     */
    public function parseBlocks($content)
    {
        // 每次解析前清除之前存储的块
        $this->template->setBlocks([]);
        // 提取块定义
        $this->extractBlocks($content);
        // 替换块内容
        return $this->replaceBlocks($content);
    }
}
