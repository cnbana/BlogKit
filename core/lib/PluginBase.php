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
 * BlogKit 插件基础类
 * 
 * 所有插件应继承此类，获取系统服务和钩子注册能力
 * 
 * @package BlogKit
 * @subpackage Plugin
 */

class PluginBase {
    /** @var array 插件信息 */
    protected $plugin;
    
    /**
     * 构造函数
     * @param array $plugin 插件信息
     */
    public function __construct($plugin) {
        $this->plugin = $plugin;
        $this->init();
    }
    
    /**
     * 初始化插件（子类可重写）
     */
    public function init() {
        // 插件初始化逻辑，子类重写此方法
    }
    
    // ==================== 钩子系统 ====================
    
    /**
     * 注册钩子
     * @param string $hook 钩子名称
     * @param callable $callback 回调函数
     * @param int $priority 优先级（数值越小越先执行）
     */
    protected function addHook($hook, callable $callback, $priority = 10) {
        Plugin::getInstance()->addHook($hook, $callback, $priority);
    }
    
    /**
     * 获取插件信息
     * @return array
     */
    public function getInfo() {
        return $this->plugin;
    }
    
    // ==================== 生命周期 ====================
    
    /**
     * 插件安装时调用（子类可重写）
     */
    public function install() {
        // 安装逻辑，如创建数据库表
    }
    
    /**
     * 插件激活时调用（子类可重写）
     */
    public function activate() {
        // 激活逻辑
    }
    
    /**
     * 插件禁用时调用（子类可重写）
     */
    public function deactivate() {
        // 禁用逻辑
    }
    
    /**
     * 插件卸载时调用（子类可重写）
     * @param bool $keepData 是否保留数据
     */
    public function uninstall($keepData = false) {
        // 卸载逻辑
    }
    
    // ==================== 插件间通信 ====================
    
    /**
     * 调用其他插件的方法
     * @param string $slug 目标插件标识
     * @param string $method 方法名
     * @param mixed ...$args 参数
     * @return mixed
     */
    protected function callPlugin($slug, $method, ...$args) {
        return Plugin::getInstance()->callPluginMethod($slug, $method, $args);
    }
    
    /**
     * 触发其他插件的事件
     * @param string $slug 目标插件标识
     * @param string $event 事件名称
     * @param mixed $data 事件数据
     * @return mixed
     */
    protected function triggerPluginEvent($slug, $event, $data = null) {
        return Plugin::getInstance()->triggerPluginEvent($slug, $event, $data);
    }
    
    /**
     * 获取所有已激活插件
     * @return array
     */
    protected function getActivePlugins() {
        return Plugin::getInstance()->getActivePlugins();
    }
    
    /**
     * 获取指定插件实例
     * @param string $slug 插件标识
     * @return mixed
     */
    protected function getPlugin($slug) {
        return Plugin::getInstance()->getPlugin($slug);
    }
    
    // ==================== 系统服务访问 ====================
    
    /**
     * 获取系统服务
     * @param string $serviceName 服务名称
     * @return mixed
     */
    protected function getService($serviceName) {
        return Plugin::getInstance()->getSystemService($serviceName);
    }
    
    /** @return Database */
    protected function getDatabase() {
        return $this->getService('database');
    }
    
    /** @return Config */
    protected function getConfig() {
        return $this->getService('config');
    }
    
    /** @return Log */
    protected function getLog() {
        return $this->getService('log');
    }
    
    /** @return Cache */
    protected function getCache() {
        return $this->getService('cache');
    }
    
    /** @return Router */
    protected function getRouter() {
        return $this->getService('router');
    }
    
    /** @return Template */
    protected function getTemplate() {
        return $this->getService('template');
    }
    
    /** @return Plugin */
    protected function getPluginService() {
        return $this->getService('plugin');
    }
    
    // ==================== 日志便捷方法 ====================
    
    /**
     * 记录日志
     * @param string $level 日志级别
     * @param string $message 日志消息
     * @param array $context 上下文
     */
    protected function log($level, $message, $context = []) {
        $log = $this->getLog();
        if ($log) {
            $log->$level($message, $context);
        }
    }
    
    /** 记录调试日志 */
    protected function debug($message, $context = []) {
        $this->log('debug', $message, $context);
    }
    
    /** 记录信息日志 */
    protected function info($message, $context = []) {
        $this->log('info', $message, $context);
    }
    
    /** 记录警告日志 */
    protected function warning($message, $context = []) {
        $this->log('warning', $message, $context);
    }
    
    /** 记录错误日志 */
    protected function error($message, $context = []) {
        $this->log('error', $message, $context);
    }
    
    /** 记录严重错误日志 */
    protected function critical($message, $context = []) {
        $this->log('critical', $message, $context);
    }
}
