<div>
    @if($visible)
        <section class="overflow-hidden rounded-3xl border border-amber-200 bg-white shadow-sm" aria-labelledby="dashboard-birthday-title">
            <div class="flex flex-col gap-4 bg-sky-950 px-5 py-5 text-white sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div class="flex items-center gap-4">
                    <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-300 text-xl text-sky-950" aria-hidden="true"><i class="fas fa-cake-candles"></i></span>
                    <div><p class="text-xs font-black uppercase tracking-[0.18em] text-amber-300">Community celebration</p><h2 id="dashboard-birthday-title" class="mt-1 text-xl font-black">@if(count($panel['today'] ?? [])) {{ count($panel['today']) }} {{ Str::plural('birthday', count($panel['today'])) }} today @else The next birthday @endif</h2></div>
                </div>
                <a href="{{ route('birthdays.index') }}" wire:navigate class="rounded-xl border border-white/25 px-4 py-2 text-sm font-bold text-white hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-amber-300">View all birthdays</a>
            </div>

            @if(count($panel['today'] ?? []))
                <div class="flex snap-x gap-4 overflow-x-auto p-5 sm:p-6">
                    @foreach($panel['today'] as $celebrant)
                        <article class="flex min-w-[280px] snap-start items-center gap-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 sm:min-w-[340px]">
                            <img src="{{ $celebrant['photo_url'] }}" alt="{{ $celebrant['name'] }}" class="h-20 w-20 shrink-0 rounded-2xl object-cover">
                            <div class="min-w-0"><p class="text-xs font-black uppercase tracking-wide text-amber-700">Happy birthday!</p><h3 class="truncate text-lg font-black text-slate-950">{{ $celebrant['name'] }}</h3><p class="truncate text-sm text-slate-600">{{ $celebrant['context'] }}</p><p class="mt-1 text-xs text-slate-500">May your new year be filled with joy and excellence.</p>@if($canSend)<a href="{{ route('birthdays.index', ['wish' => $celebrant['id']]) }}" wire:navigate class="mt-2 inline-flex text-sm font-black text-sky-800 hover:text-sky-950">Send birthday wishes</a>@endif</div>
                        </article>
                    @endforeach
                </div>
            @elseif(!empty($panel['next']))
                @php($celebrant = $panel['next'])
                <div class="grid gap-5 p-5 sm:p-6 lg:grid-cols-[1fr,0.9fr]">
                    <article class="flex items-center gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-4"><img src="{{ $celebrant['photo_url'] }}" alt="{{ $celebrant['name'] }}" class="h-20 w-20 rounded-2xl object-cover"><div class="min-w-0"><p class="text-xs font-black uppercase tracking-wide text-sky-700">Coming up · {{ $celebrant['relative_label'] }}</p><h3 class="truncate text-xl font-black text-slate-950">{{ $celebrant['name'] }}</h3><p class="text-sm text-slate-600">{{ $celebrant['context'] }}</p><p class="mt-1 font-bold text-amber-700">{{ $celebrant['birthday_label'] }}</p></div></article>
                    <div class="rounded-2xl border border-dashed border-amber-300 bg-amber-50/60 p-5"><h3 class="font-black text-slate-950">A celebration is on the way</h3><p class="mt-2 text-sm leading-6 text-slate-600">Visit the birthday directory to see the next seven days and prepare a personal greeting.</p><a href="{{ route('birthdays.index') }}" wire:navigate class="mt-3 inline-flex text-sm font-black text-sky-800">Open birthday directory <i class="fas fa-arrow-right ml-2 mt-1" aria-hidden="true"></i></a></div>
                </div>
            @else
                <div class="p-5 sm:p-6"><div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center"><h3 class="font-black text-slate-900">No upcoming birthdays are available</h3><p class="mt-1 text-sm text-slate-500">Birthdays will appear when eligible community records include a date of birth.</p></div></div>
            @endif

            @if(count($panel['upcoming'] ?? []))
                <div class="border-t border-slate-200 px-5 py-4 sm:px-6"><p class="mb-3 text-xs font-black uppercase tracking-wide text-slate-500">Next 7 days</p><div class="flex flex-wrap gap-2">@foreach($panel['upcoming'] as $celebrant)<span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-bold text-slate-700"><span class="text-sky-800">{{ $celebrant['relative_label'] }}</span> · {{ $celebrant['name'] }}</span>@endforeach</div></div>
            @endif
        </section>
    @endif
</div>
