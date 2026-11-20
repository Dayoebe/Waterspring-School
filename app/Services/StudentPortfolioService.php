<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\MediaAsset;
use App\Models\Semester;
use App\Models\StudentPortfolioItem;
use App\Models\StudentPortfolioSetting;
use App\Models\StudentPortfolioVersion;
use App\Models\Subject;
use App\Models\User;
use App\Support\ContentVisibility;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentPortfolioService
{
    public function submit(User $student, User $actor, array $data, UploadedFile $file): StudentPortfolioItem
    {
        $isStudent = $actor->id === $student->id && $actor->hasRole('student');
        abort_unless($isStudent ? $actor->can('submit own portfolio') : app(StudentRecordAccessService::class)->canContribute($actor, $student), 403);
        $this->validateAssociations($actor, $data);

        return Cache::lock("portfolio-quota:{$actor->school_id}:{$student->id}", 30)->block(10, function () use ($student, $actor, $data, $file, $isStudent): StudentPortfolioItem {
            $setting = $this->setting((int) $actor->school_id);
            $this->assertQuota($student, (int) $file->getSize(), $setting);
            if ((int) $file->getSize() > $setting->per_file_max_bytes) {
                throw ValidationException::withMessages(['file' => 'This file exceeds the school per-file portfolio limit.']);
            }
            $asset = app(ManagedMediaService::class)->storePrivate($file, $actor, 'portfolio', ContentVisibility::RECIPIENT, $student, $data['title'], null, (int) $setting->per_file_max_bytes);

            return DB::transaction(function () use ($student, $actor, $data, $asset, $isStudent): StudentPortfolioItem {
                $item = StudentPortfolioItem::query()->create([
                    'school_id' => $actor->school_id, 'student_id' => $student->id,
                    'academic_year_id' => $data['academic_year_id'] ?: null, 'semester_id' => $data['semester_id'] ?: null,
                    'subject_id' => $data['subject_id'] ?: null, 'category' => $data['category'],
                    'title' => trim($data['title']), 'description' => filled($data['description']) ? trim($data['description']) : null,
                    'submission_source' => $isStudent ? 'student' : 'teacher', 'status' => 'pending', 'visibility' => 'private',
                    'submitted_by' => $actor->id,
                ]);
                StudentPortfolioVersion::query()->create(['student_portfolio_item_id' => $item->id, 'media_asset_id' => $asset->id, 'version_number' => 1, 'change_summary' => 'Initial submission', 'created_by' => $actor->id]);
                app(AuditTrail::class)->record((int) $actor->school_id, 'portfolio', 'portfolio.submitted', 'A portfolio item was submitted.', $item, null, ['version' => 1], ['student_id' => $student->id], $actor);

                return $item->fresh(['versions.media']);
            });
        });
    }

    public function addVersion(StudentPortfolioItem $item, User $actor, UploadedFile $file, ?string $summary): void
    {
        abort_if($item->archived_at, 422, 'Archived portfolio items cannot receive new versions.');
        $isOwner = $actor->id === $item->student_id && $actor->hasRole('student');
        abort_unless($isOwner || app(StudentRecordAccessService::class)->canContribute($actor, $item->student), 403);
        Cache::lock("portfolio-quota:{$item->school_id}:{$item->student_id}", 30)->block(10, function () use ($item, $actor, $file, $summary): void {
            $setting = $this->setting((int) $item->school_id);
            $this->assertQuota($item->student, (int) $file->getSize(), $setting);
            $asset = app(ManagedMediaService::class)->storePrivate($file, $actor, 'portfolio', ContentVisibility::RECIPIENT, $item->student, $item->title, null, (int) $setting->per_file_max_bytes);
            DB::transaction(function () use ($item, $actor, $asset, $summary): void {
                $locked = StudentPortfolioItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
                $version = ((int) $locked->versions()->max('version_number')) + 1;
                StudentPortfolioVersion::query()->create(['student_portfolio_item_id' => $locked->id, 'media_asset_id' => $asset->id, 'version_number' => $version, 'change_summary' => filled($summary) ? trim($summary) : null, 'created_by' => $actor->id]);
                $locked->update(['status' => 'pending', 'visibility' => 'private', 'reviewed_by' => null, 'reviewed_at' => null]);
                MediaAsset::query()->whereIn('id', $locked->versions()->pluck('media_asset_id'))->update(['visibility' => ContentVisibility::RECIPIENT]);
            });
        });
    }

    public function review(StudentPortfolioItem $item, User $actor, string $decision, ?string $comment): void
    {
        abort_unless($actor->can('manage student portfolio') && app(StudentRecordAccessService::class)->canViewPortfolio($actor, $item->student), 403);
        if (! in_array($decision, ['approved', 'rejected'], true)) {
            throw ValidationException::withMessages(['review' => 'Select approve or reject.']);
        }
        $item->update(['status' => $decision, 'visibility' => $decision === 'approved' ? 'guardians' : 'private', 'teacher_comment' => filled($comment) ? trim($comment) : null, 'reviewed_by' => $actor->id, 'reviewed_at' => now()]);
        MediaAsset::query()->whereIn('id', $item->versions()->pluck('media_asset_id'))
            ->update(['visibility' => $decision === 'approved' ? ContentVisibility::GUARDIANS : ContentVisibility::RECIPIENT]);
        app(AuditTrail::class)->record((int) $actor->school_id, 'portfolio', 'portfolio.reviewed', 'A portfolio item was reviewed.', $item, null, ['status' => $decision], actor: $actor);
    }

    public function archive(StudentPortfolioItem $item, User $actor): void
    {
        abort_unless($actor->can('manage student portfolio') && (int) $actor->school_id === (int) $item->school_id, 403);
        $item->update(['status' => 'archived', 'visibility' => 'private', 'archived_by' => $actor->id, 'archived_at' => now()]);
        MediaAsset::query()->whereIn('id', $item->versions()->pluck('media_asset_id'))->update(['visibility' => ContentVisibility::RECIPIENT]);
    }

    public function setting(int $schoolId): StudentPortfolioSetting
    {
        return StudentPortfolioSetting::query()->firstOrCreate(['school_id' => $schoolId]);
    }

    private function assertQuota(User $student, int $newBytes, StudentPortfolioSetting $setting): void
    {
        $used = MediaAsset::query()->where('subject_user_id', $student->id)->where('usage_area', 'portfolio')->sum('file_size');
        if ($used + $newBytes > $setting->per_student_quota_bytes) {
            throw ValidationException::withMessages(['file' => 'This upload would exceed the student portfolio storage quota.']);
        }
    }

    private function validateAssociations(User $actor, array $data): void
    {
        $schoolId = $actor->school_id;
        $valid = fn (string $model, mixed $id) => ! $id || $model::query()->where('school_id', $schoolId)->whereKey($id)->exists();
        abort_unless($valid(AcademicYear::class, $data['academic_year_id'] ?? null), 422, 'The selected academic year is invalid.');
        abort_unless($valid(Semester::class, $data['semester_id'] ?? null), 422, 'The selected term is invalid.');
        abort_unless($valid(Subject::class, $data['subject_id'] ?? null), 422, 'The selected subject is invalid.');
        if (($data['academic_year_id'] ?? null) && ($data['semester_id'] ?? null)) {
            abort_unless(Semester::query()->whereKey($data['semester_id'])->where('academic_year_id', $data['academic_year_id'])->exists(), 422, 'The selected term does not belong to that academic year.');
        }
    }
}
