@component('mail::message')
# Assignment submitted

Hello {{ $submission->assignment->teacher?->name ?? 'Teacher' }},

{{ $submission->studentRecord?->user?->name ?? 'A student' }} submitted **{{ $submission->assignment->title }}**.

@component('mail::panel')
Class: {{ $submission->assignment->myClass?->name ?? 'Not specified' }}

Subject: {{ $submission->assignment->subject?->name ?? 'Class-wide assignment' }}

Submitted: {{ $submission->submitted_at?->format('D, d M Y \a\t g:i A') }}

Status: {{ str($submission->status)->replace('_', ' ')->title() }}
@endcomponent

@component('mail::button', ['url' => route('assignments.index')])
Review Submission
@endcomponent

Thanks,
{{ config('app.name') }}
@endcomponent
