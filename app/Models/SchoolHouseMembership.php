<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolHouseMembership extends Model
{
    protected $fillable = ['school_house_id', 'student_id', 'academic_year_id', 'joined_on', 'left_on', 'assigned_by'];

    protected $casts = ['joined_on' => 'date', 'left_on' => 'date'];

    public function house()
    {
        return $this->belongsTo(SchoolHouse::class, 'school_house_id');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id')->withTrashed();
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
