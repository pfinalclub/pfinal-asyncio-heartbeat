# 常见问题 (FAQ)

## 安装和配置

### Q1: 需要哪个 PHP 版本？

**A:** 需要 PHP 8.1 或更高版本，因为项目使用了 Fiber 特性。

```bash
php -v
# PHP 8.1.0 or higher
```

### Q2: 必须安装 Ev 或 Event 扩展吗？

**A:** 不是必须的，但强烈推荐。

- **不安装**: 使用 Select 事件循环（基准性能）
- **安装 Event**: 性能提升 4 倍
- **安装 Ev**: 性能提升 10 倍

安装方法：

```bash
# 推荐：Ev 扩展
sudo pecl install ev

# 或者：Event 扩展
sudo pecl install event
```

### Q3: 如何确认扩展已安装？

**A:**

```bash
php -m | grep -E "(ev|event)"
```

### Q4: Windows 系统可以运行吗？

**A:** 可以开发测试，但不推荐生产环境使用。Workerman 在 Windows 上的性能和稳定性不如 Linux。

## 性能相关

### Q5: 能支持多少并发连接？

**A:** 理论上可以支持百万级并发，实际取决于：

- **硬件配置**: CPU、内存、网络
- **系统配置**: 文件描述符限制、TCP 参数
- **应用配置**: Worker 数量、心跳间隔

单机 8核16G 可稳定支持 10-20 万并发连接。

### Q6: 如何提升性能？

**A:** 按优先级排序：

1. **安装 Ev 扩展** (最重要)
2. **系统调优** (ulimit, sysctl)
3. **增加 Worker 数量** (= CPU 核心数)
4. **多机部署** (负载均衡)
5. **降低心跳频率** (30-60秒)

### Q7: 内存占用多少？

**A:** 
- 基础内存: ~50-100MB
- 每个连接: ~1-2KB
- 10万连接: ~200-300MB
- 100万连接: ~2-3GB

### Q8: CPU 使用率多少正常？

**A:**
- 空闲: < 5%
- 10万连接: 20-40%
- 100万连接: 60-80%

如果 CPU 使用率过高，检查：
- 是否安装了 Ev/Event 扩展
- 心跳频率是否过高
- 是否有死循环

## 连接和心跳

### Q9: 客户端如何自动重连？

**A:** 客户端默认开启自动重连：

```php
$client = new HeartbeatClient('127.0.0.1', 9501, [
    'auto_reconnect' => true,
    'reconnect_interval' => 5,  // 5秒后重连
    'max_reconnect_attempts' => 0,  // 0 = 无限重连
]);
```

### Q10: 心跳间隔设置多少合适？

**A:** 根据场景调整：

- **实时性要求高**: 5-10秒
- **一般场景**: 10-30秒
- **节省带宽**: 30-60秒

超时时间建议设置为心跳间隔的 3 倍。

### Q11: 客户端断开后节点会立即清理吗？

**A:** 不会立即清理，会等待：

1. 心跳超时检测（默认 30秒）
2. 标记为离线
3. 触发超时回调
4. 清理资源

### Q12: 如何检测客户端真实在线状态？

**A:**

```php
$node = $nodeManager->getNode($nodeId);

// 检查是否在线
if ($node && $node->isOnline()) {
    // 在线
}

// 检查心跳是否超时
if ($node && !$node->isTimeout(30)) {
    // 未超时
}
```

## 开发调试

### Q13: 如何启用调试日志？

**A:**

```php
use PfinalClub\AsyncioHeartbeat\Utils\Logger;

$logger = Logger::getInstance();
$logger->setLevel(Logger::LEVEL_DEBUG);
$logger->setLogFile('/tmp/heartbeat_debug.log');
```

### Q14: 如何查看实时统计？

**A:**

```php
// 服务端
$stats = $server->getStats();
print_r($stats);

// 客户端
$stats = $client->getStats();
print_r($stats);
```

### Q15: 如何测试单个客户端？

**A:**

```bash
php examples/client.php
```

### Q16: 如何进行压力测试？

**A:**

```bash
# 测试 10000 个并发连接
php examples/stress_test.php 10000
```

## 部署运维

### Q17: 生产环境如何部署？

**A:** 参考 [生产部署指南](PRODUCTION.md)，主要步骤：

