<?php

namespace PfinalClub\AsyncioHeartbeat\Server;

use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use PfinalClub\AsyncioHeartbeat\Node\{Node, NodeManager, HeartbeatMonitor};
use PfinalClub\AsyncioHeartbeat\Channel\{ChannelScheduler, MessageRouter};
use PfinalClub\AsyncioHeartbeat\Protocol\Message;
use PfinalClub\AsyncioHeartbeat\Utils\{Logger, Metrics};
use function PfinalClub\Asyncio\{run, create_task, sleep};

/**
 * 心跳服务器 - 支持百万级并发连接
 */
class HeartbeatServer
{
    private Worker $worker;
    private NodeManager $nodeManager;
    private ChannelScheduler $channelScheduler;
    private MessageRouter $messageRouter;
    private ConnectionPool $connectionPool;
    private HeartbeatMonitor $monitor;
    private Logger $logger;
    private Metrics $metrics;
    private TcpMultiplexServer $multiplexServer;
    
    public function __construct(
        private string $host = '0.0.0.0',
        private int $port = 9501,
        private array $config = []
    ) {
        $this->config = array_merge([
            'worker_count' => 4,
            'heartbeat_timeout' => 30,
            'heartbeat_check_interval' => 10,
            'max_connections' => 1000000,
            'protocol' => 'tcp',
        ], $config);
        
        $this->logger = Logger::getInstance();
        $this->metrics = Metrics::getInstance();
        $this->nodeManager = new NodeManager($this->config['heartbeat_timeout']);
        $this->channelScheduler = new ChannelScheduler();
        $this->messageRouter = new MessageRouter($this->channelScheduler);
        $this->connectionPool = new ConnectionPool($this->config['max_connections']);
        $this->multiplexServer = new TcpMultiplexServer();
        $this->monitor = new HeartbeatMonitor(
            $this->nodeManager,
            $this->config['heartbeat_check_interval']
        );
        
        $this->setupMonitorCallbacks();
    }
    
    /**
     * 启动服务器
     */
    public function start(): void
    {
        $this->worker = new Worker("{$this->config['protocol']}://{$this->host}:{$this->port}");
        $this->worker->count = $this->config['worker_count'];
        $this->worker->name = 'HeartbeatServer';
        $this->worker->reusePort = true;
        
        // 设置协议
        $this->worker->onMessage = [$this, 'onMessage'];
        
        // 连接建立回调
        $this->worker->onConnect = function(TcpConnection $connection) {
            try {
                $connectionId = $this->connectionPool->add($connection);
                
                $this->logger->info("Connection established", [
                    'connection_id' => $connectionId,
                    'ip' => $connection->getRemoteIp(),
                    'port' => $connection->getRemotePort(),
                ]);
                
                $this->metrics->incCounter('heartbeat_connections_total');
                $this->metrics->setGauge('heartbeat_connections_active', $this->connectionPool->count());
                
            } catch (\Exception $e) {
                $this->logger->error("Failed to add connection: {$e->getMessage()}");
                $connection->close();
            }
        };
        
        // 连接断开回调
        $this->worker->onClose = function(TcpConnection $connection) {
            $this->handleClose($connection);
        };
        
        // 进程启动回调
        $this->worker->onWorkerStart = function() {
            $this->logger->info("Worker started", [
                'worker_id' => $this->worker->id,
                'pid' => posix_getpid(),
            ]);
            
            // 启动心跳监控
            run(function() {
                $this->monitor->start();
                $this->startStatsReporter();
            });
        };
        
        // 进程停止回调
        $this->worker->onWorkerStop = function() {
            $this->logger->info("Worker stopping", [
                'worker_id' => $this->worker->id,
            ]);
            
            $this->monitor->stop();
        };
        
        $this->logger->info("HeartbeatServer starting", [
            'host' => $this->host,
            'port' => $this->port,
            'workers' => $this->config['worker_count'],
            'protocol' => $this->config['protocol'],
        ]);
        
        Worker::runAll();
    }
    
