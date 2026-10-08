/**
 * 文章编辑器公共脚本（添加文章 / 编辑文章两页共用）
 *
 * 包含功能模块：
 *  1. 编辑器元信息抽屉（分类/标签/封面/别名/描述/置顶收纳进右侧抽屉，写作区保持沉浸）
 *  2. 本地草稿暂存（标题/正文定时写入 localStorage，浏览器崩溃或误关后可恢复；
 *     状态条实时显示「已暂存 HH:MM」，提交成功后由页面跳转前清除）
 *  3. 标题/描述字符统计
 *  4. 发布 / 存为草稿（动态写入 status 隐藏字段后提交表单）
 *  5. 封面图设置（本地上传 / 从正文选图 / 从媒体库选图，含两个选图弹窗的分页渲染）
 *
 * 与富文本编辑器插件的协议：
 *  插件可在 document 上派发 editor:register 事件并提供 api（evt.detail.api），
 *  本脚本与 article-description-helper.js 遵循同一协议：优先从已注册编辑器读取
 *  正文（api.getContent / api.getText），未注册时回退读取 textarea#content 原始值。
 *
 * 页面接入方式（模板内、引入本文件之前）：
 *  window.ARTICLE_DRAFT_KEY = 'xxx';   // 本地草稿的 localStorage 键名，未设置则不启用草稿暂存
 */

