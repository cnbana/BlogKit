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


/**
 * 钩子触发器辅助类
 * 提供便捷的方法来触发各种钩子
 * 
 * 使用方法：
 * HookTrigger::userLogin($user);
 * HookTrigger::articleCreate($article);
 */

class HookTrigger {
    
    /**
     * 用户相关钩子触发器
     */
    
    /**
     * 用户登录前
     * @param array $data 用户数据
     */
    public static function userLoginBefore($data) {
        return Hook::trigger(Hook::USER_LOGIN_BEFORE, $data);
    }
    
    /**
     * 用户登录后
     * @param array $user 用户信息
     */
    public static function userLoginAfter($user) {
        return Hook::trigger(Hook::USER_LOGIN_AFTER, $user);
    }
    
    /**
     * 用户登录失败
     * @param array $data 失败数据
     */
    public static function userLoginFailed($data) {
        return Hook::trigger(Hook::USER_LOGIN_FAILED, $data);
    }
    
    /**
     * 用户登出前
     * @param array $user 用户信息
     */
    public static function userLogoutBefore($user) {
        return Hook::trigger(Hook::USER_LOGOUT_BEFORE, $user);
    }
    
    /**
     * 用户登出后
     * @param array $user 用户信息
     */
    public static function userLogoutAfter($user) {
        return Hook::trigger(Hook::USER_LOGOUT_AFTER, $user);
    }
    
    /**
     * 用户注册前
     * @param array $data 注册数据
     */
    public static function userRegisterBefore($data) {
        return Hook::trigger(Hook::USER_REGISTER_BEFORE, $data);
    }
    
    /**
     * 用户注册后
     * @param array $user 用户信息
     */
    public static function userRegisterAfter($user) {
        return Hook::trigger(Hook::USER_REGISTER_AFTER, $user);
    }
    
    /**
     * 用户资料更新前
     * @param array $data 更新数据
     */
    public static function userProfileUpdateBefore($data) {
        return Hook::trigger(Hook::USER_PROFILE_UPDATE_BEFORE, $data);
    }
    
    /**
     * 用户资料更新后
     * @param array $user 用户信息
     */
    public static function userProfileUpdateAfter($user) {
        return Hook::trigger(Hook::USER_PROFILE_UPDATE_AFTER, $user);
    }
    
    /**
     * 用户密码修改前
     * @param array $data 修改数据
     */
    public static function userPasswordChangeBefore($data) {
        return Hook::trigger(Hook::USER_PASSWORD_CHANGE_BEFORE, $data);
    }
    
    /**
     * 用户密码修改后
     * @param array $user 用户信息
     */
    public static function userPasswordChangeAfter($user) {
        return Hook::trigger(Hook::USER_PASSWORD_CHANGE_AFTER, $user);
    }
    
    /**
     * 用户密码重置
     * @param array $user 用户信息
     */
    public static function userPasswordReset($user) {
        return Hook::trigger(Hook::USER_PASSWORD_RESET, $user);
    }
    
    /**
     * 用户删除前
     * @param array $user 用户信息
     */
    public static function userDeleteBefore($user) {
        return Hook::trigger(Hook::USER_DELETE_BEFORE, $user);
    }
    
    /**
     * 用户删除后
     * @param array $user 用户信息
     */
    public static function userDeleteAfter($user) {
        return Hook::trigger(Hook::USER_DELETE_AFTER, $user);
    }
    
    /**
     * 用户封禁前
     * @param array $user 用户信息
     */
    public static function userBanBefore($user) {
        return Hook::trigger(Hook::USER_BAN_BEFORE, $user);
    }
    
    /**
     * 用户封禁后
     * @param array $user 用户信息
     */
    public static function userBanAfter($user) {
        return Hook::trigger(Hook::USER_BAN_AFTER, $user);
    }
    
    /**
     * 用户解封前
     * @param array $user 用户信息
     */
    public static function userUnbanBefore($user) {
        return Hook::trigger(Hook::USER_UNBAN_BEFORE, $user);
    }
    
    /**
     * 用户解封后
     * @param array $user 用户信息
     */
    public static function userUnbanAfter($user) {
        return Hook::trigger(Hook::USER_UNBAN_AFTER, $user);
    }
    
    /**
     * 用户关注前
     * @param array $data 关注数据
     */
    public static function userFollowBefore($data) {
        return Hook::trigger(Hook::USER_FOLLOW_BEFORE, $data);
    }
    
    /**
     * 用户关注后
     * @param array $data 关注数据
     */
    public static function userFollowAfter($data) {
        return Hook::trigger(Hook::USER_FOLLOW_AFTER, $data);
    }
    
    /**
     * 用户取消关注前
     * @param array $data 关注数据
     */
    public static function userUnfollowBefore($data) {
        return Hook::trigger(Hook::USER_UNFOLLOW_BEFORE, $data);
    }
    
