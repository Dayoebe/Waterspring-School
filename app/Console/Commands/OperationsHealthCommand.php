<?php

namespace App\Console\Commands;

use App\Services\OperationalHealthService;
use Illuminate\Console\Command;

class OperationsHealthCommand extends Command
{
    protected $signature = 'ops:health';

    protected $description = 'Check database, scheduler heartbeat, and queue health';

    public function handle(OperationalHealthService $health): int
    {
        $result = $health->check();

        foreach ($result['checks'] as $name => $check) {
            $method = $check['healthy'] ? 'info' : 'error';
            $this->{$method}(sprintf('%s: %s', $name, $check['message']));
        }

        return $result['healthy'] ? self::SUCCESS : self::FAILURE;
    }
}
