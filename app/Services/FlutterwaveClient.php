<?php

namespace App\Services;

use App\Support\FlutterwaveConfiguration;
use DomainException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class FlutterwaveClient
{
    public function __construct(private readonly FlutterwaveConfiguration $configuration) {}

    public function createCheckout(array $payload): array
    {
        $response = $this->request()->post('/payments', $payload);
        $response->throw();

        return $this->responseData($response->json(), 'Flutterwave could not create this checkout.');
    }

    public function verify(int $transactionId): array
    {
        $response = $this->request()->get('/transactions/'.$transactionId.'/verify');
        $response->throw();

        return $this->responseData($response->json(), 'Flutterwave could not verify this payment.');
    }

    private function request(): PendingRequest
    {
        $this->configuration->assertCheckoutReady();

        return Http::baseUrl($this->configuration->baseUrl())
            ->acceptJson()
            ->asJson()
            ->withToken($this->configuration->secretKey())
            ->connectTimeout(5)
            ->timeout((int) config('services.flutterwave.timeout', 15));
    }

    private function responseData(mixed $body, string $fallbackMessage): array
    {
        if (! is_array($body) || ($body['status'] ?? null) !== 'success' || ! is_array($body['data'] ?? null)) {
            throw new DomainException(is_array($body) ? (string) ($body['message'] ?? $fallbackMessage) : $fallbackMessage);
        }

        return $body['data'];
    }
}
