<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentSupportUpdate extends Model
{
    use HasFactory;
    protected $fillable = ['student_support_case_id', 'author_id', 'progress', 'note', 'parent_visible'];
    protected $casts = ['parent_visible' => 'boolean'];
    public function supportCase() { return $this->belongsTo(StudentSupportCase::class, 'student_support_case_id'); }
    public function author() { return $this->belongsTo(User::class, 'author_id'); }
}
