<?php

namespace App\Models;

use App\Traits\InSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AdmissionInternalNote extends Model
{
    use InSchool;

    public const UPDATED_AT = null;

    protected $fillable = ['admission_registration_id', 'school_id', 'note', 'author_id', 'author_name'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Admission notes are append-only.'));
        static::deleting(fn () => throw new LogicException('Admission notes cannot be deleted.'));
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
