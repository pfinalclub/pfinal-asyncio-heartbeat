<?php

/**
 * 压力测试 - 测试 10,000+ 并发连接
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PfinalClub\AsyncioHeartbeat\Client\HeartbeatClient;
use PfinalClub\AsyncioHeartbeat\Utils\Logger;
use Workerman\Worker;

// 禁用大部分日志
$logger = Logger::getInstance();
$logger->disable();

// 测试配置
$targetClients = isset($argv[1]) ? (int)$argv[1] : 10000;
$batchSize = 100; // 每批创建的客户端数
$batchDelay = 0.5; // 批次间隔（秒）

$clients = [];
$stats = [
    'total' => $targetClients,
    'created' => 0,
    'connected' => 0,
    'failed' => 0,
    'start_time' => microtime(true),
];

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║              Stress Test Tool                              ║\n";
echo "╠════════════════════════════════════════════════════════════╣\n";
echo "║  Target:       " . number_format($targetClients) . " clients                               ║\n";
echo "║  Batch Size:   {$batchSize}                                          ║\n";
echo "║  Server:       127.0.0.1:9501                              ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "\n";

$worker = new Worker();
$worker->onWorkerStart = function() use (&$clients, &$stats, $targetClients, $batchSize, $batchDelay) {
    $currentBatch = 0;
    $totalBatches = ceil($targetClients / $batchSize);
    
    // 分批创建客户端
    $batchTimer = \Workerman\Timer::add($batchDelay, function() use (
        &$clients, &$stats, &$currentBatch, $targetClients, $batchSize, $totalBatches, $batchDelay
    ) {
        if ($stats['created'] >= $targetClients) {
            return;
        }
        
        $batchStart = $currentBatch * $batchSize;
        $batchEnd = min($batchStart + $batchSize, $targetClients);
        
        for ($i = $batchStart; $i < $batchEnd; $i++) {
            $client = new HeartbeatClient('127.0.0.1', 9501, [
                'heartbeat_interval' => 30,
                'auto_reconnect' => false,
            ]);
            
            $client->on('connect', function() use (&$stats) {
                $stats['connected']++;
            });
            
            $client->on('error', function() use (&$stats) {
                $stats['failed']++;
            });
            
            try {
                $client->connect();
                $clients[$i] = $client;
                $stats['created']++;
            } catch (\Exception $e) {
                $stats['failed']++;
            }
        }
        
        $currentBatch++;
        $elapsed = microtime(true) - $stats['start_time'];
        $creationRate = $stats['created'] / $elapsed;
        $progress = ($stats['created'] / $targetClients) * 100;
        
        echo sprintf(
            "📦 Batch %d/%d - Created: %s/%s (%.1f%%) - Rate: %.0f/s - Connected: %s - Failed: %d\n",
            $currentBatch,
            $totalBatches,
            number_format($stats['created']),
            number_format($targetClients),
            $progress,
            $creationRate,
            number_format($stats['connected']),
            $stats['failed']
        );
        
        if ($stats['created'] >= $targetClients) {
            echo "\n✅ All clients created!\n\n";
        }
    });
    
    // 实时统计报告
    \Workerman\Timer::add(5, function() use (&$clients, &$stats) {
        $elapsed = microtime(true) - $stats['start_time'];
        $memory = hb_memory_usage();
        
        // 统计实际连接状态
        $actualConnected = 0;
        $totalSent = 0;
        $totalReceived = 0;
        
        foreach ($clients as $client) {
            if ($client->isConnected()) {
                $actualConnected++;
                $clientStats = $client->getStats();
                $totalSent += $clientStats['messages_sent'];
                $totalReceived += $clientStats['messages_received'];
            }
        }
        
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "📊 REAL-TIME STATS - " . date('H:i:s') . "\n";
        echo str_repeat("=", 60) . "\n";
        echo sprintf("   Created:       %s / %s\n", number_format($stats['created']), number_format($stats['total']));
        echo sprintf("   Connected:     %s (%.1f%%)\n", number_format($actualConnected), ($actualConnected / max($stats['created'], 1)) * 100);
        echo sprintf("   Failed:        %d\n", $stats['failed']);
        echo sprintf("   Elapsed:       %s\n", hb_format_duration($elapsed));
        echo sprintf("   Messages Sent: %s\n", number_format($totalSent));
        echo sprintf("   Messages Recv: %s\n", number_format($totalReceived));
        echo sprintf("   Memory:        %s (Peak: %s)\n", $memory['current_formatted'], $memory['peak_formatted']);
        echo sprintf("   Avg Rate:      %.2f conn/s\n", $stats['created'] / $elapsed);
        echo str_repeat("=", 60) . "\n\n";
    });
    
    // 最终报告
    \Workerman\Timer::add(60, function() use (&$clients, &$stats, $targetClients) {
        if ($stats['created'] < $targetClients) {
            return;
        }
        
        static $reported = false;
        if ($reported) {
            return;
        }
        $reported = true;
        
        $elapsed = microtime(true) - $stats['start_time'];
        $actualConnected = 0;
        
        foreach ($clients as $client) {
            if ($client->isConnected()) {
                $actualConnected++;
            }
        }
        
        echo "\n" . str_repeat("═", 60) . "\n";
        echo "🎉 FINAL REPORT\n";
        echo str_repeat("═", 60) . "\n";
        echo sprintf("   Target:        %s clients\n", number_format($targetClients));
        echo sprintf("   Connected:     %s (%.1f%%)\n", number_format($actualConnected), ($actualConnected / $targetClients) * 100);
        echo sprintf("   Failed:        %d\n", $stats['failed']);
        echo sprintf("   Total Time:    %s\n", hb_format_duration($elapsed));
        echo sprintf("   Avg Rate:      %.2f conn/s\n", $targetClients / $elapsed);
        echo sprintf("   Memory Peak:   %s\n", hb_memory_usage()['peak_formatted']);
        echo str_repeat("═", 60) . "\n";
        
        if ($actualConnected / $targetClients >= 0.95) {
            echo "✅ SUCCESS: >= 95% connection rate\n";
        } else {
            echo "⚠️  WARNING: < 95% connection rate\n";
        }
        echo "\n";
    });
};

echo "🚀 Starting stress test...\n\n";
Worker::runAll();

