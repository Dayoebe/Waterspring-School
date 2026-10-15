<?php

namespace App\Models;

use App\Traits\InSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BehaviourReport extends Model
{
    use HasFactory, InSchool;

    protected $fillable = [
        'school_id', 'student_id', 'parent_id', 'teacher_id', 'subject_id',
        'teacher_context', 'category', 'severity', 'title', 'occurred_on',
        'status', 'initiated_by', 'last_message_at', 'resolved_at', 'resolved_by',
    ];

    protected $casts = [
        'occurred_on' => 'date',
        'last_message_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function student(): BelongsTo { return $this->belongsTo(User::class, 'student_id'); }
    public function parent(): BelongsTo { return $this->belongsTo(User::class, 'parent_id'); }
    public function teacher(): BelongsTo { return $this->belongsTo(User::class, 'teacher_id'); }
    public function subject(): BelongsTo { return $this->belongsTo(Subject::class); }
    public function initiator(): BelongsTo { return $this->belongsTo(User::class, 'initiated_by'); }
    public function resolver(): BelongsTo { return $this->belongsTo(User::class, 'resolved_by'); }
    public function messages(): HasMany { return $this->hasMany(BehaviourReportMessage::class); }
}
