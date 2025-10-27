<?php

namespace PfinalClub\AsyncioHeartbeat\Client;

use PfinalClub\AsyncioHeartbeat\Channel\Channel;

/**
 * TCP 多路复用客户端
 */
class TcpMultiplexClient
{
    private array $channels = [];
    
    /**
     * 创建通道
     */
    public function createChannel(int $channelId, string $nodeId): Channel
    {
        $channel = new Channel($channelId, $nodeId);
        $this->channels[$channelId] = $channel;
        
        return $channel;
    }
    
    /**
     * 获取通道
     */
    public function getChannel(int $channelId): ?Channel
    {
        return $this->channels[$channelId] ?? null;
    }
    
    /**
     * 关闭通道
     */
    public function closeChannel(int $channelId): void
    {
        if (isset($this->channels[$channelId])) {
            $this->channels[$channelId]->close();
            unset($this->channels[$channelId]);
        }
    }
    
    /**
     * 关闭所有通道
     */
    public function closeAll(): void
    {
        foreach ($this->channels as $channel) {
            $channel->close();
        }
        
        $this->channels = [];
    }
    
    /**
     * 获取所有通道
     */
    public function getAllChannels(): array
    {
        return $this->channels;
    }
    
    /**
     * 获取通道数量
     */
    public function getChannelCount(): int
    {
        return count($this->channels);
    }
}

