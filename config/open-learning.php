<?php

return [
    'school_id' => env('OPEN_LEARNING_SCHOOL_ID'),
    'session_lifetime_minutes' => (int) env('OPEN_LEARNING_SESSION_LIFETIME', 180),
    'requests_per_minute' => (int) env('OPEN_LEARNING_REQUESTS_PER_MINUTE', 8),
    'requests_per_session' => (int) env('OPEN_LEARNING_REQUESTS_PER_SESSION', 60),
    'max_message_characters' => (int) env('OPEN_LEARNING_MAX_MESSAGE_CHARACTERS', 1200),
    'max_history_messages' => (int) env('OPEN_LEARNING_MAX_HISTORY_MESSAGES', 12),
    'assessment_questions' => [
        'easy' => 5,
        'intermediate' => 7,
        'challenging' => 8,
    ],
    'eligible_roles' => [
        'student', 'parent', 'teacher', 'principal', 'admin', 'super-admin', 'super_admin',
    ],
];
