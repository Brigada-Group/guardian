<?php

namespace Brigada\Guardian\Http\Middleware;

use Brigada\Guardian\Support\TraceContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StartTrace
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            TraceContext::start($request->header('traceparent'));
        } catch (\Throwable) {
            // Never let tracing break the request lifecycle.
        }

        return $next($request);
    }
}