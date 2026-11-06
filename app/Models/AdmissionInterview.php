<?php

namespace App\Models;

use App\Traits\InSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionInterview extends Model
{
    use InSchool;

    protected $fillable = [
        'admission_registration_id', 'school_id', 'scheduled_at', 'duration_minutes', 'mode',
        'location', 'interviewer_id', 'status', 'outcome', 'notes', 'created_by', 'completed_at',
    ];

    protected $casts = ['scheduled_at' => 'datetime', 'completed_at' => 'datetime'];

    public function admissionRegistration(): BelongsTo
    {
        return $this->belongsTo(AdmissionRegistration::class);
    }

    public function interviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'interviewer_id');
    }
}
