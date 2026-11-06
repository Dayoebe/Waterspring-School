<?php

namespace App\Models;

use App\Traits\InSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AdmissionRegistration extends Model
{
    use HasFactory;
    use InSchool;

    protected $fillable = [
        'school_id',
        'my_class_id',
        'section_id',
        'reference_no',
        'student_name',
        'student_email',
        'gender',
        'birthday',
        'guardian_name',
        'guardian_phone',
        'guardian_email',
        'guardian_relationship',
        'address',
        'previous_school',
        'notes',
        'admin_notes',
        'document_path',
        'document_name',
        'status',
        'processed_by',
        'processed_at',
        'offered_at',
        'offered_by',
        'offer_expires_at',
        'acceptance_token_hash',
        'accepted_at',
        'accepted_by_name',
        'accepted_ip',
        'enrolled_user_id',
        'enrolled_student_record_id',
        'enrolled_parent_id',
        'enrolled_at',
    ];

    protected $casts = [
        'birthday' => 'date:Y-m-d',
        'processed_at' => 'datetime',
        'offered_at' => 'datetime',
        'offer_expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'enrolled_at' => 'datetime',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function myClass(): BelongsTo
    {
        return $this->belongsTo(MyClass::class, 'my_class_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function enrolledUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enrolled_user_id');
    }

    public function enrolledStudentRecord(): BelongsTo
    {
        return $this->belongsTo(StudentRecord::class, 'enrolled_student_record_id');
    }

    public function enrolledParent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enrolled_parent_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(AdmissionDocument::class)->latest('id');
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(AdmissionInterview::class)->latest('scheduled_at');
    }

    public function internalNotes(): HasMany
    {
        return $this->hasMany(AdmissionInternalNote::class)->latest('id');
    }

    public function feeConfirmation(): HasOne
    {
        return $this->hasOne(AdmissionFeeConfirmation::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(AdmissionStatusHistory::class)->latest('changed_at');
    }

    public function notificationEmails(): array
    {
        return collect([$this->guardian_email, $this->student_email])
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }
}
