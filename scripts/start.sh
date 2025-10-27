#!/bin/bash

# 启动脚本

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

cd "$PROJECT_DIR"

echo "================================================================"
echo " PFinal Asyncio Heartbeat - Starting Server"
echo "================================================================"
echo ""

# 检查 PHP
if ! command -v php &> /dev/null; then
    echo "❌ 错误: 未找到 PHP"
    exit 1
fi

# 检查 PHP 版本
PHP_VERSION=$(php -r 'echo PHP_VERSION;')
echo "PHP 版本: $PHP_VERSION"

# 检查扩展
echo "检查扩展:"
if php -m | grep -q "ev"; then
    echo "  ✅ Ev 扩展已安装"
elif php -m | grep -q "event"; then
    echo "  ✅ Event 扩展已安装"
else
    echo "  ⚠️  警告: 未安装 Ev/Event 扩展，性能可能受影响"
fi

# 检查 vendor
if [ ! -d "vendor" ]; then
    echo ""
    echo "❌ 错误: 未找到 vendor 目录"
    echo "请先运行: composer install"
    exit 1
fi

echo ""
echo "🚀 启动服务器..."
echo ""

# 启动服务器
php examples/server.php start

echo ""
echo "✅ 服务器已启动"

