<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('operations.force_https') || $request->isSecure()) {
            return $next($request);
        }

        if (! $request->isMethodSafe()) {
            abort(400, 'HTTPS is required for this request.');
        }

        return redirect()->secure($request->getRequestUri(), 308);
    }
}
