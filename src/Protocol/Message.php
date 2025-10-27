<?php

namespace PfinalClub\AsyncioHeartbeat\Protocol;

/**
 * 消息协议
 * 
 * 二进制格式（高性能）:
 * +--------+--------+--------+-------------+
 * | Magic  | Type   | Length | Payload     |
 * | 2 byte | 1 byte | 4 byte | N bytes     |
 * +--------+--------+--------+-------------+
 */
class Message
{
    /** 魔数，用于协议识别 */
    public const MAGIC = 0xAB12;
    
    /** 消息类型定义 */
    public const TYPE_HEARTBEAT_REQ = 0x01;  // 心跳请求
    public const TYPE_HEARTBEAT_RES = 0x02;  // 心跳响应
    public const TYPE_DATA = 0x03;           // 数据消息
    public const TYPE_PING = 0x04;           // Ping
    public const TYPE_PONG = 0x05;           // Pong
    public const TYPE_REGISTER = 0x06;       // 节点注册
    public const TYPE_UNREGISTER = 0x07;     // 节点注销
    public const TYPE_ERROR = 0x08;          // 错误消息
    
    /** 头部长度（字节） */
    public const HEADER_LENGTH = 7;
    
    public function __construct(
        public int $type,
        public string $payload = '',
        public int $channelId = 0
    ) {}
    
    /**
     * 编码消息为二进制
     */
    public function encode(): string
    {
        $data = json_encode([
            'channel_id' => $this->channelId,
            'payload' => $this->payload,
        ]);
        
        $length = strlen($data);
        
        // pack: n=unsigned short (big endian), C=unsigned char, N=unsigned long (big endian)
        return pack('nCN', self::MAGIC, $this->type, $length) . $data;
    }
    
    /**
     * 从二进制解码消息
     */
    public static function decode(string $buffer): ?self
    {
        if (strlen($buffer) < self::HEADER_LENGTH) {
            return null;
        }
        
        $header = unpack('nmagic/Ctype/Nlength', substr($buffer, 0, self::HEADER_LENGTH));
        
        if ($header['magic'] !== self::MAGIC) {
            throw new \RuntimeException('Invalid message magic: 0x' . dechex($header['magic']));
        }
        
        if (strlen($buffer) < self::HEADER_LENGTH + $header['length']) {
            return null; // 数据不完整
        }
        
        $data = json_decode(substr($buffer, self::HEADER_LENGTH, $header['length']), true);
        
        if (!is_array($data)) {
            throw new \RuntimeException('Invalid message payload');
        }
        
        return new self(
            $header['type'],
            $data['payload'] ?? '',
            $data['channel_id'] ?? 0
        );
    }
    
    /**
     * 获取完整消息包的长度
     */
    public static function getPackageLength(string $buffer): int
    {
        if (strlen($buffer) < self::HEADER_LENGTH) {
            return 0; // 需要更多数据
        }
        
        $header = unpack('nmagic/Ctype/Nlength', substr($buffer, 0, self::HEADER_LENGTH));
        
        if ($header['magic'] !== self::MAGIC) {
            return -1; // 无效的包
        }
        
        return self::HEADER_LENGTH + $header['length'];
    }
    
    /**
     * 获取消息类型名称
     */
    public function getTypeName(): string
    {
        return match($this->type) {
            self::TYPE_HEARTBEAT_REQ => 'HEARTBEAT_REQ',
            self::TYPE_HEARTBEAT_RES => 'HEARTBEAT_RES',
            self::TYPE_DATA => 'DATA',
            self::TYPE_PING => 'PING',
            self::TYPE_PONG => 'PONG',
            self::TYPE_REGISTER => 'REGISTER',
            self::TYPE_UNREGISTER => 'UNREGISTER',
            self::TYPE_ERROR => 'ERROR',
            default => 'UNKNOWN',
        };
    }
    
    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'type_name' => $this->getTypeName(),
            'channel_id' => $this->channelId,
            'payload' => $this->payload,
        ];
    }
    
    /**
     * 创建心跳请求
     */
    public static function createHeartbeatRequest(): self
    {
        return new self(
            self::TYPE_HEARTBEAT_REQ,
            json_encode(['timestamp' => microtime(true)])
        );
    }
    
    /**
     * 创建心跳响应
     */
    public static function createHeartbeatResponse(): self
    {
        return new self(
            self::TYPE_HEARTBEAT_RES,
            json_encode(['timestamp' => microtime(true)])
        );
    }
    
    /**
     * 创建注册消息
     */
    public static function createRegister(string $nodeId, array $metadata = []): self
    {
        return new self(
            self::TYPE_REGISTER,
            json_encode([
                'node_id' => $nodeId,
                'metadata' => $metadata,
                'timestamp' => microtime(true),
            ])
        );
    }
    
    /**
     * 创建数据消息
     */
    public static function createData(string $payload, int $channelId = 0): self
    {
        return new self(self::TYPE_DATA, $payload, $channelId);
    }
    
    /**
     * 创建错误消息
     */
    public static function createError(string $message, int $code = 0): self
    {
        return new self(
            self::TYPE_ERROR,
            json_encode(['message' => $message, 'code' => $code])
        );
    }
}

