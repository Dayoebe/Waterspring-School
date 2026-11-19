<?php

return [
    'timezone' => env('BIRTHDAY_TIMEZONE', 'Africa/Lagos'),
    'manual_limit_per_hour' => (int) env('BIRTHDAY_WISHES_PER_HOUR', 5),
    'message_max_length' => 1200,
    'automated_message' => "Today, we celebrate you!\n\nWishing you a wonderful birthday filled with joy, happiness, growth, excellence and beautiful memories. May this new chapter bring you countless opportunities and achievements.\n\nHappy Birthday!",
    'templates' => [
        "Happy birthday! Wishing you a wonderful year filled with happiness, wisdom, success and God's blessings.",
        'Happy birthday! May this new chapter bring you greater achievements, joy and beautiful memories.',
        'Wishing you a happy birthday and many more years of growth, excellence and success.',
        'Happy birthday! May your day be filled with laughter, love and unforgettable moments.',
    ],
];
