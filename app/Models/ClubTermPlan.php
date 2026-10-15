<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClubTermPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id', 'academic_year_id', 'semester_id', 'goal', 'objectives', 'strengths',
        'focus_areas', 'resources_needed', 'success_indicators', 'notes', 'published_at',
        'created_by', 'updated_by',
    ];

    protected $casts = ['published_at' => 'datetime'];

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function agendas(): HasMany
    {
        return $this->hasMany(ClubWeeklyAgenda::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
