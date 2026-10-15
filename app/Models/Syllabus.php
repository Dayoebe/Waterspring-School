<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Syllabus extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'description', 'file', 'subject_id', 'semester_id', 'academic_year_id',
        'my_class_id', 'teacher_id', 'overview', 'aims', 'learning_outcomes',
        'scope_and_sequence', 'prerequisites', 'teaching_methodology', 'assessment_plan',
        'learning_resources', 'differentiation', 'cross_curricular_links', 'homework_policy',
        'references', 'is_published', 'published_at',
    ];

    protected $casts = ['is_published' => 'boolean', 'published_at' => 'datetime'];

    /**
     * Get the subject that owns the Syllabus.
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(MyClass::class, 'my_class_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function scopeForSchool(Builder $query, ?int $schoolId): Builder
    {
        if (! $schoolId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('subject', function (Builder $subjectQuery) use ($schoolId) {
            $subjectQuery->where('school_id', $schoolId);
        });
    }

    public function scopeForSemester(Builder $query, ?int $semesterId): Builder
    {
        if (! $semesterId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('semester_id', $semesterId);
    }

    public function scopeForClass(Builder $query, ?int $classId): Builder
    {
        if (! $classId) {
            return $query;
        }

        return $query->where(function (Builder $syllabusQuery) use ($classId) {
            $syllabusQuery->where('my_class_id', $classId)
                ->orWhere(function (Builder $legacyQuery) use ($classId) {
                    $legacyQuery->whereNull('my_class_id')->whereHas('subject', function (Builder $subjectQuery) use ($classId) {
                        $subjectQuery->where('my_class_id', $classId)
                            ->orWhereHas('classes', fn (Builder $assignedClassQuery) => $assignedClassQuery->where('my_classes.id', $classId));
                    });
                });
        });
    }
}
