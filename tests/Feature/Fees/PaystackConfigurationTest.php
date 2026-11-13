<?php

namespace Tests\Feature\Fees;

use App\Support\PaystackConfiguration;
use Tests\TestCase;

class PaystackConfigurationTest extends TestCase
{
    public function test_checkout_is_not_ready_when_payments_are_disabled_or_the_key_is_missing(): void
    {
        config()->set([
            'services.paystack.enabled' => false,
            'services.paystack.secret_key' => null,
            'services.paystack.base_url' => 'https://api.paystack.co',
            'services.paystack.callback_url' => 'https://school.example/payments/paystack/callback',
        ]);
        $this->forcePublicUrl();

        $this->assertFalse(app(PaystackConfiguration::class)->checkoutReady());
        $this->artisan('payments:paystack-status')
            ->expectsOutputToContain('Paystack checkout is not ready')
            ->assertFailed();
    }

    public function test_status_accepts_a_complete_https_configuration_without_printing_the_key(): void
    {
        $secret = 'sk_test_do_not_print_this_value';
        config()->set([
            'services.paystack.enabled' => true,
            'services.paystack.secret_key' => $secret,
            'services.paystack.base_url' => 'https://api.paystack.co',
            'services.paystack.callback_url' => 'https://school.example/payments/paystack/callback',
        ]);
        $this->forcePublicUrl();

        $this->assertTrue(app(PaystackConfiguration::class)->checkoutReady());
        $this->artisan('payments:paystack-status')
            ->expectsOutputToContain('Configured (hidden)')
            ->doesntExpectOutputToContain($secret)
            ->assertSuccessful();
    }

    public function test_http_callback_or_webhook_urls_keep_checkout_disabled(): void
    {
        config()->set([
            'services.paystack.enabled' => true,
            'services.paystack.secret_key' => 'sk_test_server_only',
            'services.paystack.base_url' => 'https://api.paystack.co',
            'services.paystack.callback_url' => 'http://localhost/payments/paystack/callback',
        ]);

        $this->assertFalse(app(PaystackConfiguration::class)->checkoutReady());
    }

    private function forcePublicUrl(): void
    {
        $this->app['url']->forceRootUrl('https://school.example');
        $this->app['url']->forceScheme('https');
    }
}
