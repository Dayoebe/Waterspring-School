<?php

namespace App\Services;

use Illuminate\Http\Request;

class PaystackWebhookVerifier
{
    public function isValid(Request $request): bool
    {
        $body = $request->getContent();
        $signature = trim((string) $request->header('x-paystack-signature'));
        $secretKey = trim((string) config('services.paystack.secret_key'));

        return $signature !== ''
            && $secretKey !== ''
            && hash_equals(hash_hmac('sha512', $body, $secretKey), $signature);
    }
}
