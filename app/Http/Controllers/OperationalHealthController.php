<?php

namespace App\Http\Controllers;

use App\Services\OperationalHealthService;
use Illuminate\Http\JsonResponse;

class OperationalHealthController extends Controller
{
    public function __invoke(OperationalHealthService $health): JsonResponse
    {
        $result = $health->check();

        return response()->json([
            'status' => $result['healthy'] ? 'ok' : 'unhealthy',
            'checked_at' => $result['checked_at'],
            'checks' => collect($result['checks'])->map(
                fn (array $check): string => $check['healthy'] ? 'ok' : 'failed'
            ),
        ], $result['healthy'] ? 200 : 503)->header('Cache-Control', 'no-store, max-age=0');
    }
}
