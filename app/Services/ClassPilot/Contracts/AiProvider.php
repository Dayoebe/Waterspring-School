<?php

namespace App\Services\ClassPilot\Contracts;

interface AiProvider
{
    public function generate(string $system, string $prompt): array;

    public function name(): string;

    public function model(): string;

    public function configured(): bool;
}
