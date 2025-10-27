<?php

namespace PfinalClub\AsyncioHeartbeat\Tests\Protocol;

use PHPUnit\Framework\TestCase;
use PfinalClub\AsyncioHeartbeat\Protocol\Message;

class MessageTest extends TestCase
{
    public function testCreateMessage(): void
    {
        $message = new Message(Message::TYPE_HEARTBEAT_REQ, 'test payload', 123);
        
        $this->assertEquals(Message::TYPE_HEARTBEAT_REQ, $message->type);
        $this->assertEquals('test payload', $message->payload);
        $this->assertEquals(123, $message->channelId);
    }
    
    public function testEncodeDecodeMessage(): void
    {
        $original = new Message(Message::TYPE_DATA, 'Hello World', 456);
        $encoded = $original->encode();
        
        $this->assertIsString($encoded);
        $this->assertGreaterThan(Message::HEADER_LENGTH, strlen($encoded));
        
        $decoded = Message::decode($encoded);
        
        $this->assertInstanceOf(Message::class, $decoded);
        $this->assertEquals($original->type, $decoded->type);
        $this->assertEquals($original->payload, $decoded->payload);
        $this->assertEquals($original->channelId, $decoded->channelId);
    }
    
    public function testGetPackageLength(): void
    {
        $message = new Message(Message::TYPE_PING);
        $encoded = $message->encode();
        
        $length = Message::getPackageLength($encoded);
        
        $this->assertEquals(strlen($encoded), $length);
    }
    
    public function testInvalidMagic(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid message magic');
        
        $invalidData = pack('nCN', 0x0000, Message::TYPE_PING, 0);
        Message::decode($invalidData);
    }
    
    public function testIncompleteMessage(): void
    {
        $message = new Message(Message::TYPE_DATA, 'test');
        $encoded = $message->encode();
        
        // 只取前5个字节（不完整）
        $incomplete = substr($encoded, 0, 5);
        
        $decoded = Message::decode($incomplete);
        $this->assertNull($decoded);
    }
    
    public function testGetTypeName(): void
    {
        $message = new Message(Message::TYPE_HEARTBEAT_REQ);
        $this->assertEquals('HEARTBEAT_REQ', $message->getTypeName());
        
        $message = new Message(Message::TYPE_DATA);
        $this->assertEquals('DATA', $message->getTypeName());
        
        $message = new Message(99);
        $this->assertEquals('UNKNOWN', $message->getTypeName());
    }
    
    public function testCreateHeartbeatRequest(): void
    {
        $message = Message::createHeartbeatRequest();
        
        $this->assertEquals(Message::TYPE_HEARTBEAT_REQ, $message->type);
        $this->assertNotEmpty($message->payload);
        
        $payload = json_decode($message->payload, true);
        $this->assertArrayHasKey('timestamp', $payload);
    }
    
    public function testCreateHeartbeatResponse(): void
    {
        $message = Message::createHeartbeatResponse();
        
        $this->assertEquals(Message::TYPE_HEARTBEAT_RES, $message->type);
        $this->assertNotEmpty($message->payload);
    }
    
    public function testCreateRegister(): void
    {
        $message = Message::createRegister('node_123', ['version' => '1.0']);
        
        $this->assertEquals(Message::TYPE_REGISTER, $message->type);
        
        $payload = json_decode($message->payload, true);
        $this->assertEquals('node_123', $payload['node_id']);
        $this->assertEquals(['version' => '1.0'], $payload['metadata']);
    }
    
    public function testCreateData(): void
    {
        $message = Message::createData('test data', 789);
        
        $this->assertEquals(Message::TYPE_DATA, $message->type);
        $this->assertEquals('test data', $message->payload);
        $this->assertEquals(789, $message->channelId);
    }
    
    public function testCreateError(): void
    {
        $message = Message::createError('Test error', 500);
        
        $this->assertEquals(Message::TYPE_ERROR, $message->type);
        
        $payload = json_decode($message->payload, true);
        $this->assertEquals('Test error', $payload['message']);
        $this->assertEquals(500, $payload['code']);
    }
    
    public function testToArray(): void
    {
        $message = new Message(Message::TYPE_DATA, 'payload', 100);
        $array = $message->toArray();
        
        $this->assertIsArray($array);
        $this->assertEquals(Message::TYPE_DATA, $array['type']);
        $this->assertEquals('DATA', $array['type_name']);
        $this->assertEquals(100, $array['channel_id']);
        $this->assertEquals('payload', $array['payload']);
    }
}

