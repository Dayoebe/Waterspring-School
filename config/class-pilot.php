<?php

return [
    'enabled' => env('CLASS_PILOT_ENABLED', true),
    'provider' => env('CLASS_PILOT_PROVIDER', 'gemini'),
    'model' => env('GEMINI_MODEL', 'gemini-3.8-flash'),
    'api_key' => env('GEMINI_API_KEY'),
    'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
    'timeout' => (int) env('CLASS_PILOT_TIMEOUT', 30),
    'retries' => (int) env('CLASS_PILOT_RETRIES', 1),
    'daily_requests_per_user' => (int) env('CLASS_PILOT_DAILY_REQUESTS', 40),
    'max_context_characters' => (int) env('CLASS_PILOT_MAX_CONTEXT_CHARACTERS', 12000),
    'conversation_retention_days' => (int) env('CLASS_PILOT_RETENTION_DAYS', 90),
];
