<?php

namespace PfinalClub\AsyncioHeartbeat\Tests\Channel;

use PHPUnit\Framework\TestCase;
use PfinalClub\AsyncioHeartbeat\Channel\ChannelScheduler;

class ChannelSchedulerTest extends TestCase
{
    private ChannelScheduler $scheduler;
    
    protected function setUp(): void
    {
        $this->scheduler = new ChannelScheduler();
    }
    
    public function testCreateChannel(): void
    {
        $channel = $this->scheduler->createChannel('node_1');
        
        $this->assertNotNull($channel);
        $this->assertEquals('node_1', $channel->nodeId);
        $this->assertGreaterThan(0, $channel->id);
    }
    
    public function testGetChannel(): void
    {
        $channel = $this->scheduler->createChannel('node_1');
        $retrieved = $this->scheduler->getChannel($channel->id);
        
        $this->assertSame($channel, $retrieved);
    }
    
    public function testGetNonExistentChannel(): void
    {
        $channel = $this->scheduler->getChannel(999);
        
        $this->assertNull($channel);
    }
    
    public function testGetNodeChannels(): void
    {
        $channel1 = $this->scheduler->createChannel('node_1');
        $channel2 = $this->scheduler->createChannel('node_1');
        $channel3 = $this->scheduler->createChannel('node_2');
        
        $node1Channels = $this->scheduler->getNodeChannels('node_1');
        
        $this->assertCount(2, $node1Channels);
        $this->assertArrayHasKey($channel1->id, $node1Channels);
        $this->assertArrayHasKey($channel2->id, $node1Channels);
    }
    
    public function testGetNodeMainChannel(): void
    {
        $channel1 = $this->scheduler->createChannel('node_1');
        $channel2 = $this->scheduler->createChannel('node_1');
        
        $mainChannel = $this->scheduler->getNodeMainChannel('node_1');
        
        $this->assertSame($channel1, $mainChannel);
    }
    
    public function testCloseChannel(): void
    {
        $channel = $this->scheduler->createChannel('node_1');
        $channelId = $channel->id;
        
        $this->scheduler->closeChannel($channelId);
        
        $this->assertTrue($channel->isClosed());
        $this->assertNull($this->scheduler->getChannel($channelId));
    }
    
    public function testCloseNodeChannels(): void
    {
        $channel1 = $this->scheduler->createChannel('node_1');
        $channel2 = $this->scheduler->createChannel('node_1');
        $channel3 = $this->scheduler->createChannel('node_2');
        
        $this->scheduler->closeNodeChannels('node_1');
        
        $this->assertTrue($channel1->isClosed());
        $this->assertTrue($channel2->isClosed());
        $this->assertFalse($channel3->isClosed());
        
        $this->assertEmpty($this->scheduler->getNodeChannels('node_1'));
        $this->assertCount(1, $this->scheduler->getNodeChannels('node_2'));
    }
    
    public function testHasNodeChannels(): void
    {
        $this->assertFalse($this->scheduler->hasNodeChannels('node_1'));
        
        $this->scheduler->createChannel('node_1');
        
        $this->assertTrue($this->scheduler->hasNodeChannels('node_1'));
    }
    
    public function testGetActiveChannelCount(): void
    {
        $this->assertEquals(0, $this->scheduler->getActiveChannelCount());
        
        $this->scheduler->createChannel('node_1');
        $this->scheduler->createChannel('node_2');
        
        $this->assertEquals(2, $this->scheduler->getActiveChannelCount());
    }
    
    public function testGetActiveNodeCount(): void
    {
        $this->assertEquals(0, $this->scheduler->getActiveNodeCount());
        
        $this->scheduler->createChannel('node_1');
        $this->scheduler->createChannel('node_1');
        $this->scheduler->createChannel('node_2');
        
        $this->assertEquals(2, $this->scheduler->getActiveNodeCount());
    }
    
    public function testCleanup(): void
    {
        $channel1 = $this->scheduler->createChannel('node_1');
        $channel2 = $this->scheduler->createChannel('node_2');
        
        $channel1->close();
        
        $cleaned = $this->scheduler->cleanup();
        
        $this->assertEquals(1, $cleaned);
        $this->assertEquals(1, $this->scheduler->getActiveChannelCount());
    }
    
    public function testGetStats(): void
    {
        $this->scheduler->createChannel('node_1');
        $this->scheduler->createChannel('node_2');
        
        $stats = $this->scheduler->getStats();
        
        $this->assertIsArray($stats);
        $this->assertEquals(2, $stats['active_channels']);
        $this->assertEquals(2, $stats['active_nodes']);
        $this->assertArrayHasKey('channels', $stats);
    }
}

