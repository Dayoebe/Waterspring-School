<div class="space-y-7">
    @if (session()->has('birthday_success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-800" role="status">
            <i class="fas fa-circle-check mr-2" aria-hidden="true"></i>{{ session('birthday_success') }}
        </div>
    @endif

    <section class="overflow-hidden rounded-3xl border border-amber-200 bg-white shadow-sm">
        <div class="relative overflow-hidden bg-sky-950 px-6 py-7 text-white sm:px-8">
            <div class="pointer-events-none absolute -right-8 -top-10 h-40 w-40 rounded-full border-[22px] border-amber-300/10" aria-hidden="true"></div>
            <div class="relative flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.22em] text-amber-300">Water Spring celebrations</p>
                    <h1 class="mt-2 text-3xl font-black sm:text-4xl">Birthdays worth celebrating</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-sky-100">Only day and month are shown. Contact details and birth years remain private.</p>
                </div>
                <div class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3 text-sm backdrop-blur-sm">
                    <strong class="block text-2xl text-amber-300">{{ $today->count() }}</strong>
                    {{ Str::plural('celebrant', $today->count()) }} today
                </div>
            </div>
        </div>

        <div class="p-5 sm:p-7">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div><p class="text-xs font-bold uppercase tracking-[0.16em] text-amber-700">Today</p><h2 class="mt-1 text-xl font-black text-slate-950">Today’s celebrants</h2></div>
            </div>
            @if ($today->isNotEmpty())
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($today as $celebrant)
                        <article class="flex items-center gap-4 rounded-2xl border border-amber-200 bg-amber-50/60 p-4">
                            <img src="{{ $celebrant['photo_url'] }}" alt="{{ $celebrant['name'] }}" class="h-20 w-20 shrink-0 rounded-2xl object-cover" loading="lazy">
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-bold uppercase tracking-wide text-amber-700">Happy birthday!</p>
                                <h3 class="truncate text-lg font-black text-slate-950">{{ $celebrant['name'] }}</h3>
                                <p class="truncate text-sm text-slate-600">{{ $celebrant['context'] }}</p>
                                @if ($settings->wishes_enabled && auth()->user()->can('send birthday wishes'))
                                    <button type="button" wire:click="openWish({{ $celebrant['id'] }})" class="mt-2 text-sm font-bold text-sky-800 hover:text-sky-950 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2">Send birthday wishes</button>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                @php($nextCelebrant = $directory->getCollection()->first())
                <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center">
                    <i class="fas fa-calendar-day text-2xl text-sky-700" aria-hidden="true"></i>
                    <h3 class="mt-2 font-black text-slate-900">No birthday falls today</h3>
                    <p class="mt-1 text-sm text-slate-600">The next celebrations are waiting just below.</p>
                </div>
            @endif
        </div>
    </section>

    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div><p class="text-xs font-bold uppercase tracking-[0.16em] text-sky-700">Coming up</p><h2 class="mt-1 text-2xl font-black text-slate-950">Upcoming birthdays</h2></div>
            <div class="flex flex-wrap gap-2" aria-label="Upcoming birthday range">
                @foreach (['7' => 'Next 7 days', '14' => 'Next 14 days', '30' => 'Next 30 days', 'month' => 'This month', 'all' => 'All upcoming'] as $value => $label)
                    <button type="button" wire:click="$set('range', '{{ $value }}')" class="rounded-xl px-3 py-2 text-xs font-bold {{ $range === $value ? 'bg-sky-800 text-white' : 'border border-slate-300 text-slate-700 hover:bg-slate-50' }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>
        @if ($upcoming->isNotEmpty())
            <div class="mt-5 flex gap-3 overflow-x-auto pb-2 snap-x">
                @foreach ($upcoming as $celebrant)
                    <article class="min-w-[260px] snap-start rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="flex items-center gap-3"><img src="{{ $celebrant['photo_url'] }}" alt="" class="h-14 w-14 rounded-xl object-cover" loading="lazy"><div class="min-w-0"><h3 class="truncate font-black text-slate-900">{{ $celebrant['name'] }}</h3><p class="truncate text-xs text-slate-500">{{ $celebrant['context'] }}</p></div></div>
                        <div class="mt-4 flex items-center justify-between gap-2"><span class="text-sm font-bold text-sky-900">{{ $celebrant['birthday_label'] }}</span><span class="rounded-full bg-white px-2.5 py-1 text-xs font-bold text-amber-700">{{ $celebrant['relative_label'] }}</span></div>
                    </article>
                @endforeach
            </div>
        @else
            <p class="mt-5 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-5 text-center text-sm text-slate-600">No birthdays match this date range.</p>
        @endif
    </section>

    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 p-5 sm:p-7">
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-sky-700">School community</p>
            <h2 class="mt-1 text-2xl font-black text-slate-950">Birthday directory</h2>
            <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                <label class="xl:col-span-2"><span class="sr-only">Search by name</span><input type="search" wire:model.live.debounce.300ms="search" placeholder="Search by name" class="w-full rounded-xl border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500"></label>
                <label><span class="sr-only">Person type</span><select wire:model.live="type" class="w-full rounded-xl border-slate-300 text-sm"><option value="all">Students and staff</option><option value="student">Students</option><option value="staff">Staff</option></select></label>
                <label><span class="sr-only">Class</span><select wire:model.live="classFilter" class="w-full rounded-xl border-slate-300 text-sm"><option value="all">All classes</option>@foreach($classes as $class)<option value="{{ $class }}">{{ $class }}</option>@endforeach</select></label>
                <label><span class="sr-only">Birthday month</span><select wire:model.live="month" class="w-full rounded-xl border-slate-300 text-sm"><option value="all">All months</option>@for($m=1;$m<=12;$m++)<option value="{{ $m }}">{{ now()->month($m)->format('F') }}</option>@endfor</select></label>
            </div>
        </div>
        @if ($directory->isNotEmpty())
            <div class="grid gap-4 p-5 sm:grid-cols-2 xl:grid-cols-3 sm:p-7">
                @foreach ($directory as $celebrant)
                    <article class="flex items-start gap-4 rounded-2xl border border-slate-200 p-4 hover:border-sky-300 hover:shadow-sm">
                        <img src="{{ $celebrant['photo_url'] }}" alt="{{ $celebrant['name'] }}" class="h-16 w-16 shrink-0 rounded-2xl object-cover" loading="lazy">
                        <div class="min-w-0 flex-1"><h3 class="truncate font-black text-slate-950">{{ $celebrant['name'] }}</h3><p class="truncate text-sm text-slate-500">{{ $celebrant['context'] }}</p><p class="mt-2 text-xs font-bold text-sky-800"><i class="fas fa-cake-candles mr-1" aria-hidden="true"></i>{{ $celebrant['birthday_label'] }} · {{ $celebrant['relative_label'] }}</p>@if ($settings->wishes_enabled && auth()->user()->can('send birthday wishes'))<button wire:click="openWish({{ $celebrant['id'] }})" type="button" class="mt-2 text-xs font-black text-amber-700 hover:text-amber-900">Send wishes</button>@endif</div>
                    </article>
                @endforeach
            </div>
            <div class="border-t border-slate-200 px-5 py-4 sm:px-7">{{ $directory->links() }}</div>
        @else
            <div class="p-8 text-center"><i class="fas fa-magnifying-glass text-2xl text-slate-400"></i><h3 class="mt-2 font-black text-slate-900">No matching birthdays</h3><p class="mt-1 text-sm text-slate-500">Try a broader search or filter.</p></div>
        @endif
    </section>

    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between gap-3 border-b border-slate-200 p-5 sm:p-7">
            <div><p class="text-xs font-bold uppercase tracking-[0.16em] text-sky-700">Month view</p><h2 class="mt-1 text-2xl font-black text-slate-950">{{ $calendar['label'] }}</h2></div>
            <div class="flex gap-2"><button wire:click="previousCalendarMonth" class="rounded-xl border border-slate-300 px-3 py-2" aria-label="Previous month"><i class="fas fa-chevron-left"></i></button><button wire:click="nextCalendarMonth" class="rounded-xl border border-slate-300 px-3 py-2" aria-label="Next month"><i class="fas fa-chevron-right"></i></button></div>
        </div>
        <div class="overflow-x-auto p-4 sm:p-6"><div class="min-w-[720px]"><div class="grid grid-cols-7 text-center text-xs font-bold uppercase tracking-wide text-slate-500">@foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $weekday)<div class="py-2">{{ $weekday }}</div>@endforeach</div><div class="grid grid-cols-7 border-l border-t border-slate-200">@for($blank=0;$blank<$calendar['leading'];$blank++)<div class="min-h-24 border-b border-r border-slate-200 bg-slate-50"></div>@endfor @foreach($calendar['days'] as $day)<button type="button" wire:click="selectCalendarDay({{ $day['day'] }})" class="min-h-24 border-b border-r border-slate-200 p-2 text-left hover:bg-sky-50 focus:z-10 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-sky-500"><span class="text-sm font-bold text-slate-700">{{ $day['day'] }}</span>@if($day['celebrants']->isNotEmpty())<span class="mt-2 block rounded-lg bg-amber-100 px-2 py-1 text-xs font-bold text-amber-900">{{ $day['celebrants']->count() }} {{ Str::plural('birthday', $day['celebrants']->count()) }}</span><span class="mt-1 block truncate text-[11px] text-slate-600">{{ $day['celebrants']->pluck('name')->join(', ') }}</span>@endif</button>@endforeach</div></div></div>
        @if($selectedCalendarDay)
            <div class="border-t border-slate-200 bg-slate-50 p-5"><h3 class="font-black text-slate-900">{{ $calendar['label'] }} {{ $selectedCalendarDay }}</h3>@if($selectedCalendarCelebrants->isEmpty())<p class="mt-1 text-sm text-slate-500">No birthdays on this date.</p>@else<div class="mt-3 flex flex-wrap gap-2">@foreach($selectedCalendarCelebrants as $celebrant)<button wire:click="openWish({{ $celebrant['id'] }})" class="rounded-full border border-amber-200 bg-white px-3 py-2 text-sm font-bold text-slate-800">{{ $celebrant['name'] }}</button>@endforeach</div>@endif</div>
        @endif
    </section>

    @if ($wishModalOpen)
        <div class="fixed inset-0 z-[70] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="birthday-wish-title" x-data x-init="$nextTick(() => $refs.subject.focus())" @keydown.escape.window="$wire.closeWish()">
            <button type="button" wire:click="closeWish" class="absolute inset-0 bg-slate-950/70" aria-label="Close birthday wish dialog"></button>
            <div class="relative z-10 max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-3xl bg-white shadow-2xl">
                <div class="flex items-start justify-between gap-4 border-b border-slate-200 bg-amber-50 p-6"><div><p class="text-xs font-black uppercase tracking-[0.16em] text-amber-700">A private email greeting</p><h2 id="birthday-wish-title" class="mt-1 text-2xl font-black text-slate-950">Wish {{ $selectedCelebrantName }} a happy birthday</h2><p class="mt-1 text-sm text-slate-600">Sent as {{ auth()->user()->name }}. The recipient address remains private.</p></div><button wire:click="closeWish" class="rounded-full p-2 text-slate-500 hover:bg-white" aria-label="Close"><i class="fas fa-times"></i></button></div>
                <form wire:submit="sendWish" class="space-y-5 p-6">
                    <div><label class="mb-1.5 block text-sm font-bold text-slate-700">Greeting ideas</label><div class="grid gap-2 sm:grid-cols-2">@foreach(config('birthdays.templates') as $index => $template)<button type="button" wire:click="useTemplate({{ $index }})" class="rounded-xl border border-slate-200 p-3 text-left text-xs leading-5 text-slate-600 hover:border-sky-300 hover:bg-sky-50">{{ Str::limit($template, 86) }}</button>@endforeach</div></div>
                    <div><label for="birthday-subject" class="mb-1.5 block text-sm font-bold text-slate-700">Subject</label><input id="birthday-subject" x-ref="subject" wire:model="subject" maxlength="160" class="w-full rounded-xl border-slate-300 focus:border-sky-500 focus:ring-sky-500">@error('subject')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                    <div><div class="flex justify-between"><label for="birthday-message" class="mb-1.5 block text-sm font-bold text-slate-700">Message</label><span class="text-xs text-slate-400">Up to {{ config('birthdays.message_max_length') }} characters</span></div><textarea id="birthday-message" wire:model="message" rows="7" maxlength="{{ config('birthdays.message_max_length') }}" class="w-full rounded-xl border-slate-300 focus:border-sky-500 focus:ring-sky-500"></textarea>@error('message')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                    <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end"><button type="button" wire:click="closeWish" class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-bold text-slate-700">Cancel</button><button type="submit" wire:loading.attr="disabled" wire:target="sendWish" class="rounded-xl bg-sky-800 px-5 py-2.5 text-sm font-black text-white hover:bg-sky-900 disabled:opacity-60"><span wire:loading.remove wire:target="sendWish">Send birthday wishes</span><span wire:loading wire:target="sendWish">Queuing securely…</span></button></div>
                </form>
            </div>
        </div>
    @endif
</div>
