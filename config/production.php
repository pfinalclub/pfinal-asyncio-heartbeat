<?php

/**
 * 生产环境配置
 */

return [
    // 服务器配置
    'server' => [
        'host' => '0.0.0.0',
        'port' => 9501,
        'protocol' => 'tcp',
        'worker_count' => 8, // 根据 CPU 核心数调整
    ],
    
    // 心跳配置
    'heartbeat' => [
        'timeout' => 60,        // 生产环境可以设置更长的超时
        'check_interval' => 20, // 降低检查频率
        'client_interval' => 30, // 降低客户端心跳频率
    ],
    
    // 连接配置
    'connection' => [
        'max_connections' => 1000000,
        'reuse_port' => true,
    ],
    
    // 通道配置
    'channel' => [
        'max_queue_size' => 2000, // 增加队列大小
    ],
    
    // 日志配置
    'log' => [
        'enabled' => true,
        'level' => 'warning', // 生产环境只记录警告和错误
        'file' => '/var/log/heartbeat/server.log',
    ],
    
    // 监控配置
    'monitoring' => [
        'enabled' => true,
        'stats_interval' => 300, // 5分钟
        'metrics_port' => 9502,
    ],
    
    // 性能优化
    'performance' => [
        'event_loop' => 'Ev', // 使用 Ev 扩展（性能最好）
        'process_priority' => -10, // 提高进程优先级
        'cpu_affinity' => true, // 启用 CPU 亲和性
    ],
    
    // 安全配置
    'security' => [
        'max_requests_per_second' => 10000,
        'max_connections_per_ip' => 1000,
        'blacklist' => [],
        'whitelist' => [],
    ],
];

