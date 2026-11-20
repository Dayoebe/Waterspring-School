<?php

namespace App\Models;

use App\Traits\InSchool;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class HousePointTransaction extends Model
{
    use InSchool;

    protected $fillable = ['school_id', 'house_point_award_id', 'student_id', 'school_house_id', 'academic_year_id', 'semester_id', 'points', 'reason', 'occurred_on', 'recorded_by', 'recorded_at'];

    protected $casts = ['occurred_on' => 'date', 'recorded_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('House point ledger entries are immutable. Record a separate correction.'));
        static::deleting(fn () => throw new LogicException('House point ledger entries cannot be deleted.'));
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id')->withTrashed();
    }

    public function house()
    {
        return $this->belongsTo(SchoolHouse::class, 'school_house_id');
    }

    public function award()
    {
        return $this->belongsTo(HousePointAward::class, 'house_point_award_id');
    }
}
