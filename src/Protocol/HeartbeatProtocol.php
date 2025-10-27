<?php

namespace PfinalClub\AsyncioHeartbeat\Protocol;

/**
 * 心跳协议规范
 */
class HeartbeatProtocol
{
    /**
     * 协议版本
     */
    public const VERSION = '1.0.0';
    
    /**
     * 默认心跳间隔（秒）
     */
    public const DEFAULT_HEARTBEAT_INTERVAL = 10;
    
    /**
     * 默认心跳超时（秒）
     */
    public const DEFAULT_HEARTBEAT_TIMEOUT = 30;
    
    /**
     * 最大消息长度（10MB）
     */
    public const MAX_MESSAGE_LENGTH = 10 * 1024 * 1024;
    
    /**
     * 最小消息长度
     */
    public const MIN_MESSAGE_LENGTH = Message::HEADER_LENGTH;
    
    /**
     * 最大通道 ID
     */
    public const MAX_CHANNEL_ID = 65535;
    
    /**
     * 验证消息是否有效
     */
    public static function validateMessage(Message $message): bool
    {
        // 检查消息类型
        if (!self::isValidMessageType($message->type)) {
            return false;
        }
        
        // 检查通道 ID
        if ($message->channelId < 0 || $message->channelId > self::MAX_CHANNEL_ID) {
            return false;
        }
        
        // 检查 payload 长度
        if (strlen($message->payload) > self::MAX_MESSAGE_LENGTH) {
            return false;
        }
        
        return true;
    }
    
    /**
     * 检查是否为有效的消息类型
     */
    public static function isValidMessageType(int $type): bool
    {
        return in_array($type, [
            Message::TYPE_HEARTBEAT_REQ,
            Message::TYPE_HEARTBEAT_RES,
            Message::TYPE_DATA,
            Message::TYPE_PING,
            Message::TYPE_PONG,
            Message::TYPE_REGISTER,
            Message::TYPE_UNREGISTER,
            Message::TYPE_ERROR,
        ], true);
    }
    
    /**
     * 计算心跳超时时间
     */
    public static function calculateTimeout(int $heartbeatInterval): int
    {
        // 超时时间 = 心跳间隔 * 3
        return $heartbeatInterval * 3;
    }
    
    /**
     * 获取协议信息
     */
    public static function getInfo(): array
    {
        return [
            'version' => self::VERSION,
            'magic' => sprintf('0x%04X', Message::MAGIC),
            'header_length' => Message::HEADER_LENGTH,
            'max_message_length' => self::MAX_MESSAGE_LENGTH,
            'max_channel_id' => self::MAX_CHANNEL_ID,
            'message_types' => [
                'HEARTBEAT_REQ' => Message::TYPE_HEARTBEAT_REQ,
                'HEARTBEAT_RES' => Message::TYPE_HEARTBEAT_RES,
                'DATA' => Message::TYPE_DATA,
                'PING' => Message::TYPE_PING,
                'PONG' => Message::TYPE_PONG,
                'REGISTER' => Message::TYPE_REGISTER,
                'UNREGISTER' => Message::TYPE_UNREGISTER,
                'ERROR' => Message::TYPE_ERROR,
            ],
        ];
    }
}

