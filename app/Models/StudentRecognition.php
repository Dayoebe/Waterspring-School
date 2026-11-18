<?php

namespace App\Models;

use App\Services\CloudinaryImageStorage;
use App\Support\RecognitionCategories;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentRecognition extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id', 'student_id', 'my_class_id', 'academic_year_id', 'semester_id', 'category',
        'custom_category', 'reason', 'congratulatory_message', 'week_start_date',
        'week_end_date', 'photo_path', 'status', 'public_display_approved',
        'published_slot', 'custom_category_approved_at', 'custom_category_approved_by',
        'published_at', 'created_by', 'published_by',
    ];

    protected $casts = [
        'week_start_date' => 'date',
        'week_end_date' => 'date',
        'published_at' => 'datetime',
        'custom_category_approved_at' => 'datetime',
        'public_display_approved' => 'boolean',
    ];

    protected $appends = ['category_label', 'photo_url'];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id')->withTrashed();
    }

    public function myClass()
    {
        return $this->belongsTo(MyClass::class)->withTrashed();
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function publisher()
    {
        return $this->belongsTo(User::class, 'published_by')->withTrashed();
    }

    public function customCategoryApprover()
    {
        return $this->belongsTo(User::class, 'custom_category_approved_by')->withTrashed();
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')->whereNotNull('published_at');
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->published()->where('public_display_approved', true);
    }

    public function getCategoryLabelAttribute(): string
    {
        return RecognitionCategories::label($this->category, $this->custom_category);
    }

    public function getPhotoUrlAttribute(): string
    {
        return app(CloudinaryImageStorage::class)->url($this->photo_path)
            ?: ($this->student?->profile_photo_url ?? asset('images/default-avatar.png'));
    }
}
