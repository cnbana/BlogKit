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

// 包含分页类

class ProfileController {

    /**
     * SEO模板变量替换
     * @param string $template 模板字符串
     * @param array $data 额外数据
     * @return string
     */
    private function parseSeoTemplate($template, $data = []) {
        $replacements = [
            '{site.name}' => Config::get('site_name', 'My Blog'),
            '{site.description}' => Config::get('site_description', ''),
            '{site.keywords}' => Config::get('site_keywords', ''),
            '{site.url}' => Config::get('site.url', ''),
        ];

        if (isset($data['user'])) {
            $replacements['{user.nickname}'] = $data['user']['nickname'] ?? '';
            $replacements['{user.username}'] = $data['user']['username'] ?? '';
        }

        if (isset($data['page_title'])) {
            $replacements['{page.title}'] = $data['page_title'];
        }

        return strtr($template, $replacements);
    }

    /**
     * 解析用户隐私设置（从 JSON 字符串转为数组），所有字段默认均为 public
     * @param string|null $privacySettingsRaw 数据库中的 privacy_settings JSON 字符串
     * @return array 形如 ['show_following' => 'public', 'show_followers' => 'public', ...]
     */
    private function parsePrivacySettings($privacySettingsRaw) {
        $defaults = [
            'show_following' => 'public',
            'show_followers' => 'public',
            'show_likes'     => 'public',
            'show_favorites' => 'public',
        ];

        $parsed = [];
        if (!empty($privacySettingsRaw) && is_string($privacySettingsRaw)) {
            // JSON 解析失败时回退为空数组，随后用 defaults 覆盖
            $decoded = json_decode($privacySettingsRaw, true);
            if (is_array($decoded)) {
                // 只保留已知的四个 key，防止数据库里混入其他字段
                foreach (array_keys($defaults) as $key) {
                    if (array_key_exists($key, $decoded)) {
                        $parsed[$key] = $decoded[$key];
                    }
                }
            }
        }

        // 校验每个条目的值，非法值强制重置为 public
        $allowedValues = ['public', 'followers', 'following', 'mutual_follow', 'private'];
        foreach (array_keys($defaults) as $key) {
            if (!isset($parsed[$key]) || !in_array($parsed[$key], $allowedValues, true)) {
                $parsed[$key] = $defaults[$key];
            }
        }

        return $parsed;
    }

    /**
     * 判断当前访客是否可以查看被访问用户的指定 Tab 内容
     *
     * @param string $tabKey   对应 privacy_settings 的 key（show_following/show_followers/show_likes/show_favorites）
     * @param array  $privacySettings  parsePrivacySettings 返回的数组
     * @param int    $profileUserId    被查看用户的 ID
     * @param bool   $isSelf           当前访客是否就是被查看用户
     * @param int    $visitorId        当前访客的 user_id（未登录为 0）
     * @param FollowModel $followModel  关注模型，用于判断关注关系
     * @return bool true 表示可以看到内容；false 表示应显示"因隐私设置不可见"
     */
    private function isPrivacyTabAccessible($tabKey, $privacySettings, $profileUserId, $isSelf, $visitorId, $followModel) {
        // 0. 参数有效性检查：未指定或非法 key，默认可见
        if (empty($tabKey) || !is_array($privacySettings)) {
            return true;
        }

        // 1. 用户自己看自己，永远可见
        if ($isSelf) {
            return true;
        }

        // 2. 按该 Tab 的隐私级别决定（非法值一律视为 public）
        $allowedValues = ['public', 'followers', 'following', 'mutual_follow', 'private'];
        $level = $privacySettings[$tabKey] ?? 'public';
        if (!in_array($level, $allowedValues, true)) {
            $level = 'public';
        }

        switch ($level) {
            case 'public':
                return true;

            case 'private':
                return false;

            case 'followers':
                // 仅允许"关注了我（即：被访问用户的粉丝）"查看
                if ($visitorId <= 0) return false;
                return (bool)$followModel->isFollowing($visitorId, $profileUserId);

            case 'following':
                // 仅允许"我关注的人（即：被访问用户关注的人）"查看
                if ($visitorId <= 0) return false;
                return (bool)$followModel->isFollowing($profileUserId, $visitorId);

            case 'mutual_follow':
                // 仅允许"相互关注（双向关注）"的用户查看
                if ($visitorId <= 0) return false;
                return (bool)$followModel->isFollowing($visitorId, $profileUserId)
                    && (bool)$followModel->isFollowing($profileUserId, $visitorId);

            default:
                return true;
        }
    }

    /**
     * 返回某个 Tab 对应的中文名，用于在提示文字中显示
     */
    private function getPrivacyTabLabel($currentTab) {
        $labels = [
            'following' => '关注',
            'followers' => '粉丝',
            'likes'     => '点赞',
            'favorites' => '收藏',
        ];
        return $labels[$currentTab] ?? '';
    }

    /**
     * 返回JSON响应
     * @param array $data 要返回的数据
     */
    private function jsonResponse($data) {
        // 禁用调试面板输出，避免产生额外输出
        if (class_exists('Debug')) {
            Debug::disablePanel();
        }

        // 确保没有任何额外输出，只返回纯JSON
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

        $json = @json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $json = json_encode(['success' => false, 'message' => 'JSON编码失败'], JSON_UNESCAPED_UNICODE);
        }

