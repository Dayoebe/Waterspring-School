<?php

namespace App\Services\OpenLearning;

use Illuminate\Support\Str;

class SessionStore
{
    private const KEY = 'open_learning.active';

    public function current(): array
    {
        $state = session(self::KEY, []);
        if ($state === []) {
            return [];
        }

        $lastActivity = isset($state['last_activity_at']) ? now()->parse($state['last_activity_at']) : null;
        $expired = ! $lastActivity || $lastActivity->addMinutes((int) config('open-learning.session_lifetime_minutes'))->isPast();
        $claimedByAnotherUser = isset($state['claimed_by_user_id'])
            && (int) $state['claimed_by_user_id'] !== (int) (auth()->id() ?? 0);

        if ($expired || $claimedByAnotherUser) {
            $this->forget();

            return [];
        }

        return $state;
    }

    public function start(array $selection, string $style): array
    {
        $state = array_merge($selection, [
            'id' => (string) Str::uuid(),
            'claimed_by_user_id' => auth()->id(),
            'teaching_style' => $style,
            'current_section' => 1,
            'completed_sections' => [],
            'section_plan' => [],
            'sections' => [],
            'messages' => [],
            'assessment' => null,
            'beyond' => null,
            'created_at' => now()->toIso8601String(),
            'last_activity_at' => now()->toIso8601String(),
        ]);

        return $this->put($state);
    }

    public function put(array $state): array
    {
        $state['last_activity_at'] = now()->toIso8601String();
        session()->put(self::KEY, $state);

        return $state;
    }

    public function claim(int $userId): array
    {
        $state = $this->current();
        if ($state !== []) {
            $state['claimed_by_user_id'] = $userId;
            $this->put($state);
        }

        return $state;
    }

    public function forget(): void
    {
        session()->forget(self::KEY);
    }
}
