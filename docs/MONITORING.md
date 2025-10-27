# 监控和告警指南

## Prometheus 监控

### 安装 Prometheus

```bash
# 下载 Prometheus
wget https://github.com/prometheus/prometheus/releases/download/v2.45.0/prometheus-2.45.0.linux-amd64.tar.gz
tar xvf prometheus-2.45.0.linux-amd64.tar.gz
cd prometheus-2.45.0.linux-amd64
```

### 配置 Prometheus

编辑 `prometheus.yml`:

```yaml
global:
  scrape_interval: 15s
  evaluation_interval: 15s

scrape_configs:
  - job_name: 'heartbeat'
    static_configs:
      - targets: ['192.168.1.101:9502', '192.168.1.102:9502']
        labels:
          group: 'production'
```

### 暴露指标

创建 `metrics.php`:

```php
<?php
require_once 'vendor/autoload.php';

use PfinalClub\AsyncioHeartbeat\Utils\Metrics;
use Workerman\Worker;

$worker = new Worker('http://0.0.0.0:9502');

$worker->onMessage = function($connection, $request) {
    $metrics = Metrics::getInstance();
    
    $connection->send($metrics->export());
};

Worker::runAll();
```

启动：

```bash
php metrics.php start -d
```

## 核心指标

### 连接指标

```promql
# 活跃连接数
heartbeat_connections_active

# 总连接数
heartbeat_connections_total

# 连接成功率
rate(heartbeat_connections_total[5m])
```

### 节点指标

```promql
# 在线节点数
heartbeat_nodes_online

# 离线节点数
heartbeat_nodes_offline

# 总节点数
heartbeat_nodes_total

# 超时节点数
rate(heartbeat_nodes_timeout_total[5m])
```

### 消息指标

```promql
# 消息总数
heartbeat_messages_total

# 消息速率
rate(heartbeat_messages_total[5m])

# 按类型分类的消息
heartbeat_messages_total{type="HEARTBEAT_REQ"}
```

### 通道指标

```promql
# 活跃通道数
heartbeat_channels_active

# 通道队列长度
heartbeat_channel_queue_size
```

### 性能指标

```promql
# 消息处理延迟 (P50)
histogram_quantile(0.5, heartbeat_message_duration_seconds)

# 消息处理延迟 (P95)
histogram_quantile(0.95, heartbeat_message_duration_seconds)

# 消息处理延迟 (P99)
histogram_quantile(0.99, heartbeat_message_duration_seconds)
```

### 系统指标

```promql
# 运行时间
heartbeat_uptime_seconds

# 错误总数
rate(heartbeat_errors_total[5m])
```

## Grafana 可视化

### 安装 Grafana

```bash
wget https://dl.grafana.com/oss/release/grafana-10.0.0.linux-amd64.tar.gz
tar -zxvf grafana-10.0.0.linux-amd64.tar.gz
cd grafana-10.0.0
./bin/grafana-server
```

### 配置数据源

