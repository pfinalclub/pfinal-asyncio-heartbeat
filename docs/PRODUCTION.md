# 生产环境部署指南

## 系统要求

### 硬件要求

- **CPU**: 8核及以上
- **内存**: 16GB 及以上
- **网络**: 千兆网卡
- **磁盘**: SSD（用于日志）

### 软件要求

- **操作系统**: Linux (推荐 Ubuntu 22.04 或 CentOS 8+)
- **PHP**: 8.1 或更高版本
- **PHP 扩展**:
  - `ev` 或 `event` (高性能事件循环)
  - `pcntl` (进程控制)
  - `posix` (POSIX 函数)
  - `sockets` (Socket 支持)

## 安装步骤

### 1. 安装 PHP 和扩展

```bash
# Ubuntu/Debian
sudo apt update
sudo apt install php8.1-cli php8.1-dev php-pear

# 安装 Ev 扩展（推荐，性能最好）
sudo pecl install ev
echo "extension=ev.so" | sudo tee /etc/php/8.1/cli/conf.d/20-ev.ini

# 或者安装 Event 扩展
sudo apt install libevent-dev
sudo pecl install event
echo "extension=event.so" | sudo tee /etc/php/8.1/cli/conf.d/20-event.ini
```

### 2. 安装项目

```bash
cd /opt
git clone https://github.com/your-org/pfinal-asyncio-heartbeat.git
cd pfinal-asyncio-heartbeat
composer install --no-dev --optimize-autoloader
```

### 3. 系统优化

运行优化脚本：

```bash
sudo bash scripts/optimize_system.sh
```

或手动配置：

```bash
# 增加文件描述符限制
echo "* soft nofile 1000000" | sudo tee -a /etc/security/limits.conf
echo "* hard nofile 1000000" | sudo tee -a /etc/security/limits.conf

# TCP 优化
sudo tee -a /etc/sysctl.conf << EOF
net.ipv4.tcp_tw_reuse = 1
net.ipv4.tcp_fin_timeout = 30
net.core.somaxconn = 65535
net.core.netdev_max_backlog = 65535
net.ipv4.tcp_max_syn_backlog = 65535
net.ipv4.ip_local_port_range = 1024 65535
EOF

sudo sysctl -p
```

## 配置

### 1. 环境变量

创建 `.env` 文件：

```bash
HEARTBEAT_HOST=0.0.0.0
HEARTBEAT_PORT=9501
HEARTBEAT_WORKERS=8
HEARTBEAT_TIMEOUT=60
HEARTBEAT_CHECK_INTERVAL=20
HEARTBEAT_MAX_CONNECTIONS=1000000
HEARTBEAT_LOG_LEVEL=warning
HEARTBEAT_LOG_FILE=/var/log/heartbeat/server.log
```

### 2. 日志目录

```bash
sudo mkdir -p /var/log/heartbeat
sudo chown www-data:www-data /var/log/heartbeat
```

## 进程管理

### 使用 Supervisor

#### 1. 安装 Supervisor

```bash
sudo apt install supervisor
```

#### 2. 配置

```bash
sudo cp config/supervisor.conf /etc/supervisor/conf.d/heartbeat-server.conf
sudo vim /etc/supervisor/conf.d/heartbeat-server.conf
```

修改路径：

```ini
command=php /opt/pfinal-asyncio-heartbeat/examples/server.php
directory=/opt/pfinal-asyncio-heartbeat
user=www-data
```

#### 3. 启动

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start heartbeat-server:*
```

#### 4. 管理命令

```bash
# 查看状态
sudo supervisorctl status heartbeat-server:*

# 停止
sudo supervisorctl stop heartbeat-server:*

# 重启
sudo supervisorctl restart heartbeat-server:*

# 查看日志
sudo supervisorctl tail -f heartbeat-server:heartbeat-worker_00 stdout
```

### 使用 Systemd

#### 1. 创建服务文件

```bash
sudo vim /etc/systemd/system/heartbeat-server.service
```

内容：

```ini
[Unit]
Description=PFinal Asyncio Heartbeat Server
After=network.target

[Service]
Type=forking
User=www-data
Group=www-data
WorkingDirectory=/opt/pfinal-asyncio-heartbeat
ExecStart=/usr/bin/php examples/server.php start -d
ExecReload=/usr/bin/php examples/server.php reload
ExecStop=/usr/bin/php examples/server.php stop
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

#### 2. 管理命令

```bash
# 重载配置
sudo systemctl daemon-reload

# 启动
sudo systemctl start heartbeat-server

# 停止
sudo systemctl stop heartbeat-server

# 重启
sudo systemctl restart heartbeat-server

# 开机自启
sudo systemctl enable heartbeat-server

# 查看状态
sudo systemctl status heartbeat-server

# 查看日志
sudo journalctl -u heartbeat-server -f
```

