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
 * 统一验证器类
 * 提供表单验证功能，支持多种验证规则
 */
class Validator {
    
    /**
     * @var array 验证错误信息
     */
    private $errors = [];
    
    /**
     * @var array 验证规则
     */
    private $rules = [];
    
    /**
     * @var array 待验证的数据
     */
    private $data = [];
    
    /**
     * @var array 自定义错误消息
     */
    private $customMessages = [];
    
    /**
     * 构造函数
     * 
     * @param array $data 待验证的数据
     * @param array $rules 验证规则
     * @param array $customMessages 自定义错误消息（可选）
     */
    public function __construct(array $data, array $rules, array $customMessages = []) {
        $this->data = $data;
        $this->rules = $rules;
        $this->customMessages = $customMessages;
    }
    
    /**
     * 执行验证
     * 
     * @return bool 是否验证通过
     */
    public function validate() {
        foreach ($this->rules as $field => $ruleString) {
            $rules = explode('|', $ruleString);
            
            foreach ($rules as $rule) {
                $this->applyRule($field, $rule);
            }
        }
        
        return empty($this->errors);
    }
    
    /**
     * 应用单个验证规则
     * 
     * @param string $field 字段名
     * @param string $rule 规则名（可能包含参数，如 max:100）
     */
    private function applyRule($field, $rule) {
        $value = $this->data[$field] ?? null;
        
        // 检查是否包含参数（如 max:100, min:5）
        if (strpos($rule, ':') !== false) {
            list($ruleName, $param) = explode(':', $rule, 2);
            $this->applyParameterizedRule($field, $ruleName, $param, $value);
        } else {
            // 无参数规则
            switch ($rule) {
                case 'required':
                    $this->validateRequired($field, $value);
                    break;
                case 'email':
                    $this->validateEmail($field, $value);
                    break;
                case 'url':
                    $this->validateUrl($field, $value);
                    break;
                case 'numeric':
                    $this->validateNumeric($field, $value);
                    break;
                case 'integer':
                    $this->validateInteger($field, $value);
                    break;
                case 'boolean':
                    $this->validateBoolean($field, $value);
                    break;
                case 'array':
                    $this->validateArray($field, $value);
                    break;
                case 'json':
                    $this->validateJson($field, $value);
                    break;
                case 'date':
                    $this->validateDate($field, $value);
                    break;
                case 'datetime':
                    $this->validateDatetime($field, $value);
                    break;
                case 'ip':
                    $this->validateIp($field, $value);
                    break;
                case 'phone':
                    $this->validatePhone($field, $value);
                    break;
                case 'uuid':
                    $this->validateUuid($field, $value);
                    break;
                case 'hex':
                    $this->validateHex($field, $value);
                    break;
                case 'slug':
                    $this->validateSlug($field, $value);
                    break;
                case 'alpha':
                    $this->validateAlpha($field, $value);
                    break;
                case 'alphanumeric':
                    $this->validateAlphanumeric($field, $value);
                    break;
                case 'password':
                    $this->validatePassword($field, $value);
                    break;
                case 'confirmed':
                    $this->validateConfirmed($field, $value);
                    break;
            }
        }
    }
    
    /**
     * 应用带参数的验证规则
     * 
     * @param string $field 字段名
     * @param string $ruleName 规则名
     * @param string $param 参数
     * @param mixed $value 字段值
     */
    private function applyParameterizedRule($field, $ruleName, $param, $value) {
        switch ($ruleName) {
            case 'min':
                $this->validateMin($field, $value, $param);
                break;
            case 'max':
                $this->validateMax($field, $value, $param);
                break;
            case 'length':
                $this->validateLength($field, $value, $param);
                break;
            case 'between':
                $this->validateBetween($field, $value, $param);
                break;
            case 'in':
                $this->validateIn($field, $value, $param);
                break;
            case 'not_in':
                $this->validateNotIn($field, $value, $param);
                break;
            case 'regex':
                $this->validateRegex($field, $value, $param);
                break;
            case 'same':
                $this->validateSame($field, $value, $param);
                break;
            case 'different':
                $this->validateDifferent($field, $value, $param);
                break;
            case 'size':
                $this->validateSize($field, $value, $param);
                break;
        }
    }
    
