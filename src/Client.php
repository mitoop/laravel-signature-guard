<?php

namespace Mitoop\Signature;

use Closure;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * @method  SignatureResponse  get($path, array $params)
 * @method  SignatureResponse  post($path, array $params)
 * @method  SignatureResponse  put($path, array $params)
 * @method  SignatureResponse  delete($path, array $params)
 */
class Client
{
    const SCHEME_HTTP = 'http';

    const SCHEME_HTTPS = 'https';

    protected static $appInstance;

    protected $params = [];

    protected $appId;

    protected $appSecret;

    protected $identity;

    protected $host;

    protected $ip;

    protected $scheme;

    protected $port;

    protected $method;

    protected $path;

    protected $enableLog;

    /**
     * @var \GuzzleHttp\Client
     */
    protected $httpClient;

    protected $certPem;

    /**
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected $app;

    public function __construct($app, $appId, $appSecret, $identity, $enableLog)
    {
        $this->app = $app;
        $this->appId = $appId;
        $this->appSecret = $appSecret;
        $this->identity = $identity;
        $this->enableLog = $enableLog;
        self::$appInstance = $app;
    }

    protected function setPath($path)
    {
        $path = rtrim($path, '/');
        if (strpos($path, '/') !== 0) {
            $path = '/'.$path;
        }

        $this->path = $path;

        return $this;
    }

    public function setHost($host)
    {
        $host = ltrim($host, 'http://');
        $host = ltrim($host, 'https://');
        $host = rtrim($host, '/');

        $this->host = $host;

        return $this;
    }

    public function setIp($ip)
    {
        if ($ip) {
            $ip = ltrim($ip, 'http://');
            $ip = ltrim($ip, 'https://');
            $ip = rtrim($ip, '/');

            $this->ip = $ip;
        }

        return $this;
    }

    public function setScheme($scheme)
    {
        $scheme = strtolower($scheme);

        if (! in_array($scheme, [self::SCHEME_HTTP, self::SCHEME_HTTPS])) {
            throw new InvalidArgumentException('The supported schemes are : http and https');
        }

        $this->scheme = $scheme;

        return $this;
    }

    public function setPort($port)
    {
        if ($this->port) {
            $this->port = intval($port);
        }

        return $this;
    }

    protected function setParams(array $data)
    {
        $this->params = $data;

        return $this;
    }

    public function setHttpClient(\GuzzleHttp\Client $httpClient)
    {
        $this->httpClient = $httpClient;

        return $this;
    }

    public function setCertPem($certPem)
    {
        $this->certPem = $certPem;

        return $this;
    }

    /**
     * Fires an event for the Client.
     *
     * @param  string  $name
     * @return mixed
     */
    protected function fireEvent($name)
    {
        return self::$appInstance['events']->dispatch('mitoop.laravel-signature-guard.'.$name, [$this]);
    }

    /**
     * Listen the `mitoop.laravel-signature-guard.requesting` event.
     *
     * @param  Closure  $callback
     */
    public static function requesting(Closure $callback)
    {
        self::$appInstance['events']->listen('mitoop.laravel-signature-guard.requesting', $callback);
    }

    /**
     * Listen the `mitoop.laravel-signature-guard.requested` event.
     *
     * @param  Closure  $callback
     */
    public static function requested(Closure $callback)
    {
        self::$appInstance['events']->listen('mitoop.laravel-signature-guard.requested', $callback);
    }

    protected function request(string $path)
    {
        $requestStart = microtime(true);

        $this->fireEvent('requesting');

        $response = $this->httpClient->request(
            $this->method,
            $url = $this->buildUrl($path),
            $guzzleRequestOptions = $this->buildGuzzleRequestOptions()
        );

        $this->fireEvent('requested');

        if ($this->enableLog) {
            $this->app['log']->debug('API Request Detail', [
                'url' => $url,
                'method' => $this->method,
                'guzzle_request_options' => $guzzleRequestOptions,
                'status' => $response->getStatusCode(),
                'result' => $response->getBody()->getContents(),
                'request_start' => $requestStart,
                'request_end' => $requestEnd = microtime(true),
                'time' => ($requestEnd - $requestStart).'s',
            ]);
        }

        return new SignatureResponse($response);
    }

    protected function buildUrl($path)
    {
        $this->setPath($path);

        $scheme = $this->scheme;
        $url = $scheme.'://';

        if ($ip = $this->ip) {
            $url .= $ip;
        } else {
            $url .= $this->host;
        }

        if ($this->port) {
            $url .= ':'.$this->port;
        }

        return $url.$this->path;
    }

    protected function buildGuzzleRequestOptions()
    {
        $guzzleRequestOptions = $this->params;
        $guzzleRequestOptions['http_errors'] = false;

        if ($this->ip) {
            if (! isset($guzzleRequestOptions['headers'])) {
                $guzzleRequestOptions['headers'] = [];
            }
            $guzzleRequestOptions['headers']['host'] = $this->host;
        }

        if ($this->scheme == self::SCHEME_HTTPS) {
            if (! isset($guzzleRequestOptions['verify'])) {
                $guzzleRequestOptions['verify'] = $this->certPem;
            }
        }

        $signData = [];
        $signData['_app_id'] = $this->appId;
        $signData['_timestamp'] = time();
        $signData['_nonce'] = $this->identity.':'.Str::orderedUuid()->toString();

        $data = array_merge($signData, [
            '_http_method' => $this->method,
            '_http_path' => $this->path,
        ]);

        if (isset($guzzleRequestOptions['query'])) {
            $data = array_merge($data, $guzzleRequestOptions['query']);
        }

        if (isset($guzzleRequestOptions['form_params'])) {
            $data = array_merge($data, $guzzleRequestOptions['form_params']);
        }

        if (isset($guzzleRequestOptions['json'])) {
            $data = array_merge($data, $guzzleRequestOptions['json']);
        }

        $signData['_sign'] = $this->app->make(Signature::class)->sign($data, $this->appSecret);

        $guzzleRequestOptions['query'] = array_merge($signData, $guzzleRequestOptions['query'] ?? []);

        return $guzzleRequestOptions;
    }

    /**
     * Magic Method.
     *
     * @param $method
     * @param $args
     * @return \Mitoop\Signature\SignatureResponse
     * @throws \InvalidArgumentException
     */
    public function __call($method, $args)
    {
        $method = strtoupper($method);

        if (! in_array($method, ['GET', 'POST', 'PUT', 'DELETE'])) {
            throw new InvalidArgumentException('The supported magic methods are GET, POST, PUT, DELETE');
        }

        if (count($args) < 1) {
            throw new InvalidArgumentException('Magic request methods require at least a URI');
        }

        $path = $args[0];
        $params = $args[1] ?? [];

        $this->method = $method;

        $this->params = [];

        $this->setParams($params);

        return $this->request($path);
    }
}