(function() {
    'use strict';

    // 已注册的富文本编辑器 API（编辑器插件通过 editor:register 事件传入）
    var editorApi = null;

    if (window.addEventListener) {
        window.addEventListener('editor:register', function(evt) {
            if (evt && evt.detail && evt.detail.api) {
                editorApi = evt.detail.api;
            }
        }, false);
    }

    /**
     * 读取正文内容（兼容编辑器协议）。
     * 优先级：api.getContent()（HTML，草稿保真） > api.getText() > textarea 原始值
     */
    function readContentHtml() {
        if (editorApi) {
            try {
                if (typeof editorApi.getContent === 'function') {
                    var html = editorApi.getContent();
                    if (typeof html === 'string') return html;
                }
                if (typeof editorApi.getText === 'function') {
                    var text = editorApi.getText();
                    if (typeof text === 'string') return text;
                }
            } catch (e) { /* 编辑器异常时回退读取 textarea */ }
        }
        var textarea = document.getElementById('content');
        return textarea ? textarea.value : '';
    }

    // ==================== 元信息抽屉 ====================

    /** 打开右侧元信息抽屉 */
    window.openEditorDrawer = function() {
        var drawer = document.getElementById('editor-drawer');
        var overlay = document.getElementById('editor-drawer-overlay');
        if (!drawer) return;
        drawer.classList.add('show');
        if (overlay) overlay.classList.add('show');
        // 焦点移入抽屉首个可交互控件，方便键盘直接填写
        var first = drawer.querySelector('button, input, select, textarea');
        if (first) first.focus();
    };

    /** 关闭右侧元信息抽屉（common.js 全局 Esc 也会调用） */
    window.closeEditorDrawer = function() {
        var drawer = document.getElementById('editor-drawer');
        var overlay = document.getElementById('editor-drawer-overlay');
        if (drawer) drawer.classList.remove('show');
        if (overlay) overlay.classList.remove('show');
    };

    /** 抽屉开合切换（顶栏「文章设置」按钮） */
    window.toggleEditorDrawer = function() {
        var drawer = document.getElementById('editor-drawer');
        if (drawer && drawer.classList.contains('show')) {
            window.closeEditorDrawer();
        } else {
            window.openEditorDrawer();
        }
    };

    // ==================== 本地草稿暂存 ====================

    var draftTimer = null;          // 输入防抖定时器（3 秒静默后写入）
    var lastSnapshot = null;        // 上一次暂存的内容快照（避免无变化重复写入）

    /**
     * 组装草稿快照：标题 + 正文 + 暂存时间
     */
    function buildDraftSnapshot() {
        var titleInput = document.getElementById('title');
        return {
            title: titleInput ? titleInput.value : '',
            content: readContentHtml(),
            time: Date.now()
        };
    }

    /**
     * 更新顶栏保存状态条文案
     * @param {string} text 状态文案（如「已暂存 14:32」）
     */
    function setSaveStatus(text) {
        var el = document.getElementById('editor-save-status');
        if (el) el.textContent = text;
    }

    /**
     * 立即写入草稿（防抖到期后调用）
     */
    function flushDraft() {
        if (!window.ARTICLE_DRAFT_KEY) return;
        var snap = buildDraftSnapshot();
        // 标题与正文均为空时不写入，避免空草稿覆盖历史暂存
        if (!snap.title && !snap.content) return;
        // 与上次快照完全一致时跳过写入，减少无意义的存储操作
        if (lastSnapshot && lastSnapshot.title === snap.title && lastSnapshot.content === snap.content) return;

        try {
            localStorage.setItem(window.ARTICLE_DRAFT_KEY, JSON.stringify(snap));
            lastSnapshot = snap;
            var t = new Date(snap.time);
            var hh = ('0' + t.getHours()).slice(-2);
            var mm = ('0' + t.getMinutes()).slice(-2);
            setSaveStatus('已暂存 ' + hh + ':' + mm);
        } catch (e) { /* 隐私模式等存储异常时静默降级，不影响正常写作 */ }
    }

    /**
     * 输入触发：重置防抖定时器，3 秒静默后暂存
     */
    function scheduleDraft() {
        if (!window.ARTICLE_DRAFT_KEY) return;
        setSaveStatus('正在编辑…');
        if (draftTimer) clearTimeout(draftTimer);
        draftTimer = setTimeout(flushDraft, 3000);
    }

    /**
     * 页面加载时检测是否有可恢复的本地草稿：
     * 与当前表单内容存在差异时弹确认框询问是否恢复
     */
    function restoreDraftIfAny() {
        if (!window.ARTICLE_DRAFT_KEY) return;
        var raw = null;
        try {
            raw = localStorage.getItem(window.ARTICLE_DRAFT_KEY);
        } catch (e) { return; }
        if (!raw) return;

        var draft;
        try {
            draft = JSON.parse(raw);
        } catch (e) {
            // 键内容损坏时直接清理，避免反复弹窗
            localStorage.removeItem(window.ARTICLE_DRAFT_KEY);
            return;
        }
        if (!draft || (typeof draft.title !== 'string' && typeof draft.content !== 'string')) return;

        var titleInput = document.getElementById('title');
        var currentTitle = titleInput ? titleInput.value : '';
        var currentContent = document.getElementById('content') ? document.getElementById('content').value : '';

        // 草稿与当前内容完全一致（例如刷新页面）时静默清理，不打扰用户
        if (draft.title === currentTitle && draft.content === currentContent) {
            localStorage.removeItem(window.ARTICLE_DRAFT_KEY);
            return;
        }

        var t = draft.time ? new Date(draft.time) : null;
        var timeText = t ? ('，暂存于 ' + ('0' + t.getHours()).slice(-2) + ':' + ('0' + t.getMinutes()).slice(-2)) : '';
        // 统一确认弹窗（common.js）：恢复 / 放弃
        showConfirmDialog('检测到未提交的本地草稿' + timeText + '，是否恢复？放弃将清除该草稿。', {
            title: '恢复本地草稿',
            type: 'info',
            confirmText: '恢复',
            cancelText: '放弃'
        }).then(function(ok) {
            if (ok) {
                if (titleInput && typeof draft.title === 'string') {
                    titleInput.value = draft.title;
                    updateTitleCounter();
                }
                var textarea = document.getElementById('content');
                if (textarea && typeof draft.content === 'string') {
                    // 未接入富文本编辑器时直接回填；已接入时回填后由编辑器插件
                    // 监听 input 同步（若插件未同步，textarea 值提交时仍生效）
                    textarea.value = draft.content;
                    textarea.dispatchEvent(new Event('input', { bubbles: true }));
                }
                setSaveStatus('已恢复本地草稿');
            } else {
                localStorage.removeItem(window.ARTICLE_DRAFT_KEY);
            }
        });
    }

    // ==================== 标题字符统计 ====================

    window.updateTitleCounter = function() {
        var titleInput = document.getElementById('title');
        var counter = document.getElementById('title-counter');
        if (!titleInput || !counter) return;

        var len = titleInput.value.length;
        counter.textContent = len + '/60';

        // 超限时计数变红（token 语义类，双主题自动适配）
        counter.classList.toggle('counter-over', len > 60);
    };

    // ==================== 发布 / 草稿 ====================

    /**
     * 提交表单前写入指定状态值并清空本地草稿
     * @param {string} status '1'=发布，'0'=草稿
     */
    function submitWithStatus(status) {
        var form = document.getElementById('article-form');
        var statusInput = document.getElementById('status');
        if (!statusInput) {
            statusInput = document.createElement('input');
            statusInput.type = 'hidden';
            statusInput.name = 'status';
            form.appendChild(statusInput);
        }
        statusInput.value = status;
        // 正式提交前清除本地草稿，避免下次进入该页误报「检测到未提交草稿」
        if (window.ARTICLE_DRAFT_KEY) {
            try { localStorage.removeItem(window.ARTICLE_DRAFT_KEY); } catch (e) {}
        }
        form.submit();
    }

    window.publishArticle = function() { submitWithStatus('1'); };
    window.saveDraft = function() { submitWithStatus('0'); };

    // ==================== 封面图设置功能 ====================

    window.showCoverPreview = function(imageUrl) {
        var previewArea = document.getElementById('cover-preview-area');
        var previewImg = document.getElementById('current-cover-img');
        var noCoverHint = document.getElementById('no-cover-hint');
        if (previewArea && previewImg) {
            previewImg.src = imageUrl;
            previewArea.style.display = 'block';
            if (noCoverHint) noCoverHint.style.display = 'none';
        }
    };

    window.hideCoverPreview = function() {
        var previewArea = document.getElementById('cover-preview-area');
        var noCoverHint = document.getElementById('no-cover-hint');
        if (previewArea) {
            previewArea.style.display = 'none';
            // 置空字符串恢复样式表定义的 display（虚线卡片为 inline-flex），
            // 避免硬编码 block 破坏卡片布局
            if (noCoverHint) noCoverHint.style.display = '';
        }
    };

    // ==================== 弹窗图片分页选择器 ====================
    // 每页显示的图片数量（对应 4 列网格 x 3 行）
    var PICKER_PAGE_SIZE = 12;

    // 通用：按分页渲染图片网格（items 为图片 URL 数组或媒体库对象数组）
    function renderPickerItems(grid, items, page, onSelect, isMedia) {
        grid.innerHTML = '';
        grid.style.display = 'grid';
        var startIdx = (page - 1) * PICKER_PAGE_SIZE;
        // 截取当前页对应的图片切片
        var pageItems = items.slice(startIdx, startIdx + PICKER_PAGE_SIZE);

        pageItems.forEach(function(item) {
            var url = isMedia ? item.url : item;
            var name = isMedia ? item.name : '';

            var cell = document.createElement('div');
            cell.className = 'picker-cell';
            cell.onclick = function() { onSelect(url); };

            var img = document.createElement('img');
            img.src = url;
            img.alt = name || '候选封面图';
            // 图片加载失败时隐藏该单元格（如外链失效图片）
            img.onerror = function() { cell.style.display = 'none'; };
            cell.appendChild(img);

            // 媒体库图片额外显示文件名与大小
            if (isMedia && name) {
                var caption = document.createElement('div');
                caption.className = 'picker-cell-caption';
                caption.textContent = name + (item.size_formatted ? ' (' + item.size_formatted + ')' : '');
                cell.appendChild(caption);
            }

            grid.appendChild(cell);
        });
    }

    // 通用：构建分页条（上一页 / 页码 / 下一页）
    function buildPickerPagination(container, totalPages, currentPage, onGo) {
        container.innerHTML = '';
        // 分页条样式类（forms.css）：居中排列 + 可换行
        container.className = 'picker-pagination';
        // 只有一页时不显示分页条
        if (totalPages <= 1) {
            container.style.display = 'none';
            return;
        }
        container.style.display = 'flex';

        // 创建单个分页按钮（current 为 true 时高亮当前页）
        function makeBtn(text, page, disabled, current) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = text;
            btn.className = 'picker-page-btn' + (current ? ' picker-page-btn-current' : '');
            if (disabled) {
                btn.disabled = true;
            } else {
                btn.onclick = function() { onGo(page); };
            }
            return btn;
        }

        container.appendChild(makeBtn('上一页', currentPage - 1, currentPage <= 1, false));
        // 页码窗口：最多显示 5 个页码，超出时在当前页左右各取 2 页
        var start = Math.max(1, currentPage - 2);
        var end = Math.min(totalPages, start + 4);
        start = Math.max(1, end - 4);
        for (var p = start; p <= end; p++) {
            container.appendChild(makeBtn(String(p), p, false, p === currentPage));
        }
        container.appendChild(makeBtn('下一页', currentPage + 1, currentPage >= totalPages, false));
    }

    // 通用：分页渲染指定弹窗（grid + 空提示 + 分页条）
    function renderPickerPage(gridId, images, page, emptyId, emptyText, paginationId, onSelect, isMedia) {
        var grid = document.getElementById(gridId);
        var emptyHint = document.getElementById(emptyId);
        var pagination = document.getElementById(paginationId);
        if (!grid) return;

        var totalPages = Math.ceil(images.length / PICKER_PAGE_SIZE);
        // 纠正越界页码
        if (page > totalPages) page = totalPages;
        if (page < 1) page = 1;

        if (images.length === 0) {
            // 无图片：隐藏网格与分页条，显示空提示
            grid.style.display = 'none';
            grid.innerHTML = '';
            pagination.style.display = 'none';
            emptyHint.textContent = emptyText;
            emptyHint.style.display = 'block';
            return;
        }

        emptyHint.style.display = 'none';
        renderPickerItems(grid, images, page, onSelect, isMedia);
        buildPickerPagination(pagination, totalPages, page, function(newPage) {
            // 翻页回调：重新渲染指定页
            renderPickerPage(gridId, images, newPage, emptyId, emptyText, paginationId, onSelect, isMedia);
        });
    }

    window.openImagePicker = function() {
        var modal = document.getElementById('image-picker-modal');
        if (!modal) return;

        // 从正文内容中提取所有 <img> 的 src（去重），作为候选图片列表
        var content = readContentHtml();
        var imgRegex = /<img[^>]+src\s*=\s*["']([^"']+)["']/gi;
        var match;
        var imageUrls = [];
        while ((match = imgRegex.exec(content)) !== null) {
            var url = match[1].trim();
            if (url && imageUrls.indexOf(url) === -1) {
                imageUrls.push(url);
            }
        }

        // 分页渲染第 1 页
        renderPickerPage('image-picker-grid', imageUrls, 1, 'image-picker-empty', '文章内容中没有找到图片', 'image-picker-pagination', selectImageAsCover, false);

        // 通过 .show 类显示弹窗（显隐由 components.css 的 .modal-overlay 体系控制）
        modal.classList.add('show');
    };

    window.closeImagePicker = function() {
        var modal = document.getElementById('image-picker-modal');
        if (modal) modal.classList.remove('show');
    };

    function selectImageAsCover(imageUrl) {
        document.getElementById('cover_image_url').value = imageUrl;
        document.getElementById('remove_cover_image').value = '0';
        document.getElementById('cover_image').value = '';
        window.showCoverPreview(imageUrl);
        window.closeImagePicker();
    }

    // 媒体库图片列表缓存
    var mediaPickerImages = [];

    window.openMediaPicker = function() {
        var modal = document.getElementById('media-picker-modal');
        if (!modal) return;
        // 通过 .show 类显示弹窗（显隐由 components.css 的 .modal-overlay 体系控制）
        modal.classList.add('show');
        // 每次打开都重新请求，保证能看到编辑器刚上传的图片
        loadMediaPickerImages();
    };

    // 通过 AJAX 实时加载媒体库图片列表（服务端接口：ArticleController::mediaList）
    function loadMediaPickerImages() {
        var grid = document.getElementById('media-picker-grid');
        var emptyHint = document.getElementById('media-picker-empty');
        var pagination = document.getElementById('media-picker-pagination');
        var loading = document.getElementById('media-picker-loading');
        if (!grid) return;

        // 显示加载中提示，隐藏其它区域
        grid.style.display = 'none';
        grid.innerHTML = '';
        pagination.style.display = 'none';
        emptyHint.style.display = 'none';
        loading.style.display = 'block';

        // 加时间戳防止浏览器缓存旧列表
        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'admin.php?action=article&sub=mediaList&t=' + Date.now(), true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState !== 4) return;
            loading.style.display = 'none';
            if (xhr.status !== 200) {
                emptyHint.textContent = '加载图片失败，请重试';
                emptyHint.style.display = 'block';
                return;
            }
            try {
                var res = JSON.parse(xhr.responseText);
                mediaPickerImages = (res && res.data && res.data.images) ? res.data.images : [];
            } catch (e) {
                mediaPickerImages = [];
            }
            // 渲染第 1 页
            renderPickerPage('media-picker-grid', mediaPickerImages, 1, 'media-picker-empty', '媒体库中暂无图片，请先上传图片', 'media-picker-pagination', selectFromMedia, true);
        };
        xhr.send();
    }

    window.closeMediaPicker = function() {
        var modal = document.getElementById('media-picker-modal');
        if (modal) modal.classList.remove('show');
    };

    function selectFromMedia(imageUrl) {
        document.getElementById('cover_image_url').value = imageUrl;
        document.getElementById('remove_cover_image').value = '0';
        document.getElementById('cover_image').value = '';
        window.showCoverPreview(imageUrl);
        window.closeMediaPicker();
    }

    // ==================== 页面加载初始化 ====================

    document.addEventListener('DOMContentLoaded', function() {
        // 初始化标题计数显示
        window.updateTitleCounter();

        // 描述计数器由 article-description-helper.js 提供（本文件先于其加载时兼容跳过）
        if (typeof window.updateDescriptionCounter === 'function') {
            window.updateDescriptionCounter();
        }

        // 标题输入：字符统计 + 草稿防抖暂存
        var titleInput = document.getElementById('title');
        if (titleInput) {
            titleInput.addEventListener('input', function() {
                window.updateTitleCounter();
                scheduleDraft();
            });
        }

        // 正文输入：草稿防抖暂存（富文本编辑器接入后由插件自行同步 textarea，
        // 仍以 textarea input 事件兜底）
        var contentTextarea = document.getElementById('content');
        if (contentTextarea) {
            contentTextarea.addEventListener('input', scheduleDraft);
        }

        // 顶栏「文章设置」按钮：开合元信息抽屉
        var drawerToggle = document.getElementById('btn-editor-drawer');
        if (drawerToggle) {
            drawerToggle.addEventListener('click', window.toggleEditorDrawer);
        }
        // 抽屉头部 × 与遮罩点击关闭
        var drawerClose = document.getElementById('editor-drawer-close');
        if (drawerClose) {
            drawerClose.addEventListener('click', window.closeEditorDrawer);
        }
        var drawerOverlay = document.getElementById('editor-drawer-overlay');
        if (drawerOverlay) {
            drawerOverlay.addEventListener('click', window.closeEditorDrawer);
        }

        // ==================== 封面图按钮事件绑定 ====================
        // 上传触发器：优先旧「本地上传图片」按钮；现版本为虚线卡片
        // （#no-cover-hint，卡片式触发）
        var btnUpload = document.getElementById('btn-upload-cover') || document.getElementById('no-cover-hint');
        var fileInput = document.getElementById('cover_image');
        if (btnUpload && fileInput) {
            btnUpload.addEventListener('click', function() {
                fileInput.click();
            });
        }

        if (fileInput) {
            fileInput.addEventListener('change', function(e) {
                if (e.target.files && e.target.files[0]) {
                    document.getElementById('remove_cover_image').value = '0';
                    document.getElementById('cover_image_url').value = '';

                    var reader = new FileReader();
                    reader.onload = function(evt) {
                        window.showCoverPreview(evt.target.result);
                    };
                    reader.readAsDataURL(e.target.files[0]);
                }
            });
        }

        var btnPick = document.getElementById('btn-pick-from-content');
        if (btnPick) {
            btnPick.addEventListener('click', function() {
                window.openImagePicker();
            });
        }

        var btnRemove = document.getElementById('btn-remove-cover');
        if (btnRemove) {
            btnRemove.addEventListener('click', function() {
                showConfirmDialog('确定要移除封面图吗？文章列表页将不会显示图片。', { type: 'info', title: '移除封面' }).then(function(ok) {
                    if (ok) {
                        document.getElementById('remove_cover_image').value = '1';
                        document.getElementById('cover_image_url').value = '';
                        document.getElementById('cover_image').value = '';
                        window.hideCoverPreview();
                    }
                });
            });
        }

        var btnMediaPick = document.getElementById('btn-pick-from-media');
        if (btnMediaPick) {
            btnMediaPick.addEventListener('click', function() {
                window.openMediaPicker();
            });
        }

        // 草稿恢复检测（放在最后，保证各控件事件已就绪后再回填）
        restoreDraftIfAny();
    });
})();
