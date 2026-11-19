<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $school_id
 * @property bool $feature_enabled
 * @property bool $show_students
 * @property bool $show_staff
 * @property string $viewer_scope
 * @property bool $wishes_enabled
 * @property bool $automated_emails_enabled
 * @property bool $automated_student_emails
 * @property bool $automated_staff_emails
 * @property string $delivery_time
 * @property string $timezone
 * @property ?string $sender_name
 * @property ?string $sender_email
 * @property string $automated_subject
 * @property ?string $automated_message
 */
class BirthdaySetting extends Model
{
    protected $fillable = [
        'school_id', 'feature_enabled', 'show_students', 'show_staff', 'viewer_scope',
        'wishes_enabled', 'automated_emails_enabled', 'automated_student_emails',
        'automated_staff_emails', 'delivery_time', 'timezone', 'sender_name',
        'sender_email', 'automated_subject', 'automated_message',
        'public_visibility_enabled', 'greeting_wall_enabled',
    ];

    protected $casts = [
        'feature_enabled' => 'boolean',
        'show_students' => 'boolean',
        'show_staff' => 'boolean',
        'wishes_enabled' => 'boolean',
        'automated_emails_enabled' => 'boolean',
        'automated_student_emails' => 'boolean',
        'automated_staff_emails' => 'boolean',
        'public_visibility_enabled' => 'boolean',
        'greeting_wall_enabled' => 'boolean',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public static function defaultsFor(int $schoolId): self
    {
        return new self([
            'school_id' => $schoolId,
            'feature_enabled' => true,
            'show_students' => true,
            'show_staff' => true,
            'viewer_scope' => 'authenticated',
            'wishes_enabled' => true,
            'automated_emails_enabled' => false,
            'automated_student_emails' => false,
            'automated_staff_emails' => false,
            'delivery_time' => '08:00',
            'timezone' => config('birthdays.timezone'),
            'sender_name' => null,
            'sender_email' => null,
            'automated_subject' => 'Happy Birthday, {name}!',
            'automated_message' => config('birthdays.automated_message'),
            'public_visibility_enabled' => false,
            'greeting_wall_enabled' => false,
        ]);
    }
}