1. 安装 PHP 8.1+ 和 Ev 扩展
2. 系统参数优化
3. 使用 Supervisor 或 Systemd 管理进程
4. 配置监控告警
5. 多机部署 + 负载均衡

### Q18: 如何平滑重启？

**A:**

```bash
# Workerman 支持平滑重启
php examples/server.php reload

# Supervisor
sudo supervisorctl restart heartbeat-server:*
```

### Q19: 如何监控服务状态？

**A:** 使用 Prometheus + Grafana：

```php
// 暴露指标
use PfinalClub\AsyncioHeartbeat\Utils\Metrics;
echo Metrics::getInstance()->export();
```

主要监控指标：
- `heartbeat_nodes_online`
- `heartbeat_connections_active`
- `heartbeat_messages_total`
- `heartbeat_latency_seconds`

### Q20: 如何备份和恢复？

**A:** 心跳服务是无状态的，无需特殊备份。如果有持久化需求：

```bash
# 备份配置
tar -czf config_backup.tar.gz config/

# 备份日志
tar -czf logs_backup.tar.gz /var/log/heartbeat/
```

## 错误处理

### Q21: "Too many open files" 错误

**A:** 增加文件描述符限制：

```bash
ulimit -n 1000000

# 永久生效
echo "* soft nofile 1000000" | sudo tee -a /etc/security/limits.conf
echo "* hard nofile 1000000" | sudo tee -a /etc/security/limits.conf
```

### Q22: "Address already in use" 错误

**A:** 端口被占用：

```bash
# 查看占用端口的进程
sudo lsof -i :9501

# 或者
sudo netstat -tlnp | grep 9501

# 停止进程或更换端口
```

### Q23: 连接被拒绝

**A:** 检查：

1. 服务是否启动
2. 端口是否正确
3. 防火墙是否开放
4. IP 地址是否正确

```bash
# 检查端口
telnet 127.0.0.1 9501

# 检查防火墙
sudo ufw status
```

### Q24: 内存泄漏

**A:** 

1. 检查是否正确关闭通道和节点
2. 定期清理离线节点
3. 设置合理的队列大小
4. 考虑定期重启 Worker

```php
// 清理离线节点
$nodeManager->cleanupOffline();

// 清理已关闭通道
$channelScheduler->cleanup();
```

## 高级功能

### Q25: 如何实现自定义消息类型？

**A:**

```php
use PfinalClub\AsyncioHeartbeat\Protocol\Message;

// 定义新类型
const TYPE_CUSTOM = 0x10;

// 创建消息
$message = new Message(TYPE_CUSTOM, json_encode(['data' => 'value']));

// 发送
$connection->send($message->encode());
```

### Q26: 如何实现消息加密？

**A:**

```php
// 发送前加密
$encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
$client->send($encrypted);

// 接收后解密
$client->on('data', function($data) use ($key, $iv) {
    $decrypted = openssl_decrypt($data, 'AES-256-CBC', $key, 0, $iv);
    // 处理解密后的数据
});
```

### Q27: 如何实现分组/房间功能？

**A:**

```php
// 使用元数据标记分组
$node->setMetadata('group', 'room_1');

// 发送到特定分组
foreach ($nodeManager->getAllNodes() as $node) {
    if ($node->getMetadata('group') === 'room_1') {
        // 发送消息
    }
}
```

### Q28: 如何限制单 IP 连接数？

**A:**

在 `HeartbeatServer` 中添加：

```php
private array $ipConnections = [];

public function onConnect(TcpConnection $connection) {
    $ip = $connection->getRemoteIp();
    
    if (!isset($this->ipConnections[$ip])) {
        $this->ipConnections[$ip] = 0;
    }
    
    $this->ipConnections[$ip]++;
    
    if ($this->ipConnections[$ip] > $this->config['max_connections_per_ip']) {
        $connection->close();
        return;
    }
    
    // ... 继续处理
}
```

## 社区支持

### Q29: 在哪里报告 Bug？

**A:** GitHub Issues: https://github.com/pfinalclub/pfinal-asyncio-heartbeat/issues

### Q30: 如何贡献代码？

**A:** 

1. Fork 项目
2. 创建 Feature 分支
3. 提交 PR
4. 等待审核

---

**文档版本**: 1.0  
**更新时间**: 2025-01-27

如果您的问题未在此列出，欢迎在 GitHub Issues 提问！

