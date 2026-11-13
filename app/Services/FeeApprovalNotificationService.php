<?php

namespace App\Services;

use App\Mail\FeeApprovedParentMail;
use App\Models\Fee;
use App\Models\FeeApprovalNotificationDelivery;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class FeeApprovalNotificationService
{
    /** @return array{queued: int, failed: int, skipped: int} */
    public function queueForParents(Fee $fee): array
    {
        $fee->loadMissing('feeCategory.school');
        $queued = 0;
        $failed = 0;
        $skipped = 0;

        User::query()
            ->role('parent')
            ->where('school_id', $fee->feeCategory->school_id)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->eachById(function (User $parent) use ($fee, &$queued, &$failed, &$skipped): void {
                $result = $this->queueForParent($fee, $parent);
                match ($result) {
                    'queued' => $queued++,
                    'failed' => $failed++,
                    default => $skipped++,
                };
            });

        return compact('queued', 'failed', 'skipped');
    }

    private function queueForParent(Fee $fee, User $parent): string
    {
        $email = strtolower(trim((string) $parent->email));
        $key = implode(':', [
            'fee-approved',
            $fee->id,
            $fee->approval_version,
            hash('sha256', $email),
        ]);

        $delivery = FeeApprovalNotificationDelivery::query()->firstOrCreate(
            ['deduplication_key' => $key],
            [
                'fee_id' => $fee->id,
                'recipient_id' => $parent->id,
                'recipient_email' => $email,
                'status' => 'pending',
            ]
        );

        if (! $delivery->wasRecentlyCreated && $delivery->status === 'queued') {
            return 'skipped';
        }

        if ($parent->email_is_placeholder || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $delivery->update(['status' => 'failed', 'error' => 'Recipient has no valid non-placeholder email address.']);

            return 'failed';
        }

        try {
            Mail::to($email)->queue(new FeeApprovedParentMail($fee, $parent));
            $delivery->update(['status' => 'queued', 'queued_at' => now(), 'error' => null]);

            return 'queued';
        } catch (Throwable $exception) {
            $delivery->update([
                'status' => 'failed',
                'error' => Str::limit($exception->getMessage(), 2000, ''),
            ]);
            report($exception);

            return 'failed';
        }
    }
}
