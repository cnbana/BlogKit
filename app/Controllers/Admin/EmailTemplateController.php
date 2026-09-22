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


// 加载必要的模型和类

/**
 * 邮件模板管理控制器
 * 用于管理邮件模板的后台操作
 */
class EmailTemplateController {
    /**
     * 模板列表页
     */
    public function index() {
        // 模板类型映射
        $templateTypes = [
            'registration' => '注册确认',
            'password_reset' => '密码重置',
            'comment_notify' => '评论通知',
            'article_approve' => '文章审核',
            'admin_notify' => '管理员通知',
            'user_notify' => '用户通知',
            'email_verification' => '邮箱验证'
        ];
        
        // 初始化模板变量
        $template = null;
        $defaultTemplate = null;
        $showList = true; // 默认显示列表
        $method = isset($_GET['method']) ? $_GET['method'] : '';
        
        // 创建EmailTemplateModel实例
        $emailTemplateModel = new EmailTemplateModel();
        
        // 处理不同的子操作
        if ($method) {
            switch ($method) {
                case 'add':
                    // 显示添加表单
                    $showList = false;
                    // 获取模板类型
                    $type = isset($_GET['type']) ? $_GET['type'] : '';
                    
                    // 如果指定了类型，获取默认模板作为参考
                    if ($type) {
                        $defaultTemplate = EmailTemplate::getDefaultTemplate($type);
                    }
                    break;
                case 'edit':
                    // 显示编辑表单
                    $showList = false;
                    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
                    if ($id) {
                        $template = $emailTemplateModel->find($id);
                    }
                    break;
                // 其他方法（save, delete, setDefault, preview）由各自的方法处理
            }
        } else {
            // 显示列表
            $showList = true;
            // 加载模板列表
            $templates = $emailTemplateModel->getTemplates();
            // 确保templates是数组，防止foreach遍历错误
            if (!is_array($templates)) {
                $templates = [];
            }
        }
        
        // 设置模板变量，currentAction设为config，确保侧边栏"系统设置"菜单项为活动状态
        $GLOBALS['currentAction'] = 'config';
        $GLOBALS['templateTypes'] = $templateTypes;
        $GLOBALS['template'] = $template;
        $GLOBALS['defaultTemplate'] = $defaultTemplate;
        $GLOBALS['showList'] = $showList;
        $GLOBALS['templates'] = isset($templates) ? $templates : [];
        
        // 系统设置子菜单，与ConfigController保持一致
        $GLOBALS['subPages'] = [
            'basic' => '基本设置',
            'search' => '搜索设置',
            'email' => '邮箱配置',
            'user' => '用户设置',
            'article' => '文章设置',
            'comment' => '评论设置',
            'register' => '注册设置',
            'captcha' => '验证码设置',
            'login' => '登录设置',
            'feature' => '功能开关',
            'rewrite' => '伪静态设置',
            'seo' => 'SEO设置',
            'api' => 'API设置',
            'info' => '系统信息',
            'debug' => '调试设置'
        ];
        
        // 当前子页面，设置为email
        $GLOBALS['sub'] = 'email';
        
        // 设置模板变量
        $currentAction = 'config';
        $sub = 'email';
        $pageTitle = '邮件模板管理';
        
        // 使用extract函数将变量传递给模板
        extract([
            'currentAction' => $currentAction,
            'subPages' => $GLOBALS['subPages'],
            'sub' => $sub,
            'pageTitle' => $pageTitle,
            'templateTypes' => $templateTypes,
            'template' => $template,
            'defaultTemplate' => $defaultTemplate,
            'showList' => $showList,
            'templates' => isset($templates) ? $templates : []
        ]);
        
        // 渲染完整页面
        include ADMIN_PATH . '/templates/components/header.html';
        include ADMIN_PATH . '/templates/components/sidebar.html';
        include ADMIN_PATH . '/templates/email_template_full.html';
        include ADMIN_PATH . '/templates/components/footer.html';
        exit;
    }
    

    /**
     * 保存模板
     *
     * 说明：bk_email_template 表的 type 字段拥有唯一索引，
     * 所以同一类型最多只能存在一条记录。为了避免管理员在"新增模板"
     * 时不小心覆盖已有模板（saveTemplate 原本会静默 upsert），
     * 这里在保存前做一次显式检查：
     *   - 编辑操作（有 id）：允许覆盖同一条记录；
     *   - 新增操作（无 id）：如果 type 已存在，则提示冲突并拒绝保存。
     */
    public function save() {
        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            header('Location: admin.php?action=email_template');
            exit;
        }

        $templateModel = new EmailTemplateModel();

