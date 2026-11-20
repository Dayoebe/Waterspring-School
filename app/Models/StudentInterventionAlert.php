<?php

namespace App\Models;

use App\Traits\InSchool;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class StudentInterventionAlert extends Model
{
    use InSchool;

    protected $fillable = ['school_id', 'student_id', 'intervention_rule_id', 'severity', 'observed_value', 'data_complete', 'evidence', 'missing_data', 'status', 'fingerprint', 'detected_at', 'reviewed_by', 'reviewed_at', 'review_note', 'dismissal_reason', 'resolved_at'];

    protected $casts = ['observed_value' => 'decimal:2', 'data_complete' => 'boolean', 'evidence' => 'array', 'missing_data' => 'array', 'detected_at' => 'datetime', 'reviewed_at' => 'datetime', 'resolved_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(function (self $alert): void {
            if ($alert->isDirty(['school_id', 'student_id', 'intervention_rule_id', 'observed_value', 'data_complete', 'evidence', 'missing_data', 'fingerprint', 'detected_at'])) {
                throw new LogicException('Detected alert evidence is immutable. Re-evaluate the rule to create a new alert.');
            }
        });
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id')->withTrashed();
    }

    public function rule()
    {
        return $this->belongsTo(InterventionRule::class, 'intervention_rule_id');
    }

    public function followUps()
    {
        return $this->hasMany(InterventionFollowUp::class);
    }

    public function updates()
    {
        return $this->hasMany(InterventionAlertUpdate::class);
    }
}
