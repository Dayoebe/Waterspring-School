<?php

namespace App\Mail\Concerns;

trait RetriesQueuedMail
{
    public int $tries = 3;

    public int $timeout = 120;

    public array $backoff = [60, 300, 900];
}
