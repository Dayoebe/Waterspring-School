<?php

namespace App\Jobs;

use App\Mail\ParentWeeklyDigestMail;
use App\Models\ParentDigestDelivery;
use App\Models\ParentDigestSetting;
use App\Services\ParentDigestBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendParentWeeklyDigest implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public array $backoff = [60, 300, 900];

    public function __construct(public int $deliveryId) {}

    public function handle(ParentDigestBuilder $builder): void
    {
        $delivery = ParentDigestDelivery::query()->withoutGlobalScope('school')->with('guardian.school')->find($this->deliveryId);
        if (! $delivery || $delivery->sent_at) {
            return;
        }

        $guardian = $delivery->guardian;
        if (! $guardian || $guardian->locked || blank($guardian->email)) {
            $delivery->update(['status' => 'failed', 'failed_at' => now(), 'failure_code' => 'RecipientUnavailable']);

            return;
        }

        $delivery->increment('attempts');
        $settings = ParentDigestSetting::forSchool((int) $delivery->school_id);
        $digest = $builder->build(
            $guardian,
            $delivery->week_start_date->startOfDay(),
            $delivery->week_end_date->endOfDay(),
            (int) $settings->upcoming_days,
        );
        if ($digest['children'] === []) {
            $delivery->update(['status' => 'skipped', 'child_count' => 0]);

            return;
        }

        Mail::to($guardian->email)->send(new ParentWeeklyDigestMail($digest));
        $delivery->update([
            'status' => 'sent',
            'child_count' => count($digest['children']),
            'payload_hash' => hash('sha256', json_encode($digest, JSON_THROW_ON_ERROR)),
            'sent_at' => now(),
            'failed_at' => null,
            'failure_code' => null,
        ]);
    }

    public function failed(?\Throwable $exception): void
    {
        ParentDigestDelivery::query()->withoutGlobalScope('school')->whereKey($this->deliveryId)->update([
            'status' => 'failed',
            'failed_at' => now(),
            'failure_code' => $exception ? class_basename($exception) : 'UnknownFailure',
        ]);
    }
}
