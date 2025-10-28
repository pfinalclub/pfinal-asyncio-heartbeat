# 📘 升级指南

从旧版本升级到最新版本的指南。

## 🔄 主要变更

### 1. Channel 方法返回值变更

**影响范围**: 直接使用 `Channel` 类的代码

#### `send()` 方法
```php
// 旧版本 - 抛出异常
$channel->send($data);

// 新版本 - 返回布尔值
if (!$channel->send($data)) {
    // 处理发送失败（队列满）
    echo "Channel queue is full\n";
}
```

#### `putRecv()` 方法
```php
// 旧版本 - 抛出异常
$channel->putRecv($data);

// 新版本 - 返回布尔值
if (!$channel->putRecv($data)) {
    // 处理失败
}
```

#### 新增方法
```php
// 尝试发送，队列满时自动丢弃最旧消息
$channel->trySend($data, dropOldest: true);

// 尝试接收，队列满时自动丢弃最旧消息
$channel->tryPutRecv($data, dropOldest: true);
```

---

### 2. 客户端方法返回值变更

**影响范围**: 使用 `HeartbeatClient` 的代码

#### `send()` 方法
```php
// 旧版本 - 抛出异常
try {
    $client->send($data);
} catch (\RuntimeException $e) {
    echo "Failed: {$e->getMessage()}\n";
}

// 新版本 - 返回布尔值
if (!$client->send($data)) {
    echo "Failed to send data\n";
}
```

#### `ping()` 方法
```php
// 旧版本 - void
$client->ping();

// 新版本 - 返回布尔值
if ($client->ping()) {
    echo "Ping sent successfully\n";
}
```

#### 新增回调事件
```php
// 监听服务器错误
$client->on('server_error', function($error) {
    echo "Server error: {$error['message']} (code: {$error['code']})\n";
});
```

---

### 3. 服务器配置新增项

**影响范围**: 服务器启动配置

```php
$server = new HeartbeatServer('0.0.0.0', 9501, [
    // 原有配置
    'worker_count' => 8,
    'heartbeat_timeout' => 60,
    'heartbeat_check_interval' => 20,
    'max_connections' => 1000000,
    
    // 新增配置（可选，有默认值）
    'enable_rate_limit' => true,          // 是否启用限流（默认 true）
    'rate_limit_capacity' => 100,         // 令牌桶容量（默认 100）
    'rate_limit_refill_rate' => 10.0,     // 每秒补充令牌数（默认 10.0）
]);
```

---

### 4. 配置验证

**影响范围**: 所有服务器实例

服务器启动时会自动验证配置，如果配置不正确会抛出异常：

```php
// 错误的配置会在启动时被捕获
try {
    $server = new HeartbeatServer('0.0.0.0', 9501, [
        'worker_count' => -1,  // ❌ 错误：必须 >= 1
    ]);
} catch (\RuntimeException $e) {
    echo "Configuration error: {$e->getMessage()}\n";
}
```

**验证规则**:
- `worker_count`: 1-128
- `heartbeat_timeout`: >= 1 秒
- `heartbeat_check_interval`: >= 1 秒且小于 `heartbeat_timeout`
- `max_connections`: 必须是正整数
- `host`: 有效的 IP 地址或域名
- `port`: 1-65535

---

## 📦 新功能

### 1. 限流器

防止恶意客户端过载服务器：

```php
// 限流器会自动应用（如果 enable_rate_limit = true）
// 超过限流的请求会收到 429 错误响应
```

**监控指标**: `heartbeat_rate_limited_total`

### 2. 配置验证器

获取推荐配置：

```php
use PfinalClub\AsyncioHeartbeat\Utils\ConfigValidator;

$recommended = ConfigValidator::getRecommendedServerConfig();
// 返回：
// [
//     'worker_count' => CPU核心数,
//     'heartbeat_timeout' => 60,
//     'heartbeat_check_interval' => 20,
//     'max_connections' => 1000000,
//     'channel_max_queue_size' => 1000,
// ]
```

