<?php

namespace App\Jobs;

use App\Mail\SchoolEventReminderMail;
use App\Models\SchoolEventReminderDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendSchoolEventReminder implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public array $backoff = [60, 300, 900];

    public function __construct(public int $deliveryId) {}

    public function handle(): void
    {
        $delivery = SchoolEventReminderDelivery::query()->with(['event.school', 'response.student', 'guardian'])->find($this->deliveryId);
        if (! $delivery || $delivery->sent_at) {
            return;
        }
        if (! $delivery->guardian || blank($delivery->guardian->email)) {
            $delivery->update(['status' => 'failed', 'failed_at' => now(), 'failure_code' => 'RecipientUnavailable']);

            return;
        }
        $delivery->increment('attempts');
        Mail::to($delivery->guardian->email)->send(new SchoolEventReminderMail($delivery->event, $delivery->response->student, $delivery->guardian));
        $delivery->update(['status' => 'sent', 'sent_at' => now(), 'failed_at' => null, 'failure_code' => null]);
    }

    public function failed(?\Throwable $exception): void
    {
        SchoolEventReminderDelivery::query()->whereKey($this->deliveryId)->update(['status' => 'failed', 'failed_at' => now(), 'failure_code' => $exception ? class_basename($exception) : 'UnknownFailure']);
    }
}
