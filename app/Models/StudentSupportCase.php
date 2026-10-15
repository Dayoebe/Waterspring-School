<?php

namespace App\Models;

use App\Traits\InSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentSupportCase extends Model
{
    use HasFactory, InSchool;

    protected $fillable = ['school_id', 'student_id', 'parent_id', 'lead_teacher_id', 'opened_by', 'source_type', 'source_id', 'category', 'priority', 'title', 'concern', 'goal', 'status', 'review_on', 'parent_acknowledged_at', 'completed_at'];
    protected $casts = ['review_on' => 'date', 'parent_acknowledged_at' => 'datetime', 'completed_at' => 'datetime'];

    public function student() { return $this->belongsTo(User::class, 'student_id'); }
    public function parent() { return $this->belongsTo(User::class, 'parent_id'); }
    public function leadTeacher() { return $this->belongsTo(User::class, 'lead_teacher_id'); }
    public function openedBy() { return $this->belongsTo(User::class, 'opened_by'); }
    public function actions() { return $this->hasMany(StudentSupportAction::class); }
    public function updates() { return $this->hasMany(StudentSupportUpdate::class); }
    public function mailDeliveries() { return $this->hasMany(StudentSupportMailDelivery::class); }
}
