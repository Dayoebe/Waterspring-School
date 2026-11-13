<?php

namespace App\Services;

use Illuminate\Http\Request;

class PaystackWebhookVerifier
{
    public function isValid(Request $request): bool
    {
        $body = $request->getContent();
        $timestampedSignature = trim((string) $request->header('Paystack-Signature'));
        $webhookSecret = trim((string) config('services.paystack.webhook_secret'));

        if ($timestampedSignature !== '' && $webhookSecret !== '') {
            return $this->validTimestampedSignature($timestampedSignature, $body, $webhookSecret);
        }

        $legacySignature = trim((string) $request->header('x-paystack-signature'));
        $secretKey = trim((string) config('services.paystack.secret_key'));

        return $legacySignature !== ''
            && $secretKey !== ''
            && hash_equals(hash_hmac('sha512', $body, $secretKey), $legacySignature);
    }

    private function validTimestampedSignature(string $header, string $body, string $secret): bool
    {
        $parts = [];
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
            if ($key !== null && $value !== null) {
                $parts[$key][] = $value;
            }
        }

        $timestamp = isset($parts['t'][0]) ? (int) $parts['t'][0] : 0;
        $tolerance = (int) config('services.paystack.webhook_tolerance', 300);
        if ($timestamp <= 0 || abs(now()->timestamp - $timestamp) > $tolerance) {
            return false;
        }

        $expected = hash_hmac('sha512', $timestamp.'.'.$body, $secret);
        foreach ($parts['v1'] ?? [] as $signature) {
            $candidate = str_contains($signature, ':')
                ? substr($signature, strrpos($signature, ':') + 1)
                : $signature;

            if (hash_equals($expected, $candidate)) {
                return true;
            }
        }

        return false;
    }
}