    /**
     * 用户取消关注后
     * @param array $data 关注数据
     */
    public static function userUnfollowAfter($data) {
        return Hook::trigger(Hook::USER_UNFOLLOW_AFTER, $data);
    }
    
    /**
     * 文章相关钩子触发器
     */
    
    /**
     * 文章创建前
     * @param array $data 文章数据
     */
    public static function articleCreateBefore($data) {
        return Hook::trigger(Hook::ARTICLE_CREATE_BEFORE, $data);
    }
    
    /**
     * 文章创建后
     * @param array $article 文章信息
     */
    public static function articleCreateAfter($article) {
        return Hook::trigger(Hook::ARTICLE_CREATE_AFTER, $article);
    }
    
    /**
     * 文章更新前
     * @param array $data 更新数据
     */
    public static function articleUpdateBefore($data) {
        return Hook::trigger(Hook::ARTICLE_UPDATE_BEFORE, $data);
    }
    
    /**
     * 文章更新后
     * @param array $article 文章信息
     */
    public static function articleUpdateAfter($article) {
        return Hook::trigger(Hook::ARTICLE_UPDATE_AFTER, $article);
    }
    
    /**
     * 文章删除前
     * @param array $article 文章信息
     */
    public static function articleDeleteBefore($article) {
        return Hook::trigger(Hook::ARTICLE_DELETE_BEFORE, $article);
    }
    
    /**
     * 文章删除后
     * @param array $article 文章信息
     */
    public static function articleDeleteAfter($article) {
        return Hook::trigger(Hook::ARTICLE_DELETE_AFTER, $article);
    }
    
    /**
     * 文章恢复前
     * @param array $article 文章信息
     */
    public static function articleRestoreBefore($article) {
        return Hook::trigger(Hook::ARTICLE_RESTORE_BEFORE, $article);
    }
    
    /**
     * 文章恢复后
     * @param array $article 文章信息
     */
    public static function articleRestoreAfter($article) {
        return Hook::trigger(Hook::ARTICLE_RESTORE_AFTER, $article);
    }
    
    /**
     * 文章发布前
     * @param array $article 文章信息
     */
    public static function articlePublishBefore($article) {
        return Hook::trigger(Hook::ARTICLE_PUBLISH_BEFORE, $article);
    }
    
    /**
     * 文章发布后
     * @param array $article 文章信息
     */
    public static function articlePublishAfter($article) {
        return Hook::trigger(Hook::ARTICLE_PUBLISH_AFTER, $article);
    }
    
    /**
     * 文章查看前
     * @param array $article 文章信息
     */
    public static function articleViewBefore($article) {
        return Hook::trigger(Hook::ARTICLE_VIEW_BEFORE, $article);
    }
    
    /**
     * 文章查看后
     * @param array $article 文章信息
     */
    public static function articleViewAfter($article) {
        return Hook::trigger(Hook::ARTICLE_VIEW_AFTER, $article);
    }
    
    /**
     * 文章点赞前
     * @param array $data 点赞数据
     */
    public static function articleLikeBefore($data) {
        return Hook::trigger(Hook::ARTICLE_LIKE_BEFORE, $data);
    }
    
    /**
     * 文章点赞后
     * @param array $data 点赞数据
     */
    public static function articleLikeAfter($data) {
        return Hook::trigger(Hook::ARTICLE_LIKE_AFTER, $data);
    }
    
    /**
     * 文章取消点赞前
     * @param array $data 点赞数据
     */
    public static function articleUnlikeBefore($data) {
        return Hook::trigger(Hook::ARTICLE_UNLIKE_BEFORE, $data);
    }
    
    /**
     * 文章取消点赞后
     * @param array $data 点赞数据
     */
    public static function articleUnlikeAfter($data) {
        return Hook::trigger(Hook::ARTICLE_UNLIKE_AFTER, $data);
    }
    
    /**
     * 文章收藏前
     * @param array $data 收藏数据
     */
    public static function articleFavoriteBefore($data) {
        return Hook::trigger(Hook::ARTICLE_FAVORITE_BEFORE, $data);
    }
    
    /**
     * 文章收藏后
     * @param array $data 收藏数据
     */
    public static function articleFavoriteAfter($data) {
        return Hook::trigger(Hook::ARTICLE_FAVORITE_AFTER, $data);
    }
    
    /**
     * 文章取消收藏前
     * @param array $data 收藏数据
     */
    public static function articleUnfavoriteBefore($data) {
        return Hook::trigger(Hook::ARTICLE_UNFAVORITE_BEFORE, $data);
    }
    
    /**
     * 文章取消收藏后
     * @param array $data 收藏数据
     */
    public static function articleUnfavoriteAfter($data) {
        return Hook::trigger(Hook::ARTICLE_UNFAVORITE_AFTER, $data);
    }
    
    /**
     * 文章搜索前
     * @param array $data 搜索数据
     */
    public static function articleSearchBefore($data) {
        return Hook::trigger(Hook::ARTICLE_SEARCH_BEFORE, $data);
    }
    
