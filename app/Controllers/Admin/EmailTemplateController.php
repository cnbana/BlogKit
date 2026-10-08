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
            // 加载模板列表（邮件模板属于配置类数据，同一类型最多一条记录，总量很小，
            // 因此一次取全量后在内存中做分页切片，无需为模型增加专门的分页查询方法）
            $allTemplates = $emailTemplateModel->getTemplates();
            // 确保是数组，防止后续 count/slice 出错
            if (!is_array($allTemplates)) {
                $allTemplates = [];
            }

            // ── 模板类型列筛选（type：空 = 全部；非法值回退全部，
            // 选项集合即 $templateTypes 的键，与表头列筛选面板共用同一份映射） ──
            $typeFilter = isset($_GET['type']) ? (string)$_GET['type'] : '';
            if ($typeFilter !== '' && !isset($templateTypes[$typeFilter])) {
                $typeFilter = '';
            }
            if ($typeFilter !== '') {
                $allTemplates = array_values(array_filter($allTemplates, function ($t) use ($typeFilter) {
                    return (string)($t['type'] ?? '') === $typeFilter;
                }));
            }

            // ── 表头排序（与文章/评论列表页同口径：sort 字段白名单 + order 方向白名单，
            // 默认 id 升序与模型原始返回顺序一致；updated_at 排序键与展示一致地兜底 created_at） ──
            $sort = isset($_GET['sort']) ? (string)$_GET['sort'] : 'id';
            if (!in_array($sort, ['id', 'updated_at'], true)) {
                $sort = 'id';
            }
            $order = isset($_GET['order']) ? strtolower((string)$_GET['order']) : 'asc';
            if (!in_array($order, ['asc', 'desc'], true)) {
                $order = 'asc';
            }
            usort($allTemplates, function ($a, $b) use ($sort, $order) {
                // 排序键取值：更新时间列展示「updated_at ?: created_at」，排序保持同一口径
                if ($sort === 'updated_at') {
                    $aVal = (int)($a['updated_at'] ?: $a['created_at']);
                    $bVal = (int)($b['updated_at'] ?: $b['created_at']);
                } else {
                    $aVal = (int)($a['id'] ?? 0);
                    $bVal = (int)($b['id'] ?? 0);
                }
                return $order === 'desc' ? $bVal - $aVal : $aVal - $bVal;
            });

            // ====== 分页参数（与 FriendlinkController 列表写法一致，limit 走白名单校验） ======
            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $pageSize = ListQuery::pageSize();
            $allowedPageSizes = ListQuery::pageSizes();
            $offset = ($page - 1) * $pageSize;
            // 全量数据切片出当前页，并计算总数与总页数（供公共分页组件使用）
            $templates = array_slice($allTemplates, $offset, $pageSize);
            $total = count($allTemplates);
            $totalPages = max(1, (int)ceil($total / $pageSize));

            // 分页链接透传的筛选/排序参数（组件会跳过空值；排序为默认值时不携带，保持链接简洁）
            $paginationFilters = ['type' => $typeFilter];
            if ($sort !== 'id') {
                $paginationFilters['sort'] = $sort;
            }
            if ($order !== 'asc') {
                $paginationFilters['order'] = $order;
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
        // （无「文章设置」子页：系统中不存在对应模板与保存逻辑，勿在此登记）
        $GLOBALS['subPages'] = [
            'basic' => '基本设置',
            'search' => '搜索设置',
            'email' => '邮箱配置',
            'user' => '用户设置',
            'comment' => '评论设置',
            'register' => '注册设置',
            'captcha' => '验证码设置',
            'login' => '登录设置',
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
            'templates' => isset($templates) ? $templates : [],
            // 分页变量（仅列表视图使用，供公共分页组件渲染）
            'page' => isset($page) ? $page : 1,
            'pageSize' => isset($pageSize) ? $pageSize : ListQuery::pageSize(),
            'allowedPageSizes' => isset($allowedPageSizes) ? $allowedPageSizes : ListQuery::pageSizes(),
            'total' => isset($total) ? $total : 0,
            'totalPages' => isset($totalPages) ? $totalPages : 1,
            // 表头排序当前值（供 sortLink 组件渲染三态高亮；仅列表视图使用）
            'sort' => isset($sort) ? $sort : 'id',
            'order' => isset($order) ? $order : 'asc',
            // 分页透传参数（type 筛选 + 非默认排序；供公共分页组件构建链接）
            'paginationFilters' => isset($paginationFilters) ? $paginationFilters : [],
            // 当前类型筛选值（供列表模板空态文案区分「无数据」与「筛选无结果」）
            'typeFilter' => isset($typeFilter) ? $typeFilter : ''
        ]);

        if ($showList) {
            // 列表视图：渲染列表化模板（page_wrapper 统一包装头部/侧边栏/闪存消息 Toast）
            include ADMIN_PATH . '/templates/email_template.html';
            include ADMIN_PATH . '/templates/components/footer_wrapper.html';
            exit;
        }

        // 表单视图（添加/编辑）：沿用原有整页渲染流程，编辑页跳转方式保持不变
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
            Log::info('邮件模板管理 - ' . $action . '模板：成功' . $action . '邮件模板: ' . $data['name'], Log::CATEGORY_OPERATION);
            // 成功后回到列表页（page_wrapper 的 flash_render 统一收集 $_SESSION['flash'] 并以 Toast 渲染）
            $_SESSION['flash']['success'] = '模板' . $action . '成功';
        } else {
            $action = $isEdit ? '编辑' : '添加';
            Log::error('邮件模板管理 - ' . $action . '模板：' . $action . '邮件模板失败: ' . $data['name'], Log::CATEGORY_OPERATION);
            // 失败时回到表单页（email_template_full.html 顶部提示块读取旧 key flash_error）
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
            Log::info('邮件模板管理 - 删除模板：成功删除邮件模板: ' . $template['name'] . ' (ID: ' . $id . ')', Log::CATEGORY_OPERATION);
        } else {
            Log::error('邮件模板管理 - 删除模板：删除邮件模板失败: ID ' . $id, Log::CATEGORY_OPERATION);
        }
        
        // 删除成功后跳转到模板列表页
        header('Location: admin.php?action=email_template');
        exit;
    }

    /**
     * 批量删除模板
     *
     * 行为与单条 delete() 一致：逐条调用 deleteTemplate() 删除记录并记录操作日志，
     * 区别在于接收 POST 提交的 ids[] 数组并校验 CSRF 令牌，
     * 执行完成后重定向回模板列表页（结果提示由列表页的 flash_render 渲染为 Toast）。
     */
    public function batchDelete() {
        // 仅允许 POST 请求（批量操作统一走隐藏表单提交，避免 GET 链接被误触/预取）
        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            header('Location: admin.php?action=email_template');
            exit;
        }

        // CSRF 令牌校验：与文章列表批量操作保持同一安全标准
        $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
        if (!Security::validateCsrfToken($token)) {
            $_SESSION['flash']['error'] = '安全验证失败，请刷新页面后重试';
            header('Location: admin.php?action=email_template');
            exit;
        }

        // 收集并转为整数数组，过滤掉非法 ID
        $ids = isset($_POST['ids']) ? array_map('intval', (array)$_POST['ids']) : [];
        $ids = array_values(array_filter($ids, function ($id) { return $id > 0; }));

        if (empty($ids)) {
            $_SESSION['flash']['error'] = '请选择要删除的模板';
            header('Location: admin.php?action=email_template');
            exit;
        }

        $templateModel = new EmailTemplateModel();
        $successCount = 0;
        foreach ($ids as $id) {
            // 与单条 delete() 一致：先取模板信息用于日志，再执行删除
            $template = $templateModel->find($id);
            $result = $templateModel->deleteTemplate($id);

            if ($result) {
                $successCount++;
                Log::info('邮件模板管理 - 批量删除模板：成功删除邮件模板: ' . (!empty($template['name']) ? $template['name'] : '未知模板') . ' (ID: ' . $id . ')', Log::CATEGORY_OPERATION);
            } else {
                Log::error('邮件模板管理 - 批量删除模板：删除邮件模板失败: ID ' . $id, Log::CATEGORY_OPERATION);
            }
        }

        // 闪存提示批量删除结果后回到列表页（PRG，无中间提示页）
        if ($successCount > 0) {
            $_SESSION['flash']['success'] = '成功删除 ' . $successCount . ' 个模板';
        } else {
            $_SESSION['flash']['error'] = '批量删除失败，请重试';
        }
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
            Log::info('邮件模板管理 - 设置默认模板：成功设置默认邮件模板: ' . $template['name'] . ' (ID: ' . $id . ')', Log::CATEGORY_OPERATION);
        } else {
            Log::error('邮件模板管理 - 设置默认模板：设置默认邮件模板失败: ID ' . $id, Log::CATEGORY_OPERATION);
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
        
        Log::info('邮件模板管理 - 预览模板：成功预览邮件模板: ' . $template['type'], Log::CATEGORY_OPERATION);
        
        echo json_encode(['success' => true, 'data' => $result]);
        exit;
    }
}
