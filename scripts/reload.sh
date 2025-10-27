#!/bin/bash

# 平滑重启脚本

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

cd "$PROJECT_DIR"

echo "================================================================"
echo " PFinal Asyncio Heartbeat - Reloading Server"
echo "================================================================"
echo ""

# 检查服务器是否在运行
if ! php examples/server.php status &> /dev/null; then
    echo "❌ 服务器未运行"
    echo "请先启动服务器: bash scripts/start.sh"
    exit 1
fi

echo "🔄 平滑重启服务器..."
echo ""

# 平滑重启
php examples/server.php reload

echo ""
echo "✅ 服务器已重启"
echo ""
echo "提示: 平滑重启不会断开现有连接"