    /**
     * 文章搜索后
     * @param array $data 搜索结果
     */
    public static function articleSearchAfter($data) {
        return Hook::trigger(Hook::ARTICLE_SEARCH_AFTER, $data);
    }
    
    /**
     * 评论相关钩子触发器
     */
    
    /**
     * 评论添加前
     * @param array $data 评论数据
     */
    public static function commentAddBefore($data) {
        return Hook::trigger(Hook::COMMENT_ADD_BEFORE, $data);
    }
    
    /**
     * 评论添加后
     * @param array $comment 评论信息
     */
    public static function commentAddAfter($comment) {
        return Hook::trigger(Hook::COMMENT_ADD_AFTER, $comment);
    }
    
    /**
     * 评论更新前
     * @param array $data 更新数据
     */
    public static function commentUpdateBefore($data) {
        return Hook::trigger(Hook::COMMENT_UPDATE_BEFORE, $data);
    }
    
    /**
     * 评论更新后
     * @param array $comment 评论信息
     */
    public static function commentUpdateAfter($comment) {
        return Hook::trigger(Hook::COMMENT_UPDATE_AFTER, $comment);
    }
    
    /**
     * 评论删除前
     * @param array $comment 评论信息
     */
    public static function commentDeleteBefore($comment) {
        return Hook::trigger(Hook::COMMENT_DELETE_BEFORE, $comment);
    }
    
    /**
     * 评论删除后
     * @param array $comment 评论信息
     */
    public static function commentDeleteAfter($comment) {
        return Hook::trigger(Hook::COMMENT_DELETE_AFTER, $comment);
    }
    
    /**
     * 评论审核通过前
     * @param array $comment 评论信息
     */
    public static function commentApproveBefore($comment) {
        return Hook::trigger(Hook::COMMENT_APPROVE_BEFORE, $comment);
    }
    
    /**
     * 评论审核通过后
     * @param array $comment 评论信息
     */
    public static function commentApproveAfter($comment) {
        return Hook::trigger(Hook::COMMENT_APPROVE_AFTER, $comment);
    }
    
    /**
     * 评论拒绝前
     * @param array $comment 评论信息
     */
    public static function commentRejectBefore($comment) {
        return Hook::trigger(Hook::COMMENT_REJECT_BEFORE, $comment);
    }
    
    /**
     * 评论拒绝后
     * @param array $comment 评论信息
     */
    public static function commentRejectAfter($comment) {
        return Hook::trigger(Hook::COMMENT_REJECT_AFTER, $comment);
    }
    
    /**
     * 评论点赞前
     * @param array $data 点赞数据
     */
    public static function commentLikeBefore($data) {
        return Hook::trigger(Hook::COMMENT_LIKE_BEFORE, $data);
    }
    
    /**
     * 评论点赞后
     * @param array $data 点赞数据
     */
    public static function commentLikeAfter($data) {
        return Hook::trigger(Hook::COMMENT_LIKE_AFTER, $data);
    }
    
    /**
     * 评论取消点赞前
     * @param array $data 点赞数据
     */
    public static function commentUnlikeBefore($data) {
        return Hook::trigger(Hook::COMMENT_UNLIKE_BEFORE, $data);
    }
    
    /**
     * 评论取消点赞后
     * @param array $data 点赞数据
     */
    public static function commentUnlikeAfter($data) {
        return Hook::trigger(Hook::COMMENT_UNLIKE_AFTER, $data);
    }
    
    /**
     * 评论回复前
     * @param array $data 回复数据
     */
    public static function commentReplyBefore($data) {
        return Hook::trigger(Hook::COMMENT_REPLY_BEFORE, $data);
    }
    
    /**
     * 评论回复后
     * @param array $comment 评论信息
     */
    public static function commentReplyAfter($comment) {
        return Hook::trigger(Hook::COMMENT_REPLY_AFTER, $comment);
    }
    
    /**
     * 页面相关钩子触发器
     */
    
    /**
     * 页面创建前
     * @param array $data 页面数据
     */
    public static function pageCreateBefore($data) {
        return Hook::trigger(Hook::PAGE_CREATE_BEFORE, $data);
    }
    
    /**
     * 页面创建后
     * @param array $page 页面信息
     */
    public static function pageCreateAfter($page) {
        return Hook::trigger(Hook::PAGE_CREATE_AFTER, $page);
    }
    
    /**
     * 页面更新前
     * @param array $data 更新数据
     */
    public static function pageUpdateBefore($data) {
        return Hook::trigger(Hook::PAGE_UPDATE_BEFORE, $data);
    }
    
    /**
     * 页面更新后
     * @param array $page 页面信息
     */
    public static function pageUpdateAfter($page) {
        return Hook::trigger(Hook::PAGE_UPDATE_AFTER, $page);
    }
    
