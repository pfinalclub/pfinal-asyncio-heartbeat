# API 文档

## 服务端 API

### HeartbeatServer

#### 构造函数

```php
public function __construct(
    string $host = '0.0.0.0',
    int $port = 9501,
    array $config = []
)
```

**参数**:
- `$host`: 监听地址
- `$port`: 监听端口
- `$config`: 配置数组
  - `worker_count`: Worker 进程数（默认: 4）
  - `heartbeat_timeout`: 心跳超时时间（默认: 30秒）
  - `heartbeat_check_interval`: 心跳检查间隔（默认: 10秒）
  - `max_connections`: 最大连接数（默认: 1000000）

**示例**:

```php
$server = new HeartbeatServer('0.0.0.0', 9501, [
    'worker_count' => 8,
    'heartbeat_timeout' => 30,
]);
```

#### start()

启动服务器。

```php
public function start(): void
```

**示例**:

```php
$server->start();
```

#### getStats()

获取服务器统计信息。

```php
public function getStats(): array
```

**返回**:

```php
[
    'server' => [
        'host' => '0.0.0.0',
        'port' => 9501,
        'workers' => 8,
    ],
    'nodes' => [
        'total' => 1000,
        'online' => 950,
        'offline' => 50,
    ],
    'channels' => [
        'active_channels' => 1000,
        'active_nodes' => 950,
    ],
    'connections' => [
        'active_connections' => 1000,
        'max_connections' => 1000000,
    ],
]
```

## 客户端 API

### HeartbeatClient

#### 构造函数

```php
public function __construct(
    string $host = '127.0.0.1',
    int $port = 9501,
    array $config = []
)
```

**参数**:
- `$host`: 服务器地址
- `$port`: 服务器端口
- `$config`: 配置数组
  - `heartbeat_interval`: 心跳间隔（默认: 10秒）
  - `auto_reconnect`: 自动重连（默认: true）
  - `reconnect_interval`: 重连间隔（默认: 5秒）
  - `node_id`: 节点 ID（可选）
  - `metadata`: 元数据（可选）

**示例**:

```php
$client = new HeartbeatClient('127.0.0.1', 9501, [
    'heartbeat_interval' => 10,
    'auto_reconnect' => true,
    'metadata' => ['version' => '1.0'],
]);
```

#### connect()

连接到服务器。

```php
public function connect(): void
```

**示例**:

```php
$client->connect();
```

#### disconnect()

断开连接。

```php
public function disconnect(): void
```

**示例**:

```php
$client->disconnect();
```

#### send()

发送数据。

```php
public function send(string $data, int $channelId = 0): void
```

**参数**:
- `$data`: 要发送的数据
- `$channelId`: 通道 ID（可选）

**示例**:

```php
$client->send('Hello Server');
```

#### ping()

发送 Ping。

```php
public function ping(): void
```

**示例**:

```php
$client->ping();
```

#### on()

注册事件回调。

```php
public function on(string $event, callable $callback): void
```

**支持的事件**:
- `connect`: 连接成功
- `register`: 注册成功
- `heartbeat`: 收到心跳响应
- `data`: 收到数据
- `close`: 连接关闭
- `error`: 发生错误
- `pong`: 收到 Pong

**示例**:

```php
$client->on('connect', function() {
    echo "Connected!\n";
});

$client->on('register', function($nodeId, $channelId) {
    echo "Registered: {$nodeId}, Channel: {$channelId}\n";
});

$client->on('data', function($data, $channelId) {
    echo "Received: {$data}\n";
});
```

#### isConnected()

检查是否已连接。

```php
public function isConnected(): bool
```

**示例**:

```php
if ($client->isConnected()) {
    $client->send('test');
}
```

#### getStats()

获取客户端统计信息。

```php
public function getStats(): array
```

**返回**:

```php
[
    'connected' => true,
    'node_id' => 'client_abc123',
    'channel_id' => 1,
    'messages_sent' => 100,
    'messages_received' => 95,
    'uptime' => 120.5,
]
```

## 协议 API

### Message

#### 构造函数

```php
public function __construct(
    int $type,
    string $payload = '',
    int $channelId = 0
)
```

#### encode()

编码消息为二进制。

```php
public function encode(): string
```

#### decode()

从二进制解码消息。

```php
public static function decode(string $buffer): ?self
```

#### 工厂方法

```php
// 创建心跳请求
Message::createHeartbeatRequest(): Message

// 创建心跳响应
Message::createHeartbeatResponse(): Message

// 创建注册消息
Message::createRegister(string $nodeId, array $metadata = []): Message

// 创建数据消息
Message::createData(string $payload, int $channelId = 0): Message

// 创建错误消息
Message::createError(string $message, int $code = 0): Message
```

## 通道 API

### Channel

#### send()

发送消息到通道。

```php
public function send(string $data): void
```

#### recv()

从通道接收消息（同步）。

```php
public function recv(float $timeout = 0): mixed
```

#### close()

关闭通道。

```php
public function close(): void
```

### ChannelScheduler

#### createChannel()

创建通道。

```php
public function createChannel(string $nodeId, int $maxQueueSize = 1000): Channel
```

#### getChannel()

获取通道。

```php
public function getChannel(int $channelId): ?Channel
```

#### closeChannel()

关闭通道。

```php
public function closeChannel(int $channelId): void
```

## 节点管理 API

### NodeManager

#### register()

注册节点。

```php
public function register(Node $node): void
```

#### unregister()

注销节点。

```php
public function unregister(string $nodeId): ?Node
```

#### getNode()

获取节点。

```php
public function getNode(string $nodeId): ?Node
```

#### updateHeartbeat()

更新节点心跳。

```php
public function updateHeartbeat(string $nodeId): bool
```

#### getOnlineNodes()

获取所有在线节点。

```php
public function getOnlineNodes(): array
```

## 辅助函数

### 日志函数

```php
// 记录日志
hb_log(string $message, string $level = 'info', array $context = []): void
```

### 指标函数

```php
// 增加计数器
hb_metric_inc(string $name, int $value = 1, array $labels = []): void

// 设置仪表
hb_metric_set(string $name, float $value, array $labels = []): void

// 记录直方图
hb_metric_observe(string $name, float $value, array $labels = []): void
```

### 工具函数

```php
// 格式化字节数
hb_format_bytes(int $bytes, int $precision = 2): string

// 格式化时间长度
hb_format_duration(float $seconds): string

// 获取内存使用情况
hb_memory_usage(): array

// 生成节点 ID
hb_generate_node_id(string $prefix = 'node'): string

// 基准测试
hb_benchmark(callable $callback, int $iterations = 1): array
```

## 完整示例

### 服务端

```php
<?php
require_once 'vendor/autoload.php';

use PfinalClub\AsyncioHeartbeat\Server\HeartbeatServer;

$server = new HeartbeatServer('0.0.0.0', 9501, [
    'worker_count' => 8,
    'heartbeat_timeout' => 30,
    'heartbeat_check_interval' => 10,
]);

$server->start();
```

### 客户端

```php
<?php
require_once 'vendor/autoload.php';

use PfinalClub\AsyncioHeartbeat\Client\HeartbeatClient;
use Workerman\Worker;

$client = new HeartbeatClient('127.0.0.1', 9501);

$client->on('connect', function() {
    echo "Connected!\n";
});

$client->on('register', function($nodeId, $channelId) {
    echo "Registered: {$nodeId}\n";
});

$client->on('data', function($data) {
    echo "Received: {$data}\n";
});

$client->connect();

Worker::runAll();
```

---

**文档版本**: 1.0  
**更新时间**: 2025-01-27