        // 获取表单数据
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'type' => trim($_POST['type'] ?? ''),
            'subject' => trim($_POST['subject'] ?? ''),
            'content' => $_POST['content'] ?? '',
            'is_default' => isset($_POST['is_default']) ? 1 : 0,
            'status' => isset($_POST['status']) ? 1 : 0
        ];

        // ====== 基础字段校验 ======
        if ($data['name'] === '' || $data['type'] === '' || $data['subject'] === '' || trim($data['content']) === '') {
            $_SESSION['flash_error'] = '请填写完整的模板名称、类型、主题和内容';
            header('Location: admin.php?action=email_template&method='
                . (isset($_POST['id']) && !empty($_POST['id']) ? 'edit&id=' . (int)$_POST['id'] : 'add'));
            exit;
        }

        $isEdit = !empty($_POST['id']);
        if ($isEdit) {
            $data['id'] = (int)$_POST['id'];
        }

        // ====== type 唯一性检查 ======
        if (!$isEdit) {
            // 新增：该 type 已经存在时，拒绝保存并提示用户去"编辑"该模板
            $existing = $templateModel->get(['type' => $data['type']]);
            if (!empty($existing)) {
                $existingName = !empty($existing[0]['name']) ? $existing[0]['name'] : '（无名称）';
                $_SESSION['flash_error'] =
                    '该类型的邮件模板已经存在（名称：' . htmlspecialchars($existingName, ENT_QUOTES, 'UTF-8') . '），'
                    . '请前往列表页点击"编辑"，而不是"新增"。';
                header('Location: admin.php?action=email_template&method=add&type=' . urlencode($data['type']));
                exit;
            }
        }

        // 保存模板
        $templateId = $templateModel->saveTemplate($data);

        if ($templateId) {
            $action = $isEdit ? '编辑' : '添加';
            Log::info('邮件模板管理', $action . '模板', '成功' . $action . '邮件模板: ' . $data['name'], Log::CATEGORY_OPERATION);
            $_SESSION['flash_success'] = '模板' . $action . '成功';
        } else {
            $action = $isEdit ? '编辑' : '添加';
            Log::error('邮件模板管理', $action . '模板', $action . '邮件模板失败: ' . $data['name'], Log::CATEGORY_OPERATION);
            $_SESSION['flash_error'] = '模板' . $action . '失败，请检查字段后重试';
        }

        // 保存成功后跳转到模板列表页
        header('Location: admin.php?action=email_template');
        exit;
    }
    
    /**
     * 删除模板
     */
    public function delete() {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if (!$id) {
            header('Location: admin.php?action=email_template');
            exit;
        }
        
        $templateModel = new EmailTemplateModel();
        $template = $templateModel->find($id);
        $result = $templateModel->deleteTemplate($id);
        
        if ($result) {
            Log::info('邮件模板管理', '删除模板', '成功删除邮件模板: ' . $template['name'] . ' (ID: ' . $id . ')', Log::CATEGORY_OPERATION);
        } else {
            Log::error('邮件模板管理', '删除模板', '删除邮件模板失败: ID ' . $id, Log::CATEGORY_OPERATION);
        }
        
        // 删除成功后跳转到模板列表页
        header('Location: admin.php?action=email_template');
        exit;
    }
    
    /**
     * 设置默认模板
     */
    public function setDefault() {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if (!$id) {
            header('Location: admin.php?action=email_template');
            exit;
        }
        
        $templateModel = new EmailTemplateModel();
        $template = $templateModel->find($id);
        $result = $templateModel->setDefaultTemplate($id);
        
        if ($result) {
            Log::info('邮件模板管理', '设置默认模板', '成功设置默认邮件模板: ' . $template['name'] . ' (ID: ' . $id . ')', Log::CATEGORY_OPERATION);
        } else {
            Log::error('邮件模板管理', '设置默认模板', '设置默认邮件模板失败: ID ' . $id, Log::CATEGORY_OPERATION);
        }
        
        // 设置成功后跳转到模板列表页
        header('Location: admin.php?action=email_template');
        exit;
    }
    
    /**
     * 预览模板
     */
    public function preview() {
        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            echo json_encode(['success' => false, 'message' => '无效请求']);
            exit;
        }
        
        // 获取模板数据
        $template = [
            'type' => $_POST['type'],
            'subject' => $_POST['subject'],
            'content' => $_POST['content']
        ];
        
        // 预览模板
        $result = EmailTemplate::previewTemplate($template);
        
        Log::info('邮件模板管理', '预览模板', '成功预览邮件模板: ' . $template['type'], Log::CATEGORY_OPERATION);
        
        echo json_encode(['success' => true, 'data' => $result]);
        exit;
    }
}