    /**
     * 消息处理回调
     */
    public function onMessage(TcpConnection $connection, string $data): void
    {
        run(function() use ($connection, $data) {
            $this->handleMessage($connection, $data);
        });
    }
    
    /**
     * 处理消息
     */
    private function handleMessage(TcpConnection $connection, string $data): void
    {
        $startTime = microtime(true);
        
        try {
            $message = Message::decode($data);
            
            if (!$message) {
                $this->logger->warning("Invalid message format");
                return;
            }
            
            $this->metrics->incCounter('heartbeat_messages_total', 1, [
                'type' => $message->getTypeName()
            ]);
            
            switch ($message->type) {
                case Message::TYPE_REGISTER:
                    $this->handleRegister($connection, $message);
                    break;
                    
                case Message::TYPE_HEARTBEAT_REQ:
                    $this->handleHeartbeat($connection, $message);
                    break;
                    
                case Message::TYPE_DATA:
                    $this->handleData($connection, $message);
                    break;
                    
                case Message::TYPE_PING:
                    $this->handlePing($connection);
                    break;
                    
                case Message::TYPE_UNREGISTER:
                    $this->handleUnregister($connection, $message);
                    break;
                    
                default:
                    $this->logger->warning("Unknown message type: {$message->type}");
            }
            
            $duration = microtime(true) - $startTime;
            $this->metrics->observeHistogram('heartbeat_message_duration_seconds', $duration);
            
        } catch (\Exception $e) {
            $this->logger->error("Failed to handle message: {$e->getMessage()}", [
                'trace' => $e->getTraceAsString(),
            ]);
            
            $this->metrics->incCounter('heartbeat_errors_total');
        }
    }
    
    /**
     * 处理节点注册
     */
    private function handleRegister(TcpConnection $connection, Message $message): void
    {
        $payload = json_decode($message->payload, true);
        $nodeId = $payload['node_id'] ?? hb_generate_node_id();
        $metadata = $payload['metadata'] ?? [];
        
        $node = new Node(
            $nodeId,
            $connection->getRemoteIp(),
            $connection->getRemotePort(),
            $connection->id
        );
        
        foreach ($metadata as $key => $value) {
            $node->setMetadata($key, $value);
        }
        
        $this->nodeManager->register($node);
        
        // 创建默认通道
        $channel = $this->channelScheduler->createChannel($nodeId);
        
        // 发送注册成功响应
        $response = Message::createRegister($nodeId, [
            'channel_id' => $channel->id,
            'status' => 'ok',
            'server_time' => microtime(true),
        ]);
        
        $connection->send($response->encode());
        
        $this->logger->info("Node registered", [
            'node_id' => $nodeId,
            'connection_id' => $connection->id,
            'channel_id' => $channel->id,
        ]);
        
        $this->metrics->incCounter('heartbeat_nodes_registered_total');
        $this->metrics->setGauge('heartbeat_nodes_online', $this->nodeManager->getOnlineNodeCount());
    }
    
    /**
     * 处理心跳
     */
    private function handleHeartbeat(TcpConnection $connection, Message $message): void
    {
        $node = $this->nodeManager->getNodeByConnection($connection->id);
        
        if ($node) {
            $this->nodeManager->updateHeartbeat($node->id);
            
            // 发送心跳响应
            $response = Message::createHeartbeatResponse();
            $connection->send($response->encode());
            
            $this->metrics->incCounter('heartbeat_received_total');
        } else {
            $this->logger->warning("Heartbeat from unregistered connection", [
                'connection_id' => $connection->id,
            ]);
        }
    }
    
    /**
     * 处理数据消息
     */
    private function handleData(TcpConnection $connection, Message $message): void
    {
        $node = $this->nodeManager->getNodeByConnection($connection->id);
        
        if ($node) {
            $this->nodeManager->updateMessage($node->id);
            
            // 路由消息到对应通道
            $this->messageRouter->route($message);
            
            $this->metrics->incCounter('heartbeat_data_messages_total');
        }
    }
    
    /**
     * 处理 Ping
     */
    private function handlePing(TcpConnection $connection): void
    {
        $response = new Message(Message::TYPE_PONG);
        $connection->send($response->encode());
    }
    
