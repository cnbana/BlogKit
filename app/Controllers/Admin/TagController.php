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


// 包含模型类
if (!class_exists('TagModel')) {
}
// 加载日志类

class TagController {
    public function index() {
        $tagModel = new TagModel();
        $tags = $tagModel->getAllTags();
        
        // 转换时间格式
        foreach ($tags as &$tag) {
            $tag['created_at'] = date('Y-m-d H:i:s', $tag['created_at']);
        }

        // ── 表头排序（本地全量数据，PHP 内存排序）──
        // 白名单校验（与 SQL 排序页面同口径的 ListQuery::sort 助手）：
        // 未指定排序时维持默认（文章数量降序、名称升序）
        list($sort, $order) = ListQuery::sort(['id', 'article_count', 'created_at', 'name']);
        if ($sort === '') {
            usort($tags, function($a, $b) {
                if ($a['article_count'] == $b['article_count']) {
                    return strcmp($a['name'], $b['name']);
                }
                return $b['article_count'] - $a['article_count'];
            });
        } else {
            // 指定排序字段：按升降序比较（数值/字符串通用），并列时按名称升序稳定排列
            $direction = ($order === 'asc') ? 1 : -1;
            usort($tags, function($a, $b) use ($sort, $direction) {
                if ($a[$sort] == $b[$sort]) {
                    return strcmp($a['name'], $b['name']);
                }
                return ($a[$sort] < $b[$sort]) ? -$direction : $direction;
            });
        }

        // ── 分页（服务端切页：标签列表为本地全量数据，此处按页切片）──
        // 标签会随文章增长而无限增多，与少量固定集合的分类不同，需要分页；
        // 每页条数走白名单（与插件/主题等分页页面同口径）
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $allowedLimits = [10, 20, 50, 100];
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        if (!in_array($limit, $allowedLimits, true)) {
            $limit = 20;
        }
        $total = count($tags);
        $totalPages = max(1, (int)ceil($total / $limit));
        if ($page > $totalPages) {
            $page = $totalPages; // 越界页码（如删除后条数变少）归位到末页
        }
        // 当前页切片：模板表格仅渲染本页数据
        $pagedTags = array_slice($tags, ($page - 1) * $limit, $limit);

        // 分页链接透传的筛选参数（排序状态随分页链接保留）
        $paginationFilters = [];
        if ($sort !== '') {
            $paginationFilters['sort'] = $sort;
            $paginationFilters['order'] = $order;
        }

        include ADMIN_PATH . '/templates/tag.html';
    }
    
    public function add() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $name = trim($_POST['name']);
            
            if (empty($name)) {
                respondWithToast('标签名称不能为空', 'back');
                exit;
            }
            
            $tagModel = new TagModel();
            if ($tagModel->createTag($name)) {
                Log::info('标签管理 - 添加标签：成功添加标签: ' . $name, Log::CATEGORY_OPERATION);
                respondWithToast('标签添加成功', 'admin.php?action=tag');
            } else {
                Log::error('标签管理 - 添加标签：添加标签失败: ' . $name, Log::CATEGORY_OPERATION);
                respondWithToast('标签添加失败', 'back');
            }
            exit;
        }
        
        include ADMIN_PATH . '/templates/tag_add.html';
    }
    
    public function edit() {
        $id = $_GET['id'] ?? 0;
        if (empty($id)) {
            respondWithToast('标签ID不能为空', 'admin.php?action=tag');
            exit;
        }
        
        $tagModel = new TagModel();
        $tag = $tagModel->getTagById($id);
        
        if (!$tag) {
            respondWithToast('标签不存在', 'admin.php?action=tag');
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $name = trim($_POST['name']);
            
            if (empty($name)) {
                respondWithToast('标签名称不能为空', 'back');
                exit;
            }
            
            if ($tagModel->updateTag($id, $name)) {
                Log::info('标签管理 - 编辑标签：成功编辑标签ID ' . $id . ': ' . $name, Log::CATEGORY_OPERATION);
                respondWithToast('标签更新成功', 'admin.php?action=tag');
            } else {
                Log::error('标签管理 - 编辑标签：编辑标签失败ID ' . $id, Log::CATEGORY_OPERATION);
                respondWithToast('标签更新失败', 'back');
            }
            exit;
        }
        
        include ADMIN_PATH . '/templates/tag_edit.html';
    }
    
    public function delete() {
        // 行内删除链接操作：闪存 + 重定向（PRG），无中间提示页
        $id = $_GET['id'] ?? 0;
        if (empty($id)) {
            respondFlash(false, '标签ID不能为空', 'admin.php?action=tag');
        }

        $tagModel = new TagModel();
        $tag = $tagModel->getTagById($id);
        if ($tagModel->deleteTag($id)) {
            Log::info('标签管理 - 删除标签：成功删除标签: ' . $tag['name'] . ' (ID: ' . $id . ')', Log::CATEGORY_OPERATION);
            respondFlash(true, '标签删除成功', 'admin.php?action=tag');
        } else {
            Log::error('标签管理 - 删除标签：删除标签失败ID ' . $id, Log::CATEGORY_OPERATION);
            respondFlash(false, '标签删除失败', 'admin.php?action=tag');
        }
    }

    /**
     * 批量删除标签
     *
     * 与单条 delete() 语义一致：逐条复用 TagModel::deleteTag()，
     * 该方法会先清除文章-标签关联记录（bk_article_tag）再删除标签本身，
     * 因此批量删除不会留下悬空的关联数据。循环内不做任何跳转，
     * 全部执行完后在返回消息中说明成功删除的数量。
     */
    public function batchDelete() {
        // 批量删除仅接受 POST 提交（前端 submitBatchForm 统一以 POST + csrf_token 提交）
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respondFlash(false, '非法请求', 'admin.php?action=tag');
        }

        // 检查 CSRF 令牌，防止跨站伪造的批量删除请求
        $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
        if (!Security::validateCsrfToken($token)) {
            respondFlash(false, '非法请求', 'admin.php?action=tag');
        }

        $ids = isset($_POST['ids']) ? $_POST['ids'] : [];
        if (empty($ids)) {
            respondFlash(false, '请选择要删除的标签', 'admin.php?action=tag');
        }

        $tagModel = new TagModel();
        $successCount = 0; // 成功删除的标签数量

        foreach ($ids as $id) {
            $id = (int)$id;
            // deleteTag 内部先删关联再删标签，返回值表示标签本身是否删除成功
            if ($id > 0 && $tagModel->deleteTag($id)) {
                $successCount++;
            }
        }

        // 记录批量删除日志（沿用本控制器的日志风格：模块 + 动作 + 说明 + 分类）
        if ($successCount > 0) {
            Log::info('标签管理 - 批量删除：成功批量删除标签 ' . $successCount . ' 个 (IDs: ' . implode(',', array_map('intval', $ids)) . ')', Log::CATEGORY_OPERATION);
        } else {
            Log::error('标签管理 - 批量删除：批量删除标签失败 (IDs: ' . implode(',', array_map('intval', $ids)) . ')', Log::CATEGORY_OPERATION);
        }

        // 汇总消息并回到标签列表页（闪存 + 重定向，由列表页 Toast 渲染结果）
        if ($successCount > 0) {
            respondFlash(true, '成功删除 ' . $successCount . ' 个标签', 'admin.php?action=tag');
        } else {
            respondFlash(false, '标签删除失败', 'admin.php?action=tag');
        }
    }
}
