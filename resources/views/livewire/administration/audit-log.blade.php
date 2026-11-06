<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.18em] text-red-700">Accountability</p>
                <h1 class="mt-1 text-2xl font-black text-slate-950">Audit history</h1>
                <p class="mt-1 text-sm text-slate-600">Append-only evidence for sensitive academic, financial, access, support, and AI actions.</p>
            </div>
            <button wire:click="clearFilters" class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-200">Clear filters</button>
        </div>

        <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
            <input wire:model.live.debounce.300ms="search" placeholder="Search event, person or record" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm xl:col-span-2">
            <select wire:model.live="category" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
                <option value="all">All categories</option>
                @foreach($categories as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <select wire:model.live="actorId" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
                <option value="">All actors</option>
                @foreach($actors as $actor)
                    <option value="{{ $actor->actor_id }}">{{ $actor->actor_name }}</option>
                @endforeach
            </select>
            <div class="grid grid-cols-2 gap-2">
                <input wire:model.live="dateFrom" type="date" title="From date" class="min-w-0 rounded-xl border border-slate-300 px-2 py-2.5 text-xs">
                <input wire:model.live="dateTo" type="date" title="To date" class="min-w-0 rounded-xl border border-slate-300 px-2 py-2.5 text-xs">
            </div>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-black uppercase tracking-wider text-slate-500">When</th>
                            <th class="px-4 py-3 text-left text-xs font-black uppercase tracking-wider text-slate-500">Actor</th>
                            <th class="px-4 py-3 text-left text-xs font-black uppercase tracking-wider text-slate-500">Event</th>
                            <th class="px-4 py-3 text-left text-xs font-black uppercase tracking-wider text-slate-500">Record</th>
                            <th class="px-4 py-3"><span class="sr-only">Details</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($events as $event)
                            <tr class="align-top {{ $selectedEventId === $event->id ? 'bg-red-50/60' : '' }}">
                                <td class="whitespace-nowrap px-4 py-3 text-xs text-slate-600">{{ $event->occurred_at->format('M d, Y') }}<br>{{ $event->occurred_at->format('h:i:s A') }}</td>
                                <td class="px-4 py-3"><p class="text-sm font-bold text-slate-800">{{ $event->actor_name ?: 'System' }}</p><p class="text-xs text-slate-500">{{ $event->ip_address ?: 'No request IP' }}</p></td>
                                <td class="px-4 py-3"><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-bold text-slate-700">{{ $categories[$event->category] ?? str($event->category)->title() }}</span><p class="mt-2 max-w-lg text-sm text-slate-700">{{ $event->description }}</p></td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $event->subject_label ?: 'System record' }}<p class="text-xs text-slate-400">#{{ $event->subject_id ?: '—' }}</p></td>
                                <td class="px-4 py-3"><button wire:click="selectEvent({{ $event->id }})" class="rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-bold text-white">Inspect</button></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-12 text-center text-sm text-slate-500">No audit events match these filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3">{{ $events->links() }}</div>
        </div>

        <aside class="h-fit rounded-2xl border border-slate-200 bg-white p-5 shadow-sm xl:sticky xl:top-5">
            @if($selectedEvent)
                <div class="flex items-start justify-between gap-3">
                    <div><p class="text-xs font-black uppercase tracking-wider text-slate-500">Event details</p><h2 class="mt-1 text-lg font-black text-slate-900">{{ str($selectedEvent->event)->replace('.', ' ')->title() }}</h2></div>
                    <button wire:click="$set('selectedEventId', null)" class="text-slate-400 hover:text-slate-700" aria-label="Close details">×</button>
                </div>
                <dl class="mt-4 space-y-3 text-sm">
                    <div><dt class="font-bold text-slate-500">Actor</dt><dd class="text-slate-800">{{ $selectedEvent->actor_name ?: 'System' }}</dd></div>
                    <div><dt class="font-bold text-slate-500">Occurred</dt><dd class="text-slate-800">{{ $selectedEvent->occurred_at->format('M d, Y h:i:s A') }}</dd></div>
                    <div><dt class="font-bold text-slate-500">Description</dt><dd class="text-slate-800">{{ $selectedEvent->description }}</dd></div>
                </dl>
                @foreach(['old_values' => 'Before', 'new_values' => 'After', 'metadata' => 'Context'] as $field => $label)
                    @if($selectedEvent->{$field})
                        <div class="mt-4"><p class="mb-1 text-xs font-black uppercase tracking-wider text-slate-500">{{ $label }}</p><pre class="max-h-72 overflow-auto rounded-xl bg-slate-950 p-3 text-xs text-slate-100">{{ json_encode($selectedEvent->{$field}, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></div>
                    @endif
                @endforeach
            @else
                <p class="text-sm text-slate-500">Select an event to inspect its before-and-after values and request context.</p>
            @endif
        </aside>
    </div>
</div>
