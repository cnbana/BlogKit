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
 * BlogKit 模板核心解析器组件
 * 
 * 从 Template.php 中提取的核心模板标签解析引擎，负责解析所有模板标签：
 *   - {cache} / {/cache}          区块缓存
 *   - {macro} / {/macro}          宏定义与调用
 *   - {assign var="x" value="y"}  变量赋值
 *   - {include file="xxx"}        模板包含
 *   - {year} / {current_lang}     内置变量
 *   - {csrf_field}                 CSRF令牌
 *   - {current_url}               当前URL
 *   - {site.xxx} / {config}       站点/配置变量
 *   - {plugin_hook}               插件钩子
 *   - {if ...} / {/if}            条件判断（多种变体）
 *   - {loop} / {/loop}            循环
 *   - {php} / {/php}              PHP代码
 *   - {field}                     字段输出
 *   - {url:xxx:yyy}               URL生成
 *   - {breadcrumb}                面包屑
 *   - {pagination} / {pagination:info}  分页
 *   - {func:xxx}                  函数调用
 *   - {top_badge}                 置顶标识
 *   - {variable|filter}           变量输出与过滤器
 * 
 * @package BlogKit
 * @since 2.2.0
 */
class TemplateParser
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
     * 解析模板内容（核心方法）
     * @param string $content 模板内容
     * @return string 解析后的内容
     */
    public function parse($content)
    {
        $content = $this->parseCacheBlocks($content);
        $content = $this->parseMacroDefinitions($content);
        $content = $this->parseAssignments($content);
        $content = $this->parseIncludeFiles($content);
        $content = $this->parseBuiltinVariables($content);
        // parseTranslationTags 已移除（多语言从核心移除，若启用多语言插件请在 {plugin_hook} 中注入）
        $content = $this->parseSecurityTags($content);
        $content = $this->parseUrlVariables($content);
        $content = $this->parseConfigTags($content);
        $content = $this->parsePluginHooks($content);
        $content = $this->parseMacroCalls($content);
        $content = $this->parseSimpleCondition($content);
        $content = $this->parseSiteConfig($content);
        $content = $this->parseIfConditions($content);
        $content = $this->parseLoops($content);
        $content = $this->parsePhpBlocks($content);
        $content = $this->parseFieldConditions($content);
        $content = $this->parseComplexCondition($content);
        $content = $this->parseInArrayCondition($content);
        $content = $this->parseIssetCondition($content);
        $content = $this->parseEmptyCondition($content);
        $content = $this->parseEqualCondition($content);
        $content = $this->parseVariableCompare($content);
        $content = $this->parseSimpleIf($content);
        $content = $this->parseEchoVariable($content);
        $content = $this->parseLoopsAgain($content);
        $content = $this->parseCount($content);
        $content = $this->parseFieldTags($content);
        $content = $this->parsePermissionTags($content);
        $content = $this->parseUrlTags($content);
        $content = $this->parseBreadcrumb($content);
        $content = $this->parsePagination($content);
        $content = $this->parseFunctionCalls($content);
        $content = $this->parsePictureTags($content);
        $content = $this->parseContentProcessTags($content);
        $content = $this->parseTopBadge($content);
        $content = $this->parseVariableOutput($content);
        
        // 处理模板块（放在最后，确保所有其他标签都已解析）
        $content = $this->template->inheritance()->parseBlocks($content);
        
        return $content;
    }

    // ==================== 区块缓存 ====================
    
    private function parseCacheBlocks($content)
    {
        return preg_replace_callback('/\{cache\s+ttl="(\d+)"\s*(?:key="([^"]+)")?\s*\}([\s\S]*?)\{\/cache\}/', function($matches) {
            $ttl = (int)$matches[1];
            $key = !empty($matches[2]) ? $matches[2] : 'block_' . md5($matches[3]);
            $blockContent = $matches[3];
            
            $cacheFile = STORAGE_PATH . '/cache/templates/' . md5($key) . '.html';
            
            if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
                return file_get_contents($cacheFile);
            }
            
            $rendered = $this->parse($blockContent);
            
            if (!is_dir(dirname($cacheFile))) {
                @mkdir(dirname($cacheFile), 0755, true);
            }
            file_put_contents($cacheFile, $rendered);
            
            return $rendered;
        }, $content);
    }

    // ==================== 宏定义/调用 ====================
    
    private function parseMacroDefinitions($content)
    {
        return preg_replace_callback('/\{macro\s+name="([^"]+)"\s*(?:params="([^"]+)")?\s*\}([\s\S]*?)\{\/macro\}/', function($matches) {
            $macroName = $matches[1];
            $paramsStr = isset($matches[2]) ? $matches[2] : '';
            $macroContent = $matches[3];
            
            $params = [];
            if (!empty($paramsStr)) {
                $paramList = explode(',', $paramsStr);
                foreach ($paramList as $param) {
                    $params[trim($param)] = '';
                }
            }
            
            $this->template->registerMacro($macroName, $macroContent, $params);
            return '';
        }, $content);
    }

    private function parseMacroCalls($content)
    {
        return preg_replace_callback('/\{macro\s+name="([^"]+)"\s*([\s\S]*?)\}/', function($matches) {
            $macroName = $matches[1];
            $attrsStr = $matches[2];
            $macros = $this->template->getMacros();
            
            if (!isset($macros[$macroName])) {
                return "<!-- Macro '{$macroName}' not found -->";
            }
            
            $macro = $macros[$macroName];
            $macroContent = $macro['content'];
            $macroParams = $macro['params'];
            
            $callParams = [];
            if (preg_match_all('/\s+([a-zA-Z0-9_]+)="([^"]+)"/', $attrsStr, $attrMatches)) {
                for ($i = 0; $i < count($attrMatches[1]); $i++) {
                    $callParams[$attrMatches[1][$i]] = $attrMatches[2][$i];
                }
            }
            
            $params = array_merge($macroParams, $callParams);
            
            $originalData = $this->template->getData();
            $originalLoopItem = $this->template->getCurrentLoopItem();
            $originalLoopData = $this->template->getCurrentLoopData();
            
            foreach ($params as $key => $value) {
                $this->template->dataSet($key, $value);
            }
            
            $parsedContent = $this->parse($macroContent);
            
            $this->template->dataRestore($originalData);
            $this->template->setCurrentLoopItem($originalLoopItem);
            $this->template->setCurrentLoopData($originalLoopData);
            
            return $parsedContent;
        }, $content);
    }

    // ==================== 变量赋值 ====================
    
    private function parseAssignments($content)
    {
        return preg_replace_callback('/\{assign\s+var=([\'"])(.+?)\1\s+value=([\'"])(.+?)\3\s*\}/s', function($matches) {
            $varName = $matches[2];
            $varValue = $matches[4];
            $parsedValue = $this->parse($varValue);
            $this->template->assign($varName, $parsedValue);
            return '';
        }, $content);
    }

    // ==================== 包含标签 ====================
    
    private function parseIncludeFiles($content)
    {
        $includePaths = $this->template->getIncludePaths();
        
        return preg_replace_callback('/\{include\s+file="([^"]+)"\s*\}/', function($matches) use ($includePaths) {
            $includeFileName = $matches[1];
            $cleanName = str_replace('.html', '', $includeFileName);
            
            $searchPatterns = [];
            if (strpos($cleanName, '/') !== false) {
                $searchPatterns[] = $cleanName;
                $searchPatterns[] = $cleanName . '.html';
            } else {
                $searchPatterns[] = $cleanName;
                $searchPatterns[] = $cleanName . '.html';
                $searchPatterns[] = 'common/' . $cleanName;
                $searchPatterns[] = 'common/' . $cleanName . '.html';
            }
            
            foreach ($includePaths as $path) {
                foreach ($searchPatterns as $pattern) {
                    $file = $path . $pattern;
                    if (file_exists($file)) {
                        $includeContent = file_get_contents($file);
                        return $this->parse($includeContent);
                    }
                }
            }
            
            return "<!-- Include file '{$includeFileName}' not found -->";
        }, $content);
    }

    // ==================== 内置变量 ====================
    
    private function parseBuiltinVariables($content)
    {
        $content = str_replace('{year}', date('Y'), $content);
        $content = str_replace('{current_lang}', $this->template->getCurrentLang(), $content);
        return $content;
    }

    // ==================== 安全标签 ====================
    
    private function parseSecurityTags($content)
    {
        $content = str_replace('{csrf_field}', $this->template->output()->getCsrfField(), $content);
        return $content;
    }

    // ==================== URL变量 ====================
    
    private function parseUrlVariables($content)
    {
        $siteConfig = $this->template->getSiteConfig();
        $currentUrl = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
        $fullUrl = $siteConfig['url'] . $currentUrl;
        $content = str_replace('{current_url}', $fullUrl, $content);
        
        $urlWithoutLang = $currentUrl;
        if (strpos($urlWithoutLang, '?') !== false) {
            $parts = parse_url($urlWithoutLang);
            parse_str($parts['query'], $params);
            unset($params['lang']);
            if (!empty($params)) {
                $urlWithoutLang = $parts['path'] . '?' . http_build_query($params);
            } else {
                $urlWithoutLang = $parts['path'];
            }
        }
        $content = str_replace('{current_url_without_lang}', $urlWithoutLang, $content);
        
        return $content;
    }

    // ==================== 配置/站点变量标签 ====================
    
    private function parseConfigTags($content)
    {
        // {site.xxx}
        $content = preg_replace_callback('/\{site\.([a-zA-Z0-9_]+)\}/', function($matches) {
            $siteConfig = $this->template->getSiteConfig();
            $key = $matches[1];
            return isset($siteConfig[$key]) ? $siteConfig[$key] : '';
        }, $content);
        
        // {config name="xxx"}
        $content = preg_replace_callback('/\{config\s+name="([^"]+)"\s*\}/', function($matches) {
            return Config::get($matches[1], '');
        }, $content);
        
        return $content;
    }

    private function parseSiteConfig($content)
    {
        // Already handled in parseConfigTags, keep for compatibility
        return $content;
    }

    // ==================== 插件钩子 ====================
    
    private function parsePluginHooks($content)
    {
        return preg_replace_callback('/\{plugin_hook\s+name="([^"]+)"\s*\}/', function($matches) {
            $hookName = $matches[1];
            if (class_exists('Hook')) {
                return Hook::trigger($hookName);
            }
            return '';
        }, $content);
    }

    // ==================== 条件判断（多种格式） ====================
    
    private function parseSimpleCondition($content)
    {
        // {if current_lang == 'zh-cn'}...{else}...{endif}
        return preg_replace_callback('/\{if\s+([a-zA-Z0-9_]+)\s*==\s*([\'"\w-]+)\s*\}([\s\S]*?)(?:\{else\}([\s\S]*?))?\{endif\}/', function($matches) {
            $variable = $matches[1];
            $value = str_replace(['"', "'"], '', $matches[2]);
            $ifContent = $matches[3];
            $elseContent = isset($matches[4]) ? $matches[4] : '';
            
            if ($variable === 'current_lang' && $this->template->getCurrentLang() === $value) {
                return $this->parse($ifContent);
            }
            return $this->parse($elseContent);
        }, $content);
    }

    private function parseIfConditions($content)
    {
        // {if condition="expression"}...{else}...{/if} （简单版无花括号）
        $content = preg_replace_callback('/\{if\s+condition\s*=\s*"([^"]+)"\s*\}([^\{\}]*?)\{else\}([^\{\}]*?)\{\/if\}/', function($matches) {
            $condition = $matches[1];
            if ($this->template->condition()->evaluate($condition)) {
                return $this->parse($matches[2]);
            }
            return $this->parse($matches[3]);
        }, $content);
        
        // {if condition="expression"}...{else}...{endif}
        $content = preg_replace_callback('/\{if\s+condition\s*=\s*"([^"]+)"\s*\}([^\{\}]*?)\{else\}([^\{\}]*?)\{endif\}/', function($matches) {
            $condition = $matches[1];
            if ($this->template->condition()->evaluate($condition)) {
                return $this->parse($matches[2]);
            }
            return $this->parse($matches[3]);
        }, $content);
        
        // {if condition="expression"}...{else}...{/if} (复杂版支持嵌套)
        $content = preg_replace_callback('/\{if\s+condition\s*=\s*"([^"]+)"\s*\}([\s\S]*?)(?:\{else\}([\s\S]*?))?(?:\{\/(?:if|endif)\}|\{endif\})/', function($matches) {
            $condition = $matches[1];
            if ($this->template->condition()->evaluate($condition)) {
                return $this->parse($matches[2]);
            }
            return $this->parse(isset($matches[3]) ? $matches[3] : '');
        }, $content);
        
        return $content;
    }

    /**
     * 解析 {if field='xxx'}...{else}...{/if} 或 {if !field='xxx'} 条件
     * 使用嵌套深度计数，确保匹配到正确的关闭标签
     * 支持单引号和双引号
     */
    private function parseFieldConditions($content)
    {
        // {if field='xxx'}...{else}...{/if} 或 {if !field='xxx'}...{else}...{/if}
        $offset = 0;
        while (preg_match(
            '/\{if\s+(!)?field\s*=\s*([\'"])([^\'"]+)\2\s*\}/',
            $content, $m, PREG_OFFSET_CAPTURE, $offset
        )) {
            $matchPos  = $m[0][1];
            $matchLen  = strlen($m[0][0]);
            $negate    = !empty($m[1][0]);
            $fieldName = $m[3][0];
            $bodyStart = $matchPos + $matchLen;

            list($body, $closeEnd) = $this->findMatchingCloseTag($content, $bodyStart);
            if ($closeEnd === false) { $offset = $bodyStart; break; }

            $fullBlockLen = $closeEnd - $matchPos;

            // 保护嵌套 {if} 块
            $markers = [];
            $bodyProtected = preg_replace_callback(
                '/\{if\s+.+?\}[\s\S]*?\{\/(?:if|endif)\}|\{endif\}/',
                function($nm) use (&$markers) {
                    $key = '__NESTF_' . count($markers) . '__';
                    $markers[$key] = $nm[0];
                    return $key;
                },
                $body
            );

            $ifContent = $bodyProtected;
            $elseContent = '';
            $elsePos = strpos($bodyProtected, '{else}');
            if ($elsePos !== false) {
                $ifContent   = substr($bodyProtected, 0, $elsePos);
                $elseContent = substr($bodyProtected, $elsePos + 6);
            }

            $restore = function($s) use ($markers) {
                return str_replace(array_keys($markers), array_values($markers), $s);
            };

            $value = $this->resolveFieldValue($fieldName);
            if (($negate && !$value) || (!$negate && $value)) {
                $rendered = $this->parse($restore($ifContent));
            } else {
                $rendered = $this->parse($restore($elseContent));
            }

            $content = substr_replace($content, $rendered, $matchPos, $fullBlockLen);
            $offset = $matchPos;
        }

        // {if field="x" == value}...{elseif}...{else}...{/if}
        $content = $this->parseFieldCompareWithElseif($content);

        return $content;
    }

    /**
     * 解析 {if field='xxx' == value}...{elseif}...{else}...{/if}
     * 使用嵌套深度计数，支持嵌套 if 块
     */
    private function parseFieldCompareWithElseif($content)
    {
        $offset = 0;
        while (preg_match(
            '/\{if\s+field\s*=\s*([\'"])([a-zA-Z0-9_]+)\1\s*([<>=!]=?|<>|<=|>=)\s*([\'"]?)([\w.]+)\4\s*\}/',
            $content, $m, PREG_OFFSET_CAPTURE, $offset
        )) {
            $matchPos  = $m[0][1];
            $matchLen  = strlen($m[0][0]);
            $fieldName = $m[2][0];
            $operator  = $m[3][0];
            $compareValRaw = $m[5][0];
            $bodyStart = $matchPos + $matchLen;

            list($body, $closeEnd) = $this->findMatchingCloseTag($content, $bodyStart);
            if ($closeEnd === false) { $offset = $bodyStart; break; }

            $fullBlockLen = $closeEnd - $matchPos;

            // 保护嵌套 {if} 块，避免内部的 {elseif/else} 被误解析
            $markers = [];
            $bodyProtected = preg_replace_callback(
                '/\{if\s+.+?\}[\s\S]*?\{\/(?:if|endif)\}|\{endif\}/',
                function($nm) use (&$markers) {
                    $key = '__NESTC_' . count($markers) . '__';
                    $markers[$key] = $nm[0];
                    return $key;
                },
                $body
            );

            // 按 {elseif ...} 分割主体
            $segments = preg_split('/\{elseif\s+field\s*=\s*[\'"]([a-zA-Z0-9_]+)[\'"]\s*([<>=!]=?|<>|<=|>=)\s*[\'"]?([\w.]+)[\'"]?\s*\}/',
                $bodyProtected, -1, PREG_SPLIT_DELIM_CAPTURE);

            $ifContent = $segments[0];
            $elseifs = [];
            $elseContent = '';

            // 收集 elseif 条件（每3个元素：fieldName, operator, value, content）
            for ($i = 1; $i < count($segments) - 1; $i += 3) {
                $eField = $segments[$i];
                $eOp    = $segments[$i + 1];
                $eVal   = $segments[$i + 2];
                $eContent = isset($segments[$i + 3]) ? $segments[$i + 3] : '';
                $elseifs[] = ['field' => $eField, 'operator' => $eOp, 'value' => $eVal, 'content' => $eContent];
            }

            // 从最后一个 elseif 的 content 中分离 else 块
            $lastPart =& $segments[count($segments) - 1];
            $elsePos = strpos($lastPart, '{else}');
            if ($elsePos !== false) {
                $elseContent = substr($lastPart, $elsePos + 6);
                $lastPart = substr($lastPart, 0, $elsePos);
            }
            if (count($segments) > 1) {
                $elseifs[count($elseifs) - 1]['content'] = $lastPart;
            } elseif ($elsePos !== false) {
                // 仅有 if + else，没有 elseif
                $ifContent = $lastPart;
            }

            $restore = function($s) use ($markers) {
                return str_replace(array_keys($markers), array_values($markers), $s);
            };

            // 求值主条件
            $value = $this->resolveFieldValue($fieldName);
            $compareValue = $this->normalizeValue($compareValRaw);
            $rendered = '';
            if ($this->compareValues($value, $compareValue, $operator)) {
                $rendered = $this->parse($restore($ifContent));
            } else {
                // 尝试 elseif
                foreach ($elseifs as $eif) {
                    $eValue = $this->resolveFieldValue($eif['field']);
                    $eCompare = $this->normalizeValue($eif['value']);
                    if ($this->compareValues($eValue, $eCompare, $eif['operator'])) {
                        $rendered = $this->parse($restore($eif['content']));
                        break;
                    }
                }
                if ($rendered === '') {
                    $rendered = $this->parse($restore($elseContent));
                }
            }

            $content = substr_replace($content, $rendered, $matchPos, $fullBlockLen);
            $offset = $matchPos;
        }

        return $content;
    }

    private function resolveFieldValue($fieldName)
    {
        $value = null;
        $data = $this->template->getData();
        $loopItem = $this->template->getCurrentLoopItem();
        
        // 尝试从article数据获取
        if (isset($data['article']) && is_array($data['article'])) {
            if (strpos($fieldName, '.') !== false) {
                $keys = explode('.', $fieldName);
                $itemValue = $data['article'];
                foreach ($keys as $k) {
                    if (!is_array($itemValue) || !isset($itemValue[$k])) {
                        $itemValue = null;
                        break;
                    }
                    $itemValue = $itemValue[$k];
                }
                $value = $itemValue;
            } elseif (isset($data['article'][$fieldName])) {
                $value = $data['article'][$fieldName];
            }
        }
        
        if ($value === null) {
            $value = $this->template->getDataValue($fieldName);
        }
        
        if ($value === null && $loopItem) {
            if (strpos($fieldName, '.') !== false) {
                $keys = explode('.', $fieldName);
                $itemValue = $loopItem;
                foreach ($keys as $k) {
                    if (!is_array($itemValue) || !isset($itemValue[$k])) {
                        $itemValue = null;
                        break;
                    }
                    $itemValue = $itemValue[$k];
                }
                $value = $itemValue;
            } elseif (isset($loopItem[$fieldName])) {
                $value = $loopItem[$fieldName];
            }
        }
        
        if ($fieldName === 'is_favorite' || $fieldName === 'is_following' || $fieldName === 'is_liked') {
            $value = (bool)$value;
        }
        
        return $value;
    }

    private function normalizeValue($compareValue)
    {
        if (is_numeric($compareValue)) {
            return floatval($compareValue);
        } elseif ($compareValue === 'true') {
            return true;
        } elseif ($compareValue === 'false') {
            return false;
        }
        return $compareValue;
    }

    private function compareValues($value, $compareValue, $operator)
    {
        switch ($operator) {
            case '==': return ($value == $compareValue);
            case '!=': case '<>': return ($value != $compareValue);
            case '===': return ($value === $compareValue);
            case '!==': return ($value !== $compareValue);
            case '>': return ($value > $compareValue);
            case '<': return ($value < $compareValue);
            case '>=': return ($value >= $compareValue);
            case '<=': return ($value <= $compareValue);
        }
        return false;
    }

    /**
     * 解析 {if variable1 && variable2}...{else}...{/if} 条件
     * 使用嵌套深度计数
     */
    private function parseComplexCondition($content)
    {
        $offset = 0;
        while (preg_match(
            '/\{if\s+([a-zA-Z0-9_.]+)\s*&&\s*([a-zA-Z0-9_.]+)\s*\}/',
            $content, $m, PREG_OFFSET_CAPTURE, $offset
        )) {
            $matchPos  = $m[0][1];
            $matchLen  = strlen($m[0][0]);
            $var1      = $m[1][0];
            $var2      = $m[2][0];
            $bodyStart = $matchPos + $matchLen;

            list($body, $closeEnd) = $this->findMatchingCloseTag($content, $bodyStart);
            if ($closeEnd === false) { $offset = $bodyStart; break; }

            $fullBlockLen = $closeEnd - $matchPos;

            $markers = [];
            $bodyProtected = preg_replace_callback(
                '/\{if\s+.+?\}[\s\S]*?\{\/(?:if|endif)\}|\{endif\}/',
                function($nm) use (&$markers) {
                    $key = '__NESTX_' . count($markers) . '__';
                    $markers[$key] = $nm[0];
                    return $key;
                },
                $body
            );

            $ifContent = $bodyProtected;
            $elseContent = '';
            $elsePos = strpos($bodyProtected, '{else}');
            if ($elsePos !== false) {
                $ifContent   = substr($bodyProtected, 0, $elsePos);
                $elseContent = substr($bodyProtected, $elsePos + 6);
            }

            $restore = function($s) use ($markers) {
                return str_replace(array_keys($markers), array_values($markers), $s);
            };

            $value1 = $this->template->getDataValue($var1);
            $value2 = $this->template->getDataValue($var2);
            if ($value1 && $value2) {
                $rendered = $this->parse($restore($ifContent));
            } else {
                $rendered = $this->parse($restore($elseContent));
            }

            $content = substr_replace($content, $rendered, $matchPos, $fullBlockLen);
            $offset = $matchPos;
        }

        return $content;
    }

    /**
     * 解析 {if value="x" in="array"}...{/if} 条件
     * 使用嵌套深度计数
     */
    private function parseInArrayCondition($content)
    {
        $offset = 0;
        while (preg_match(
            '/\{if\s+value\s*=\s*[\'"]([^\'"]+)[\'"]\s+in\s*=\s*[\'"]([^\'"]+)[\'"]\s*\}/',
            $content, $m, PREG_OFFSET_CAPTURE, $offset
        )) {
            $matchPos  = $m[0][1];
            $matchLen  = strlen($m[0][0]);
            $value     = $m[1][0];
            $arrayName = $m[2][0];
            $bodyStart = $matchPos + $matchLen;

            list($body, $closeEnd) = $this->findMatchingCloseTag($content, $bodyStart);
            if ($closeEnd === false) { $offset = $bodyStart; break; }

            $fullBlockLen = $closeEnd - $matchPos;

            $data = $this->template->getData();
            if (isset($data[$arrayName]) && is_array($data[$arrayName]) && in_array($value, $data[$arrayName])) {
                $rendered = $this->parse($body);
            } else {
                $rendered = '';
            }

            $content = substr_replace($content, $rendered, $matchPos, $fullBlockLen);
            $offset = $matchPos;
        }

        return $content;
    }

    /**
     * 查找与 {if 起始标签匹配的关闭标签位置
     * 通过计数嵌套的 {if ...} / {/if}|{/endif}|{endif} 深度来找到正确的关闭标签
     *
     * @param string $content  完整内容
     * @param int    $startPos body 起始位置（{if ...} 标签之后）
     * @return array [bodyText, closeEndPos]  body 为关闭标签前的内容，closeEndPos 为关闭标签后的位置，false 表示未找到
     */
    private function findMatchingCloseTag($content, $startPos)
    {
        $depth = 1;
        $pos   = $startPos;
        $len   = strlen($content);

        while ($pos < $len && $depth > 0) {
            $nextOpen  = false;
            $nextClose = false;
            $closeLen  = 0;

            if (preg_match('/\{if\s+/', $content, $om, PREG_OFFSET_CAPTURE, $pos)) {
                $nextOpen = $om[0][1];
            }
            if (preg_match('/\{\/(?:if|endif)\}|\{endif\}/', $content, $cm, PREG_OFFSET_CAPTURE, $pos)) {
                $nextClose = $cm[0][1];
                $closeLen  = strlen($cm[0][0]);
            }

            if ($nextClose === false) {
                return [substr($content, $startPos), false];
            }

            if ($nextOpen !== false && $nextOpen < $nextClose) {
                $depth++;
                $pos = $nextOpen + strlen($om[0][0]);
            } else {
                $depth--;
                if ($depth === 0) {
                    $body = substr($content, $startPos, $nextClose - $startPos);
                    return [$body, $nextClose + $closeLen];
                }
                $pos = $nextClose + $closeLen;
            }
        }

        return [substr($content, $startPos), false];
    }

    private function parseIssetCondition($content)
    {
        // 使用嵌套深度计数确保匹配到正确的关闭标签（而非内层 {/if}）
        // 支持：{if isset="var"} / {if !isset="var"} / {if isset="var" && expr} / {elseif} / {else}
        // 支持单引号和双引号
        $offset = 0;
        while (preg_match(
            '/\{if\s+(!)?isset\s*=\s*[\'"]([^\'"]+)[\'"](\s*(&&|\|\|)\s*(.+?))?\s*\}/',
            $content, $m, PREG_OFFSET_CAPTURE, $offset
        )) {
            $matchPos  = $m[0][1];
            $matchLen  = strlen($m[0][0]);
            $bodyStart = $matchPos + $matchLen;

            $negate    = !empty($m[1][0]);
            $varName   = $m[2][0];
            $hasExtra  = !empty($m[3][0]);
            $logicOp   = $m[4][0] ?? '';
            $extraExpr = isset($m[5][0]) ? trim($m[5][0]) : '';

            list($body, $closeEnd) = $this->findMatchingCloseTag($content, $bodyStart);
            if ($closeEnd === false) { $offset = $bodyStart; break; }

            $fullBlockLen = $closeEnd - $matchPos;

            // 保护嵌套 {if} 块，避免其内的 {elseif/else} 被误解析
            $markers = [];
            $bodyProtected = preg_replace_callback(
                '/\{if\s+.+?\}[\s\S]*?\{\/(?:if|endif)\}|\{endif\}/',
                function($nm) use (&$markers) {
                    $m = '__NESTS_' . count($markers) . '__';
                    $markers[$m] = $nm[0];
                    return $m;
                },
                $body
            );

            $segments = preg_split('/\{elseif\s+(.+?)\s*\}/', $bodyProtected, -1, PREG_SPLIT_DELIM_CAPTURE);

            $ifContent   = $segments[0];
            $elseifs     = [];
            $elseContent = '';

            for ($i = 1; $i < count($segments); $i += 2) {
                $eExpr    = trim($segments[$i]);
                $eContent = isset($segments[$i + 1]) ? $segments[$i + 1] : '';
                $elseifs[] = ['expr' => $eExpr, 'content' => $eContent];
            }

            $lastPart =& $segments[count($segments) - 1];
            $elsePos  = strpos($lastPart, '{else}');
            if ($elsePos !== false) {
                $elseContent = substr($lastPart, $elsePos + 6);
                $lastPart    = substr($lastPart, 0, $elsePos);
            }

            $restore = function($s) use ($markers) {
                return str_replace(array_keys($markers), array_values($markers), $s);
            };

            if (count($segments) > 1 && $elsePos !== false) {
                $elseifs[count($elseifs) - 1]['content'] = $lastPart;
            } elseif (count($segments) === 1) {
                $ifContent = $lastPart;
            }

            // === 条件求值 ===
            $value = $this->template->getDataValue($varName);
            $isSet = ($value !== null && $value !== false);
            if ($negate) { $isSet = !$isSet; }

            $result = $isSet;
            if ($hasExtra) {
                $extraResult = $this->template->condition()->evaluate($extraExpr);
                $result = ($logicOp === '&&') ? ($isSet && $extraResult) : ($isSet || $extraResult);
            }

            if ($result) {
                $rendered = $this->parse($restore($ifContent));
            } else {
                $rendered = '';
                foreach ($elseifs as $eif) {
                    if ($this->template->condition()->evaluate($eif['expr'])) {
                        $rendered = $this->parse($restore($eif['content']));
                        break;
                    }
                }
                if ($rendered === '') {
                    $rendered = $this->parse($restore($elseContent));
                }
            }

            // 替换整个块（从 {if 开头到匹配的关闭标签之后）
            $content = substr_replace($content, $rendered, $matchPos, $fullBlockLen);
            $offset = $matchPos;
        }

        return $content;
    }

    /**
     * 解析 {if empty="xxx"}...{else}...{/if} 条件
     * 使用嵌套深度计数，确保匹配到正确的关闭标签（而非内层 {/if}）
     * 支持单引号和双引号
     */
    private function parseEmptyCondition($content)
    {
        $offset = 0;
        while (preg_match(
            '/\{if\s+empty\s*=\s*[\'"]([^\'"]+)[\'"]\s*\}/',
            $content, $m, PREG_OFFSET_CAPTURE, $offset
        )) {
            $matchPos  = $m[0][1];
            $matchLen  = strlen($m[0][0]);
            $varName   = $m[1][0];
            $bodyStart = $matchPos + $matchLen;

            // 使用嵌套计数找到正确的关闭标签
            list($body, $closeEnd) = $this->findMatchingCloseTag($content, $bodyStart);
            if ($closeEnd === false) { $offset = $bodyStart; break; }

            $fullBlockLen = $closeEnd - $matchPos;

            // 保护嵌套 {if} 块，避免其内的 {else} 被误识别为当前块的 else
            $markers = [];
            $bodyProtected = preg_replace_callback(
                '/\{if\s+.+?\}[\s\S]*?\{\/(?:if|endif)\}|\{endif\}/',
                function($nm) use (&$markers) {
                    $key = '__NESTE_' . count($markers) . '__';
                    $markers[$key] = $nm[0];
                    return $key;
                },
                $body
            );

            // 分离 if 主体和 else 主体
            $ifContent = $bodyProtected;
            $elseContent = '';
            $elsePos = strpos($bodyProtected, '{else}');
            if ($elsePos !== false) {
                $ifContent   = substr($bodyProtected, 0, $elsePos);
                $elseContent = substr($bodyProtected, $elsePos + 6);
            }

            // 恢复嵌套块内容
            $restore = function($s) use ($markers) {
                return str_replace(array_keys($markers), array_values($markers), $s);
            };

            // 求值：empty 判断
            $value = $this->template->getDataValue($varName);
            if (empty($value)) {
                $rendered = $this->parse($restore($ifContent));
            } else {
                $rendered = $this->parse($restore($elseContent));
            }

            // 替换整个 if/endif 块
            $content = substr_replace($content, $rendered, $matchPos, $fullBlockLen);
            $offset = $matchPos;
        }

        return $content;
    }

    /**
     * 解析 {if equal="key" value="compareValue"}...{else}...{/if} 条件
     * 使用嵌套深度计数，支持单/双引号
     */
    private function parseEqualCondition($content)
    {
        $offset = 0;
        while (preg_match(
            '/\{if\s+equal\s*=\s*[\'"]([^\'"]+)[\'"]\s+value\s*=\s*[\'"]([^\'"]+)[\'"]\s*\}/',
            $content, $m, PREG_OFFSET_CAPTURE, $offset
        )) {
            $matchPos  = $m[0][1];
            $matchLen  = strlen($m[0][0]);
            $key       = $m[1][0];
            $rawCompare = $m[2][0];
            $bodyStart = $matchPos + $matchLen;

            list($body, $closeEnd) = $this->findMatchingCloseTag($content, $bodyStart);
            if ($closeEnd === false) { $offset = $bodyStart; break; }

            $fullBlockLen = $closeEnd - $matchPos;

            // 保护嵌套 {if} 块
            $markers = [];
            $bodyProtected = preg_replace_callback(
                '/\{if\s+.+?\}[\s\S]*?\{\/(?:if|endif)\}|\{endif\}/',
                function($nm) use (&$markers) {
                    $key = '__NESTE_' . count($markers) . '__';
                    $markers[$key] = $nm[0];
                    return $key;
                },
                $body
            );

            $ifContent = $bodyProtected;
            $elseContent = '';
            $elsePos = strpos($bodyProtected, '{else}');
            if ($elsePos !== false) {
                $ifContent   = substr($bodyProtected, 0, $elsePos);
                $elseContent = substr($bodyProtected, $elsePos + 6);
            }

            $restore = function($s) use ($markers) {
                return str_replace(array_keys($markers), array_values($markers), $s);
            };

            // 解析比较值（可能包含模板变量）
            $compareValue = $this->parse($rawCompare);

            // 求值：equal=xxx value=yyy
            $value = null;
            if (strpos($key, 'get.') === 0) {
                $paramName = substr($key, 4);
                $value = isset($_GET[$paramName]) ? $_GET[$paramName] : null;
            } elseif ($key === 'current_url') {
                $siteConfig = $this->template->getSiteConfig();
                $currentUrl = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
                $value = $siteConfig['url'] . $currentUrl;
                $value = self::stripQueryString($value);
                $compareValue = self::stripQueryString($compareValue);
            } else {
                $value = $this->template->getDataValue($key);
            }

            if ($value == $compareValue) {
                $rendered = $this->parse($restore($ifContent));
            } else {
                $rendered = $this->parse($restore($elseContent));
            }

            $content = substr_replace($content, $rendered, $matchPos, $fullBlockLen);
            $offset = $matchPos;
        }

        return $content;
    }

    /**
     * 去掉 URL 中的 query string（保留 scheme/host/path 部分），便于与生成 URL 比较
     * 例如：http://example.com/category/5?page=2 -> http://example.com/category/5
     */
    private static function stripQueryString($url) {
        if ($url === null || $url === '') {
            return $url;
        }
        $pos = strpos($url, '?');
        if ($pos === false) {
            $pos = strpos($url, '&');
        }
        if ($pos !== false) {
            return substr($url, 0, $pos);
        }
        return $url;
    }

    private function parseVariableCompare($content)
    {
        // {if variable > value}，使用偏移量循环 + 嵌套深度计数（与 parseSimpleIf 保持一致）
        // 修复说明：旧实现把"嵌套扫描 + substr_replace 替换"写在 preg_replace_callback 回调里，
        //           但回调的返回值只会替换"匹配到的 {if ...} 起始标签"本身，
        //           substr_replace 的结果会被 preg_replace_callback 的最终返回值覆盖丢失，
        //           导致条件标签被删除而 if/else 两个分支的正文全部残留在页面中。
        //           改为偏移量循环后：整体匹配 → 计算条件 → 解析对应分支 → 原位替换 → 继续向后扫描。
        $offset = 0;
        while (preg_match(
            '/\{if\s+([a-zA-Z0-9_.]+)\s*([<>=!]=?|<>|<=|>=)\s*([\w.]+)\s*\}/',
            $content, $m, PREG_OFFSET_CAPTURE, $offset
        )) {
            $matchPos = $m[0][1];
            $matchLen = strlen($m[0][0]);
            $variableName = $m[1][0];
            $operator = $m[2][0];
            $compareValue = $m[3][0];

            $value = $this->template->getDataValue($variableName);
            $compareValue = $this->normalizeValue($compareValue);

            // 尝试从数据中获取比较值
            if (is_string($compareValue)) {
                $dataValue = $this->template->getDataValue($compareValue);
                if ($dataValue !== null) {
                    $compareValue = $dataValue;
                }
            }

            $result = $this->compareValues($value, $compareValue, $operator);

            // 查找匹配的结束标签，处理嵌套（{if 计数递增，/if 递减，level==1 时的 else 属于当前块）
            $bodyStart = $matchPos + $matchLen;
            $endPos = $bodyStart;
            $level = 1;
            $ifContent = '';
            $elseContent = '';
            $elsePos = -1;
            $closeLen = 0;

            while ($endPos < strlen($content) && $level > 0) {
                $nextIf = strpos($content, '{if', $endPos);
                $nextElse = strpos($content, '{else}', $endPos);
                $nextEndIf = strpos($content, '{/if}', $endPos);
                $nextEndIf2 = strpos($content, '{endif}', $endPos);

                $minPos = PHP_INT_MAX;
                if ($nextIf !== false) $minPos = min($minPos, $nextIf);
                if ($nextElse !== false) $minPos = min($minPos, $nextElse);
                if ($nextEndIf !== false) $minPos = min($minPos, $nextEndIf);
                if ($nextEndIf2 !== false) $minPos = min($minPos, $nextEndIf2);

                if ($minPos === PHP_INT_MAX) break;

                if ($nextIf !== false && $nextIf === $minPos) {
                    // 遇到嵌套的 {if，深度 +1
                    $level++;
                    $endPos = $nextIf + 3;
                } elseif ($nextElse !== false && $nextElse === $minPos) {
                    // level==1 时的 else 才属于当前条件块
                    if ($level === 1) {
                        $ifContent = substr($content, $bodyStart, $nextElse - $bodyStart);
                        $elsePos = $nextElse;
                    }
                    $endPos = $nextElse + 6;
                } else {
                    // 遇到结束标签，深度 -1；减到 0 说明找到当前块的闭合
                    $isShortClose = ($nextEndIf !== false && $nextEndIf === $minPos);
                    $closeLen = $isShortClose ? 5 : 7;
                    $level--;
                    if ($level === 0) {
                        if ($elsePos !== -1) {
                            $elseContent = substr($content, $elsePos + 6, $minPos - ($elsePos + 6));
                        } else {
                            $ifContent = substr($content, $bodyStart, $minPos - $bodyStart);
                        }
                    }
                    $endPos = $minPos + $closeLen;
                }
            }

            // 未找到闭合标签：跳过该标签，避免死循环
            if ($level > 0) {
                $offset = $bodyStart;
                continue;
            }

            $rendered = $result ? $this->parse($ifContent) : $this->parse($elseContent);
            $content = substr_replace($content, $rendered, $matchPos, $endPos - $matchPos);
            // 从替换点继续扫描，保证后续同级别条件标签也能被处理
            $offset = $matchPos;
        }

        return $content;
    }

    /**
     * 解析 {if variable}...{else}...{/if} 条件
     * 使用嵌套深度计数，确保匹配到正确的关闭标签
     */
    private function parseSimpleIf($content)
    {
        $offset = 0;
        while (preg_match(
            '/\{if\s+([a-zA-Z0-9_.]+)\s*\}/',
            $content, $m, PREG_OFFSET_CAPTURE, $offset
        )) {
            $matchPos  = $m[0][1];
            $matchLen  = strlen($m[0][0]);
            $varName   = $m[1][0];
            $bodyStart = $matchPos + $matchLen;

            // 避免误匹配已经被其他方法处理过的 {if empty/isset/field/equal} 标签
            // 这些标签由各自的专用方法处理
            if (in_array($varName, ['empty', 'isset', 'field', 'equal', 'value', '!isset', '!empty'])) {
                $offset = $bodyStart;
                continue;
            }

            list($body, $closeEnd) = $this->findMatchingCloseTag($content, $bodyStart);
            if ($closeEnd === false) { $offset = $bodyStart; break; }

            $fullBlockLen = $closeEnd - $matchPos;

            // 保护嵌套块中的 {else}，避免被误识别
            $markers = [];
            $bodyProtected = preg_replace_callback(
                '/\{if\s+.+?\}[\s\S]*?\{\/(?:if|endif)\}|\{endif\}/',
                function($nm) use (&$markers) {
                    $key = '__NESTS_' . count($markers) . '__';
                    $markers[$key] = $nm[0];
                    return $key;
                },
                $body
            );

            $ifContent = $bodyProtected;
            $elseContent = '';
            $elsePos = strpos($bodyProtected, '{else}');
            if ($elsePos !== false) {
                $ifContent   = substr($bodyProtected, 0, $elsePos);
                $elseContent = substr($bodyProtected, $elsePos + 6);
            }

            $restore = function($s) use ($markers) {
                return str_replace(array_keys($markers), array_values($markers), $s);
            };

            $value = $this->template->getDataValue($varName);
            if ($value) {
                $rendered = $this->parse($restore($ifContent));
            } else {
                $rendered = $this->parse($restore($elseContent));
            }

            $content = substr_replace($content, $rendered, $matchPos, $fullBlockLen);
            $offset = $matchPos;
        }

        return $content;
    }

    // ==================== 循环 ====================
    /**
     * 解析循环标签（支持任意深度嵌套）
     * 
     * 语法：
     *   {loop name="dataName"}...{/loop}           // 从顶级 data 中取数据
     *   {loop name=":"}...{/loop}                   // 遍历当前循环 item 自身（嵌套数组）
     *   {loop name=":fieldName"}...{/loop}          // 从当前 item 中取字段作为数据源
     *   {loop name="parent.child"}...{/loop}        // 按点号路径从顶级 data 中查找
     * 
     * 循环体内部可以使用：
     *   {field name="xxx"}     // 访问当前 item 的字段
     *   {field name="_key"}    // 访问当前循环键值（如年份 "2026"、月份 "06"）
     *   {field name="_index"}  // 访问当前循环索引（0, 1, 2...）
     *   {count name=":"}       // 当前循环 item 的元素数量
     *   {loop name=":"}        // 嵌套遍历当前 item 自身
     *   {if field='xxx'}...{/if}  // 条件判断
     */
    private function parseLoops($content)
    {
        $offset = 0;
        $output = '';
        
        while (true) {
            // 查找下一个 {loop name="xxx"} 的起始位置
            $startPos = strpos($content, '{loop', $offset);
            if ($startPos === false) {
                $output .= substr($content, $offset);
                break;
            }
            
            // 提取 loop name
            $nameMatch = [];
            if (!preg_match('/\{loop\s+name="([^"]+)"\s*\}/', substr($content, $startPos, 100), $nameMatch)) {
                // 格式不正确，跳过这个位置
                $output .= substr($content, $offset, $startPos - $offset + 5);
                $offset = $startPos + 5;
                continue;
            }
            
            $loopName = $nameMatch[1];
            $loopOpenTag = $nameMatch[0];
            $loopContentStart = $startPos + strlen($loopOpenTag);
            
            // ============================================
            // 手动扫描找到匹配的 {/loop}（支持任意深度嵌套）
            // ============================================
            $nesting = 1;
            $scanPos = $loopContentStart;
            $loopContentEnd = -1;
            
            while ($nesting > 0) {
                $nextOpen = strpos($content, '{loop', $scanPos);
                $nextClose = strpos($content, '{/loop}', $scanPos);
                
                if ($nextClose === false) {
                    // 没找到闭合标签，放弃整个循环
                    break 2;
                }
                
                if ($nextOpen !== false && $nextOpen < $nextClose) {
                    // 先遇到 {loop，嵌套深度 +1
                    $nesting++;
                    $scanPos = $nextOpen + 5;
                } else {
                    // 先遇到 {/loop}，嵌套深度 -1
                    $nesting--;
                    if ($nesting === 0) {
                        $loopContentEnd = $nextClose;
                    }
                    $scanPos = $nextClose + 7;
                }
            }
            
            if ($loopContentEnd === -1) {
                // 没找到匹配的闭合标签
                $output .= substr($content, $offset, $startPos - $offset);
                $offset = $startPos + strlen($loopOpenTag);
                continue;
            }
            
            // 提取循环体（不含 {loop} 和 {/loop}）
            $loopContent = substr($content, $loopContentStart, $loopContentEnd - $loopContentStart);
            
            // 把当前循环之前的内容追加到输出
            $output .= substr($content, $offset, $startPos - $offset);
            
            // ==================== 数据源解析策略 ====================
            $data = $this->template->getData();
            $loopData = null;
            
            if ($loopName === ':') {
                // 遍历当前循环 item 自身（用于嵌套数组场景）
                $loopItem = $this->template->getCurrentLoopItem();
                if (is_array($loopItem)) {
                    $loopData = $loopItem;
                }
            } elseif (strpos($loopName, ':') === 0) {
                $fieldName = substr($loopName, 1);
                $loopItem = $this->template->getCurrentLoopItem();
                if ($loopItem && is_array($loopItem)) {
                    if (strpos($fieldName, '.') !== false) {
                        $keys = explode('.', $fieldName);
                        $val = $loopItem;
                        foreach ($keys as $k) {
                            if (is_array($val) && isset($val[$k])) {
                                $val = $val[$k];
                            } else {
                                $val = null;
                                break;
                            }
                        }
                        $loopData = $val;
                    } elseif (isset($loopItem[$fieldName])) {
                        $loopData = $loopItem[$fieldName];
                    }
                }
            } elseif (strpos($loopName, '.') !== false) {
                $loopData = $this->template->getDataValue($loopName);
            } else {
                if (isset($data[$loopName])) {
                    $loopData = $data[$loopName];
                }
            }
            
            // ==================== 执行循环 ====================
            $rendered = '';
            
            // 保存当前（外层）循环上下文，内层循环可能会覆盖这些值
            $savedItem   = $this->template->getCurrentLoopItem();
            $savedData   = $this->template->getCurrentLoopData();
            $savedKey    = $this->template->getCurrentLoopKey();
            $savedIndex  = $this->template->getCurrentLoopIndex();
            
            if (is_array($loopData) && !empty($loopData)) {
                $this->template->setCurrentLoopData($loopData);
                $index = 0;
                
                foreach ($loopData as $key => $item) {
                    $this->template->setCurrentLoopItem($item);
                    $this->template->setCurrentLoopKey($key);
                    $this->template->setCurrentLoopIndex($index);
                    // 对循环体递归解析（里面可能还有嵌套 loop、if、field 等）
                    $rendered .= $this->parse($loopContent);
                    $index++;
                }
            }
            
            // 恢复外层循环上下文（重要：内层循环结束时会清空这些状态）
            $this->template->setCurrentLoopItem($savedItem);
            $this->template->setCurrentLoopData($savedData);
            $this->template->setCurrentLoopKey($savedKey);
            $this->template->setCurrentLoopIndex($savedIndex);
            
            $output .= $rendered;
            
            // 推进扫描位置到 {/loop} 之后
            $offset = $loopContentEnd + 7;
        }
        
        return $output;
    }

    private function parseLoopsAgain($content)
    {
        return $this->parseLoops($content);
    }

    // ==================== 数组元素计数 ====================
    // 语法：{count name="xxx"}
    // 支持：顶级变量、:前缀（从当前循环 item 取）、点号路径
    private function parseCount($content)
    {
        return preg_replace_callback('/\{count\s+name="([^"]+)"\s*\}/', function($matches) {
            $name = $matches[1];
            $data = $this->template->getData();
            $target = null;
            
            // 数据源解析策略（与 parseLoops 一致）
            if ($name === ':') {
                $loopItem = $this->template->getCurrentLoopItem();
                if (is_array($loopItem)) {
                    $target = $loopItem;
                }
            } elseif (strpos($name, ':') === 0) {
                $fieldName = substr($name, 1);
                $loopItem = $this->template->getCurrentLoopItem();
                if ($loopItem && is_array($loopItem)) {
                    if (strpos($fieldName, '.') !== false) {
                        $keys = explode('.', $fieldName);
                        $val = $loopItem;
                        foreach ($keys as $k) {
                            if (is_array($val) && isset($val[$k])) {
                                $val = $val[$k];
                            } else {
                                $val = null;
                                break;
                            }
                        }
                        $target = $val;
                    } elseif (isset($loopItem[$fieldName])) {
                        $target = $loopItem[$fieldName];
                    }
                }
            } elseif (strpos($name, '.') !== false) {
                $target = $this->template->getDataValue($name);
            } else {
                if (isset($data[$name])) {
                    $target = $data[$name];
                }
            }
            
            if (is_array($target)) {
                return count($target);
            }
            return '0';
        }, $content);
    }

    // ==================== PHP代码块 ====================
    
    private function parsePhpBlocks($content)
    {
        return preg_replace_callback('/\{php\}([\s\S]*?)\{\/php\}/', function($matches) {
            $phpCode = $matches[1];
            $originalLoopItem = $this->template->getCurrentLoopItem();
            $originalLoopData = $this->template->getCurrentLoopData();
            $data = $this->template->getData();
            
            ob_start();
            try {
                // 将模板实例注入到 php 代码块作用域，以便调用 $template->url()->generate()
                $template = $this->template;
                if ($this->template->getCurrentLoopItem()) {
                    $item = $this->template->getCurrentLoopItem();
                    if (isset($item['id']) && isset($item['title']) && isset($item['content'])) {
                        $article = $item;
                    }
                }
                if (isset($data['article'])) {
                    $article = $data['article'];
                }
                eval($phpCode);
            } catch (Exception $e) {
                if (Config::get('debug.enabled', false)) {
                    echo 'PHP代码执行错误: ' . htmlspecialchars($e->getMessage());
                }
            }
            $output = ob_get_clean();
            
            $this->template->setCurrentLoopItem($originalLoopItem);
            $this->template->setCurrentLoopData($originalLoopData);
            
            return $output;
        }, $content);
    }

    // ==================== 变量输出 ====================
    
    private function parseEchoVariable($content)
    {
        return preg_replace_callback('/\{echo\s+value="([^"]+)"\s*\}/', function($matches) {
            $value = $this->template->getDataValue($matches[1]);
            return $value !== null ? $this->template->escapeValue($value) : '';
        }, $content);
    }

    private function parseFieldTags($content)
    {
        return preg_replace_callback('/\{field\s+name=(\'|")(.+?)\1\s*(?:filter=(\'|")(.+?)\3)?\s*(?:escape=(\'|")(.+?)\5)?\s*\}/s', function($matches) {
            $fieldName = $matches[2];
            $filters = isset($matches[4]) ? $matches[4] : '';
            $escape = isset($matches[6]) ? ($matches[6] === 'false' ? false : true) : true;
            
            // 尝试多种数据源
            $value = $this->getFieldFromAllSources($fieldName);
            
            if ($value !== null) {
                $value = $this->template->filter()->apply($value, $filters);
                return $escape ? $this->template->escapeValue($value) : $value;
            }
            
            return '';
        }, $content);
    }

    private function getFieldFromAllSources($fieldName)
    {
        // ====== 特殊字段：_key（当前循环键）、_index（当前循环索引）======
        if ($fieldName === '_key') {
            $key = $this->template->getCurrentLoopKey();
            return $key !== null ? $key : null;
        }
        if ($fieldName === '_index') {
            return $this->template->getCurrentLoopIndex();
        }
        
        $data = $this->template->getData();
        $loopItem = $this->template->getCurrentLoopItem();
        
        // 1. 当前循环项
        if ($loopItem) {
            $value = $this->resolveNestedValue($loopItem, $fieldName);
            if ($value !== null) return $value;
        }
        
        // 2. 全局数据
        $value = $this->template->getDataValue($fieldName);
        if ($value !== null) return $value;
        
        // 3. article/tag/page/category 专用数据源
        $sources = ['article', 'tag', 'page', 'category'];
        foreach ($sources as $source) {
            if (isset($data[$source]) && is_array($data[$source])) {
                $value = $this->resolveNestedValue($data[$source], $fieldName);
                if ($value !== null) return $value;
            }
        }
        
        return null;
    }

    private function resolveNestedValue($source, $fieldName)
    {
        if (strpos($fieldName, '.') !== false) {
            $keys = explode('.', $fieldName);
            $itemValue = $source;
            foreach ($keys as $k) {
                if (!is_array($itemValue) || !isset($itemValue[$k])) {
                    return null;
                }
                $itemValue = $itemValue[$k];
            }
            return $itemValue;
        } elseif (isset($source[$fieldName])) {
            return $source[$fieldName];
        }
        return null;
    }

    // ==================== 权限判断 ====================
    
    private function parsePermissionTags($content)
    {
        return preg_replace_callback('/\{if-permission\s+type="([^"]+)"\s*\}([\s\S]*?)\{\/if-permission\}/', function($matches) {
            $permissionType = $matches[1];
            $content = $matches[2];
            
            switch ($permissionType) {
                case 'is_user':
                    return isset($_SESSION['user']['id']) ? $content : '';
                case 'is_admin':
                    return isset($_SESSION['user']['is_admin']) && $_SESSION['user']['is_admin'] ? $content : '';
                case 'is_guest':
                    return !isset($_SESSION['user']['id']) ? $content : '';
                default:
                    return '';
            }
        }, $content);
    }

    // ==================== URL标签 ====================
    
    private function parseUrlTags($content)
    {
        // 支持两种写法：
        //   1. {url:article:id}                    // 从当前循环项取 id
        //   2. {url:article:{previous_article.id}}  // 嵌套变量解析（点号路径）
        //   3. {url:category:category_id}           // 从 item 取外键字段
        return preg_replace_callback('/\{url:([a-zA-Z0-9_-]+)(:\{?([a-zA-Z0-9_.]+)\}?)?\}/', function($matches) {
            $type = $matches[1];
            $param = isset($matches[3]) ? $matches[3] : '';

            $item = $this->template->getCurrentLoopItem();

            // 如果 param 含有点号（例如 previous_article.id），
            // 说明要访问顶级数据的嵌套字段；把嵌套字段值取出作为 param，
            // 并把原始的 item（例如文章本身）作为数据源传入 generate。
            $resolvedParam = $param;
            if (strpos($param, '.') !== false) {
                $value = $this->template->getDataValue($param);
                if ($value !== null) {
                    $resolvedParam = $value;
                }
            }

            return $this->template->url()->generate($type, $item, $resolvedParam);
        }, $content);
    }

    // ==================== 面包屑/分页 ====================
    
    private function parseBreadcrumb($content)
    {
        return preg_replace_callback('/\{breadcrumb\}/', function($matches) {
            return $this->template->output()->renderBreadcrumb();
        }, $content);
    }

    private function parsePagination($content)
    {
        $content = preg_replace_callback('/\{pagination:info\}/', function($matches) {
            return $this->template->output()->renderPaginationInfo();
        }, $content);
        
        $content = preg_replace_callback('/\{pagination\}/', function($matches) {
            return $this->template->output()->renderPagination();
        }, $content);
        
        return $content;
    }

    // ==================== 函数调用 ====================
    
    private function parseFunctionCalls($content)
    {
        return preg_replace_callback('/\{func:([a-zA-Z0-9_]+)(?:\s+([^\}]+))?\}/', function($matches) {
            $funcName = $matches[1];
            $argsStr = isset($matches[2]) ? $matches[2] : '';
            $args = [];
            $functions = $this->template->getFunctions();
            
            // 解析命名参数
            if (preg_match_all('/\s+([a-zA-Z0-9_]+)="([^"]+)"/', $argsStr, $namedArgs)) {
                $args = array_combine($namedArgs[1], $namedArgs[2]);
            } elseif (strpos($argsStr, ':') === 0) {
                $args = array_map(function($arg) {
                    return trim($arg, '"');
                }, explode(':', substr($argsStr, 1)));
            }
            
            foreach ($args as &$arg) {
                if (is_string($arg) && preg_match('/\{([a-zA-Z0-9_.]+)\}/', $arg)) {
                    $arg = preg_replace_callback('/\{([a-zA-Z0-9_.]+)\}/', function($varMatches) {
                        $value = $this->template->getDataValue($varMatches[1]);
                        return $value !== null ? $value : $varMatches[0];
                    }, $arg);
                }
            }
            
            if (isset($functions[$funcName])) {
                return call_user_func_array($functions[$funcName], $args);
            }
            
            return '';
        }, $content);
    }

    // ==================== WebP 图片标签 ====================
    
    /**
     * 解析 {picture src="xxx,yyy" alt="xxx" class="xxx" wrap_url="article" url_field="id" lazy="true"} 标签
     * 替代原模板中的 {php} echo \WebPHelper::picture(...) {/php}
     * 当图片路径为空时返回空字符串，避免输出空标签
     * 
     * 参数说明：
     *   src       - 必填，图片路径字段名，支持逗号分隔多字段回退（如 cover_image,first_image）
     *   alt       - 可选，alt 文本字段名（默认为 "title"）
     *   class     - 可选，CSS 类名（默认为 "lazy-img"）
     *   wrap_url  - 可选，URL 路由类型（如 article），如指定则在图片外包裹链接
     *   url_field - 可选，配合 wrap_url 使用，指定 ID 字段名（默认为 "id"）
     *   lazy      - 可选，是否启用懒加载（默认为 true）
     */
    private function parsePictureTags($content)
    {
        return preg_replace_callback('/\{picture\s+src\s*=\s*([\'\"])([a-zA-Z0-9_,]+)\1(?:\s+alt\s*=\s*([\'\"])([a-zA-Z0-9_.]+)\3)?(?:\s+class\s*=\s*([\'\"])([^\"]+)\5)?(?:\s+wrap_url\s*=\s*([\'\"])([a-zA-Z0-9_]+)\7)?(?:\s+url_field\s*=\s*([\'\"])([a-zA-Z0-9_.]+)\9)?(?:\s+wrap_class\s*=\s*([\'\"])([a-zA-Z0-9_-]+)\11)?(?:\s+lazy\s*=\s*(true|false))?\s*\}/', function($matches) {
            $srcFields = explode(',', $matches[2]);
            $altField = isset($matches[4]) && !empty($matches[4]) ? $matches[4] : 'title';
            $class = isset($matches[6]) && !empty($matches[6]) ? $matches[6] : 'lazy-img';
            $wrapUrl = isset($matches[8]) && !empty($matches[8]) ? $matches[8] : '';
            $urlField = isset($matches[10]) && !empty($matches[10]) ? $matches[10] : 'id';
            $wrapClass = isset($matches[12]) && !empty($matches[12]) ? $matches[12] : '';
            $lazy = isset($matches[13]) ? ($matches[13] === 'true') : true;
            
            // 按顺序查找第一个非空的图片路径字段（支持多字段回退）
            $imagePath = '';
            foreach ($srcFields as $field) {
                $field = trim($field);
                if (!empty($field)) {
                    $value = $this->resolveFieldValue($field);
                    if (!empty($value)) {
                        $imagePath = $value;
                        break;
                    }
                }
            }
            
            // 没有有效图片路径则返回空
            if (empty($imagePath)) {
                return '';
            }
            
            // 获取 alt 文本
            $altText = $this->resolveFieldValue($altField);
            if (empty($altText)) {
                $altText = '';
            }
            
            // 生成图片标签
            if (class_exists('WebPHelper')) {
                $imgTag = \WebPHelper::picture($imagePath, $altText, $class, $lazy);
            } else {
                $safePath = htmlspecialchars($imagePath, ENT_QUOTES);
                $safeAlt = htmlspecialchars($altText, ENT_QUOTES);
                $safeClass = htmlspecialchars($class, ENT_QUOTES);
                if ($lazy) {
                    $imgTag = '<img class="' . $safeClass . '" data-src="' . $safePath . '" alt="' . $safeAlt . '" />';
                } else {
                    $imgTag = '<img class="' . $safeClass . '" src="' . $safePath . '" alt="' . $safeAlt . '" />';
                }
            }
            
            // 如果指定了 wrap_url，则在外层包裹链接
            if (!empty($wrapUrl)) {
                // 与 parseUrlTags() 保持一致的 URL 生成逻辑
                $item = $this->template->getCurrentLoopItem();
                if (method_exists($this->template, 'url')) {
                    try {
                        $url = $this->template->url()->generate($wrapUrl, $item, $urlField);
                    } catch (\Exception $e) {
                        $url = '?c=Home&m=' . $wrapUrl . '&id=' . $this->resolveFieldValue($urlField);
                    }
                } else {
                    $url = '?c=Home&m=' . $wrapUrl . '&id=' . $this->resolveFieldValue($urlField);
                }
                // wrap_class 默认值为 post-cover
                $divClass = !empty($wrapClass) ? $wrapClass : 'post-cover';
                return '<a href="' . htmlspecialchars($url, ENT_QUOTES) . '" class="post-cover-link"><div class="' . htmlspecialchars($divClass, ENT_QUOTES) . '">' . $imgTag . '</div></a>';
            }
            
            // 如果只指定了 wrap_class（没有 wrap_url），则用 div 包裹
            if (!empty($wrapClass)) {
                return '<div class="' . htmlspecialchars($wrapClass, ENT_QUOTES) . '">' . $imgTag . '</div>';
            }
            
            return $imgTag;
        }, $content);
    }
    
    /**
     * 解析 {content_process field="xxx"} 标签
     * 替代原模板中的 {php} echo \WebPHelper::processContent(...) {/php}
     * 
     * 参数说明：
     *   field - 必填，内容字段名（从当前循环 item 或模板数据中获取）
     */
    private function parseContentProcessTags($content)
    {
        return preg_replace_callback('/\{content_process\s+field\s*=\s*([\'\"])([a-zA-Z0-9_.]+)\1\s*\}/', function($matches) {
            $fieldName = $matches[2];
            $htmlContent = $this->resolveFieldValue($fieldName);
            
            if (empty($htmlContent)) {
                return '';
            }
            
            // 调用 WebPHelper::processContent()
            if (class_exists('WebPHelper')) {
                return \WebPHelper::processContent($htmlContent);
            }
            
            return $htmlContent;
        }, $content);
    }
    
    // ==================== 置顶标识 ====================
    
    private function parseTopBadge($content)
    {
        return preg_replace_callback('/\{top_badge\}/', function($matches) {
            $topType = $this->resolveFieldValue('top_type');
            
            if ($topType == 1) {
                return '<span class="top-badge top-global">置顶</span>';
            } elseif ($topType == 2) {
                return '<span class="top-badge top-home">置顶</span>';
            } elseif ($topType == 3) {
                return '<span class="top-badge top-category">置顶</span>';
            }
            return '';
        }, $content);
    }

    // ==================== 变量输出与过滤器 ====================
    
    private function parseVariableOutput($content)
    {
        // 首先保护 JavaScript 模板字符串（反引号内的内容），避免其中的 ${变量} 被解析
        $placeholder = '__JS_TEMPLATE_STRING__';
        $jsTemplates = [];
        
        $content = preg_replace_callback('/`[^`]*`/', function($matches) use (&$jsTemplates, $placeholder) {
            $key = $placeholder . count($jsTemplates);
            $jsTemplates[$key] = $matches[0];
            return $key;
        }, $content);
        
        // 然后解析模板变量
        $content = preg_replace_callback('/\{\$?([a-zA-Z0-9_.+\-*\/() ]+)(?:\|([^\}]+))?\}/', function($matches) {
            $expression = $matches[1];
            $filters = isset($matches[2]) ? $matches[2] : '';
            $siteConfig = $this->template->getSiteConfig();
            
            // 算术表达式
            if (preg_match('/[+\-*\/()]/', $expression)) {
                $evaluatedExpression = preg_replace_callback('/([a-zA-Z0-9_.]+)/', function($varMatches) {
                    $varValue = $this->template->getDataValue($varMatches[1]);
                    return $varValue !== null ? $varValue : $varMatches[0];
                }, $expression);
                
                try {
                    $value = eval("return \$evaluatedExpression;");
                    if (is_numeric($value)) {
                        $value = $this->template->filter()->apply($value, $filters);
                        return $this->template->escapeValue($value);
                    }
                } catch (Exception $e) {
                    return '';
                }
            } else {
                // 尝试从循环项获取变量（如 $notification.content）
                $value = null;
                $loopItem = $this->template->getCurrentLoopItem();
                
                // 处理嵌套变量，如 notification.content
                if (strpos($expression, '.') !== false) {
                    $parts = explode('.', $expression);
                    $baseVar = $parts[0];
                    $subVar = $parts[1];
                    
                    // 优先从循环项获取
                    if ($loopItem && isset($loopItem[$baseVar]) && isset($loopItem[$baseVar][$subVar])) {
                        $value = $loopItem[$baseVar][$subVar];
                    } elseif ($loopItem && isset($loopItem[$expression])) {
                        $value = $loopItem[$expression];
                    } else {
                        // 从全局数据获取
                        $value = $this->template->getDataValue($expression);
                    }
                } else {
                    // 普通变量
                    if ($loopItem && isset($loopItem[$expression])) {
                        $value = $loopItem[$expression];
                    } else {
                        $value = $this->template->getDataValue($expression);
                    }
                }
                
                if ($value !== null) {
                    $value = $this->template->filter()->apply($value, $filters);
                    return $this->template->escapeValue($value);
                }
                
                // 站点配置
                if (strpos($expression, 'site.') === 0) {
                    $key = substr($expression, 5);
                    if (isset($siteConfig[$key])) {
                        $value = $this->template->filter()->apply($siteConfig[$key], $filters);
                        return $value;
                    }
                    return '';
                }
            }
            
            return '';
        }, $content);
        
        // 恢复 JavaScript 模板字符串
        foreach ($jsTemplates as $key => $jsTemplate) {
            $content = str_replace($key, $jsTemplate, $content);
        }
        
        return $content;
    }
}
