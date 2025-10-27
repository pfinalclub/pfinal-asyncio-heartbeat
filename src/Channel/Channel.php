<?php

namespace PfinalClub\AsyncioHeartbeat\Channel;

use PfinalClub\Asyncio\Future;
use function PfinalClub\Asyncio\{create_future, sleep};

/**
 * 通道 - 实现 TCP 多路复用的单个通道
 */
class Channel
{
    private array $sendQueue = [];
    private array $recvQueue = [];
    private array $waitingFutures = [];
    private bool $closed = false;
    private int $sendCount = 0;
    private int $recvCount = 0;
    private float $createdAt;
    
    public function __construct(
        public readonly int $id,
        public readonly string $nodeId,
        private int $maxQueueSize = 1000
    ) {
        $this->createdAt = microtime(true);
    }
    
    /**
     * 发送消息到通道
     */
    public function send(string $data): void
    {
        if ($this->closed) {
            throw new \RuntimeException("Channel {$this->id} is closed");
        }
        
        if (count($this->sendQueue) >= $this->maxQueueSize) {
            throw new \RuntimeException("Channel {$this->id} send queue is full");
        }
        
        $this->sendQueue[] = $data;
        $this->sendCount++;
    }
    
    /**
     * 从通道接收消息（同步，会阻塞）
     */
    public function recv(float $timeout = 0): mixed
    {
        if ($this->closed && empty($this->recvQueue)) {
            return null;
        }
        
        $startTime = microtime(true);
        
        // 如果队列为空，等待消息
        while (empty($this->recvQueue) && !$this->closed) {
            sleep(0.001); // 1ms 轮询间隔
            
            if ($timeout > 0 && (microtime(true) - $startTime) >= $timeout) {
                return null; // 超时
            }
        }
        
        if (empty($this->recvQueue)) {
            return null;
        }
        
        $this->recvCount++;
        return array_shift($this->recvQueue);
    }
    
    /**
     * 从通道接收消息（异步）
     */
    public function recvAsync(): Future
    {
        $future = create_future();
        
        if (!empty($this->recvQueue)) {
            $this->recvCount++;
            $future->setResult(array_shift($this->recvQueue));
        } else if ($this->closed) {
            $future->setResult(null);
        } else {
            $this->waitingFutures[] = $future;
        }
        
        return $future;
    }
    
    /**
     * 将消息放入接收队列
     */
    public function putRecv(string $data): void
    {
        if ($this->closed) {
            return;
        }
        
        // 如果有等待的 Future，直接满足
        if (!empty($this->waitingFutures)) {
            $future = array_shift($this->waitingFutures);
            $future->setResult($data);
            $this->recvCount++;
            return;
        }
        
        if (count($this->recvQueue) >= $this->maxQueueSize) {
            throw new \RuntimeException("Channel {$this->id} recv queue is full");
        }
        
        $this->recvQueue[] = $data;
    }
    
    /**
     * 获取待发送消息（并清空队列）
     */
    public function getSendQueue(): array
    {
        $queue = $this->sendQueue;
        $this->sendQueue = [];
        return $queue;
    }
    
    /**
     * 获取待发送消息数量
     */
    public function getSendQueueSize(): int
    {
        return count($this->sendQueue);
    }
    
    /**
     * 获取待接收消息数量
     */
    public function getRecvQueueSize(): int
    {
        return count($this->recvQueue);
    }
    
    /**
     * 关闭通道
     */
    public function close(): void
    {
        $this->closed = true;
        
        // 取消所有等待的 Future
        foreach ($this->waitingFutures as $future) {
            $future->setResult(null);
        }
        
        $this->sendQueue = [];
        $this->recvQueue = [];
        $this->waitingFutures = [];
    }
    
    /**
     * 检查通道是否已关闭
     */
    public function isClosed(): bool
    {
        return $this->closed;
    }
    
    /**
     * 获取通道统计信息
     */
    public function getStats(): array
    {
        return [
            'id' => $this->id,
            'node_id' => $this->nodeId,
            'closed' => $this->closed,
            'send_queue_size' => count($this->sendQueue),
            'recv_queue_size' => count($this->recvQueue),
            'waiting_futures' => count($this->waitingFutures),
            'send_count' => $this->sendCount,
            'recv_count' => $this->recvCount,
            'created_at' => $this->createdAt,
            'lifetime' => microtime(true) - $this->createdAt,
        ];
    }
}

