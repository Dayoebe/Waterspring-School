<?php

namespace App\Models;

use App\Traits\InSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class StudentActivityRecord extends Model
{
    use InSchool;

    protected $fillable = [
        'school_id', 'student_id', 'classification', 'category', 'title', 'description', 'occurred_on',
        'academic_year_id', 'semester_id', 'subject_id', 'club_id', 'sport', 'school_event_id',
        'student_recognition_id', 'approval_status', 'review_note', 'submitted_by', 'observed_by',
        'approved_by', 'approved_at', 'eligible_timeline', 'eligible_portfolio', 'eligible_celebration',
        'eligible_digest', 'eligible_display', 'eligible_house_points', 'eligible_memory_book',
    ];

    protected $casts = [
        'occurred_on' => 'date', 'approved_at' => 'datetime', 'eligible_timeline' => 'boolean',
        'eligible_portfolio' => 'boolean', 'eligible_celebration' => 'boolean', 'eligible_digest' => 'boolean',
        'eligible_display' => 'boolean', 'eligible_house_points' => 'boolean', 'eligible_memory_book' => 'boolean',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id')->withTrashed();
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

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(SchoolEvent::class, 'school_event_id');
    }

    public function recognition(): BelongsTo
    {
        return $this->belongsTo(StudentRecognition::class, 'student_recognition_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by')->withTrashed();
    }

    public function observer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'observed_by')->withTrashed();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }

    public function media(): BelongsToMany
    {
        return $this->belongsToMany(MediaAsset::class, 'student_activity_record_media')
            ->withPivot(['caption', 'added_by'])->withTimestamps();
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('approval_status', 'approved')->whereNotNull('approved_at');
    }

    public function getDisplayTitleAttribute(): string
    {
        return $this->recognition ? $this->recognition->category_label : ((string) $this->title ?: 'Student activity');
    }

    public function getDisplayDescriptionAttribute(): string
    {
        return $this->recognition ? $this->recognition->reason : (string) $this->description;
    }
}
