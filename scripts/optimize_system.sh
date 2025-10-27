#!/bin/bash

# 系统参数优化脚本

set -e

echo "================================================================"
echo " PFinal Asyncio Heartbeat - System Optimizer"
echo "================================================================"
echo ""

# 检查是否是 root 用户
if [[ $EUID -ne 0 ]]; then
   echo "错误: 此脚本需要 root 权限运行"
   echo "请使用: sudo bash $0"
   exit 1
fi

echo "正在优化系统参数..."
echo ""

# 备份原配置
echo "📦 备份原配置..."
cp /etc/security/limits.conf /etc/security/limits.conf.backup.$(date +%Y%m%d_%H%M%S)
cp /etc/sysctl.conf /etc/sysctl.conf.backup.$(date +%Y%m%d_%H%M%S)
echo "✅ 备份完成"
echo ""

# 1. 增加文件描述符限制
echo "⚙️  设置文件描述符限制..."
cat >> /etc/security/limits.conf << EOF

# PFinal Asyncio Heartbeat - File Descriptors
* soft nofile 1000000
* hard nofile 1000000
root soft nofile 1000000
root hard nofile 1000000
EOF

# 临时生效
ulimit -n 1000000
echo "✅ 文件描述符限制已设置: 1000000"
echo ""

# 2. TCP/IP 参数优化
echo "⚙️  优化 TCP/IP 参数..."
cat >> /etc/sysctl.conf << EOF

# PFinal Asyncio Heartbeat - TCP/IP Optimization

# TCP 连接复用
net.ipv4.tcp_tw_reuse = 1

# TIME_WAIT 快速回收
net.ipv4.tcp_fin_timeout = 30

# SYN 队列长度
net.ipv4.tcp_max_syn_backlog = 65535

# 完整连接队列长度
net.core.somaxconn = 65535

# 网络设备接收队列
net.core.netdev_max_backlog = 65535

# 本地端口范围
net.ipv4.ip_local_port_range = 1024 65535

# TCP 内存
net.ipv4.tcp_mem = 786432 2097152 3145728
net.ipv4.tcp_rmem = 4096 87380 16777216
net.ipv4.tcp_wmem = 4096 65536 16777216

# 最大孤儿连接数
net.ipv4.tcp_max_orphans = 262144

# 启用 TCP 时间戳
net.ipv4.tcp_timestamps = 1

# 启用 SYN Cookies
net.ipv4.tcp_syncookies = 1

# TCP keepalive
net.ipv4.tcp_keepalive_time = 600
net.ipv4.tcp_keepalive_probes = 3
net.ipv4.tcp_keepalive_intvl = 15

# 文件系统
fs.file-max = 2097152
fs.nr_open = 2097152
EOF

# 应用配置
sysctl -p > /dev/null 2>&1
echo "✅ TCP/IP 参数优化完成"
echo ""

# 3. 检查并配置交换空间
echo "⚙️  检查交换空间..."
SWAP_SIZE=$(free -m | awk '/Swap:/ {print $2}')
if [ "$SWAP_SIZE" -eq 0 ]; then
    echo "⚠️  警告: 未检测到交换空间"
    read -p "是否创建 4GB 交换空间? [y/N]: " create_swap
    
    if [[ $create_swap =~ ^[Yy]$ ]]; then
        echo "创建交换空间..."
        dd if=/dev/zero of=/swapfile bs=1M count=4096
        chmod 600 /swapfile
        mkswap /swapfile
        swapon /swapfile
        echo "/swapfile none swap sw 0 0" >> /etc/fstab
        echo "✅ 交换空间创建完成"
    fi
else
    echo "✅ 交换空间: ${SWAP_SIZE}MB"
fi
echo ""

# 4. 优化内核参数
echo "⚙️  优化内核参数..."
cat >> /etc/sysctl.conf << EOF

# 内核参数优化
vm.swappiness = 10
vm.overcommit_memory = 1
kernel.panic = 10
kernel.panic_on_oops = 1
EOF

sysctl -p > /dev/null 2>&1
echo "✅ 内核参数优化完成"
echo ""

# 5. 显示当前配置
echo "================================================================"
echo " 当前系统配置"
echo "================================================================"
echo ""

echo "📊 文件描述符限制:"
ulimit -n
echo ""

echo "📊 TCP 参数:"
echo "  net.core.somaxconn = $(sysctl -n net.core.somaxconn)"
echo "  net.ipv4.tcp_max_syn_backlog = $(sysctl -n net.ipv4.tcp_max_syn_backlog)"
echo "  net.ipv4.ip_local_port_range = $(sysctl -n net.ipv4.ip_local_port_range)"
echo ""

echo "📊 内存信息:"
free -h
echo ""

echo "================================================================"
echo " ✅ 优化完成！"
echo "================================================================"
echo ""
echo "提示:"
echo "  1. 重新登录以使文件描述符限制生效"
echo "  2. 或运行: ulimit -n 1000000"
echo "  3. 重启服务以应用所有更改"
echo ""

