<?php

namespace App\Models;

use App\Traits\InSchool;
use Illuminate\Database\Eloquent\Model;

class StudentPortfolioSetting extends Model
{
    use InSchool;

    protected $fillable = ['school_id', 'per_student_quota_bytes', 'per_file_max_bytes', 'updated_by'];

    protected $casts = ['per_student_quota_bytes' => 'integer', 'per_file_max_bytes' => 'integer'];
}
