<?php

namespace Mitoop\Signature\Facades;

use Illuminate\Support\Facades\Facade;
use Mitoop\Signature\ClientManager;

/**
 * @method static \Mitoop\Signature\Client  connect(string|null $client = null)
 * @method static \Mitoop\Signature\SignatureResponse  get($path, array $data = null, array $headers = null)
 * @method static \Mitoop\Signature\SignatureResponse  post($path, array $data = null, array $headers = null)
 * @method static \Mitoop\Signature\SignatureResponse  put($path, array $data = null, array $headers = null)
 * @method static \Mitoop\Signature\SignatureResponse  delete($path, array $data = null, array $headers = null)
 */
class Client extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return ClientManager::class;
    }
}
