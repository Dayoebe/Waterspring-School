<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class InterventionAlertUpdate extends Model
{
    protected $fillable = ['student_intervention_alert_id', 'author_id', 'event', 'note'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Intervention history is immutable.'));
        static::deleting(fn () => throw new LogicException('Intervention history cannot be deleted.'));
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id')->withTrashed();
    }
}
