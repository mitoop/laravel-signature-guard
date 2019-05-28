<?php

namespace Mitoop\Signature;

use Illuminate\Http\Request;
use Mitoop\Signature\Exception\InvalidSignatureException;

class Signature
{
    const REQUEST_EXPIRATION = 10;

    /**
     * The application instance.
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected $app;

    /**
     * @var \Illuminate\Http\Request;
     */
    protected $request;

    /**
     * @var array client config.
     */
    protected $client;

    /**
     * @var string config name
     */
    protected $configName;

    /**
     * Create a new Client manager instance.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     *
     * @param $configName
     */
    public function __construct($app, $configName)
    {
        $this->app = $app;
        $this->configName = $configName;
    }

    /**
     * Get signature.
     *
     * @param  array  $params
     * @param  string  $secret
     *
     * @return string
     */
    public function sign(array $params, $secret)
    {
        ksort($params);

        return hash_hmac('sha256', http_build_query($params, null, '&'), $secret);
    }

    /**
     * Validate signature.
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return \Mitoop\Signature\ClientUser
     * @throws InvalidSignatureException
     */
    public function validSign(Request $request)
    {
        $this->request = $request;

        $client = $this->parseClient();

        $nonce = $this->request->query('_nonce');

        $this->validTimestamp()->validNonce($nonce)->validHmac()->setNonceCache($nonce);

        unset($client['app_secret']);

        return new ClientUser($client);
    }

    /**
     * Parse client on the basis of app id.
     *
     * @return array
     * @throws \Mitoop\Signature\Exception\InvalidSignatureException
     */
    protected function parseClient()
    {
        $appId = $this->request->query('_app_id');

        if (is_null($appId)) {
            throw new InvalidSignatureException('App id is missing');
        }

        $clients = $this->app['config']->get("{$this->configName}.clients", []);

        $client = current(array_filter($clients, function ($client) use ($appId) {
            return $client['app_id'] == $appId;
        }, ARRAY_FILTER_USE_BOTH));

        if ($client === false || ! isset($client['app_secret'])) {
            throw new InvalidSignatureException('Invalid App id');
        }

        $this->client = $client;

        return $client;
    }

    /**
     * Validate hmac.
     *
     * @return $this
     * @throws \Mitoop\Signature\Exception\InvalidSignatureException
     */
    protected function validHmac()
    {
        $params = $this->request->input();

        $params = array_merge($params, [
            '_http_method' => $this->request->method(), // method() is always uppercase
            '_http_path' => $this->request->getPathInfo(),
        ]);

        $signature = $params['_sign'];

        unset($params['_sign']);

        if (is_null($signature) || ! hash_equals($this->sign($params, $this->client['app_secret']), $signature)) {
            throw new InvalidSignatureException('Invalid Signature');
        }

        return $this;
    }

    /**
     * Validate timestamp.
     *
     * @return $this
     * @throws \Mitoop\Signature\Exception\InvalidSignatureException
     */
    protected function validTimestamp()
    {
        $timestamp = intval($this->request->query('_timestamp', 0));

        $now = time();

        if ($timestamp <= 0 || $timestamp > $now) {
            throw new InvalidSignatureException('Request is invalid');
        }

        if ($now - $timestamp >= self::REQUEST_EXPIRATION) {
            throw new InvalidSignatureException('Request is expired');
        }

        return $this;
    }

    /**
     * Validate nonce.
     *
     * @param $nonce
     * @return $this
     * @throws \Mitoop\Signature\Exception\InvalidSignatureException
     */
    protected function validNonce($nonce)
    {
        if (is_null($nonce) || $this->app['cache']->has('nonce:'.$nonce)) {
            throw new InvalidSignatureException('Nonce request');
        }

        return $this;
    }

    /**
     * Create nonce cache.
     *
     * @param $nonce
     */
    protected function setNonceCache($nonce)
    {
        $this->app['cache']->add('nonce:'.$nonce, 1, self::REQUEST_EXPIRATION);
    }
}
