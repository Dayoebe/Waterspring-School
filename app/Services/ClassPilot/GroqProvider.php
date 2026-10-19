<?php

namespace App\Services\ClassPilot;

use App\Services\ClassPilot\Contracts\AiProvider;
use App\Services\ClassPilot\Exceptions\ClassPilotException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class GroqProvider implements AiProvider
{
    public function name(): string
    {
        return 'groq';
    }

    public function model(): string
    {
        return (string) config('class-pilot.model');
    }

    public function configured(): bool
    {
        return filled(config('class-pilot.api_key'));
    }

    public function generate(string $system, string $prompt): array
    {
        if (! $this->configured()) {
            throw new ClassPilotException('ClassPilot AI is not configured. An administrator must add the Groq API key.');
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withToken((string) config('class-pilot.api_key'))
                ->timeout((int) config('class-pilot.timeout'))
                ->retry((int) config('class-pilot.retries'), 400, throw: false)
                ->post(rtrim((string) config('class-pilot.base_url'), '/').'/chat/completions', [
                    'model' => $this->model(),
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.35,
                    'response_format' => ['type' => 'json_object'],
                ]);
        } catch (ConnectionException $exception) {
            throw new ClassPilotException('ClassPilot could not reach the AI service. Please try again later.', 0, $exception);
        }

        if (! $response->successful()) {
            $message = match ($response->status()) {
                401, 403 => 'The configured Groq API key is invalid or not authorised. An administrator must replace it.',
                404 => 'The configured Groq model is unavailable. An administrator must select a supported model.',
                429 => 'The AI service request limit has been reached. Please wait and try again.',
                default => 'The AI service could not complete this request.',
            };

            throw new ClassPilotException($message);
        }

        $text = $response->json('choices.0.message.content');
        if (! is_string($text) || trim($text) === '') {
            throw new ClassPilotException('The AI service returned an empty response.');
        }

        $decoded = json_decode($text, true);
        if (! is_array($decoded)) {
            throw new ClassPilotException('The AI service returned an invalid response. Please regenerate it.');
        }

        return $decoded;
    }
}
