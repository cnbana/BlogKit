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
 * BlogKit 模板条件表达式评估组件
 * 
 * 从 Template.php 中提取的条件表达式引擎：
 *   - 嵌套变量替换（支持深层嵌套如 session.user.id）
 *   - 字段标签 {field name="xxx"} 替换
 *   - 特殊操作符转换（&&→AND, ||→OR）
 *   - 安全表达式求值
 * 
 * @package BlogKit
 * @since 2.2.0
 */
class TemplateCondition
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
     * 评估条件表达式
     * @param string $condition 条件表达式
     * @return bool 条件是否成立
     */
    public function evaluate($condition)
    {
        // 0. 处理条件表达式中的{field}标签
        $condition = preg_replace_callback('/\{field\s+name=([\'\"])(.+?)\1\s*\}/s', function($fieldMatches) {
            $fieldName = $fieldMatches[2];
            $value = null;
            
            // 尝试从article数据获取（文章详情页专用）
            $data = $this->template->getData();
            $loopItem = $this->template->getCurrentLoopItem();
            
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
            
            // 确保布尔值正确处理
            if ($fieldName === 'is_favorite' || $fieldName === 'is_following' || $fieldName === 'is_liked') {
                $value = (bool)$value;
            }
            
            if ($value === null) {
                return "null";
            }
            if (is_array($value)) {
                return !empty($value) ? "true" : "false";
            }
            return is_numeric($value) ? $value : "'" . addslashes(strval($value)) . "'";
        }, $condition);
        
        // 1. 先处理所有嵌套变量，包括深层嵌套
        $condition = preg_replace_callback('/([a-zA-Z0-9_]+)(\.[a-zA-Z0-9_]+)+/', function($matches) {
            $fullKey = $matches[0];
            $siteConfig = $this->template->getSiteConfig();
            
            // 特殊处理site.xxx变量
            if (strpos($fullKey, 'site.') === 0) {
                $key = substr($fullKey, 5);
                if (isset($siteConfig[$key])) {
                    $value = $siteConfig[$key];
                    if (is_array($value)) {
                        return !empty($value) ? "true" : "false";
                    }
                    return is_numeric($value) ? $value : "'" . addslashes(strval($value)) . "'";
                }
                return "null";
            }
            
            $value = $this->template->getDataValue($fullKey);
            if ($value === null) {
                return "null";
            }
            if (is_array($value)) {
                return !empty($value) ? "true" : "false";
            }
            return is_numeric($value) ? $value : "'" . addslashes(strval($value)) . "'";
        }, $condition);
        
        // 2. 替换大括号包裹的变量
        $condition = preg_replace_callback('/\{([a-zA-Z0-9_.]+)\}/', function($matches) {
            $value = $this->template->getDataValue($matches[1]);
            if (is_array($value)) {
                return !empty($value) ? "true" : "false";
            }
            return is_numeric($value) ? $value : "'" . addslashes(strval($value)) . "'";
        }, $condition);
        
        // 3. 处理独立变量，确保只在非字符串上下文中替换
        $stringMarkers = [];
        $condition = preg_replace_callback('/(\'[^\']*\'|"[^"]*")/', function($matches) use (&$stringMarkers) {
            $marker = "__STRING_MARKER_" . count($stringMarkers) . "__";
            $stringMarkers[$marker] = $matches[0];
            return $marker;
        }, $condition);
        
        $data = $this->template->getData();
        $condition = preg_replace_callback('/\b([a-zA-Z0-9_]+)\b/', function($matches) use ($data) {
            $key = $matches[1];
            $keywords = ['true', 'false', 'null', 'AND', 'OR', 'NOT', 'is', 'empty'];
            if (in_array(strtoupper($key), $keywords) || is_numeric($key)) {
                return $key;
            }
            
            if (array_key_exists($key, $data)) {
                $value = $data[$key];
                if (is_array($value)) {
                    return !empty($value) ? "true" : "false";
                }
                if ($value === false) {
                    return "false";
                }
                return is_numeric($value) ? $value : "'" . addslashes(strval($value)) . "'";
            }
            
            $value = $this->template->getDataValue($key);
            if ($value !== null) {
                if (is_array($value)) {
                    return !empty($value) ? "true" : "false";
                }
                return is_numeric($value) ? $value : "'" . addslashes(strval($value)) . "'";
            }
            
            return $key;
        }, $condition);
        
        // 恢复字符串部分
        foreach ($stringMarkers as $marker => $original) {
            $condition = str_replace($marker, $original, $condition);
        }
        
        // 4. 替换特殊操作符为PHP操作符
        $condition = str_replace('&&', ' AND ', $condition);
        $condition = str_replace('||', ' OR ', $condition);
        $condition = str_replace('==', ' == ', $condition);
        $condition = str_replace('!=', ' != ', $condition);
        $condition = str_replace('>', ' > ', $condition);
        $condition = str_replace('<', ' < ', $condition);
        $condition = str_replace('>=', ' >= ', $condition);
        $condition = str_replace('<=', ' <= ', $condition);
        
        // 5. 确保操作符周围有空格
        $condition = preg_replace('/\s*([=!<>]+)\s*/', ' \1 ', $condition);
        
        // 6. 安全表达式求值：先保护字符串内容，再过滤危险字符，最后恢复
        // （避免字符串中的中文/特殊符号被安全正则误删）
        $stringSafeMarkers = [];
        $condition = preg_replace_callback('/(\'[^\']*\'|"[^"]*")/', function($matches) use (&$stringSafeMarkers) {
            $marker = "__SAFESTR_" . count($stringSafeMarkers) . "__";
            $stringSafeMarkers[$marker] = $matches[0];
            return $marker;
        }, $condition);
        
        // 仅对非字符串部分做字符白名单过滤
        $condition = preg_replace('/[^0-9a-zA-Z\s()\'".,!=<>:_]/', '', $condition);
        
        // 恢复受保护的字符串内容
        foreach ($stringSafeMarkers as $marker => $original) {
            $condition = str_replace($marker, $original, $condition);
        }
        
        // 7. 安全验证：在执行前进行严格的表达式白名单检查
        // 禁止任何函数调用、类方法调用、对象属性访问等危险操作
        $unsafePatterns = [
            '/\([^)]*\)[a-zA-Z_]/',      // 禁止函数后紧跟变量名（如 functionName()$var）
            '/[a-zA-Z_][a-zA-Z0-9_]*\s*\(/', // 禁止函数调用
            '/\$[a-zA-Z_][a-zA-Z0-9_]*/',   // 禁止直接PHP变量
            '/->/',                        // 禁止对象方法/属性访问
            '/::/',                        // 禁止类静态方法/属性访问
            '/`/',                         // 禁止反引号执行
            '/\b(include|require|eval|exec|system|passthru|shell_exec|popen|proc_open)\b/i', // 禁止危险函数名
            '/\b(assert|create_function|call_user_func|call_user_func_array)\b/i', // 禁止回调函数
            '/\b(goto|die|exit|throw)\b/i', // 禁止控制流语句
            '/;/',                         // 禁止多条语句
        ];
        
        foreach ($unsafePatterns as $pattern) {
            if (preg_match($pattern, $condition)) {
                error_log('TemplateCondition: 检测到不安全的条件表达式: ' . $condition);
                return false;
            }
        }
        
        // 8. 执行表达式求值（已通过安全检查）
        try {
            return eval("return ($condition);");
        } catch (Exception $e) {
            error_log('TemplateCondition: 表达式求值失败: ' . $e->getMessage() . ', 表达式: ' . $condition);
            return false;
        }
    }
}
