<?php

use App\Http\Middleware\EnsureDefaultPasswordIsChanged;
use App\Http\Middleware\PreventBrowserCaching;
use App\Http\Middleware\PreventGraduatedStudent;
use App\Http\Middleware\PreventLockAccountAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware([
    PreventBrowserCaching::class,
    'auth:sanctum',
    PreventLockAccountAccess::class,
    EnsureDefaultPasswordIsChanged::class,
    PreventGraduatedStudent::class,
    'verified',
])->get('/user', function (Request $request) {
    return $request->user();
});
