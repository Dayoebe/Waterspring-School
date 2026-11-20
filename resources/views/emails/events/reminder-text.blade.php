Event reminder: {{ $event->title }}

Dear {{ $guardian->name }},

{{ $student->name }} has an upcoming school event.
When: {{ $event->starts_at->format('D, M j, Y · g:i A') }}
@if($event->location)Where: {{ $event->location }}@endif

Sign in to the school portal to review or update the participation and consent response.
