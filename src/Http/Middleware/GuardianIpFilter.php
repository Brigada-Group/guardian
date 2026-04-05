<?php

namespace Brigada\Guardian\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GuardianIpFilter
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowedIps = config('guardian.dashboard.allowed_ips', []);

        if (empty($allowedIps)) {
            return $next($request);
        }

        if (! in_array($request->ip(), $allowedIps, true)) {
            abort(403, 'IP not allowed.');
        }

        return $next($request);
    }
}
