<?php

namespace Illuminate\Tests\Redis;

use Illuminate\Redis\Connectors\PredisConnector;
use PHPUnit\Framework\TestCase;

class PredisConnectorTest extends TestCase
{
    protected PredisConnector $connector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connector = new PredisConnector;
    }

    // --- connect: unified ssl/scheme ---

    public function testConnectMergesUnifiedSslIntoConfig()
    {
        $connection = $this->connector->connect(
            ['host' => '127.0.0.1', 'port' => 6379],
            ['ssl' => ['verify_peer' => false], 'scheme' => 'tls']
        );

        $parameters = $connection->client()->getConnection()->getParameters();

        $this->assertSame('tls', $parameters->scheme);
        $this->assertSame(['verify_peer' => false], $parameters->ssl);
    }

    public function testConnectDoesNotOverrideExplicitConfig()
    {
        $connection = $this->connector->connect(
            ['host' => '127.0.0.1', 'port' => 6379, 'scheme' => 'tcp', 'ssl' => ['cafile' => '/custom']],
            ['ssl' => ['verify_peer' => false], 'scheme' => 'tls']
        );

        $parameters = $connection->client()->getConnection()->getParameters();

        $this->assertSame('tcp', $parameters->scheme);
        $this->assertSame(['cafile' => '/custom'], $parameters->ssl);
    }

    // --- connectToCluster: ssl/scheme promotion to parameters ---

    public function testClusterPromotesSslToParameters()
    {
        $connection = $this->connector->connectToCluster(
            [['host' => '127.0.0.1', 'port' => 7001]],
            ['cluster' => 'predis'],
            ['ssl' => ['verify_peer' => false], 'scheme' => 'tls']
        );

        $parameters = $connection->client()->getOptions()->parameters;

        $this->assertSame(['verify_peer' => false], $parameters['ssl']);
        $this->assertSame('tls', $parameters['scheme']);
    }

    public function testClusterDoesNotOverrideExplicitParameters()
    {
        $connection = $this->connector->connectToCluster(
            [['host' => '127.0.0.1', 'port' => 7001]],
            [
                'cluster' => 'predis',
                'parameters' => [
                    'ssl' => ['cafile' => '/custom'],
                    'scheme' => 'tcp',
                ],
            ],
            ['ssl' => ['verify_peer' => false], 'scheme' => 'tls']
        );

        $parameters = $connection->client()->getOptions()->parameters;

        $this->assertSame(['cafile' => '/custom'], $parameters['ssl']);
        $this->assertSame('tcp', $parameters['scheme']);
    }
}
