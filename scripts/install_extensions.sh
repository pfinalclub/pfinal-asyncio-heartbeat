#!/bin/bash

# 安装 PHP 性能扩展脚本

set -e

echo "================================================================"
echo " PFinal Asyncio Heartbeat - Extension Installer"
echo "================================================================"
echo ""

# 检查是否是 root 用户
if [[ $EUID -ne 0 ]]; then
   echo "错误: 此脚本需要 root 权限运行"
   echo "请使用: sudo bash $0"
   exit 1
fi

# 检测 PHP 版本
PHP_VERSION=$(php -r 'echo PHP_VERSION;')
echo "检测到 PHP 版本: $PHP_VERSION"
echo ""

# 检查 PHP 版本是否 >= 8.1
PHP_MAJOR=$(php -r 'echo PHP_MAJOR_VERSION;')
PHP_MINOR=$(php -r 'echo PHP_MINOR_VERSION;')

if [ "$PHP_MAJOR" -lt 8 ] || ([ "$PHP_MAJOR" -eq 8 ] && [ "$PHP_MINOR" -lt 1 ]); then
    echo "错误: 需要 PHP 8.1 或更高版本"
    exit 1
fi

# 询问用户安装哪个扩展
echo "请选择要安装的扩展:"
echo "  1) Ev (推荐，性能提升 10倍)"
echo "  2) Event (性能提升 4倍)"
echo "  3) 两者都安装"
echo "  4) 跳过"
echo ""
read -p "请输入选项 [1-4]: " choice

case $choice in
    1)
        echo ""
        echo "正在安装 Ev 扩展..."
        pecl install ev
        
        # 添加配置
        PHP_INI_DIR=$(php -i | grep "Scan this dir for additional .ini files" | awk '{print $NF}')
        echo "extension=ev.so" > "$PHP_INI_DIR/20-ev.ini"
        
        echo "✅ Ev 扩展安装完成"
        ;;
    2)
        echo ""
        echo "正在安装 Event 扩展..."
        
        # 安装依赖
        if command -v apt-get &> /dev/null; then
            apt-get install -y libevent-dev
        elif command -v yum &> /dev/null; then
            yum install -y libevent-devel
        fi
        
        pecl install event
        
        # 添加配置
        PHP_INI_DIR=$(php -i | grep "Scan this dir for additional .ini files" | awk '{print $NF}')
        echo "extension=event.so" > "$PHP_INI_DIR/20-event.ini"
        
        echo "✅ Event 扩展安装完成"
        ;;
    3)
        echo ""
        echo "正在安装 Ev 和 Event 扩展..."
        
        # 安装 libevent
        if command -v apt-get &> /dev/null; then
            apt-get install -y libevent-dev
        elif command -v yum &> /dev/null; then
            yum install -y libevent-devel
        fi
        
        pecl install ev
        pecl install event
        
        PHP_INI_DIR=$(php -i | grep "Scan this dir for additional .ini files" | awk '{print $NF}')
        echo "extension=ev.so" > "$PHP_INI_DIR/20-ev.ini"
        echo "extension=event.so" > "$PHP_INI_DIR/20-event.ini"
        
        echo "✅ Ev 和 Event 扩展安装完成"
        ;;
    4)
        echo "跳过扩展安装"
        ;;
    *)
        echo "无效的选项"
        exit 1
        ;;
esac

echo ""
echo "================================================================"
echo " 验证安装"
echo "================================================================"
echo ""

# 验证扩展
echo "已安装的扩展:"
php -m | grep -E "(ev|event)" || echo "未安装性能扩展"

echo ""
echo "✅ 安装完成！"
echo ""
echo "提示: 使用 Ev 扩展可获得最佳性能"
echo ""

