<?php

/**
 * 心跳服务器示例
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PfinalClub\AsyncioHeartbeat\Server\HeartbeatServer;
use PfinalClub\AsyncioHeartbeat\Utils\Logger;

// 配置日志
$logger = Logger::getInstance();
$logger->setLevel(Logger::LEVEL_INFO);

// 服务器配置
$config = [
    'worker_count' => 8,              // 8 个进程（充分利用多核）
    'heartbeat_timeout' => 30,        // 30秒心跳超时
    'heartbeat_check_interval' => 10, // 10秒检查一次
    'max_connections' => 1000000,     // 最大连接数
];

// 创建并启动服务器
$server = new HeartbeatServer('0.0.0.0', 9501, $config);

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║          PFinal Asyncio Heartbeat Server v1.0            ║\n";
echo "╠════════════════════════════════════════════════════════════╣\n";
echo "║  Host:             0.0.0.0                                 ║\n";
echo "║  Port:             9501                                    ║\n";
echo "║  Workers:          8                                       ║\n";
echo "║  Max Connections:  1,000,000                               ║\n";
echo "║  Heartbeat:        30s timeout / 10s check                 ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "🚀 Starting server...\n";
echo "\n";

$server->start();

