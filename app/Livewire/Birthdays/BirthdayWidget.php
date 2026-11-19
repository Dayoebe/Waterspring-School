<?php

namespace App\Livewire\Birthdays;

use App\Services\BirthdayService;
use Livewire\Component;

class BirthdayWidget extends Component
{
    public function render(BirthdayService $birthdays)
    {
        $user = auth()->user();
        if (! $user || ! $birthdays->canView($user)) {
            return view('livewire.birthdays.birthday-widget', ['visible' => false, 'panel' => [], 'canSend' => false]);
        }

        return view('livewire.birthdays.birthday-widget', [
            'visible' => true,
            'panel' => $birthdays->dashboard((int) $user->school_id),
            'canSend' => $user->can('send birthday wishes') && $birthdays->settings((int) $user->school_id)->wishes_enabled,
        ]);
    }
}
