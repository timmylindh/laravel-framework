<?php

namespace Illuminate\Redis\Connectors;

use Illuminate\Contracts\Redis\Connector;
use Illuminate\Redis\Connections\PredisClusterConnection;
use Illuminate\Redis\Connections\PredisConnection;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Predis\Client;

class PredisConnector implements Connector
{
    /**
     * Create a new connection.
     *
     * @param  array  $config
     * @param  array  $options
     * @return \Illuminate\Redis\Connections\PredisConnection
     */
    public function connect(array $config, array $options)
    {
        $formattedOptions = array_merge(
            ['timeout' => 10.0], $options, Arr::pull($config, 'options', [])
        );

        if (isset($config['prefix'])) {
            $formattedOptions['prefix'] = $config['prefix'];
        }

        if (isset($config['host']) && str_starts_with($config['host'], 'tls://')) {
            $config['scheme'] = 'tls';
            $config['host'] = Str::after($config['host'], 'tls://');
        }

        if (isset($options['scheme']) && ! isset($config['scheme'])) {
            $config['scheme'] = $options['scheme'];
        }

        if (isset($options['ssl']) && ! isset($config['ssl'])) {
            $config['ssl'] = $options['ssl'];
        }

        return new PredisConnection(new Client($config, $formattedOptions));
    }

    /**
     * Create a new clustered Predis connection.
     *
     * @param  array  $config
     * @param  array  $clusterOptions
     * @param  array  $options
     * @return \Illuminate\Redis\Connections\PredisClusterConnection
     */
    public function connectToCluster(array $config, array $clusterOptions, array $options)
    {
        $clusterSpecificOptions = Arr::pull($config, 'options', []);

        if (isset($config['prefix'])) {
            $clusterSpecificOptions['prefix'] = $config['prefix'];
        }

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

        return new PredisClusterConnection(new Client(array_values($config), $mergedOptions));
    }
}
