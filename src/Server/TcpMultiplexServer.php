<?php

namespace PfinalClub\AsyncioHeartbeat\Server;

use Workerman\Connection\TcpConnection;
use PfinalClub\AsyncioHeartbeat\Channel\ChannelScheduler;
use PfinalClub\AsyncioHeartbeat\Protocol\Message;

/**
 * TCP 多路复用服务器
 */
class TcpMultiplexServer
{
    private ChannelScheduler $scheduler;
    private array $connectionChannels = [];
    
    public function __construct()
    {
        $this->scheduler = new ChannelScheduler();
    }
    
    /**
     * 为连接创建通道
     */
    public function createChannel(TcpConnection $connection, string $nodeId): int
    {
        $channel = $this->scheduler->createChannel($nodeId);
        
        if (!isset($this->connectionChannels[$connection->id])) {
            $this->connectionChannels[$connection->id] = [];
        }
        
        $this->connectionChannels[$connection->id][$channel->id] = $channel;
        
        return $channel->id;
    }
    
    /**
     * 获取通道调度器
     */
    public function getScheduler(): ChannelScheduler
    {
        return $this->scheduler;
    }
    
    /**
     * 发送消息到通道
     */
    public function sendToChannel(int $channelId, Message $message, TcpConnection $connection): bool
    {
        try {
            $connection->send($message->encode());
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * 关闭连接的所有通道
     */
    public function closeConnectionChannels(int $connectionId): void
    {
        if (isset($this->connectionChannels[$connectionId])) {
            foreach ($this->connectionChannels[$connectionId] as $channel) {
                $this->scheduler->closeChannel($channel->id);
            }
            
            unset($this->connectionChannels[$connectionId]);
        }
    }
    
    /**
     * 获取连接的通道数量
     */
    public function getConnectionChannelCount(int $connectionId): int
    {
        return isset($this->connectionChannels[$connectionId]) 
            ? count($this->connectionChannels[$connectionId]) 
            : 0;
    }
    
    /**
     * 获取统计信息
     */
    public function getStats(): array
    {
        return [
            'connections_with_channels' => count($this->connectionChannels),
            'scheduler_stats' => $this->scheduler->getStats(),
        ];
    }
}

