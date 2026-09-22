# BlogKit

BlogKit 是一款轻量级开源博客系统，使用原生 PHP 开发，不依赖任何框架，开箱即用、易于二次开发。

- 项目主页：<https://www.blogkit.cn>
- 文档中心：<https://www.blogkit.cn/docs>
- 社区反馈：<https://www.blogkit.cn/community>

## 功能特性

- **博客核心**：文章 / 分类 / 标签 / 独立页面 / 评论 / 友情链接
- **用户体系**：注册登录、角色与权限管理、关注、点赞、收藏、阅读历史
- **消息通知**：站内通知、邮件模板、互动消息
- **外观扩展**：主题系统、插件系统（内置 wangEditor 富文本编辑器插件）、钩子（Hook）机制、应用市场
- **媒体管理**：图片上传、WebP 转换、媒体库
- **接口能力**：RESTful API 与 API 密钥管理，方便对接小程序 / App
- **SEO 友好**：自定义 SEO、伪静态、RSS、搜索引擎优化设置
- **安全防护**：验证码、IP 白名单、登录防爆破、CSRF 防护
- **运维工具**：数据备份、数据迁移、缓存管理、日志审计

## 环境要求

| 环境 | 要求 |
| --- | --- |
| PHP | 7.4 及以上（需 PDO、gd、curl、mbstring 扩展） |
| MySQL | 5.7 及以上 |
| Web 服务器 | Apache（含 `.htaccess`）或 Nginx（含 PATH_INFO 伪静态配置） |

## 快速安装

1. 下载源码并上传至网站根目录；
2. 配置 Web 服务器的伪静态规则（Nginx 参考 `nginx.htaccess`）；
3. 浏览器访问 `install.php`，按向导完成数据库配置与管理员创建；
4. 安装完成后请删除服务器上的 `install.php`，并确保 `core/config/database.php` 不对外泄露；
5. 访问 `admin.php` 进入后台。

## 目录结构

```
├── admin/            后台界面（模板与静态资源）
├── app/              业务代码（控制器 / 模型 / 服务 / 中间件）
├── core/             框架内核（路由、模板引擎、数据库、插件与钩子系统）
├── plugins/          插件目录
├── storage/          运行时数据（缓存、日志，需可写）
├── themes/           主题目录
└── uploads/          上传文件（需可写）
```

## 参与贡献

欢迎通过 Issue 反馈问题或提交建议，也欢迎 Fork 后提交 Pull Request。

## 开源许可

本项目基于 [GNU General Public License v3.0 (GPLv3)](LICENSE) 授权发布。

Copyright (C) 2026 石林波 (Bana)
