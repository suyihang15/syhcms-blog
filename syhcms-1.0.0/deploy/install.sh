#!/usr/bin/env bash
#
# 命令行一键安装脚本
#
#   bash deploy/install.sh --site-url=https://example.com --site-name=我的博客
#
# 不传参数时使用默认值：站点 http://localhost，管理员 admin / password。
set -euo pipefail

cd "$(dirname "$0")/.."

# 终端下才着色，重定向到文件/管道时不输出颜色控制符
if [ -t 1 ]; then
    C_OK='\033[0;32m'; C_ERR='\033[0;31m'; C_DIM='\033[0;33m'; C_END='\033[0m'
else
    C_OK=''; C_ERR=''; C_DIM=''; C_END=''
fi

echo "==> 检查运行环境"

if ! command -v php >/dev/null 2>&1; then
    echo -e "  ${C_ERR}✗${C_END} 没有找到 php 命令，请先安装 PHP 8.1 或更高版本。" >&2
    exit 1
fi
PHP_VER="$(php -r 'echo PHP_VERSION;')"
echo -e "  ${C_OK}✓${C_END} PHP ${PHP_VER}"

if ! php -r 'exit(version_compare(PHP_VERSION, "8.1.0", ">=") ? 0 : 1);'; then
    echo -e "  ${C_ERR}✗${C_END} PHP 版本过低（当前 ${PHP_VER}），需要 8.1 或更高版本。" >&2
    exit 1
fi

# 必要扩展检查
MISSING=()
for ext in pdo_sqlite mbstring gd fileinfo; do
    if php -r "exit(extension_loaded('${ext}') ? 0 : 1);"; then
        echo -e "  ${C_OK}✓${C_END} 扩展 ${ext}"
    else
        echo -e "  ${C_ERR}✗${C_END} 缺少扩展 ${ext}"
        MISSING+=("${ext}")
    fi
done
if [ ${#MISSING[@]} -gt 0 ]; then
    echo ""
    echo -e "${C_ERR}缺少必要扩展：${MISSING[*]}${C_END}" >&2
    echo "  宝塔面板：软件商店 → PHP → 设置 → 安装扩展" >&2
    echo "  Debian/Ubuntu：sudo apt install php-sqlite3 php-mbstring php-gd php-fileinfo" >&2
    exit 1
fi

echo "==> 准备目录与权限"
mkdir -p data public/wp-content/uploads data/sessions data/cache data/backup
chmod -R 775 data public/wp-content/uploads 2>/dev/null || true

echo "==> 开始安装"
php public/install.php "$@"

echo ""
echo "接下来："
echo "  1. 把 Nginx 的 root 指到 $(pwd)/public（不是程序根目录）"
echo "  2. 打开站点，确认首页正常"
echo "  3. 删除 public/install.php"
