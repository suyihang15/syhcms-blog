# 贡献指南

感谢你有兴趣为 syhcms 做贡献！这是一个轻量的个人博客程序：PHP + SQLite，无 Composer、无数据库服务。

## 提 Bug / 提需求

- 先在 [Issues](../../issues) 里搜一下，避免重复。
- Bug 请说明：运行环境（PHP 版本、Web 服务器）、复现步骤、预期与实际表现。
- 功能建议请说明使用场景。

## 提交代码（Pull Request）

1. Fork 本仓库并 clone 到本地。
2. 新建分支，名字简短描述改动，例如 `fix/comment-xss`、`feat/theme-export`。
3. 一个 PR 只做一件事，方便 review。
4. 遵循现有代码风格（见下）。
5. 提交信息写清楚「做了什么、为什么」。
6. Push 分支后发起 PR，并按模板填写信息。

## 本地开发

```bash
php -S 127.0.0.1:8000 -t public public/router.php
```

访问 `http://127.0.0.1:8000/install.php` 完成安装后即可开发调试。

## 代码风格

- PHP 文件以 `<?php` + `declare(strict_types=1);` 开头，4 空格缩进，函数名 `snake_case`。
- 模板（`templates/`）输出用户内容一律走 `e()` 转义。
- 新文件在头部写清用途与关键设计，参考 `src/FriendFeed.php`。

## 许可证

提交即表示你同意在 MIT 许可证下发布你的代码。
