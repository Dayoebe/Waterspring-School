<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InterventionFollowUp extends Model
{
    protected $fillable = ['student_intervention_alert_id', 'owner_id', 'title', 'notes', 'due_on', 'status', 'escalated_at', 'escalated_by', 'completed_at', 'completed_by'];

    protected $casts = ['due_on' => 'date', 'escalated_at' => 'datetime', 'completed_at' => 'datetime'];

    public function alert()
    {
        return $this->belongsTo(StudentInterventionAlert::class, 'student_intervention_alert_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id')->withTrashed();
    }
}
