<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAcademicPeriodIsSet
{
    /**
     * Keep users inside the dashboard when a feature requires an active period.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $school = $request->user()?->school;

        if (! $school?->academic_year_id || ! $school?->semester_id) {
            return redirect()->route('dashboard')->with(
                'danger',
                'Set the current academic year and term before using assignments.'
            );
        }

        return $next($request);
    }
}
