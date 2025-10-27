<?php

namespace PfinalClub\AsyncioHeartbeat\Tests\Channel;

use PHPUnit\Framework\TestCase;
use PfinalClub\AsyncioHeartbeat\Channel\Channel;

class ChannelTest extends TestCase
{
    public function testCreateChannel(): void
    {
        $channel = new Channel(1, 'node_test');
        
        $this->assertEquals(1, $channel->id);
        $this->assertEquals('node_test', $channel->nodeId);
        $this->assertFalse($channel->isClosed());
    }
    
    public function testSendAndReceive(): void
    {
        $channel = new Channel(1, 'node_test');
        
        $channel->send('test data 1');
        $channel->send('test data 2');
        
        $this->assertEquals(2, $channel->getSendQueueSize());
        
        $queue = $channel->getSendQueue();
        $this->assertCount(2, $queue);
        $this->assertEquals('test data 1', $queue[0]);
        $this->assertEquals('test data 2', $queue[1]);
        
        // 队列应该被清空
        $this->assertEquals(0, $channel->getSendQueueSize());
    }
    
    public function testPutRecvAndRecv(): void
    {
        $channel = new Channel(1, 'node_test');
        
        $channel->putRecv('received data 1');
        $channel->putRecv('received data 2');
        
        $this->assertEquals(2, $channel->getRecvQueueSize());
        
        $data1 = $channel->recv(0.001);
        $this->assertEquals('received data 1', $data1);
        
        $data2 = $channel->recv(0.001);
        $this->assertEquals('received data 2', $data2);
        
        $this->assertEquals(0, $channel->getRecvQueueSize());
    }
    
    public function testRecvTimeout(): void
    {
        $channel = new Channel(1, 'node_test');
        
        $startTime = microtime(true);
        $result = $channel->recv(0.01); // 10ms timeout
        $elapsed = microtime(true) - $startTime;
        
        $this->assertNull($result);
        $this->assertGreaterThanOrEqual(0.01, $elapsed);
    }
    
    public function testCloseChannel(): void
    {
        $channel = new Channel(1, 'node_test');
        
        $channel->send('data1');
        $channel->putRecv('data2');
        
        $channel->close();
        
        $this->assertTrue($channel->isClosed());
        $this->assertEquals(0, $channel->getSendQueueSize());
        $this->assertEquals(0, $channel->getRecvQueueSize());
    }
    
    public function testSendToClosedChannel(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('is closed');
        
        $channel = new Channel(1, 'node_test');
        $channel->close();
        $channel->send('test');
    }
    
    public function testQueueSizeLimit(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('send queue is full');
        
        $channel = new Channel(1, 'node_test', 5);
        
        for ($i = 0; $i < 10; $i++) {
            $channel->send("data{$i}");
        }
    }
    
    public function testGetStats(): void
    {
        $channel = new Channel(1, 'node_test');
        
        $channel->send('data1');
        $channel->putRecv('data2');
        
        $stats = $channel->getStats();
        
        $this->assertIsArray($stats);
        $this->assertEquals(1, $stats['id']);
        $this->assertEquals('node_test', $stats['node_id']);
        $this->assertFalse($stats['closed']);
        $this->assertEquals(1, $stats['send_queue_size']);
        $this->assertEquals(1, $stats['recv_queue_size']);
    }
}

