<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassExamParticipation extends Model
{
    public const NON_INTERNAL_STATUSES = ['external', 'exempt', 'postponed', 'cancelled'];

    protected $fillable = [
        'school_id',
        'academic_year_id',
        'semester_id',
        'my_class_id',
        'status',
        'examination_name',
        'reason',
        'notes',
        'recorded_by',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function myClass(): BelongsTo
    {
        return $this->belongsTo(MyClass::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
