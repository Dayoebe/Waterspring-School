<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Validation\Rule;

final class Username
{
    public static function normalize(?string $value): ?string
    {
        $username = strtolower(trim((string) $value));

        return $username === '' ? null : $username;
    }

    /**
     * @return array<int, mixed>
     */
    public static function rules(?int $ignoreUserId = null, bool $required = false): array
    {
        $unique = Rule::unique(User::class, 'username');

        if ($ignoreUserId !== null) {
            $unique->ignore($ignoreUserId);
        }

        return [
            $required ? 'required' : 'nullable',
            'string',
            'min:3',
            'max:50',
            'regex:/\A[a-zA-Z0-9._-]+\z/',
            $unique,
        ];
    }
}