## 负载均衡

### Nginx TCP 负载均衡

#### 1. 安装带 stream 模块的 Nginx

```bash
sudo apt install nginx-full
```

#### 2. 配置

```bash
sudo cp config/nginx.conf /etc/nginx/nginx.conf
```

#### 3. 启动

```bash
sudo nginx -t
sudo systemctl restart nginx
```

### LVS 负载均衡

```bash
# 安装 ipvsadm
sudo apt install ipvsadm

# 配置 LVS
sudo ipvsadm -A -t 192.168.1.100:9500 -s wrr
sudo ipvsadm -a -t 192.168.1.100:9500 -r 192.168.1.101:9501 -g -w 1
sudo ipvsadm -a -t 192.168.1.100:9500 -r 192.168.1.102:9501 -g -w 1
```

## 监控

### Prometheus + Grafana

#### 1. 暴露指标

在服务器上添加 HTTP 端点：

```php
// metrics.php
require_once 'vendor/autoload.php';

use PfinalClub\AsyncioHeartbeat\Utils\Metrics;

header('Content-Type: text/plain');
echo Metrics::getInstance()->export();
```

#### 2. Prometheus 配置

```yaml
scrape_configs:
  - job_name: 'heartbeat'
    static_configs:
      - targets: ['192.168.1.101:9502', '192.168.1.102:9502']
```

#### 3. Grafana Dashboard

导入监控面板，监控以下指标：

- 在线节点数
- 活跃连接数
- 消息吞吐量
- 心跳延迟
- 系统资源使用

## 备份与恢复

### 数据备份

如果使用持久化存储（Redis/MySQL），定期备份：

```bash
# 备份脚本
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR=/backup/heartbeat

# 备份配置
tar -czf $BACKUP_DIR/config_$DATE.tar.gz /opt/pfinal-asyncio-heartbeat/config

# 备份日志
tar -czf $BACKUP_DIR/logs_$DATE.tar.gz /var/log/heartbeat

# 清理旧备份（保留 7 天）
find $BACKUP_DIR -name "*.tar.gz" -mtime +7 -delete
```

## 性能调优

### PHP 配置

编辑 `php.ini`:

```ini
memory_limit = 1024M
max_execution_time = 0
```

### Workerman 配置

```php
$config = [
    'worker_count' => 8,              // CPU 核心数
    'heartbeat_timeout' => 60,        // 增加超时时间
    'heartbeat_check_interval' => 20, // 降低检查频率
    'max_connections' => 1000000,
];
```

### 系统参数

```bash
# 增加连接队列
sudo sysctl -w net.core.somaxconn=65535

# TCP 快速回收
sudo sysctl -w net.ipv4.tcp_tw_reuse=1

# 增加端口范围
sudo sysctl -w net.ipv4.ip_local_port_range="1024 65535"
```

## 安全加固

### 1. 防火墙

```bash
# UFW
sudo ufw allow 9501/tcp
sudo ufw enable

# iptables
sudo iptables -A INPUT -p tcp --dport 9501 -j ACCEPT
```

### 2. 连接限制

在服务器代码中添加：

```php
$config = [
    'max_connections_per_ip' => 1000,
    'blacklist' => ['1.2.3.4'],
];
```

### 3. TLS/SSL

使用 stunnel 或 Nginx stream SSL：

```nginx
stream {
    server {
        listen 9501 ssl;
        ssl_certificate /path/to/cert.pem;
        ssl_certificate_key /path/to/key.pem;
        proxy_pass 127.0.0.1:9502;
    }
}
```

## 故障排查

### 查看连接数

```bash
ss -an | grep 9501 | wc -l
```

### 查看进程

```bash
ps aux | grep heartbeat
```

### 查看端口

```bash
netstat -tlnp | grep 9501
```

### 查看日志

```bash
tail -f /var/log/heartbeat/server.log
```

### 内存使用

```bash
free -h
```

### CPU 使用

```bash
top -c
```

## 平滑重启

### Workerman 平滑重启

```bash
php examples/server.php reload
```

### 不中断服务的部署

```bash
# 1. 更新代码
git pull

# 2. 安装依赖
composer install --no-dev

# 3. 平滑重启
php examples/server.php reload
```

## 常见问题

### Q: 连接数达不到百万？

A: 检查系统限制：

```bash
ulimit -n
cat /proc/sys/fs/file-max
```

### Q: 性能不够？

A: 
1. 安装 Ev 扩展
2. 增加 Worker 数量
3. 使用多机部署

### Q: 内存占用过高？

A:
1. 检查是否有内存泄漏
2. 定期重启 Worker
3. 优化队列大小

---

**文档版本**: 1.0  
**更新时间**: 2025-01-27

