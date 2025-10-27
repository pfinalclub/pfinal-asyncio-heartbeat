<?php

namespace PfinalClub\AsyncioHeartbeat\Utils;

/**
 * 指标统计 - Prometheus 格式
 */
class Metrics
{
    private static ?Metrics $instance = null;
    
    private array $counters = [];
    private array $gauges = [];
    private array $histograms = [];
    private float $startTime;
    
    private function __construct()
    {
        $this->startTime = microtime(true);
    }
    
    /**
     * 获取单例实例
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    /**
     * 增加计数器
     */
    public function incCounter(string $name, int $value = 1, array $labels = []): void
    {
        $key = $this->getKey($name, $labels);
        
        if (!isset($this->counters[$name])) {
            $this->counters[$name] = [];
        }
        
        if (!isset($this->counters[$name][$key])) {
            $this->counters[$name][$key] = ['value' => 0, 'labels' => $labels];
        }
        
        $this->counters[$name][$key]['value'] += $value;
    }
    
    /**
     * 设置仪表值
     */
    public function setGauge(string $name, float $value, array $labels = []): void
    {
        $key = $this->getKey($name, $labels);
        
        if (!isset($this->gauges[$name])) {
            $this->gauges[$name] = [];
        }
        
        $this->gauges[$name][$key] = ['value' => $value, 'labels' => $labels];
    }
    
    /**
     * 记录直方图值
     */
    public function observeHistogram(string $name, float $value, array $labels = []): void
    {
        $key = $this->getKey($name, $labels);
        
        if (!isset($this->histograms[$name])) {
            $this->histograms[$name] = [];
        }
        
        if (!isset($this->histograms[$name][$key])) {
            $this->histograms[$name][$key] = [
                'values' => [],
                'sum' => 0,
                'count' => 0,
                'labels' => $labels,
            ];
        }
        
        $this->histograms[$name][$key]['values'][] = $value;
        $this->histograms[$name][$key]['sum'] += $value;
        $this->histograms[$name][$key]['count']++;
    }
    
    /**
     * 导出 Prometheus 格式指标
     */
    public function export(): string
    {
        $output = [];
        
        // 运行时间
        $uptime = microtime(true) - $this->startTime;
        $output[] = "# HELP heartbeat_uptime_seconds Server uptime in seconds";
        $output[] = "# TYPE heartbeat_uptime_seconds gauge";
        $output[] = "heartbeat_uptime_seconds {$uptime}";
        $output[] = "";
        
        // 计数器
        foreach ($this->counters as $name => $metrics) {
            $output[] = "# HELP {$name} Counter metric";
            $output[] = "# TYPE {$name} counter";
            
            foreach ($metrics as $metric) {
                $labels = $this->formatLabels($metric['labels']);
                $output[] = "{$name}{$labels} {$metric['value']}";
            }
            
            $output[] = "";
        }
        
        // 仪表
        foreach ($this->gauges as $name => $metrics) {
            $output[] = "# HELP {$name} Gauge metric";
            $output[] = "# TYPE {$name} gauge";
            
            foreach ($metrics as $metric) {
                $labels = $this->formatLabels($metric['labels']);
                $output[] = "{$name}{$labels} {$metric['value']}";
            }
            
            $output[] = "";
        }
        
        // 直方图
        foreach ($this->histograms as $name => $metrics) {
            $output[] = "# HELP {$name} Histogram metric";
            $output[] = "# TYPE {$name} histogram";
            
            foreach ($metrics as $metric) {
                $labels = $this->formatLabels($metric['labels']);
                $output[] = "{$name}_sum{$labels} {$metric['sum']}";
                $output[] = "{$name}_count{$labels} {$metric['count']}";
                
                // 计算分位数
                if (!empty($metric['values'])) {
                    sort($metric['values']);
                    $p50 = $this->percentile($metric['values'], 0.5);
                    $p95 = $this->percentile($metric['values'], 0.95);
                    $p99 = $this->percentile($metric['values'], 0.99);
                    
                    $output[] = "{$name}{$this->formatLabels(array_merge($metric['labels'], ['quantile' => '0.5']))} {$p50}";
                    $output[] = "{$name}{$this->formatLabels(array_merge($metric['labels'], ['quantile' => '0.95']))} {$p95}";
                    $output[] = "{$name}{$this->formatLabels(array_merge($metric['labels'], ['quantile' => '0.99']))} {$p99}";
                }
            }
            
            $output[] = "";
        }
        
        return implode("\n", $output);
    }
    
    /**
     * 导出 JSON 格式指标
     */
    public function exportJson(): string
    {
        $data = [
            'uptime' => microtime(true) - $this->startTime,
            'counters' => $this->counters,
            'gauges' => $this->gauges,
            'histograms' => $this->histograms,
            'timestamp' => microtime(true),
        ];
        
        return json_encode($data, JSON_PRETTY_PRINT);
    }
    
    /**
     * 重置所有指标
     */
    public function reset(): void
    {
        $this->counters = [];
        $this->gauges = [];
        $this->histograms = [];
        $this->startTime = microtime(true);
    }
    
    /**
     * 获取键名
     */
    private function getKey(string $name, array $labels): string
    {
        ksort($labels);
        return $name . '_' . md5(json_encode($labels));
    }
    
    /**
     * 格式化标签
     */
    private function formatLabels(array $labels): string
    {
        if (empty($labels)) {
            return '';
        }
        
        $parts = [];
        foreach ($labels as $key => $value) {
            $parts[] = "{$key}=\"{$value}\"";
        }
        
        return '{' . implode(',', $parts) . '}';
    }
    
    /**
     * 计算百分位数
     */
    private function percentile(array $values, float $percentile): float
    {
        $count = count($values);
        $index = (int) ceil($count * $percentile) - 1;
        $index = max(0, min($index, $count - 1));
        
        return $values[$index];
    }
    
    /**
     * 获取所有指标
     */
    public function getAll(): array
    {
        return [
            'counters' => $this->counters,
            'gauges' => $this->gauges,
            'histograms' => $this->histograms,
        ];
    }
}