    /**
     * 处理节点注销
     */
    private function handleUnregister(TcpConnection $connection, Message $message): void
    {
        $node = $this->nodeManager->getNodeByConnection($connection->id);
        
        if ($node) {
            $this->logger->info("Node unregistered", [
                'node_id' => $node->id,
                'connection_id' => $connection->id,
            ]);
            
            $this->channelScheduler->closeNodeChannels($node->id);
            $this->nodeManager->unregisterByConnection($connection->id);
            
            $this->metrics->incCounter('heartbeat_nodes_unregistered_total');
            $this->metrics->setGauge('heartbeat_nodes_online', $this->nodeManager->getOnlineNodeCount());
        }
    }
    
    /**
     * 处理连接关闭
     */
    private function handleClose(TcpConnection $connection): void
    {
        $node = $this->nodeManager->getNodeByConnection($connection->id);
        
        if ($node) {
            $this->logger->info("Node disconnected", [
                'node_id' => $node->id,
                'connection_id' => $connection->id,
            ]);
            
            $this->channelScheduler->closeNodeChannels($node->id);
            $this->nodeManager->unregisterByConnection($connection->id);
            
            $this->metrics->setGauge('heartbeat_nodes_online', $this->nodeManager->getOnlineNodeCount());
        }
        
        $this->connectionPool->remove($connection->id);
        $this->metrics->setGauge('heartbeat_connections_active', $this->connectionPool->count());
    }
    
    /**
     * 设置监控回调
     */
    private function setupMonitorCallbacks(): void
    {
        $this->monitor->onTimeout(function(Node $node) {
            $this->logger->warning("Node timeout", [
                'node_id' => $node->id,
                'last_heartbeat' => $node->getSecondsSinceLastHeartbeat(),
            ]);
            
            $this->metrics->incCounter('heartbeat_nodes_timeout_total');
            
            // 关闭节点通道
            $this->channelScheduler->closeNodeChannels($node->id);
            
            // 关闭连接
            $connection = $this->connectionPool->get($node->connectionId);
            if ($connection) {
                $connection->close();
            }
        });
    }
    
    /**
     * 启动统计报告器
     */
    private function startStatsReporter(): void
    {
        create_task(function() {
            while (true) {
                sleep(60); // 每分钟报告一次
                
                $nodeStats = $this->nodeManager->getStatsSummary();
                $channelStats = $this->channelScheduler->getStatsSummary();
                $connectionStats = $this->connectionPool->getStats();
                
                $this->logger->info("Server stats", [
                    'nodes' => $nodeStats,
                    'channels' => $channelStats,
                    'connections' => $connectionStats,
                    'memory' => hb_memory_usage(),
                ]);
                
                // 更新指标
                $this->metrics->setGauge('heartbeat_nodes_total', $nodeStats['total']);
                $this->metrics->setGauge('heartbeat_nodes_online', $nodeStats['online']);
                $this->metrics->setGauge('heartbeat_channels_active', $channelStats['active_channels']);
                $this->metrics->setGauge('heartbeat_connections_active', $connectionStats['active_connections']);
            }
        });
    }
    
    /**
     * 获取统计信息
     */
    public function getStats(): array
    {
        return [
            'server' => [
                'host' => $this->host,
                'port' => $this->port,
                'workers' => $this->config['worker_count'],
                'protocol' => $this->config['protocol'],
            ],
            'nodes' => $this->nodeManager->getStatsSummary(),
            'channels' => $this->channelScheduler->getStatsSummary(),
            'connections' => $this->connectionPool->getStats(),
            'router' => $this->messageRouter->getStats(),
            'monitor' => $this->monitor->getStats(),
        ];
    }
    
    /**
     * 获取节点管理器
     */
    public function getNodeManager(): NodeManager
    {
        return $this->nodeManager;
    }
    
    /**
     * 获取通道调度器
     */
    public function getChannelScheduler(): ChannelScheduler
    {
        return $this->channelScheduler;
    }
}

