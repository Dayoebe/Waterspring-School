<?php

namespace App\Models;

use App\Traits\InSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentPortfolioItem extends Model
{
    use InSchool;

    protected $fillable = [
        'school_id', 'student_id', 'student_activity_record_id', 'academic_year_id', 'semester_id',
        'subject_id', 'category', 'title', 'description', 'submission_source', 'status', 'visibility',
        'teacher_comment', 'submitted_by', 'reviewed_by', 'reviewed_at', 'archived_by', 'archived_at',
    ];

    protected $casts = ['reviewed_at' => 'datetime', 'archived_at' => 'datetime'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id')->withTrashed();
    }

    public function activityRecord(): BelongsTo
    {
        return $this->belongsTo(StudentActivityRecord::class, 'student_activity_record_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class)->withTrashed();
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by')->withTrashed();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    public function versions(): HasMany
    {
        return $this->hasMany(StudentPortfolioVersion::class)->orderByDesc('version_number');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved')->whereNotNull('reviewed_at');
    }
}
