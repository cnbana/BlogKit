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
 * API控制器基类
 * 提供统一的API接口管理
 * 
 * 使用方法：
 * class MyApiController extends ApiController {
 *     public function getUser() {
 *         $this->validateRequired(['user_id']);
 *         $userId = $this->getParam('user_id');
 *         // 业务逻辑...
 *         $this->success(['user' => $userData]);
 *     }
 * }
 */

class ApiController {
    
    protected $requestMethod;
    protected $requestParams;
    protected $currentUser;
    protected $isAdmin;
    
    /**
     * 构造函数
     */
    public function __construct() {
        $this->requestMethod = $_SERVER['REQUEST_METHOD'];
        $this->requestParams = $this->getRequestParams();
        $this->currentUser = $this->getCurrentUser();
        // 修复无限递归问题，直接从当前用户获取管理员状态
        $this->isAdmin = !empty($this->currentUser) && $this->currentUser['role'] == 1;
        
        // 加载安全类
        if (file_exists(CORE_PATH . '/lib/Security.php')) {
        }
        
        // 验证CSRF令牌（对于非GET请求）
        if (!in_array($this->requestMethod, ['GET', 'HEAD', 'OPTIONS'])) {
            $this->validateCsrfToken();
        }
    }
    
    /**
     * 验证CSRF令牌
     */
    protected function validateCsrfToken() {
        $csrfToken = null;

        // 1. 优先从请求参数获取（POST表单 / JSON body中的 csrf_token）
        $csrfToken = $this->getParam('csrf_token');

        // 2. 从请求头获取（AJAX场景，支持多种header名）
        if (!$csrfToken) {
            // 遍历所有可能的header名
            $headerNames = [
                'HTTP_X_CSRF_TOKEN',
                'HTTP_X_XSRF_TOKEN',
                'HTTP_X_CSRFTOKEN',
            ];
            foreach ($headerNames as $name) {
                if (!empty($_SERVER[$name])) {
                    $csrfToken = $_SERVER[$name];
                    break;
                }
            }
        }

        // 3. getallheaders() 作为备选（某些服务器环境下 $_SERVER 不含自定义 header）
        if (!$csrfToken && function_exists('getallheaders')) {
            $headers = @getallheaders();
            if (is_array($headers)) {
                foreach ($headers as $name => $value) {
                    $lowerName = strtolower($name);
                    if ($lowerName === 'x-csrf-token' || $lowerName === 'x-xsrf-token' || $lowerName === 'x-csrftoken') {
                        $csrfToken = $value;
                        break;
                    }
                }
            }
        }

        // 验证 CSRF 令牌
        if (class_exists('Security') && !Security::validateCsrfToken($csrfToken)) {
            $this->error('CSRF令牌验证失败', 403);
        }
    }
    
    /**
     * 获取请求参数
     * @return array
     */
    protected function getRequestParams() {
        $params = [];

        if ($this->requestMethod === 'GET') {
            $params = $_GET;
        } elseif ($this->requestMethod === 'POST') {
            // 先尝试从 $_POST 获取（标准表单提交场景）
            $params = $_POST;

            // 检查Content-Type是否为JSON格式
            // Content-Type 的典型值：application/json、application/json; charset=utf-8
            $contentType = '';
            if (isset($_SERVER['CONTENT_TYPE'])) {
                $contentType = $_SERVER['CONTENT_TYPE'];
            } elseif (isset($_SERVER['HTTP_CONTENT_TYPE'])) {
                $contentType = $_SERVER['HTTP_CONTENT_TYPE'];
            }

            $isJsonRequest = (stripos($contentType, 'application/json') !== false);

            // 如果是JSON请求，或 $_POST为空，尝试解析JSON body
            if ($isJsonRequest || empty($params)) {
                // 注意：php://input只能读取一次，使用 @ 抑制错误
                $input = @file_get_contents('php://input');
                if (!empty($input)) {
                    $jsonParams = json_decode($input, true);
                    if (is_array($jsonParams)) {
                        $params = $jsonParams;
                    }
                }
            }
        } elseif ($this->requestMethod === 'PUT' || $this->requestMethod === 'DELETE') {
            $input = @file_get_contents('php://input');
            $params = json_decode($input, true) ?: [];
        }

        return $params;
    }
    
    /**
     * 获取单个参数
     * @param string $key 参数名
     * @param mixed $default 默认值
     * @return mixed
     */
    protected function getParam($key, $default = null) {
        return isset($this->requestParams[$key]) ? $this->requestParams[$key] : $default;
    }
    
