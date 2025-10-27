<?php

/**
 * 多客户端并发示例 - 模拟 1000 个客户端同时连接
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PfinalClub\AsyncioHeartbeat\Client\HeartbeatClient;
use PfinalClub\AsyncioHeartbeat\Utils\Logger;
use Workerman\Worker;

// 配置日志（减少输出）
$logger = Logger::getInstance();
$logger->setLevel(Logger::LEVEL_WARNING);

$clientCount = 1000;
$clients = [];
$connectedCount = 0;
$startTime = microtime(true);

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║        Multi-Client Connection Test                       ║\n";
echo "╠════════════════════════════════════════════════════════════╣\n";
echo "║  Clients:  {$clientCount}                                           ║\n";
echo "║  Server:   127.0.0.1:9501                                  ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "🚀 Creating {$clientCount} clients...\n";

$worker = new Worker();
$worker->onWorkerStart = function() use (&$clients, $clientCount, &$connectedCount, $startTime) {
    // 创建多个客户端
    for ($i = 0; $i < $clientCount; $i++) {
        $client = new HeartbeatClient('127.0.0.1', 9501, [
            'heartbeat_interval' => 15,
            'auto_reconnect' => true,
            'metadata' => [
                'client_index' => $i,
            ],
        ]);
        
        $client->on('connect', function() use (&$connectedCount, $i, $clientCount, $startTime) {
            $connectedCount++;
            
            if ($connectedCount % 100 === 0 || $connectedCount === $clientCount) {
                $elapsed = microtime(true) - $startTime;
                $rate = $connectedCount / $elapsed;
                echo sprintf(
                    "✅ Connected: %d/%d (%.2f/s) - %.2fs elapsed\n",
                    $connectedCount,
                    $clientCount,
                    $rate,
                    $elapsed
                );
            }
        });
        
        $clients[$i] = $client;
        
        // 延迟连接，避免同时发起
        // 确保时间间隔至少为 0.001 秒
        \Workerman\Timer::add(
            max(0.001, 0.01 * $i),  // ✅ 修复：确保最小为 0.001 秒
            function() use ($client) {
                $client->connect();
            },
            [],
            false  // 只执行一次
        );
    }
    
    // 定时报告状态
    \Workerman\Timer::add(10, function() use (&$clients, $clientCount, &$connectedCount) {
        $totalSent = 0;
        $totalReceived = 0;
        $connected = 0;
        
        foreach ($clients as $client) {
            $stats = $client->getStats();
            if ($stats['connected']) {
                $connected++;
            }
            $totalSent += $stats['messages_sent'];
            $totalReceived += $stats['messages_received'];
        }
        
        echo "\n📊 Status Report:\n";
        echo "   Connected: {$connected}/{$clientCount}\n";
        echo "   Total Messages Sent: {$totalSent}\n";
        echo "   Total Messages Received: {$totalReceived}\n";
        echo "   Memory: " . hb_memory_usage()['current_formatted'] . "\n";
        echo "\n";
    });
};

Worker::runAll();