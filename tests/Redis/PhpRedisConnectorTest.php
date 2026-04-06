<?php

namespace Illuminate\Tests\Redis;

use Illuminate\Redis\Connectors\PhpRedisConnector;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class PhpRedisConnectorTest extends TestCase
{
    protected PhpRedisConnector $phpRedisConnector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->phpRedisConnector = new PhpRedisConnector;
    }

    // --- PhpRedis: normalizeContext (single connection) ---

    public function testNormalizeContextWrapsFlatArrayInStream()
    {
        $result = $this->callNormalizeContext([
            'verify_peer' => false,
            'verify_peer_name' => false,
        ]);

        $this->assertSame([
            'stream' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ], $result);
    }

    public function testNormalizeContextConvertsSslKeyToStream()
    {
        $result = $this->callNormalizeContext([
            'ssl' => [
                'verify_peer' => false,
                'cafile' => '/path/to/ca.pem',
            ],
        ]);

        $this->assertSame([
            'stream' => [
                'verify_peer' => false,
                'cafile' => '/path/to/ca.pem',
            ],
        ], $result);
    }

    public function testNormalizeContextPassesThroughStreamKey()
    {
        $context = [
            'stream' => ['verify_peer' => false],
            'auth' => ['user', 'pass'],
        ];

        $this->assertSame($context, $this->callNormalizeContext($context));
    }

    public function testNormalizeContextSslKeyTakesPrecedenceOverFlatKeys()
    {
        $result = $this->callNormalizeContext([
            'verify_peer' => true,
            'ssl' => ['verify_peer' => false],
        ]);

        $this->assertSame(['stream' => ['verify_peer' => false]], $result);
    }

    public function testNormalizeContextPreservesAuthWithFlatSslOptions()
    {
        $result = $this->callNormalizeContext([
            'verify_peer' => false,
            'auth' => ['user', 'secret'],
        ]);

        $this->assertSame([
            'auth' => ['user', 'secret'],
            'stream' => ['verify_peer' => false],
        ], $result);
    }

    public function testNormalizeContextPreservesAuthWithSslKey()
    {
        $result = $this->callNormalizeContext([
            'ssl' => ['verify_peer' => false],
            'auth' => ['user', 'secret'],
        ]);

        $this->assertSame([
            'auth' => ['user', 'secret'],
            'stream' => ['verify_peer' => false],
        ], $result);
    }

    public function testNormalizeContextReturnsAuthOnlyWhenNoSslOptions()
    {
        $result = $this->callNormalizeContext([
            'auth' => ['user', 'secret'],
        ]);

        $this->assertSame(['auth' => ['user', 'secret']], $result);
    }

    // --- PhpRedis: normalizeClusterContext ---

    public function testNormalizeClusterContextUnwrapsSslKey()
    {
        $result = $this->callNormalizeClusterContext([
            'ssl' => ['verify_peer' => false, 'peer_name' => 'example.com'],
        ]);

        $this->assertSame(['verify_peer' => false, 'peer_name' => 'example.com'], $result);
    }

    public function testNormalizeClusterContextUnwrapsStreamKey()
    {
        $result = $this->callNormalizeClusterContext([
            'stream' => ['verify_peer' => false],
        ]);

        $this->assertSame(['verify_peer' => false], $result);
    }

    public function testNormalizeClusterContextPassesThroughFlatArray()
    {
        $context = ['verify_peer' => false, 'verify_peer_name' => false];

        $this->assertSame($context, $this->callNormalizeClusterContext($context));
    }

    public function testNormalizeClusterContextSslKeyTakesPrecedenceOverFlatKeys()
    {
        $result = $this->callNormalizeClusterContext([
            'verify_peer' => true,
            'ssl' => ['verify_peer' => false],
        ]);

        $this->assertSame(['verify_peer' => false], $result);
    }

    // --- Helpers ---

    protected function callNormalizeContext(array $context): array
    {
        return (new ReflectionMethod(PhpRedisConnector::class, 'normalizeContext'))
            ->invoke($this->phpRedisConnector, $context);
    }

    protected function callNormalizeClusterContext(array $context): array
    {
        return (new ReflectionMethod(PhpRedisConnector::class, 'normalizeClusterContext'))
            ->invoke($this->phpRedisConnector, $context);
    }
}
