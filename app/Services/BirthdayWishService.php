<?php

namespace App\Services;

use App\Jobs\SendBirthdayWishEmail;
use App\Models\BirthdayEmailDelivery;
use App\Models\BirthdaySetting;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class BirthdayWishService
{
    public function __construct(private readonly BirthdayService $birthdays) {}

    public function sendManual(User $sender, int $celebrantId, string $subject, string $message): BirthdayEmailDelivery
    {
        $settings = $this->birthdays->settings((int) $sender->school_id);
        abort_unless($this->birthdays->canView($sender, $settings) && $settings->wishes_enabled && $sender->can('send birthday wishes'), 403);

        $celebrant = $this->eligibleCelebrant((int) $sender->school_id, $celebrantId);
        $recipient = $this->resolveRecipient($celebrant);

        if (! $recipient) {
            throw ValidationException::withMessages([
                'message' => 'This celebrant does not have an eligible registered email recipient.',
            ]);
        }

        $rateKey = "birthday-wishes:sender:{$sender->id}";
        $limit = max(1, (int) config('birthdays.manual_limit_per_hour', 5));
        if (RateLimiter::tooManyAttempts($rateKey, $limit)) {
            throw ValidationException::withMessages([
                'message' => 'You have reached the birthday-wish limit. Please try again later.',
            ]);
        }
        RateLimiter::hit($rateKey, 3600);

        $delivery = BirthdayEmailDelivery::query()->create([
            'school_id' => $sender->school_id,
            'celebrant_id' => $celebrant->id,
            'sender_id' => $sender->id,
            'recipient_user_id' => $recipient['user_id'],
            'kind' => 'manual',
            'birthday_year' => now(config('birthdays.timezone'))->year,
            'recipient_type' => $recipient['type'],
            'status' => 'queued',
            'queued_at' => now(),
        ]);

        if (! $this->dispatch($delivery, $recipient['email'], $subject, $message, $sender->name, $settings)) {
            throw ValidationException::withMessages([
                'message' => 'Your birthday wish could not be queued. Please try again later.',
            ]);
        }

        return $delivery;
    }

    public function queueAutomated(User $celebrant, BirthdaySetting $settings, int $birthdayYear): ?BirthdayEmailDelivery
    {
        $type = $celebrant->hasRole('student') ? 'student' : 'staff';
        if (($type === 'student' && ! $settings->automated_student_emails)
            || ($type === 'staff' && ! $settings->automated_staff_emails)) {
            return null;
        }

        $recipient = $this->resolveRecipient($celebrant);
        if (! $recipient) {
            return null;
        }

        $deduplicationKey = "birthday:auto:{$celebrant->school_id}:{$celebrant->id}:{$birthdayYear}";
        $delivery = BirthdayEmailDelivery::query()->firstOrCreate(
            ['deduplication_key' => $deduplicationKey],
            [
                'school_id' => $celebrant->school_id,
                'celebrant_id' => $celebrant->id,
                'sender_id' => null,
                'recipient_user_id' => $recipient['user_id'],
                'kind' => 'automated',
                'birthday_year' => $birthdayYear,
                'recipient_type' => $recipient['type'],
                'status' => 'queued',
                'queued_at' => now(),
            ],
        );

        if (! $delivery->wasRecentlyCreated) {
            return null;
        }

        $subject = str_replace('{name}', $celebrant->name, $settings->automated_subject);
        $message = str_replace('{name}', $celebrant->name, $settings->automated_message ?: config('birthdays.automated_message'));
        $senderName = $settings->sender_name ?: School::query()->whereKey($celebrant->school_id)->value('name') ?: config('app.name');
        if (! $this->dispatch($delivery, $recipient['email'], $subject, $message, $senderName, $settings)) {
            return null;
        }

        return $delivery;
    }

    private function eligibleCelebrant(int $schoolId, int $celebrantId): User
    {
        $allowed = $this->birthdays->celebrants($schoolId)->contains('id', $celebrantId);
        abort_unless($allowed, 404);

        return User::query()->where('school_id', $schoolId)->with(['roles', 'parents'])->findOrFail($celebrantId);
    }

    /** @return array{email:string,user_id:int,type:string}|null */
    public function resolveRecipient(User $celebrant): ?array
    {
        $celebrant->loadMissing(['roles', 'parents']);

        if ($this->isDeliverable($celebrant)) {
            return ['email' => $celebrant->email, 'user_id' => $celebrant->id, 'type' => $celebrant->hasRole('student') ? 'student' : 'staff'];
        }

        if ($celebrant->hasRole('student')) {
            $parent = $celebrant->parents->first(fn (User $parent): bool => $this->isDeliverable($parent));
            if ($parent) {
                return ['email' => $parent->email, 'user_id' => $parent->id, 'type' => 'guardian'];
            }
        }

        return null;
    }

    private function isDeliverable(User $user): bool
    {
        return ! $user->locked
            && ! $user->email_is_placeholder
            && filter_var($user->email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function dispatch(BirthdayEmailDelivery $delivery, string $email, string $subject, string $message, string $senderName, BirthdaySetting $settings): bool
    {
        try {
            SendBirthdayWishEmail::dispatch(
                $delivery->id,
                $email,
                $subject,
                $message,
                $senderName,
                $settings->sender_name,
                $settings->sender_email,
            );

            return true;
        } catch (\Throwable $exception) {
            $delivery->update([
                'status' => 'failed',
                'failed_at' => now(),
                'failure_code' => 'QueueDispatchFailure',
            ]);
            report($exception);

            return false;
        }
    }
}