1. 打开 Grafana (http://localhost:3000)
2. 添加 Prometheus 数据源
3. URL: http://localhost:9090

### Dashboard 面板

#### 1. 概览面板

```json
{
  "title": "Heartbeat Overview",
  "panels": [
    {
      "title": "Online Nodes",
      "targets": [
        {
          "expr": "heartbeat_nodes_online"
        }
      ]
    },
    {
      "title": "Active Connections",
      "targets": [
        {
          "expr": "heartbeat_connections_active"
        }
      ]
    },
    {
      "title": "Message Rate",
      "targets": [
        {
          "expr": "rate(heartbeat_messages_total[5m])"
        }
      ]
    }
  ]
}
```

#### 2. 性能面板

- 消息延迟 (P50, P95, P99)
- 消息吞吐量
- CPU 使用率
- 内存使用率

#### 3. 告警面板

- 离线节点
- 连接失败
- 高延迟
- 错误率

## 告警规则

### Prometheus 告警

创建 `alerts.yml`:

```yaml
groups:
  - name: heartbeat
    interval: 30s
    rules:
      # 在线节点数低于阈值
      - alert: LowOnlineNodes
        expr: heartbeat_nodes_online < 1000
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "在线节点数过低"
          description: "当前在线节点数: {{ $value }}"
      
      # 连接数超过阈值
      - alert: HighConnections
        expr: heartbeat_connections_active > 800000
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "连接数接近上限"
          description: "当前连接数: {{ $value }}"
      
      # 消息延迟过高
      - alert: HighLatency
        expr: histogram_quantile(0.95, heartbeat_message_duration_seconds) > 0.1
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "消息延迟过高"
          description: "P95 延迟: {{ $value }}s"
      
      # 错误率过高
      - alert: HighErrorRate
        expr: rate(heartbeat_errors_total[5m]) > 10
        for: 5m
        labels:
          severity: critical
        annotations:
          summary: "错误率过高"
          description: "错误率: {{ $value }}/s"
      
      # 服务宕机
      - alert: ServiceDown
        expr: up{job="heartbeat"} == 0
        for: 1m
        labels:
          severity: critical
        annotations:
          summary: "服务宕机"
          description: "{{ $labels.instance }} 无法访问"
```

### Alertmanager 配置

```yaml
global:
  resolve_timeout: 5m

route:
  group_by: ['alertname']
  group_wait: 10s
  group_interval: 10s
  repeat_interval: 1h
  receiver: 'default'

receivers:
  - name: 'default'
    email_configs:
      - to: 'admin@example.com'
        from: 'alertmanager@example.com'
        smarthost: 'smtp.example.com:587'
        auth_username: 'alertmanager@example.com'
        auth_password: 'password'
    
    webhook_configs:
      - url: 'http://example.com/webhook'
    
    slack_configs:
      - api_url: 'https://hooks.slack.com/services/xxx'
        channel: '#alerts'
```

## 日志监控

### 日志级别

```php
use PfinalClub\AsyncioHeartbeat\Utils\Logger;

$logger = Logger::getInstance();

// 生产环境只记录 WARNING 和 ERROR
$logger->setLevel(Logger::LEVEL_WARNING);

// 设置日志文件
$logger->setLogFile('/var/log/heartbeat/server.log');
```

### 日志格式

```
[2025-01-27 10:30:45] [INFO] Server started {"workers":8,"port":9501}
[2025-01-27 10:30:50] [INFO] Node registered {"node_id":"client_abc123","ip":"192.168.1.100"}
[2025-01-27 10:31:00] [WARNING] Node timeout {"node_id":"client_xyz789","last_heartbeat":35.2}
[2025-01-27 10:31:05] [ERROR] Failed to handle message {"error":"Invalid message format"}
```

### 日志收集

#### 使用 ELK Stack

```bash
# 安装 Filebeat
wget https://artifacts.elastic.co/downloads/beats/filebeat/filebeat-8.0.0-linux-x86_64.tar.gz
tar xvf filebeat-8.0.0-linux-x86_64.tar.gz
cd filebeat-8.0.0-linux-x86_64

# 配置 filebeat.yml
filebeat.inputs:
  - type: log
    enabled: true
    paths:
      - /var/log/heartbeat/*.log

output.elasticsearch:
  hosts: ["localhost:9200"]

# 启动
./filebeat -e
```

## 健康检查

### HTTP 健康检查端点

```php
// health.php
<?php
require_once 'vendor/autoload.php';

use Workerman\Worker;

$worker = new Worker('http://0.0.0.0:9503');

$worker->onMessage = function($connection, $request) {
    // 检查服务健康状态
    $healthy = true;
    $status = 200;
    $response = ['status' => 'ok'];
    
    // 检查内存
    $memory = memory_get_usage(true);
    if ($memory > 10 * 1024 * 1024 * 1024) { // 10GB
        $healthy = false;
        $response['memory'] = 'high';
    }
    
    // 检查连接数
    // ...
    
    if (!$healthy) {
        $status = 503;
        $response['status'] = 'unhealthy';
    }
    
    $connection->send(json_encode($response));
};

Worker::runAll();
```

### 监控脚本

```bash
#!/bin/bash

# health_check.sh

URL="http://localhost:9503"
TIMEOUT=5

response=$(curl -s -w "%{http_code}" -o /tmp/health_response.txt --connect-timeout $TIMEOUT $URL)

if [ "$response" = "200" ]; then
    echo "✅ Service is healthy"
    exit 0
else
    echo "❌ Service is unhealthy (HTTP $response)"
    cat /tmp/health_response.txt
    exit 1
fi
```

## 性能分析

### 使用 XHProf

```bash
# 安装 XHProf
pecl install xhprof

# 启用扩展
echo "extension=xhprof.so" >> /etc/php/8.1/cli/conf.d/20-xhprof.ini
```

```php
// 在代码中使用
xhprof_enable(XHPROF_FLAGS_CPU + XHPROF_FLAGS_MEMORY);

// 你的代码...

$data = xhprof_disable();

// 保存分析数据
file_put_contents('/tmp/xhprof.json', json_encode($data));
```

### 使用 BlackFire

```bash
# 安装 BlackFire
wget -O - https://packagecloud.io/gpg.key | sudo apt-key add -
echo "deb http://packages.blackfire.io/debian any main" | sudo tee /etc/apt/sources.list.d/blackfire.list
sudo apt-get update
sudo apt-get install blackfire-agent blackfire-php

# 配置
blackfire-agent --register
sudo /etc/init.d/blackfire-agent start

# 分析
blackfire run php examples/benchmark.php
```

## 监控最佳实践

### 1. 关键指标

必须监控的指标：

- ✅ 在线节点数
- ✅ 活跃连接数
- ✅ 消息延迟
- ✅ 错误率
- ✅ CPU/内存使用

### 2. 告警阈值

建议告警阈值：

| 指标 | Warning | Critical |
|-----|---------|----------|
| 在线节点数 | < 总数 * 0.9 | < 总数 * 0.7 |
| 连接数 | > 最大 * 0.8 | > 最大 * 0.9 |
| P95 延迟 | > 100ms | > 500ms |
| 错误率 | > 1% | > 5% |
| CPU | > 80% | > 95% |
| 内存 | > 80% | > 95% |

### 3. 监控频率

| 指标类型 | 采集频率 |
|---------|---------|
| 核心指标 | 15秒 |
| 性能指标 | 1分钟 |
| 系统指标 | 1分钟 |
| 日志 | 实时 |

---

**文档版本**: 1.0  
**更新时间**: 2025-01-27