    /**
     * 获取整数参数
     * @param string $key 参数名
     * @param int $default 默认值
     * @return int
     */
    protected function getIntParam($key, $default = 0) {
        $value = $this->getParam($key, $default);
        return is_numeric($value) ? (int)$value : $default;
    }

    /**
     * 获取分页每页数量
     * 优先使用请求参数 ?page_size=xx；未传时读取后台 api_pagination_default；
     * 超过 api_pagination_max 时被裁剪到上限，避免一次性拉取过大数量。
     * @return int 每页数量
     */
    protected function getPageSize() {
        $defaultSize = (int)Config::get('api_pagination_default', 20);
        $maxSize     = (int)Config::get('api_pagination_max', 100);
        $size        = $this->getIntParam('page_size', $defaultSize);
        if ($size < 1) $size = 1;
        if ($size > $maxSize) $size = $maxSize;
        return $size;
    }

    /**
     * 获取字符串参数
     * @param string $key 参数名
     * @param string $default 默认值
     * @return string
     */
    protected function getStringParam($key, $default = '') {
        $value = $this->getParam($key, $default);
        return is_string($value) ? trim($value) : $default;
    }
    
    /**
     * 获取数组参数
     * @param string $key 参数名
     * @param array $default 默认值
     * @return array
     */
    protected function getArrayParam($key, $default = []) {
        $value = $this->getParam($key, $default);
        return is_array($value) ? $value : $default;
    }
    
