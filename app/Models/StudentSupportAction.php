<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentSupportAction extends Model
{
    use HasFactory;
    protected $fillable = ['student_support_case_id', 'owner_id', 'title', 'details', 'due_on', 'status', 'completed_at'];
    protected $casts = ['due_on' => 'date', 'completed_at' => 'datetime'];
    public function supportCase() { return $this->belongsTo(StudentSupportCase::class, 'student_support_case_id'); }
    public function owner() { return $this->belongsTo(User::class, 'owner_id'); }
}
