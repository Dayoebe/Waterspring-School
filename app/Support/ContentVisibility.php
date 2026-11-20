<?php

namespace App\Support;

use Illuminate\Validation\Rule;

final class ContentVisibility
{
    public const PRIVATE = 'private';

    public const RECIPIENT = 'recipient';

    public const GUARDIANS = 'guardians';

    public const SCHOOL = 'school';

    public const PUBLIC = 'public';

    public static function values(): array
    {
        return [self::PRIVATE, self::RECIPIENT, self::GUARDIANS, self::SCHOOL, self::PUBLIC];
    }

    public static function rule(): Rule
    {
        return Rule::in(self::values());
    }
}
