<?php

namespace PfinalClub\AsyncioHeartbeat\Utils;

/**
 * 配置验证器 - 验证服务器和客户端配置的正确性
 */
class ConfigValidator
{
    /**
     * 验证服务器配置
     */
    public static function validateServerConfig(array $config): void
    {
        // 必需的配置项
        $requiredKeys = [
            'worker_count' => 'int',
            'heartbeat_timeout' => 'numeric',
            'heartbeat_check_interval' => 'numeric',
        ];
        
        foreach ($requiredKeys as $key => $type) {
            if (!isset($config[$key])) {
                throw new \InvalidArgumentException("Missing required config key: {$key}");
            }
            
            self::validateType($config[$key], $type, $key);
        }
        
        // 验证具体值的合理性
        if ($config['worker_count'] < 1) {
            throw new \InvalidArgumentException('worker_count must be >= 1');
        }
        
        if ($config['worker_count'] > 128) {
            throw new \InvalidArgumentException('worker_count should not exceed 128');
        }
        
        if ($config['heartbeat_timeout'] < 1) {
            throw new \InvalidArgumentException('heartbeat_timeout must be >= 1 second');
        }
        
        if ($config['heartbeat_check_interval'] < 1) {
            throw new \InvalidArgumentException('heartbeat_check_interval must be >= 1 second');
        }
        
        if ($config['heartbeat_check_interval'] >= $config['heartbeat_timeout']) {
            throw new \InvalidArgumentException(
                'heartbeat_check_interval should be less than heartbeat_timeout'
            );
        }
        
        if (isset($config['max_connections'])) {
            if (!is_int($config['max_connections']) || $config['max_connections'] < 1) {
                throw new \InvalidArgumentException('max_connections must be a positive integer');
            }
        }
        
        if (isset($config['channel_max_queue_size'])) {
            if (!is_int($config['channel_max_queue_size']) || $config['channel_max_queue_size'] < 1) {
                throw new \InvalidArgumentException('channel_max_queue_size must be a positive integer');
            }
        }
    }
    
    /**
     * 验证客户端配置
     */
    public static function validateClientConfig(array $config): void
    {
        if (isset($config['heartbeat_interval'])) {
            if (!is_numeric($config['heartbeat_interval']) || $config['heartbeat_interval'] < 1) {
                throw new \InvalidArgumentException('heartbeat_interval must be >= 1 second');
            }
        }
        
        if (isset($config['reconnect_interval'])) {
            if (!is_numeric($config['reconnect_interval']) || $config['reconnect_interval'] < 1) {
                throw new \InvalidArgumentException('reconnect_interval must be >= 1 second');
            }
        }
        
        if (isset($config['max_reconnect_attempts'])) {
            if (!is_int($config['max_reconnect_attempts']) || $config['max_reconnect_attempts'] < 0) {
                throw new \InvalidArgumentException('max_reconnect_attempts must be >= 0');
            }
        }
        
        if (isset($config['auto_reconnect'])) {
            if (!is_bool($config['auto_reconnect'])) {
                throw new \InvalidArgumentException('auto_reconnect must be a boolean');
            }
        }
    }
    
    /**
     * 验证类型
     */
    private static function validateType(mixed $value, string $expectedType, string $key): void
    {
        $valid = match($expectedType) {
            'int' => is_int($value),
            'float' => is_float($value),
            'string' => is_string($value),
            'bool' => is_bool($value),
            'array' => is_array($value),
            'numeric' => is_numeric($value),
            default => true,
        };
        
        if (!$valid) {
            throw new \InvalidArgumentException(
                "Config key '{$key}' must be of type {$expectedType}, " . 
                gettype($value) . " given"
            );
        }
    }
    
    /**
     * 验证主机名
     */
    public static function validateHost(string $host): bool
    {
        // 允许 IPv4、IPv6 和域名
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return true;
        }
        
        if (filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * 验证端口号
     */
    public static function validatePort(int $port): bool
    {
        return $port >= 1 && $port <= 65535;
    }
    
    /**
     * 获取推荐的配置
     */
    public static function getRecommendedServerConfig(): array
    {
        $cpuCount = self::getCpuCount();
        
        return [
            'worker_count' => $cpuCount,
            'heartbeat_timeout' => 60,
            'heartbeat_check_interval' => 20,
            'max_connections' => 1000000,
            'channel_max_queue_size' => 1000,
        ];
    }
    
    /**
     * 获取 CPU 核心数
     */
    private static function getCpuCount(): int
    {
        $cpuCount = 1;
        
        if (is_file('/proc/cpuinfo')) {
            $cpuinfo = file_get_contents('/proc/cpuinfo');
            preg_match_all('/^processor/m', $cpuinfo, $matches);
            $cpuCount = count($matches[0]);
        } elseif (DIRECTORY_SEPARATOR === '\\') {
            // Windows
            $process = @popen('wmic cpu get NumberOfCores', 'rb');
            if ($process !== false) {
                fgets($process);
                $cpuCount = (int) fgets($process);
                pclose($process);
            }
        } else {
            // macOS and other Unix
            $process = @popen('sysctl -n hw.ncpu', 'rb');
            if ($process !== false) {
                $cpuCount = (int) fgets($process);
                pclose($process);
            }
        }
        
        return max(1, $cpuCount);
    }
}

