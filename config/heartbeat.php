<?php

/**
 * 心跳服务配置
 */

return [
    // 服务器配置
    'server' => [
        'host' => env('HEARTBEAT_HOST', '0.0.0.0'),
        'port' => env('HEARTBEAT_PORT', 9501),
        'protocol' => env('HEARTBEAT_PROTOCOL', 'tcp'),
        'worker_count' => env('HEARTBEAT_WORKERS', 4),
    ],
    
    // 心跳配置
    'heartbeat' => [
        'timeout' => env('HEARTBEAT_TIMEOUT', 30),
        'check_interval' => env('HEARTBEAT_CHECK_INTERVAL', 10),
        'client_interval' => env('HEARTBEAT_CLIENT_INTERVAL', 10),
    ],
    
    // 连接配置
    'connection' => [
        'max_connections' => env('HEARTBEAT_MAX_CONNECTIONS', 1000000),
        'reuse_port' => env('HEARTBEAT_REUSE_PORT', true),
    ],
    
    // 通道配置
    'channel' => [
        'max_queue_size' => env('HEARTBEAT_CHANNEL_QUEUE_SIZE', 1000),
    ],
    
    // 日志配置
    'log' => [
        'enabled' => env('HEARTBEAT_LOG_ENABLED', true),
        'level' => env('HEARTBEAT_LOG_LEVEL', 'info'), // debug, info, warning, error
        'file' => env('HEARTBEAT_LOG_FILE', null),
    ],
    
    // 监控配置
    'monitoring' => [
        'enabled' => env('HEARTBEAT_MONITORING_ENABLED', true),
        'stats_interval' => env('HEARTBEAT_STATS_INTERVAL', 60),
        'metrics_port' => env('HEARTBEAT_METRICS_PORT', 9502),
    ],
];

/**
 * 获取环境变量
 */
function env(string $key, mixed $default = null): mixed
{
    $value = getenv($key);
    
    if ($value === false) {
        return $default;
    }
    
    // 转换布尔值
    if (in_array(strtolower($value), ['true', 'false'])) {
        return strtolower($value) === 'true';
    }
    
    // 转换数字
    if (is_numeric($value)) {
        return strpos($value, '.') !== false ? (float)$value : (int)$value;
    }
    
    return $value;
}

