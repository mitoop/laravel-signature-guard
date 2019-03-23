<?php

namespace Mitoop\Signature;

class ClientUser
{
    /**
     * @var array
     */
    protected $client;

    public function __construct(array $client)
    {
        $this->client = $client;
    }

    public function getAuthIdentifier()
    {
        return $this->client['app_id'];
    }
}