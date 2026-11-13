<?php

namespace App\Services;

use App\Models\Fee;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Throwable;

class FeeApprovalService
{
    public function __construct(private readonly FeeApprovalNotificationService $notifications) {}

    /** @return array{fee: Fee, notifications: array{queued: int, failed: int, skipped: int}} */
    public function approve(Fee $fee, User $approver): array
    {
        if (! $approver->can('approve fee')) {
            throw new DomainException('You are not allowed to approve fees.');
        }

        $approvedFee = DB::transaction(function () use ($fee, $approver): Fee {
            $lockedFee = Fee::query()
                ->with('feeCategory')
                ->whereKey($fee->id)
                ->whereHas('feeCategory', fn ($query) => $query->where('school_id', $approver->school_id))
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedFee->status === 'approved') {
                throw new DomainException('This fee is already approved.');
            }
            if ((int) $lockedFee->getRawOriginal('default_amount') <= 0) {
                throw new DomainException('Add an amount greater than zero before approving this fee.');
            }
            if (mb_strlen(trim((string) $lockedFee->description)) < 10) {
                throw new DomainException('Add a clear explanation of at least 10 characters before approving this fee.');
            }

            $lockedFee->update([
                'status' => 'approved',
                'approval_version' => max(1, (int) $lockedFee->approval_version + 1),
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ]);

            app(AuditTrail::class)->record(
                (int) $lockedFee->feeCategory->school_id,
                'finance',
                'fee.approved',
                'A fee definition was approved and parent notifications were prepared.',
                $lockedFee,
                ['status' => 'draft'],
                [
                    'status' => 'approved',
                    'amount' => (string) $lockedFee->default_amount,
                    'approval_version' => $lockedFee->approval_version,
                ],
                actor: $approver,
                subjectLabel: $lockedFee->name,
            );

            return $lockedFee;
        }, 3);

        try {
            $notifications = $this->notifications->queueForParents($approvedFee);
        } catch (Throwable $exception) {
            report($exception);
            $notifications = ['queued' => 0, 'failed' => 0, 'skipped' => 0];
        }

        return ['fee' => $approvedFee, 'notifications' => $notifications];
    }
}