    /**
     * 页面删除前
     * @param array $page 页面信息
     */
    public static function pageDeleteBefore($page) {
        return Hook::trigger(Hook::PAGE_DELETE_BEFORE, $page);
    }
    
    /**
     * 页面删除后
     * @param array $page 页面信息
     */
    public static function pageDeleteAfter($page) {
        return Hook::trigger(Hook::PAGE_DELETE_AFTER, $page);
    }
    
    /**
     * 页面查看前
     * @param array $page 页面信息
     */
    public static function pageViewBefore($page) {
        return Hook::trigger(Hook::PAGE_VIEW_BEFORE, $page);
    }
    
    /**
     * 页面查看后
     * @param array $page 页面信息
     */
    public static function pageViewAfter($page) {
        return Hook::trigger(Hook::PAGE_VIEW_AFTER, $page);
    }
    
    /**
     * 分类相关钩子触发器
     */
    
    /**
     * 分类创建前
     * @param array $data 分类数据
     */
    public static function categoryCreateBefore($data) {
        return Hook::trigger(Hook::CATEGORY_CREATE_BEFORE, $data);
    }
    
    /**
     * 分类创建后
     * @param array $category 分类信息
     */
    public static function categoryCreateAfter($category) {
        return Hook::trigger(Hook::CATEGORY_CREATE_AFTER, $category);
    }
    
    /**
     * 分类更新前
     * @param array $data 更新数据
     */
    public static function categoryUpdateBefore($data) {
        return Hook::trigger(Hook::CATEGORY_UPDATE_BEFORE, $data);
    }
    
    /**
     * 分类更新后
     * @param array $category 分类信息
     */
    public static function categoryUpdateAfter($category) {
        return Hook::trigger(Hook::CATEGORY_UPDATE_AFTER, $category);
    }
    
    /**
     * 分类删除前
     * @param array $category 分类信息
     */
    public static function categoryDeleteBefore($category) {
        return Hook::trigger(Hook::CATEGORY_DELETE_BEFORE, $category);
    }
    
    /**
     * 分类删除后
     * @param array $category 分类信息
     */
    public static function categoryDeleteAfter($category) {
        return Hook::trigger(Hook::CATEGORY_DELETE_AFTER, $category);
    }
    
    /**
     * 标签相关钩子触发器
     */
    
    /**
     * 标签创建前
     * @param array $data 标签数据
     */
    public static function tagCreateBefore($data) {
        return Hook::trigger(Hook::TAG_CREATE_BEFORE, $data);
    }
    
    /**
     * 标签创建后
     * @param array $tag 标签信息
     */
    public static function tagCreateAfter($tag) {
        return Hook::trigger(Hook::TAG_CREATE_AFTER, $tag);
    }
    
    /**
     * 标签更新前
     * @param array $data 更新数据
     */
    public static function tagUpdateBefore($data) {
        return Hook::trigger(Hook::TAG_UPDATE_BEFORE, $data);
    }
    
    /**
     * 标签更新后
     * @param array $tag 标签信息
     */
    public static function tagUpdateAfter($tag) {
        return Hook::trigger(Hook::TAG_UPDATE_AFTER, $tag);
    }
    
    /**
     * 标签删除前
     * @param array $tag 标签信息
     */
    public static function tagDeleteBefore($tag) {
        return Hook::trigger(Hook::TAG_DELETE_BEFORE, $tag);
    }
    
    /**
     * 标签删除后
     * @param array $tag 标签信息
     */
    public static function tagDeleteAfter($tag) {
        return Hook::trigger(Hook::TAG_DELETE_AFTER, $tag);
    }
    
    /**
     * 系统相关钩子触发器
     */
    
    /**
     * 系统初始化
     */
    public static function systemInit() {
        return Hook::trigger(Hook::SYSTEM_INIT);
    }
    
    /**
     * 系统关闭
     */
    public static function systemShutdown() {
        return Hook::trigger(Hook::SYSTEM_SHUTDOWN);
    }
    
    /**
     * 系统缓存清除
     */
    public static function systemCacheClear() {
        return Hook::trigger(Hook::SYSTEM_CACHE_CLEAR);
    }
    
    /**
     * 系统配置更新
     * @param array $config 配置数据
     */
    public static function systemConfigUpdate($config) {
        return Hook::trigger(Hook::SYSTEM_CONFIG_UPDATE, $config);
    }
    
    /**
     * 系统错误
     * @param array $error 错误信息
     */
    public static function systemError($error) {
        return Hook::trigger(Hook::SYSTEM_ERROR, $error);
    }
    
    /**
     * 文件上传相关钩子触发器
     */
    
    /**
     * 文件上传前
     * @param array $data 上传数据
     */
    public static function fileUploadBefore($data) {
        return Hook::trigger(Hook::FILE_UPLOAD_BEFORE, $data);
    }
    
