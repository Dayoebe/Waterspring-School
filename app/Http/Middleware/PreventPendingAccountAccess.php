<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventPendingAccountAccess
{
    /**
     * Prevent self-registered applicants from entering the portal before approval.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user->hasRole('applicant') && ! $user->accountApplication()->exists()) {
            return $next($request);
        }

        $message = 'Your account application is awaiting administrator approval. Portal access has not been granted yet.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        auth()->guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('login')->with('status', $message);
    }
}
