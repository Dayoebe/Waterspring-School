<x-mail::message>
# Transport update

Hello {{ $recipient->name }},

@if($event->event_type === 'dropped_off')
The school has recorded that **{{ $event->student->name }}** was dropped off at the assigned stop.
@else
The school has recorded that **{{ $event->student->name }}** arrived safely.
@endif

<x-mail::panel>
**Time:** {{ $event->occurred_at->format('M d, Y h:i A') }}

**Route:** {{ $event->route?->name ?: 'School transport' }}

**Stop:** {{ $event->stop?->name ?: 'School' }}

**Recorded by:** {{ $event->recorder_name }}
</x-mail::panel>

<x-mail::button :url="route('transport.index')">
View Transport History
</x-mail::button>

Regards,

{{ config('app.name') }}
</x-mail::message>
