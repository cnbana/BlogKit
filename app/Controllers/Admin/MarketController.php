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
 * 应用市场控制器（P3「在线安装」，方案 4.2；后台一键安装扩展见 doInstall）
 *
 * 主系统与官网打通的桥接层（全部免费，无购买校验）：
 * - index      应用列表：服务端 curl 调官网 /api/market/apps（带 site_key 与当前版本），
 *              按 version_code 过滤兼容版本，结果缓存 10 分钟（bk_config）；
 * - settings   市场设置：填写官网地址 + 站点密钥（site_key），保存即尝试绑定
 *              （POST /api/site/bind），显示绑定状态；
 * - install    安装/更新（官网 302 跳转入口）：官网 /apps/install 302 过来携一次性令牌
 *              （5 分钟有效），持令牌走 installByToken 安装管线；
 * - doInstall  安装/更新（后台一键安装入口）：列表页 POST app_id（+CSRF）→ 调官网
 *              POST /api/market/install-token（site_key 鉴权）签发一次性令牌 →
 *              同样走 installByToken 安装管线，全程不出后台（无需官网浏览器登录态）。
 *
 * 安装管线（installByToken，两条入口共用）：
 *   持令牌下载 zip → zip-slip 校验解压 → 读取清单（plugin.json/theme.json）
 *   → 清单 slug 校验 → 按类型分流到现有 Plugin 安装管线 / 主题注册
 *   → 装完回调 /api/market/installed（安装量+1）。
 *
 * 官网 API 地址约定：{market_api_url}/index.php/api/...（PATH_INFO 形态，无需伪静态）。
 * 配置存储 bk_config：market_api_url / market_site_key / market_cache / market_cache_time。
 *
 * @package BlogKit
 */
class MarketController
{
    /** @var int 应用列表缓存时长（秒，方案 4.2：10 分钟） */
    const CACHE_TTL = 600;

    /**
     * 入口：按 op 参数分流（index 列表 / settings 设置 / detail 详情子页 /
     * install 安装 / do_install 后台一键安装）
     * 注：Market 不在 admin.php 的 SUB_METHOD_CONTROLLERS 白名单内，
     * op 由本类内部路由，避免改动主系统白名单配置。
     */
    public function index()
    {
        $op = isset($_GET['op']) ? (string)$_GET['op'] : '';
        if ($op === 'settings') {
            $this->settings();
        } elseif ($op === 'detail') {
            $this->detail();
        } elseif ($op === 'install') {
            $this->install();
        } elseif ($op === 'do_install') {
            $this->doInstall();
        } else {
            $this->marketList();
        }
    }

    /**
     * 应用市场列表页（默认 op）——内嵌完整市场体验（卡片 + 搜索 + 类型 Tab + 分类筛选）
     *
     * 与官网 /apps 的能力对齐：搜索（kw）、类型 Tab（type）、分类筛选（cat），
     * 区别在于筛选全部在主系统本地完成——列表接口返回全量应用（10 分钟缓存，
     * 仪表盘 countUpdatableApps 依赖同份全量缓存），本地过滤零延迟且官网抖动时可用。
     * URL 参数（GET）：
     * - type：theme / plugin / 空 = 全部（Tab 高亮依据）
     * - cat ：分类名称（与 type 组合过滤；分类名在官网按类型分组，跨类型同名互不影响）
     * - kw  ：关键词（名称/简介/作者模糊匹配，超长截断 50 字）
     */
    private function marketList()
    {
        $notice = '';
        $bound = $this->siteKey() !== '';

        // GET 筛选参数规范化（与官网 /apps 同口径）
        $type = isset($_GET['type']) ? (string)$_GET['type'] : '';
        if (!in_array($type, ['theme', 'plugin', ''], true)) {
            $type = '';
        }
        $catId = isset($_GET['cat']) ? (string)$_GET['cat'] : '';
        $kw = isset($_GET['kw']) ? trim((string)$_GET['kw']) : '';
        if (mb_strlen($kw) > 50) {
            $kw = mb_substr($kw, 0, 50); // 防御性截断：LIKE 匹配无需超长词
        }

        $apps = []; // 官网返回的全量应用（缓存口径）
        // 未配置官网地址/密钥：提示先完成设置
        if ($this->apiUrl() === '' || !$bound) {
            $notice = '尚未绑定官网站点，请先在「市场设置」中填写官网地址与站点密钥。';
        } else {
            $cache = $this->readCache();
            if ($cache !== null) {
                $apps = $cache;
            } else {
                // 缓存过期：服务端拉官网列表（带当前版本号做兼容过滤）
                $version = $this->currentVersion();
                $resp = $this->httpGet($this->apiUrl() . '/index.php/api/market/apps'
                    . '?site_key=' . urlencode($this->siteKey())
                    . '&version=' . urlencode($version));
                $data = $this->parseApi($resp);
                if ($data !== null) {
                    $apps = $data['apps'];
                    $this->writeCache($apps);
                } else {
                    $notice = '无法连接官网应用市场，请检查官网地址与站点密钥。';
                }
            }
        }

        // ── 本地筛选（顺序：type 聚合分类 → cat 分类过滤 → kw 关键词过滤）──
        // 分类 chips 基于当前类型子集聚合（distinct 分类名，排除未分类），
        // 分类过滤用分类名匹配（API 返回的 category 为名称，无需 ID）
        $catRows = [];
        $counts = ['all' => 0, 'theme' => 0, 'plugin' => 0];
        $filtered = [];
        foreach ($apps as $app) {
            $t = ($app['type'] === 'theme') ? 'theme' : 'plugin';
            $counts[$t]++;
            $counts['all']++;
            // 1) 类型过滤（type='' 为全部 Tab，不筛）
            if ($type !== '' && $t !== $type) {
                continue;
            }
            // 聚合当前类型下的分类 chips（只统计未被 cat/kw 过滤的集合）
            $catName = isset($app['category']) ? (string)$app['category'] : '';
            if ($catName !== '' && !isset($catRows[$catName])) {
                $catRows[$catName] = ['name' => $catName, 'count' => 1];
            } elseif ($catName !== '') {
                $catRows[$catName]['count']++;
            }
            // 2) 分类过滤（cat='' 为「全部」chip，不筛）
            if ($catId !== '' && $catName !== $catId) {
                continue;
            }
            // 3) 关键词过滤：名称/简介/作者模糊匹配（与官网 getApps 同口径）
            if ($kw !== '' && mb_stripos((string)$app['name'] . ' '
                . (string)$app['description'] . ' ' . (string)$app['author'], $kw) === false) {
                continue;
            }
            $filtered[] = $app;
        }

        // 分类 chips 行数据：active 高亮在控制器层比对（"全部" chip 高亮由标量开关处理）
        $cats = [];
        foreach ($catRows as $c) {
            $cats[] = [
                'name'   => $c['name'],
                'count'  => $c['count'],
                // 高亮：当前分类筛选命中（查询串 cat 参数与分类名一致）
                'active' => ($catId === $c['name']) ? 'is-active' : '',
            ];
        }

        // 卡片行数据预计算：图片/占位、简介截断、安装状态徽章、安装按钮、详情链接
        // （分支判断全部在控制器层完成，模板只做循环与原样输出）
        $rows = [];
        foreach ($filtered as $app) {
            $rows[] = $this->formatCardRow($app);
        }

        // 空状态文案（有无关键词分支在控制器层拼好，模板不做嵌套判断）
        $emptyDesc = ($kw !== '')
            ? '没有找到与“' . htmlspecialchars($kw, ENT_QUOTES) . '”匹配的应用，换个关键词试试。'
            : '该分类下暂无上架应用，欢迎稍后再来。';

        // 主程序新版本检测（P1-9：官网 /api/latest-version，红点数据源，带缓存静默降级）
        list($latestVersion, $hasNewVersion) = $this->checkNewVersion();

        $currentPage = 'market';
        $pageTitle = '应用市场';
        include ADMIN_PATH . '/templates/market.html';
    }

