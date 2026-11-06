<?php

namespace App\Models;

use App\Casts\Money;
use App\Traits\InSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AdmissionFeeConfirmation extends Model
{
    use InSchool;

    public const UPDATED_AT = null;

    protected $fillable = [
        'admission_registration_id', 'school_id', 'amount', 'currency', 'payment_method',
        'reference', 'notes', 'confirmed_by', 'confirmer_name', 'confirmed_at',
    ];

    protected $casts = ['amount' => Money::class, 'confirmed_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Admission fee confirmations are immutable.'));
        static::deleting(fn () => throw new LogicException('Admission fee confirmations cannot be deleted.'));
    }

    public function admissionRegistration(): BelongsTo
    {
        return $this->belongsTo(AdmissionRegistration::class);
    }
}
