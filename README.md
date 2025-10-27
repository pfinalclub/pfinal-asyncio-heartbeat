# 🚀 PFinal Asyncio Heartbeat

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-blue.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

百万节点心跳总线 - 基于 [pfinal-asyncio](https://github.com/pfinalclub/pfinal-asyncio) 的高性能 TCP multiplex + channel 调度系统

## ✨ 核心特性

- ✅ **百万级并发** - 支持百万节点同时在线
- ✅ **TCP Multiplexing** - 单连接多通道复用，节省资源
- ✅ **Channel 调度** - 高效的消息路由系统
- ✅ **自动心跳检测** - 实时监控节点状态
- ✅ **多进程架构** - 充分利用多核 CPU
- ✅ **自动重连** - 客户端断线自动重连
- ✅ **高性能协议** - 二进制协议，零拷贝
- ✅ **生产就绪** - 完整的监控、日志、部署方案

## 📋 系统要求

- PHP >= 8.1 (需要 Fiber 支持)
- Workerman >= 4.0
- pfinal-asyncio >= 2.0
- Linux/Unix 系统（推荐）

## 📦 安装

```bash
composer require pfinalclub/pfinal-asyncio-heartbeat
```

## 🚀 快速开始

### 启动服务端

```php
<?php
require_once 'vendor/autoload.php';

use PfinalClub\AsyncioHeartbeat\Server\HeartbeatServer;

$server = new HeartbeatServer('0.0.0.0', 9501, [
    'worker_count' => 8,              // 8 进程（充分利用多核）
    'heartbeat_timeout' => 30,        // 30秒心跳超时
    'heartbeat_check_interval' => 10, // 10秒检查一次
]);

$server->start();
```

### 启动客户端

```php
<?php
require_once 'vendor/autoload.php';

use PfinalClub\AsyncioHeartbeat\Client\HeartbeatClient;
use Workerman\Worker;

$client = new HeartbeatClient('127.0.0.1', 9501, [
    'heartbeat_interval' => 10,
    'auto_reconnect' => true,
]);

// 注册数据接收回调
$client->on('data', function($data) {
    echo "收到数据: $data\n";
});

// 连接服务器
$client->connect();

Worker::runAll();
```

## 📊 性能基准

**测试环境**: 
- CPU: 8核 Intel Xeon
- 内存: 16GB
- OS: Ubuntu 22.04
- PHP: 8.1 + Ev 扩展

**测试结果**:
- ✅ **100,000 并发连接**: 稳定运行
- ✅ **内存占用**: ~2GB
- ✅ **CPU 使用率**: 40-60%
- ✅ **心跳延迟**: < 10ms
- ✅ **消息吞吐**: 50,000+ msg/s

运行压力测试：

```bash
php examples/stress_test.php
```

## 🏗️ 架构设计

```
┌─────────────────────────────────────────────────────────┐
│                    HeartbeatServer                      │
│  ┌───────────┐  ┌───────────┐  ┌───────────┐          │
│  │ Worker 1  │  │ Worker 2  │  │ Worker N  │          │
│  └─────┬─────┘  └─────┬─────┘  └─────┬─────┘          │
└────────┼──────────────┼──────────────┼─────────────────┘
         │              │              │
         └──────────────┴──────────────┘
                        │
         ┌──────────────┴──────────────┐
         │     TCP Multiplexing        │
         │   ┌──────────────────┐      │
         │   │ ChannelScheduler │      │
         │   └────────┬─────────┘      │
         │   ┌────────┴─────────┐      │
         │   │ Channel 1 ... N  │      │
         │   └──────────────────┘      │
         └──────────────┬──────────────┘
                        │
         ┌──────────────┴──────────────┐
         │      NodeManager            │
         │   ┌──────────────────┐      │
         │   │ Node 1 ... Node N│      │
         │   └──────────────────┘      │
         └─────────────────────────────┘
```

## 📚 文档

- [架构设计](docs/ARCHITECTURE.md) - 系统架构详解
- [API 文档](docs/API.md) - 完整的 API 使用指南
- [性能测试](docs/PERFORMANCE.md) - 性能测试报告
- [生产部署](docs/PRODUCTION.md) - 生产环境部署指南
- [监控告警](docs/MONITORING.md) - 监控和告警配置
- [常见问题](docs/FAQ.md) - FAQ

## 🔧 配置

### 服务端配置

```php
$config = [
    // 进程数（建议设置为 CPU 核心数）
    'worker_count' => 8,
    
    // 心跳超时时间（秒）
    'heartbeat_timeout' => 30,
    
    // 心跳检查间隔（秒）
    'heartbeat_check_interval' => 10,
    
    // 每个通道的最大队列大小
    'channel_max_queue_size' => 1000,
    
    // 连接池最大连接数
    'max_connections' => 1000000,
];
```

### 客户端配置

```php
$config = [
    // 心跳间隔（秒）
    'heartbeat_interval' => 10,
    
    // 自动重连
    'auto_reconnect' => true,
    
    // 重连间隔（秒）
    'reconnect_interval' => 5,
];
```

## 🧪 测试

运行单元测试：

```bash
composer test
```

运行测试并生成覆盖率报告：

```bash
composer test-coverage
```

运行代码静态分析：

```bash
composer analyse
```

## 🚀 生产部署

### 1. 安装高性能事件循环扩展

```bash
# 推荐：安装 Ev 扩展（性能提升 10 倍）
pecl install ev

# 或者安装 Event 扩展（性能提升 4 倍）
pecl install event
```

### 2. 系统参数优化

```bash
# 增加文件描述符限制
ulimit -n 1000000

# 运行优化脚本
bash scripts/optimize_system.sh
```

### 3. 使用 Supervisor 守护进程

```bash
# 复制配置文件
cp config/supervisor.conf /etc/supervisor/conf.d/heartbeat-server.conf

# 重启 supervisor
supervisorctl reread
supervisorctl update
supervisorctl start heartbeat-server:*
```

### 4. 使用 Nginx 负载均衡（可选）

```bash
# 复制 Nginx 配置
cp config/nginx.conf /etc/nginx/sites-available/heartbeat-lb.conf
ln -s /etc/nginx/sites-available/heartbeat-lb.conf /etc/nginx/sites-enabled/

# 重启 Nginx
nginx -t && nginx -s reload
```

详细部署指南请参考 [PRODUCTION.md](docs/PRODUCTION.md)

## 📈 监控

系统提供 Prometheus 格式的监控指标：

```php
use PfinalClub\AsyncioHeartbeat\Utils\Metrics;

$metrics = Metrics::getInstance();

// 导出 Prometheus 格式指标
echo $metrics->export();
```

主要监控指标：

- `heartbeat_nodes_total` - 总节点数
- `heartbeat_nodes_online` - 在线节点数
- `heartbeat_channels_total` - 总通道数
- `heartbeat_messages_total` - 消息总数
- `heartbeat_latency_seconds` - 心跳延迟

## 🤝 贡献

欢迎提交 Issue 和 Pull Request！

## 📄 许可证

本项目采用 [MIT](LICENSE) 许可证。

## 🔗 相关链接

- [pfinal-asyncio](https://github.com/pfinalclub/pfinal-asyncio) - 核心异步框架
- [Workerman](https://www.workerman.net/) - 高性能 PHP Socket 框架

## 📞 联系我们

- Email: pfinalclub@gmail.com
- GitHub: [@pfinalclub](https://github.com/pfinalclub)

---

**版本**: 1.0.0  
**更新日期**: 2025-01-27  
**PHP 要求**: >= 8.1

