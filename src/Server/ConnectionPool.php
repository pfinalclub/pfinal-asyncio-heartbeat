<?php

namespace PfinalClub\AsyncioHeartbeat\Server;

use Workerman\Connection\TcpConnection;

/**
 * 连接池管理
 */
class ConnectionPool
{
    private array $connections = [];
    private int $maxConnections;
    private int $nextConnectionId = 1;
    private int $totalConnections = 0;
    private int $totalClosed = 0;
    
    public function __construct(int $maxConnections = 1000000)
    {
        $this->maxConnections = $maxConnections;
    }
    
    /**
     * 添加连接
     */
    public function add(TcpConnection $connection): int
    {
        if (count($this->connections) >= $this->maxConnections) {
            throw new \RuntimeException('Connection pool is full');
        }
        
        $connectionId = $this->nextConnectionId++;
        $connection->id = $connectionId;
        $this->connections[$connectionId] = $connection;
        $this->totalConnections++;
        
        return $connectionId;
    }
    
    /**
     * 移除连接
     */
    public function remove(int $connectionId): ?TcpConnection
    {
        if (isset($this->connections[$connectionId])) {
            $connection = $this->connections[$connectionId];
            unset($this->connections[$connectionId]);
            $this->totalClosed++;
            return $connection;
        }
        
        return null;
    }
    
    /**
     * 获取连接
     */
    public function get(int $connectionId): ?TcpConnection
    {
        return $this->connections[$connectionId] ?? null;
    }
    
    /**
     * 检查连接是否存在
     */
    public function has(int $connectionId): bool
    {
        return isset($this->connections[$connectionId]);
    }
    
    /**
     * 获取所有连接
     */
    public function getAll(): array
    {
        return $this->connections;
    }
    
    /**
     * 获取连接数量
     */
    public function count(): int
    {
        return count($this->connections);
    }
    
    /**
     * 清空连接池
     */
    public function clear(): void
    {
        foreach ($this->connections as $connection) {
            try {
                $connection->close();
            } catch (\Exception $e) {
                // 忽略错误
            }
        }
        
        $this->connections = [];
    }
    
    /**
     * 获取统计信息
     */
    public function getStats(): array
    {
        return [
            'active_connections' => count($this->connections),
            'max_connections' => $this->maxConnections,
            'total_connections' => $this->totalConnections,
            'total_closed' => $this->totalClosed,
            'usage_percent' => (count($this->connections) / $this->maxConnections) * 100,
        ];
    }
}

