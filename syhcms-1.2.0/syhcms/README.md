# 个人博客系统（PHP + SQLite）

一个轻量的个人博客 / 站点程序：PHP 原生实现，数据存 SQLite，无需 MySQL，上传即用。
包含文章、页面、分类标签、评论、媒体库、菜单、友情链接、访问统计和后台管理。

## 环境要求

- PHP 8.1 及以上（CLI 与 FPM 均可）
- PHP 扩展：`pdo_sqlite`、`mbstring`、`curl`、`fileinfo`
- Web 服务器：Nginx 或 Apache
- 对 `data/` 和 `public/wp-content/uploads/` 目录有写权限
- 不需要 MySQL

## 目录结构

```
├── config.php              数据库、上传、会话等路径配置
├── public/                 站点根目录（Web 根指向这里）
│   ├── index.php           入口
│   ├── install.php         安装向导（装完请删除）
│   ├── router.php          PHP 内置服务器的路由文件
│   ├── assets/             前端样式与脚本
│   └── wp-content/uploads/ 上传目录
├── src/                    程序代码
├── templates/              页面模板
└── data/                   安装后生成：SQLite 数据库、会话、缓存
```

## 安装步骤

1. 把全部文件上传到服务器，例如 `/www/wwwroot/example.com`。
2. 网站根目录设为 `/www/wwwroot/example.com/public`，**不要**直接指向项目根目录。
3. 给写权限（按实际运行用户调整）：

   ```bash
   chown -R www:www /www/wwwroot/example.com/data \
                      /www/wwwroot/example.com/public/wp-content/uploads
   chmod -R 775 /www/wwwroot/example.com/data \
                /www/wwwroot/example.com/public/wp-content/uploads
   ```

4. 浏览器访问 `https://你的域名/install.php`，填写站点名称、站点地址、管理员账号和密码。
5. 安装完成后**删除 `public/install.php`**，然后到 `https://你的域名/admin/login/` 登录后台。
6. 在后台“设置”“外观”里修改站点名称、描述、作者署名等，新增你的第一篇文章。

若安装时提示目录不可写，检查第 3 步和 PHP 运行用户。

## 伪静态 / 重写规则

程序使用简短固定链接（如 `/about/`、`/2026/11/hello/`），需要把不存在的路径交给 `index.php`。

Nginx 示例见 `deploy/nginx.conf.example`。核心两行：

```nginx
root /www/wwwroot/example.com/public;
location / { try_files $uri $uri/ /index.php?$query_string; }
```

Apache 已自带 `public/.htaccess`，确认站点配置里 `AllowOverride All` 且 `mod_rewrite` 已启用。

## 本地快速体验

```bash
cd /path/to/project
php -S 127.0.0.1:8000 -t public public/router.php
```

然后访问 `http://127.0.0.1:8000/install.php`。

## 安全建议

- 安装后立即删除 `public/install.php`。
- 上传目录已禁止执行 PHP（Nginx 配置与 `.htaccess` 中都有），请勿去掉。
- 全站启用 HTTPS；后台密码使用 10 位以上随机字符。
- 定期备份 `data/site.sqlite` 与 `public/wp-content/uploads/`。
- `config.php` 中 `debug` 保持 `false`。

## 说明

- 默认站点名称、描述和首页封面均为通用占位内容，安装时与后台“设置/外观”中可自由修改。
- 页脚署名默认显示“本站由作者本人搭建”；如需显示姓名与个人链接，在后台“设置”里填写作者名称和作者链接。
- 未附带任何演示文章、图片、评论或个人数据，安装后是一个空白站点。

## 许可证

MIT License。
