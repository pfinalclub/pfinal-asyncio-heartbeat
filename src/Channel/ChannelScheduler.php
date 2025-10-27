<?php

namespace PfinalClub\AsyncioHeartbeat\Channel;

use function PfinalClub\Asyncio\{create_task, sleep};

/**
 * 通道调度器 - 管理所有通道的消息调度
 */
class ChannelScheduler
{
    private array $channels = [];
    private array $nodeChannels = [];
    private int $nextChannelId = 1;
    private bool $running = false;
    private int $totalChannelsCreated = 0;
    private int $totalChannelsClosed = 0;
    
    /**
     * 创建通道
     */
    public function createChannel(string $nodeId, int $maxQueueSize = 1000): Channel
    {
        $channelId = $this->nextChannelId++;
        $channel = new Channel($channelId, $nodeId, $maxQueueSize);
        
        $this->channels[$channelId] = $channel;
        
        if (!isset($this->nodeChannels[$nodeId])) {
            $this->nodeChannels[$nodeId] = [];
        }
        $this->nodeChannels[$nodeId][$channelId] = $channel;
        
        $this->totalChannelsCreated++;
        
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
     * 获取节点的所有通道
     */
    public function getNodeChannels(string $nodeId): array
    {
        return $this->nodeChannels[$nodeId] ?? [];
    }
    
    /**
     * 获取节点的主通道（第一个通道）
     */
    public function getNodeMainChannel(string $nodeId): ?Channel
    {
        $channels = $this->getNodeChannels($nodeId);
        return empty($channels) ? null : reset($channels);
    }
    
    /**
     * 关闭通道
     */
    public function closeChannel(int $channelId): void
    {
        if (isset($this->channels[$channelId])) {
            $channel = $this->channels[$channelId];
            $channel->close();
            
            unset($this->channels[$channelId]);
            
            if (isset($this->nodeChannels[$channel->nodeId][$channelId])) {
                unset($this->nodeChannels[$channel->nodeId][$channelId]);
                
                // 如果节点没有通道了，删除节点记录
                if (empty($this->nodeChannels[$channel->nodeId])) {
                    unset($this->nodeChannels[$channel->nodeId]);
                }
            }
            
            $this->totalChannelsClosed++;
        }
    }
    
    /**
     * 关闭节点的所有通道
     */
    public function closeNodeChannels(string $nodeId): void
    {
        if (isset($this->nodeChannels[$nodeId])) {
            foreach ($this->nodeChannels[$nodeId] as $channel) {
                $channel->close();
                unset($this->channels[$channel->id]);
                $this->totalChannelsClosed++;
            }
            unset($this->nodeChannels[$nodeId]);
        }
    }
    
    /**
     * 检查节点是否有活跃通道
     */
    public function hasNodeChannels(string $nodeId): bool
    {
        return isset($this->nodeChannels[$nodeId]) && !empty($this->nodeChannels[$nodeId]);
    }
    
    /**
     * 获取所有通道
     */
    public function getAllChannels(): array
    {
        return $this->channels;
    }
    
    /**
     * 获取活跃通道数量
     */
    public function getActiveChannelCount(): int
    {
        return count($this->channels);
    }
    
    /**
     * 获取活跃节点数量
     */
    public function getActiveNodeCount(): int
    {
        return count($this->nodeChannels);
    }
    
    /**
     * 清理已关闭的通道
     */
    public function cleanup(): int
    {
        $cleaned = 0;
        
        foreach ($this->channels as $channelId => $channel) {
            if ($channel->isClosed()) {
                unset($this->channels[$channelId]);
                
                if (isset($this->nodeChannels[$channel->nodeId][$channelId])) {
                    unset($this->nodeChannels[$channel->nodeId][$channelId]);
                    
                    if (empty($this->nodeChannels[$channel->nodeId])) {
                        unset($this->nodeChannels[$channel->nodeId]);
                    }
                }
                
                $cleaned++;
            }
        }
        
        return $cleaned;
    }
    
    /**
     * 获取统计信息
     */
    public function getStats(): array
    {
        $channelStats = [];
        foreach ($this->channels as $channel) {
            $channelStats[] = $channel->getStats();
        }
        
        return [
            'active_channels' => count($this->channels),
            'active_nodes' => count($this->nodeChannels),
            'total_created' => $this->totalChannelsCreated,
            'total_closed' => $this->totalChannelsClosed,
            'channels' => $channelStats,
        ];
    }
    
    /**
     * 获取简要统计信息
     */
    public function getStatsSummary(): array
    {
        $totalSendQueue = 0;
        $totalRecvQueue = 0;
        $totalSendCount = 0;
        $totalRecvCount = 0;
        
        foreach ($this->channels as $channel) {
            $totalSendQueue += $channel->getSendQueueSize();
            $totalRecvQueue += $channel->getRecvQueueSize();
            $stats = $channel->getStats();
            $totalSendCount += $stats['send_count'];
            $totalRecvCount += $stats['recv_count'];
        }
        
        return [
            'active_channels' => count($this->channels),
            'active_nodes' => count($this->nodeChannels),
            'total_send_queue' => $totalSendQueue,
            'total_recv_queue' => $totalRecvQueue,
            'total_send_count' => $totalSendCount,
            'total_recv_count' => $totalRecvCount,
        ];
    }
}

