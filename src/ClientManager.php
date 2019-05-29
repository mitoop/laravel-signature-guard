<?php

namespace Mitoop\Signature;

use InvalidArgumentException;

class ClientManager
{
    /**
     * The application instance.
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected $app;

    /**
     * The array of resolved clients.
     *
     * @var array
     */
    protected $connections = [];

    /**
     * @var \GuzzleHttp\Client
     */
    protected $httpClient;

    /**
     * @var string config name
     */
    protected $configName;

    /**
     * Create a new Client manager instance.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @param  string  $configName
     * @param  \GuzzleHttp\Client  $client
     */
    public function __construct($app, $configName, $client)
    {
        $this->app = $app;
        $this->configName = $configName;
        $this->httpClient = $client;
    }

    public function connect($client = null)
    {
        $client = $client ?: $this->getDefaultClient();

        return $this->connections[$client] = $this->get($client);
    }

    protected function getDefaultClient()
    {
        return $this->app['config']["{$this->configName}.default"];
    }

    protected function get($client)
    {
        $client = $this->connections[$client] ?? $this->resolve($client);

        $client->setHttpClient(clone $this->httpClient);

        return $client;
    }

    protected function resolve($client)
    {
        $config = $this->getConfig($client);
        $client = new Client(
            $this->app,
            $config['app_id'],
            $config['app_secret'],
            $this->getIdentity(),
            boolval($config['enable_log'])
        );
        $client->setScheme($config['scheme']);
        $client->setHost($config['host']);
        $client->setIp($config['ip']);
        $client->setPort($config['port']);
        $client->setCertPem($config['https_cert_pem']);

        return $client;
    }

    /**
     * Get client config.
     *
     * @param $client
     * @return array
     * @throws \InvalidArgumentException
     */
    protected function getConfig($client)
    {
        $config = $this->app['config']["{$this->configName}.clients.{$client}"];

        if (is_null($config)) {
            throw new InvalidArgumentException("Client [{$client}] is not defined.");
        }

        $config = array_merge([
                 'app_id'         => '',
                 'app_secret'     => '',
                 'scheme'         => 'http',
                 'host'           => '',
                 'ip'             => '',
                 'port'           => '',
                 'https_cert_pem' => false,
                 'enable_log'     => true,
             ], $config);

        if ($config['app_id'] == '') {
            throw new InvalidArgumentException('app_id is not defined.');
        }

        if ($config['app_secret'] == '') {
            throw new InvalidArgumentException('app_secret is not defined.');
        }

        if ($config['host'] == '') {
            throw new InvalidArgumentException('host is not defined.');
        }

        return $config;
    }

    protected function getIdentity()
    {
        return $this->app['config']["{$this->configName}.identity"] ?? 'identity';
    }

    /**
     * Dynamically call the default client instance.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->connect()->$method(...$parameters);
    }
}
