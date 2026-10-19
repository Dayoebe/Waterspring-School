<?php

namespace Tests\Unit\ClassPilot;

use App\Services\ClassPilot\Exceptions\ClassPilotException;
use App\Services\ClassPilot\GeminiProvider;
use Illuminate\Support\Facades\Http;
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

    public function test_denied_project_returns_an_actionable_safe_error(): void
    {
        config(['class-pilot.api_key' => 'test-key']);
        Http::fake(['*' => Http::response([
            'error' => ['code' => 403, 'status' => 'PERMISSION_DENIED', 'message' => 'Sensitive provider detail'],
        ], 403)]);

        $this->expectException(ClassPilotException::class);
        $this->expectExceptionMessage('project is not authorised');

        app(GeminiProvider::class)->generate('system', 'prompt');
    }
}
