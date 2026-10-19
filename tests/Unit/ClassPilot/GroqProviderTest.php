<?php

namespace Tests\Unit\ClassPilot;

use App\Services\ClassPilot\Exceptions\ClassPilotException;
use App\Services\ClassPilot\GroqProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GroqProviderTest extends TestCase
{
    public function test_it_returns_structured_json_from_the_chat_completion(): void
    {
        config([
            'class-pilot.api_key' => 'test-key',
            'class-pilot.model' => 'openai/gpt-oss-20b',
            'class-pilot.base_url' => 'https://api.groq.test/openai/v1',
        ]);
        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => '{"answer":"working"}']]],
        ])]);

        $result = app(GroqProvider::class)->generate('system', 'prompt');

        $this->assertSame(['answer' => 'working'], $result);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.groq.test/openai/v1/chat/completions'
            && $request['response_format'] === ['type' => 'json_object']
            && $request->hasHeader('Authorization', 'Bearer test-key'));
    }

    public function test_invalid_credentials_return_a_safe_error(): void
    {
        config(['class-pilot.api_key' => 'test-key']);
        Http::fake(['*' => Http::response(['error' => ['message' => 'provider detail']], 401)]);

        $this->expectException(ClassPilotException::class);
        $this->expectExceptionMessage('invalid or not authorised');

        app(GroqProvider::class)->generate('system', 'prompt');
    }
}
