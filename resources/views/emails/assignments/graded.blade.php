@component('mail::message')
# Assignment graded

Hello {{ $recipient->name }},

@if ($audience === 'parent')
{{ $student->name }}'s submission for **{{ $submission->assignment->title }}** has been graded.
@else
Your submission for **{{ $submission->assignment->title }}** has been graded.
@endif

@component('mail::panel')
Score: {{ $submission->score + 0 }}{{ $submission->assignment->max_score !== null ? ' / '.($submission->assignment->max_score + 0) : '' }}

Subject: {{ $submission->assignment->subject?->name ?? 'Class-wide assignment' }}

Graded: {{ $submission->graded_at?->format('D, d M Y \a\t g:i A') }}
@endcomponent

@if ($submission->feedback)
**Teacher feedback**

{{ $submission->feedback }}
@endif

@component('mail::button', ['url' => route('assignments.index')])
View Feedback
@endcomponent

Thanks,
{{ config('app.name') }}
@endcomponent
