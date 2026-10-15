<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClubWeeklyAgenda extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_term_plan_id', 'week_number', 'scheduled_for', 'title', 'agenda', 'status',
        'completion_notes', 'completed_at', 'completed_by', 'created_by',
    ];

    protected $casts = ['scheduled_for' => 'date', 'completed_at' => 'datetime'];

    public function termPlan(): BelongsTo
    {
        return $this->belongsTo(ClubTermPlan::class, 'club_term_plan_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
