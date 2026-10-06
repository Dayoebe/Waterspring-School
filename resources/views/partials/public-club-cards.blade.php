@php
    $clubColours = [
        'sky' => ['bg-sky-50', 'text-sky-800', 'bg-sky-500'],
        'violet' => ['bg-violet-50', 'text-violet-800', 'bg-violet-500'],
        'emerald' => ['bg-emerald-50', 'text-emerald-800', 'bg-emerald-500'],
        'amber' => ['bg-amber-50', 'text-amber-900', 'bg-amber-500'],
        'rose' => ['bg-rose-50', 'text-rose-800', 'bg-rose-500'],
        'indigo' => ['bg-indigo-50', 'text-indigo-800', 'bg-indigo-500'],
    ];
@endphp

<div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
    @forelse ($clubs as $club)
        @php($palette = $clubColours[$club->colour] ?? $clubColours['sky'])
        <article class="group relative overflow-hidden rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-xl">
            <span class="absolute inset-x-0 top-0 h-1.5 {{ $palette[2] }}"></span>
            <div class="flex items-start justify-between gap-4">
                <span class="flex h-12 w-12 items-center justify-center rounded-2xl {{ $palette[0] }} {{ $palette[1] }}">
                    <i class="fas fa-people-group text-lg" aria-hidden="true"></i>
                </span>
                @if ($club->category)
                    <span class="rounded-full {{ $palette[0] }} px-3 py-1 text-xs font-bold {{ $palette[1] }}">{{ $club->category }}</span>
                @endif
            </div>
            <h3 class="mt-5 text-xl font-black text-slate-950">{{ $club->name }}</h3>
            <p class="mt-3 line-clamp-4 leading-7 text-slate-600">{{ $club->description }}</p>

            @if ($club->meeting_day || $club->meeting_time || $club->meeting_location)
                <dl class="mt-5 space-y-2 border-t border-slate-100 pt-4 text-sm text-slate-600">
                    @if ($club->meeting_day || $club->meeting_time)
                        <div class="flex gap-3"><dt class="w-5 text-sky-700"><i class="far fa-calendar" aria-hidden="true"></i><span class="sr-only">Meeting time</span></dt><dd>{{ $club->meeting_day }}@if($club->meeting_day && $club->meeting_time), @endif{{ $club->meeting_time ? date('g:i A', strtotime($club->meeting_time)) : '' }}</dd></div>
                    @endif
                    @if ($club->meeting_location)
                        <div class="flex gap-3"><dt class="w-5 text-sky-700"><i class="fas fa-location-dot" aria-hidden="true"></i><span class="sr-only">Location</span></dt><dd>{{ $club->meeting_location }}</dd></div>
                    @endif
                </dl>
            @endif
        </article>
    @empty
        <div class="rounded-3xl border border-dashed border-sky-200 bg-sky-50 p-8 text-center sm:col-span-2 lg:col-span-3">
            <i class="fas fa-people-group text-3xl text-sky-500" aria-hidden="true"></i>
            <h3 class="mt-4 text-xl font-black text-slate-900">Club programme updates are coming soon</h3>
            <p class="mx-auto mt-2 max-w-xl leading-7 text-slate-600">Contact the school office for current extracurricular opportunities.</p>
        </div>
    @endforelse
</div>
