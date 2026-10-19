<?php

namespace App\Services\ClassPilot;

use App\Services\ClassPilot\Contracts\AiProvider;
use App\Services\ClassPilot\Exceptions\ClassPilotException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class GeminiProvider implements AiProvider
{
    public function name(): string
    {
        return 'gemini';
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
            throw new ClassPilotException('ClassPilot AI is not configured. An administrator must add the Gemini API key.');
        }

        $url = rtrim((string) config('class-pilot.base_url'), '/').'/models/'.rawurlencode($this->model()).':generateContent';

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('class-pilot.timeout'))
                ->retry((int) config('class-pilot.retries'), 400, throw: false)
                ->withQueryParameters(['key' => config('class-pilot.api_key')])
                ->post($url, [
                    'systemInstruction' => ['parts' => [['text' => $system]]],
                    'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                        'temperature' => 0.35,
                    ],
                ]);
        } catch (ConnectionException $exception) {
            throw new ClassPilotException('ClassPilot could not reach the AI service. Please try again later.', 0, $exception);
        }

        if (! $response->successful()) {
            $code = $response->status() === 429 ? 'The AI service is busy. Please wait and try again.' : 'The AI service could not complete this request.';
            throw new ClassPilotException($code);
        }

        $text = $response->json('candidates.0.content.parts.0.text');
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