    /**
     * 验证必填字段
     */
    private function validateRequired($field, $value) {
        if ($value === null || $value === '' || $value === [] || $value === false) {
            $this->addError($field, 'required', '字段 :field 不能为空');
        }
    }
    
    /**
     * 验证邮箱格式
     */
    private function validateEmail($field, $value) {
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, 'email', '字段 :field 格式不正确');
        }
    }
    
    /**
     * 验证URL格式
     */
    private function validateUrl($field, $value) {
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
            $this->addError($field, 'url', '字段 :field 格式不正确');
        }
    }
    
    /**
     * 验证数字
     */
    private function validateNumeric($field, $value) {
        if ($value !== null && $value !== '' && !is_numeric($value)) {
            $this->addError($field, 'numeric', '字段 :field 必须是数字');
        }
    }
    
    /**
     * 验证整数
     */
    private function validateInteger($field, $value) {
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_INT)) {
            $this->addError($field, 'integer', '字段 :field 必须是整数');
        }
    }
    
    /**
     * 验证布尔值
     */
    private function validateBoolean($field, $value) {
        if ($value !== null && !in_array($value, [true, false, 0, 1, '0', '1'], true)) {
            $this->addError($field, 'boolean', '字段 :field 必须是布尔值');
        }
    }
    
    /**
     * 验证数组
     */
    private function validateArray($field, $value) {
        if ($value !== null && !is_array($value)) {
            $this->addError($field, 'array', '字段 :field 必须是数组');
        }
    }
    
    /**
     * 验证JSON格式
     */
    private function validateJson($field, $value) {
        if ($value !== null && $value !== '' && !is_array(json_decode($value, true))) {
            $this->addError($field, 'json', '字段 :field 不是有效的JSON');
        }
    }
    
    /**
     * 验证日期格式
     */
    private function validateDate($field, $value) {
        if ($value !== null && $value !== '') {
            $date = DateTime::createFromFormat('Y-m-d', $value);
            if (!$date || $date->format('Y-m-d') !== $value) {
                $this->addError($field, 'date', '字段 :field 日期格式不正确');
            }
        }
    }
    
    /**
     * 验证日期时间格式
     */
    private function validateDatetime($field, $value) {
        if ($value !== null && $value !== '') {
            $date = DateTime::createFromFormat('Y-m-d H:i:s', $value);
            if (!$date || $date->format('Y-m-d H:i:s') !== $value) {
                $this->addError($field, 'datetime', '字段 :field 日期时间格式不正确');
            }
        }
    }
    
    /**
     * 验证IP地址
     */
    private function validateIp($field, $value) {
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_IP)) {
            $this->addError($field, 'ip', '字段 :field 不是有效的IP地址');
        }
    }
    
    /**
     * 验证手机号
     */
    private function validatePhone($field, $value) {
        if ($value !== null && $value !== '' && !preg_match('/^1[3-9]\d{9}$/', $value)) {
            $this->addError($field, 'phone', '字段 :field 手机号格式不正确');
        }
    }
    
    /**
     * 验证UUID
     */
    private function validateUuid($field, $value) {
        if ($value !== null && $value !== '' && !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value)) {
            $this->addError($field, 'uuid', '字段 :field 不是有效的UUID');
        }
    }
    
    /**
     * 验证十六进制
     */
    private function validateHex($field, $value) {
        if ($value !== null && $value !== '' && !preg_match('/^[0-9a-f]+$/i', $value)) {
            $this->addError($field, 'hex', '字段 :field 不是有效的十六进制');
        }
    }
    
    /**
     * 验证slug格式
     */
    private function validateSlug($field, $value) {
        if ($value !== null && $value !== '' && !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value)) {
            $this->addError($field, 'slug', '字段 :field 格式不正确（只能包含小写字母、数字和连字符）');
        }
    }
    
    /**
     * 验证字母
     */
    private function validateAlpha($field, $value) {
        if ($value !== null && $value !== '' && !preg_match('/^[a-zA-Z]+$/', $value)) {
            $this->addError($field, 'alpha', '字段 :field 只能包含字母');
        }
    }
    
    /**
     * 验证字母数字
     */
    private function validateAlphanumeric($field, $value) {
        if ($value !== null && $value !== '' && !preg_match('/^[a-zA-Z0-9]+$/', $value)) {
            $this->addError($field, 'alphanumeric', '字段 :field 只能包含字母和数字');
        }
    }
    
    /**
     * 验证密码强度
     */
    private function validatePassword($field, $value) {
        if ($value !== null && $value !== '') {
            $errors = [];
            
            if (strlen($value) < 8) {
                $errors[] = '至少8个字符';
            }
            if (!preg_match('/[a-z]/', $value)) {
                $errors[] = '包含小写字母';
            }
            if (!preg_match('/[A-Z]/', $value)) {
                $errors[] = '包含大写字母';
            }
            if (!preg_match('/[0-9]/', $value)) {
                $errors[] = '包含数字';
            }
            
            if (!empty($errors)) {
                $this->addError($field, 'password', '字段 :field ' . implode('、', $errors));
            }
        }
    }
    
    /**
     * 验证确认字段
     */
    private function validateConfirmed($field, $value) {
        $confirmField = $field . '_confirmation';
        if ($value !== null && $value !== '' && (!isset($this->data[$confirmField]) || $this->data[$confirmField] !== $value)) {
            $this->addError($field, 'confirmed', '字段 :field 两次输入不一致');
        }
    }
    
    /**
     * 验证最小值
     */
    private function validateMin($field, $value, $min) {
        if ($value !== null && $value !== '') {
            if (is_numeric($value)) {
                if ((float)$value < (float)$min) {
                    $this->addError($field, 'min', "字段 :field 不能小于 {$min}");
                }
            } else {
                if (strlen($value) < (int)$min) {
                    $this->addError($field, 'min', "字段 :field 长度不能小于 {$min}");
                }
            }
        }
    }
    
    /**
     * 验证最大值
     */
    private function validateMax($field, $value, $max) {
        if ($value !== null && $value !== '') {
            if (is_numeric($value)) {
                if ((float)$value > (float)$max) {
                    $this->addError($field, 'max', "字段 :field 不能大于 {$max}");
                }
            } else {
                if (strlen($value) > (int)$max) {
                    $this->addError($field, 'max', "字段 :field 长度不能大于 {$max}");
                }
            }
        }
    }
    
    /**
     * 验证固定长度
     */
    private function validateLength($field, $value, $length) {
        if ($value !== null && $value !== '' && strlen($value) != (int)$length) {
            $this->addError($field, 'length', "字段 :field 长度必须为 {$length}");
        }
    }
    
    /**
     * 验证范围
     */
    private function validateBetween($field, $value, $range) {
        list($min, $max) = explode(',', $range);
        
        if ($value !== null && $value !== '') {
            if (is_numeric($value)) {
                if ((float)$value < (float)$min || (float)$value > (float)$max) {
                    $this->addError($field, 'between', "字段 :field 必须在 {$min} 和 {$max} 之间");
                }
            } else {
                $len = strlen($value);
                if ($len < (int)$min || $len > (int)$max) {
                    $this->addError($field, 'between', "字段 :field 长度必须在 {$min} 和 {$max} 之间");
                }
            }
        }
    }
    
    /**
     * 验证是否在列表中
     */
    private function validateIn($field, $value, $list) {
        $options = explode(',', $list);
        
        if ($value !== null && $value !== '' && !in_array($value, $options)) {
            $this->addError($field, 'in', "字段 :field 必须是 " . implode('、', $options) . " 之一");
        }
    }
    
    /**
     * 验证是否不在列表中
     */
    private function validateNotIn($field, $value, $list) {
        $options = explode(',', $list);
        
        if ($value !== null && in_array($value, $options)) {
            $this->addError($field, 'not_in', "字段 :field 不能是 " . implode('、', $options));
        }
    }
    
    /**
     * 验证正则表达式
     */
    private function validateRegex($field, $value, $pattern) {
        if ($value !== null && $value !== '' && !preg_match("/{$pattern}/", $value)) {
            $this->addError($field, 'regex', '字段 :field 格式不正确');
        }
    }
    
    /**
     * 验证是否与另一个字段相同
     */
    private function validateSame($field, $value, $otherField) {
        if ($value !== null && (!isset($this->data[$otherField]) || $this->data[$otherField] !== $value)) {
            $this->addError($field, 'same', "字段 :field 必须与 {$otherField} 相同");
        }
    }
    
    /**
     * 验证是否与另一个字段不同
     */
    private function validateDifferent($field, $value, $otherField) {
        if ($value !== null && isset($this->data[$otherField]) && $this->data[$otherField] === $value) {
            $this->addError($field, 'different', "字段 :field 必须与 {$otherField} 不同");
        }
    }
    
    /**
     * 验证文件大小
     */
    private function validateSize($field, $value, $size) {
        if (is_array($value) && isset($value['size'])) {
            $bytes = $this->parseSize($size);
            if ($value['size'] > $bytes) {
                $this->addError($field, 'size', "字段 :field 大小不能超过 {$size}");
            }
        }
    }
    
    /**
     * 解析大小字符串为字节数
     */
    private function parseSize($size) {
        $units = ['b' => 1, 'kb' => 1024, 'mb' => 1024 * 1024, 'gb' => 1024 * 1024 * 1024];
        $size = strtolower(trim($size));
        
        if (preg_match('/^(\d+)([bkmgtp]b?)$/', $size, $matches)) {
            return (int)$matches[1] * $units[$matches[2]];
        }
        
        return (int)$size;
    }
    
    /**
     * 添加错误信息
     */
    private function addError($field, $rule, $message) {
        // 检查是否有自定义错误消息
        $key = "{$field}.{$rule}";
        if (isset($this->customMessages[$key])) {
            $message = $this->customMessages[$key];
        }
        
        // 替换占位符
        $message = str_replace(':field', $field, $message);
        
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        
        $this->errors[$field][] = $message;
    }
    
    /**
     * 获取所有错误信息
     * 
     * @return array 错误信息数组
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * 获取第一个错误信息
     * 
     * @return string|null 第一个错误消息
     */
    public function getFirstError() {
        foreach ($this->errors as $fieldErrors) {
            return $fieldErrors[0];
        }
        return null;
    }
    
    /**
     * 获取指定字段的错误信息
     * 
     * @param string $field 字段名
     * @return array|null 该字段的错误信息
     */
    public function getFieldErrors($field) {
        return $this->errors[$field] ?? null;
    }
    
    /**
     * 检查是否有错误
     * 
     * @return bool 是否有错误
     */
    public function hasErrors() {
        return !empty($this->errors);
    }
    
    /**
     * 获取格式化的错误消息（按字段分组）
     * 
     * @return array 格式化的错误消息
     */
    public function getFormattedErrors() {
        $formatted = [];
        
        foreach ($this->errors as $field => $errors) {
            $formatted[$field] = implode(' ', $errors);
        }
        
        return $formatted;
    }
    
    /**
     * 静态方法：快速验证
     * 
     * @param array $data 数据
     * @param array $rules 规则
     * @param array $customMessages 自定义消息
     * @return array ['valid' => bool, 'errors' => array]
     */
    public static function make(array $data, array $rules, array $customMessages = []) {
        $validator = new self($data, $rules, $customMessages);
        $valid = $validator->validate();
        
        return [
            'valid' => $valid,
            'errors' => $validator->getErrors()
        ];
    }
}