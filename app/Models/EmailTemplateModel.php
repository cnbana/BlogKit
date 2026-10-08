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


// 加载Model父类
require_once CORE_PATH . '/lib/Model.php';

/**
 * 邮件模板模型类
 * 用于管理邮件模板的数据库操作
 */
class EmailTemplateModel extends Model {
    /**
     * 构造函数
     */
    public function __construct() {
        parent::__construct('email_template');
    }
    
    /**
     * 获取模板列表
     * @param int $status 状态过滤（可选）
     * @return array 模板列表
     */
    public function getTemplates($status = null) {
        $where = [];
        if ($status !== null) {
            $where['status'] = $status;
        }
        
        return $this->get($where, ['id' => 'ASC']);
    }
    
    /**
     * 根据类型获取模板
     * @param string $type 模板类型
     * @return array|null 模板信息
     */
    public function getTemplateByType($type) {
        $result = $this->get(['type' => $type, 'status' => 1]);
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * 保存模板
     * @param array $data 模板数据
     * @return int 模板ID
     */
    public function saveTemplate($data) {
        $currentTime = time();
        
        // 设置默认值
        if (!isset($data['is_default'])) {
            $data['is_default'] = 0;
        }
        
        if (!isset($data['status'])) {
            $data['status'] = 1;
        }
        
        // 如果设置为默认模板，先将其他模板设为非默认
        if ($data['is_default'] == 1) {
            $this->update(['is_default' => 0], ['type' => $data['type']]);
        }
        
        if (isset($data['id']) && !empty($data['id'])) {
            // 更新模板
            $data['updated_at'] = $currentTime;
            $this->update($data, ['id' => $data['id']]);
            return $data['id'];
        } else {
            // 检查该类型的模板是否已经存在
            $existingTemplate = $this->get(['type' => $data['type']]);
            if (!empty($existingTemplate)) {
                // 如果存在，就更新它
                $templateId = $existingTemplate[0]['id'];
                $data['id'] = $templateId;
                $data['updated_at'] = $currentTime;
                $this->update($data, ['id' => $templateId]);
                return $templateId;
            } else {
                // 添加新模板
                $data['created_at'] = $currentTime;
                $data['updated_at'] = $currentTime;
                return $this->insert($data);
            }
        }
    }
    
    /**
     * 删除模板
     * @param int $id 模板ID
     * @return int 受影响行数
     */
    public function deleteTemplate($id) {
        return $this->delete(['id' => $id]);
    }
    
    /**
     * 设置默认模板
     * @param int $id 模板ID
     * @return bool 是否设置成功
     */
    public function setDefaultTemplate($id) {
        // 获取模板信息
        $template = $this->find($id);
        if (!$template) {
            return false;
        }
        
        // 事务处理
        $db = Database::getInstance();
        $db->beginTransaction();
        
        try {
            // 将所有同类型模板设为非默认
            $db->update('email_template', ['is_default' => 0], ['type' => $template['type']]);
            
            // 将当前模板设为默认
            $db->update('email_template', ['is_default' => 1], ['id' => $id]);
            
            $db->commit();
            return true;
        } catch (Exception $e) {
            $db->rollBack();
            return false;
        }
    }
    
    /**
     * 获取所有模板类型
     * @return array 模板类型列表
     */
    public function getTemplateTypes() {
        return [
            'registration' => '注册确认',
            'password_reset' => '密码重置',
            'comment_notify' => '评论通知',
            'article_approve' => '文章审核通知',
            'admin_notify' => '管理员通知',
            'user_notify' => '用户通知',
            'email_verification' => '邮箱验证'
        ];
    }
}
