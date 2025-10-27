<?php

namespace PfinalClub\AsyncioHeartbeat\Client;

use Workerman\Connection\AsyncTcpConnection;
use Workerman\Timer;
use PfinalClub\AsyncioHeartbeat\Protocol\Message;
use PfinalClub\AsyncioHeartbeat\Utils\Logger;
use function PfinalClub\Asyncio\{run, create_task, sleep};

/**
 * 心跳客户端
 */
class HeartbeatClient
{
    private ?AsyncTcpConnection $connection = null;
    private ?string $nodeId = null;
    private ?int $channelId = null;
    private bool $connected = false;
    private array $callbacks = [];
    private ?int $heartbeatTimer = null;
    private Logger $logger;
    private TcpMultiplexClient $multiplexClient;
    private int $reconnectAttempts = 0;
    private int $maxReconnectAttempts = 0;
    private int $messagesSent = 0;
    private int $messagesReceived = 0;
    private float $connectedAt = 0;
    
    public function __construct(
        private string $host = '127.0.0.1',
        private int $port = 9501,
        private array $config = []
    ) {
        $this->config = array_merge([
            'heartbeat_interval' => 10,
            'auto_reconnect' => true,
            'reconnect_interval' => 5,
            'max_reconnect_attempts' => 0, // 0 = 无限重连
            'node_id' => null,
            'metadata' => [],
        ], $config);
        
        $this->logger = Logger::getInstance();
        $this->multiplexClient = new TcpMultiplexClient();
        $this->maxReconnectAttempts = $this->config['max_reconnect_attempts'];
    }
    
    /**
     * 连接服务器
     */
    public function connect(): void
    {
        if ($this->connected) {
            return;
        }
        
        $this->connection = new AsyncTcpConnection("tcp://{$this->host}:{$this->port}");
        
        $this->connection->onConnect = function() {
            $this->logger->info("Connected to server", [
                'host' => $this->host,
                'port' => $this->port,
            ]);
            
            $this->connected = true;
            $this->connectedAt = microtime(true);
            $this->reconnectAttempts = 0;
            
            $this->register();
            $this->startHeartbeat();
            
            if (isset($this->callbacks['connect'])) {
                $this->callbacks['connect']();
            }
        };
        
        $this->connection->onMessage = function($connection, $data) {
            run(function() use ($data) {
                $this->handleMessage($data);
            });
        };
        
        $this->connection->onClose = function() {
            $this->logger->info("Connection closed");
            
            $this->connected = false;
            $this->stopHeartbeat();
            $this->multiplexClient->closeAll();
            
            if (isset($this->callbacks['close'])) {
                $this->callbacks['close']();
            }
            
            if ($this->config['auto_reconnect']) {
                $this->scheduleReconnect();
            }
        };
        
        $this->connection->onError = function($connection, $code, $msg) {
            $this->logger->error("Connection error: $msg", ['code' => $code]);
            
            if (isset($this->callbacks['error'])) {
                $this->callbacks['error']($code, $msg);
            }
        };
        
        $this->connection->connect();
    }
    
    /**
     * 注册节点
     */
    private function register(): void
    {
        $nodeId = $this->config['node_id'] ?? hb_generate_node_id('client');
        
        $message = Message::createRegister($nodeId, $this->config['metadata']);
        $this->connection->send($message->encode());
        
        $this->logger->info("Sending registration", ['node_id' => $nodeId]);
    }
    
    /**
     * 处理消息
     */
    private function handleMessage(string $data): void
    {
        try {
            $message = Message::decode($data);
            
            if (!$message) {
                $this->logger->warning("Invalid message format");
                return;
            }
            
            $this->messagesReceived++;
            
            switch ($message->type) {
                case Message::TYPE_REGISTER:
                    $this->handleRegisterResponse($message);
                    break;
                    
                case Message::TYPE_HEARTBEAT_RES:
                    // 心跳响应
                    if (isset($this->callbacks['heartbeat'])) {
                        $this->callbacks['heartbeat']();
                    }
                    break;
                    
                case Message::TYPE_DATA:
                    if (isset($this->callbacks['data'])) {
                        $this->callbacks['data']($message->payload, $message->channelId);
                    }
                    break;
                    
                case Message::TYPE_PONG:
                    if (isset($this->callbacks['pong'])) {
                        $this->callbacks['pong']();
                    }
                    break;
                    
                case Message::TYPE_ERROR:
                    $payload = json_decode($message->payload, true);
                    $this->logger->error("Server error", $payload);
                    break;
            }
            
        } catch (\Exception $e) {
            $this->logger->error("Failed to handle message: {$e->getMessage()}");
        }
    }
    
