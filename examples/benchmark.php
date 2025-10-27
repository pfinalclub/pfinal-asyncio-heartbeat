<?php

/**
 * 性能基准测试
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PfinalClub\AsyncioHeartbeat\Protocol\Message;
use PfinalClub\AsyncioHeartbeat\Channel\{Channel, ChannelScheduler};
use PfinalClub\AsyncioHeartbeat\Node\{Node, NodeManager};

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║              Performance Benchmark Tool                    ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "\n";

// 测试 1: 消息编解码性能
echo "📊 Test 1: Message Encode/Decode Performance\n";
echo str_repeat("-", 60) . "\n";

$iterations = 100000;
$message = Message::createHeartbeatRequest();

$result = hb_benchmark(function() use ($message) {
    $encoded = $message->encode();
    Message::decode($encoded);
}, $iterations);

echo sprintf("   Iterations:     %s\n", number_format($iterations));
echo sprintf("   Total Time:     %.4f seconds\n", $result['total_time']);
echo sprintf("   Avg Time:       %.6f seconds\n", $result['avg_time']);
echo sprintf("   Throughput:     %s ops/s\n", number_format($result['ops_per_second']));
echo sprintf("   Memory Used:    %s\n", hb_format_bytes($result['memory_used']));
echo "\n";

// 测试 2: Channel 操作性能（修复版）
echo "📊 Test 2: Channel Operations Performance\n";
echo str_repeat("-", 60) . "\n";

$iterations = 10000; // 降低迭代次数，避免队列溢出
$channel = new Channel(1, 'test_node', 50000); // 增加队列容量

$result = hb_benchmark(function() use ($channel) {
    $channel->send("test data");
    $channel->putRecv("test response");
    $channel->recv(0.001);
    
    // 清空队列，避免溢出
    $channel->getSendQueue();
}, $iterations);

echo sprintf("   Iterations:     %s\n", number_format($iterations));
echo sprintf("   Total Time:     %.4f seconds\n", $result['total_time']);
echo sprintf("   Avg Time:       %.6f seconds\n", $result['avg_time']);
echo sprintf("   Throughput:     %s ops/s\n", number_format($result['ops_per_second']));
echo sprintf("   Memory Used:    %s\n", hb_format_bytes($result['memory_used']));
echo "\n";

// 测试 3: ChannelScheduler 性能
echo "📊 Test 3: ChannelScheduler Performance\n";
echo str_repeat("-", 60) . "\n";

$scheduler = new ChannelScheduler();
$iterations = 10000;

// 预创建通道
for ($i = 0; $i < 100; $i++) {
    $scheduler->createChannel("node_{$i}");
}

$result = hb_benchmark(function() use ($scheduler) {
    $channel = $scheduler->createChannel(uniqid());
    $scheduler->getChannel($channel->id);
    $scheduler->closeChannel($channel->id);
}, $iterations);

echo sprintf("   Iterations:     %s\n", number_format($iterations));
echo sprintf("   Total Time:     %.4f seconds\n", $result['total_time']);
echo sprintf("   Avg Time:       %.6f seconds\n", $result['avg_time']);
echo sprintf("   Throughput:     %s ops/s\n", number_format($result['ops_per_second']));
echo sprintf("   Memory Used:    %s\n", hb_format_bytes($result['memory_used']));
echo "\n";

// 测试 4: NodeManager 性能
echo "📊 Test 4: NodeManager Performance\n";
echo str_repeat("-", 60) . "\n";

$nodeManager = new NodeManager();
$iterations = 10000;

$result = hb_benchmark(function() use ($nodeManager) {
    static $id = 0;
    $node = new Node("node_" . $id++, "127.0.0.1", 9501, $id);
    $nodeManager->register($node);
    $nodeManager->updateHeartbeat($node->id);
    $nodeManager->unregister($node->id);
}, $iterations);

echo sprintf("   Iterations:     %s\n", number_format($iterations));
echo sprintf("   Total Time:     %.4f seconds\n", $result['total_time']);
echo sprintf("   Avg Time:       %.6f seconds\n", $result['avg_time']);
echo sprintf("   Throughput:     %s ops/s\n", number_format($result['ops_per_second']));
echo sprintf("   Memory Used:    %s\n", hb_format_bytes($result['memory_used']));
echo "\n";

// 总结
echo str_repeat("═", 60) . "\n";
echo "📈 Benchmark Summary\n";
echo str_repeat("═", 60) . "\n";
echo "✅ All benchmarks completed successfully\n";
echo sprintf("   Total Memory Peak: %s\n", hb_memory_usage()['peak_formatted']);
echo "\n";