    /**
     * 文件上传后
     * @param array $data 上传数据
     */
    public static function fileUploadAfter($data) {
        return Hook::trigger(Hook::FILE_UPLOAD_AFTER, $data);
    }
    
    /**
     * 文件上传错误
     * @param array $error 错误信息
     */
    public static function fileUploadError($error) {
        return Hook::trigger(Hook::FILE_UPLOAD_ERROR, $error);
    }
    
    /**
     * 文件删除前
     * @param array $file 文件信息
     */
    public static function fileDeleteBefore($file) {
        return Hook::trigger(Hook::FILE_DELETE_BEFORE, $file);
    }
    
    /**
     * 文件删除后
     * @param array $file 文件信息
     */
    public static function fileDeleteAfter($file) {
        return Hook::trigger(Hook::FILE_DELETE_AFTER, $file);
    }
    
    /**
     * 通知相关钩子触发器
     */
    
    /**
     * 发送通知前
     * @param array $data 通知数据
     */
    public static function notificationSendBefore($data) {
        return Hook::trigger(Hook::NOTIFICATION_SEND_BEFORE, $data);
    }
    
    /**
     * 发送通知后
     * @param array $data 通知数据
     */
    public static function notificationSendAfter($data) {
        return Hook::trigger(Hook::NOTIFICATION_SEND_AFTER, $data);
    }
    
    /**
     * 通知已读
     * @param array $notification 通知信息
     */
    public static function notificationRead($notification) {
        return Hook::trigger(Hook::NOTIFICATION_READ, $notification);
    }
    
    /**
     * 邮件相关钩子触发器
     */
    
    /**
     * 发送邮件前
     * @param array $data 邮件数据
     */
    public static function emailSendBefore($data) {
        return Hook::trigger(Hook::EMAIL_SEND_BEFORE, $data);
    }
    
    /**
     * 发送邮件后
     * @param array $data 邮件数据
     */
    public static function emailSendAfter($data) {
        return Hook::trigger(Hook::EMAIL_SEND_AFTER, $data);
    }
    
    /**
     * 邮件发送错误
     * @param array $error 错误信息
     */
    public static function emailSendError($error) {
        return Hook::trigger(Hook::EMAIL_SEND_ERROR, $error);
    }
    
    /**
     * 短信相关钩子触发器
     */
    
    /**
     * 发送短信前
     * @param array $data 短信数据
     */
    public static function smsSendBefore($data) {
        return Hook::trigger(Hook::SMS_SEND_BEFORE, $data);
    }
    
    /**
     * 发送短信后
     * @param array $data 短信数据
     */
    public static function smsSendAfter($data) {
        return Hook::trigger(Hook::SMS_SEND_AFTER, $data);
    }
    
    /**
     * 短信发送错误
     * @param array $error 错误信息
     */
    public static function smsSendError($error) {
        return Hook::trigger(Hook::SMS_SEND_ERROR, $error);
    }
    
    /**
     * 管理后台相关钩子触发器
     */
    
    /**
     * 管理员登录前
     * @param array $data 登录数据
     */
    public static function adminLoginBefore($data) {
        return Hook::trigger(Hook::ADMIN_LOGIN_BEFORE, $data);
    }
    
    /**
     * 管理员登录后
     * @param array $admin 管理员信息
     */
    public static function adminLoginAfter($admin) {
        return Hook::trigger(Hook::ADMIN_LOGIN_AFTER, $admin);
    }
    
    /**
     * 管理员登录失败
     * @param array $data 失败数据
     */
    public static function adminLoginFailed($data) {
        return Hook::trigger(Hook::ADMIN_LOGIN_FAILED, $data);
    }
    
    /**
     * 管理员登出前
     * @param array $admin 管理员信息
     */
    public static function adminLogoutBefore($admin) {
        return Hook::trigger(Hook::ADMIN_LOGOUT_BEFORE, $admin);
    }
    
    /**
     * 管理员登出后
     * @param array $admin 管理员信息
     */
    public static function adminLogoutAfter($admin) {
        return Hook::trigger(Hook::ADMIN_LOGOUT_AFTER, $admin);
    }
    
    /**
     * SEO相关钩子触发器
     */
    
    /**
     * 生成SEO元数据
     * @param array $data 页面数据
     */
    public static function seoMetaGenerate($data) {
        return Hook::trigger(Hook::SEO_META_GENERATE, $data);
    }
    
    /**
     * 生成网站地图
     * @param array $data 网站数据
     */
    public static function seoSitemapGenerate($data) {
        return Hook::trigger(Hook::SEO_SITEMAP_GENERATE, $data);
    }
    
    /**
     * 生成robots.txt
     * @param array $data 网站数据
     */
    public static function seoRobotsGenerate($data) {
        return Hook::trigger(Hook::SEO_ROBOTS_GENERATE, $data);
    }
    
    /**
     * 管理后台相关钩子触发器
     */
    
