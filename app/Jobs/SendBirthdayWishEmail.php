<?php

namespace App\Jobs;

use App\Mail\BirthdayWishMail;
use App\Models\BirthdayEmailDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendBirthdayWishEmail implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public array $backoff = [60, 300, 900];

    public function __construct(
        public int $deliveryId,
        public string $recipientEmail,
        public string $subject,
        public string $message,
        public string $senderName,
        public ?string $fromName = null,
        public ?string $fromEmail = null,
    ) {}

    public function handle(): void
    {
        $delivery = BirthdayEmailDelivery::query()->with(['celebrant.school'])->find($this->deliveryId);
        if (! $delivery || $delivery->sent_at) {
            return;
        }

        $mail = new BirthdayWishMail(
            celebrantName: $delivery->celebrant->name,
            senderName: $this->senderName,
            mailSubject: $this->subject,
            personalMessage: $this->message,
            schoolName: $delivery->celebrant->school?->name ?: config('app.name'),
            logoUrl: $delivery->celebrant->school?->logo_url ?: asset(config('app.logo')),
            fromName: $this->fromName,
            fromEmail: $this->fromEmail,
        );

        Mail::to($this->recipientEmail)->send($mail);
        $delivery->update(['status' => 'sent', 'sent_at' => now(), 'failed_at' => null, 'failure_code' => null]);
    }

    public function failed(?\Throwable $exception): void
    {
        BirthdayEmailDelivery::query()->whereKey($this->deliveryId)->update([
            'status' => 'failed',
            'failed_at' => now(),
            'failure_code' => $exception ? class_basename($exception) : 'UnknownFailure',
        ]);
    }
}
