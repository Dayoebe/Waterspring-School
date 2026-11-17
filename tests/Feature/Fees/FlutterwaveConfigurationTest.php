<?php

namespace Tests\Feature\Fees;

use App\Support\FlutterwaveConfiguration;
use Tests\TestCase;

class FlutterwaveConfigurationTest extends TestCase
{
    public function test_checkout_is_not_ready_when_flutterwave_is_disabled_or_secrets_are_missing(): void
    {
        config()->set([
            'services.flutterwave.enabled' => false,
            'services.flutterwave.secret_key' => null,
            'services.flutterwave.secret_hash' => null,
            'services.flutterwave.base_url' => 'https://api.flutterwave.com/v3',
            'services.flutterwave.callback_url' => 'https://school.example/payments/flutterwave/callback',
        ]);
        $this->forcePublicUrl();

        $this->assertFalse(app(FlutterwaveConfiguration::class)->checkoutReady());
        $this->artisan('payments:flutterwave-status')
            ->expectsOutputToContain('Flutterwave checkout is not ready')
            ->assertFailed();
    }

    public function test_status_accepts_complete_https_configuration_without_printing_secrets(): void
    {
        $secretKey = 'FLWSECK_TEST-12345678901234567890-X';
        $secretHash = 'never-print-this-webhook-secret';
        config()->set([
            'services.flutterwave.enabled' => true,
            'services.flutterwave.secret_key' => $secretKey,
            'services.flutterwave.secret_hash' => $secretHash,
            'services.flutterwave.base_url' => 'https://api.flutterwave.com/v3',
            'services.flutterwave.callback_url' => 'https://school.example/payments/flutterwave/callback',
        ]);
        $this->forcePublicUrl();

        $this->assertTrue(app(FlutterwaveConfiguration::class)->checkoutReady());
        $this->artisan('payments:flutterwave-status')
            ->expectsOutputToContain('Configured (hidden)')
            ->doesntExpectOutputToContain($secretKey)
            ->doesntExpectOutputToContain($secretHash)
            ->assertSuccessful();
    }

    public function test_http_callback_or_webhook_urls_keep_checkout_disabled(): void
    {
        config()->set([
            'services.flutterwave.enabled' => true,
            'services.flutterwave.secret_key' => 'FLWSECK_TEST-12345678901234567890-X',
            'services.flutterwave.secret_hash' => 'a-long-random-secret-hash',
            'services.flutterwave.base_url' => 'https://api.flutterwave.com/v3',
            'services.flutterwave.callback_url' => 'http://localhost/payments/flutterwave/callback',
        ]);

        $this->assertFalse(app(FlutterwaveConfiguration::class)->checkoutReady());
    }

    private function forcePublicUrl(): void
    {
        $this->app['url']->forceRootUrl('https://school.example');
        $this->app['url']->forceScheme('https');
    }
}
