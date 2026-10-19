@if (($isStudent || $isParent) && $learningPanel !== [])
    <section class="mt-6 overflow-hidden rounded-xl border border-emerald-200 bg-white shadow-sm">
        <div class="border-b border-emerald-100 bg-emerald-50 p-6">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Today's timetable</p>
            <h3 class="mt-2 text-2xl font-bold text-slate-900">{{ $isParent ? 'Your wards’ activities today' : 'Your class activities today' }}</h3>
            <p class="mt-1 text-sm text-slate-600">Only activities scheduled for today are shown here.</p>
        </div>
        <div class="grid gap-4 p-6">
            @foreach ($learningPanel as $learning)
                <article class="rounded-2xl border border-slate-200 p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            @if ($isParent)
                                <p class="text-xs font-bold uppercase tracking-widest text-violet-700">{{ $learning['student'] }}</p>
                            @endif
                            <h4 class="{{ $isParent ? 'mt-1' : '' }} text-xl font-black text-slate-900">{{ $learning['class_name'] }}</h4>
                            <p class="mt-1 text-sm text-slate-500">{{ $learning['timetable_name'] ?: 'Timetable awaiting publication' }}</p>
                        </div>
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700"><i class="fas fa-school"></i></span>
                    </div>

                    @if (!empty($learning['today_periods']))
                        <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-xs font-black uppercase tracking-widest text-emerald-700">Today · {{ $learning['today_name'] }}</p>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                                @foreach ($learning['today_periods'] as $period)
                                    <div class="rounded-xl {{ $period['is_break'] ? 'bg-amber-100 text-amber-950' : 'bg-white text-slate-800' }} px-4 py-3 shadow-sm">
                                        <p class="text-[10px] font-bold uppercase tracking-wide opacity-70">{{ $period['time'] }} · {{ $period['label'] }}</p>
                                        <p class="mt-1 text-sm font-bold">{{ $period['item'] }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <p class="mt-5 rounded-xl bg-amber-50 p-3 text-sm font-medium text-amber-900">
                            {{ $learning['timetable_name'] ? 'There are no class activities scheduled for today.' : 'No timetable has been published for this class and term yet.' }}
                        </p>
                    @endif

                    @if ($learning['class_id'])
                        <div class="mt-5 flex flex-wrap gap-2">
                            <a href="{{ route('timetables.index', ['selectedClass' => $learning['class_id']]) }}" wire:navigate class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-800"><i class="fas fa-calendar-week mr-1"></i>Full timetable</a>
                            <a href="{{ route('syllabi.index', ['class' => $learning['class_id']]) }}" wire:navigate class="rounded-lg bg-sky-700 px-3 py-2 text-xs font-bold text-white hover:bg-sky-800"><i class="fas fa-book-open mr-1"></i>Syllabi</a>
                            <a href="{{ route('schemes.index') }}" wire:navigate class="rounded-lg bg-violet-700 px-3 py-2 text-xs font-bold text-white hover:bg-violet-800"><i class="fas fa-list-check mr-1"></i>Schemes</a>
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    </section>
@endif
