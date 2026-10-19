<?php

namespace App\Services\ClassPilot;

use App\Services\ClassPilot\Exceptions\ClassPilotException;
use Illuminate\Support\Facades\Validator;

class ResponseValidator
{
    public function validate(string $feature, array $response): array
    {
        $rules = match ($feature) {
            'lesson' => ['title' => 'required|string|max:200', 'summary' => 'required|string', 'objectives' => 'required|array|min:1', 'sections' => 'required|array|min:1'],
            'assessment' => ['title' => 'required|string|max:200', 'instructions' => 'required|string', 'questions' => 'required|array|min:1', 'questions.*.prompt' => 'required|string', 'questions.*.marks' => 'required|integer|min:1|max:100', 'total_marks' => 'required|integer|min:1'],
            'practice' => ['title' => 'required|string|max:200', 'questions' => 'required|array|min:1', 'questions.*.prompt' => 'required|string', 'questions.*.answer' => 'required|string', 'questions.*.explanation' => 'required|string'],
            default => ['answer' => 'required|string|max:30000', 'suggested_next_steps' => 'nullable|array'],
        };

        $validator = Validator::make($response, $rules);
        if ($validator->fails()) {
            throw new ClassPilotException('The AI response was incomplete or malformed. Nothing was saved; please regenerate it.');
        }

        if ($feature === 'assessment') {
            $sum = collect($response['questions'])->sum(fn ($question) => (int) ($question['marks'] ?? 0));
            if ($sum !== (int) $response['total_marks']) {
                throw new ClassPilotException('The generated question marks do not match the stated total. Nothing was saved.');
            }
        }

        return $response;
    }
}
