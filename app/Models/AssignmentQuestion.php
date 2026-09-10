<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssignmentQuestion extends Model
{
    use HasFactory;

    public const TYPES = [
        'multiple_choice' => 'Multiple choice',
        'multiple_select' => 'Multiple answers',
        'true_false' => 'True or false',
        'fill_blank' => 'Fill in the gap',
        'short_answer' => 'Short answer',
        'long_essay' => 'Long essay',
    ];

    protected $fillable = [
        'assignment_id', 'question_type', 'prompt', 'options', 'correct_answers',
        'points', 'explanation', 'position', 'is_required',
    ];

    protected $casts = [
        'options' => 'array',
        'correct_answers' => 'array',
        'points' => 'decimal:2',
        'is_required' => 'boolean',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AssignmentAnswer::class);
    }

    public function isAutomaticallyMarked(): bool
    {
        return in_array($this->question_type, ['multiple_choice', 'multiple_select', 'true_false', 'fill_blank'], true)
            || ($this->question_type === 'short_answer' && filled($this->correct_answers));
    }

    public function mark(array|string|null $answer): ?array
    {
        if (! $this->isAutomaticallyMarked()) {
            return null;
        }

        $given = is_array($answer) ? $answer : [$answer];
        $correct = $this->correct_answers ?? [];
        $normalize = fn ($values) => collect($values)->map(fn ($value) => mb_strtolower(trim((string) $value)))->filter()->sort()->values()->all();
        $isCorrect = $normalize($given) === $normalize($correct);

        return ['is_correct' => $isCorrect, 'score' => $isCorrect ? (float) $this->points : 0.0];
    }
}
