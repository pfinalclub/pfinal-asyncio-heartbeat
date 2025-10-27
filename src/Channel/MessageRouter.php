<?php

namespace PfinalClub\AsyncioHeartbeat\Channel;

use PfinalClub\AsyncioHeartbeat\Protocol\Message;

/**
 * 消息路由器 - 将消息路由到正确的通道
 */
class MessageRouter
{
    private array $routes = [];
    private array $handlers = [];
    private int $totalRouted = 0;
    private int $totalFailed = 0;
    
    public function __construct(
        private ChannelScheduler $scheduler
    ) {}
    
    /**
     * 路由消息到指定通道
     */
    public function route(Message $message): bool
    {
        try {
            // 如果消息指定了通道 ID
            if ($message->channelId > 0) {
                $channel = $this->scheduler->getChannel($message->channelId);
                
                if ($channel && !$channel->isClosed()) {
                    $channel->putRecv($message->payload);
                    $this->totalRouted++;
                    return true;
                }
            }
            
            // 否则尝试根据消息类型路由
            if (isset($this->handlers[$message->type])) {
                $handler = $this->handlers[$message->type];
                $handler($message);
                $this->totalRouted++;
                return true;
            }
            
            $this->totalFailed++;
            return false;
            
        } catch (\Exception $e) {
            $this->totalFailed++;
            return false;
        }
    }
    
    /**
     * 广播消息到节点的所有通道
     */
    public function broadcast(string $nodeId, Message $message): int
    {
        $channels = $this->scheduler->getNodeChannels($nodeId);
        $count = 0;
        
        foreach ($channels as $channel) {
            if (!$channel->isClosed()) {
                try {
                    $channel->putRecv($message->payload);
                    $count++;
                } catch (\Exception $e) {
                    // 忽略错误，继续广播
                }
            }
        }
        
        $this->totalRouted += $count;
        return $count;
    }
    
    /**
     * 广播消息到所有通道
     */
    public function broadcastAll(Message $message): int
    {
        $channels = $this->scheduler->getAllChannels();
        $count = 0;
        
        foreach ($channels as $channel) {
            if (!$channel->isClosed()) {
                try {
                    $channel->putRecv($message->payload);
                    $count++;
                } catch (\Exception $e) {
                    // 忽略错误，继续广播
                }
            }
        }
        
        $this->totalRouted += $count;
        return $count;
    }
    
    /**
     * 注册消息类型处理器
     */
    public function registerHandler(int $messageType, callable $handler): void
    {
        $this->handlers[$messageType] = $handler;
    }
    
    /**
     * 移除消息类型处理器
     */
    public function unregisterHandler(int $messageType): void
    {
        unset($this->handlers[$messageType]);
    }
    
    /**
     * 添加路由规则
     */
    public function addRoute(string $pattern, int $channelId): void
    {
        $this->routes[$pattern] = $channelId;
    }
    
    /**
     * 移除路由规则
     */
    public function removeRoute(string $pattern): void
    {
        unset($this->routes[$pattern]);
    }
    
    /**
     * 根据模式匹配路由
     */
    private function matchRoute(string $key): ?int
    {
        foreach ($this->routes as $pattern => $channelId) {
            if (fnmatch($pattern, $key)) {
                return $channelId;
            }
        }
        
        return null;
    }
    
    /**
     * 获取统计信息
     */
    public function getStats(): array
    {
        return [
            'total_routed' => $this->totalRouted,
            'total_failed' => $this->totalFailed,
            'handlers_count' => count($this->handlers),
            'routes_count' => count($this->routes),
        ];
    }
}

