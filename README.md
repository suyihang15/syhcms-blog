# syhcms

一个轻量的个人博客程序：PHP + SQLite，不需要 Composer，也不需要单独的数据库服务。上传、安装、写文章，就这三步。

## 功能特性

**开箱即用的前台主题**

- 响应式布局，手机 / 平板 / 桌面自适应
- 亮色 / 暗色模式，一键切换并记住偏好
- 文章自动生成目录（TOC），代码块带语法高亮与「复制」按钮
- 图片灯箱浏览、懒加载
- 文章页：上一篇 / 下一篇、相关文章、分类、标签
- 站内搜索、分类 / 标签 / 作者 / 日期归档

**内容与页面**

- 文章、页面两种内容类型
- 分类（支持多级）与标签
- 四种内置页面模板：友链页、留言板、归档页、统计页

**评论与互动**

- 嵌套回复
- 垃圾防护：蜜罐字段 + 频率限制 + 重复内容拦截
- 默认人工审核，可信邮箱可自动放行
- 新评论 / 回复 / 审核通过 邮件通知（SMTP）

**访问统计**

- 文章浏览量
- 访问来源自动分类：直接访问 / 搜索引擎 / 社交平台 / 外链
- 30 天趋势、热门文章、分类热度（统计页可视化）

**后台管理**

- 仪表盘：文章、评论、媒体、友链、浏览量总览
- 文章 / 页面 / 分类 / 标签 / 评论 / 媒体库 / 菜单 / 友链 / 用户 全后台管理
- 多角色：管理员、编辑、作者、订阅者
- 媒体库：图片（jpg / png / gif / webp / avif）、PDF、ZIP、文本上传

**外观与个性化**

- 主题色、背景色、文字色在线调色
- Logo、favicon、首页封面、横幅文字
- 自定义 CSS（附加在主题样式之后）
- 社交链接（QQ / 微信二维码、B 站、GitHub）
- 打赏二维码、备案号（ICP / 公安）

**SEO 与订阅**

- 自动生成 `sitemap.xml`、`robots.txt`
- RSS / Atom 订阅（文章 + 评论）
- canonical、Open Graph 标签
- favicon 301 重定向

**运维友好**

- 数据全部存于单个 SQLite 文件，备份即复制一个文件
- 无第三方依赖、无 Composer、无数据库服务
- 内置命令行安装脚本，自动检查 PHP 版本与扩展

## 环境要求

- PHP 8.1 及以上
- 扩展：`pdo_sqlite`、`mbstring`、`gd`、`fileinfo`
- Nginx 或 Apache（Apache 已内置 `public/.htaccess`）
- `data/` 和 `public/wp-content/uploads/` 需要可写

## 快速部署

三种方式任选其一，推荐「宝塔面板」，全程点点鼠标。

### 方式一：宝塔面板（最省事）

1. 安装宝塔面板，软件商店装好 **Nginx** 和 **PHP 8.1+**；在「PHP 设置 → 安装扩展」里确认 `pdo_sqlite`、`mbstring`、`gd`、`fileinfo` 四个扩展已安装（`pdo_sqlite` 尤其要确认）。
2. 「网站 → 添加站点」，填域名并创建。
3. 进入站点目录，上传压缩包并解压。
4. 「网站 → 设置 → 网站目录」，把 **运行目录** 选为 `/public`（关键步骤）。
5. 「网站 → 设置 → 伪静态」，粘贴：

   ```nginx
   location / {
       try_files $uri $uri/ /index.php?$query_string;
   }
   ```

6. 给 `data/` 和 `public/wp-content/uploads/` 写权限（文件管理器里右键目录 → 权限 → 勾选写入，或命令行 `chmod 775`）。
7. 浏览器打开 `http://你的域名/install.php`，填写站点信息，完成安装。
8. 删除 `public/install.php`，然后访问 `http://你的域名/admin/` 登录。

### 方式二：一条命令（命令行）

上传解压后，在站点目录执行：

```bash
bash deploy/install.sh \
  --site-url=https://example.com \
  --site-name=我的博客 \
  --admin-user=admin \
  --admin-pass=你的密码
```

脚本会自动检查 PHP 版本与扩展、创建目录并设置权限。装完后再按下方「Nginx 配置」把 root 指向 `public/` 即可。

### 方式三：手动（通用服务器）

把站点根目录指向 `public/`，浏览器访问 `/install.php` 按提示安装。Nginx 见下方；Apache 无需额外配置，程序已自带 `public/.htaccess`，只要确保开启 `mod_rewrite`。

> 无论哪种方式，安装都会创建 `data/site.sqlite`，写入两篇示例文章、关于 / 友链 / 留言板三个页面和一组导航菜单。默认管理员 `admin` / `password`，登录后请立刻在「用户」里改掉。

## Nginx 配置

`deploy/nginx.conf.example` 是完整示例，核心只有两行：

```nginx
root /www/wwwroot/example.com/public;   # 指向 public/，不是程序根目录

location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

`fastcgi_pass` 地址按你的环境改：

| 环境 | fastcgi_pass |
| --- | --- |
| 宝塔面板 | `unix:/tmp/php-cgi-81.sock`（版本号换成你的）|
| Debian / Ubuntu | `unix:/run/php/php8.1-fpm.sock` |
| 通用 | `127.0.0.1:9000` |

## Apache 配置

无需手动写伪静态。确认启用 `mod_rewrite` 后，把 `DocumentRoot` 指向 `public/` 即可，`public/.htaccess` 已包含规则。若你的环境禁用了 `.htaccess`，把下面规则写进虚拟主机：

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} -f [OR]
    RewriteCond %{REQUEST_FILENAME} -d
    RewriteRule ^ - [L]
    RewriteRule ^ index.php [L]
</IfModule>
```

