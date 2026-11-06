<?php

namespace App\Jobs;

use App\Mail\BroadcastMessageMail;
use App\Models\BroadcastMessageRecipient;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SendBroadcastMessageEmail implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public array $backoff = [60, 300, 900];

    public function __construct(
        public int $broadcastMessageId,
        public int $recipientId,
    ) {}

    public function handle(): void
    {
        $delivery = BroadcastMessageRecipient::query()
            ->with('broadcastMessage')
            ->where('broadcast_message_id', $this->broadcastMessageId)
            ->where('user_id', $this->recipientId)
            ->first();

        if (! $delivery || $delivery->email_delivered_at || ! $delivery->email) {
            return;
        }

        $recipient = User::query()
            ->whereKey($this->recipientId)
            ->where('school_id', $delivery->school_id)
            ->first();

        if (! $recipient || ! $delivery->broadcastMessage) {
            return;
        }

        Mail::to($delivery->email)->sendNow(
            new BroadcastMessageMail($delivery->broadcastMessage, $recipient)
        );

        DB::transaction(function () use ($delivery): void {
            $updated = BroadcastMessageRecipient::query()
                ->whereKey($delivery->id)
                ->whereNull('email_delivered_at')
                ->update(['email_delivered_at' => now()]);

            if ($updated === 1) {
                $delivery->broadcastMessage()->whereNull('email_sent_at')->update(['email_sent_at' => now()]);
            }
        });
    }
}
