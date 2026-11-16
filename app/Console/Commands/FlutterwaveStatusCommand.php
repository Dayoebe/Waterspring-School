<?php

namespace App\Console\Commands;

use App\Support\FlutterwaveConfiguration;
use Illuminate\Console\Command;

class FlutterwaveStatusCommand extends Command
{
    protected $signature = 'payments:flutterwave-status';

    protected $description = 'Check Flutterwave configuration without displaying secret values';

    public function handle(FlutterwaveConfiguration $configuration): int
    {
        $callbackUrl = $configuration->callbackUrl();
        $webhookUrl = $configuration->webhookUrl();
        $checks = [
            ['Payments enabled', $configuration->enabled(), $configuration->enabled() ? 'Yes' : 'No'],
            ['Secret key', $configuration->hasValidSecretKey(), $configuration->hasValidSecretKey() ? 'Configured (hidden)' : 'Missing or invalid'],
            ['Webhook secret hash', $configuration->hasValidSecretHash(), $configuration->hasValidSecretHash() ? 'Configured (hidden)' : 'Missing or too short'],
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
            $this->error('Flutterwave checkout is not ready. Update .env, run php artisan config:clear, and check again.');

            return self::FAILURE;
        }

        $this->info('Flutterwave checkout configuration is ready. Complete a small provider acceptance payment before going live.');

        return self::SUCCESS;
    }
}