## 后台

- 地址：`/admin/`
- 首次登录：`admin` / `password`
- 设置：站点名称、描述、地址、时区、每页文章数、备案号、SMTP 邮件通知
- 外观：主题色、背景色、文字色、Logo、favicon、首页封面、横幅文字、自定义 CSS
- 内容：文章、页面、分类、标签、评论、媒体库、菜单、友链、用户

## 自定义

大部分调整不需要改代码：

1. **配色**：后台「外观」里直接选颜色；也可以写自定义 CSS，例如：

   ```css
   :root {
       --primary: #0a84ff;
       --bg: #f7f7f8;
       --text: #1d1d1f;
   }
   ```

2. **首页**：外观页可以换封面图和横幅文字；不填封面时使用 `public/assets/home.jpg`。
3. **页面模板**：新建页面时可选模板 —— `page-links.php` 是友链页，`page-guestbook.php` 是留言板，`page-archive.php` 是归档页，`page-stats.php` 是统计页。
4. **导航**：后台「菜单」里增删，支持把页面挂到导航上。
5. **友链**：后台「友链」维护分组、名称、地址和头像；页脚会自动展示。
6. **评论**：默认需要审核；可在「设置 - 评论」里调整，邮件提醒在「设置 - 邮件通知」里配置。

## 目录结构与文件说明

```
syhcms/
├── config.php              全局配置：数据库、上传、会话、缓存、时区、调试
├── deploy/
│   ├── install.sh          命令行一键安装脚本（自动检查环境、建目录、装数据）
│   └── nginx.conf.example  Nginx 站点配置示例
├── data/                   SQLite 数据库、会话、缓存、备份（不对外，需可写）
├── public/                 Web 根目录（站点 DocumentRoot 指向这里）
│   ├── index.php           入口文件：加载引导并运行应用
│   ├── install.php         安装向导（浏览器 / 命令行共用，装完删掉）
│   ├── router.php          PHP 内置服务器路由（php -S 本地调试用）
│   ├── .htaccess           Apache 伪静态规则
│   ├── assets/             主题 CSS / JS 和默认封面
│   └── wp-content/uploads/ 后台上传的媒体文件（需可写）
├── src/                    后端核心代码
└── templates/              前端模板
```

### 后端 `src/` 各文件

| 文件 | 作用 |
| --- | --- |
| `bootstrap.php` | 引导：定义常量、加载依赖、时区、错误处理、会话初始化、建目录 |
| `App.php` | 前端控制器：路由分发（API / 后台 / 评论 / 前台）、安全响应头 |
| `Frontend.php` | 前台逻辑：首页、文章、页面、归档、搜索、RSS、站点地图、评论提交 |
| `Admin.php` | 后台逻辑：登录、仪表盘、文章 / 页面 / 分类 / 标签 / 评论 / 媒体 / 菜单 / 友链 / 用户 / 设置 / 外观 / 源码编辑 |
| `Auth.php` | 认证与会话：登录 / 登出、角色权限、CSRF、密码哈希（兼容 WordPress 旧哈希） |
| `Database.php` | SQLite 封装：PDO 连接、建表迁移、增删改查辅助方法 |
| `Mailer.php` | 极简 SMTP 客户端：评论 / 回复 / 审核通过邮件通知 |
| `CodeEditor.php` | 后台源码编辑器：目录白名单、语法检查、自动备份、原子写入 |
| `helpers.php` | 全局辅助函数：HTML 转义、URL 生成、短代码解析、目录生成、统计、菜单、分页等 |

### 前端 `templates/` 各文件

前台（`templates/`）：

| 文件 | 作用 |
| --- | --- |
| `header.php` / `footer.php` | 页面布局的头部与底部（含导航、友链、备案、脚本加载） |
| `home.php` | 首页文章列表 |
| `single.php` | 单篇文章页（含上一篇 / 下一篇、相关文章、评论） |
| `page.php` | 普通页面 |
| `page-links.php` | 友链页面模板 |
| `page-guestbook.php` | 留言板页面模板 |
| `page-archive.php` | 归档页面模板 |
| `page-stats.php` | 访问统计页面模板 |
| `archive.php` | 分类 / 标签 / 作者 / 日期归档列表 |
| `search.php` | 搜索结果页 |
| `comments.php` | 评论列表与评论表单 |
| `404.php` | 404 页面 |
| `partials/post-card.php` | 文章卡片（列表页复用） |

后台（`templates/admin/`）：

| 文件 | 作用 |
| --- | --- |
| `header.php` / `footer.php` / `menu.php` | 后台布局：头部、底部、侧边导航 |
| `login.php` | 登录页 |
| `dashboard.php` | 仪表盘 |
| `posts.php` | 文章 / 页面列表（共用） |
| `post-edit.php` | 文章 / 页面编辑器 |
| `comments.php` | 评论管理 |
| `media.php` | 媒体库 |
| `terms.php` | 分类 / 标签管理 |
| `links.php` | 友链管理 |
| `users.php` | 用户管理 |
| `profile.php` | 个人资料 |
| `settings.php` | 站点设置 |
| `appearance.php` | 外观设置 |
| `code.php` | 源码编辑器 |
| `not-found.php` | 后台 404 |

## 备份与恢复

需要备份的东西只有两样：

- `data/site.sqlite`（以及可能存在的 `site.sqlite-wal`、`site.sqlite-shm`）
- `public/wp-content/uploads/`

恢复时把文件放回原位，确认目录权限可写即可。换域名后记得在后台把「站点地址」改掉。

## 许可

MIT License，作者 suyihang15。详见 `LICENSE`。