    /**
     * 市场设置页（GET 表单 / POST 保存+绑定）
     */
    private function settings()
    {
        $db = Database::getInstance();

        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
            // CSRF 校验（复用全局 Security，与主题/插件管理同策略）
            if (!Security::validateCsrfToken(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
                $this->showMessage('安全校验失败，请刷新页面后重试', 'admin.php?action=market&op=settings');
            }
            $apiUrl = trim(isset($_POST['market_api_url']) ? $_POST['market_api_url'] : '');
            $siteKey = trim(isset($_POST['market_site_key']) ? $_POST['market_site_key'] : '');
            // 官网地址校验：http(s) 且去掉末尾斜杠（拼接 API 路径用）
            if ($apiUrl !== '' && (!preg_match('#^https?://#i', $apiUrl) || !filter_var($apiUrl, FILTER_VALIDATE_URL))) {
                $this->showMessage('官网地址格式不正确（需 http:// 或 https:// 开头）', 'admin.php?action=market&op=settings');
            }
            $apiUrl = rtrim($apiUrl, '/');
            // 站点密钥格式校验：32 位十六进制（官网「我的站点」生成的格式）
            if ($siteKey !== '' && !preg_match('/^[a-f0-9]{32}$/', $siteKey)) {
                $this->showMessage('站点密钥格式不正确（应为 32 位字符，请在官网「我的站点」复制）', 'admin.php?action=market&op=settings');
            }
            $this->saveConfig('market_api_url', $apiUrl);
            $this->saveConfig('market_site_key', $siteKey);
            // 密钥/地址变更后旧缓存立即失效
            $this->saveConfig('market_cache', '');
            $this->saveConfig('market_cache_time', 0);

            // 保存后立即尝试绑定（密钥非空时）：POST /api/site/bind 携站点 URL 与版本
            if ($siteKey !== '' && $apiUrl !== '') {
                $siteUrl = Config::get('site_url', '');
                $resp = $this->httpPost($apiUrl . '/index.php/api/site/bind', [
                    'site_key'     => $siteKey,
                    'site_url'     => $siteUrl,
                    'version'      => $this->currentVersion(),
                    'version_code' => $this->currentVersionCode(),
                ]);
                $data = $this->parseApi($resp);
                if ($data !== null) {
                    $this->showMessage('绑定成功：站点 ' . $data['site_url'] . ' 已连接官网', 'admin.php?action=market');
                }
                $this->showMessage('绑定失败：' . (isset($data['msg']) ? $data['msg'] : '无法连接官网'), 'admin.php?action=market&op=settings');
            }
            $this->showMessage('市场设置已保存', 'admin.php?action=market');
        }

        // GET：渲染设置页（回显当前配置 + 绑定状态）
        $currentPage = 'market';
        $pageTitle = '应用市场设置';
        $apiUrl = $this->apiUrl();
        $siteKey = $this->siteKey();
        // 绑定状态探测：密钥存在时轻量调一次列表接口（不带版本参数）验证密钥有效性
        $bindStatus = '未绑定';
        if ($apiUrl !== '' && $siteKey !== '') {
            $resp = $this->httpGet($apiUrl . '/index.php/api/market/apps?site_key=' . urlencode($siteKey));
            $bindStatus = ($this->parseApi($resp) !== null) ? '已绑定（密钥有效）' : '密钥无效或官网不可达';
        }
        include ADMIN_PATH . '/templates/market_settings.html';
    }

