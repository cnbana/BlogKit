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
 * WangEditor v5 富文本编辑器插件
 *
 * 基于 wangEditor v5.1.23 官方预构建产物（npm @wangeditor/editor）
 * 通过 article/page 增改页面的钩子注入编辑器，替换原生 textarea
 *
 * 注意：编辑器菜单键必须是 v5 官方 key（如 headerSelect / through / insertImage），
 *       v4 的键（head / list / strikeThrough 等）会触发 "Not found menu item factory" 错误
 */

if (!class_exists('PluginBase')) {
    require_once dirname(dirname(__DIR__)) . '/core/lib/Plugin.php';
}

class WangeditorPlugin extends PluginBase {

    /** @var array 插件信息（来自 plugin.json） */
    protected $info = array();

    /** @var array|null 合并默认值后的插件设置缓存 */
    protected $settings = null;

    public function __construct($plugin = null) {
        // 读取插件描述文件
        $plugin_info_file = dirname(__FILE__) . '/plugin.json';
        if (file_exists($plugin_info_file)) {
            $json_content = file_get_contents($plugin_info_file);
            $this->info = json_decode($json_content, true);
        }

        if ($plugin) {
            // 系统正常加载：传入数据库中的插件记录
            parent::__construct($plugin);
        } else {
            // 手动实例化（如安装脚本环境）：直接使用 plugin.json 信息
            $this->plugin = $this->info;
            $this->init();
        }
    }

    /**
     * 初始化：注册编辑器注入钩子
     */
    public function init() {
        $this->registerHooks();
    }

    /**
     * 获取插件设置（默认值 + 数据库已保存值合并）
     */
    public function getPluginSettings() {
        if ($this->settings !== null) {
            return $this->settings;
        }

        // 从数据库读取已保存的配置
        $pluginSystem = Plugin::getInstance();
        if ($pluginSystem !== null) {
            $savedSettings = $pluginSystem->getPluginConfig($this->info['slug']);
        } else {
            $savedSettings = array();
        }

        // 逐项合并默认值与已保存值
        $defaultSettings = isset($this->info['default_settings']) ? $this->info['default_settings'] : array();
        $this->settings = array();
        foreach ($defaultSettings as $key => $default) {
            $defaultValue = isset($default['value']) ? $default['value'] : null;
            if (isset($savedSettings[$key])) {
                $savedValue = $savedSettings[$key];
                // 兼容两种保存结构：纯值 或 ['value' => x] 数组
                if (is_array($savedValue) && isset($savedValue['value'])) {
                    $this->settings[$key] = $savedValue['value'];
                } else {
                    $this->settings[$key] = $savedValue;
                }
            } else {
                $this->settings[$key] = $defaultValue;
            }
        }

        return $this->settings;
    }

    /**
     * 注册钩子：
     *  - *_before：在页面 <head> 输出 CSS/JS 资源
     *  - *_after ：在内容区输出编辑器初始化脚本
     */
    private function registerHooks() {
        $plugin_instance = Plugin::getInstance();
        if ($plugin_instance === null) {
            return;
        }

        // 资源加载钩子（文章/页面的新增与编辑页头部）
        $plugin_instance->addHook('article_add_before', array($this, 'loadEditorResources'));
        $plugin_instance->addHook('article_edit_before', array($this, 'loadEditorResources'));
        $plugin_instance->addHook('page_add_before', array($this, 'loadEditorResources'));
        $plugin_instance->addHook('page_edit_before', array($this, 'loadEditorResources'));

        // 编辑器渲染钩子（内容区尾部）
        $plugin_instance->addHook('article_add_after', array($this, 'renderEditor'));
        $plugin_instance->addHook('article_edit_after', array($this, 'renderEditor'));
        $plugin_instance->addHook('page_add_after', array($this, 'renderEditor'));
        $plugin_instance->addHook('page_edit_after', array($this, 'renderEditor'));
    }

