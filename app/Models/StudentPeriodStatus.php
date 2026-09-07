<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentPeriodStatus extends Model
{
    public const EXCLUDED_STATUSES = ['withdrawn', 'transferred', 'inactive', 'suspended'];

    protected $fillable = [
        'school_id',
        'student_record_id',
        'academic_year_id',
        'semester_id',
        'status',
        'reason',
        'notes',
        'recorded_by',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function studentRecord(): BelongsTo
    {
        return $this->belongsTo(StudentRecord::class)->withoutGlobalScope('notGraduated');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
