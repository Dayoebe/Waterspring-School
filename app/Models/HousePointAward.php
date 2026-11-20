<?php

namespace App\Models;

use App\Traits\InSchool;
use Illuminate\Database\Eloquent\Model;

class HousePointAward extends Model
{
    use InSchool;

    protected $fillable = ['school_id', 'student_id', 'school_house_id', 'house_point_rule_id', 'student_activity_record_id', 'academic_year_id', 'semester_id', 'kind', 'points', 'reason', 'occurred_on', 'status', 'deduplication_key', 'requested_by', 'reviewed_by', 'review_note', 'reviewed_at'];

    protected $casts = ['occurred_on' => 'date', 'reviewed_at' => 'datetime'];

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id')->withTrashed();
    }

    public function house()
    {
        return $this->belongsTo(SchoolHouse::class, 'school_house_id');
    }

    public function rule()
    {
        return $this->belongsTo(HousePointRule::class, 'house_point_rule_id');
    }

    public function transaction()
    {
        return $this->hasOne(HousePointTransaction::class);
    }
}