    /**
     * 输出编辑器所需的 CSS / JS（本地资源，无 CDN 依赖）
     */
    public function loadEditorResources($params = array()) {
        if (!$this->isEnabled()) {
            return;
        }

        $plugin_url = '/plugins/wangeditor';
        echo '<link rel="stylesheet" href="' . $plugin_url . '/css/style.css">';
        echo '<script src="' . $plugin_url . '/wangEditor.js"></script>';
    }

    /**
     * 根据插件设置构建 v5 工具栏菜单键列表
     *
     * 全部使用 wangEditor v5 官方菜单 key，分组顺序参考官方文档：
     * https://www.wangeditor.com/v5/menu-config.html
     */
    private function buildToolbarKeys($settings) {
        // 分组符统一用 '|' 标记，最终输出时转为带引号的字符串并处理相邻重复
        $SEP = '|';
        $toolbar = array();

        // 常用组：撤销 / 重做 / 格式刷 / 清除格式
        if (!empty($settings['toolbar_undo_redo'])) {
            $toolbar[] = '"undo"';
            $toolbar[] = '"redo"';
        }
        // 注意：v5.1.23 内置模块不含 formatPainter / clearStyle，
        // 使用未注册的菜单键会导致 "Not found menu item factory" 并中断工具栏渲染
        if (!empty($toolbar)) {
            $toolbar[] = $SEP;
        }

        // 标题组
        if (!empty($settings['toolbar_title'])) {
            $toolbar[] = '"headerSelect"';
            $toolbar[] = $SEP;
        }

        // 字体字号组
        if (!empty($settings['toolbar_font'])) {
            $toolbar[] = '"fontSize"';
            $toolbar[] = '"fontFamily"';
            $toolbar[] = '"lineHeight"';
            $toolbar[] = $SEP;
        }

        // 文本格式组：粗体/斜体/下划线/删除线/上标/下标/行内代码
        if (!empty($settings['toolbar_text_format'])) {
            $toolbar[] = '"bold"';
            $toolbar[] = '"italic"';
            $toolbar[] = '"underline"';
            $toolbar[] = '"through"';
            $toolbar[] = '"sub"';
            $toolbar[] = '"sup"';
            $toolbar[] = '"code"';
            $toolbar[] = '"clearStyle"'; // 清除格式（v5 已注册的合法菜单键）
            $toolbar[] = $SEP;
        }

        // 颜色背景组
        if (!empty($settings['toolbar_color'])) {
            $toolbar[] = '"color"';
            $toolbar[] = '"bgColor"';
            $toolbar[] = $SEP;
        }

        // 列表缩进组
        if (!empty($settings['toolbar_list'])) {
            $toolbar[] = '"bulletedList"';
            $toolbar[] = '"numberedList"';
            $toolbar[] = '"todo"';
            $toolbar[] = $SEP;
        }

        // 对齐组
        if (!empty($settings['toolbar_align'])) {
            $toolbar[] = '"justifyLeft"';
            $toolbar[] = '"justifyRight"';
            $toolbar[] = '"justifyCenter"';
            $toolbar[] = '"justifyJustify"';
            $toolbar[] = $SEP;
        }

        // 插入类组：引用 / 表情 / 代码块 / 分割线 / 链接
        $insertItems = array();
        if (!empty($settings['toolbar_blockquote'])) {
            $insertItems[] = '"blockquote"';
        }
        if (!empty($settings['toolbar_emotion'])) {
            $insertItems[] = '"emotion"';
        }
        if (!empty($settings['toolbar_code_block'])) {
            $insertItems[] = '"codeBlock"';
        }
        if (!empty($settings['toolbar_divider'])) {
            $insertItems[] = '"divider"';
        }
        if (!empty($settings['toolbar_link'])) {
            $insertItems[] = '"insertLink"';
            $insertItems[] = '"editLink"';
            $insertItems[] = '"unLink"';
        }
        if (!empty($insertItems)) {
            $toolbar = array_merge($toolbar, $insertItems);
            $toolbar[] = $SEP;
        }

        // 图片组 / 视频组：group-* 键不是注册的菜单，必须以对象形式传入
        // （含 key / title / iconSvg / menuKeys），否则触发
        // "Not found menu item factory by key 'group-image'" 并中断工具栏渲染
        if (!empty($settings['toolbar_image'])) {
            $toolbar[] = '{"key":"group-image","title":"图片","iconSvg":"<svg viewBox=\\"0 0 1024 1024\\"><path d=\\"M959.877 128l0.123 0.123v767.775l-0.123 0.123H64.123L64 895.877V128.123L64.123 128h715.754zM960 64H64C28.795 64 0 92.795 0 128v768c0 35.205 28.795 64 64 64h896c35.205 0 64-28.795 64-64V128c0-35.205-28.795-64-64-64zM832 288c0 53.019-42.981 96-96 96s-96-42.981-96-96 42.981-96 96-96 96 42.981 96 96zm-64 544H256l128-256 128 128 128-192z\\"></path></svg>","menuKeys":["insertImage","uploadImage"]}';
            $toolbar[] = $SEP;
        }

        if (!empty($settings['toolbar_video'])) {
            $toolbar[] = '{"key":"group-video","title":"视频","iconSvg":"<svg viewBox=\\"0 0 1024 1024\\"><path d=\\"M981.184 160.096H42.88C19.232 160.096 0 179.296 0 202.976v618.048c0 23.68 19.232 42.88 42.88 42.88h938.304c23.68 0 42.88-19.2 42.88-42.88V202.976c0-23.68-19.2-42.88-42.88-42.88zM128 256l128 128-128 128V256z m768 512H128l224-320 160 192 128-160 256 288z\\"></path></svg>","menuKeys":["insertVideo","uploadVideo"]}';
            $toolbar[] = $SEP;
        }

        // 表格组
        if (!empty($settings['toolbar_table'])) {
            $toolbar[] = '"insertTable"';
            $toolbar[] = $SEP;
        }

        // 全屏
        if (!empty($settings['toolbar_fullscreen'])) {
            $toolbar[] = '"fullScreen"';
        }

        // 后处理：去重菜单键、合并相邻分组符、去掉首尾分组符
        $seen = array();
        $result = array();
        foreach ($toolbar as $key) {
            if ($key === $SEP) {
                // 只有上一个元素不是分组符时才追加，避免连续两个分组符
                if (!empty($result) && $result[count($result) - 1] !== $SEP) {
                    $result[] = $SEP;
                }
            } elseif (!isset($seen[$key])) {
                $result[] = $key;
                $seen[$key] = true;
            }
        }

        // 去掉首尾多余的分组符
        while (!empty($result) && $result[0] === $SEP) {
            array_shift($result);
        }
        while (!empty($result) && $result[count($result) - 1] === $SEP) {
            array_pop($result);
        }

        // 分组符输出为带引号的字符串（wangEditor 要求 "|" 是字符串字面量）
        return '[' . implode(',', str_replace($SEP, '"|"', $result)) . ']';
    }

