<?php

namespace PfinalClub\AsyncioHeartbeat\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PfinalClub\AsyncioHeartbeat\Protocol\Message;
use PfinalClub\AsyncioHeartbeat\Node\{Node, NodeManager};
use PfinalClub\AsyncioHeartbeat\Channel\ChannelScheduler;

/**
 * 集成测试 - 测试组件协作
 */
class HeartbeatTest extends TestCase
{
    public function testCompleteHeartbeatFlow(): void
    {
        // 1. 创建管理器
        $nodeManager = new NodeManager(30.0);
        $channelScheduler = new ChannelScheduler();
        
        // 2. 模拟客户端注册
        $node = new Node('client_001', '127.0.0.1', 12345, 1);
        $nodeManager->register($node);
        
        // 3. 为节点创建通道
        $channel = $channelScheduler->createChannel('client_001');
        
        // 4. 模拟发送注册响应
        $registerResponse = Message::createRegister('client_001', [
            'channel_id' => $channel->id,
            'status' => 'ok',
        ]);
        
        $encoded = $registerResponse->encode();
        $decoded = Message::decode($encoded);
        
        $this->assertEquals(Message::TYPE_REGISTER, $decoded->type);
        
        // 5. 模拟心跳流程
        for ($i = 0; $i < 5; $i++) {
            $heartbeatReq = Message::createHeartbeatRequest();
            $encoded = $heartbeatReq->encode();
            $decoded = Message::decode($encoded);
            
            $this->assertEquals(Message::TYPE_HEARTBEAT_REQ, $decoded->type);
            
            // 更新节点心跳
            $nodeManager->updateHeartbeat('client_001');
        }
        
        // 6. 验证心跳次数
        $this->assertEquals(5, $node->heartbeatCount);
        
        // 7. 模拟数据传输
        $dataMessage = Message::createData('Hello from client', $channel->id);
        $channel->putRecv($dataMessage->payload);
        
        $received = $channel->recv(0.001);
        $this->assertEquals('Hello from client', $received);
        
        // 8. 清理
        $channelScheduler->closeNodeChannels('client_001');
        $nodeManager->unregister('client_001');
        
        $this->assertNull($nodeManager->getNode('client_001'));
        $this->assertEmpty($channelScheduler->getNodeChannels('client_001'));
    }
    
    public function testMultipleNodesWithMultipleChannels(): void
    {
        $nodeManager = new NodeManager(30.0);
        $channelScheduler = new ChannelScheduler();
        
        // 创建3个节点，每个节点2个通道
        for ($i = 1; $i <= 3; $i++) {
            $node = new Node("node_{$i}", '127.0.0.1', 9000 + $i, $i);
            $nodeManager->register($node);
            
            $channelScheduler->createChannel("node_{$i}");
            $channelScheduler->createChannel("node_{$i}");
        }
        
        // 验证统计
        $this->assertEquals(3, $nodeManager->getNodeCount());
        $this->assertEquals(6, $channelScheduler->getActiveChannelCount());
        $this->assertEquals(3, $channelScheduler->getActiveNodeCount());
        
        // 关闭一个节点的所有通道
        $channelScheduler->closeNodeChannels('node_2');
        
        $this->assertEquals(4, $channelScheduler->getActiveChannelCount());
        $this->assertEquals(2, $channelScheduler->getActiveNodeCount());
    }
    
    public function testNodeTimeoutScenario(): void
    {
        $nodeManager = new NodeManager(0.1); // 100ms 超时
        
        $node1 = new Node('node_1', '127.0.0.1', 9501, 1);
        $node2 = new Node('node_2', '127.0.0.1', 9502, 2);
        
        $nodeManager->register($node1);
        $nodeManager->register($node2);
        
        // node_1 正常发送心跳
        $nodeManager->updateHeartbeat('node_1');
        
        // 等待超时
        usleep(150000); // 150ms
        
        // 再次更新 node_1
        $nodeManager->updateHeartbeat('node_1');
        
        // 检查超时
        $timeoutNodes = $nodeManager->checkTimeout();
        
        // node_2 应该超时，node_1 不应该
        $this->assertCount(1, $timeoutNodes);
        $this->assertEquals('node_2', $timeoutNodes[0]);
        $this->assertEquals('offline', $node2->status);
        $this->assertEquals('online', $node1->status);
    }
}

