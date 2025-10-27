<?php

/**
 * 辅助函数
 */

use PfinalClub\AsyncioHeartbeat\Utils\Logger;
use PfinalClub\AsyncioHeartbeat\Utils\Metrics;

if (!function_exists('hb_log')) {
    /**
     * 快速日志记录
     */
    function hb_log(string $message, string $level = 'info', array $context = []): void
    {
        $logger = Logger::getInstance();
        
        match ($level) {
            'debug' => $logger->debug($message, $context),
            'info' => $logger->info($message, $context),
            'warning' => $logger->warning($message, $context),
            'error' => $logger->error($message, $context),
            default => $logger->info($message, $context),
        };
    }
}

if (!function_exists('hb_metric_inc')) {
    /**
     * 增加计数器指标
     */
    function hb_metric_inc(string $name, int $value = 1, array $labels = []): void
    {
        Metrics::getInstance()->incCounter($name, $value, $labels);
    }
}

if (!function_exists('hb_metric_set')) {
    /**
     * 设置仪表指标
     */
    function hb_metric_set(string $name, float $value, array $labels = []): void
    {
        Metrics::getInstance()->setGauge($name, $value, $labels);
    }
}

if (!function_exists('hb_metric_observe')) {
    /**
     * 记录直方图指标
     */
    function hb_metric_observe(string $name, float $value, array $labels = []): void
    {
        Metrics::getInstance()->observeHistogram($name, $value, $labels);
    }
}

if (!function_exists('hb_format_bytes')) {
    /**
     * 格式化字节数
     */
    function hb_format_bytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

if (!function_exists('hb_format_duration')) {
    /**
     * 格式化时间长度
     */
    function hb_format_duration(float $seconds): string
    {
        if ($seconds < 60) {
            return round($seconds, 2) . 's';
        }
        
        $minutes = floor($seconds / 60);
        $seconds = $seconds % 60;
        
        if ($minutes < 60) {
            return "{$minutes}m " . round($seconds) . 's';
        }
        
        $hours = floor($minutes / 60);
        $minutes = $minutes % 60;
        
        if ($hours < 24) {
            return "{$hours}h {$minutes}m";
        }
        
        $days = floor($hours / 24);
        $hours = $hours % 24;
        
        return "{$days}d {$hours}h";
    }
}

if (!function_exists('hb_memory_usage')) {
    /**
     * 获取内存使用情况
     */
    function hb_memory_usage(): array
    {
        return [
            'current' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'current_formatted' => hb_format_bytes(memory_get_usage(true)),
            'peak_formatted' => hb_format_bytes(memory_get_peak_usage(true)),
        ];
    }
}

if (!function_exists('hb_generate_node_id')) {
    /**
     * 生成节点 ID
     */
    function hb_generate_node_id(string $prefix = 'node'): string
    {
        return $prefix . '_' . uniqid() . '_' . bin2hex(random_bytes(4));
    }
}

if (!function_exists('hb_benchmark')) {
    /**
     * 执行基准测试
     */
    function hb_benchmark(callable $callback, int $iterations = 1): array
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        for ($i = 0; $i < $iterations; $i++) {
            $callback();
        }
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        
        $duration = $endTime - $startTime;
        $memoryUsed = $endMemory - $startMemory;
        
        return [
            'iterations' => $iterations,
            'total_time' => $duration,
            'avg_time' => $duration / $iterations,
            'memory_used' => $memoryUsed,
            'ops_per_second' => $iterations / $duration,
        ];
    }
}

