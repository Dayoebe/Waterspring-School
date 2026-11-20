<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Club;
use App\Models\SchoolEvent;
use App\Models\Semester;
use App\Models\StudentActivityRecord;
use App\Models\StudentRecognition;
use App\Models\Subject;
use App\Models\User;
use App\Support\ContentVisibility;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentActivityRecordService
{
    public function create(User $student, User $actor, array $data, ?UploadedFile $evidence = null): StudentActivityRecord
    {
        $isStudent = $actor->id === $student->id && $actor->hasRole('student');
        if (! $isStudent && ! app(StudentRecordAccessService::class)->canContribute($actor, $student)) {
            abort(403);
        }
        $classification = $isStudent ? 'student_submitted' : (string) ($data['classification'] ?? 'observed');
        if (! in_array($classification, ['verified', 'observed', 'student_submitted'], true)) {
            throw ValidationException::withMessages(['classification' => 'Select a valid record classification.']);
        }
        if ($classification === 'verified' && ! $actor->can('approve student records')) {
            throw ValidationException::withMessages(['classification' => 'Only an authorised administrator can create a verified record.']);
        }
        $this->validateAssociations($actor, $data);

        $record = DB::transaction(function () use ($student, $actor, $data, $classification, $isStudent): StudentActivityRecord {
            $approved = $classification === 'verified' && $actor->can('approve student records');

            return StudentActivityRecord::query()->create([
                'school_id' => $actor->school_id, 'student_id' => $student->id, 'classification' => $classification,
                'category' => $data['category'], 'title' => trim($data['title']), 'description' => trim($data['description']),
                'occurred_on' => $data['occurred_on'], 'academic_year_id' => $data['academic_year_id'] ?: null,
                'semester_id' => $data['semester_id'] ?: null, 'subject_id' => $data['subject_id'] ?: null,
                'club_id' => $data['club_id'] ?: null, 'sport' => filled($data['sport'] ?? null) ? trim($data['sport']) : null,
                'school_event_id' => $data['school_event_id'] ?: null, 'approval_status' => $approved ? 'approved' : 'pending',
                'submitted_by' => $actor->id, 'observed_by' => $isStudent ? null : $actor->id,
                'approved_by' => $approved ? $actor->id : null, 'approved_at' => $approved ? now() : null,
                ...($isStudent ? $this->studentEligibility() : $this->eligibility($data)),
            ]);
        });

        if ($evidence) {
            $asset = app(ManagedMediaService::class)->storePrivate($evidence, $actor, 'student_record_evidence', ContentVisibility::RECIPIENT, $student, $record->display_title);
            $record->media()->attach($asset->id, ['added_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        }
        app(AuditTrail::class)->record((int) $actor->school_id, 'student_records', 'student_record.created', 'A student activity record was created.', $record, null, ['classification' => $classification, 'status' => $record->approval_status], ['student_id' => $student->id], $actor);

        return $record->fresh(['media', 'recognition']);
    }

    public function review(StudentActivityRecord $record, User $actor, string $decision, ?string $note = null): void
    {
        abort_unless($actor->can('approve student records') && (int) $record->school_id === (int) $actor->school_id, 403);
        if (! in_array($decision, ['approved', 'rejected'], true)) {
            throw ValidationException::withMessages(['review' => 'Select approve or reject.']);
        }
        $old = $record->approval_status;
        $record->update([
            'approval_status' => $decision, 'review_note' => filled($note) ? trim($note) : null,
            'approved_by' => $decision === 'approved' ? $actor->id : null,
            'approved_at' => $decision === 'approved' ? now() : null,
        ]);
        $record->media()->update(['visibility' => $decision === 'approved' ? ContentVisibility::GUARDIANS : ContentVisibility::RECIPIENT]);
        app(AuditTrail::class)->record((int) $actor->school_id, 'student_records', 'student_record.reviewed', 'A student activity record was reviewed.', $record, ['status' => $old], ['status' => $decision], actor: $actor);
    }

    public function syncRecognition(StudentRecognition $recognition): StudentActivityRecord
    {
        return StudentActivityRecord::query()->updateOrCreate(
            ['student_recognition_id' => $recognition->id],
            [
                'school_id' => $recognition->school_id, 'student_id' => $recognition->student_id,
                'classification' => 'verified', 'category' => 'recognition', 'title' => null, 'description' => null,
                'occurred_on' => $recognition->week_start_date, 'academic_year_id' => $recognition->academic_year_id,
                'semester_id' => $recognition->semester_id, 'approval_status' => $recognition->status === 'published' ? 'approved' : 'pending',
                'submitted_by' => $recognition->created_by, 'observed_by' => $recognition->created_by,
                'approved_by' => $recognition->published_by, 'approved_at' => $recognition->published_at,
                'eligible_timeline' => true, 'eligible_portfolio' => false, 'eligible_celebration' => true,
                'eligible_digest' => true, 'eligible_display' => $recognition->public_display_approved,
                'eligible_house_points' => false, 'eligible_memory_book' => true,
            ],
        );
    }

    private function eligibility(array $data): array
    {
        return collect(['timeline', 'portfolio', 'celebration', 'digest', 'display', 'house_points', 'memory_book'])
            ->mapWithKeys(fn ($flag) => ['eligible_'.$flag => (bool) ($data['eligible_'.$flag] ?? false)])->all();
    }

    private function studentEligibility(): array
    {
        return [
            'eligible_timeline' => true, 'eligible_portfolio' => false, 'eligible_celebration' => false,
            'eligible_digest' => false, 'eligible_display' => false, 'eligible_house_points' => false,
            'eligible_memory_book' => false,
        ];
    }

    private function validateAssociations(User $actor, array $data): void
    {
        $schoolId = $actor->school_id;
        $valid = fn (string $model, mixed $id) => ! $id || $model::query()->where('school_id', $schoolId)->whereKey($id)->exists();
        abort_unless($valid(AcademicYear::class, $data['academic_year_id'] ?? null), 422, 'The selected academic year is invalid.');
        abort_unless($valid(Semester::class, $data['semester_id'] ?? null), 422, 'The selected term is invalid.');
        abort_unless($valid(Subject::class, $data['subject_id'] ?? null), 422, 'The selected subject is invalid.');
        abort_unless($valid(Club::class, $data['club_id'] ?? null), 422, 'The selected club is invalid.');
        abort_unless($valid(SchoolEvent::class, $data['school_event_id'] ?? null), 422, 'The selected event is invalid.');
        if (($data['academic_year_id'] ?? null) && ($data['semester_id'] ?? null)) {
            abort_unless(Semester::query()->whereKey($data['semester_id'])->where('academic_year_id', $data['academic_year_id'])->exists(), 422, 'The selected term does not belong to that academic year.');
        }
    }
}
