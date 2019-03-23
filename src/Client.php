<?php

namespace Mitoop\Signature;

use Closure;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * @method  SignatureResponse  get($path, array $data = null, array $headers = null)
 * @method  SignatureResponse  post($path, array $data = null, array $headers = null)
 * @method  SignatureResponse  put($path, array $data = null, array $headers = null)
 * @method  SignatureResponse  delete($path, array $data = null, array $headers = null)
 */
final class Client
{

    const SCHEME_HTTP = 'http';
    const SCHEME_HTTPS = 'https';

    const HTTP_DEFAULT_PORT = 80;
    const HTTPS_DEFAULT_PORT = 443;

    const SUPPORTED_HTTP_METHODS = [
        'GET',
        'POST',
        'PUT',
        'DELETE'
    ];

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
        if(!$scheme) {
            // default http
            $scheme = self::SCHEME_HTTP;
        }

        $scheme = strtolower($scheme);

        if (! in_array($scheme, [self::SCHEME_HTTP, self::SCHEME_HTTPS])) {
            throw new InvalidArgumentException('The supported schemes are : http and https');
        }

        $this->scheme = $scheme;

        return $this;
    }

    public function setPort($port)
    {
        if(!$port) {
            // default 80
            $port = self::HTTP_DEFAULT_PORT;
        }

        $this->port = intval($port);

        return $this;
    }

    protected function setDatas($key, $value = null)
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->params[$k] = $v;
            }
        }else{
            $this->params[$key] = $value;
        }

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
     * @param  string $name
     * @return mixed
     */
    protected function fireEvent($name)
    {
        return self::$appInstance['events']->dispatch('mitoop.laravel-api-signature.' . $name, [$this]);
    }

    /**
     * Fires the `mitoop.laravel-signature-guard.requesting` event.
     *
     * @param Closure $callback
     */
    public static function requesting(Closure $callback)
    {
        self::$appInstance['events']->listen('mitoop.laravel-signature-guard.requesting', $callback);
    }

    /**
     * Fires the `mitoop.laravel-signature-guard.requesting` event.
     *
     * @param Closure $callback
     */
    public static function requested(Closure $callback)
    {
        self::$appInstance['events']->listen('mitoop.laravel-signature-guard.requested', $callback);
    }

    protected function request(string $path, array $headers)
    {
        $requestStart = microtime(true);

        $this->fireEvent('requesting');

        $response = $this->httpClient->request($this->method, $url = $this->buildUrl($path), $guzzleHttpData = $this->buildGuzzleHttpData($headers));

        $this->fireEvent('requested');
        
        if($this->enableLog) {
            $this->app['log']->debug('API Request Info', [
                'url'           => $url,
                'status'        => $response->getStatusCode(),
                'request_start' => $requestStart,
                'request_end'   => $requestEnd = microtime(true),
                'time'          => ($requestEnd - $requestStart).'s',
                'method'        => $this->method,
                'data'          => $guzzleHttpData,
                'result'        => $response->getBody()->getContents(),
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

        if (($scheme == self::SCHEME_HTTP && $this->port != self::HTTP_DEFAULT_PORT) || ($scheme == self::SCHEME_HTTPS && $this->port != self::HTTPS_DEFAULT_PORT)) {
            $url .= ':'.$this->port;
        }

        return $url . $this->path . '?' . $this->buildSignData();
    }

    protected function buildSignData()
    {
        $signData = [];
        $signData['_app_id']    = $this->appId;
        $signData['_timestamp'] = time();
        $signData['_nonce']     = $this->identity . ':' . Str::orderedUuid()->toString();
        $data = array_merge($signData, [
            '_http_method' => $this->method,
            '_http_path'   => $this->path,
        ], $this->params);

        $signData['_sign'] = $this->app->make(Signature::class)->sign($data, $this->appSecret);
        
        if($this->method == 'GET') {
            $signData = array_merge($signData, $this->params);
        }

        return http_build_query($signData, null, '&');
    }

    protected function buildGuzzleHttpData(array $headers)
    {
        $guzzleHttpData = [];
        $guzzleHttpData['http_errors'] = false;
        $guzzleHttpData['headers'] = [];

        if(in_array($this->method, ['POST', 'PUT', 'DELETE'])) {
            $guzzleHttpData['form_params'] = $this->params;
        }

        foreach ($headers as $header => $value) {
            $guzzleHttpData['headers'][$header] = $value;
        }

        if ($this->ip) {
            $guzzleHttpData['headers'] = [
                'Host' => $this->host,
            ];
        }

        if ($this->scheme == self::SCHEME_HTTPS) {
            $guzzleHttpData['verify'] = $this->certPem;
        }

        if(empty($guzzleHttpData['headers'])) {
            unset($guzzleHttpData['headers']);
        }

        return $guzzleHttpData;
    }

    /**
     * Magic Method.
     * @param $method
     * @param $args
     * @return \Mitoop\Signature\SignatureResponse
     * @throws \InvalidArgumentException
     */
    public function __call($method, $args)
    {
        if (count($args) < 1) {
            throw new InvalidArgumentException('Magic request methods require at least a URI');
        }
        $method  = strtoupper($method);
        $path    = $args[0];
        $datas   = $args[1] ?? [];
        $headers = $args[2] ?? [];

        if ( ! in_array($method, self::SUPPORTED_HTTP_METHODS)) {
            throw new InvalidArgumentException('The magic method is not supported');
        }

        $this->method = $method;

        $this->params = [];

        $this->setDatas($datas);

        return $this->request($path, $headers);
    }
}