    /**
     * 获取布尔参数
     * @param string $key 参数名
     * @param bool $default 默认值
     * @return bool
     */
    protected function getBoolParam($key, $default = false) {
        $value = $this->getParam($key, $default);
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (bool)$value;
        }
        if (is_string($value)) {
            return in_array(strtolower($value), ['true', '1', 'yes', 'on']);
        }
        return $default;
    }
    
    /**
     * 验证必填参数
     * @param array $requiredParams 必填参数数组
     * @return bool
     */
    protected function validateRequired($requiredParams) {
        $missing = [];
        foreach ($requiredParams as $param) {
            if (!isset($this->requestParams[$param]) || $this->requestParams[$param] === '') {
                $missing[] = $param;
            }
        }
        
        if (!empty($missing)) {
            $this->error('缺少必填参数：' . implode(', ', $missing), 400);
            return false;
        }
        
        return true;
    }
    
    /**
     * 验证参数规则
     * @param array $rules 验证规则
     * @return bool
     */
    protected function validateRules($rules) {
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $value = $this->getParam($field);
            
            // 必填验证
            if (isset($rule['required']) && $rule['required'] && ($value === null || $value === '')) {
                $errors[$field] = $rule['message'] ?? $field . '不能为空';
                continue;
            }
            
            // 如果值为空且不是必填，跳过其他验证
            if ($value === null || $value === '') {
                continue;
            }
            
            // 类型验证
            if (isset($rule['type'])) {
                switch ($rule['type']) {
                    case 'int':
                        if (!is_numeric($value)) {
                            $errors[$field] = $rule['message'] ?? $field . '必须是整数';
                        }
                        break;
                    case 'email':
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[$field] = $rule['message'] ?? $field . '格式不正确';
                        }
                        break;
                    case 'url':
                        if (!filter_var($value, FILTER_VALIDATE_URL)) {
                            $errors[$field] = $rule['message'] ?? $field . '格式不正确';
                        }
                        break;
                    case 'date':
                        if (!strtotime($value)) {
                            $errors[$field] = $rule['message'] ?? $field . '格式不正确';
                        }
                        break;
                }
            }
            
            // 长度验证
            if (isset($rule['min']) && mb_strlen($value) < $rule['min']) {
                $errors[$field] = $rule['message'] ?? $field . '长度不能少于' . $rule['min'] . '个字符';
            }
            
            if (isset($rule['max']) && mb_strlen($value) > $rule['max']) {
                $errors[$field] = $rule['message'] ?? $field . '长度不能超过' . $rule['max'] . '个字符';
            }
            
            // 范围验证
            if (isset($rule['min_value']) && $value < $rule['min_value']) {
                $errors[$field] = $rule['message'] ?? $field . '不能小于' . $rule['min_value'];
            }
            
            if (isset($rule['max_value']) && $value > $rule['max_value']) {
                $errors[$field] = $rule['message'] ?? $field . '不能大于' . $rule['max_value'];
            }
            
            // 正则验证
            if (isset($rule['pattern']) && !preg_match($rule['pattern'], $value)) {
                $errors[$field] = $rule['message'] ?? $field . '格式不正确';
            }
            
            // 自定义验证
            if (isset($rule['callback']) && is_callable($rule['callback'])) {
                $result = call_user_func($rule['callback'], $value);
                if ($result !== true) {
                    $errors[$field] = is_string($result) ? $result : ($rule['message'] ?? $field . '验证失败');
                }
            }
        }
        
        if (!empty($errors)) {
            $this->error('参数验证失败', 400, $errors);
            return false;
        }
        
        return true;
    }
    
    /**
     * 检查用户是否登录
     * @return bool
     */
    protected function isLogin() {
        return !empty($this->currentUser);
    }
    
    /**
     * 检查是否为管理员
     * @return bool
     */
    protected function isAdmin() {
        return !empty($this->isAdmin);
    }
    
    /**
     * 获取当前用户
     * @return array|null
     */
    protected function getCurrentUser() {
        // 先从会话获取用户（用于浏览器访问API的情况）
        if (isset($_SESSION['user'])) {
            return $_SESSION['user'];
        }
        
        // 根据API认证类型进行认证
        $authType = Config::get('api_auth_type', 'session');
        
        switch ($authType) {
            case 'session':
                // 会话认证，直接返回null，因为已经在上面检查过会话
                return null;
            case 'api_key':
                return $this->authenticateByApiKey();
            default:
                return null;
        }
    }
    
    /**
     * 通过API Key认证
     * @return array|null
     */
    protected function authenticateByApiKey() {
        // 从请求头获取API Key
        $apiKey = $_SERVER['HTTP_API_KEY'] ?? null;
        // 从请求参数获取API Key（备用）
        if (!$apiKey) {
            $apiKey = $_GET['api_key'] ?? $_POST['api_key'] ?? null;
        }
        
        if (!$apiKey) {
            $this->error('未提供API Key', 401);
            return null;
        }
        
        // 实现API Key认证逻辑
        $validationResult = ApiKeyModel::validate($apiKey);
        
        if ($validationResult['valid']) {
            // 返回一个默认的管理员用户信息，因为API Key认证不再关联具体用户
            return [
                'id' => 0,
                'username' => 'api_user',
                'role' => 1, // 管理员角色
                'status' => 1
            ];
        } else {
            // 返回详细的错误信息
            $this->error($validationResult['error'], 401);
            return null;
        }
    }
    
    /**
     * 获取管理员
     * @return array|null
     */
    protected function getAdmin() {
        return isset($_SESSION['admin']) ? $_SESSION['admin'] : null;
    }
    
    /**
     * 检查权限
     * @param string $permission 权限标识
     * @return bool
     */
    protected function checkPermission($permission) {
        if (!$this->isAdmin()) {
            return false;
        }
        
        return RoleModel::checkUserPermission($_SESSION['admin']['id'], $permission);
    }
    
    /**
     * 需要登录
     */
    protected function requireLogin() {
        if (!$this->isLogin()) {
            $this->error('请先登录', 401);
        }
    }
    
    /**
     * 需要管理员权限
     */
    protected function requireAdmin() {
        if (!$this->isAdmin()) {
            $this->error('需要管理员权限', 403);
        }
    }
    
    /**
     * 需要特定权限
     * @param string $permission 权限标识
     */
    protected function requirePermission($permission) {
        if (!$this->checkPermission($permission)) {
            $this->error('没有权限', 403);
        }
    }
    
    /**
     * 成功响应
     * @param mixed $data 响应数据
     * @param string $message 成功消息
     * @param int $code 状态码
     */
    protected function success($data = null, $message = '操作成功', $code = 200) {
        $this->jsonResponse([
            'code' => $code,
            'message' => $message,
            'data' => $data
        ]);
    }
    
    /**
     * 错误响应
     * @param string $message 错误消息
     * @param int $code 状态码
     * @param mixed $errors 错误详情
     */
    protected function error($message = '操作失败', $code = 500, $errors = null) {
        $response = [
            'code' => $code,
            'message' => $message
        ];
        
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        
        $this->jsonResponse($response);
    }
    
    /**
     * 分页响应
     * @param array $items 数据项
     * @param int $total 总数
     * @param int $page 当前页
     * @param int $pageSize 每页数量
     * @param string $message 成功消息
     */
    protected function paginate($items, $total, $page, $pageSize, $message = '获取成功') {
        $totalPages = ceil($total / $pageSize);
        
        $this->success([
            'items' => $items,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'page_size' => $pageSize,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1
            ]
        ], $message);
    }
    
    /**
     * JSON响应
     * @param array $data 响应数据
     */
    protected function jsonResponse($data) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * 触发API钩子
     * @param string $hookName 钩子名称
     * @param mixed $data 钩子数据
     */
    protected function triggerHook($hookName, $data = null) {
        if (class_exists('Hook')) {
            Hook::trigger($hookName, $data);
        }
    }
    

}
