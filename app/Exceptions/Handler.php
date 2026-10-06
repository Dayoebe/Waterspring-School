<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        ApplicationException::class,
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register(): void
    {
        $this->renderable(function (HttpExceptionInterface $exception, Request $request) {
            if ($exception->getStatusCode() !== 409 || ! $request->user() || $request->expectsJson()) {
                return null;
            }

            return redirect()->route('dashboard')->with(
                'danger',
                $exception->getMessage() ?: 'That action cannot be completed with the current school setup.'
            );
        });

        $this->reportable(function (Throwable $e) {
            //
        });
    }
}
