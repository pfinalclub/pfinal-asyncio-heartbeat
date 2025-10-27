<?php

namespace PfinalClub\AsyncioHeartbeat\Node;

use function PfinalClub\Asyncio\{create_task, sleep};

/**
 * 节点管理器 - 管理所有连接的节点
 */
class NodeManager
{
    private array $nodes = [];
    private array $connectionNodes = [];
    private int $totalRegistered = 0;
    private int $totalUnregistered = 0;
    private int $totalTimeout = 0;
    
    public function __construct(
        private float $heartbeatTimeout = 30.0
    ) {}
    
    /**
     * 注册节点
     */
    public function register(Node $node): void
    {
        $this->nodes[$node->id] = $node;
        $this->connectionNodes[$node->connectionId] = $node->id;
        $this->totalRegistered++;
    }
    
    /**
     * 注销节点
     */
    public function unregister(string $nodeId): ?Node
    {
        if (isset($this->nodes[$nodeId])) {
            $node = $this->nodes[$nodeId];
            unset($this->connectionNodes[$node->connectionId]);
            unset($this->nodes[$nodeId]);
            $this->totalUnregistered++;
            return $node;
        }
        
        return null;
    }
    
    /**
     * 通过连接 ID 注销节点
     */
    public function unregisterByConnection(int $connectionId): ?Node
    {
        if (isset($this->connectionNodes[$connectionId])) {
            $nodeId = $this->connectionNodes[$connectionId];
            return $this->unregister($nodeId);
        }
        
        return null;
    }
    
    /**
     * 获取节点
     */
    public function getNode(string $nodeId): ?Node
    {
        return $this->nodes[$nodeId] ?? null;
    }
    
    /**
     * 通过连接 ID 获取节点
     */
    public function getNodeByConnection(int $connectionId): ?Node
    {
        $nodeId = $this->connectionNodes[$connectionId] ?? null;
        return $nodeId ? $this->nodes[$nodeId] : null;
    }
    
    /**
     * 检查节点是否存在
     */
    public function hasNode(string $nodeId): bool
    {
        return isset($this->nodes[$nodeId]);
    }
    
    /**
     * 更新节点心跳
     */
    public function updateHeartbeat(string $nodeId): bool
    {
        if (isset($this->nodes[$nodeId])) {
            $this->nodes[$nodeId]->updateHeartbeat();
            return true;
        }
        return false;
    }
    
    /**
     * 更新节点消息计数
     */
    public function updateMessage(string $nodeId): bool
    {
        if (isset($this->nodes[$nodeId])) {
            $this->nodes[$nodeId]->updateMessage();
            return true;
        }
        return false;
    }
    
    /**
     * 获取所有节点
     */
    public function getAllNodes(): array
    {
        return $this->nodes;
    }
    
    /**
     * 获取所有在线节点
     */
    public function getOnlineNodes(): array
    {
        return array_filter($this->nodes, fn($node) => $node->isOnline());
    }
    
    /**
     * 获取所有离线节点
     */
    public function getOfflineNodes(): array
    {
        return array_filter($this->nodes, fn($node) => !$node->isOnline());
    }
    
    /**
     * 检查并移除超时节点
     */
    public function checkTimeout(): array
    {
        $timeoutNodes = [];
        
        foreach ($this->nodes as $nodeId => $node) {
            if ($node->isTimeout($this->heartbeatTimeout)) {
                $node->markOffline();
                $timeoutNodes[] = $nodeId;
                $this->totalTimeout++;
            }
        }
        
        return $timeoutNodes;
    }
    
    /**
     * 清理离线节点
     */
    public function cleanupOffline(): int
    {
        $cleaned = 0;
        
        foreach ($this->nodes as $nodeId => $node) {
            if (!$node->isOnline()) {
                $this->unregister($nodeId);
                $cleaned++;
            }
        }
        
        return $cleaned;
    }
    
    /**
     * 获取节点数量
     */
    public function getNodeCount(): int
    {
        return count($this->nodes);
    }
    
    /**
     * 获取在线节点数量
     */
    public function getOnlineNodeCount(): int
    {
        return count(array_filter($this->nodes, fn($n) => $n->isOnline()));
    }
    
    /**
     * 获取离线节点数量
     */
    public function getOfflineNodeCount(): int
    {
        return count(array_filter($this->nodes, fn($n) => !$n->isOnline()));
    }
    
    /**
     * 获取统计信息
     */
    public function getStats(): array
    {
        $online = $this->getOnlineNodeCount();
        $offline = $this->getOfflineNodeCount();
        
        $nodeStats = [];
        foreach ($this->nodes as $node) {
            $nodeStats[] = $node->toArray();
        }
        
        return [
            'total' => count($this->nodes),
            'online' => $online,
            'offline' => $offline,
            'total_registered' => $this->totalRegistered,
            'total_unregistered' => $this->totalUnregistered,
            'total_timeout' => $this->totalTimeout,
            'nodes' => $nodeStats,
        ];
    }
    
    /**
     * 获取简要统计信息
     */
    public function getStatsSummary(): array
    {
        $totalHeartbeats = 0;
        $totalMessages = 0;
        
        foreach ($this->nodes as $node) {
            $totalHeartbeats += $node->heartbeatCount;
            $totalMessages += $node->totalMessages;
        }
        
        return [
            'total' => count($this->nodes),
            'online' => $this->getOnlineNodeCount(),
            'offline' => $this->getOfflineNodeCount(),
            'total_heartbeats' => $totalHeartbeats,
            'total_messages' => $totalMessages,
            'total_registered' => $this->totalRegistered,
            'total_unregistered' => $this->totalUnregistered,
            'total_timeout' => $this->totalTimeout,
        ];
    }
    
    /**
     * 设置心跳超时时间
     */
    public function setHeartbeatTimeout(float $timeout): void
    {
        $this->heartbeatTimeout = $timeout;
    }
    
    /**
     * 获取心跳超时时间
     */
    public function getHeartbeatTimeout(): float
    {
        return $this->heartbeatTimeout;
    }
}