    /**
     * 管理员菜单渲染
     * @param array $menu 菜单数据
     */
    public static function adminMenuRender($menu) {
        return Hook::trigger(Hook::ADMIN_MENU_RENDER, $menu);
    }
    
    /**
     * 管理员仪表盘渲染
     * @param array $dashboard 仪表盘数据
     */
    public static function adminDashboardRender($dashboard) {
        return Hook::trigger(Hook::ADMIN_DASHBOARD_RENDER, $dashboard);
    }
    
    /**
     * 管理员操作前
     * @param array $action 操作数据
     */
    public static function adminActionBefore($action) {
        return Hook::trigger(Hook::ADMIN_ACTION_BEFORE, $action);
    }
    
    /**
     * 管理员操作后
     * @param array $action 操作数据
     */
    public static function adminActionAfter($action) {
        return Hook::trigger(Hook::ADMIN_ACTION_AFTER, $action);
    }
    
    /**
     * 主题相关钩子触发器
     */
    
    /**
     * 主题激活前
     * @param array $theme 主题数据
     */
    public static function themeActivateBefore($theme) {
        return Hook::trigger(Hook::THEME_ACTIVATE_BEFORE, $theme);
    }
    
    /**
     * 主题激活后
     * @param array $theme 主题数据
     */
    public static function themeActivateAfter($theme) {
        return Hook::trigger(Hook::THEME_ACTIVATE_AFTER, $theme);
    }
    
    /**
     * 主题停用前
     * @param array $theme 主题数据
     */
    public static function themeDeactivateBefore($theme) {
        return Hook::trigger(Hook::THEME_DEACTIVATE_BEFORE, $theme);
    }
    
    /**
     * 主题停用时
     * @param array $theme 主题数据
     */
    public static function themeDeactivateAfter($theme) {
        return Hook::trigger(Hook::THEME_DEACTIVATE_AFTER, $theme);
    }
    
    /**
     * 主题设置更新
     * @param array $settings 设置数据
     */
    public static function themeSettingsUpdate($settings) {
        return Hook::trigger(Hook::THEME_SETTINGS_UPDATE, $settings);
    }
    
    /**
     * 主题定制
     * @param array $theme 主题数据
     */
    public static function themeCustomize($theme) {
        return Hook::trigger(Hook::THEME_CUSTOMIZE, $theme);
    }
    
    /**
     * 插件相关钩子触发器
     */
    
    /**
     * 插件安装前
     * @param array $plugin 插件数据
     */
    public static function pluginInstallBefore($plugin) {
        return Hook::trigger(Hook::PLUGIN_INSTALL_BEFORE, $plugin);
    }
    
    /**
     * 插件安装后
     * @param array $plugin 插件数据
     */
    public static function pluginInstallAfter($plugin) {
        return Hook::trigger(Hook::PLUGIN_INSTALL_AFTER, $plugin);
    }
    
    /**
     * 插件卸载前
     * @param array $plugin 插件数据
     */
    public static function pluginUninstallBefore($plugin) {
        return Hook::trigger(Hook::PLUGIN_UNINSTALL_BEFORE, $plugin);
    }
    
    /**
     * 插件卸载后
     * @param array $plugin 插件数据
     */
    public static function pluginUninstallAfter($plugin) {
        return Hook::trigger(Hook::PLUGIN_UNINSTALL_AFTER, $plugin);
    }
    
    /**
     * 插件激活前
     * @param array $plugin 插件数据
     */
    public static function pluginActivateBefore($plugin) {
        return Hook::trigger(Hook::PLUGIN_ACTIVATE_BEFORE, $plugin);
    }
    
    /**
     * 插件激活后
     * @param array $plugin 插件数据
     */
    public static function pluginActivateAfter($plugin) {
        return Hook::trigger(Hook::PLUGIN_ACTIVATE_AFTER, $plugin);
    }
    
    /**
     * 插件停用前
     * @param array $plugin 插件数据
     */
    public static function pluginDeactivateBefore($plugin) {
        return Hook::trigger(Hook::PLUGIN_DEACTIVATE_BEFORE, $plugin);
    }
    
    /**
     * 插件停用时
     * @param array $plugin 插件数据
     */
    public static function pluginDeactivateAfter($plugin) {
        return Hook::trigger(Hook::PLUGIN_DEACTIVATE_AFTER, $plugin);
    }
    
    /**
     * 插件配置更新
     * @param array $plugin 插件数据
     */
    public static function pluginConfigUpdate($plugin) {
        return Hook::trigger(Hook::PLUGIN_CONFIG_UPDATE, $plugin);
    }
    
    /**
     * 会员相关钩子触发器
     */
    
    /**
     * 会员等级变更
     * @param array $data 会员数据
     */
    public static function membershipLevelChange($data) {
        return Hook::trigger(Hook::MEMBERSHIP_LEVEL_CHANGE, $data);
    }
    
