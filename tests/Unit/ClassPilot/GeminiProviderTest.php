<?php

namespace Tests\Unit\ClassPilot;

use App\Services\ClassPilot\Exceptions\ClassPilotException;
use App\Services\ClassPilot\GeminiProvider;
use Tests\TestCase;

class GeminiProviderTest extends TestCase
{
    public function test_missing_credentials_fail_safely_without_an_http_request(): void
    {
        config(['class-pilot.api_key' => null]);
        $this->expectException(ClassPilotException::class);
        $this->expectExceptionMessage('not configured');
        app(GeminiProvider::class)->generate('system', 'prompt');
    }
}
