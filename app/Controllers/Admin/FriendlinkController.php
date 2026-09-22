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
 * 友情链接控制器
 * 处理友情链接的后台管理操作
 */

class FriendlinkController {
    private $friendlinkModel;
    
    /**
     * 构造函数
     */
    public function __construct() {
        $this->friendlinkModel = new FriendlinkModel();
    }
    
    /**
     * 显示友情链接列表
     */
    public function index() {
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        // 每页条数（limit 白名单校验）
        $limit = ListQuery::pageSize();
        $allowedPageSizes = ListQuery::pageSizes();
        $offset = ($page - 1) * $limit;
        
        // 获取友情链接列表
        $friendlinks = $this->friendlinkModel->getFriendlinks([], $limit, $offset);
        
        // 获取友情链接总数
        $total = $this->friendlinkModel->getFriendlinkCount();
        
        // 计算总页数
        $totalPages = ceil($total / $limit);
        
        // 显示友情链接列表页面
        include ADMIN_PATH . '/templates/friendlink.html';
    }
    
    /**
     * 显示添加友情链接页面
     */
    public function add() {
        include ADMIN_PATH . '/templates/friendlink_add.html';
    }
    
    /**
     * 保存添加的友情链接
     */
    public function save_add() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data = [
                'name' => $_POST['name'],
                'url' => $_POST['url'],
                'description' => $_POST['description'] ?? '',
                'logo' => $_POST['logo'] ?? '',
                'sort' => intval($_POST['sort'] ?? 0),
                'status' => isset($_POST['status']) ? 1 : 0
            ];
            
            // 验证数据
            if (empty($data['name'])) {
                $this->showMessage('友情链接名称不能为空', 'admin.php?action=friendlink&sub=add');
                return;
            }
            
            if (empty($data['url'])) {
                $this->showMessage('友情链接地址不能为空', 'admin.php?action=friendlink&sub=add');
                return;
            }
            
            // 添加友情链接
            $id = $this->friendlinkModel->addFriendlink($data);
            
            if ($id) {
                Log::info('友情链接管理', '添加友情链接', '成功添加友情链接: ' . $data['name'], Log::CATEGORY_OPERATION);
                $this->showMessage('友情链接添加成功', 'admin.php?action=friendlink');
            } else {
                Log::error('友情链接管理', '添加友情链接', '友情链接添加失败: ' . $data['name'], Log::CATEGORY_OPERATION);
                $this->showMessage('友情链接添加失败', 'admin.php?action=friendlink&sub=add');
            }
        }
    }
    
    /**
     * 显示编辑友情链接页面
     */
    public function edit() {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if ($id <= 0) {
            $this->showMessage('无效的友情链接ID', 'admin.php?action=friendlink');
            return;
        }
        
        // 获取友情链接信息
        $friendlink = $this->friendlinkModel->getFriendlinkById($id);
        
        if (!$friendlink) {
            $this->showMessage('友情链接不存在', 'admin.php?action=friendlink');
            return;
        }
        
        include ADMIN_PATH . '/templates/friendlink_edit.html';
    }
    
    /**
     * 保存编辑的友情链接
     */
    public function save_edit() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $id = intval($_POST['id']);
            
            if ($id <= 0) {
                $this->showMessage('无效的友情链接ID', 'admin.php?action=friendlink');
                return;
            }
            
            $data = [
                'name' => $_POST['name'],
                'url' => $_POST['url'],
                'description' => $_POST['description'] ?? '',
                'logo' => $_POST['logo'] ?? '',
                'sort' => intval($_POST['sort'] ?? 0),
                'status' => isset($_POST['status']) ? 1 : 0
            ];
            
            // 验证数据
            if (empty($data['name'])) {
                $this->showMessage('友情链接名称不能为空', 'admin.php?action=friendlink&sub=edit&id=' . $id);
                return;
            }
            
            if (empty($data['url'])) {
                $this->showMessage('友情链接地址不能为空', 'admin.php?action=friendlink&sub=edit&id=' . $id);
                return;
            }
            
            // 更新友情链接
            $result = $this->friendlinkModel->updateFriendlink($id, $data);
            
            if ($result) {
                Log::info('友情链接管理', '编辑友情链接', '成功编辑友情链接: ' . $data['name'] . ' (ID: ' . $id . ')', Log::CATEGORY_OPERATION);
                $this->showMessage('友情链接更新成功', 'admin.php?action=friendlink');
            } else {
                Log::error('友情链接管理', '编辑友情链接', '友情链接更新失败: ID ' . $id, Log::CATEGORY_OPERATION);
                $this->showMessage('友情链接更新失败', 'admin.php?action=friendlink&sub=edit&id=' . $id);
            }
        }
    }
    
    /**
     * 删除友情链接
     */
    public function delete() {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if ($id <= 0) {
            $this->showMessage('无效的友情链接ID', 'admin.php?action=friendlink');
            return;
        }
        
        // 删除友情链接
        $friendlink = $this->friendlinkModel->getFriendlinkById($id);
        $result = $this->friendlinkModel->deleteFriendlink($id);
        
        if ($result) {
            Log::info('友情链接管理', '删除友情链接', '成功删除友情链接: ' . $friendlink['name'] . ' (ID: ' . $id . ')', Log::CATEGORY_OPERATION);
            $this->showMessage('友情链接删除成功', 'admin.php?action=friendlink');
        } else {
            Log::error('友情链接管理', '删除友情链接', '友情链接删除失败: ID ' . $id, Log::CATEGORY_OPERATION);
            $this->showMessage('友情链接删除失败', 'admin.php?action=friendlink');
        }
    }
    
    /**
     * 批量删除友情链接
     */
    public function batch_delete() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $ids = isset($_POST['ids']) ? $_POST['ids'] : [];
            
            if (empty($ids)) {
                $this->showMessage('请选择要删除的友情链接', 'admin.php?action=friendlink');
                return;
            }
            
            // 批量删除友情链接
            $result = $this->friendlinkModel->batchDeleteFriendlinks($ids);
            
            if ($result) {
                Log::info('友情链接管理', '批量删除', '成功批量删除友情链接: ' . count($ids) . ' 条', Log::CATEGORY_OPERATION);
                $this->showMessage('友情链接批量删除成功', 'admin.php?action=friendlink');
            } else {
                Log::error('友情链接管理', '批量删除', '友情链接批量删除失败', Log::CATEGORY_OPERATION);
                $this->showMessage('友情链接批量删除失败', 'admin.php?action=friendlink');
            }
        }
    }
    
    /**
     * 处理子操作
     */
    public function handleSubAction() {
        if (isset($_GET['sub'])) {
            $subAction = $_GET['sub'];
            if (method_exists($this, $subAction)) {
                $this->$subAction();
            } else {
                $this->showMessage('操作不存在', 'admin.php?action=friendlink');
            }
        } else {
            $this->index();
        }
    }
    
    /**
     * 显示消息并跳转
     * @param string $message 消息内容
     * @param string $redirect 跳转地址
     */
    private function showMessage($message, $redirect) {
        echo "<script>alert('{$message}'); window.location.href = '{$redirect}';</script>";
        exit;
    }
}
