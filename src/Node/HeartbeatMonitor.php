<?php

namespace PfinalClub\AsyncioHeartbeat\Node;

use function PfinalClub\Asyncio\{create_task, sleep};

/**
 * 心跳监控器 - 定期检查节点心跳状态
 */
class HeartbeatMonitor
{
    private bool $running = false;
    private ?object $task = null;
    private array $timeoutCallbacks = [];
    private int $checkCount = 0;
    
    public function __construct(
        private NodeManager $nodeManager,
        private float $checkInterval = 10.0
    ) {}
    
    /**
     * 启动监控
     */
    public function start(): void
    {
        if ($this->running) {
            return;
        }
        
        $this->running = true;
        
        $this->task = create_task(function() {
            $this->monitorLoop();
        });
    }
    
    /**
     * 停止监控
     */
    public function stop(): void
    {
        $this->running = false;
    }
    
    /**
     * 监控循环
     */
    private function monitorLoop(): void
    {
        while ($this->running) {
            sleep($this->checkInterval);
            
            $this->checkCount++;
            $timeoutNodes = $this->nodeManager->checkTimeout();
            
            if (!empty($timeoutNodes)) {
                $this->handleTimeoutNodes($timeoutNodes);
            }
        }
    }
    
    /**
     * 处理超时节点
     */
    private function handleTimeoutNodes(array $nodeIds): void
    {
        foreach ($nodeIds as $nodeId) {
            $node = $this->nodeManager->getNode($nodeId);
            
            if ($node) {
                // 触发超时回调
                foreach ($this->timeoutCallbacks as $callback) {
                    try {
                        $callback($node);
                    } catch (\Exception $e) {
                        // 忽略回调错误
                    }
                }
            }
        }
    }
    
    /**
     * 注册超时回调
     */
    public function onTimeout(callable $callback): void
    {
        $this->timeoutCallbacks[] = $callback;
    }
    
    /**
     * 清除所有超时回调
     */
    public function clearTimeoutCallbacks(): void
    {
        $this->timeoutCallbacks = [];
    }
    
    /**
     * 设置检查间隔
     */
    public function setCheckInterval(float $interval): void
    {
        $this->checkInterval = $interval;
    }
    
    /**
     * 获取检查间隔
     */
    public function getCheckInterval(): float
    {
        return $this->checkInterval;
    }
    
    /**
     * 是否正在运行
     */
    public function isRunning(): bool
    {
        return $this->running;
    }
    
    /**
     * 获取统计信息
     */
    public function getStats(): array
    {
        return [
            'running' => $this->running,
            'check_interval' => $this->checkInterval,
            'check_count' => $this->checkCount,
            'timeout_callbacks' => count($this->timeoutCallbacks),
        ];
    }
}

