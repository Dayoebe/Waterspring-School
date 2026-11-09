<x-mail::message>
# Parent-teacher appointment

Hello {{ $recipient->name }},

@if($notificationType === 'requested')
A meeting has been requested for **{{ $appointment->student->name }}** and is awaiting the teacher’s response.
@elseif($notificationType === 'confirmed')
The meeting for **{{ $appointment->student->name }}** has been confirmed.
@elseif($notificationType === 'declined')
The requested meeting for **{{ $appointment->student->name }}** could not be confirmed. Please choose another available time.
@elseif($notificationType === 'cancelled')
The meeting for **{{ $appointment->student->name }}** has been cancelled.
@elseif($notificationType === 'completed')
The meeting for **{{ $appointment->student->name }}** has been marked complete. Sign in to review any follow-up actions shared by the school.
@else
This is a reminder that the meeting for **{{ $appointment->student->name }}** is approaching.
@endif

<x-mail::panel>
**Date:** {{ $appointment->slot->starts_at->format('M d, Y') }}

**Time:** {{ $appointment->slot->starts_at->format('h:i A') }} – {{ $appointment->slot->ends_at->format('h:i A') }}

**Teacher:** {{ $appointment->teacher->name }}

**Parent:** {{ $appointment->parent->name }}

**Mode:** {{ str($appointment->slot->mode)->replace('_', ' ')->title() }}

**Location:** {{ $appointment->slot->location ?: 'To be confirmed' }}
</x-mail::panel>

<x-mail::button :url="route('appointments.index')">
Open Appointment Workspace
</x-mail::button>

Regards,

{{ $appointment->teacher->school?->name ?: config('app.name') }}
</x-mail::message>
