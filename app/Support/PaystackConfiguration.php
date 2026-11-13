<?php

namespace App\Support;

use DomainException;

final class PaystackConfiguration
{
    public function enabled(): bool
    {
        return (bool) config('services.paystack.enabled');
    }

    public function secretKey(): string
    {
        return trim((string) config('services.paystack.secret_key'));
    }

    public function hasValidSecretKey(): bool
    {
        return preg_match('/^sk_(test|live)_[A-Za-z0-9_-]+$/', $this->secretKey()) === 1;
    }

    public function mode(): ?string
    {
        return match (true) {
            str_starts_with($this->secretKey(), 'sk_test_') => 'test',
            str_starts_with($this->secretKey(), 'sk_live_') => 'live',
            default => null,
        };
    }

    public function callbackUrl(): string
    {
        return trim((string) config('services.paystack.callback_url'))
            ?: route('fee-payments.paystack.callback');
    }

    public function baseUrl(): string
    {
        return rtrim(trim((string) config('services.paystack.base_url')), '/');
    }

    public function webhookUrl(): string
    {
        return route('api.fee-payments.paystack.webhook');
    }

    public function checkoutReady(): bool
    {
        return $this->enabled()
            && $this->hasValidSecretKey()
            && $this->isSecurePublicUrl($this->baseUrl())
            && $this->isSecurePublicUrl($this->callbackUrl())
            && $this->isSecurePublicUrl($this->webhookUrl());
    }

    public function assertCheckoutReady(): void
    {
        $this->secretKeyOrFail();

        if (! $this->isSecurePublicUrl($this->baseUrl())) {
            throw new DomainException('Paystack requires a valid HTTPS API base URL.');
        }

        if (! $this->isSecurePublicUrl($this->callbackUrl()) || ! $this->isSecurePublicUrl($this->webhookUrl())) {
            throw new DomainException('Paystack requires public HTTPS callback and webhook URLs. Update APP_URL or PAYSTACK_CALLBACK_URL.');
        }
    }

    public function secretKeyOrFail(): string
    {
        if (! $this->enabled()) {
            throw new DomainException('Online payment is disabled. Set PAYSTACK_ENABLED=true after completing the Paystack setup.');
        }

        if (! $this->hasValidSecretKey()) {
            throw new DomainException('Paystack is missing a valid server-side secret key.');
        }

        return $this->secretKey();
    }

    public function isSecurePublicUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https';
    }
}
