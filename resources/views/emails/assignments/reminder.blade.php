@component('mail::message')
# {{ $reminderType === 'overdue_alert' ? 'Assignment overdue' : 'Assignment due soon' }}

Hello {{ $recipient->name }},

@if ($audience === 'parent')
{{ $student->name }} has not yet submitted the assignment below.
@else
You have not yet submitted the assignment below.
@endif

@component('mail::panel')
Assignment: {{ $assignment->title }}

Class: {{ $assignment->myClass?->name ?? 'Not specified' }}

Subject: {{ $assignment->subject?->name ?? 'Class-wide assignment' }}

Due: {{ $assignment->due_at?->format('D, d M Y \a\t g:i A') }}
@endcomponent

@if ($reminderType === 'overdue_alert')
The deadline has passed. Please submit the work as soon as possible or contact the teacher if help is needed.
@else
Please complete and submit the work before the deadline.
@endif

@component('mail::button', ['url' => route('assignments.index')])
View Assignment
@endcomponent

Thanks,
{{ config('app.name') }}
@endcomponent
