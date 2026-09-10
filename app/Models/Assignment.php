<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Assignment extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'school_id', 'academic_year_id', 'semester_id', 'my_class_id', 'subject_id',
        'teacher_id', 'title', 'instructions', 'due_at', 'max_score',
        'attachment_path', 'attachment_name', 'published_at', 'notifications_enabled',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'published_at' => 'datetime',
        'max_score' => 'decimal:2',
        'notifications_enabled' => 'boolean',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

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

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(StudentRecord::class, 'assignment_recipients')
            ->withPivot('assigned_at')
            ->withTimestamps();
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(AssignmentQuestion::class)->orderBy('position');
    }
}
