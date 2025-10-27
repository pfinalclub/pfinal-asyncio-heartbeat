<?php

namespace PfinalClub\AsyncioHeartbeat\Utils;

/**
 * 日志记录器
 */
class Logger
{
    private static ?Logger $instance = null;
    
    public const LEVEL_DEBUG = 0;
    public const LEVEL_INFO = 1;
    public const LEVEL_WARNING = 2;
    public const LEVEL_ERROR = 3;
    
    private int $level = self::LEVEL_INFO;
    private bool $enabled = true;
    private ?string $logFile = null;
    private array $buffer = [];
    private int $bufferSize = 100;
    
    private function __construct() {}
    
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
     * 设置日志级别
     */
    public function setLevel(int $level): void
    {
        $this->level = $level;
    }
    
    /**
     * 启用日志
     */
    public function enable(): void
    {
        $this->enabled = true;
    }
    
    /**
     * 禁用日志
     */
    public function disable(): void
    {
        $this->enabled = false;
    }
    
    /**
     * 设置日志文件
     */
    public function setLogFile(?string $file): void
    {
        $this->logFile = $file;
    }
    
    /**
     * 设置缓冲区大小
     */
    public function setBufferSize(int $size): void
    {
        $this->bufferSize = $size;
    }
    
    /**
     * Debug 日志
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_DEBUG, $message, $context);
    }
    
    /**
     * Info 日志
     */
    public function info(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_INFO, $message, $context);
    }
    
    /**
     * Warning 日志
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_WARNING, $message, $context);
    }
    
    /**
     * Error 日志
     */
    public function error(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_ERROR, $message, $context);
    }
    
    /**
     * 记录日志
     */
    private function log(int $level, string $message, array $context = []): void
    {
        if (!$this->enabled || $level < $this->level) {
            return;
        }
        
        $levelName = $this->getLevelName($level);
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = empty($context) ? '' : ' ' . json_encode($context);
        
        $logMessage = "[{$timestamp}] [{$levelName}] {$message}{$contextStr}";
        
        // 添加到缓冲区
        $this->buffer[] = $logMessage;
        
        // 输出到控制台
        echo $logMessage . PHP_EOL;
        
        // 如果设置了日志文件，写入文件
        if ($this->logFile) {
            file_put_contents($this->logFile, $logMessage . PHP_EOL, FILE_APPEND);
        }
        
        // 如果缓冲区满了，清空一半
        if (count($this->buffer) >= $this->bufferSize) {
            $this->buffer = array_slice($this->buffer, $this->bufferSize / 2);
        }
    }
    
    /**
     * 获取级别名称
     */
    private function getLevelName(int $level): string
    {
        return match($level) {
            self::LEVEL_DEBUG => 'DEBUG',
            self::LEVEL_INFO => 'INFO',
            self::LEVEL_WARNING => 'WARNING',
            self::LEVEL_ERROR => 'ERROR',
            default => 'UNKNOWN',
        };
    }
    
    /**
     * 获取日志缓冲区
     */
    public function getBuffer(): array
    {
        return $this->buffer;
    }
    
    /**
     * 清空日志缓冲区
     */
    public function clearBuffer(): void
    {
        $this->buffer = [];
    }
    
    /**
     * 刷新日志（强制写入文件）
     */
    public function flush(): void
    {
        if ($this->logFile && !empty($this->buffer)) {
            file_put_contents($this->logFile, implode(PHP_EOL, $this->buffer) . PHP_EOL, FILE_APPEND);
            $this->clearBuffer();
        }
    }
}

