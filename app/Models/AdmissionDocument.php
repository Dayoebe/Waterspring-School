<?php

namespace App\Models;

use App\Traits\InSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionDocument extends Model
{
    use InSchool;

    protected $fillable = [
        'admission_registration_id', 'school_id', 'category', 'original_name', 'path', 'disk',
        'mime_type', 'size_bytes', 'uploaded_by',
    ];

    public function admissionRegistration(): BelongsTo
    {
        return $this->belongsTo(AdmissionRegistration::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
