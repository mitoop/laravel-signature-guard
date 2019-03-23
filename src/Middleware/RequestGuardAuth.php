<?php

namespace Mitoop\Signature\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class RequestGuardAuth
{
    public function handle($request, Closure $next, $guard)
    {
        Auth::guard($guard)->check();

        return $next($request);
    }
}