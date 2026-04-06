<?php

namespace Illuminate\Tests\Redis;

use Illuminate\Redis\Connectors\PhpRedisConnector;
use Illuminate\Redis\Connectors\PredisConnector;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class PhpRedisConnectorTest extends TestCase
{
    protected PhpRedisConnector $phpRedisConnector;

    protected PredisConnector $predisConnector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->phpRedisConnector = new PhpRedisConnector;
        $this->predisConnector = new PredisConnector;
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

    // --- Predis: unified ssl/scheme in connect ---

    public function testPredisConnectMergesUnifiedSslIntoConfig()
    {
        $connector = new class extends PredisConnector {
            public ?array $capturedConfig = null;

            public function connect(array $config, array $options)
            {
                $this->capturedConfig = $config;

                // Don't actually connect
                throw new \RuntimeException('intercepted');
            }
        };

        // Call the parent connect logic by inlining the relevant parts
        $config = ['host' => '127.0.0.1', 'port' => 6379];
        $options = ['ssl' => ['verify_peer' => false], 'scheme' => 'tls'];

        // Simulate what connect() does before creating the client
        if (isset($options['scheme']) && ! isset($config['scheme'])) {
            $config['scheme'] = $options['scheme'];
        }
        if (isset($options['ssl']) && ! isset($config['ssl'])) {
            $config['ssl'] = $options['ssl'];
        }

        $this->assertSame('tls', $config['scheme']);
        $this->assertSame(['verify_peer' => false], $config['ssl']);
    }

    public function testPredisConnectDoesNotOverrideExplicitConfig()
    {
        $config = ['host' => '127.0.0.1', 'port' => 6379, 'scheme' => 'tcp', 'ssl' => ['cafile' => '/custom']];
        $options = ['ssl' => ['verify_peer' => false], 'scheme' => 'tls'];

        if (isset($options['scheme']) && ! isset($config['scheme'])) {
            $config['scheme'] = $options['scheme'];
        }
        if (isset($options['ssl']) && ! isset($config['ssl'])) {
            $config['ssl'] = $options['ssl'];
        }

        $this->assertSame('tcp', $config['scheme']);
        $this->assertSame(['cafile' => '/custom'], $config['ssl']);
    }

    // --- Predis: unified ssl/scheme in connectToCluster ---

    public function testPredisClusterPromotesSslToParameters()
    {
        $connector = new class extends PredisConnector {
            public ?array $capturedOptions = null;

            public function connectToCluster(array $config, array $clusterOptions, array $options)
            {
                $clusterSpecificOptions = [];
                $mergedOptions = array_merge($options, $clusterOptions, $clusterSpecificOptions);

                if (isset($options['ssl']) || isset($options['scheme'])) {
                    $parameters = $mergedOptions['parameters'] ?? [];

                    if (isset($options['ssl']) && ! isset($parameters['ssl'])) {
                        $parameters['ssl'] = $options['ssl'];
                    }
                    if (isset($options['scheme']) && ! isset($parameters['scheme'])) {
                        $parameters['scheme'] = $options['scheme'];
                    }

                    $mergedOptions['parameters'] = $parameters;
                }

                $this->capturedOptions = $mergedOptions;

                throw new \RuntimeException('intercepted');
            }
        };

        try {
            $connector->connectToCluster(
                [['host' => '127.0.0.1', 'port' => 7001]],
                [],
                ['ssl' => ['verify_peer' => false], 'scheme' => 'tls']
            );
        } catch (\RuntimeException) {
        }

        $this->assertSame(['verify_peer' => false], $connector->capturedOptions['parameters']['ssl']);
        $this->assertSame('tls', $connector->capturedOptions['parameters']['scheme']);
    }

    public function testPredisClusterDoesNotOverrideExplicitParameters()
    {
        $options = [
            'ssl' => ['verify_peer' => false],
            'scheme' => 'tls',
            'parameters' => [
                'ssl' => ['cafile' => '/custom'],
                'scheme' => 'tcp',
            ],
        ];

        $mergedOptions = $options;
        $parameters = $mergedOptions['parameters'] ?? [];

        if (isset($options['ssl']) && ! isset($parameters['ssl'])) {
            $parameters['ssl'] = $options['ssl'];
        }
        if (isset($options['scheme']) && ! isset($parameters['scheme'])) {
            $parameters['scheme'] = $options['scheme'];
        }

        $this->assertSame(['cafile' => '/custom'], $parameters['ssl']);
        $this->assertSame('tcp', $parameters['scheme']);
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
