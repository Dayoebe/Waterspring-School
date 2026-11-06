<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

class EnsureDefaultPasswordIsChanged
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response|RedirectResponse)  $next
     * @return Response|RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if ($request->routeIs(
            'password.change',
            'password.change.update',
            'profile.edit',
            'profile.update',
            'verification.*',
            'logout'
        )) {
            return $next($request);
        }

        $user = $request->user();
        if ($user->requires_password_change
            || Hash::check('password', $user->password)
            || Hash::check('12345678', $user->password)) {
            session()->flash('danger', 'Please change your password to proceed.');

            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