    /**
     * 安装/更新执行——官网 302 跳转入口
     * （官网 /apps/install 302 过来：op=install&token=...&app=N&version_id=N）
     *
     * 流程：参数校验 → 拉应用详情（type/slug/版本）→ 按 version_id 定位目标版本
     * → installByToken 安装管线（下载 → 解压 → 安装 → 回执）→ 弹窗回列表页。
     */
    private function install()
    {
        $back = 'admin.php?action=market';
        $token = isset($_GET['token']) ? (string)$_GET['token'] : '';
        $appId = isset($_GET['app']) ? (int)$_GET['app'] : 0;
        $versionId = isset($_GET['version_id']) ? (int)$_GET['version_id'] : 0;
        // 令牌格式预校验（64 位十六进制），避免无效请求打到官网
        if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token) || $appId <= 0 || $versionId <= 0) {
            $this->showMessage('安装参数无效', $back);
        }
        if ($this->apiUrl() === '' || $this->siteKey() === '') {
            $this->showMessage('请先在市场设置中完成官网绑定', 'admin.php?action=market&op=settings');
        }

        // 1. 取应用详情（type/slug/版本列表）
        list($app, $versions) = $this->fetchAppDetail($appId, $back);

        // 2. 按 URL 中的 version_id 定位目标版本（302 入口由官网指定版本）
        $targetVersion = null;
        foreach ($versions as $v) {
            if ((int)$v['version_id'] === $versionId) {
                $targetVersion = $v;
                break;
            }
        }
        if ($targetVersion === null || (int)$targetVersion['has_package'] !== 1) {
            $this->showMessage('目标版本不存在或无安装包', $back);
        }

        // 3. 共用安装管线：下载 → 解压 → 安装 → 回执
        $this->installByToken($token, $app, $targetVersion, $back);
    }

    /**
     * 安装/更新执行——后台一键安装入口（列表页 POST app_id + CSRF，方案：后台一键安装）
     *
     * 与 install() 的差别仅在令牌来源：本入口由主系统服务端调官网
     * POST /api/market/install-token（site_key 鉴权）签发一次性令牌，
     * 无需官网浏览器登录态，安装全程不出后台。
     *
     * 流程：CSRF 校验 → 拉应用详情 → 取最新兼容版本（官网已按当前版本码过滤，
     * latest 为 null 说明无兼容版本）→ 调 install-token 签发令牌 → installByToken。
     */
    private function doInstall()
    {
        $back = 'admin.php?action=market';
        // 仅接受 POST（列表页操作按钮为 POST 表单，与主题/插件管理同安全策略）
        if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->showMessage('非法请求方式', $back);
        }
        // CSRF 校验（复用全局 Security，与主题/插件管理同策略）
        if (!Security::validateCsrfToken(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
            $this->showMessage('安全校验失败，请刷新页面后重试', $back);
        }
        $appId = isset($_POST['app_id']) ? (int)$_POST['app_id'] : 0;
        if ($appId <= 0) {
            $this->showMessage('安装参数无效', $back);
        }
        if ($this->apiUrl() === '' || $this->siteKey() === '') {
            $this->showMessage('请先在市场设置中完成官网绑定', 'admin.php?action=market&op=settings');
        }

        // 1. 取应用详情（type/slug/最新兼容版本）
        list($app, $versions, $latest) = $this->fetchAppDetail($appId, $back);

        // 2. 目标版本 = 最新兼容版本（marketApp 接口的 latest 已按主系统版本码过滤）
        if ($latest === null || (int)$latest['has_package'] !== 1) {
            $this->showMessage('该应用暂无与当前系统兼容的可用安装包', $back);
        }
        $targetVersion = $latest;
        $versionId = (int)$targetVersion['version_id'];

        // 3. 调官网 install-token 签发一次性令牌（site_key 鉴权，服务端到服务端）
        $resp = $this->httpPost($this->apiUrl() . '/index.php/api/market/install-token', [
            'site_key'   => $this->siteKey(),
            'app_id'     => $appId,
            'version_id' => $versionId,
        ]);
        $tokenData = $this->parseApi($resp);
        if ($tokenData === null || empty($tokenData['token'])) {
            $msg = '无法获取安装令牌（官网不可达、密钥无效或应用已下架）';
            if ($resp !== false && $resp !== '') {
                // 带出官网明确错误信息便于排障（如「站点密钥无效」「目标版本不存在」）
                $decoded = json_decode((string)$resp, true);
                if (is_array($decoded) && isset($decoded['msg']) && $decoded['msg'] !== '') {
                    $msg .= '：' . $decoded['msg'];
                }
            }
            $this->showMessage($msg, $back);
        }
        $token = (string)$tokenData['token'];

        // 4. 共用安装管线：下载 → 解压 → 安装 → 回执
        $this->installByToken($token, $app, $targetVersion, $back);
    }

    /**
     * 拉取官网应用详情（install / doInstall 共用）
     *
     * @param int    $appId 应用 ID
     * @param string $back  出错跳转地址
     * @return array [app 应用信息, versions 版本列表, latest 最新兼容版本(可能为 null)]
     */
    private function fetchAppDetail($appId, $back)
    {
        $resp = $this->httpGet($this->apiUrl() . '/index.php/api/market/app'
            . '?site_key=' . urlencode($this->siteKey()) . '&app_id=' . $appId);
        $data = $this->parseApi($resp);
        if ($data === null || !isset($data['app'])) {
            $this->showMessage('获取应用信息失败（官网不可达或密钥无效）', $back);
        }
        $app = $data['app'];
        $versions = isset($data['versions']) && is_array($data['versions']) ? $data['versions'] : [];
        $latest = (isset($app['latest']) && is_array($app['latest'])) ? $app['latest'] : null;
        return [$app, $versions, $latest];
    }

    /**
     * 安装管线主体（install 与 doInstall 两条入口共用）
     *
     * 流程：持令牌下载 zip → zip-slip 校验解压到临时目录 → 读取清单
     * （plugin.json/theme.json，兼容 zip 带单层顶层目录）→ 清单 slug 校验
     * → 落盘（覆盖更新：先删旧目录）→ 按类型分流安装（插件走 Plugin 管线；
     * 主题落盘后注册 bk_theme）→ 回调 /api/market/installed（安装量+1，幂等）
     * → 刷新列表缓存 → 弹窗回列表页。
     *
     * @param string $token         一次性安装令牌（未消费；下载点会消费并计数）
     * @param array  $app           应用信息（type/name/slug）
     * @param array  $targetVersion 目标版本行（version/has_package）
     * @param string $back          出错跳转地址
     */
    private function installByToken($token, $app, $targetVersion, $back)
    {
        $type = ($app['type'] === 'theme') ? 'theme' : 'plugin'; // 类型白名单

        // 1. 持令牌下载安装包到临时文件
        $tmpZip = tempnam(sys_get_temp_dir(), 'bkmarket_');
        $dlDiag = [];
        $ok = $this->httpDownload($this->apiUrl() . '/index.php/api/market/download?token=' . urlencode($token), $tmpZip, $dlDiag);
        if (!$ok || filesize($tmpZip) === 0) {
            @unlink($tmpZip);
            // 细分网络层错误，便于定位（如反代/HTTPS/超时），仅后台管理员可见
            $detail = 'HTTP ' . $dlDiag['http_code']
                . ($dlDiag['error'] !== '' ? '，cURL: ' . $dlDiag['error'] : '');
            Log::error('应用市场-安装应用：安装包下载失败：' . $detail, Log::CATEGORY_OPERATION);
            $this->showMessage('安装包下载失败（' . $detail . '；令牌可能已过期，请重试安装）', $back);
        }

        // 1b. zip 魔数校验：正常 zip 必须以 PK（0x50 0x4B）开头。
        // 拦截「HTTP 200 但返回 HTML 错误页/网关拦截页」的情况，避免误报成解压失败。
        $fh = fopen($tmpZip, 'rb');
        $magic = $fh ? fread($fh, 4) : '';
        if ($fh) {
            fclose($fh);
        }
        if (strlen($magic) < 2 || $magic[0] !== 'P' || $magic[1] !== 'K') {
            $head = mb_substr((string)@file_get_contents($tmpZip, false, null, 0, 200), 0, 200);
            @unlink($tmpZip);
            Log::error('应用市场-安装应用：下载内容不是有效 zip：Content-Type=' . $dlDiag['content_type']
                . '，大小=' . $dlDiag['size'] . '，开头=' . $head,
                Log::CATEGORY_OPERATION);
            $this->showMessage('下载内容不是有效安装包（收到 Content-Type: ' . $dlDiag['content_type']
                . '，大小 ' . $dlDiag['size'] . ' 字节）。通常是官网/防火墙返回了拦截页面，请检查服务器到官网的网络后重试。', $back);
        }

        // 2. 解压到临时目录（防 zip-slip：entry 路径禁 .. 与绝对路径）
        $tmpDir = $tmpZip . '_dir';
        $extractReason = '';
        if (!$this->extractZipSafe($tmpZip, $tmpDir, $extractReason)) {
            @unlink($tmpZip);
            $this->rrmdir($tmpDir);
            Log::error('应用市场-安装应用：安装包解压失败：' . $extractReason, Log::CATEGORY_OPERATION);
            $this->showMessage('安装包解压失败：' . $extractReason, $back);
        }
        @unlink($tmpZip);

        // 3. 定位清单文件：根目录或单层子目录
        $manifestFile = '';
        foreach (['plugin.json', 'theme.json'] as $mf) {
            if (is_file($tmpDir . '/' . $mf)) {
                $manifestFile = $tmpDir . '/' . $mf;
                break;
            }
        }
        if ($manifestFile === '') {
            // 兼容 zip 带顶层目录的情况：仅当临时目录下只有一个子目录时进入查找
            $dirs = glob($tmpDir . '/*', GLOB_ONLYDIR);
            $files = glob($tmpDir . '/*');
            if (count($dirs) === 1 && count($files) === 1) {
                foreach (['plugin.json', 'theme.json'] as $mf) {
                    if (is_file($dirs[0] . '/' . $mf)) {
                        $manifestFile = $dirs[0] . '/' . $mf;
                        $tmpDir = $dirs[0]; // 包实际根目录
                        break;
                    }
                }
            }
        }
        if ($manifestFile === '') {
            $this->rrmdir($tmpDir);
            $this->showMessage('安装包缺少 plugin.json / theme.json 清单文件', $back);
        }
        $manifest = json_decode((string)file_get_contents($manifestFile), true);
        if (!is_array($manifest)) {
            $this->rrmdir(dirname($manifestFile));
            $this->showMessage('清单文件（plugin.json / theme.json）不是有效的 JSON', $back);
        }
        // slug 字段兼容：插件清单用 slug，主题清单（theme.json）历史上用 id。
        // 两者都接受，统一按 slug 落盘/注册；新打包的主题建议同时提供 slug。
        $pkgSlug = '';
        if (isset($manifest['slug']) && is_string($manifest['slug']) && $manifest['slug'] !== '') {
            $pkgSlug = $manifest['slug'];
        } elseif ($type === 'theme' && isset($manifest['id']) && is_string($manifest['id']) && $manifest['id'] !== '') {
            $pkgSlug = $manifest['id'];
        }
        if ($pkgSlug === '' || !preg_match('/^[a-zA-Z0-9_-]+$/', $pkgSlug)) {
            $this->rrmdir(dirname($manifestFile));
            $this->showMessage('清单文件 slug 缺失或非法（插件需 plugin.json 的 slug 字段；主题需 theme.json 的 slug 或 id 字段，仅限字母、数字、下划线、短横线）', $back);
        }

        // 4. 落盘：目标目录 = plugins/{slug} 或 themes/{slug}（已存在则先删旧的 = 覆盖更新）
        $baseDir = ROOT_PATH . ($type === 'theme' ? '/themes' : '/plugins');
        $target = $baseDir . '/' . $pkgSlug;
        if (!is_dir($baseDir) && !mkdir($baseDir, 0755, true)) {
            $this->rrmdir(dirname($manifestFile));
            $this->showMessage('扩展目录创建失败（请检查目录写权限）', $back);
        }
        if (is_dir($target) && !$this->rrmdir($target)) {
            $this->rrmdir(dirname($manifestFile));
            $this->showMessage('旧版本目录删除失败（请检查目录写权限）', $back);
        }
        if (!$this->moveDir($tmpDir, $target)) {
            $this->rrmdir(dirname($manifestFile));
            $this->showMessage('应用文件落盘失败（请检查目录写权限）', $back);
        }

        // 5. 按类型分流安装
        $installWarning = '';
        if ($type === 'plugin') {
            // 插件：复用现有 Plugin 安装管线（读 plugin.json → 注册 bk_plugin → install()）
            // 文件此时已落盘；插件自身 install() 若抛异常（如表已存在/SQL 问题）不应让整个
            // 请求变 500——记录并提示，用户可到插件管理页排查（实测：注册成功但白屏 500）
            try {
                require_once CORE_PATH . '/lib/Plugin.php';
                $plugin = new Plugin();
                if (!$plugin->installPlugin($pkgSlug)) {
                    $this->showMessage('插件文件已落盘但注册失败，请到插件管理页检查', $back);
                }
            } catch (\Throwable $e) {
                $installWarning = '（插件已落盘注册，但初始化方法报错：' . $e->getMessage()
                    . '，请到插件管理页检查）';
                Log::error('应用市场-安装应用：插件「' . $pkgSlug . '」install() 抛异常：' . $e->getMessage()
                    . ' @ ' . $e->getFile() . ':' . $e->getLine(),
                    Log::CATEGORY_OPERATION);
            }
        } else {
            // 主题：落盘后注册 bk_theme（与 ThemeController::scanThemes 同规则，status=0 未启用）
            $this->registerTheme($pkgSlug, $manifest);
        }

        // 6. 安装回执（官网 installs+1；幂等，任何异常都不影响本地安装结果）
        try {
            $this->httpPost($this->apiUrl() . '/index.php/api/market/installed', ['token' => $token]);
        } catch (\Throwable $e) {
            Log::warning('应用市场-安装应用：安装回执上报失败（不影响使用）：' . $e->getMessage(), Log::CATEGORY_OPERATION);
        }

        // 7. 刷新应用列表缓存（下次进列表页重新拉取，角标状态即时更新）
        try {
            $this->saveConfig('market_cache', '');
            $this->saveConfig('market_cache_time', 0);
        } catch (\Throwable $e) {
            Log::warning('应用市场-安装应用：刷新市场缓存失败（不影响使用）：' . $e->getMessage(), Log::CATEGORY_OPERATION);
        }

        $ver = isset($targetVersion['version']) ? $targetVersion['version'] : '';
        $appName = isset($app['name']) && $app['name'] !== '' ? $app['name'] : $pkgSlug;
        $label = ($type === 'theme' ? '主题' : '插件') . '「' . $appName . '」';
        $this->showMessage($label . ($ver !== '' ? ' ' . $ver : '') . ' 安装成功' . $installWarning, $back);
    }

    // ================================================
    // 私有辅助：配置读写 / HTTP / 缓存 / zip / 目录
    // ================================================

    /**
     * 官网 API 根地址（bk_config：market_api_url）
     */
    private function apiUrl()
    {
        return rtrim((string)Config::get('market_api_url', ''), '/');
    }

    /**
     * 站点密钥（bk_config：market_site_key）
     */
    private function siteKey()
    {
        return (string)Config::get('market_site_key', '');
    }

    /**
     * 当前主系统版本号（core/config/version.php 为唯一真相来源）
     */
    private function currentVersion()
    {
        $v = include CORE_PATH . '/config/version.php';
        return isset($v['version']) ? $v['version'] : '1.0.0';
    }

    /**
     * 当前主系统版本码
     */
    private function currentVersionCode()
    {
        $v = include CORE_PATH . '/config/version.php';
        return isset($v['version_code']) ? (int)$v['version_code'] : 0;
    }

    /**
     * 读应用列表缓存（10 分钟内有效返回缓存数组，否则 null）
     */
    private function readCache()
    {
        $time = (int)Config::get('market_cache_time', 0);
        if ($time <= 0 || time() - $time > self::CACHE_TTL) {
            return null;
        }
        $data = json_decode((string)Config::get('market_cache', ''), true);
        return is_array($data) ? $data : null;
    }

    /**
     * 主程序新版本检测（P1-9：调官网 GET /api/latest-version，方案 15.1 更新检测）
     *
     * 缓存与降级策略（bk_config：market_latest_ver JSON + market_latest_time 时间戳）：
     * - TTL 与应用列表缓存一致（10 分钟），避免每次进市场页都请求官网；
     * - 请求失败/未配置地址时写入空结果缓存（version=''），TTL 内不再重试，
     *   保证官网不可达时市场页照常可用（方案 15.1 离线兜底）；
     * - has_new 按版本码整数比较（官网 version_code 与主系统同规则换算）。
     *
     * 侧边栏红点（P1-9）也复用本方法：后台每个页面渲染侧边栏时调用，
     * 得益于 10 分钟缓存（含失败空结果缓存），仅缓存过期后的首个页面
     * 才真正请求官网，普通翻页零额外开销，故开放为 public。
     *
     * @return array [string 最新版本号（未知为空串）, int has_new 1=有新版本 0=否]
     */
    public function checkNewVersion()
    {
        // 读缓存（含"失败空结果"缓存：json_decode 后仍是数组即命中）
        $cached = null;
        $time = (int)Config::get('market_latest_time', 0);
        if ($time > 0 && time() - $time <= self::CACHE_TTL) {
            $decoded = json_decode((string)Config::get('market_latest_ver', ''), true);
            if (is_array($decoded)) {
                $cached = $decoded;
            }
        }

        // 缓存过期/不存在：请求官网（未配置官网地址时直接记空结果，跳过请求）
        if ($cached === null) {
            $cached = ['version' => '', 'code' => 0];
            if ($this->apiUrl() !== '') {
                $resp = $this->httpGet($this->apiUrl() . '/index.php/api/latest-version');
                $data = $this->parseApi($resp);
                if ($data !== null && isset($data['version'])) {
                    $cached = [
                        'version' => (string)$data['version'],
                        'code'    => isset($data['version_code']) ? (int)$data['version_code'] : 0,
                    ];
                }
            }
            $this->saveConfig('market_latest_ver', json_encode($cached, JSON_UNESCAPED_UNICODE));
            $this->saveConfig('market_latest_time', time());
        }

        // 版本码比较：官网 code 可解析（>0）且大于当前系统版本码才算有新版本
        $latestCode = isset($cached['code']) ? (int)$cached['code'] : 0;
        $hasNew = ($latestCode > 0 && $latestCode > $this->currentVersionCode()) ? 1 : 0;
        return [isset($cached['version']) ? (string)$cached['version'] : '', $hasNew];
    }

    /**
     * 写应用列表缓存（bk_config：market_cache JSON + market_cache_time 时间戳）
     */
    private function writeCache($apps)
    {
        $this->saveConfig('market_cache', json_encode($apps, JSON_UNESCAPED_UNICODE));
        $this->saveConfig('market_cache_time', time());
    }

    /**
     * 保存单项配置到 bk_config（存在则更新，不存在则插入）
     *
     * @param string $name  配置键名
     * @param mixed  $value 配置值（标量）
     */
    private function saveConfig($name, $value)
    {
        $db = Database::getInstance();
        $existing = $db->fetch("SELECT id FROM {$db->table('config')} WHERE name = ?", [$name]);
        if ($existing) {
            $db->update('config', ['value' => (string)$value], ['name' => $name]);
        } else {
            $db->insert('config', ['name' => $name, 'value' => (string)$value]);
        }
    }

    /**
     * 读本地已安装插件/主题的清单版本号
     *
     * @param string $slug 应用 slug（目录名）
     * @param string $type 应用类型 plugin|theme
     * @return string 本地版本号（未安装/清单损坏返回空串）
     */
    private function getLocalManifestVersion($slug, $type)
    {
        $manifestPath = ROOT_PATH . ($type === 'theme' ? '/themes/' : '/plugins/')
            . $slug . '/' . ($type === 'theme' ? 'theme.json' : 'plugin.json');
        if (!is_file($manifestPath)) {
            return '';
        }
        $m = json_decode((string)file_get_contents($manifestPath), true);
        return (is_array($m) && isset($m['version'])) ? (string)$m['version'] : '';
    }

    /**
     * 统计本地已安装应用中「有可用更新」的数量（仪表盘待办提醒用）
     *
     * 数据源：应用列表缓存（bk_config market_cache，10 分钟 TTL）——
     * 仅读缓存，缓存缺失或过期时直接返回 0，绝不触发官网请求，
     * 保证仪表盘零额外延迟；缓存由应用市场页正常刷新。
     * 判定口径与市场页 formatAppRow 的 hasUpdate 完全一致：
     * 已安装 && 官网最新版本号非空 && 与本地版本号不同。
     *
     * @return int 有更新的已安装应用数（无缓存/无更新时为 0）
     */
    public function countUpdatableApps()
    {
        $apps = $this->readCache();
        if ($apps === null) {
            return 0;
        }
        $count = 0;
        foreach ($apps as $app) {
            if (!is_array($app) || empty($app['slug'])
                || !isset($app['latest']['version']) || (string)$app['latest']['version'] === '') {
                continue;
            }
            $type = (isset($app['type']) && $app['type'] === 'theme') ? 'theme' : 'plugin';
            $localVersion = $this->getLocalManifestVersion((string)$app['slug'], $type);
            if ($localVersion !== '' && (string)$app['latest']['version'] !== $localVersion) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * 生成「安装/更新」按钮 HTML（列表卡片与详情子页共用）
     *
     * 安全策略：安装为状态变更动作，必须 POST + CSRF（与主题/插件管理同策略），
     * 表单只携带 app_id，目标版本号由服务端调详情接口二次确认（latest 兼容版本）；
     * confirm 文案不带应用名——应用名可能含引号等字符，注入 onsubmit 属性有风险，
     * 版本号为纯数字与点号可安全拼接；CSRF token 由 Security 复用会话内未过期令牌。
     *
     * @param int    $appId      应用 ID
     * @param bool   $installed  本地是否已安装（决定按钮文案「安装」/「更新」与确认文案）
     * @param string $latestVer  最新兼容版本号（confirm 文案展示）
     * @param string $btnClass   按钮样式类（卡片用 btn-sm，详情用默认）
     * @return string 表单 HTML
     */
    private function installButtonHtml($appId, $installed, $latestVer, $btnClass)
    {
        $btnLabel = $installed ? '更新' : '安装';
        $confirmText = $installed
            ? '确认更新到 ' . $latestVer . '（将覆盖本地旧版本文件）？'
            : '确认安装 ' . $latestVer . ' 版本？';
        return '<form method="post" action="admin.php?action=market&op=do_install" '
            . 'style="display:inline-block; margin:0;" '
            . 'onsubmit="return confirm(\'' . $confirmText . '\')">'
            . '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(Security::generateCsrfToken(), ENT_QUOTES) . '">'
            . '<input type="hidden" name="app_id" value="' . (int)$appId . '">'
            . '<button type="submit" class="' . $btnClass . '">' . $btnLabel . '</button>'
            . '</form>';
    }

    /**
     * 应用行 → 市场卡片行数据（含本地安装状态比对与操作区 HTML 预计算）
     *
     * 卡片布局对齐官网 /apps 的 formatCard 口径：主题展示封面（cover_image）、
     * 插件展示图标（icon）；官网返回 root 相对路径（如 /uploads/...），
     * 拼接官网地址前缀保证后台页面引用可达；无图时用文字占位块。
     * 安装状态判定：本地清单版本号 != 官网最新兼容版本号 → 有更新。
     *
     * @param array $app 官网 API 返回的应用行
     * @return array
     */
    private function formatCardRow($app)
    {
        $type = ($app['type'] === 'theme') ? 'theme' : 'plugin';
        $localVersion = $this->getLocalManifestVersion((string)$app['slug'], $type);
        $installed = ($localVersion !== '');
        $latest = isset($app['latest']) && is_array($app['latest']) ? $app['latest'] : null;
        $latestVersion = $latest ? (string)$latest['version'] : '';
        $hasUpdate = $installed && $latestVersion !== '' && $latestVersion !== $localVersion;
        $canInstall = $latest && (int)$latest['has_package'] === 1;

        // 卡片图 HTML 预计算：有图输出 img / 无图输出占位块（与官网 formatCard 同口径）
        $image = ($type === 'theme') ? (string)$app['cover_image'] : (string)$app['icon'];
        $apiBase = $this->apiUrl();
        if ($image !== '') {
            // root 相对路径（/uploads/...）直接接官网地址；其余相对路径补斜杠
            $src = ($image[0] === '/') ? $apiBase . $image : $apiBase . '/' . ltrim($image, '/');
            $imageHtml = '<img src="' . htmlspecialchars($src, ENT_QUOTES) . '" alt="'
                . htmlspecialchars((string)$app['name'], ENT_QUOTES) . '">';
        } else {
            // 生态预览阶段无图：文字占位块（避免依赖官网 emoji 资源）
            $imageHtml = '<span class="mk-card-ph">' . ($type === 'theme' ? '主题' : '插件') . '</span>';
        }

        // 状态区 HTML 预计算（分支在控制器层拼好，模板循环内原样输出）
        if ($hasUpdate) {
            $statusHtml = '<span class="label label-warning">有更新 ' . $localVersion . ' → ' . $latestVersion . '</span>';
        } elseif ($installed) {
            $statusHtml = '<span class="label label-success">已安装 ' . $localVersion . '</span>';
        } elseif (!$canInstall) {
            $statusHtml = '<span class="label label-default">暂无可用安装包</span>';
        } else {
            $statusHtml = '';
        }

        // 安装/更新按钮：可安装（未安装或有更新）才输出，其余仅状态徽章
        $installBtn = '';
        if ($canInstall && (!$installed || $hasUpdate)) {
            $installBtn = $this->installButtonHtml((int)$app['id'], $installed, $latestVersion, 'btn btn-primary btn-sm');
        }

        return [
            'id'          => (int)$app['id'],
            'name'        => htmlspecialchars((string)$app['name'], ENT_QUOTES),
            'type_label'  => $type === 'theme' ? '主题' : '插件',
            'author'      => htmlspecialchars((string)$app['author'], ENT_QUOTES),
            // 分类徽章（可能为空 = 未分类，控制器已滤掉空值聚合，行内仍需判空输出）
            'category'    => htmlspecialchars((string)$app['category'], ENT_QUOTES),
            // 简介截断 60 字（卡片高度一致性）
            'description' => htmlspecialchars(mb_substr((string)$app['description'], 0, 60), ENT_QUOTES),
            'installs'    => (int)$app['installs'],
            'latest'      => $latestVersion !== '' ? $latestVersion : '-',
            'image_html'  => $imageHtml,
            'status_html' => $statusHtml,
            'install_btn' => $installBtn,
            // 后台详情子页链接（内嵌详情，替代官网跳转）
            'detail_url'  => 'admin.php?action=market&op=detail&app_id=' . (int)$app['id'],
        ];
    }

    /**
     * 应用详情子页（op=detail）——内嵌完整详情，替代跳转官网
     *
     * 调官网 /api/market/app（详情接口，响应含 detail 富文本正文与全量版本历史），
     * 渲染：封面/图标、名称、类型、分类、作者、简介、详情正文、版本历史列表、
     * 最新兼容版本安装按钮（与列表卡片共用 installButtonHtml）。
     * 版本历史仅展示（安装动作只针对最新兼容版）；官网完整介绍入口保留。
     */
    private function detail()
    {
        $back = 'admin.php?action=market';
        $appId = isset($_GET['app_id']) ? (int)$_GET['app_id'] : 0;
        if ($appId <= 0) {
            $this->showMessage('应用参数无效', $back);
        }
        if ($this->apiUrl() === '' || $this->siteKey() === '') {
            $this->showMessage('请先在市场设置中完成官网绑定', 'admin.php?action=market&op=settings');
        }

        // 取应用详情（含 detail 富文本 + 全量版本历史）
        list($app, $versions, $latest) = $this->fetchAppDetail($appId, $back);
        $type = ($app['type'] === 'theme') ? 'theme' : 'plugin';

        // 本地安装状态比对（与卡片行同口径）
        $localVersion = $this->getLocalManifestVersion((string)$app['slug'], $type);
        $installed = ($localVersion !== '');
        $latestVersion = $latest ? (string)$latest['version'] : '';
        $hasUpdate = $installed && $latestVersion !== '' && $latestVersion !== $localVersion;
        $canInstall = $latest && (int)$latest['has_package'] === 1;

        // 头部图：主题封面优先，插件图标（同卡片口径）
        $image = ($type === 'theme') ? (string)$app['cover_image'] : (string)$app['icon'];
        if ($image !== '') {
            $src = ($image[0] === '/') ? $this->apiUrl() . $image : $this->apiUrl() . '/' . ltrim($image, '/');
            $imageHtml = '<img src="' . htmlspecialchars($src, ENT_QUOTES) . '" alt="'
                . htmlspecialchars((string)$app['name'], ENT_QUOTES) . '">';
        } else {
            $imageHtml = '<span class="mk-card-ph mk-card-ph-lg">' . ($type === 'theme' ? '主题' : '插件') . '</span>';
        }

        // 状态徽章 + 安装按钮（详情页按钮用默认尺寸）
        if ($hasUpdate) {
            $statusHtml = '<span class="label label-warning">有更新 ' . $localVersion . ' → ' . $latestVersion . '</span>';
        } elseif ($installed) {
            $statusHtml = '<span class="label label-success">已安装 ' . $localVersion . '</span>';
        } elseif (!$canInstall) {
            $statusHtml = '<span class="label label-default">暂无可用安装包</span>';
        } else {
            $statusHtml = '';
        }
        $installBtn = '';
        if ($canInstall && (!$installed || $hasUpdate)) {
            $installBtn = $this->installButtonHtml((int)$app['id'], $installed, $latestVersion, 'btn btn-primary');
        }

        // 版本历史行数据（时间格式化 + 大小格式化在控制器层完成）
        $versionRows = [];
        foreach ($versions as $v) {
            $size = (int)$v['file_size'];
            $versionRows[] = [
                'version'     => htmlspecialchars((string)$v['version'], ENT_QUOTES),
                // 兼容标记： incompatible 标签（当前系统不在版本区间内）
                'incompatible' => (isset($v['compatible']) && (int)$v['compatible'] === 0) ? 1 : 0,
                'published'   => htmlspecialchars((string)$v['created_at'], ENT_QUOTES),
                'size'        => $size >= 1048576
                    ? round($size / 1048576, 2) . ' MB'
                    : round($size / 1024, 1) . ' KB',
                'changelog'   => htmlspecialchars((string)$v['changelog'], ENT_QUOTES),
            ];
        }

        $currentPage = 'market';
        $pageTitle = '应用详情';
        include ADMIN_PATH . '/templates/market_detail.html';
    }

    /**
     * 注册主题到 bk_theme（落盘后调用；与 ThemeController::scanThemes 同规则）
     *
     * @param string $slug     主题目录名
     * @param array  $manifest theme.json 解析结果
     */
    private function registerTheme($slug, $manifest)
    {
        $db = Database::getInstance();
        $existing = $db->fetch("SELECT id FROM {$db->table('theme')} WHERE slug = ?", [$slug]);
        if ($existing) {
            return; // 已注册（更新场景保留原启用状态）
        }
        try {
            $db->insert('theme', [
                'name'        => isset($manifest['name']) ? $manifest['name'] : $slug,
                'slug'        => $slug,
                'description' => isset($manifest['description']) ? $manifest['description'] : '',
                'version'     => isset($manifest['version']) ? $manifest['version'] : '1.0.0',
                'status'      => 0, // 未启用，到主题管理页手动启用
                'settings'    => '{}',
                'created_at'  => time(),
                'installed_at' => time(),
            ]);
        } catch (\Exception $e) {
            // UNIQUE 冲突等异常静默（与 scanThemes 同策略）
        }
    }

    /**
     * GET 请求（curl，10 秒超时）
     *
     * @param string $url
     * @return string|false 响应体（失败 false）
     */
    private function httpGet($url)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        return $body;
    }

    /**
     * 文件下载（curl，60 秒超时，写临时文件）
     *
     * @param string $url
     * @param string $target 保存路径
     * @return bool 是否成功
     */
    private function httpDownload($url, $target, array &$diag = null)
    {
        $fp = fopen($target, 'wb');
        if (!$fp) {
            if ($diag !== null) {
                $diag = ['http_code' => 0, 'content_type' => '', 'errno' => 0, 'error' => '无法打开本地目标文件写入', 'size' => 0];
            }
            return false;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fp,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $execOk = curl_exec($ch);
        // 收集诊断信息：安装失败时用于区分「网络/HTTP 错误」与「下载内容被篡改（WAF/反代错误页）」
        $diag = [
            'http_code'    => (int)curl_getinfo($ch, CURLINFO_HTTP_CODE),
            'content_type' => (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE),
            'errno'        => (int)curl_errno($ch),
            'error'        => (string)curl_error($ch),
            'size'         => is_file($target) ? (int)filesize($target) : 0,
        ];
        curl_close($ch);
        fclose($fp);
        return $execOk !== false && $diag['http_code'] === 200;
    }

    /**
     * POST 请求（curl，application/x-www-form-urlencoded，10 秒超时）
     *
     * @param string $url
     * @param array  $fields POST 字段
     * @return string|false 响应体（失败 false）
     */
    private function httpPost($url, $fields)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($fields),
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        return $body;
    }

    /**
     * 解析官网 API JSON 响应（code=0 视为成功）
     *
     * @param string|false $body
     * @return array|null 成功返回 data 部分，失败 null
     */
    private function parseApi($body)
    {
        if ($body === false || $body === '') {
            return null;
        }
        $json = json_decode($body, true);
        if (!is_array($json) || !isset($json['code']) || (int)$json['code'] !== 0) {
            return null;
        }
        return isset($json['data']) && is_array($json['data']) ? $json['data'] : [];
    }

    /**
     * 安全解压 zip（防 zip-slip：entry 目标路径必须位于解压目录内）
     *
     * @param string $zipFile  压缩包路径
     * @param string $destDir  解压目标目录（自动创建）
     * @param string $reason   输出参数：失败时回填人类可读的具体原因
     * @return bool 是否成功
     */
    private function extractZipSafe($zipFile, $destDir, &$reason = '')
    {
        if (!class_exists('ZipArchive')) {
            $reason = '当前 PHP 未安装/启用 zip 扩展，请在服务器安装 php-zip 后重试';
            return false;
        }
        $zip = new ZipArchive();
        $openRes = $zip->open($zipFile);
        if ($openRes !== true) {
            // ZipArchive::open 失败码映射（ER_NOZIP=19 文件不是 zip；ER_OPEN=11 打不开等）
            static $errMap = [
                ZipArchive::ER_EXISTS => '文件已存在冲突',
                ZipArchive::ER_INCONS => 'zip 结构不一致或已损坏',
                ZipArchive::ER_INVAL  => '参数无效',
                ZipArchive::ER_MEMORY => '内存不足',
                ZipArchive::ER_NOENT  => '临时文件不存在',
                ZipArchive::ER_NOZIP  => '文件不是有效的 zip 压缩包',
                ZipArchive::ER_OPEN   => '无法打开文件（权限或路径问题）',
                ZipArchive::ER_READ   => '读取文件失败',
                ZipArchive::ER_SEEK   => '文件定位失败',
            ];
            $reason = '打开压缩包失败（错误码 ' . $openRes . '：'
                . ($errMap[$openRes] ?? '未知错误') . '；文件大小 ' . (int)@filesize($zipFile) . ' 字节）';
            return false;
        }
        if (!is_dir($destDir) && !mkdir($destDir, 0755, true)) {
            $zip->close();
            $reason = '无法创建临时解压目录：' . $destDir . '（检查临时目录权限或磁盘空间）';
            return false;
        }
        // 解析解压基准目录（用于包含校验）。
        // 首选 realpath：解析符号链接，最严格；但部分环境（如宝塔 open_basedir + PHP-FPM）
        // 对 /tmp 下刚创建的子目录 realpath 会返回 false，若直接转成空字符串会导致
        // 「合法条目全部误判为非法路径」（线上实测：default/ 第一个目录条目即报错）。
        // 此时退化为词法规范化的原路径——临时目录为本次新建，内部不存在预置符号链接，
        // 配合下方的分量级 .. 拒绝，安全性不受影响。
        $destReal = realpath($destDir);
        if ($destReal === false) {
            Log::warning('应用市场-安装应用：realpath 解析临时解压目录失败，已退化为词法路径校验：' . $destDir,
                Log::CATEGORY_OPERATION);
            $destReal = $destDir;
        }
        $destReal = rtrim(str_replace('\\', '/', $destReal), '/');
        if ($destReal === '') {
            $zip->close();
            $reason = '临时解压目录路径异常（解析后为空）：' . $destDir;
            return false;
        }
        $ok = true;
        $badEntry = '';
        $debugInfo = ''; // 万一仍误判，把内部路径计算带出，便于一次性定位
        $hasBackslash = false; // 条目名是否含反斜杠（Windows 压缩工具产物）
        for ($i = 0; $i < $zip->numFiles; $i++) {
            // 统一分隔符：部分 Windows 压缩工具（如系统自带“发送到压缩文件夹”）
            // 在 zip 条目名里使用反斜杠，不统一的话 Linux 解压会生成畸形文件名
            $rawName = (string)$zip->getNameIndex($i);
            $name = str_replace('\\', '/', $rawName);
            if ($rawName !== $name) {
                $hasBackslash = true;
            }
            if ($name === '' || $name === '/') {
                continue;
            }
            // 第一层：词法拒绝（任何 ..、Unix 绝对路径、Windows 盘符一律拒绝）
            if (strpos($name, '..') !== false || $name[0] === '/' || preg_match('#^[a-zA-Z]:#', $name)) {
                $ok = false;
                $badEntry = $rawName;
                break;
            }
            // 第二层：拼接后做目录包含校验（防 ./ 与多余分隔符等绕过形态）。
            // 不能用「explode 跳空段再 implode」手工规范化：Unix 绝对路径的前导斜杠
            // 会被第一个空段吞掉（/tmp/x → tmp/x），导致所有合法条目误判非法。
            // 这里用系统 dirname() 逐层收敛（天然正确处理根斜杠/点段），配合第一层
            // 已拒绝所有 .. 段，任何合法条目最终必等于或位于基准目录内。
            $target = $destReal . '/' . $name;
            $probe = $target;
            $contained = ($probe === $destReal);
            while (!$contained && ($parent = dirname($probe)) !== $probe) {
                $probe = $parent;
                if ($probe === $destReal) {
                    $contained = true;
                    break;
                }
            }
            if (!$contained) {
                $ok = false;
                $badEntry = $rawName;
                $debugInfo = '；[调试] base=' . $destReal . ' target=' . $target
                    . ' realpath=' . (realpath($destDir) !== false ? 'ok' : 'false');
                break;
            }
        }
        if (!$ok) {
            $zip->close();
            $reason = '压缩包包含非法路径条目（zip-slip 风险）：' . $badEntry . $debugInfo;
            return false;
        }
        // 落盘解压。
        // 正常包直接用 extractTo（C 层实现，快且保留权限）；
        // 含反斜杠条目的 Windows 产物改为逐条手动写入——extractTo 在 Linux 上会把
        // 反斜杠当普通字符，生成名为「default\assets\style.css」的畸形文件。
        if ($hasBackslash) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $normName = str_replace('\\', '/', (string)$zip->getNameIndex($i));
                if ($normName === '' || $normName === '/') {
                    continue;
                }
                $targetFile = $destDir . '/' . $normName;
                if (substr($normName, -1) === '/') {
                    if (!is_dir($targetFile) && !@mkdir($targetFile, 0755, true)) {
                        $zip->close();
                        $reason = '写入解压目录失败：' . $normName . '（检查临时目录权限或磁盘空间）';
                        return false;
                    }
                    continue;
                }
                $targetParent = dirname($targetFile);
                if (!is_dir($targetParent) && !@mkdir($targetParent, 0755, true)) {
                    $zip->close();
                    $reason = '写入解压目录失败：' . $targetParent . '（检查临时目录权限或磁盘空间）';
                    return false;
                }
                $stream = $zip->getStream($zip->getNameIndex($i));
                if ($stream === false) {
                    $zip->close();
                    $reason = '读取压缩包条目失败：' . $normName;
                    return false;
                }
                $written = file_put_contents($targetFile, stream_get_contents($stream));
                fclose($stream);
                if ($written === false) {
                    $zip->close();
                    $reason = '写入解压文件失败：' . $normName . '（检查临时目录权限或磁盘空间）';
                    return false;
                }
            }
            $zip->close();
            return true;
        }

        if (!$zip->extractTo($destDir)) {
            // getStatusString() 返回系统层错误（如 Permission denied / No space left on device），
            // Linux 服务器上磁盘满/权限不足时能直接定位（须在 close 前取值与缓存条目数）
            $sysErr = method_exists($zip, 'getStatusString') ? (string)$zip->getStatusString() : '';
            $numFiles = (int)$zip->numFiles;
            $zip->close();
            $reason = '写入解压文件失败' . ($sysErr !== '' ? '（系统错误：' . $sysErr . '）' : '（检查临时目录权限或磁盘空间）')
                . '；共 ' . $numFiles . ' 个条目';
            return false;
        }
        $zip->close();
        return true;
    }

    /**
     * 递归移动目录（落盘最后一步；目标不存在）
     *
     * @param string $src 源目录
     * @param string $dst 目标目录
     * @return bool 是否成功
     */
    private function moveDir($src, $dst)
    {
        if (!is_dir($src)) {
            return false;
        }
        if (@rename($src, $dst)) {
            return true; // 同盘 rename 最快
        }
        // 跨盘回退：复制+删除
        if (!mkdir($dst, 0755, true)) {
            return false;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $target = $dst . '/' . $iterator->getSubPathName();
            if ($item->isDir()) {
                if (!mkdir($target, 0755, true) && !is_dir($target)) {
                    return false;
                }
            } elseif (!copy($item->getPathname(), $target)) {
                return false;
            }
        }
        $this->rrmdir($src);
        return true;
    }

    /**
     * 递归删除目录（更新覆盖旧版本 / 清理临时目录用）
     *
     * @param string $dir
     * @return bool 是否成功
     */
    private function rrmdir($dir)
    {
        if (!is_dir($dir)) {
            return false;
        }
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        return @rmdir($dir);
    }

    /**
     * 弹窗提示并跳转（与 ThemeController::showMessage 同输出形态）
     *
     * @param string $message  提示文案
     * @param string $redirect 跳转地址
     */
    private function showMessage($message, $redirect)
    {
        // 转义消息中的特殊字符：安装警告会带出异常原文（可能含引号/换行/反斜杠），
        // 不转义会破坏 alert 的 JS 字符串甚至造成页面脚本中断。
        // 顺序很重要：先转义反斜杠本身，再转义引号，最后把裸换行换成 \n 字面量
        // （若先插 \n 再转义反斜杠会被加倍成 \\n，弹窗会显示出字面的 \n）
        $safeMsg = str_replace(['\\', "'", "\r", "\n"], ['\\\\', "\\'", '', '\\n'], $message);
        $safeUrl = htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8');
        echo "<script>alert('{$safeMsg}'); window.location.href = '{$safeUrl}';</script>";
        exit;
    }
}
