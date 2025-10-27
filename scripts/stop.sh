#!/bin/bash

# 停止脚本

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

cd "$PROJECT_DIR"

echo "================================================================"
echo " PFinal Asyncio Heartbeat - Stopping Server"
echo "================================================================"
echo ""

# 停止服务器
php examples/server.php stop

echo ""
echo "✅ 服务器已停止"

