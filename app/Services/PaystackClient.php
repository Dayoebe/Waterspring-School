<?php

namespace App\Services;

use App\Support\PaystackConfiguration;
use DomainException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class PaystackClient
{
    public function __construct(private readonly PaystackConfiguration $configuration) {}

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
        $secret = $this->configuration->secretKeyOrFail();

        return Http::baseUrl($this->configuration->baseUrl())
            ->acceptJson()
            ->asJson()
            ->withToken($secret)
            ->connectTimeout(5)
            ->timeout((int) config('services.paystack.timeout', 15));
    }
}