        echo $json;
        exit;
    }

    /**
     * 个人中心
     */
    public function profile() {
        // 检查用户是否已登录
        if (!isset($_SESSION['user'])) {
            header('Location: /index.php?c=Auth&m=login');
            exit;
        }

        // 处理头像上传请求
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
            if ($_POST['action'] == 'update_avatar') {
                $this->updateAvatar();
                return;
            } elseif ($_POST['action'] == 'delete' && isset($_POST['id'])) {
                // 删除单条阅读记录
                $readHistoryModel = new ReadHistoryModel();
                $readHistoryModel->deleteReadHistory($_SESSION['user']['id'], intval($_POST['id']));
                // 重定向回阅读历史页面
                header('Location: /index.php/profile?m=history');
                exit;
            } elseif ($_POST['action'] == 'clear') {
                // 清空所有阅读记录
                $readHistoryModel = new ReadHistoryModel();
                $readHistoryModel->clearReadHistory($_SESSION['user']['id']);
                // 重定向回阅读历史页面
                header('Location: /index.php/profile?m=history');
                exit;
            }
        }

        // 获取当前请求的子方法
        $method = isset($_GET['m']) ? $_GET['m'] : 'info';

        // 初始化模板引擎
        $template = new Template();

        // 获取显示在菜单中的分类
        $categoryModel = new CategoryModel();
        $categories = $categoryModel->getMenuCategories();

        // 获取菜单页面
        $pageModel = new PageModel();
        $pages = $pageModel->getMenuPages();

        $userModel = new UserModel();
        $user = $_SESSION['user'];

        // 格式化时间
        $user['created_at_formatted'] = date('Y-m-d H:i:s', $user['created_at']);

        // 关注功能相关
        $is_following = false;
        $following_count = 0;
        $followers_count = 0;

        // 初始化FollowModel
        $followModel = new FollowModel();

        // 获取当前用户ID
        $userId = $_SESSION['user']['id'];

        // 获取关注和粉丝数量
        $following_count = $followModel->getFollowingCount($userId);
        $followers_count = $followModel->getFollowersCount($userId);

        // 检查当前用户是否已登录
        if (isset($_SESSION['user'])) {
            // 检查是否已关注
            $is_following = $followModel->isFollowing($_SESSION['user']['id'], $userId);
        }

        // 传递用户数据到模板
        $template->assign('profile_user', $user);
        $template->assign('is_following', $is_following);
        $template->assign('is_not_self', (isset($_SESSION['user']['id']) && $_SESSION['user']['id'] !== $userId));
        $template->assign('following_count', $following_count);
        $template->assign('followers_count', $followers_count);

        // 传递会话用户数据到模板，用于条件判断
        if (isset($_SESSION['user'])) {
            $template->assign('user', $_SESSION['user']);
        }

        // 传递分类数据到模板
        $template->assign('categories', $categories);

        // 传递页面菜单数据到模板
        $template->assign('pages', $pages);

        // 获取消息配置
        $message_enabled = Config::get('message_enabled', '1');
        $message_duration = Config::get('message_duration', '3');
        $template->assign('message_enabled', $message_enabled);
        $template->assign('message_duration', $message_duration);

        // 获取未读评论数量
        $commentModel = new CommentModel();
        $unread_comments = $commentModel->getUserUnreadCommentMessagesCount($_SESSION['user']['id']);
        $template->assign('total_comments', $unread_comments);

        // 获取未读通知数量
        $notificationModel = new NotificationModel();
        $unread_notifications = $notificationModel->getUnreadCount($_SESSION['user']['id']);
        $template->assign('total_notifications', $unread_notifications);

        // 将方法名作为tab变量传递给模板
        $template->assign('tab', $method);

        // 兼容tab参数（从get参数获取，用于模板中的条件判断）
        $template->assign('get', $_GET);
        $template->assign('get.tab', $method);

        // 定义各个子页面的标题
        $pageTitles = [
            'info' => '个人资料',
            'articles' => '我的文章',
            'favorites' => '我的收藏',
            'likes' => '我的点赞',
            'comments' => '我的评论',
            'history' => '阅读历史',
            'settings' => '设置',
            'security' => '安全设置',
            'privacy' => '隐私设置',
            'notifications' => '通知设置',
            'messages' => '消息中心',
            'following' => '我的关注',
            'followers' => '我的粉丝',
        ];

        $pageTitle = isset($pageTitles[$method]) ? $pageTitles[$method] : '个人中心';
        // 设置SEO
        $seoData = ['user' => $user, 'page_title' => $pageTitle];
        $template->assign('seo_title', $this->parseSeoTemplate(Config::get('user_seo_title', '{user.nickname} - {site.name}'), $seoData));
        $template->assign('seo_description', $this->parseSeoTemplate(Config::get('user_seo_description', '{user.nickname}的个人主页'), $seoData));
        $template->assign('seo_keywords', $this->parseSeoTemplate(Config::get('user_seo_keywords', '{user.nickname},{site.keywords}'), $seoData));

        // 根据不同的方法渲染不同的模板
        switch ($method) {
            case 'comments':
                // 获取当前用户的评论列表
                $commentModel = new CommentModel();
                $userId = $_SESSION['user']['id'];
                // 获取评论页面的分页数量配置
                $page_size = Config::get('pagination_comments_count', Config::get('pagination_count', 10));
                // 从 GET 参数读取当前页码
                $current_page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
                // 获取用户评论总数
                $total_comments = $commentModel->getUserCommentsCount($userId);
                // 创建分页对象
                $pagination = new Pagination($total_comments, $page_size);
                // 按当前页获取评论列表
                $comments = $commentModel->getComments($current_page, $page_size, ['user_id' => $userId]);
                // 获取文章标题和其他必要信息
                $articleModel = new ArticleModel();
                // 为每个评论添加文章信息
                foreach ($comments as &$comment) {
                    $article = $articleModel->getArticleById($comment['article_id']);
                    $comment['article_title'] = $article['title'] ?? '未知文章';
                    // 生成正确格式的文章链接：/index.php/article/ID
                    $comment['article_url'] = Config::get('site.url') . '/index.php/article/' . $comment['article_id'];
                }
                $template->assign('comments', $comments);
                $template->assign('pagination', $pagination->createLinks());
                $template->assign('pagination_info', $pagination->getPaginationInfo());
                $template->display('profile_comments');
                break;
            case 'settings':
                if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                    $action = isset($_POST['action']) ? $_POST['action'] : '';

                    if ($action === 'update_profile') {
                        $nickname = isset($_POST['nickname']) ? trim($_POST['nickname']) : '';
                        $bio = isset($_POST['bio']) ? trim($_POST['bio']) : '';
                        $website = isset($_POST['website']) ? trim($_POST['website']) : '';

                        // —— 基本信息校验 ——
                        // 使用 mb_strlen 按 UTF-8 字符数校验（中文每个字算1个字符），
                        // 否则中文每字占 3 字节会导致超长误判（例如"测试用户"=12字节被拒绝）
                        if (empty($nickname)) {
                            $error = '昵称不能为空';
                        } elseif (mb_strlen($nickname, 'UTF-8') < 1 || mb_strlen($nickname, 'UTF-8') > 10) {
                            $error = '昵称长度必须在 1-10 个字符之间';
                        } elseif (mb_strlen($bio, 'UTF-8') > 200) {
                            $error = '个人简介不能超过 200 个字符';
                        } elseif (!empty($website) && !self::isValidUrl($website)) {
                            $error = '个人网站格式不正确，必须以 http:// 或 https:// 开头';
                        } else {
                            $updateData = [];
                            $updateData['nickname'] = mb_substr($nickname, 0, 10, 'UTF-8');
                            // bio 允许为空（用户主动清空简介的合法场景），超长时截断到 200 字符
                            $updateData['bio'] = mb_substr($bio, 0, 200, 'UTF-8');
                            if (!empty($website)) {
                                $updateData['website'] = $website;
                            }

                            // —— 可选修改密码：新密码非空时必须校验当前密码 ——
                            $oldPassword = isset($_POST['old_password']) ? $_POST['old_password'] : '';
                            $newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';
                            $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

                            if ($newPassword !== '' || $confirmPassword !== '' || $oldPassword !== '') {
                                // 用户尝试修改密码：三项都不能空
                                if ($oldPassword === '') {
                                    $error = '请输入当前密码';
                                } elseif ($newPassword === '') {
                                    $error = '请输入新密码';
                                } elseif ($confirmPassword === '') {
                                    $error = '请再次输入新密码';
                                } elseif (strlen($newPassword) < 6) {
                                    $error = '新密码长度不能少于 6 位';
                                } elseif ($newPassword !== $confirmPassword) {
                                    $error = '两次输入的新密码不一致';
                                } else {
                                    $userModel = new UserModel();
                                    $user = $userModel->getUserById($_SESSION['user']['id']);
                                    if (!$user || !password_verify($oldPassword, $user['password'])) {
                                        $error = '当前密码错误，请重新输入';
                                    } else {
                                        $updateData['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
                                    }
                                }
                            }

                            if (!isset($error)) {
                                $result = UserModel::updateUser($_SESSION['user']['id'], $updateData);

                                if ($result) {
                                    $success = '个人信息更新成功';

                                    $userModel = new UserModel();
                                    $user = $userModel->getUserById($_SESSION['user']['id']);
                                    $_SESSION['user'] = $user;

                                    $template->assign('success', $success);
                                } else {
                                    $error = '个人信息更新失败，请稍后重试';
                                    $template->assign('error', $error);
                                }
                            }
                        }
                    } elseif ($action === 'send_email_verification') {
                        $newEmail = isset($_POST['email']) ? trim($_POST['email']) : '';

                        if (empty($newEmail)) {
                            $error = '请输入新邮箱地址';
                        } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                            $error = '请输入有效的邮箱地址';
                        } else {
                            $userModel = new UserModel();
                            $result = $userModel->sendEmailVerificationCode($_SESSION['user']['id'], $newEmail);

                            if ($result) {
                                $success = '验证码已发送至您的邮箱，请在30分钟内完成验证';
                                $template->assign('success', $success);
                                $template->assign('email_verification_sent', true);
                            } else {
                                $error = '验证码发送失败，该邮箱可能已被使用或邮箱服务不可用';
                                $template->assign('error', $error);
                            }
                        }
                    } elseif ($action === 'verify_email') {
                        $verificationCode = isset($_POST['verification_code']) ? trim($_POST['verification_code']) : '';

                        if (empty($verificationCode)) {
                            $error = '请输入验证码';
                        } else {
                            $userModel = new UserModel();
                            $result = $userModel->verifyEmailCode($_SESSION['user']['id'], $verificationCode);

                            if ($result) {
                                $success = '邮箱修改成功';

                                $user = $userModel->getUserById($_SESSION['user']['id']);
                                $_SESSION['user'] = $user;

                                $template->assign('success', $success);
                            } else {
                                $error = '验证码错误或已过期，请重新获取验证码';
                                $template->assign('error', $error);
                            }
                        }
                    } elseif ($action === 'update_password') {
                        $oldPassword = isset($_POST['old_password']) ? $_POST['old_password'] : '';
                        $newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';
                        $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

                        if (empty($newPassword)) {
                            $success = '密码未修改';
                            $template->assign('success', $success);
                        } elseif (empty($oldPassword)) {
                            $error = '请输入当前密码';
                        } elseif (strlen($newPassword) < 6) {
                            $error = '新密码长度不能少于6位';
                        } elseif ($newPassword != $confirmPassword) {
                            $error = '两次输入的密码不一致';
                        } else {
                            $userModel = new UserModel();
                            $user = $userModel->getUserById($_SESSION['user']['id']);

                            if (!password_verify($oldPassword, $user['password'])) {
                                $error = '当前密码错误';
                                $template->assign('error', $error);
                            } else {
                                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                                $result = UserModel::updateUser($_SESSION['user']['id'], ['password' => $hashedPassword]);

                                if ($result) {
                                    $success = '密码修改成功，请使用新密码登录';
                                    $template->assign('success', $success);
                                } else {
                                    $error = '密码修改失败，请稍后重试';
                                    $template->assign('error', $error);
                                }
                            }
                        }
                    } elseif ($action === 'logout') {
                        unset($_SESSION['user']);
                        $_SESSION['success_message'] = '退出登录成功！';
                        header('Location: /index.php');
                        exit;
                    }
                }

                // 用户注销功能——已提取为插件 (plugins/accountdeletion)
                // 插件通过钩子 'profile_settings_deletion' 注入注销相关变量和处理逻辑
                $userModel = new UserModel();
                $user = $userModel->getUserById($_SESSION['user']['id']);
                Hook::trigger('profile_settings_deletion', [
                    'user' => $user,
                    'template' => $template,
                    'action' => $action
                ]);

                $template->assign('categories', $categories);
                $template->assign('pages', $pages);
                $template->assign('captcha_enabled', $captchaEnabled);
                $template->assign('captcha_login_enabled', $captchaLoginEnabled);
                $template->assign('message_duration', $message_duration);

                $template->display('profile_settings');
                break;
            case 'articles':
                // 获取当前用户的文章列表
                $articleModel = new ArticleModel();
                $userId = $_SESSION['user']['id'];
                // 获取文章页面的分页数量配置
                $page_size = Config::get('pagination_articles_count', Config::get('pagination_count', 10));
                // 从 GET 参数读取当前页码
                $current_page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
                // 获取用户文章总数
                $total_articles = $articleModel->getArticleCountByUserId($userId);
                // 创建分页对象
                $pagination = new Pagination($total_articles, $page_size);
                // 按当前页获取用户已发布的文章
                $articles = $articleModel->getArticles($current_page, $page_size, false, ['user_id' => $userId]);
                // 批量填充标签和统计数据（消除 N+1 查询）
                $articles = HomeController::batchEnrichArticles($articles);

                $template->assign('articles', $articles);
                $template->assign('pagination', $pagination->createLinks());
                $template->assign('pagination_info', $pagination->getPaginationInfo());
                $template->display('profile_articles');
                break;
            case 'drafts':
                $template->display('profile_drafts');
                break;
            case 'stats':
                $template->display('profile_stats');
                break;
            case 'favorites':
                // 获取当前用户的收藏列表
                $favoriteModel = new FavoriteModel();
                $userId = $_SESSION['user']['id'];
                // 获取收藏页面的分页数量配置
                $page_size = Config::get('pagination_favorites_count', Config::get('pagination_count', 10));
                // 从 GET 参数读取当前页码
                $current_page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
                // 获取收藏总数
                $favoriteCount = $favoriteModel->getUserFavoritesCount($userId);
                // 创建分页对象
                $pagination = new Pagination($favoriteCount, $page_size);
                // 按当前页获取用户收藏的文章
                $favorites = $favoriteModel->getUserFavorites($userId, $current_page, $page_size);

                $template->assign('favorites', $favorites);
                $template->assign('favorite_count', $favoriteCount);
                $template->assign('pagination', $pagination->createLinks());
                $template->assign('pagination_info', $pagination->getPaginationInfo());
                $template->display('profile_favorites');
                break;
            case 'likes':
                // 获取当前用户的点赞数据
                $likeModel = new LikeModel();
                $userId = $_SESSION['user']['id'];

                // 获取点赞页面的分页数量配置
                $page_size = Config::get('pagination_likes_count', Config::get('pagination_count', 10));
                // 从 GET 参数读取当前页码
                $current_page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
                // 获取点赞总数（文章点赞）
                $likeCount = $likeModel->getUserLikesCount($userId);
                // 创建分页对象
                $pagination = new Pagination($likeCount, $page_size);
                // 按当前页获取用户点赞的文章
                $likedArticles = $likeModel->getUserLikes($userId, $current_page, $page_size);
                // 批量填充文章标签和统计数据（消除 N+1 查询）
                $likedArticles = HomeController::batchEnrichArticles($likedArticles);
                $template->assign('liked_articles', $likedArticles);

                // 获取用户点赞的评论
                $likedComments = $likeModel->getUserCommentLikes($userId, $current_page, $page_size);
                $template->assign('liked_comments', $likedComments);

                // 获取评论点赞总数
                $commentLikeCount = $likeModel->getUserCommentLikesCount($userId);
                $template->assign('like_count', $likeCount);
                $template->assign('comment_like_count', $commentLikeCount);

                $template->assign('pagination', $pagination->createLinks());
                $template->assign('pagination_info', $pagination->getPaginationInfo());
                $template->display('profile_likes');
                break;
            case 'replies':
                $template->display('profile_replies');
                break;
            case 'notifications':
                // 处理通知设置更新请求
                if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
                    if ($_POST['action'] == 'update_notification_settings') {
                        // 获取通知设置数据
                        $notificationTypes = isset($_POST['notification_types']) ? $_POST['notification_types'] : [];

                        // 构建通知设置JSON
                        $notificationSettings = json_encode([
                            'types' => $notificationTypes
                        ]);

                        // 更新用户表中的通知设置
                        $result = UserModel::updateUser($_SESSION['user']['id'], [
                            'notification_settings' => $notificationSettings
                        ]);

                        if ($result) {
                            // 更新会话中的用户信息
                            $_SESSION['user']['notification_settings'] = $notificationSettings;

                            // 设置成功消息
                            $template->assign('success', '通知设置更新成功！');
                        } else {
                            $template->assign('error', '通知设置更新失败，请稍后重试！');
                        }
                    }
                }

                // 获取当前用户的通知设置
                $user = UserModel::getUserById($_SESSION['user']['id']);
                $notificationSettings = json_decode($user['notification_settings'] ?? '{}', true);

                // 将通知设置传递给模板
                $template->assign('notification_settings', $notificationSettings);

                $template->display('profile_notifications');
                break;
            case 'security':
                $template->display('profile_security');
                break;
            case 'privacy':
                // 确保从数据库读取最新的隐私设置（session 可能没有）
                $dbUser = $userModel->getUserById($user['id']);
                $privacyRaw = $dbUser['privacy_settings'] ?? null;

                // ===== 保存隐私设置 =====
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_privacy_settings') {
                    // 读取 POST 数据（允许的键名与有效值）
                    $allowedKeys = ['show_following', 'show_followers', 'show_likes', 'show_favorites'];
                    $allowedValues = ['public', 'followers', 'following', 'mutual_follow', 'private'];

                    $newSettings = [];
                    foreach ($allowedKeys as $key) {
                        $val = isset($_POST[$key]) ? trim($_POST[$key]) : '';
                        $newSettings[$key] = in_array($val, $allowedValues, true) ? $val : 'public';
                    }

                    $json = json_encode($newSettings, JSON_UNESCAPED_UNICODE);

                    // 写入数据库
                    $saveResult = $userModel->updateUser($user['id'], ['privacy_settings' => $json]);

                    // 同步更新会话缓存（自己看自己主页时生效）
                    if ($saveResult) {
                        $_SESSION['user']['privacy_settings'] = $json;
                        $template->assign('success_msg', '隐私设置已更新');
                    } else {
                        $template->assign('error_msg', '隐私设置保存失败，请重试');
                    }

                    // 展平为单个字符串变量供模板渲染
                    foreach ($newSettings as $k => $v) {
                        $template->assign('privacy_' . $k, $v);
                    }
                } else {
                    // 未提交时读取现有设置
                    $existing = $this->parsePrivacySettings($privacyRaw);
                    foreach ($existing as $k => $v) {
                        $template->assign('privacy_' . $k, $v);
                    }
                }

                $template->display('profile_privacy');
                break;
            // 处理消息中心
            case 'messages':
            // 消息中心功能实现
            // 获取用户ID
            $user_id = $_SESSION['user']['id'];

            // 获取消息类型
            $message_type = isset($_GET['type']) ? $_GET['type'] : 'all';

            // 获取搜索条件
            $search = isset($_GET['search']) ? trim($_GET['search']) : '';

            // 获取排序方式
            $sort = isset($_GET['sort']) ? $_GET['sort'] : 'recent';

            // 分页处理
            $current_page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
            // 获取消息中心的分页数量配置
            $page_size = Config::get('pagination_messages_count', Config::get('pagination_count', 10));
            $offset = ($current_page - 1) * $page_size;

            // 初始化数据
            $notifications = [];
            $total_notifications = 0;
            $total_pages = 0;

            // 处理通知相关操作
            if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
                // 检查是否为AJAX请求
                $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

                if ($_POST['action'] == 'mark_notification_read') {
                    // 标记单个通知为已读
                    $notificationId = isset($_POST['notification_id']) ? intval($_POST['notification_id']) : 0;
                    if ($notificationId > 0) {
                        $notificationModel = new NotificationModel();
                        $notificationModel->markAsRead($notificationId, $user_id);
                    }

                    // 如果是AJAX请求，返回JSON响应
                    if ($isAjax) {
                        header('Content-Type: application/json');
                        echo json_encode(['status' => 'success']);
                        exit;
                    }
                } else if ($_POST['action'] == 'mark_all_as_read') {
                    // 标记所有未读通知为已读
                    $notificationModel = new NotificationModel();
                    $notificationModel->markAllAsRead($user_id);

                    // 如果是AJAX请求，返回JSON响应
                    if ($isAjax) {
                        header('Content-Type: application/json');
                        echo json_encode(['status' => 'success']);
                        exit;
                    }
                } else if ($_POST['action'] == 'delete_notification') {
                    // 软删除单条通知
                    $notificationId = isset($_POST['notification_id']) ? intval($_POST['notification_id']) : 0;
                    if ($notificationId > 0) {
                        $notificationModel = new NotificationModel();
                        $notificationModel->softDeleteNotification($notificationId);
                    }

                    // 如果是AJAX请求，返回JSON响应
                    if ($isAjax) {
                        header('Content-Type: application/json');
                        echo json_encode(['status' => 'success']);
                        exit;
                    }

                    // 普通表单提交，重定向回消息中心页面
                    header('Location: /index.php/profile?m=messages');
                    exit;
                } else if ($_POST['action'] == 'clear_all_messages') {
                    // 清空所有通知（软删除进入回收站）
                    $notificationModel = new NotificationModel();
                    $notificationModel->softDeleteAllByUserId($user_id);

                    // 如果是AJAX请求，返回JSON响应
                    if ($isAjax) {
                        header('Content-Type: application/json');
                        echo json_encode(['status' => 'success']);
                        exit;
                    }

                    // 普通表单提交，重定向回消息中心页面
                    header('Location: /index.php/profile?m=messages');
                    exit;
                }
            }

            // 初始化NotificationModel
            $notificationModel = new NotificationModel();

            // 获取通知数据
            $notifications = $notificationModel->getNotifications($user_id, $current_page, $page_size, $message_type, $search, 'all', $sort);

            // 获取通知总数
            $total_notifications = $notificationModel->getNotificationCount($user_id, $message_type, $search);

            // 计算总页数
            $total_pages = ceil($total_notifications / $page_size);

            // 获取各类型未读通知数量
            $total_comments = $notificationModel->getNotificationCount($user_id, 'comments', '', true);
            $total_system_notifications = $notificationModel->getNotificationCount($user_id, 'system', '', true);
            $total_follow_notifications = $notificationModel->getNotificationCount($user_id, 'follows', '', true);
            $total_article_notifications = $notificationModel->getNotificationCount($user_id, 'articles', '', true);
            $total_like_notifications = $notificationModel->getNotificationCount($user_id, 'likes', '', true);
            $total_article_like_notifications = $notificationModel->getNotificationCount($user_id, 'article_likes', '', true);
            $total_comment_like_notifications = $notificationModel->getNotificationCount($user_id, 'comment_likes', '', true);
            $total_favorite_notifications = $notificationModel->getNotificationCount($user_id, 'favorites', '', true);

            // 格式化通知数据
            foreach ($notifications as &$notification) {
                $notification['created_at_formatted'] = date('Y-m-d H:i:s', $notification['created_at']);
                // 将数据库字段 read 映射为模板中使用的 is_read
                $notification['is_read'] = $notification['read'];

                // 预处理：通知项的展示数据
                $itemClasses = [];
                $statusText = '';
                $statusClass = '';

                // 未读状态类
                if ($notification['read'] == 0) {
                    $itemClasses[] = 'unread';
                    $statusText = '未读';
                    $statusClass = 'unread';
                } else {
                    $statusText = '已读';
                    $statusClass = 'read';
                }

                // 通知类型（用于 li class，保留类型信息）
                $type = $notification['type'];
                $itemClasses[] = $type;

                $notification['item_class'] = implode(' ', $itemClasses);
                $notification['status_text'] = $statusText;
                $notification['status_class'] = $statusClass;

                // 预处理：查看原文/查看主页的链接HTML
                $linkHtml = '';
                if ($type === 'comments' && !empty($notification['article_id'])) {
                    $linkHtml = '<a href="/index.php/article?id=' . intval($notification['article_id']) . '" class="message-link">查看原文</a>';
                } elseif ($type === 'follows' && !empty($notification['follower_id'])) {
                    $linkHtml = '<a href="/index.php/user?id=' . intval($notification['follower_id']) . '" class="message-link">查看主页</a>';
                }
                $notification['link_html'] = $linkHtml;

                // 预处理：未读时显示"标记已读"按钮的标志（未读 = !is_read）
                $notification['show_mark_read'] = $notification['read'] == 0 ? 1 : 0;
            }

            // 分配模板变量
            $template->assign('message_type', $message_type);
            $template->assign('notifications', $notifications);
            $template->assign('total_notifications', $notificationModel->getUnreadCount($user_id));
            $template->assign('total_comments', $total_comments);
            $template->assign('total_system_notifications', $total_system_notifications);
            $template->assign('total_follow_notifications', $total_follow_notifications);
            $template->assign('total_article_notifications', $total_article_notifications);
            $template->assign('total_like_notifications', $total_like_notifications);
            $template->assign('total_article_like_notifications', $total_article_like_notifications);
            $template->assign('total_comment_like_notifications', $total_comment_like_notifications);
            $template->assign('total_favorite_notifications', $total_favorite_notifications);
            $template->assign('total_pages', $total_pages);
            $template->assign('current_page', $current_page);
            $template->assign('search', $search);
            $template->assign('sort', $sort);
            // 排序方式下拉框的selected属性
            $template->assign('sort_selected_recent', $sort === 'recent' ? 'selected' : '');
            $template->assign('sort_selected_oldest', $sort === 'oldest' ? 'selected' : '');

            // 初始化分页对象
            $pagination = new Pagination($total_notifications, $page_size);
            $template->assign('pagination', $pagination);

            $template->display('profile_messages');
            break;

            case 'get_unread_count':
            // 处理获取未读通知数量的AJAX请求
            header('Content-Type: application/json');
            $user_id = $_SESSION['user']['id'];
            $notificationModel = new NotificationModel();
            $unreadCount = $notificationModel->getUnreadCount($user_id);
            echo json_encode(['count' => $unreadCount]);
            exit;
            break;
            case 'credits':
                $template->display('profile_credits');
                break;
            case 'follow':
                // 重定向到follow方法
                $this->follow();
                break;
            case 'following':
                // 重定向到following方法
                $this->following();
                break;
            case 'followers':
                // 重定向到followers方法
                $this->followers();
                break;
            case 'history':
                // 获取当前用户的阅读历史列表
                $readHistoryModel = new ReadHistoryModel();
                $userId = $_SESSION['user']['id'];

                // 分页处理
                $current_page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
                // 获取阅读历史的分页数量配置
                $page_size = Config::get('pagination_history_count', Config::get('pagination_count', 10));

                // 获取阅读历史总数
                $total_history = $readHistoryModel->getReadHistoryCount($userId);

                // 初始化分页类
                $pagination = new Pagination($total_history, $page_size);

                // 获取当前页的阅读历史
                $history = $readHistoryModel->getReadHistory($userId, $current_page, $page_size);

                // 获取关注和粉丝数量
                $followModel = new FollowModel();
                $following_count = $followModel->getFollowingCount($userId);
                $followers_count = $followModel->getFollowersCount($userId);

                // 格式化当前用户时间
                $user = $_SESSION['user'];
                $user['created_at_formatted'] = date('Y-m-d H:i:s', $user['created_at']);

                // 将数据传递给模板
                $template->assign('user', $user);
                $template->assign('history', $history);
                $template->assign('pagination', $pagination);
                $template->assign('pagination_info', $pagination->getPaginationInfo());
                $template->assign('following_count', $following_count);
                $template->assign('followers_count', $followers_count);

                $template->display('profile_history');
                break;
            default:
                $template->display('profile_info');
                break;
        }
    }

    /**
     * 更新用户头像
     */
    private function updateAvatar() {
        try {
            // 检查用户是否已登录
            if (!isset($_SESSION['user'])) {
                $this->jsonResponse(['success' => false, 'message' => '用户未登录']);
                return;
            }

            // 后台关闭了「允许用户设置头像」开关时，拒绝上传请求
            // 注意：前台模板在关闭时也不会渲染「更换头像」入口，此处为兜底校验
            $avatarEnabled = Config::get('profile_avatar_enabled', '1');
            if ($avatarEnabled !== '1' && $avatarEnabled !== 1 && $avatarEnabled !== true) {
                $this->jsonResponse(['success' => false, 'message' => '当前站点未开启用户头像上传功能']);
                return;
            }

            // 检查是否有文件上传
            if (!isset($_FILES['avatar'])) {
                $this->jsonResponse(['success' => false, 'message' => '未选择文件']);
                return;
            }

            $file = $_FILES['avatar'];

            // 检查上传错误
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errorMessages = [
                    UPLOAD_ERR_INI_SIZE => '文件大小超过限制',
                    UPLOAD_ERR_FORM_SIZE => '文件大小超过表单限制',
                    UPLOAD_ERR_PARTIAL => '文件只上传了一部分',
                    UPLOAD_ERR_NO_FILE => '没有文件被上传',
                    UPLOAD_ERR_NO_TMP_DIR => '找不到临时文件夹',
                    UPLOAD_ERR_CANT_WRITE => '无法写入磁盘',
                    UPLOAD_ERR_EXTENSION => '文件上传被扩展中断'
                ];
                $errorMsg = $errorMessages[$file['error']] ?? '未知上传错误';
                $this->jsonResponse(['success' => false, 'message' => $errorMsg]);
                return;
            }

            $userModel = new UserModel();
            $userId = $_SESSION['user']['id'];

            // 保存头像
            $avatarUrl = $userModel->saveUserAvatar($userId, $file);

            if ($avatarUrl) {
                // 更新会话中的用户信息
                $_SESSION['user']['avatar'] = $avatarUrl;

                $this->jsonResponse([
                    'success' => true,
                    'message' => '头像更新成功',
                    'avatar_url' => $avatarUrl
                ]);
            } else {
                // 获取Upload类的错误信息
                $uploadError = '';
                if (method_exists($userModel, 'getUploadError')) {
                    $uploadError = $userModel->getUploadError();
                }
                $this->jsonResponse(['success' => false, 'message' => '头像保存失败' . ($uploadError ? '：' . $uploadError : '')]);
            }
        } catch (Exception $e) {
            // 捕获所有异常，日志记录详细信息，客户端返回通用提示
            if (class_exists('Log')) { Log::error('Profile avatar error', 'auth', ['message' => $e->getMessage()]); }
            else { error_log('Profile avatar error: ' . $e->getMessage()); }
            $this->jsonResponse(['success' => false, 'message' => '服务器繁忙，请稍后重试']);
        }
    }

    /**
     * 显示用户公开主页（含文章/点赞/收藏列表与搜索）
     */
    public function user($userId = null) {
        // 初始化模板引擎
        $template = new Template();

        // 获取显示在菜单中的分类
        $categoryModel = new CategoryModel();
        $categories = $categoryModel->getMenuCategories();

        // 获取菜单页面
        $pageModel = new PageModel();
        $pages = $pageModel->getMenuPages();

        // 获取用户ID - 优先使用路由参数，如果没有则使用GET参数
        if ($userId !== null) {
            $userId = intval($userId);
        } else {
            $userId = isset($_GET['id']) ? intval($_GET['id']) : 0;
        }

        // 验证用户ID
        if ($userId <= 0) {
            // 用户ID无效，重定向到首页
            header('Location: index.php');
            exit;
        }

        // 获取用户信息
        $userModel = new UserModel();
        $user = $userModel->getUserById($userId);

        // 检查用户是否存在
        if (!$user || $user['is_deleted'] == 1) {
            // 用户不存在，重定向到首页
            header('Location: index.php');
            exit;
        }

        // 格式化时间
        $user['created_at_formatted'] = date('Y-m-d H:i:s', $user['created_at']);

        // ============ 顶部档案条 - 统计数据 ============
        // 关注功能相关
        $is_following = false;
        $following_count = 0;
        $followers_count = 0;
        $article_count = 0;
        $total_likes = 0;
        $total_views = 0;

        // 初始化FollowModel
        $followModel = new FollowModel();

        // 获取关注和粉丝数量
        $following_count = $followModel->getFollowingCount($userId);
        $followers_count = $followModel->getFollowersCount($userId);

        // 检查当前用户是否已登录
        if (isset($_SESSION['user'])) {
            // 检查是否已关注
            $is_following = $followModel->isFollowing($_SESSION['user']['id'], $userId);
        }

        // 获取文章相关统计
        $articleModel = new ArticleModel();
        $article_count = $articleModel->getArticleCountByUserId($userId);
        $total_likes = $articleModel->getTotalLikesByUserId($userId);
        $total_views = $articleModel->getTotalViewsByUserId($userId);

        // ============ Tab/搜索与内容列表 ============
        // 获取当前 Tab（默认：文章）
        $current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'articles';
        if (!in_array($current_tab, ['articles', 'likes', 'favorites', 'following', 'followers'])) {
            $current_tab = 'articles';
        }

        // 处理搜索关键字（仅文章/点赞/收藏支持搜索）
        $search_keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
        $is_search = !empty($search_keyword) && in_array($current_tab, ['articles', 'likes', 'favorites']);

        // 分页参数
        $current_page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
        $page_size = 10;

        // 初始化列表数据
        $content_list = [];
        $total_count = 0;

        // ========== 隐私设置检查 ==========
        // 解析被访问用户的隐私设置
        $privacySettings = $this->parsePrivacySettings($user['privacy_settings'] ?? null);

        // 访问者标识：是否是自己、自己的 user_id
        $visitorId = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0;
        $isSelf = ($visitorId > 0 && $visitorId === (int)$userId);

        // Tab -> 隐私设置 key 的映射（文章 Tab 永远可见，无需判断）
        $privacyTabMap = [
            'likes'     => 'show_likes',
            'favorites' => 'show_favorites',
            'following' => 'show_following',
            'followers' => 'show_followers',
        ];

        // 当前 Tab 是否被隐私规则屏蔽？
        $isPrivacyBlocked = false;
        if (!$is_search && isset($privacyTabMap[$current_tab])) {
            $tabKey = $privacyTabMap[$current_tab];
            $isPrivacyBlocked = !$this->isPrivacyTabAccessible(
                $tabKey,
                $privacySettings,
                (int)$userId,
                $isSelf,
                $visitorId,
                $followModel
            );
        }

        // 搜索模式下，如果搜索的 Tab 涉及隐私，同样应用隐私规则
        $isSearchPrivacyBlocked = false;
        if ($is_search && isset($privacyTabMap[$current_tab])) {
            $tabKey = $privacyTabMap[$current_tab];
            $isSearchPrivacyBlocked = !$this->isPrivacyTabAccessible(
                $tabKey,
                $privacySettings,
                (int)$userId,
                $isSelf,
                $visitorId,
                $followModel
            );
        }

        // 点赞模型
        $likeModel = new LikeModel();
        $favoriteModel = new FavoriteModel();

        if ($is_search) {
            // 若当前搜索的 Tab 因隐私设置不可见，直接返回空列表
            if ($isSearchPrivacyBlocked) {
                $content_list = [];
                $total_count = 0;
            } else {
                // ========== 搜索模式：在发布的文章/点赞的文章/收藏的文章中综合搜索
                $content_list = $articleModel->searchUserRelatedArticles($userId, $search_keyword, $current_page, $page_size);
                $total_count = $articleModel->getSearchUserRelatedCount($userId, $search_keyword);
            }
            $template->assign('search_keyword', $search_keyword);
        } else {
            // ========== 普通 Tab 模式
            if ($isPrivacyBlocked) {
                // 该 Tab 被隐私设置屏蔽，不查询任何数据
                $content_list = [];
                $total_count = 0;
            } else {
                switch ($current_tab) {
                    case 'likes':
                        // 该用户点赞的文章
                        $content_list = $likeModel->getUserLikes($userId, $current_page, $page_size);
                        $content_list = HomeController::batchEnrichArticles($content_list);
                        $total_count = $likeModel->getUserLikesCount($userId);
                        break;
                    case 'favorites':
                        // 该用户收藏的文章
                        $content_list = $favoriteModel->getUserFavorites($userId, $current_page, $page_size);
                        $content_list = HomeController::batchEnrichArticles($content_list);
                        $total_count = $favoriteModel->getUserFavoritesCount($userId);
                        break;
                    case 'following':
                        // 该用户关注的人
                        $content_list = $followModel->getFollowingList($userId, $current_page, $page_size);
                        // 格式化关注时间，并标记当前访客是否已关注列表中的人
                        foreach ($content_list as &$item) {
                            $item['follow_time_formatted'] = date('Y-m-d', $item['follow_time']);
                            if ($visitorId && !$isSelf) {
                                $item['is_following_by_visitor'] = $followModel->isFollowing($visitorId, $item['id']);
                            } else {
                                $item['is_following_by_visitor'] = false;
                            }
                            // 标记列表中的用户是否就是当前访客（用于模板决定是否显示关注按钮）
                            $item['is_current_visitor'] = ($visitorId === (int)$item['id']);
                        }
                        unset($item);
                        $total_count = $following_count;
                        break;
                    case 'followers':
                        // 关注该用户的人（粉丝）
                        $content_list = $followModel->getFollowersList($userId, $current_page, $page_size);
                        // 格式化关注时间，并检查当前登录用户是否也关注了这些人
                        foreach ($content_list as &$item) {
                            $item['follow_time_formatted'] = date('Y-m-d', $item['follow_time']);
                            if ($visitorId && !$isSelf) {
                                $item['is_following_by_visitor'] = $followModel->isFollowing($visitorId, $item['id']);
                            } else {
                                $item['is_following_by_visitor'] = false;
                            }
                            // 标记列表中的用户是否就是当前访客（用于模板决定是否显示关注按钮）
                            $item['is_current_visitor'] = ($visitorId === (int)$item['id']);
                        }
                        unset($item);
                        $total_count = $followers_count;
                        break;
                    case 'articles':
                    default:
                        // 该用户发布的文章
                        $content_list = $articleModel->getArticlesByUserId($userId, $current_page, $page_size);
                        $total_count = $articleModel->getArticleCountByUserId($userId);
                        break;
                }
            }
        }

        // 传递隐私屏蔽相关的模板变量：在 Controller 中预计算标志，避免模板嵌套 if 解析问题
        $reallyBlocked = ($isPrivacyBlocked || $isSearchPrivacyBlocked);
        $template->assign('is_privacy_blocked', $reallyBlocked);
        // 以下三个标志互斥，同一时刻只有一个为 true，模板中用独立的 simple if 渲染
        $template->assign('show_privacy_tip', $reallyBlocked);
        $template->assign('show_empty_state', !$reallyBlocked && empty($content_list));
        $template->assign('show_content_list', !$reallyBlocked && !empty($content_list));
        if ($reallyBlocked) {
            $label = $this->getPrivacyTabLabel($current_tab);
            $template->assign(
                'privacy_tip',
                ($label !== '')
                    ? ('由于该用户隐私设置，' . $label . '列表不可见')
                    : '由于该用户隐私设置，此内容不可见'
            );
        } else {
            $template->assign('privacy_tip', '');
        }

        // 初始化分页类（传递对象，模板用 {pagination} 标签渲染）
        $pagination = new Pagination($total_count, $page_size);

        // 传递分页相关数据到模板
        $template->assign('current_tab', $current_tab);
        $template->assign('is_search', $is_search);
        // JS 友好的布尔值（输出为字面量 true/false），避免 {if is_search} 解析问题
        $template->assign('is_search_var', $is_search ? 'true' : 'false');
        // 为模板提供简单变量标志（避免在 condition 表达式中嵌套 field 条件）
        $template->assign('is_articles_tab', ($current_tab === 'articles'));
        $template->assign('is_likes_tab', ($current_tab === 'likes'));
        $template->assign('is_favorites_tab', ($current_tab === 'favorites'));
        $template->assign('is_following_tab', ($current_tab === 'following'));
        $template->assign('is_followers_tab', ($current_tab === 'followers'));
        $template->assign('is_user_list_tab', in_array($current_tab, ['following', 'followers']));
        // 文章类列表 Tab（文章/点赞/收藏/搜索）统一用一个标志
        $template->assign('is_article_list_tab', in_array($current_tab, ['articles', 'likes', 'favorites']) || $is_search);
        // Tab 激活状态标志（搜索模式下文章/点赞/收藏 Tab 不高亮，显示搜索结果标签）
        $template->assign('is_not_search', !$is_search);
        $template->assign('content_list', $content_list);
        $template->assign('total_count', $total_count);
        $template->assign('pagination', $pagination);
        $template->assign('pagination_info', $pagination->getPaginationInfo());

        // ============ 传递顶部档案条数据到模板 ============
        $template->assign('profile_user', $user);
        $template->assign('is_following', $is_following);
        $template->assign('is_not_self', (isset($_SESSION['user']['id']) && $_SESSION['user']['id'] !== $userId));
        $template->assign('following_count', $following_count);
        $template->assign('followers_count', $followers_count);
        $template->assign('article_count', $article_count);
        $template->assign('total_likes', $total_likes);
        $template->assign('total_views', $total_views);

        // 传递会话用户数据到模板，用于条件判断
        if (isset($_SESSION['user'])) {
            $template->assign('user', $_SESSION['user']);
        }

        // 传递分类数据到模板
        $template->assign('categories', $categories);

        // 传递页面菜单数据到模板
        $template->assign('pages', $pages);

        // 获取消息配置
        $message_enabled = Config::get('message_enabled', '1');
        $message_duration = Config::get('message_duration', '3');
        $template->assign('message_enabled', $message_enabled);
        $template->assign('message_duration', $message_duration);

        // 设置SEO
        $seoData = ['user' => $user];
        $template->assign('seo_title', $this->parseSeoTemplate(Config::get('user_seo_title', '{user.nickname} - {site.name}'), $seoData));
        $template->assign('seo_description', $this->parseSeoTemplate(Config::get('user_seo_description', '{user.nickname}的个人主页'), $seoData));
        $template->assign('seo_keywords', $this->parseSeoTemplate(Config::get('user_seo_keywords', '{user.nickname},{user.username},{site.keywords}'), $seoData));

        // 渲染用户资料模板
        $template->display('user_profile');
    }

    /**
     * 处理关注/取消关注的 AJAX 请求
     */
    public function followAjax() {
        // 设置响应头为 JSON 格式
        header('Content-Type: application/json');

        // 检查用户是否已登录
        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => false, 'message' => '请先登录']);
            exit;
        }

        // 获取被关注用户ID
        $followingId = isset($_POST['id']) ? intval($_POST['id']) : 0;

        // 验证用户ID
        if ($followingId <= 0) {
            echo json_encode(['success' => false, 'message' => '用户ID无效']);
            exit;
        }

        // 初始化FollowModel
        $followModel = new FollowModel();

        // 获取当前用户ID
        $followerId = $_SESSION['user']['id'];

        try {
            // 检查是否已关注
            if ($followModel->isFollowing($followerId, $followingId)) {
                // 已关注，取消关注
                $result = $followModel->unfollowUser($followerId, $followingId);
                if ($result) {
                    echo json_encode([
                        'success' => true,
                        'message' => '取消关注成功',
                        'is_following' => false,
                        'followers_count' => $followModel->getFollowersCount($followingId)
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => '取消关注失败']);
                }
            } else {
                // 未关注，关注用户
                $result = $followModel->followUser($followerId, $followingId);
                if ($result) {
                    echo json_encode([
                        'success' => true,
                        'message' => '关注成功',
                        'is_following' => true,
                        'followers_count' => $followModel->getFollowersCount($followingId)
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => '关注失败']);
                }
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => '操作失败，请重试']);
        }
        exit;
    }

    /**
     * 处理关注/取消关注请求
     */
    public function follow() {
        // 检查用户是否已登录
        if (!isset($_SESSION['user'])) {
            header('Location: /index.php?c=Auth&m=login');
            exit;
        }

        // 获取被关注用户ID
        $followingId = isset($_GET['id']) ? intval($_GET['id']) : 0;

        // 验证用户ID
        if ($followingId <= 0) {
            header('Location: index.php');
            exit;
        }

        // 初始化FollowModel
        $followModel = new FollowModel();

        // 获取当前用户ID
        $followerId = $_SESSION['user']['id'];

        try {
            // 检查是否已关注
            if ($followModel->isFollowing($followerId, $followingId)) {
                // 已关注，取消关注
                $followModel->unfollowUser($followerId, $followingId);
                $_SESSION['success_message'] = '取消关注成功！';
            } else {
                // 未关注，关注用户
                $followModel->followUser($followerId, $followingId);
                $_SESSION['success_message'] = '关注成功！';
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = '操作失败，请重试';
        }

        // 重定向回用户资料页面
        header('Location: /index.php/user/' . $followingId);
        exit;
    }

    /**
     * 显示用户关注列表
     */
    public function following() {
        // 检查用户是否已登录
        if (!isset($_SESSION['user'])) {
            header('Location: /index.php?c=Auth&m=login');
            exit;
        }

        // 获取目标用户ID
        $targetUserId = isset($_GET['id']) ? intval($_GET['id']) : $_SESSION['user']['id'];

        // 初始化模板引擎
        $template = new Template();

        // 获取显示在菜单中的分类
        $categoryModel = new CategoryModel();
        $categories = $categoryModel->getMenuCategories();

        // 获取菜单页面
        $pageModel = new PageModel();
        $pages = $pageModel->getMenuPages();

        // 初始化FollowModel
        $followModel = new FollowModel();

        // 获取当前用户ID
        $userId = $_SESSION['user']['id'];

        // 分页处理
        $current_page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
        // 获取关注页面的分页数量配置，默认使用通用分页配置
        $page_size = Config::get('pagination_following_count', Config::get('pagination_count', 20));

        // 获取关注列表
        $followingList = $followModel->getFollowingList($targetUserId, $current_page, $page_size);

        // 处理关注列表数据
        foreach ($followingList as &$user) {
            // 格式化关注时间
            $user['follow_time_formatted'] = date('Y-m-d H:i:s', $user['follow_time']);
            // 检查当前用户是否已关注该用户
            $user['is_following'] = $followModel->isFollowing($userId, $user['id']);
        }

        // 获取关注总数
        $followingCount = $followModel->getFollowingCount($targetUserId);

        // 初始化分页类
        $pagination = new Pagination($followingCount, $page_size);

        // 获取当前登录用户信息
        $currentUser = $_SESSION['user'];

        // 格式化当前用户的时间
        $currentUser['created_at_formatted'] = date('Y-m-d H:i:s', $currentUser['created_at']);

        // 获取当前用户ID
        $userId = $_SESSION['user']['id'];

        // 获取关注和粉丝数量
        $following_count = $followModel->getFollowingCount($userId);
        $followers_count = $followModel->getFollowersCount($userId);

        // 传递数据到模板
        $template->assign('categories', $categories);
        $template->assign('pages', $pages);
        $template->assign('target_user', $currentUser);
        $template->assign('profile_user', $currentUser);
        $template->assign('user', $currentUser);
        $template->assign('following_list', $followingList);
        $template->assign('following_count', $following_count);
        $template->assign('followers_count', $followers_count);
        $template->assign('pagination', $pagination);
        $template->assign('pagination_info', $pagination->getPaginationInfo());

        // 设置SEO
        $pageTitle = '我的关注';
        $seoData = ['user' => $currentUser, 'page_title' => $pageTitle];
        $template->assign('seo_title', $this->parseSeoTemplate(Config::get('user_seo_title', '{user.nickname} - {site.name}'), $seoData));
        $template->assign('seo_description', $this->parseSeoTemplate(Config::get('user_seo_description', '{user.nickname}的个人主页'), $seoData));
        $template->assign('seo_keywords', $this->parseSeoTemplate(Config::get('user_seo_keywords', '{user.nickname},{site.keywords}'), $seoData));

        // 渲染模板
        $template->display('profile_following');
    }

    /**
     * 显示用户粉丝列表
     */
    public function followers() {
        // 检查用户是否已登录
        if (!isset($_SESSION['user'])) {
            header('Location: /index.php?c=Auth&m=login');
            exit;
        }

        // 获取目标用户ID
        $targetUserId = isset($_GET['id']) ? intval($_GET['id']) : $_SESSION['user']['id'];

        // 初始化模板引擎
        $template = new Template();

        // 获取显示在菜单中的分类
        $categoryModel = new CategoryModel();
        $categories = $categoryModel->getMenuCategories();

        // 获取菜单页面
        $pageModel = new PageModel();
        $pages = $pageModel->getMenuPages();

        // 初始化FollowModel
        $followModel = new FollowModel();

        // 获取当前用户ID
        $userId = $_SESSION['user']['id'];

        // 分页处理
        $current_page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
        // 获取粉丝页面的分页数量配置，默认使用通用分页配置
        $page_size = Config::get('pagination_followers_count', Config::get('pagination_count', 20));

        // 获取粉丝列表
        $followersList = $followModel->getFollowersList($targetUserId, $current_page, $page_size);

        // 处理粉丝列表数据
        foreach ($followersList as &$user) {
            // 格式化关注时间
            $user['follow_time_formatted'] = date('Y-m-d H:i:s', $user['follow_time']);
            // 检查当前用户是否已关注该用户
            $user['is_following'] = $followModel->isFollowing($userId, $user['id']);
        }

        // 获取粉丝总数
        $followersCount = $followModel->getFollowersCount($targetUserId);

        // 初始化分页类
        $pagination = new Pagination($followersCount, $page_size);

        // 获取当前登录用户信息
        $currentUser = $_SESSION['user'];

        // 格式化当前用户的时间
        $currentUser['created_at_formatted'] = date('Y-m-d H:i:s', $currentUser['created_at']);

        // 获取当前用户ID
        $userId = $_SESSION['user']['id'];

        // 获取关注和粉丝数量
        $following_count = $followModel->getFollowingCount($userId);
        $followers_count = $followModel->getFollowersCount($userId);

        // 传递数据到模板
        $template->assign('categories', $categories);
        $template->assign('pages', $pages);
        $template->assign('target_user', $currentUser);
        $template->assign('profile_user', $currentUser);
        $template->assign('user', $currentUser);
        $template->assign('followers_list', $followersList);
        $template->assign('following_count', $following_count);
        $template->assign('followers_count', $followers_count);
        $template->assign('pagination', $pagination);
        $template->assign('pagination_info', $pagination->getPaginationInfo());

        // 设置SEO
        $pageTitle = '我的粉丝';
        $seoData = ['user' => $currentUser, 'page_title' => $pageTitle];
        $template->assign('seo_title', $this->parseSeoTemplate(Config::get('user_seo_title', '{user.nickname} - {site.name}'), $seoData));
        $template->assign('seo_description', $this->parseSeoTemplate(Config::get('user_seo_description', '{user.nickname}的个人主页'), $seoData));
        $template->assign('seo_keywords', $this->parseSeoTemplate(Config::get('user_seo_keywords', '{user.nickname},{site.keywords}'), $seoData));

        // 渲染模板
        $template->display('profile_followers');
    }

    /**
     * 校检一个 URL 是否合法：必须以 http:// 或 https:// 开头，并包含合法的主机名
     * @param string $url
     * @return bool
     */
    private static function isValidUrl($url) {
        if (!is_string($url) || trim($url) === '') {
            return false;
        }
        // 必须带协议头（http 或 https）
        if (!preg_match('#^https?://#i', $url)) {
            return false;
        }
        // 使用 PHP 内置的 filter_var 做一次完整校验
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}
