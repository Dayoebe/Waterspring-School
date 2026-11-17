<?php

namespace App\Support;

use DomainException;

final class FlutterwaveConfiguration
{
    public function enabled(): bool
    {
        return (bool) config('services.flutterwave.enabled');
    }

    public function secretKey(): string
    {
        return trim((string) config('services.flutterwave.secret_key'));
    }

    public function secretHash(): string
    {
        return trim((string) config('services.flutterwave.secret_hash'));
    }

    public function hasValidSecretKey(): bool
    {
        return str_starts_with($this->secretKey(), 'FLWSECK')
            && strlen($this->secretKey()) >= 24;
    }

    public function hasValidSecretHash(): bool
    {
        return strlen($this->secretHash()) >= 16;
    }

    public function mode(): ?string
    {
        return match (true) {
            str_starts_with($this->secretKey(), 'FLWSECK_TEST-') => 'test',
            str_starts_with($this->secretKey(), 'FLWSECK-') => 'live',
            default => null,
        };
    }

    public function baseUrl(): string
    {
        return rtrim(trim((string) config('services.flutterwave.base_url')), '/');
    }

    public function callbackUrl(): string
    {
        return trim((string) config('services.flutterwave.callback_url'))
            ?: route('fee-payments.flutterwave.callback');
    }

    public function webhookUrl(): string
    {
        return route('api.fee-payments.flutterwave.webhook');
    }

    public function checkoutReady(): bool
    {
        return $this->enabled()
            && $this->hasValidSecretKey()
            && $this->hasValidSecretHash()
            && $this->isSecurePublicUrl($this->baseUrl())
            && $this->isSecurePublicUrl($this->callbackUrl())
            && $this->isSecurePublicUrl($this->webhookUrl());
    }

    public function assertCheckoutReady(): void
    {
        if (! $this->enabled()) {
            throw new DomainException('Flutterwave payment is disabled. Complete the setup before enabling it.');
        }

        if (! $this->hasValidSecretKey()) {
            throw new DomainException('Flutterwave is missing a valid server-side secret key.');
        }

        if (! $this->hasValidSecretHash()) {
            throw new DomainException('Flutterwave is missing a webhook secret hash of at least 16 characters.');
        }

        if (! $this->isSecurePublicUrl($this->baseUrl())) {
            throw new DomainException('Flutterwave requires a valid HTTPS API base URL.');
        }

        if (! $this->isSecurePublicUrl($this->callbackUrl()) || ! $this->isSecurePublicUrl($this->webhookUrl())) {
            throw new DomainException('Flutterwave requires public HTTPS callback and webhook URLs. Update APP_URL or FLUTTERWAVE_CALLBACK_URL.');
        }
    }

    public function isSecurePublicUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https';
    }
}
