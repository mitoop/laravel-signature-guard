<?php

namespace Mitoop\Signature\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class RequestGuardAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $guard
     *
     * @return mixed
     *
     * @throws \Mitoop\Signature\Exception\InvalidSignatureException
     * @see \Mitoop\Signature\Signature@validSign
     */
    public function handle($request, Closure $next, $guard)
    {
        Auth::guard($guard)->check();

        return $next($request);
    }
}