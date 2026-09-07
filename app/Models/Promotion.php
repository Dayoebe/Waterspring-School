<?php

namespace App\Models;

use App\Traits\InSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Promotion extends Model
{
    use HasFactory;
    use InSchool;

    protected $fillable = [
        'old_class_id',
        'new_class_id',
        'old_section_id',
        'new_section_id',
        'academic_year_id',
        'from_academic_year_id',
        'movement_type',
        'students',
        'student_snapshots',
        'school_id',
    ];

    protected $casts = [
        'students' => 'array',
        'student_snapshots' => 'array',
    ];

    public function getLabelAttribute()
    {
        $oldClass = $this->oldClass?->name ?? 'Class unavailable';
        $oldSection = $this->oldSection?->name ?? 'No section';
        $newClass = $this->newClass?->name ?? 'Class unavailable';
        $newSection = $this->newSection?->name ?? 'No section';
        $academicYear = $this->academicYear
            ? "{$this->academicYear->start_year} - {$this->academicYear->stop_year}"
            : 'Academic year unavailable';

        return "{$oldClass} - {$oldSection} to {$newClass} - {$newSection} year: {$academicYear}";
    }

    public function oldClass(): BelongsTo
    {
        return $this->belongsTo(MyClass::class, 'old_class_id')->withTrashed();
    }

    public function newClass(): BelongsTo
    {
        return $this->belongsTo(MyClass::class, 'new_class_id')->withTrashed();
    }

    public function oldSection(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'old_section_id');
    }

    public function newSection(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'new_section_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function fromAcademicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'from_academic_year_id');
    }
}
