<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventLockAccountAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user->locked && ! $user->hasAnyRole(['super-admin', 'super_admin'])) {
            auth()->guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => "This account is locked. If this is an error, contact your school's administrator.",
                ], 403);
            }

            abort(403, "This account is locked. If this is an error, contact your school's administrator.");
        }

        return $next($request);
    }
}
