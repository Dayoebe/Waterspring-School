<?php

namespace App\Console\Commands;

use App\Support\PaystackConfiguration;
use Illuminate\Console\Command;

class PaystackStatusCommand extends Command
{
    protected $signature = 'payments:paystack-status';

    protected $description = 'Check Paystack configuration without displaying secret values';

    public function handle(PaystackConfiguration $configuration): int
    {
        $callbackUrl = $configuration->callbackUrl();
        $webhookUrl = $configuration->webhookUrl();
        $checks = [
            ['Payments enabled', $configuration->enabled(), $configuration->enabled() ? 'Yes' : 'No'],
            ['Secret key', $configuration->hasValidSecretKey(), $configuration->hasValidSecretKey() ? 'Configured (hidden)' : 'Missing or invalid'],
            ['Key mode', $configuration->mode() !== null, $configuration->mode() ? ucfirst($configuration->mode()) : 'Unknown'],
            ['HTTPS API URL', $configuration->isSecurePublicUrl($configuration->baseUrl()), $configuration->baseUrl()],
            ['HTTPS callback URL', $configuration->isSecurePublicUrl($callbackUrl), $callbackUrl],
            ['HTTPS webhook URL', $configuration->isSecurePublicUrl($webhookUrl), $webhookUrl],
        ];

        $this->table(
            ['Check', 'Status', 'Value'],
            array_map(fn (array $check): array => [
                $check[0],
                $check[1] ? '<fg=green>PASS</>' : '<fg=red>FAIL</>',
                $check[2],
            ], $checks)
        );

        if (! $configuration->checkoutReady()) {
            $this->error('Paystack checkout is not ready. Update .env, run php artisan config:clear, and check again.');

            return self::FAILURE;
        }

        $this->info('Paystack checkout configuration is ready. Complete a small provider acceptance payment before going live.');

        return self::SUCCESS;
    }
}
