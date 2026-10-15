<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SchemeTopic extends Model
{
    use HasFactory;

    protected $fillable = [
        'scheme_of_work_id', 'week_number', 'scheduled_for', 'topic', 'aim', 'objectives',
        'content_outline', 'teacher_activities', 'learner_activities', 'resources', 'assessment',
        'homework', 'status', 'completion_notes', 'completed_at', 'completed_by',
    ];

    protected $casts = ['scheduled_for' => 'date', 'completed_at' => 'datetime'];

    public function scheme()
    {
        return $this->belongsTo(SchemeOfWork::class, 'scheme_of_work_id');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
