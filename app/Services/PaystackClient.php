<?php

namespace App\Services;

use DomainException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class PaystackClient
{
    public function initialize(array $payload): array
    {
        $response = $this->request()->post('/transaction/initialize', $payload);
        $response->throw();

        $body = $response->json();
        if (! is_array($body) || ($body['status'] ?? false) !== true || ! is_array($body['data'] ?? null)) {
            throw new DomainException((string) ($body['message'] ?? 'Paystack could not initialize this payment.'));
        }

        return $body['data'];
    }

    public function verify(string $reference): array
    {
        $response = $this->request()->get('/transaction/verify/'.rawurlencode($reference));
        $response->throw();

        $body = $response->json();
        if (! is_array($body) || ($body['status'] ?? false) !== true || ! is_array($body['data'] ?? null)) {
            throw new DomainException((string) ($body['message'] ?? 'Paystack could not verify this payment.'));
        }

        return $body['data'];
    }

    private function request(): PendingRequest
    {
        $secret = trim((string) config('services.paystack.secret_key'));
        if (! config('services.paystack.enabled') || $secret === '') {
            throw new DomainException('Online payment is not configured yet.');
        }

        return Http::baseUrl(rtrim((string) config('services.paystack.base_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->withToken($secret)
            ->connectTimeout(5)
            ->timeout((int) config('services.paystack.timeout', 15));
    }
}
