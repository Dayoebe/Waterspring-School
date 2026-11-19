<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class OperationalHealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
        ])->header('Cache-Control', 'no-store, max-age=0');
    }
}