    /**
     * 处理注册响应
     */
    private function handleRegisterResponse(Message $message): void
    {
        $payload = json_decode($message->payload, true);
        
        $this->nodeId = $payload['node_id'] ?? $payload['channel_id'] ?? null;
        $this->channelId = $payload['channel_id'] ?? 0;
        
        // 创建主通道
        if ($this->channelId > 0 && $this->nodeId) {
            $this->multiplexClient->createChannel($this->channelId, $this->nodeId);
        }
        
        $this->logger->info("Registration successful", [
            'node_id' => $this->nodeId,
            'channel_id' => $this->channelId,
        ]);
        
        if (isset($this->callbacks['register'])) {
            $this->callbacks['register']($this->nodeId, $this->channelId);
        }
    }
    
    /**
     * 启动心跳发送
     */
    private function startHeartbeat(): void
    {
        $this->heartbeatTimer = Timer::add($this->config['heartbeat_interval'], function() {
            if ($this->connected && $this->connection) {
                $message = Message::createHeartbeatRequest();
                $this->connection->send($message->encode());
                $this->messagesSent++;
            }
        });
    }
    
    /**
     * 停止心跳发送
     */
    private function stopHeartbeat(): void
    {
        if ($this->heartbeatTimer !== null) {
            Timer::del($this->heartbeatTimer);
            $this->heartbeatTimer = null;
        }
    }
    
    /**
     * 计划重连
     */
    private function scheduleReconnect(): void
    {
        if ($this->maxReconnectAttempts > 0 && $this->reconnectAttempts >= $this->maxReconnectAttempts) {
            $this->logger->error("Max reconnect attempts reached");
            return;
        }
        
        $this->reconnectAttempts++;
        
        $this->logger->info("Scheduling reconnect", [
            'attempt' => $this->reconnectAttempts,
            'interval' => $this->config['reconnect_interval'],
        ]);
        
        Timer::add($this->config['reconnect_interval'], function() {
            $this->connect();
        }, [], false);
    }
    
    /**
     * 发送数据
     */
    public function send(string $data, int $channelId = 0): void
    {
        if (!$this->connected || !$this->connection) {
            throw new \RuntimeException('Not connected to server');
        }
        
        $message = Message::createData($data, $channelId > 0 ? $channelId : $this->channelId);
        $this->connection->send($message->encode());
        $this->messagesSent++;
    }
    
    /**
     * 发送 Ping
     */
    public function ping(): void
    {
        if (!$this->connected || !$this->connection) {
            throw new \RuntimeException('Not connected to server');
        }
        
        $message = new Message(Message::TYPE_PING);
        $this->connection->send($message->encode());
        $this->messagesSent++;
    }
    
    /**
     * 注册回调
     */
    public function on(string $event, callable $callback): void
    {
        $this->callbacks[$event] = $callback;
    }
    
    /**
     * 断开连接
     */
    public function disconnect(): void
    {
        if ($this->connection) {
            // 发送注销消息
            if ($this->connected) {
                $message = new Message(Message::TYPE_UNREGISTER);
                $this->connection->send($message->encode());
            }
            
            $this->stopHeartbeat();
            $this->connection->close();
            $this->connected = false;
        }
    }
    
    /**
     * 是否已连接
     */
    public function isConnected(): bool
    {
        return $this->connected;
    }
    
    /**
     * 获取节点 ID
     */
    public function getNodeId(): ?string
    {
        return $this->nodeId;
    }
    
    /**
     * 获取通道 ID
     */
    public function getChannelId(): ?int
    {
        return $this->channelId;
    }
    
    /**
     * 获取统计信息
     */
    public function getStats(): array
    {
        return [
            'connected' => $this->connected,
            'node_id' => $this->nodeId,
            'channel_id' => $this->channelId,
            'messages_sent' => $this->messagesSent,
            'messages_received' => $this->messagesReceived,
            'reconnect_attempts' => $this->reconnectAttempts,
            'uptime' => $this->connectedAt > 0 ? microtime(true) - $this->connectedAt : 0,
            'channels' => $this->multiplexClient->getChannelCount(),
        ];
    }
}

