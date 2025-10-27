<?php

namespace PfinalClub\AsyncioHeartbeat\Node;

/**
 * 节点模型 - 表示一个连接的客户端节点
 */
class Node
{
    public float $lastHeartbeatTime;
    public int $heartbeatCount = 0;
    public string $status = 'online';
    public array $metadata = [];
    public float $createdAt;
    public int $totalMessages = 0;
    public float $lastMessageTime;
    
    public function __construct(
        public readonly string $id,
        public readonly string $ip,
        public readonly int $port,
        public readonly int $connectionId
    ) {
        $this->lastHeartbeatTime = microtime(true);
        $this->createdAt = microtime(true);
        $this->lastMessageTime = microtime(true);
    }
    
    /**
     * 更新心跳时间
     */
    public function updateHeartbeat(): void
    {
        $this->lastHeartbeatTime = microtime(true);
        $this->heartbeatCount++;
        $this->status = 'online';
    }
    
    /**
     * 更新消息时间
     */
    public function updateMessage(): void
    {
        $this->lastMessageTime = microtime(true);
        $this->totalMessages++;
    }
    
    /**
     * 检查是否超时
     */
    public function isTimeout(float $timeoutSeconds): bool
    {
        return (microtime(true) - $this->lastHeartbeatTime) > $timeoutSeconds;
    }
    
    /**
     * 获取上次心跳距今的秒数
     */
    public function getSecondsSinceLastHeartbeat(): float
    {
        return microtime(true) - $this->lastHeartbeatTime;
    }
    
    /**
     * 获取节点存活时间（秒）
     */
    public function getLifetime(): float
    {
        return microtime(true) - $this->createdAt;
    }
    
    /**
     * 标记为离线
     */
    public function markOffline(): void
    {
        $this->status = 'offline';
    }
    
    /**
     * 标记为在线
     */
    public function markOnline(): void
    {
        $this->status = 'online';
    }
    
    /**
     * 设置元数据
     */
    public function setMetadata(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
    }
    
    /**
     * 获取元数据
     */
    public function getMetadata(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }
    
    /**
     * 是否在线
     */
    public function isOnline(): bool
    {
        return $this->status === 'online';
    }
    
    /**
     * 获取节点信息
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'ip' => $this->ip,
            'port' => $this->port,
            'connection_id' => $this->connectionId,
            'status' => $this->status,
            'last_heartbeat' => $this->lastHeartbeatTime,
            'seconds_since_heartbeat' => $this->getSecondsSinceLastHeartbeat(),
            'heartbeat_count' => $this->heartbeatCount,
            'total_messages' => $this->totalMessages,
            'created_at' => $this->createdAt,
            'lifetime' => $this->getLifetime(),
            'metadata' => $this->metadata,
        ];
    }
    
    /**
     * 获取简要信息
     */
    public function getSummary(): string
    {
        return sprintf(
            "Node[%s] %s:%d - %s (heartbeats: %d, lifetime: %.1fs)",
            $this->id,
            $this->ip,
            $this->port,
            $this->status,
            $this->heartbeatCount,
            $this->getLifetime()
        );
    }
}

