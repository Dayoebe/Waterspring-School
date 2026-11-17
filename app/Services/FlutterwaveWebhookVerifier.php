<?php

namespace App\Services;

use Illuminate\Http\Request;

class FlutterwaveWebhookVerifier
{
    public function isValid(Request $request): bool
    {
        $signature = trim((string) $request->header('verif-hash'));
        $secretHash = trim((string) config('services.flutterwave.secret_hash'));

        return $signature !== ''
            && $secretHash !== ''
            && hash_equals($secretHash, $signature);
    }
}