    /**
     * 会员到期
     * @param array $data 会员数据
     */
    public static function membershipExpire($data) {
        return Hook::trigger(Hook::MEMBERSHIP_EXPIRE, $data);
    }
    
    /**
     * 会员续费
     * @param array $data 会员数据
     */
    public static function membershipRenew($data) {
        return Hook::trigger(Hook::MEMBERSHIP_RENEW, $data);
    }
    
    /**
     * 支付相关钩子触发器
     */
    
    /**
     * 支付处理前
     * @param array $data 支付数据
     */
    public static function paymentProcessBefore($data) {
        return Hook::trigger(Hook::PAYMENT_PROCESS_BEFORE, $data);
    }
    
    /**
     * 支付处理后
     * @param array $data 支付数据
     */
    public static function paymentProcessAfter($data) {
        return Hook::trigger(Hook::PAYMENT_PROCESS_AFTER, $data);
    }
    
    /**
     * 支付成功
     * @param array $data 支付数据
     */
    public static function paymentSuccess($data) {
        return Hook::trigger(Hook::PAYMENT_SUCCESS, $data);
    }
    
    /**
     * 支付失败
     * @param array $data 支付数据
     */
    public static function paymentFailed($data) {
        return Hook::trigger(Hook::PAYMENT_FAILED, $data);
    }
    
    /**
     * 支付退款
     * @param array $data 支付数据
     */
    public static function paymentRefund($data) {
        return Hook::trigger(Hook::PAYMENT_REFUND, $data);
    }
    
    /**
     * 搜索相关钩子触发器
     */
    
    /**
     * 搜索查询前
     * @param array $data 搜索数据
     */
    public static function searchQueryBefore($data) {
        return Hook::trigger(Hook::SEARCH_QUERY_BEFORE, $data);
    }
    
    /**
     * 搜索查询后
     * @param array $data 搜索结果
     */
    public static function searchQueryAfter($data) {
        return Hook::trigger(Hook::SEARCH_QUERY_AFTER, $data);
    }
    
    /**
     * 搜索结果过滤
     * @param array $data 搜索结果
     */
    public static function searchResultsFilter($data) {
        return Hook::trigger(Hook::SEARCH_RESULTS_FILTER, $data);
    }
    
    /**
     * 缓存相关钩子触发器
     */
    
    /**
     * 缓存清除前
     * @param array $data 缓存数据
     */
    public static function cacheClearBefore($data) {
        return Hook::trigger(Hook::CACHE_CLEAR_BEFORE, $data);
    }
    
    /**
     * 缓存清除后
     * @param array $data 缓存数据
     */
    public static function cacheClearAfter($data) {
        return Hook::trigger(Hook::CACHE_CLEAR_AFTER, $data);
    }
    
    /**
     * 获取缓存
     * @param array $data 缓存数据
     */
    public static function cacheGet($data) {
        return Hook::trigger(Hook::CACHE_GET, $data);
    }
    
    /**
     * 设置缓存
     * @param array $data 缓存数据
     */
    public static function cacheSet($data) {
        return Hook::trigger(Hook::CACHE_SET, $data);
    }
    
    /**
     * 性能相关钩子触发器
     */
    
    /**
     * 性能优化
     * @param array $data 性能数据
     */
    public static function performanceOptimize($data) {
        return Hook::trigger(Hook::PERFORMANCE_OPTIMIZE, $data);
    }
    
    /**
     * 资源加载
     * @param array $data 资源数据
     */
    public static function resourceLoad($data) {
        return Hook::trigger(Hook::RESOURCE_LOAD, $data);
    }
    
    /**
     * 页面加载完成
     * @param array $data 页面数据
     */
    public static function pageLoadComplete($data) {
        return Hook::trigger(Hook::PAGE_LOAD_COMPLETE, $data);
    }
    
    /**
     * 多语言相关钩子触发器
     */
    
    /**
     * 语言切换
     * @param array $data 语言数据
     */
    public static function languageSwitch($data) {
        return Hook::trigger(Hook::LANGUAGE_SWITCH, $data);
    }
    
    /**
     * 翻译加载
     * @param array $data 翻译数据
     */
    public static function translationLoad($data) {
        return Hook::trigger(Hook::TRANSLATION_LOAD, $data);
    }
    
    /**
     * 翻译过滤
     * @param array $data 翻译数据
     */
    public static function translationFilter($data) {
        return Hook::trigger(Hook::TRANSLATION_FILTER, $data);
    }
    
    /**
     * 社交相关钩子触发器
     */
    
    /**
     * 社交分享
     * @param array $data 分享数据
     */
    public static function socialShare($data) {
        return Hook::trigger(Hook::SOCIAL_SHARE, $data);
    }
    
    /**
     * 社交登录
     * @param array $data 登录数据
     */
    public static function socialLogin($data) {
        return Hook::trigger(Hook::SOCIAL_LOGIN, $data);
    }
    
