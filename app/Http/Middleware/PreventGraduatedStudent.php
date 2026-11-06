<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PreventGraduatedStudent
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response|RedirectResponse)  $next
     * @return Response|RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if (! auth()->user()->hasRole('student')) {
            return $next($request);
        }

        $studentRecord = auth()->user()
            ->studentRecord()
            ->withoutGlobalScopes()
            ->with('baseClass')
            ->first();

        if (! $studentRecord) {
            abort(403, 'You cannot access this resource because your student record is not available.');
        }

        if ($studentRecord->is_graduated === true) {
            abort(403, 'You cannot access this resource because you have been marked as graduated.');
        }

        if (! $studentRecord->isActiveStudent()) {
            abort(403, 'You cannot access this resource because your student account is not currently active.');
        }

        return $next($request);
    }
}
