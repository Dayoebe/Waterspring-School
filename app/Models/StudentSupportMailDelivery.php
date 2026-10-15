<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentSupportMailDelivery extends Model
{
    use HasFactory;
    protected $fillable = ['student_support_case_id', 'recipient_id', 'event', 'deduplication_key', 'status', 'error', 'sent_at'];
    protected $casts = ['sent_at' => 'datetime'];
    public function supportCase() { return $this->belongsTo(StudentSupportCase::class, 'student_support_case_id'); }
    public function recipient() { return $this->belongsTo(User::class, 'recipient_id'); }
}
