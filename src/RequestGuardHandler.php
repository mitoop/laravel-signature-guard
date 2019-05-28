<?php

namespace Mitoop\Signature;

use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;

class RequestGuardHandler
{
    /**
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Contracts\Auth\UserProvider  $provider
     * @return \Mitoop\Signature\ClientUser
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \Mitoop\Signature\Exception\InvalidSignatureException
     */
    public function user(Request $request, UserProvider $provider = null)
    {
        return $this->app->make(Signature::class)->validSign($request);
    }
}