    /**
     * 渲染编辑器初始化脚本：
     *  1. 找到原生 #content textarea，隐藏并在其位置创建编辑器容器
     *  2. 用初始 HTML 内容创建编辑器 + 工具栏
     *  3. 内容变化时同步回 textarea，表单提交前强制同步一次
     *  4. 配置图片/视频上传（对接系统上传接口，UEditor 返回格式）
     *  5. 派发 editor:register / editor:change 标准事件供字数统计等模块使用
     */
    public function renderEditor($params = array()) {
        if (!$this->isEnabled()) {
            return;
        }

        $settings = $this->getPluginSettings();

        // 读取各项设置（含默认值兜底）
        $height          = isset($settings['height']) ? (int)$settings['height'] : 500;
        $placeholder     = isset($settings['placeholder']) ? $settings['placeholder'] : '开始写作...';
        $readonly        = !empty($settings['readonly']);
        $autoFocus       = !empty($settings['auto_focus']);
        $zIndex          = isset($settings['z_index']) ? (int)$settings['z_index'] : 10000;
        $imageUploadUrl  = isset($settings['image_upload_url']) ? $settings['image_upload_url'] : '/admin.php?action=uploadimage';
        $imageMaxSize    = isset($settings['image_max_size']) ? (int)$settings['image_max_size'] : 5;
        $videoUploadUrl  = isset($settings['video_upload_url']) ? $settings['video_upload_url'] : '/admin.php?action=uploadvideo';
        $toolbarKeys     = $this->buildToolbarKeys($settings);

        // JS 内的字符串需转义，防止占位符中出现引号破坏脚本
        $placeholderJs = json_encode($placeholder, JSON_UNESCAPED_UNICODE);
        ?>
        <script>
        (function() {
            // 等待 wangEditor UMD 全局对象可用后再初始化
            function boot() {
                var contentTextarea = document.getElementById("content");
                if (!contentTextarea) {
                    console.error("WangEditor: 未找到 #content 输入框，编辑器初始化中止");
                    return;
                }
                var wangEditorLib = window.wangEditor;
                if (!wangEditorLib || typeof wangEditorLib.createEditor !== "function") {
                    console.error("WangEditor: 库文件未加载");
                    return;
                }

                // 保存初始内容并隐藏原 textarea
                var initialContent = contentTextarea.value;
                contentTextarea.style.display = "none";

                // 在原 textarea 位置插入编辑器 DOM 结构：
                // <div id="wangeditor-container"> <div 工具栏> <div 编辑区> </div>
                var container = document.createElement("div");
                container.id = "wangeditor-container";
                container.style.border = "1px solid #ddd";
                container.style.zIndex = "<?php echo $zIndex; ?>";
                contentTextarea.parentNode.insertBefore(container, contentTextarea);

                var toolbarDiv = document.createElement("div");
                toolbarDiv.style.borderBottom = "1px solid #ddd";
                container.appendChild(toolbarDiv);

                var editorDiv = document.createElement("div");
                editorDiv.style.height = "<?php echo $height; ?>px";
                editorDiv.style.overflowY = "hidden";
                container.appendChild(editorDiv);

                // ---- 创建编辑器实例 ----
                var editor = wangEditorLib.createEditor({
                    selector: editorDiv,
                    html: initialContent,
                    config: {
                        placeholder: <?php echo $placeholderJs; ?>,
                        readOnly: <?php echo $readonly ? 'true' : 'false'; ?>,
                        autoFocus: <?php echo $autoFocus ? 'true' : 'false'; ?>,
                        // 编辑区域默认样式
                        scroll: true,
                        MENU_CONF: {
                            // ---- 图片上传配置 ----
                            uploadImage: {
                                maxFileSize: <?php echo $imageMaxSize; ?> * 1024 * 1024,
                                // 自定义上传：调用系统上传接口（UEditor 返回格式 {state, url}）
                                customUpload: function(file, insertFn) {
                                    var formData = new FormData();
                                    formData.append("upfile", file);
                                    formData.append("type", "article");

                                    fetch(<?php echo json_encode($imageUploadUrl); ?>, {
                                        method: "POST",
                                        body: formData,
                                        credentials: "include"
                                    })
                                    .then(function(response) { return response.json(); })
                                    .then(function(data) {
                                        if (data.state === "SUCCESS" && data.url) {
                                            // insertFn(图片URL, alt文本, 链接)
                                            insertFn(data.url, "", data.url);
                                        } else {
                                            alert("图片上传失败：" + (data.state || "未知错误"));
                                        }
                                    })
                                    .catch(function(error) {
                                        console.error("WangEditor 图片上传错误：", error);
                                        alert("图片上传失败");
                                    });
                                }
                            },
                            // ---- 视频上传配置 ----
                            uploadVideo: {
                                customUpload: function(file, insertFn) {
                                    var formData = new FormData();
                                    formData.append("upfile", file);
                                    formData.append("type", "article");

                                    fetch(<?php echo json_encode($videoUploadUrl); ?>, {
                                        method: "POST",
                                        body: formData,
                                        credentials: "include"
                                    })
                                    .then(function(response) { return response.json(); })
                                    .then(function(data) {
                                        if (data.state === "SUCCESS" && data.url) {
                                            insertFn(data.url);
                                        } else {
                                            alert("视频上传失败：" + (data.state || "未知错误"));
                                        }
                                    })
                                    .catch(function(error) {
                                        console.error("WangEditor 视频上传错误：", error);
                                        alert("视频上传失败");
                                    });
                                }
                            }
                        }
                    }
                });

                // ---- 创建工具栏 ----
                var toolbar = wangEditorLib.createToolbar({
                    editor: editor,
                    selector: toolbarDiv,
                    config: {
                        toolbarKeys: <?php echo $toolbarKeys; ?>
                    }
                });

                // ---- 内容同步：编辑变化时写回 textarea ----
                function syncContent() {
                    contentTextarea.value = editor.getHtml();
                }
                editor.on("change", syncContent);

                // 表单提交前强制同步一次（防止 change 事件未触发）
                var form = contentTextarea.closest("form");
                if (form) {
                    form.addEventListener("submit", syncContent);
                }

                // ============================================================
                // 派发标准 CustomEvent，供文章编辑页的字数统计 / 其它模块订阅
                // 协议（与 quilleditor 等编辑器插件保持一致）：
                //   ① editor:register（编辑器就绪）
                //        detail: { api: { getText, getHTML, on, off }, name: 'wangeditor' }
                //   ② editor:change（内容变化兜底事件）
                //        detail: { text: string, html: string }
                // ============================================================
                (function registerEditor() {
                    var api = {
                        getText: function() {
                            try { return editor.getText(); } catch (e) { return ""; }
                        },
                        getHTML: function() {
                            try { return editor.getHtml() || ""; } catch (e) { return ""; }
                        },
                        on: function(evt, cb) {
                            if (typeof cb !== "function") return api;
                            if (evt === "change") {
                                editor.on("change", cb);
                            }
                            return api;
                        },
                        off: function(evt, cb) {
                            if (evt === "change") {
                                try { editor.off("change", cb); } catch (e) {}
                            }
                            return api;
                        }
                    };

                    // 通知页面编辑器已就绪
                    document.dispatchEvent(new CustomEvent("editor:register", {
                        detail: { api: api, name: "wangeditor" }
                    }));

                    // 内容变化兜底事件
                    editor.on("change", function() {
                        document.dispatchEvent(new CustomEvent("editor:change", {
                            detail: { text: api.getText(), html: api.getHTML() }
                        }));
                    });
                })();

                // 暴露到全局，便于调试和其它模块访问
                window.wangeditorInstance = editor;
                window.wangeditorToolbar = toolbar;
            }

            // DOM 就绪后启动；库脚本先于本脚本加载，因此直接执行即可
            if (document.readyState === "loading") {
                document.addEventListener("DOMContentLoaded", boot);
            } else {
                boot();
            }
        })();
        </script>
        <?php
    }