    /**
     * 社交连接
     * @param array $data 连接数据
     */
    public static function socialConnect($data) {
        return Hook::trigger(Hook::SOCIAL_CONNECT, $data);
    }
    
    /**
     * 统计相关钩子触发器
     */
    
    /**
     * 统计数据收集
     * @param array $data 统计数据
     */
    public static function statisticsCollect($data) {
        return Hook::trigger(Hook::STATISTICS_COLLECT, $data);
    }
    
    /**
     * 统计报表生成
     * @param array $data 统计数据
     */
    public static function statisticsReport($data) {
        return Hook::trigger(Hook::STATISTICS_REPORT, $data);
    }
    
    /**
     * API相关钩子触发器
     */
    
    /**
     * API请求前
     * @param array $data 请求数据
     */
    public static function apiRequestBefore($data) {
        return Hook::trigger(Hook::API_REQUEST_BEFORE, $data);
    }
    
    /**
     * API请求后
     * @param array $data 请求数据
     */
    public static function apiRequestAfter($data) {
        return Hook::trigger(Hook::API_REQUEST_AFTER, $data);
    }
    
    /**
     * API响应前
     * @param array $data 响应数据
     */
    public static function apiResponseBefore($data) {
        return Hook::trigger(Hook::API_RESPONSE_BEFORE, $data);
    }
    
    /**
     * API响应后
     * @param array $data 响应数据
     */
    public static function apiResponseAfter($data) {
        return Hook::trigger(Hook::API_RESPONSE_AFTER, $data);
    }
    
    /**
     * API错误
     * @param array $data 错误数据
     */
    public static function apiError($data) {
        return Hook::trigger(Hook::API_ERROR, $data);
    }
    
    /**
     * 模板相关钩子触发器
     */
    
    /**
     * 模板渲染前
     * @param array $data 模板数据
     */
    public static function templateRenderBefore($data) {
        return Hook::trigger(Hook::TEMPLATE_RENDER_BEFORE, $data);
    }
    
    /**
     * 模板渲染后
     * @param array $data 模板数据
     */
    public static function templateRenderAfter($data) {
        return Hook::trigger(Hook::TEMPLATE_RENDER_AFTER, $data);
    }
    
    /**
     * 模板变量赋值
     * @param array $data 变量数据
     */
    public static function templateAssign($data) {
        return Hook::trigger(Hook::TEMPLATE_ASSIGN, $data);
    }
    
    /**
     * 数据库相关钩子触发器
     */
    
    /**
     * 数据库查询前
     * @param array $data 查询数据
     */
    public static function databaseQueryBefore($data) {
        return Hook::trigger(Hook::DATABASE_QUERY_BEFORE, $data);
    }
    
    /**
     * 数据库查询后
     * @param array $data 查询数据
     */
    public static function databaseQueryAfter($data) {
        return Hook::trigger(Hook::DATABASE_QUERY_AFTER, $data);
    }
    
    /**
     * 数据库错误
     * @param array $data 错误数据
     */
    public static function databaseError($data) {
        return Hook::trigger(Hook::DATABASE_ERROR, $data);
    }
    
    /**
     * 安全相关钩子触发器
     */
    
    /**
     * 安全检查
     * @param array $data 检查数据
     */
    public static function securityCheck($data) {
        return Hook::trigger(Hook::SECURITY_CHECK, $data);
    }
    
    /**
     * 安全breach
     * @param array $data 安全数据
     */
    public static function securityBreach($data) {
        return Hook::trigger(Hook::SECURITY_BREACH, $data);
    }
    
    /**
     * 安全登录尝试
     * @param array $data 登录数据
     */
    public static function securityLoginAttempt($data) {
        return Hook::trigger(Hook::SECURITY_LOGIN_ATTEMPT, $data);
    }
    
    /**
     * 系统相关钩子触发器
     */
    
    /**
     * 系统备份前
     * @param array $data 备份数据
     */
    public static function systemBackupBefore($data) {
        return Hook::trigger(Hook::SYSTEM_BACKUP_BEFORE, $data);
    }
    
    /**
     * 系统备份后
     * @param array $data 备份数据
     */
    public static function systemBackupAfter($data) {
        return Hook::trigger(Hook::SYSTEM_BACKUP_AFTER, $data);
    }
    
    /**
     * 系统恢复前
     * @param array $data 恢复数据
     */
    public static function systemRestoreBefore($data) {
        return Hook::trigger(Hook::SYSTEM_RESTORE_BEFORE, $data);
    }
    
    /**
     * 系统恢复后
     * @param array $data 恢复数据
     */
    public static function systemRestoreAfter($data) {
        return Hook::trigger(Hook::SYSTEM_RESTORE_AFTER, $data);
    }
    
    /**
     * 系统定时任务
     * @param array $data 任务数据
     */
    public static function systemCron($data) {
        return Hook::trigger(Hook::SYSTEM_CRON, $data);
    }
}

