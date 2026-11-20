{{ $digest['school']['name'] }} — Parent weekly digest
{{ \Carbon\Carbon::parse($digest['period']['start'])->format('M j') }} to {{ \Carbon\Carbon::parse($digest['period']['end'])->format('M j, Y') }}

Hello {{ $digest['guardian']['name'] }},

@foreach($digest['children'] as $child)
{{ $child['name'] }}
@if($child['attendance'] !== null)Attendance: {{ $child['attendance']['recorded_days'] }} recorded; {{ $child['attendance']['present'] }} present, {{ $child['attendance']['late'] }} late, {{ $child['attendance']['absent'] }} absent, {{ $child['attendance']['excused'] }} excused.
@endif
@if($child['assignments'] !== null)Assignments:
@forelse($child['assignments'] as $assignment)- {{ $assignment['title'] }}: {{ str_replace('_', ' ', $assignment['status']) }}
@empty- No assignment deadlines recorded.
@endforelse @endif
@if($child['results'] !== null)Published result average: {{ $child['results']['average'] === null ? 'No approved results available' : $child['results']['average'].'%' }}
@endif
@if($child['achievements'] !== [])Achievements and activities:
@foreach($child['achievements'] as $achievement)- {{ $achievement['category'] }}: {{ $achievement['message'] }}
@endforeach @endif
@endforeach
Upcoming events:
@forelse($digest['events'] as $event)- {{ $event['title'] }} — {{ \Carbon\Carbon::parse($event['starts_at'])->format('D, M j · g:i A') }}
@empty- None scheduled.
@endforelse

This digest contains only information authorised for your verified guardian relationship.
