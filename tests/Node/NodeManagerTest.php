<?php

namespace PfinalClub\AsyncioHeartbeat\Tests\Node;

use PHPUnit\Framework\TestCase;
use PfinalClub\AsyncioHeartbeat\Node\{Node, NodeManager};

class NodeManagerTest extends TestCase
{
    private NodeManager $manager;
    
    protected function setUp(): void
    {
        $this->manager = new NodeManager(30.0);
    }
    
    public function testRegisterNode(): void
    {
        $node = new Node('node_1', '127.0.0.1', 9501, 1);
        
        $this->manager->register($node);
        
        $retrieved = $this->manager->getNode('node_1');
        $this->assertSame($node, $retrieved);
    }
    
    public function testUnregisterNode(): void
    {
        $node = new Node('node_1', '127.0.0.1', 9501, 1);
        $this->manager->register($node);
        
        $unregistered = $this->manager->unregister('node_1');
        
        $this->assertSame($node, $unregistered);
        $this->assertNull($this->manager->getNode('node_1'));
    }
    
    public function testGetNodeByConnection(): void
    {
        $node = new Node('node_1', '127.0.0.1', 9501, 123);
        $this->manager->register($node);
        
        $retrieved = $this->manager->getNodeByConnection(123);
        
        $this->assertSame($node, $retrieved);
    }
    
    public function testUnregisterByConnection(): void
    {
        $node = new Node('node_1', '127.0.0.1', 9501, 123);
        $this->manager->register($node);
        
        $unregistered = $this->manager->unregisterByConnection(123);
        
        $this->assertSame($node, $unregistered);
        $this->assertNull($this->manager->getNodeByConnection(123));
    }
    
    public function testHasNode(): void
    {
        $this->assertFalse($this->manager->hasNode('node_1'));
        
        $node = new Node('node_1', '127.0.0.1', 9501, 1);
        $this->manager->register($node);
        
        $this->assertTrue($this->manager->hasNode('node_1'));
    }
    
    public function testUpdateHeartbeat(): void
    {
        $node = new Node('node_1', '127.0.0.1', 9501, 1);
        $this->manager->register($node);
        
        $initialCount = $node->heartbeatCount;
        $initialTime = $node->lastHeartbeatTime;
        
        usleep(10000); // 10ms
        
        $result = $this->manager->updateHeartbeat('node_1');
        
        $this->assertTrue($result);
        $this->assertEquals($initialCount + 1, $node->heartbeatCount);
        $this->assertGreaterThan($initialTime, $node->lastHeartbeatTime);
    }
    
    public function testGetOnlineNodes(): void
    {
        $node1 = new Node('node_1', '127.0.0.1', 9501, 1);
        $node2 = new Node('node_2', '127.0.0.1', 9502, 2);
        
        $this->manager->register($node1);
        $this->manager->register($node2);
        
        $node2->markOffline();
        
        $onlineNodes = $this->manager->getOnlineNodes();
        
        $this->assertCount(1, $onlineNodes);
        $this->assertArrayHasKey('node_1', $onlineNodes);
    }
    
    public function testGetOfflineNodes(): void
    {
        $node1 = new Node('node_1', '127.0.0.1', 9501, 1);
        $node2 = new Node('node_2', '127.0.0.1', 9502, 2);
        
        $this->manager->register($node1);
        $this->manager->register($node2);
        
        $node2->markOffline();
        
        $offlineNodes = $this->manager->getOfflineNodes();
        
        $this->assertCount(1, $offlineNodes);
        $this->assertArrayHasKey('node_2', $offlineNodes);
    }
    
    public function testCheckTimeout(): void
    {
        $manager = new NodeManager(0.1); // 0.1秒超时
        
        $node = new Node('node_1', '127.0.0.1', 9501, 1);
        $manager->register($node);
        
        usleep(150000); // 150ms
        
        $timeoutNodes = $manager->checkTimeout();
        
        $this->assertCount(1, $timeoutNodes);
        $this->assertEquals('node_1', $timeoutNodes[0]);
        $this->assertEquals('offline', $node->status);
    }
    
    public function testCleanupOffline(): void
    {
        $node1 = new Node('node_1', '127.0.0.1', 9501, 1);
        $node2 = new Node('node_2', '127.0.0.1', 9502, 2);
        
        $this->manager->register($node1);
        $this->manager->register($node2);
        
        $node2->markOffline();
        
        $cleaned = $this->manager->cleanupOffline();
        
        $this->assertEquals(1, $cleaned);
        $this->assertEquals(1, $this->manager->getNodeCount());
    }
    
    public function testGetNodeCount(): void
    {
        $this->assertEquals(0, $this->manager->getNodeCount());
        
        $this->manager->register(new Node('node_1', '127.0.0.1', 9501, 1));
        $this->manager->register(new Node('node_2', '127.0.0.1', 9502, 2));
        
        $this->assertEquals(2, $this->manager->getNodeCount());
    }
    
    public function testGetStatsSummary(): void
    {
        $node1 = new Node('node_1', '127.0.0.1', 9501, 1);
        $node2 = new Node('node_2', '127.0.0.1', 9502, 2);
        
        $this->manager->register($node1);
        $this->manager->register($node2);
        
        $node1->updateHeartbeat();
        $node1->updateHeartbeat();
        
        $stats = $this->manager->getStatsSummary();
        
        $this->assertIsArray($stats);
        $this->assertEquals(2, $stats['total']);
        $this->assertEquals(2, $stats['online']);
        $this->assertGreaterThanOrEqual(2, $stats['total_heartbeats']);
    }
}

