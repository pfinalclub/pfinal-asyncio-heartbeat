<?php

/**
 * 心跳客户端示例
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PfinalClub\AsyncioHeartbeat\Client\HeartbeatClient;
use PfinalClub\AsyncioHeartbeat\Utils\Logger;
use Workerman\Worker;

// 配置日志
$logger = Logger::getInstance();
$logger->setLevel(Logger::LEVEL_INFO);

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║          PFinal Asyncio Heartbeat Client v1.0            ║\n";
echo "╠════════════════════════════════════════════════════════════╣\n";
echo "║  Server:  127.0.0.1:9501                                   ║\n";
echo "║  Mode:    Auto Reconnect                                   ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "\n";

// 创建一个空的 Worker 来启动事件循环
$worker = new Worker();
$worker->onWorkerStart = function() {
    // 客户端配置
    $config = [
        'heartbeat_interval' => 10,       // 10秒发送一次心跳
        'auto_reconnect' => true,         // 自动重连
        'reconnect_interval' => 5,        // 5秒重连间隔
        'metadata' => [
            'version' => '1.0.0',
            'type' => 'demo_client',
        ],
    ];

    $client = new HeartbeatClient('127.0.0.1', 9501, $config);

    // 注册连接回调
    $client->on('connect', function() {
        echo "✅ Connected to server\n";
    });

    // 注册注册成功回调
    $client->on('register', function($nodeId, $channelId) {
        echo "✅ Registered successfully\n";
        echo "   Node ID: {$nodeId}\n";
        echo "   Channel ID: {$channelId}\n";
    });

    // 注册心跳回调
    $client->on('heartbeat', function() {
        static $count = 0;
        $count++;
        echo "💓 Heartbeat #{$count}\n";
    });

    // 注册数据接收回调
    $client->on('data', function($data, $channelId) {
        echo "📨 Received data on channel {$channelId}: {$data}\n";
    });

    // 注册断开回调
    $client->on('close', function() {
        echo "⚠️  Connection closed, will reconnect...\n";
    });

    // 注册错误回调
    $client->on('error', function($code, $msg) {
        echo "❌ Error [{$code}]: {$msg}\n";
    });

    echo "🔗 Connecting to server...\n";
    echo "\n";

    // 连接服务器
    $client->connect();

    // 定时发送测试数据
    \Workerman\Timer::add(30, function() use ($client) {
        if ($client->isConnected()) {
            try {
                $client->send("Test message at " . date('Y-m-d H:i:s'));
                echo "📤 Sent test message\n";
            } catch (\Exception $e) {
                echo "❌ Failed to send: {$e->getMessage()}\n";
            }
        }
    });
    
    // 定时显示统计信息
    \Workerman\Timer::add(60, function() use ($client) {
        $stats = $client->getStats();
        echo "\n📊 Stats:\n";
        echo "   Connected: " . ($stats['connected'] ? 'Yes' : 'No') . "\n";
        echo "   Messages Sent: {$stats['messages_sent']}\n";
        echo "   Messages Received: {$stats['messages_received']}\n";
        echo "   Uptime: " . hb_format_duration($stats['uptime']) . "\n";
        echo "\n";
    });
};

Worker::runAll();