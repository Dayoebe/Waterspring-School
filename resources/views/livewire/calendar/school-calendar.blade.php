<div class="space-y-6">
    @if (session()->has('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>
    @endif

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-4 border-b border-slate-200 p-5 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-sky-700">Academic planning</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-950">{{ $monthLabel }}</h1>
                <p class="mt-1 text-sm text-slate-500">School events and assignment deadlines in one place.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button wire:click="previousMonth" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" aria-label="Previous month"><i class="fas fa-chevron-left"></i></button>
                <button wire:click="today" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Today</button>
                <button wire:click="nextMonth" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" aria-label="Next month"><i class="fas fa-chevron-right"></i></button>
                @if ($canManage)
                    <button wire:click="createEvent" class="rounded-xl bg-sky-700 px-4 py-2 text-sm font-bold text-white hover:bg-sky-800"><i class="fas fa-plus mr-2"></i>Add event</button>
                @endif
            </div>
        </div>

        <div class="grid gap-3 border-b border-slate-200 bg-slate-50 p-4 md:grid-cols-2">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search events, places, or details" class="rounded-xl border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
            <select wire:model.live="categoryFilter" class="rounded-xl border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                <option value="all">All event types</option>
                <option value="event">General events</option>
                <option value="academic">Academic</option>
                <option value="deadline">Deadlines</option>
                <option value="holiday">Holidays</option>
                <option value="meeting">Meetings</option>
                <option value="activity">Activities</option>
            </select>
        </div>

        <div class="overflow-x-auto p-4">
            <div class="min-w-[850px]">
                <div class="grid grid-cols-7 border-b border-slate-200 text-center text-xs font-bold uppercase tracking-wide text-slate-500">
                    @foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $weekday)<div class="py-3">{{ $weekday }}</div>@endforeach
                </div>
                <div class="grid grid-cols-7 border-l border-slate-200">
                    @for ($blank = 0; $blank < $leadingBlankDays; $blank++)
                        <div class="min-h-32 border-b border-r border-slate-200 bg-slate-50"></div>
                    @endfor
                    @foreach ($days as $day)
                        <div class="min-h-32 border-b border-r border-slate-200 p-2 {{ $day['date']->isToday() ? 'bg-sky-50' : 'bg-white' }}">
                            <div class="mb-2 flex items-center justify-between">
                                <span class="inline-flex h-7 w-7 items-center justify-center rounded-full text-sm font-bold {{ $day['date']->isToday() ? 'bg-sky-700 text-white' : 'text-slate-700' }}">{{ $day['date']->day }}</span>
                            </div>
                            <div class="space-y-1.5">
                                @foreach ($day['events'] as $event)
                                    <button @if($canManage) wire:click="editEvent({{ $event->id }})" @endif type="button" class="block w-full rounded-lg border border-sky-200 bg-sky-50 px-2 py-1.5 text-left text-xs text-sky-950">
                                        <strong class="block truncate">{{ $event->title }}</strong>
                                        <span class="text-sky-700">{{ $event->all_day ? 'All day' : $event->starts_at->format('g:i A') }}</span>
                                    </button>
                                @endforeach
                                @foreach ($day['deadlines'] as $assignment)
                                    <a href="{{ route('assignments.index') }}" wire:navigate class="block rounded-lg border border-amber-200 bg-amber-50 px-2 py-1.5 text-xs text-amber-950">
                                        <strong class="block truncate">{{ $assignment->title }}</strong>
                                        <span class="text-amber-700">Due {{ $assignment->due_at->format('g:i A') }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    @if ($canManage && ($editingEventId !== null || $startsAt !== ''))
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div><h2 class="text-xl font-bold text-slate-950">{{ $editingEventId ? 'Edit calendar event' : 'Create calendar event' }}</h2><p class="mt-1 text-sm text-slate-500">Choose who can see this event in their workspace.</p></div>
                <button wire:click="cancelEditing" class="text-slate-400 hover:text-slate-700" aria-label="Close"><i class="fas fa-times"></i></button>
            </div>
            <form wire:submit="saveEvent" class="mt-5 grid gap-4 md:grid-cols-2">
                <div class="md:col-span-2"><label class="mb-1.5 block text-sm font-semibold text-slate-700">Title</label><input wire:model="title" class="w-full rounded-xl border-slate-300 focus:border-sky-500 focus:ring-sky-500">@error('title')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                <div><label class="mb-1.5 block text-sm font-semibold text-slate-700">Type</label><select wire:model="category" class="w-full rounded-xl border-slate-300"><option value="event">General event</option><option value="academic">Academic</option><option value="deadline">Deadline</option><option value="holiday">Holiday</option><option value="meeting">Meeting</option><option value="activity">Activity</option></select></div>
                <div><label class="mb-1.5 block text-sm font-semibold text-slate-700">Location</label><input wire:model="location" class="w-full rounded-xl border-slate-300" placeholder="Optional"></div>
                <div><label class="mb-1.5 block text-sm font-semibold text-slate-700">Starts</label><input type="datetime-local" wire:model="startsAt" class="w-full rounded-xl border-slate-300">@error('startsAt')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                <div><label class="mb-1.5 block text-sm font-semibold text-slate-700">Ends</label><input type="datetime-local" wire:model="endsAt" class="w-full rounded-xl border-slate-300">@error('endsAt')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                <label class="flex items-center gap-2 text-sm font-semibold text-slate-700"><input type="checkbox" wire:model="allDay" class="rounded border-slate-300 text-sky-700">All-day event</label>
                <div class="md:col-span-2"><label class="mb-2 block text-sm font-semibold text-slate-700">Visible to</label><div class="flex flex-wrap gap-4">@foreach(['all'=>'Everyone','staff'=>'All staff','teacher'=>'Teachers','student'=>'Students','parent'=>'Parents'] as $value=>$label)<label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" wire:model="audiences" value="{{ $value }}" class="rounded border-slate-300 text-sky-700">{{ $label }}</label>@endforeach</div>@error('audiences')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                <div class="md:col-span-2"><label class="mb-1.5 block text-sm font-semibold text-slate-700">Description</label><textarea rows="4" wire:model="description" class="w-full rounded-xl border-slate-300" placeholder="Event details"></textarea></div>
                <div class="md:col-span-2 flex flex-wrap justify-between gap-3 border-t border-slate-200 pt-4">
                    <div>@if($editingEventId)<button type="button" wire:click="deleteEvent({{ $editingEventId }})" wire:confirm="Delete this calendar event?" class="rounded-xl border border-red-200 px-4 py-2 text-sm font-bold text-red-700 hover:bg-red-50">Delete</button>@endif</div>
                    <div class="flex gap-2"><button type="button" wire:click="cancelEditing" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold">Cancel</button><button type="submit" class="rounded-xl bg-sky-700 px-5 py-2 text-sm font-bold text-white hover:bg-sky-800">Save event</button></div>
                </div>
            </form>
        </section>
    @endif
</div>
