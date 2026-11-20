<?php

namespace App\Models;

use App\Traits\InSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParentDigestSetting extends Model
{
    use InSchool;

    protected $fillable = ['school_id', 'enabled', 'delivery_day', 'delivery_time', 'timezone', 'upcoming_days', 'updated_by'];

    protected $casts = ['enabled' => 'boolean', 'delivery_day' => 'integer', 'upcoming_days' => 'integer'];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public static function forSchool(int $schoolId): self
    {
        return static::query()->firstOrNew(['school_id' => $schoolId], [
            'enabled' => false,
            'delivery_day' => 1,
            'delivery_time' => '07:00',
            'timezone' => 'Africa/Lagos',
            'upcoming_days' => 7,
        ]);
    }
}
