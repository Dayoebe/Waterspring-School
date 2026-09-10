@component('mail::message')
# New assignment: {{ $assignment->title }}

Hello {{ $recipient->name }},

@if ($audience === 'parent')
A new assignment has been published for {{ count($studentNames) === 1 ? $studentNames[0] : implode(', ', $studentNames) }}.
@else
A new assignment has been published for you.
@endif

@component('mail::panel')
Class: {{ $assignment->myClass?->name ?? 'Not specified' }}

Subject: {{ $assignment->subject?->name ?? 'Class-wide assignment' }}

Set by: {{ $assignment->teacher?->name ?? 'School staff' }}

Due: {{ $assignment->due_at?->format('D, d M Y \a\t g:i A') }}
@endcomponent

{{ $assignment->instructions }}

@if ($assignment->attachment_path)
This assignment includes a supporting file. Sign in to the school portal to view it.
@endif

@component('mail::button', ['url' => route('assignments.index')])
View Assignment
@endcomponent

Thanks,
{{ config('app.name') }}
@endcomponent
