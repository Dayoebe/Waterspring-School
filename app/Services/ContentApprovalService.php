<?php

namespace App\Services;

use App\Models\ContentReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContentApprovalService
{
    public function submit(Model $subject, User $submitter, string $workflow, array $snapshot = []): ContentReview
    {
        $schoolId = (int) $subject->getAttribute('school_id');
        $this->assertSameSchool($schoolId, $submitter);

        return DB::transaction(function () use ($subject, $submitter, $workflow, $snapshot, $schoolId): ContentReview {
            $reviews = ContentReview::query()->withoutGlobalScope('school')
                ->where('school_id', $schoolId)
                ->where('subject_type', $subject->getMorphClass())
                ->where('subject_id', $subject->getKey())
                ->where('workflow', $workflow)
                ->lockForUpdate();

            $pending = (clone $reviews)->pending()->latest('id')->first();
            if ($pending) {
                return $pending;
            }

            $review = ContentReview::query()->withoutGlobalScope('school')->create([
                'school_id' => $schoolId,
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'workflow' => $workflow,
                'status' => 'pending',
                'submission_version' => ((int) (clone $reviews)->max('submission_version')) + 1,
                'submitted_by' => $submitter->id,
                'submitted_at' => now(),
                'snapshot' => $snapshot ?: null,
            ]);

            app(AuditTrail::class)->record(
                $schoolId,
                'content',
                'content.review.submitted',
                'Content was submitted for review.',
                $subject,
                null,
                ['workflow' => $workflow, 'submission_version' => $review->submission_version],
                actor: $submitter,
            );

            return $review;
        });
    }

    public function approve(ContentReview $review, User $reviewer, ?string $note = null): ContentReview
    {
        return $this->decide($review, $reviewer, 'approved', $note);
    }

    public function reject(ContentReview $review, User $reviewer, string $note): ContentReview
    {
        return $this->decide($review, $reviewer, 'rejected', $note);
    }

    public function requestChanges(ContentReview $review, User $reviewer, string $note): ContentReview
    {
        return $this->decide($review, $reviewer, 'changes_requested', $note);
    }

    private function decide(ContentReview $review, User $reviewer, string $status, ?string $note): ContentReview
    {
        $this->assertSameSchool((int) $review->school_id, $reviewer);
        if (! $reviewer->can('moderate community content')) {
            throw ValidationException::withMessages(['review' => 'You are not authorised to moderate community content.']);
        }
        if ($status !== 'approved' && blank($note)) {
            throw ValidationException::withMessages(['review' => 'A decision note is required when content is not approved.']);
        }

        return DB::transaction(function () use ($review, $reviewer, $status, $note): ContentReview {
            $locked = ContentReview::query()->withoutGlobalScope('school')->lockForUpdate()->findOrFail($review->id);
            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['review' => 'This submission has already been reviewed.']);
            }

            $locked->forceFill([
                'status' => $status,
                'decided_by' => $reviewer->id,
                'decided_at' => now(),
                'decision_note' => filled($note) ? trim((string) $note) : null,
            ])->save();

            app(AuditTrail::class)->record(
                (int) $locked->school_id,
                'content',
                'content.review.'.$status,
                'A content review decision was recorded.',
                $locked->subject,
                ['status' => 'pending'],
                ['status' => $status, 'decision_note' => $locked->decision_note],
                ['workflow' => $locked->workflow, 'submission_version' => $locked->submission_version],
                $reviewer,
            );

            return $locked->fresh();
        });
    }

    private function assertSameSchool(int $schoolId, User $user): void
    {
        if ($schoolId <= 0 || $schoolId !== (int) $user->school_id) {
            throw ValidationException::withMessages(['review' => 'Content review must stay within the current school.']);
        }
    }
}
