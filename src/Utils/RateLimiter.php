<?php

namespace PfinalClub\AsyncioHeartbeat\Utils;

/**
 * 限流器 - 使用令牌桶算法防止系统过载
 */
class RateLimiter
{
    private array $buckets = [];
    private int $capacity;
    private float $refillRate;
    
    /**
     * @param int $capacity 桶容量（最大令牌数）
     * @param float $refillRate 每秒补充的令牌数
     */
    public function __construct(int $capacity = 100, float $refillRate = 10.0)
    {
        $this->capacity = $capacity;
        $this->refillRate = $refillRate;
    }
    
    /**
     * 尝试获取令牌
     * 
     * @param string $key 限流的键（如节点ID、IP地址等）
     * @param int $tokens 需要的令牌数
     * @return bool 是否成功获取令牌
     */
    public function allow(string $key, int $tokens = 1): bool
    {
        $now = microtime(true);
        
        if (!isset($this->buckets[$key])) {
            $this->buckets[$key] = [
                'tokens' => $this->capacity,
                'last_refill' => $now,
            ];
        }
        
        $bucket = &$this->buckets[$key];
        
        // 计算应该补充的令牌数
        $timePassed = $now - $bucket['last_refill'];
        $tokensToAdd = $timePassed * $this->refillRate;
        
        if ($tokensToAdd > 0) {
            $bucket['tokens'] = min($this->capacity, $bucket['tokens'] + $tokensToAdd);
            $bucket['last_refill'] = $now;
        }
        
        // 检查是否有足够的令牌
        if ($bucket['tokens'] >= $tokens) {
            $bucket['tokens'] -= $tokens;
            return true;
        }
        
        return false;
    }
    
    /**
     * 重置指定键的限流器
     */
    public function reset(string $key): void
    {
        if (isset($this->buckets[$key])) {
            $this->buckets[$key] = [
                'tokens' => $this->capacity,
                'last_refill' => microtime(true),
            ];
        }
    }
    
    /**
     * 清除所有限流记录
     */
    public function clear(): void
    {
        $this->buckets = [];
    }
    
    /**
     * 获取指定键的剩余令牌数
     */
    public function getTokens(string $key): float
    {
        if (!isset($this->buckets[$key])) {
            return $this->capacity;
        }
        
        $now = microtime(true);
        $bucket = $this->buckets[$key];
        
        // 计算当前应该有的令牌数
        $timePassed = $now - $bucket['last_refill'];
        $tokensToAdd = $timePassed * $this->refillRate;
        
        return min($this->capacity, $bucket['tokens'] + $tokensToAdd);
    }
    
    /**
     * 清理长时间未使用的桶（节省内存）
     */
    public function cleanup(float $ttl = 300.0): int
    {
        $now = microtime(true);
        $cleaned = 0;
        
        foreach ($this->buckets as $key => $bucket) {
            if (($now - $bucket['last_refill']) > $ttl) {
                unset($this->buckets[$key]);
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
        $totalTokens = 0;
        $activeBuckets = count($this->buckets);
        
        foreach ($this->buckets as $bucket) {
            $totalTokens += $bucket['tokens'];
        }
        
        return [
            'active_buckets' => $activeBuckets,
            'total_tokens' => $totalTokens,
            'capacity_per_bucket' => $this->capacity,
            'refill_rate' => $this->refillRate,
            'avg_tokens' => $activeBuckets > 0 ? $totalTokens / $activeBuckets : 0,
        ];
    }
}

