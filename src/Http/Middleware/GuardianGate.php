<?php

namespace Brigada\Guardian\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class GuardianGate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Gate::has('viewGuardianDashboard')) {
            abort(403, 'Guardian dashboard access not configured.');
        }

        if (! Gate::check('viewGuardianDashboard', [$request->user()])) {
            abort(403, 'Unauthorized.');
        }

        return $next($request);
    }
}
