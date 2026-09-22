// BlogKit 文章详情页交互脚本
// 从 article.html 提取，包含：点赞/收藏AJAX、评论加载/渲染/分页/回复、分享面板交互

(function() {
    'use strict';
    
    /**
     * 相对时间格式化函数（与 PHP filter_relative1 一致）
     * 规则：
     * <1分钟：XX秒前
     * 1min～60min：XX分钟前
     * 1h～当天 24 点：XX小时前
     * 跨天<48h：昨天 HH:MM
     * 2～7 天：X 天前
     * ＞7 天：M-D（5-12）
     * 跨年：YYYY-M-D（2025-03-10）
     */
    function formatRelativeTime(timestamp) {
        if (typeof timestamp === 'string' && timestamp.match(/^\d{4}-\d{2}-\d{2}/)) {
            timestamp = new Date(timestamp).getTime() / 1000;
        } else if (typeof timestamp === 'string') {
            timestamp = parseInt(timestamp);
        } else if (typeof timestamp === 'number' && timestamp > 9999999999) {
            timestamp = timestamp / 1000; // 毫秒转秒
        }
        
        var now = Math.floor(Date.now() / 1000);
        var diff = now - timestamp;
        var dateObj = new Date(timestamp * 1000);
        
        var todayStart = new Date();
        todayStart.setHours(0, 0, 0, 0);
        var todayStartTimestamp = Math.floor(todayStart.getTime() / 1000);
        
        var yesterdayStart = new Date(todayStart);
        yesterdayStart.setDate(yesterdayStart.getDate() - 1);
        var yesterdayStartTimestamp = Math.floor(yesterdayStart.getTime() / 1000);
        
        var isSameDay = timestamp >= todayStartTimestamp;
        var isYesterday = timestamp >= yesterdayStartTimestamp && timestamp < todayStartTimestamp;
        
        var thisYear = new Date().getFullYear();
        var thatYear = dateObj.getFullYear();
        var isCrossYear = thisYear !== thatYear;
        
        if (diff < 60) {
            // < 1分钟
            return diff + '秒前';
        } else if (diff < 3600) {
            // 1分钟 ~ 60分钟
            var minutes = Math.floor(diff / 60);
            return minutes + '分钟前';
        } else if (isSameDay) {
            // 当天剩余时间
            var hours = Math.floor(diff / 3600);
            return hours + '小时前';
        } else if (isYesterday) {
            // 昨天
            var hours = ('0' + dateObj.getHours()).slice(-2);
            var minutes = ('0' + dateObj.getMinutes()).slice(-2);
            return '昨天 ' + hours + ':' + minutes;
        } else {
            var days = Math.floor(diff / 86400);
            if (days <= 7) {
                // 2 ~ 7天
                return days + '天前';
            } else if (!isCrossYear) {
                // 当年，显示 MM-DD
                var month = ('0' + (dateObj.getMonth() + 1)).slice(-2);
                var day = ('0' + dateObj.getDate()).slice(-2);
                return month + '-' + day;
            } else {
                // 跨年，显示 YYYY-MM-DD
                var year = dateObj.getFullYear();
                var month = ('0' + (dateObj.getMonth() + 1)).slice(-2);
                var day = ('0' + dateObj.getDate()).slice(-2);
                return year + '-' + month + '-' + day;
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        // 读取模板注入的配置
        var articleEl = document.getElementById('article-page');
        if (!articleEl) return;
        
        var articleId = parseInt(articleEl.dataset.articleId) || 0;
        var currentUserId = parseInt(articleEl.dataset.currentUserId) || 0;
        var articleAuthorId = parseInt(articleEl.dataset.articleAuthorId) || 0;
        var siteUrl = articleEl.dataset.siteUrl || '';
        var isArticleAuthor = (currentUserId === articleAuthorId && currentUserId > 0);
        
        if (!siteUrl) siteUrl = window.location.origin;
        if (!siteUrl.endsWith('/')) siteUrl += '/';
        
        // ============ 收藏功能 ============
        $('.post-actions .favorite-btn').click(function() {
            var btn = $(this);
            var aId = btn.data('article-id') || articleId;
            var isFavorited = btn.hasClass('favorited');
            var action = isFavorited ? 'remove' : 'add';
            
            if (!currentUserId) { alert('请先登录'); return; }
            
            $.ajax({
                url: siteUrl + 'index.php?c=Home&m=favorite',
                type: 'POST',
                data: { article_id: aId, action: action },
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        if (action === 'add') {
                            btn.addClass('favorited');
                        } else {
                            btn.removeClass('favorited');
                        }
                        // 仅更新数字角标，SVG 图标颜色由 CSS 类控制
                        btn.find('.action-badge').text(res.favorite_count);
                    } else { alert(res.msg); }
                },
                error: function() { alert('操作失败，请稍后重试'); }
            });
        });
        
        // ============ 点赞功能 ============
        $('.post-actions .article-like-btn').click(function() {
            var btn = $(this);
            var aId = btn.data('article-id') || articleId;
            var isLiked = btn.hasClass('liked');
            var action = isLiked ? 'remove' : 'add';
            
            if (!currentUserId) { alert('请先登录'); return; }
            
            $.ajax({
                url: siteUrl + 'index.php?c=Home&m=like',
                type: 'POST',
                data: { article_id: aId, action: action },
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        if (action === 'add') {
                            btn.addClass('liked');
                        } else {
                            btn.removeClass('liked');
                        }
                        // 仅更新数字角标，SVG 图标颜色由 CSS 类控制
                        btn.find('.action-badge').text(res.like_count);
                    } else { alert(res.msg); }
                },
                error: function() { alert('操作失败，请稍后重试'); }
            });
        });
        
        // ============ 评论系统 ============
        var currentPage = 1;
        var currentSort = 'latest';
        var pageSize = 10; // 默认值，首次加载后从后端响应中获取真实值
        var isInitialLoad = true;
        var currentCommentsData = null; // 保存当前评论数据
        // 存储每个评论的回复展开状态
        var expandedReplies = {}; // commentId -> visibleCount

        // ============ 点赞按钮 SVG 图标 ============
        var LIKE_ICON_OUTLINE = '<svg width="14" height="14" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4.18898 22.1733C4.08737 21.0047 5.00852 20 6.18146 20H10C11.1046 20 12 20.8954 12 22V41C12 42.1046 11.1046 43 10 43H7.83363C6.79622 43 5.93102 42.2068 5.84115 41.1733L4.18898 22.1733Z" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/><path d="M18 21.3745C18 20.5388 18.5194 19.7908 19.2753 19.4345C20.9238 18.6574 23.7329 17.0938 25 14.9805C26.6331 12.2569 26.9411 7.33595 26.9912 6.20878C26.9982 6.05099 26.9937 5.89301 27.0154 5.73656C27.2861 3.78446 31.0543 6.06492 32.5 8.47612C33.2846 9.78471 33.3852 11.504 33.3027 12.8463C33.2144 14.2825 32.7933 15.6699 32.3802 17.0483L31.5 19.9845H42.3569C43.6832 19.9845 44.6421 21.2518 44.2816 22.5281L38.9113 41.5436C38.668 42.4051 37.8818 43 36.9866 43H20C18.8954 43 18 42.1046 18 41V21.3745Z" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        var LIKE_ICON_FILLED = '<svg width="14" height="14" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4.18898 22.1733C4.08737 21.0047 5.00852 20 6.18146 20H10C11.1046 20 12 20.8954 12 22V41C12 42.1046 11.1046 43 10 43H7.83363C6.79622 43 5.93102 42.2068 5.84115 41.1733L4.18898 22.1733Z" fill="currentColor" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/><path d="M18 21.3745C18 20.5388 18.5194 19.7908 19.2753 19.4345C20.9238 18.6574 23.7329 17.0938 25 14.9805C26.6331 12.2569 26.9411 7.33595 26.9912 6.20878C26.9982 6.05099 26.9937 5.89301 27.0154 5.73656C27.2861 3.78446 31.0543 6.06492 32.5 8.47612C33.2846 9.78471 33.3852 11.504 33.3027 12.8463C33.2144 14.2825 32.7933 15.6699 32.3802 17.0483L31.5 19.9845H42.3569C43.6832 19.9845 44.6421 21.2518 44.2816 22.5281L38.9113 41.5436C38.668 42.4051 37.8818 43 36.9866 43H20C18.8954 43 18 42.1046 18 41V21.3745Z" fill="currentColor" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        // 收起/查看更多图标
        var COLLAPSE_ICON = '<svg width="14" height="14" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M13 30L25 18L37 30" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        var EXPAND_ICON = '<svg width="14" height="14" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M36 18L24 30L12 18" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></svg>';

        // 回复按钮图标
        var REPLY_ICON_OUTLINE = '<svg width="14" height="14" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M44 6H4V36H13V41L23 36H44V6Z" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 19.5V22.5" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/><path d="M24 19.5V22.5" stroke="#8a919f" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/><path d="M34 19.5V22.5" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        var REPLY_ICON_FILLED = '<svg width="14" height="14" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M44 6H4V36H13V41L23 36H44V6Z" fill="currentColor" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 19.5V22.5" stroke="#FFF" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/><path d="M24 19.5V22.5" stroke="#FFF" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/><path d="M34 19.5V22.5" stroke="#FFF" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></svg>';

        /**
         * 根据点赞状态和数量渲染点赞按钮内容
         * @param {boolean} isLiked 是否已点赞
         * @param {number} likeCount 点赞数量
         * @returns {string} HTML内容
         */
        function renderLikeButtonContent(isLiked, likeCount) {
            likeCount = likeCount || 0;
            // 0 点赞且未点赞：显示空心图标 + "点赞"文字
            // 有任何点赞（isLiked 或 likeCount > 0）：显示对应图标 + 数字
            if (likeCount > 0 || isLiked) {
                var icon = isLiked ? LIKE_ICON_FILLED : LIKE_ICON_OUTLINE;
                return icon + ' ' + likeCount;
            } else {
                return LIKE_ICON_OUTLINE + ' 点赞';
            }
        }

        /**
         * 根据回复数量和展开状态渲染回复按钮内容
         * @param {number} replyCount 回复数量
         * @param {boolean} isExpanded 是否已展开（点击后状态）
         * @returns {string} HTML内容
         */
        function renderReplyButtonContent(replyCount, isExpanded) {
            replyCount = replyCount || 0;
            // 已展开（点击后）：总是显示实心图标 + "取消"
            if (isExpanded) {
                return REPLY_ICON_FILLED + ' 取消';
            }
            // 未展开：
            //   - 有回复（>= 1）：显示空心图标 + 回复数量
            //   - 无回复：显示空心图标 + "回复"文字
            if (replyCount > 0) {
                return REPLY_ICON_OUTLINE + ' ' + replyCount;
            } else {
                return REPLY_ICON_OUTLINE + ' 回复';
            }
        }

        // ============ 验证码与评论配置 ============
        // 从 article 元素的 data 属性读取验证码配置（模板注入）
        var captchaEnabled = (articleEl.dataset.captchaEnabled === '1' || articleEl.dataset.captchaEnabled === 'true');
        var captchaCommentEnabled = (articleEl.dataset.captchaCommentEnabled === '1' || articleEl.dataset.captchaCommentEnabled === 'true');
        var captchaWidth = parseInt(articleEl.dataset.captchaWidth) || 120;
        var captchaHeight = parseInt(articleEl.dataset.captchaHeight) || 40;
        var captchaFontSize = parseInt(articleEl.dataset.captchaFontSize) || 20;
        var commentMinLength = parseInt(articleEl.dataset.commentMinLength) || 1;
        var commentMaxLength = parseInt(articleEl.dataset.commentMaxLength) || 1000;
        var commentEnabled = (articleEl.dataset.commentEnabled === '1' || articleEl.dataset.commentEnabled === 'true');

        // ---------- 通用评论表单生成函数 ----------
        // 为顶部评论和内联回复框提供统一的表单 HTML 结构
        // options: { isInline, articleId, parentId, authorName, minLength, maxLength, showCaptcha, captchaWidth, captchaHeight, captchaFontSize }
        function renderCommentForm(options) {
            var isInline = options.isInline || false;
            var articleIdValue = options.articleId || articleId;
            var parentIdValue = options.parentId || 0;
            var authorName = options.authorName || '';
            var minLen = options.minLength || commentMinLength;
            var maxLen = options.maxLength || commentMaxLength;
            var showCaptcha = (typeof options.showCaptcha === 'boolean')
                ? options.showCaptcha
                : (captchaEnabled && captchaCommentEnabled);

            // 容器类名和表单类名
            var containerClass = isInline ? 'inline-reply-form' : 'comment-form';
            var formClass = isInline ? 'inline-reply-form-inner' : '';
            var textareaClass = isInline ? 'inline-reply-content' : 'comment-textarea';
            var textareaWrapperClass = isInline ? 'inline-reply-textarea-wrapper' : 'comment-textarea-wrapper';
            var counterClass = isInline ? 'inline-char-counter' : 'char-counter';
            var currentLengthClass = isInline ? 'inline-current-length' : 'current-length';
            var maxLengthClass = isInline ? 'inline-max-length' : 'max-length';
            var actionsClass = isInline ? 'inline-reply-actions' : 'form-group';
            var submitBtnClass = isInline ? 'inline-submit-reply' : 'submit-comment btn btn-primary';
            var cancelBtnClass = isInline ? 'inline-cancel-reply' : '';
            var placeholder = isInline ? ('回复 @' + authorName + '...') : '写下你的评论...';
            var submitText = isInline ? '提交回复' : '提交评论';

            var html = '';
            // 内联回复框的容器
            if (isInline) {
                html += '<div class="' + containerClass + '" data-comment-id="' + parentIdValue + '" style="display:none;">';
            }
            html += '<form class="' + formClass + '">';
            html += '<input type="hidden" name="article_id" value="' + articleIdValue + '">';
            html += '<input type="hidden" name="parent_id" value="' + parentIdValue + '">';
            html += '<div class="' + textareaWrapperClass + '">';
            html += '<textarea name="content" rows="' + (isInline ? '3' : '5') + '" placeholder="' + placeholder + '" class="' + textareaClass + '" data-min-length="' + minLen + '" data-max-length="' + maxLen + '" required></textarea>';
            html += '<div class="' + counterClass + '">';
            html += '<span class="' + currentLengthClass + '">0</span>';
            if (isInline) {
                html += '<span class="inline-length-separator">/</span>';
            } else {
                html += '<span class="length-separator">/</span>';
            }
            html += '<span class="' + maxLengthClass + '">' + maxLen + '</span>';
            html += '</div></div>';

            // 验证码区域（只有顶部评论表单和启用验证码时才显示）
            if (showCaptcha && isInline) {
                html += '<div class="inline-captcha-wrapper">';
                html += '<div class="captcha-wrapper" data-captcha-width="' + captchaWidth + '" data-captcha-height="' + captchaHeight + '" data-captcha-font-size="' + captchaFontSize + '"></div>';
                html += '</div>';
            }

            html += '<div class="' + actionsClass + '">';
            html += '<button type="submit" class="' + submitBtnClass + '">' + submitText + '</button>';
            if (isInline) {
                html += '<button type="button" class="' + cancelBtnClass + '">取消</button>';
            }
            html += '</div>';
            html += '</form>';
            if (isInline) {
                html += '</div>';
            }
            return html;
        }

        // ---------- 内联回复框 ----------
        // 生成内联回复框的 HTML（插入到每条评论的下方）
        function renderInlineReplyForm(articleIdValue, parentIdValue, authorName) {
            return renderCommentForm({
                isInline: true,
                articleId: articleIdValue,
                parentId: parentIdValue,
                authorName: authorName,
                minLength: commentMinLength,
                maxLength: commentMaxLength
            });
        }
        
        function loadComments(page, sort, skipRequest, autoScroll) {
            page = page || 1;
            sort = sort || currentSort;
            currentPage = page;
            currentSort = sort;

            // 默认行为：分页切换时自动滚动，排序切换时不滚动
            if (typeof autoScroll === 'undefined') {
                autoScroll = true;
            }

            // 如果只是更新展开/收起状态且有缓存数据，直接渲染
            if (skipRequest && currentCommentsData) {
                renderComments(currentCommentsData.comments);
                return;
            }

            $.ajax({
                url: siteUrl + 'index.php/comment?method=getList',
                type: 'GET',
                data: { article_id: articleId, page: page, sort: sort },
                dataType: 'json',
                success: function(res) {
                    if (res.code === 200) {
                        // 保存当前数据
                        currentCommentsData = res.data;

                        // 从后端响应中获取真实的 pageSize（如果后端返回了）
                        if (res.data.pageSize && res.data.pageSize > 0) {
                            pageSize = res.data.pageSize;
                        }

                        renderComments(res.data.comments);
                        renderPagination(res.data.totalMainComments, page);
                        $('.comment-count').text(res.data.totalCount || 0);
                        if (res.data.totalCount > 0) {
                            $('.comments-sort').show();
                        } else {
                            $('.comments-sort').hide();
                        }

                        // 仅在需要滚动且非初始加载时平滑滚动到排序区域（最新/最热）
                        if (autoScroll && !isInitialLoad) {
                            var sortSection = document.querySelector('.comments-sort');
                            if (sortSection) {
                                var targetTop = sortSection.getBoundingClientRect().top + window.pageYOffset - 10;
                                window.scrollTo({
                                    top: targetTop,
                                    behavior: 'smooth'
                                });
                            }
                        }

                        isInitialLoad = false;
                    }
                }
            });
        }
        
        function renderPagination(totalMainComments, currentPage) {
            var totalPages = Math.ceil(totalMainComments / pageSize);
            var container = $('.comments-pagination');
            
            if (totalMainComments === 0 || totalPages <= 1) {
                container.hide();
                return;
            }
            container.show();
            
            var totalPages = Math.ceil(totalMainComments / pageSize);
            var html = '<div class="pagination-info">共 ' + totalMainComments + ' 条评论，第 ' + currentPage + ' / ' + totalPages + ' 页</div>';
            html += '<div class="pagination-links">';
            
            if (currentPage > 1) {
                html += '<a href="#" data-page="' + (currentPage - 1) + '">上一页</a>';
            } else {
                html += '<span class="disabled">上一页</span>';
            }
            
            var startPage = Math.max(1, currentPage - 2);
            var endPage = Math.min(totalPages, currentPage + 2);
            
            if (startPage > 1) {
                html += '<a href="#" data-page="1">1</a>';
                if (startPage > 2) html += '<span class="ellipsis">...</span>';
            }
            
            for (var i = startPage; i <= endPage; i++) {
                if (i === currentPage) {
                    html += '<span class="current">' + i + '</span>';
                } else {
                    html += '<a href="#" data-page="' + i + '">' + i + '</a>';
                }
            }
            
            if (endPage < totalPages) {
                if (endPage < totalPages - 1) html += '<span class="ellipsis">...</span>';
                html += '<a href="#" data-page="' + totalPages + '">' + totalPages + '</a>';
            }
            
            if (currentPage < totalPages) {
                html += '<a href="#" data-page="' + (currentPage + 1) + '">下一页</a>';
            } else {
                html += '<span class="disabled">下一页</span>';
            }
            
            html += '</div>';
            container.html(html);
            container.addClass('pagination-wrapper');
            
            $('.comments-pagination a').click(function(e) {
                e.preventDefault();
                var p = $(this).data('page');
                if (p) loadComments(p);
            });
        }
        
        function renderComments(comments) {
            var container = $('.comments-list');
            
            if (!comments || comments.length === 0) {
                container.html('<div class="empty-state"><svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="8" y1="12" x2="16" y2="12"></line></svg><p>暂无评论，快来发表你的看法吧！</p></div>');
                return;
            }
            
            var html = '';
            comments.forEach(function(comment) {
                var isLiked = comment.user_liked ? ' liked' : '';
                var isAuthor = comment.is_author ? ' <span class="author-badge">作者</span>' : '';
                var isPending = comment.status === 0 ? ' <span class="pending-badge">待审核</span>' : '';
                var isToppedBadge = comment.is_top ? ' <span class="topped-badge">置顶</span>' : '';
                
                html += '<div class="comment-item topped' + (comment.is_top ? ' is-topped' : '') + '" data-comment-id="' + comment.id + '">';
                html += '<div class="comment-author">';
                html += '<img src="' + (comment.avatar || siteUrl + 'themes/default/assets/images/avatar.png') + '" alt="' + (comment.nickname || comment.username) + '" class="avatar">';
                html += '<div class="author-info">';
                html += '<a href="' + siteUrl + 'index.php/user/' + comment.user_id + '" class="name">' + (comment.nickname || comment.username) + '</a>' + isAuthor + isPending + isToppedBadge;
                html += '</div></div>';
                html += '<div class="comment-content' + (comment.is_top ? ' topped-content' : '') + '">' + comment.content + '</div>';
                var isToppedClass = comment.is_top ? ' topped' : '';
                var topText = comment.is_top ? '取消置顶' : '置顶评论';
                html += '<div class="comment-actions">';
                html += '<span class="time">' + formatRelativeTime(comment.created_at) + '</span>';
                // 仅在评论功能启用时显示回复按钮和内联回复框（主评论和回复统一控制）
                if (commentEnabled) {
                    var replyCount = comment.replies ? comment.replies.length : 0;
                    html += '<button class="reply-btn" data-comment-id="' + comment.id + '" data-author="' + (comment.nickname || comment.username) + '" data-reply-count="' + replyCount + '">' + renderReplyButtonContent(replyCount, false) + '</button>';
                }
                html += '<button class="like-btn' + isLiked + '" data-comment-id="' + comment.id + '">' + renderLikeButtonContent(comment.user_liked, comment.like_count) + '</button>';
                html += '<div class="comment-options">';
                if (isArticleAuthor) html += '<a href="javascript:void(0);" class="comment-option top-comment' + isToppedClass + '" data-comment-id="' + comment.id + '">' + topText + '</a>';
                if (isArticleAuthor || (currentUserId && currentUserId === comment.user_id)) html += '<a href="javascript:void(0);" class="comment-option delete-comment" data-comment-id="' + comment.id + '">删除评论</a>';
                html += '</div></div>';
                // 仅在评论功能启用时渲染内联回复框
                if (commentEnabled) {
                    html += renderInlineReplyForm(articleId, comment.id, (comment.nickname || comment.username));
                }
                html += '<div class="replies-list">' + renderReplies(comment.replies, comment.id) + '</div>';
                html += '</div>';
            });

            container.html(html);
            bindReplyEvents();
            bindLikeEvents();
            bindInlineReplyEvents();

            // 初始化动态生成的验证码组件（如果启用了评论验证码）
            if (typeof Captcha !== 'undefined' && captchaEnabled && captchaCommentEnabled) {
                Captcha.initAll(container[0]);
            }
        }
        
        function renderReplies(replies, commentId) {
            if (!replies || replies.length === 0) return '';
            
            var defaultMaxVisible = 3;
            var maxVisibleReplies = expandedReplies[commentId] || defaultMaxVisible;
            var html = '';
            
            function renderReplyRecursive(reply) {
                var isLiked = reply.user_liked ? ' liked' : '';
                var parentAuthor = reply.parent_nickname || reply.parent_username;
                var parentUserId = reply.parent_user_id;
                var parentAuthorLink = parentUserId ? '<a href="' + siteUrl + 'index.php/user/' + parentUserId + '" class="name">' + parentAuthor + '</a>' : parentAuthor;
                var replySuffix = parentAuthor ? ' 回复 ' + parentAuthorLink : '';
                var isAuthor = reply.is_author ? ' <span class="author-badge">作者</span>' : '';
                var isPending = reply.status === 0 ? ' <span class="pending-badge">待审核</span>' : '';
                var isToppedBadge = reply.is_top ? ' <span class="topped-badge">置顶</span>' : '';
                
                html += '<div class="reply-item' + (reply.is_top ? ' is-topped' : '') + '" data-reply-id="' + reply.id + '">';
                html += '<div class="reply-author">';
                html += '<img src="' + (reply.avatar || siteUrl + 'themes/default/assets/images/avatar.png') + '" alt="' + (reply.nickname || reply.username) + '" class="avatar">';
                html += '<div class="author-info">';
                html += '<a href="' + siteUrl + 'index.php/user/' + reply.user_id + '" class="name">' + (reply.nickname || reply.username) + '</a>' + isAuthor + isPending + isToppedBadge + replySuffix;
                html += '</div></div>';
                html += '<div class="reply-content' + (reply.is_top ? ' topped-content' : '') + '">' + reply.content + '</div>';
                html += '<div class="reply-actions">';
                html += '<span class="time">' + formatRelativeTime(reply.created_at) + '</span>';
                // 仅在评论功能启用时显示回复按钮和内联回复框（主评论和回复统一控制）
                if (commentEnabled) {
                    // 回复项本身没有子回复列表，回复数量为 0
                    html += '<button class="reply-btn" data-comment-id="' + reply.parent_id + '" data-reply-to="' + reply.id + '" data-author="' + (reply.nickname || reply.username) + '" data-reply-count="0">' + renderReplyButtonContent(0, false) + '</button>';
                }
                html += '<button class="like-btn' + isLiked + '" data-comment-id="' + reply.id + '">' + renderLikeButtonContent(reply.user_liked, reply.like_count) + '</button>';
                html += '<div class="comment-options">';
                var isToppedClass = reply.is_top ? ' topped' : '';
                var topText = reply.is_top ? '取消置顶' : '置顶评论';
                if (isArticleAuthor) html += '<a href="javascript:void(0);" class="comment-option top-comment' + isToppedClass + '" data-comment-id="' + reply.id + '">' + topText + '</a>';
                if (isArticleAuthor || (currentUserId && currentUserId === reply.user_id)) html += '<a href="javascript:void(0);" class="comment-option delete-comment" data-comment-id="' + reply.id + '">删除评论</a>';
                html += '</div></div>';
                // 仅在评论功能启用时渲染内联回复框
                if (commentEnabled) {
                    html += renderInlineReplyForm(articleId, reply.id, (reply.nickname || reply.username));
                }
                html += '</div>';
            }
            
            var visibleReplies = replies.slice(0, maxVisibleReplies);
            visibleReplies.forEach(function(reply) { renderReplyRecursive(reply); });
            
            if (replies.length > maxVisibleReplies) {
                var remaining = replies.length - maxVisibleReplies;
                html += '<div class="reply-more" data-replies-count="' + replies.length + '" data-visible-count="' + maxVisibleReplies + '" data-comment-id="' + commentId + '">';
                html += '<button class="reply-more-btn">' + EXPAND_ICON + ' 查看更多 ' + remaining + ' 条回复</button>';
                html += '<button class="reply-collapse-btn" style="display:none;">' + COLLAPSE_ICON + ' 收起</button></div>';
            } else if (maxVisibleReplies > defaultMaxVisible) {
                html += '<div class="reply-more" data-replies-count="' + replies.length + '" data-visible-count="' + maxVisibleReplies + '" data-comment-id="' + commentId + '">';
                html += '<button class="reply-more-btn" style="display:none;">' + EXPAND_ICON + ' 查看更多</button>';
                html += '<button class="reply-collapse-btn">' + COLLAPSE_ICON + ' 收起</button></div>';
            }
            
            return html;
        }
        
        // 排序标签
        $('.sort-tab').click(function() {
            var sort = $(this).data('sort');
            if (sort !== currentSort) {
                $('.sort-tab').removeClass('active');
                $(this).addClass('active');
                loadComments(1, sort, false, false);
            }
        });
        
        // 回复按钮
        function bindReplyEvents() {
            $('.reply-btn').off('click').click(function() {
                if (!currentUserId) { alert('请先登录'); return; }
                var commentId = $(this).data('comment-id');
                var replyTo = $(this).data('reply-to') || 0;
                var author = $(this).data('author');
                var replyCount = $(this).data('reply-count') || 0;
                var $thisBtn = $(this);

                // 检查此按钮当前是否已处于展开状态（显示"取消"）
                var isExpanded = $thisBtn.hasClass('reply-expanded');

                // 先重置其他所有回复按钮的状态（恢复为未展开状态）
                $('.reply-btn').not($thisBtn).each(function() {
                    var $btn = $(this);
                    var btnReplyCount = $btn.data('reply-count') || 0;
                    $btn.removeClass('reply-expanded');
                    $btn.html(renderReplyButtonContent(btnReplyCount, false));
                });

                // 关闭其他所有已打开的内联回复框，确保同一时间只打开一个
                $('.inline-reply-form').not($thisBtn.closest('.comment-item, .reply-item').find('> .inline-reply-form')).hide();

                // 找到当前评论/回复的父容器，处理内联回复框的展开/收起
                var $item = $thisBtn.closest('.comment-item, .reply-item');
                var $inlineForm = $item.find('> .inline-reply-form');

                if ($inlineForm.length > 0) {
                    if (isExpanded) {
                        // 当前已展开，点击后收起
                        $inlineForm.hide();
                        $thisBtn.removeClass('reply-expanded');
                        $thisBtn.html(renderReplyButtonContent(replyCount, false));
                    } else {
                        // 当前未展开，点击后展开
                        $inlineForm.show();
                        $thisBtn.addClass('reply-expanded');
                        $thisBtn.html(renderReplyButtonContent(replyCount, true));
                        $inlineForm.find('textarea').focus().attr('placeholder', '回复 @' + author + '...');

                        // 如果启用了验证码，确保验证码组件已初始化
                        if (captchaEnabled && captchaCommentEnabled && typeof Captcha !== 'undefined') {
                            // 初始化新展开的回复框内的验证码
                            Captcha.initAll($inlineForm[0]);

                            // 刷新验证码（避免过期）
                            var captchas = Captcha.getInstancesIn($inlineForm[0]);
                            captchas.forEach(function(captchaInstance) {
                                captchaInstance.refresh();
                            });
                        }

                        // 平滑滚动到回复框位置
                        var scrollTop = $inlineForm.offset().top - 120;
                        $('html, body').animate({ scrollTop: scrollTop }, 200);
                    }
                }
            });
        }

        // 内联回复框的事件绑定（字符计数、提交、取消、验证码）
        function bindInlineReplyEvents() {
            // 1. 字符计数器 - 实时更新并验证
            $(document).on('input', '.inline-reply-content', function() {
                var $textarea = $(this);
                var $form = $textarea.closest('.inline-reply-form');
                var $counter = $form.find('.inline-current-length');
                var content = typeof $textarea.val() === 'string' ? $textarea.val() : '';
                var length = content.length;
                var maxLength = parseInt($textarea.data('max-length')) || commentMaxLength;
                var minLength = parseInt($textarea.data('min-length')) || commentMinLength;

                $counter.text(length);

                var $counterWrap = $form.find('.inline-char-counter');
                $counterWrap.removeClass('warning error');
                if (length > maxLength) {
                    $counterWrap.addClass('error');
                } else if (length > maxLength * 0.8) {
                    $counterWrap.addClass('warning');
                }

                var $submitBtn = $form.find('.inline-submit-reply');
                $submitBtn.prop('disabled', !(length >= minLength && length <= maxLength));
            });

            // 2. 取消按钮 - 隐藏内联回复框、清空内容并刷新验证码，同时恢复回复按钮状态
            $(document).on('click', '.inline-cancel-reply', function() {
                var $form = $(this).closest('.inline-reply-form');
                $form.find('textarea').val('');
                $form.find('.inline-current-length').text('0');

                // 刷新该回复框内的验证码
                if (captchaEnabled && captchaCommentEnabled && typeof Captcha !== 'undefined') {
                    var captchaInstances = Captcha.getInstancesIn($form[0]);
                    captchaInstances.forEach(function(captchaInstance) {
                        captchaInstance.refresh();
                    });
                }

                // 找到对应的回复按钮，恢复其默认状态
                var $item = $form.closest('.comment-item, .reply-item');
                var $replyBtn = $item.find('> .comment-actions .reply-btn, > .reply-actions .reply-btn');
                if ($replyBtn.length > 0) {
                    var btnReplyCount = $replyBtn.data('reply-count') || 0;
                    $replyBtn.removeClass('reply-expanded');
                    $replyBtn.html(renderReplyButtonContent(btnReplyCount, false));
                }

                $form.hide();
            });

            // 3. 表单提交 - AJAX 提交回复
            $(document).on('submit', '.inline-reply-form-inner', function(e) {
                e.preventDefault();
                if (!currentUserId) { alert('请先登录'); return; }

                var $form = $(this);
                var $submitBtn = $form.find('.inline-submit-reply');
                var formData = $form.serialize();

                // 基本内容长度校验
                var content = $form.find('.inline-reply-content').val() || '';
                if (content.length < commentMinLength || content.length > commentMaxLength) {
                    alert('评论内容长度不合法');
                    return;
                }

                // 验证码校验（如果启用）
                if (captchaEnabled && captchaCommentEnabled && typeof Captcha !== 'undefined') {
                    var captchaInstances = Captcha.getInstancesIn($form[0]);
                    if (captchaInstances.length > 0) {
                        var captchaValue = captchaInstances[0].getValue();
                        if (!captchaValue || captchaValue.length < 4) {
                            alert('请输入正确的验证码');
                            captchaInstances[0].focus();
                            return;
                        }
                    }
                }

                $submitBtn.prop('disabled', true).text('提交中...');

                $.ajax({
                    url: siteUrl + 'index.php/comment?method=submit',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(res) {
                        if (res.code === 200) {
                            // 清空文本框并隐藏回复框，刷新评论列表显示新回复
                            $form.find('textarea').val('');
                            $form.find('.inline-current-length').text('0');

                            // 标记验证码已被使用，防止提交后仍显示验证码错误
                            // （验证码组件的实时验证可能在提交后才返回结果）
                            if (captchaEnabled && captchaCommentEnabled && typeof Captcha !== 'undefined') {
                                var captchasToConsume = Captcha.getInstancesIn($form[0]);
                                captchasToConsume.forEach(function(captchaInstance) {
                                    captchaInstance.markAsConsumed();
                                });
                            }

                            $form.closest('.inline-reply-form').hide();
                            loadComments(1);
                            // 使用后端返回的消息（区分"回复成功"和"回复已提交，等待审核"）
                            alert(res.msg || '回复成功');
                        } else {
                            // 验证码错误时刷新验证码
                            if (captchaEnabled && captchaCommentEnabled && typeof Captcha !== 'undefined') {
                                var captchas = Captcha.getInstancesIn($form[0]);
                                captchas.forEach(function(captchaInstance) {
                                    captchaInstance.refresh();
                                });
                            }
                            alert(res.msg || '回复失败');
                        }
                    },
                    error: function() {
                        alert('提交失败，请稍后重试');
                    },
                    complete: function() {
                        $submitBtn.prop('disabled', false).text('提交回复');
                    }
                });
            });
        }

        // 查看更多回复
        $(document).on('click', '.reply-more-btn', function() {
            var $more = $(this).closest('.reply-more');
            var commentId = $more.data('comment-id');
            var visibleCount = parseInt($more.data('visible-count'));
            var repliesCount = parseInt($more.data('replies-count'));
            
            var step = visibleCount === 3 ? 10 : 10;
            var newVisibleCount = Math.min(visibleCount + step, repliesCount);
            
            // 更新本地状态
            expandedReplies[commentId] = newVisibleCount;
            
            // 重新渲染评论列表，跳过网络请求
            loadComments(currentPage, currentSort, true);
        });
        
        // 收起回复
        $(document).on('click', '.reply-collapse-btn', function() {
            var $more = $(this).closest('.reply-more');
            var commentId = $more.data('comment-id');
            
            // 重置本地状态
            delete expandedReplies[commentId];
            
            // 重新渲染评论列表，跳过网络请求
            loadComments(currentPage, currentSort, true);
        });
        
        // 评论点赞
        function bindLikeEvents() {
            $('.like-btn').off('click').click(function() {
                if (!currentUserId) { alert('请先登录'); return; }
                var btn = $(this);
                var commentId = btn.data('comment-id');
                var isLiked = btn.hasClass('liked');
                
                $.ajax({
                    url: siteUrl + 'index.php/comment?method=like',
                    type: 'POST',
                    data: { comment_id: commentId, action: isLiked ? 'unlike' : 'like' },
                    dataType: 'json',
                    success: function(res) {
                        if (res.code === 200) {
                            var newLikedState = !isLiked; // 翻转点赞状态
                            if (isLiked) {
                                btn.removeClass('liked');
                            } else {
                                btn.addClass('liked');
                            }
                            btn.html(renderLikeButtonContent(newLikedState, res.data.like_count || 0));
                        } else { alert(res.msg); }
                    },
                    error: function() { alert('操作失败，请稍后重试'); }
                });
            });
        }
        
        // ============================================================
        // 评论相关逻辑（字符统计、提交、删除、置顶）
        // 重要：只在页面存在 #comment_content 元素时才初始化相关逻辑，
        //      避免在没有评论功能的文章页面抛出 TypeError
        // ============================================================
        var $commentContent = $('#comment_content');

        if ($commentContent.length > 0) {
            var $submitBtn = $('.submit-comment');
            var $charCounter = $('.char-counter');
            var $currentLength = $charCounter.find('.current-length');
            var minLength = parseInt($commentContent.data('min-length')) || 1;
            var maxLength = parseInt($commentContent.data('max-length')) || 1000;

            // 初始化提交按钮状态
            $submitBtn.prop('disabled', true);

            // 字符统计与按钮状态刷新函数
            function updateCharCounter() {
                // 防御性编程：保证 content 始终是字符串，避免后续 .length 报错
                var content = $commentContent.val();
                if (typeof content !== 'string') {
                    content = '';
                }
                var length = content.length;

                $currentLength.text(length);

                // 更新状态样式
                $charCounter.removeClass('warning error');
                if (length > maxLength) {
                    $charCounter.addClass('error');
                } else if (length > maxLength * 0.8) {
                    $charCounter.addClass('warning');
                }

                // 更新提交按钮状态
                var isValid = length >= minLength && length <= maxLength;
                $submitBtn.prop('disabled', !isValid);
            }

            // 绑定输入事件
            $commentContent.on('input', updateCharCounter);

            // 初始化字符统计
            updateCharCounter();

            // 评论提交
            $('#commentForm').submit(function(e) {
                e.preventDefault();
                if (!currentUserId) { alert('请先登录'); return; }

                var formData = $(this).serialize();
                $.ajax({
                    url: siteUrl + 'index.php/comment?method=submit',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(res) {
                        if (res.code === 200) {
                            $('#comment_content').val('');
                            $('#commentForm input[name="parent_id"]').val('0');
                            $('#comment_content').attr('placeholder', '写下你的评论...');
                            updateCharCounter();
                            loadComments(1);

                            // 标记验证码已被使用并刷新
                            // 防止验证码组件的实时验证在提交后仍返回错误结果
                            if (captchaEnabled && captchaCommentEnabled && typeof Captcha !== 'undefined') {
                                // 获取主评论区的所有验证码实例（在 commentForm 表单内或其容器内）
                                var commentForm = document.getElementById('commentForm');
                                if (commentForm) {
                                    var captchas = Captcha.getInstancesIn(commentForm);
                                    // 若 commentForm 内没有，尝试在整个文档中查找主评论验证码
                                    if (captchas.length === 0) {
                                        captchas = Captcha.getInstancesIn(document.body);
                                        // 过滤出主评论区的验证码（非内联回复框的）
                                        captchas = captchas.filter(function(c) {
                                            return c.container && !c.container.classList.contains('inline-reply-form');
                                        });
                                    }
                                    captchas.forEach(function(captchaInstance) {
                                        captchaInstance.markAsConsumed();
                                        captchaInstance.refresh();
                                    });
                                }
                            }

                            // 使用后端返回的消息（区分"提交成功"和"等待审核"）
                            alert(res.msg || '评论成功');
                        } else { alert(res.msg); }
                    },
                    error: function() { alert('提交失败，请稍后重试'); }
                });
            });

            // 删除评论（通过事件委托绑定到 document，即使评论列表被刷新也能正常工作
            $(document).on('click', '.delete-comment', function() {
                var commentItem = $(this).closest('.comment-item, .reply-item');
                var commentId = commentItem.data('comment-id') || commentItem.data('reply-id');
                if (!confirm('确认删除该评论？')) return;

                $.ajax({
                    url: siteUrl + 'index.php/comment?method=delete',
                    type: 'POST',
                    data: { comment_id: commentId },
                    dataType: 'json',
                    success: function(res) {
                        if (res.code === 200) {
                            loadComments(currentPage);
                            alert('删除成功');
                        } else {
                            alert('删除失败：' + (res.msg || '请稍后重试'));
                        }
                    },
                    error: function(xhr) {
                        var errorMsg = '删除失败，请稍后重试';
                        if (xhr.status === 403) {
                            errorMsg = '您没有权限删除该评论';
                        } else if (xhr.status === 404) {
                            errorMsg = '评论不存在';
                        }
                        alert(errorMsg);
                    }
                });
            });

            // 置顶评论
            $(document).on('click', '.top-comment', function() {
                var commentItem = $(this).closest('.comment-item, .reply-item');
                var commentId = commentItem.data('comment-id') || commentItem.data('reply-id');
                var isCurrentlyTopped = $(this).hasClass('topped');
                var message = isCurrentlyTopped ? '确定要取消置顶该评论吗？' : '确定要置顶该评论吗？';

                if (!confirm(message)) return;

                $.ajax({
                    url: siteUrl + 'index.php/comment?method=top',
                    type: 'POST',
                    data: { comment_id: commentId },
                    dataType: 'json',
                    success: function(res) {
                        if (res.code === 200) {
                            loadComments(currentPage);
                        } else { alert(res.msg); }
                    },
                    error: function() { alert('操作失败，请稍后重试'); }
                });
            });
        }
        
        // ============ 垂直互动按钮 ============
        // 点赞按钮
        $('.vertical-actions .article-like-btn').click(function() {
            var btn = $(this);
            var aId = btn.data('article-id') || articleId;
            var isLiked = btn.hasClass('liked');
            var action = isLiked ? 'remove' : 'add';
            
            if (!currentUserId) { alert('请先登录'); return; }
            
            $.ajax({
                url: siteUrl + 'index.php?c=Home&m=like',
                type: 'POST',
                data: { article_id: aId, action: action },
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        if (action === 'add') {
                            btn.addClass('liked');
                        } else {
                            btn.removeClass('liked');
                        }
                        var badge = btn.find('.action-badge');
                        if (badge.length) badge.text(res.like_count);
                        // 同步水平按钮
                        var hBtn = $('.post-actions .article-like-btn');
                        if (hBtn.length) {
                            if (action === 'add') {
                                hBtn.addClass('liked').html('已点赞 ' + res.like_count);
                            } else {
                                hBtn.removeClass('liked').html('点赞 ' + res.like_count);
                            }
                        }
                    } else { alert(res.msg); }
                },
                error: function() { alert('操作失败，请稍后重试'); }
            });
        });
        
        // 收藏按钮
        $('.vertical-actions .favorite-btn').click(function() {
            var btn = $(this);
            var aId = btn.data('article-id') || articleId;
            var isFavorited = btn.hasClass('favorited');
            var action = isFavorited ? 'remove' : 'add';
            
            if (!currentUserId) { alert('请先登录'); return; }
            
            $.ajax({
                url: siteUrl + 'index.php?c=Home&m=favorite',
                type: 'POST',
                data: { article_id: aId, action: action },
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        if (action === 'add') {
                            btn.addClass('favorited');
                        } else {
                            btn.removeClass('favorited');
                        }
                        var badge = btn.find('.action-badge');
                        if (badge.length) badge.text(res.favorite_count);
                        // 同步水平按钮
                        var hBtn = $('.post-actions .favorite-btn');
                        if (hBtn.length) {
                            if (action === 'add') {
                                hBtn.addClass('favorited').html('已收藏 ' + res.favorite_count);
                            } else {
                                hBtn.removeClass('favorited').html('收藏 ' + res.favorite_count);
                            }
                        }
                    } else { alert(res.msg); }
                },
                error: function() { alert('操作失败，请稍后重试'); }
            });
        });
        
        // 评论按钮（滚动到评论区）
        $('.comment-btn').click(function() {
            var commentsSection = document.querySelector('.comments-section');
            if (commentsSection) {
                commentsSection.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
        
        // ============ 分享功能 ============
        // 移动端互动操作区的分享按钮 → 引导用户使用系统自带的分享功能
        $('.post-actions .share-btn').click(function(e) {
            e.stopPropagation();
            alert('请使用设备自带的分享功能进行分享');
        });

        // PC 端垂直互动区的分享按钮 → 弹出分享面板
        $('.vertical-actions .share-btn').click(function(e) {
            e.stopPropagation();
            $(this).closest('.share-container').toggleClass('active');
        });

        // 关闭分享面板（点击×关闭）
        $('.share-panel-close').click(function() {
            $(this).closest('.share-container').removeClass('active');
        });

        // 点击文档空白处关闭所有分享面板
        $(document).click(function() {
            $('.share-container').removeClass('active');
        });

        // 点击分享面板内部不关闭
        $('.share-panel').click(function(e) {
            e.stopPropagation();
        });

        // 微信分享（垂直互动区的微信二维码弹窗）
        $('.share-option.wechat').click(function() {
            if (typeof QRCode !== 'undefined') {
                var qrContainer = document.getElementById('wechat-qrcode');
                if (qrContainer) {
                    qrContainer.innerHTML = '';
                    QRCode.toCanvas(qrContainer, window.location.href, {
                        width: 180,
                        margin: 2
                    }, function(error) {
                        if (error) {
                            console.error('QRCode generation error:', error);
                        }
                    });
                }
            }
            $(this).closest('.share-container').find('.wechat-share-modal').addClass('active');
        });
        
        $('.wechat-share-close').click(function() {
            $(this).closest('.wechat-share-modal').removeClass('active');
        });
        
        $('.wechat-share-modal').click(function(e) {
            if (e.target === this) $(this).removeClass('active');
        });
        
        // 微博分享
        $('.share-option.weibo').click(function() {
            var url = 'https://service.weibo.com/share/share.php?url=' + encodeURIComponent(window.location.href) + 
                      '&title=' + encodeURIComponent(document.title);
            window.open(url, '_blank', 'width=600,height=500');
        });
        
        // QQ分享
        $('.share-option.qq').click(function() {
            var url = 'https://connect.qq.com/widget/shareqq/index.html?url=' + encodeURIComponent(window.location.href) + 
                      '&title=' + encodeURIComponent(document.title);
            window.open(url, '_blank', 'width=600,height=500');
        });
        
        // 复制链接
        $('.share-option.copy-link').click(function() {
            var textarea = document.createElement('textarea');
            textarea.value = window.location.href;
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy');
                alert('链接已复制到剪贴板');
            } catch (e) {
                alert('复制失败，请手动复制链接：' + window.location.href);
            }
            document.body.removeChild(textarea);
        });
        
        // ============ 垂直互动区域滚动隐藏 ============
        (function() {
            var $verticalActions = $('.vertical-actions');
            var $commentsSection = $('.comments-section');

            if ($verticalActions.length === 0 || $commentsSection.length === 0) {
                return;
            }

            var commentsOffsetTop = $commentsSection.offset().top;

            function handleScroll() {
                var scrollTop = $(window).scrollTop();
                var windowHeight = $(window).height();

                // 精确判断：视口底部到达评论区域顶部时才隐藏
                if (scrollTop + windowHeight >= commentsOffsetTop) {
                    $verticalActions.addClass('hidden');
                } else {
                    $verticalActions.removeClass('hidden');
                }
            }

            // 初始化时检查一次
            handleScroll();

            // 绑定滚动事件
            $(window).on('scroll', handleScroll);
        })();
        
        // ============ 初始加载评论 ============
        loadComments(1, 'latest');
    });

})();
