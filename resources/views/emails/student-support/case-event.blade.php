@component('mail::message')
# Student support update

Hello {{ $recipient->name }},

@if($event === 'opened')
A support plan has been opened for **{{ $supportCase->student->name }}**.
@elseif($event === 'review_due')
The support plan for **{{ $supportCase->student->name }}** is due for review.
@elseif($event === 'completed')
The support plan for **{{ $supportCase->student->name }}** has been marked completed.
@else
There is a new update on the support plan for **{{ $supportCase->student->name }}**.
@endif

@component('mail::panel')
Plan: {{ $supportCase->title }}

Status: {{ ucwords(str_replace('_', ' ', $supportCase->status)) }}

Review date: {{ $supportCase->review_on->format('D, d M Y') }}
@endcomponent

For privacy, sensitive notes are not included in this email. Sign in to view the plan and respond.

@component('mail::button', ['url' => route('student-support.index')])
Open Student Support Centre
@endcomponent

Thanks,
{{ config('app.name') }}
@endcomponent
