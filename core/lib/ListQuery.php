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
 * 后台列表查询参数助手
 *
 * 统一处理后台各管理页的两个公共参数，避免每个控制器重复实现：
 *   1. limit  —— 每页条数（白名单校验，非法值回退到默认配置）
 *   2. sort / order —— 表头排序（字段白名单 + 升降序白名单，防 SQL 注入）
 *
 * 使用示例（控制器 index 方法内）：
 *   $pageSize = ListQuery::pageSize();                       // 每页条数
 *   list($sort, $order) = ListQuery::sort(['id', 'created_at']); // 表头排序
 *
 * 模板侧约定（使用公共分页组件 pagination.html 时）：
 *   - 将 $limit、$pageSizes（可选集合）传给分页组件渲染下拉框
 *   - 将 sort/order 并入 $filters 以便翻页时保留排序状态
 *
 * @package BlogKit
 * @since 2.2.0
 */
class ListQuery
{
    /** @var array 每页条数可选项白名单（下拉框选项） */
    const PAGE_SIZES = [10, 20, 50, 100];

    /**
     * 获取每页条数
     * 优先读取 URL 的 limit 参数（必须在白名单内），否则回退到系统配置 admin_pagination_count
     * @return int 每页条数
     */
    public static function pageSize() {
        // 系统默认值（后台"基本设置"中的每页条数配置）
        $default = (int)Config::get('admin_pagination_count', 10);
        // 允许的选择集合：默认配置不在白名单时动态并入，保证下拉框能选中当前值
        $allowed = self::PAGE_SIZES;
        if (!in_array($default, $allowed)) {
            $allowed[] = $default;
            sort($allowed);
        }
        // URL 参数校验：仅接受白名单内的整数值
        if (isset($_GET['limit']) && in_array((int)$_GET['limit'], $allowed, true)) {
            return (int)$_GET['limit'];
        }
        return $default;
    }

    /**
     * 获取每页条数的可选集合（供分页组件渲染下拉框）
     * @return array 可选条数列表（升序）
     */
    public static function pageSizes() {
        $default = (int)Config::get('admin_pagination_count', 10);
        $allowed = self::PAGE_SIZES;
        if (!in_array($default, $allowed)) {
            $allowed[] = $default;
            sort($allowed);
        }
        return $allowed;
    }

    /**
     * 获取表头排序参数
     * @param array $whiteList 允许排序的字段白名单（如 ['id', 'created_at']）
     * @return array [sort字段名, 升降序]，非法值返回 ['', 'desc']（即不排序）
     */
    public static function sort($whiteList) {
        // 字段必须在调用方声明的白名单内
        $sort = (isset($_GET['sort']) && in_array($_GET['sort'], $whiteList)) ? $_GET['sort'] : '';
        // 升降序只允许 asc，其余一律按 desc 处理
        $order = (isset($_GET['order']) && strtolower($_GET['order']) === 'asc') ? 'asc' : 'desc';
        return [$sort, $order];
    }
}