### 3. 更好的错误处理

服务器现在会向客户端发送错误消息：

**客户端接收**:
```php
$client->on('server_error', function($error) {
    // $error = ['message' => '...', 'code' => 500]
    echo "Error from server: {$error['message']}\n";
});
```

**常见错误码**:
- `429`: Rate limit exceeded（超过限流）
- `500`: Internal server error（服务器内部错误）
- `503`: Service unavailable（服务不可用，如连接池满）

---

## 🔍 兼容性

### 向后兼容

✅ **大部分代码无需修改**

如果您的代码没有：
1. 直接使用 `Channel` 类
2. 捕获 `send()` 或 `ping()` 的返回值

那么您的代码应该可以无缝升级。

### 需要修改的场景

❌ **以下代码需要修改**

#### 场景 1: 直接使用 Channel
```php
// 旧代码
try {
    $channel->send($data);
} catch (\RuntimeException $e) {
    // 处理错误
}

// 新代码
if (!$channel->send($data)) {
    // 处理错误
}
```

#### 场景 2: 假设 send()/ping() 总是成功
```php
// 旧代码
$client->send($data);
// 假设总是成功

// 新代码
if (!$client->send($data)) {
    // 处理失败情况
}
```

---

## 📊 性能影响

### 正面影响 ✅

1. **CPU 使用率降低 85-90%**（Channel 优化）
2. **更好的限流保护**（防止过载）
3. **更优雅的错误处理**（不会因为队列满而崩溃）

### 轻微影响 ⚠️

1. **响应延迟增加约 10ms**（Channel 轮询间隔从 1ms 到 10ms）
   - 对于心跳场景，10ms 的延迟完全可以接受
   - 换来的是 CPU 使用率大幅降低

---

## 🚀 升级步骤

### 1. 更新代码

```bash
composer update pfinalclub/pfinal-asyncio-heartbeat
```

### 2. 修改配置（可选）

如果需要自定义限流配置：

```php
$config = [
    // ... 原有配置
    'enable_rate_limit' => true,
    'rate_limit_capacity' => 100,
    'rate_limit_refill_rate' => 10.0,
];
```

### 3. 更新代码（如有需要）

根据上面的"需要修改的场景"检查并更新代码。

### 4. 测试

```bash
# 运行单元测试
composer test

# 运行压力测试
php examples/stress_test.php
```

### 5. 监控

关注新增的监控指标：
- `heartbeat_rate_limited_total`: 被限流的请求数
- `heartbeat_connection_rejected_total`: 被拒绝的连接数

---

## ❓ 常见问题

### Q1: 升级后 CPU 使用率反而增加了？

**A**: 检查是否有大量的 Channel 接收操作在等待。新版本使用 10ms 轮询间隔，如果有大量等待可能会累积。考虑使用 `recvAsync()` 代替 `recv()`。

### Q2: 限流器太严格，正常请求被拒绝了？

**A**: 调整限流配置：
```php
'rate_limit_capacity' => 200,      // 增加容量
'rate_limit_refill_rate' => 20.0,  // 增加补充速率
```

### Q3: 想关闭限流功能？

**A**: 设置配置：
```php
'enable_rate_limit' => false,
```

### Q4: 如何处理队列满的情况？

**A**: 使用新的 `trySend()` 方法：
```php
// 自动丢弃最旧消息
$channel->trySend($data, dropOldest: true);
```

---

## 📞 获取帮助

如果遇到升级问题：

1. 查看 [FIXES.md](FIXES.md) 了解详细修复内容
2. 查看 [FAQ.md](docs/FAQ.md) 常见问题
3. 提交 Issue: https://github.com/pfinalclub/pfinal-asyncio-heartbeat/issues
4. 邮件: pfinalclub@gmail.com

---

**祝升级顺利！** 🎉

