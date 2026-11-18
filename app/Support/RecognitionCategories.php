<?php

namespace App\Support;

class RecognitionCategories
{
    public const CUSTOM = 'custom';

    public static function all(): array
    {
        return [
            'academic_excellence' => 'Academic Excellence',
            'most_improved' => 'Most Improved Student',
            'leadership_responsibility' => 'Leadership & Responsibility',
            'character_integrity' => 'Good Character & Integrity',
            'discipline_punctuality' => 'Discipline & Punctuality',
            'creativity_innovation' => 'Creativity & Innovation',
            'teamwork_collaboration' => 'Teamwork & Collaboration',
            'sports_extracurricular' => 'Sports & Extracurricular Excellence',
            'outstanding_contribution' => 'Outstanding Contribution',
            self::CUSTOM => 'Custom recognition',
        ];
    }

    public static function label(string $category, ?string $custom = null): string
    {
        return $category === self::CUSTOM
            ? (trim((string) $custom) ?: 'Custom recognition')
            : (self::all()[$category] ?? 'Recognition');
    }
}