    /**
     * 内容保存前钩子（预留：wangEditor 直接同步 textarea，无需额外处理）
     */
    public function handleEditorContent($params = array()) {
        if (!$this->isEnabled()) {
            return;
        }
    }

    /**
     * 判断插件是否已启用（查询数据库 plugin 表）
     */
    public function isEnabled() {
        $db = Database::getInstance();
        $plugin = $db->fetch("SELECT * FROM {$db->table('plugin')} WHERE slug = ?", array($this->info['slug']));
        return $plugin && $plugin['status'] == 1;
    }

    /** 安装钩子（无需建表） */
    public function install() {
        return true;
    }

    /** 激活钩子 */
    public function activate() {
        return true;
    }

    /** 停用钩子 */
    public function deactivate() {
        return true;
    }

    /**
     * 卸载钩子
     * @param bool|null $keepData 是否保留数据（null 时读取插件配置）
     */
    public function uninstall($keepData = null) {
        if ($keepData === null) {
            $pluginSystem = Plugin::getInstance();
            $settings = $pluginSystem->getPluginConfig($this->info['slug']);
            $keepData = isset($settings['keep_data_on_uninstall']) ? $settings['keep_data_on_uninstall'] : true;
        }

        if (!$keepData) {
            Cache::delete('plugin_wangeditor_settings');
        }

        return true;
    }
}
?>
