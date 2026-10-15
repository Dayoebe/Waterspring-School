<div class="space-y-6">
    <section class="overflow-hidden rounded-2xl bg-slate-950 text-white shadow-xl">
        <div class="grid gap-8 px-6 py-8 lg:grid-cols-[1fr_auto] lg:px-10">
            <div>
                <div class="mb-3 inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-sky-200"><i class="fas fa-calendar-week"></i> Weekly class schedule</div>
                <h1 class="text-3xl font-black tracking-tight sm:text-4xl">Construct a proper school timetable</h1>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300 sm:text-base">Set the teaching day once. WaterSpring will build every lesson period and break in order, then open the weekly grid for subject allocation.</p>
            </div>
            <div class="flex items-start">
                @if($canReadTimetable)
                    <button type="button" wire:click="switchMode('list')" class="rounded-xl border border-white/20 bg-white/10 px-4 py-2.5 font-semibold text-white hover:bg-white/20"><i class="fas fa-arrow-left mr-2"></i>All timetables</button>
                @else
                    <a href="{{ route('dashboard') }}" wire:navigate class="rounded-xl border border-white/20 bg-white/10 px-4 py-2.5 font-semibold text-white hover:bg-white/20"><i class="fas fa-arrow-left mr-2"></i>Dashboard</a>
                @endif
            </div>
        </div>
    </section>

    @if(session('error'))<div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-800"><i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}</div>@endif
    @if(!$activeSemesterId)<div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-amber-900"><i class="fas fa-info-circle mr-2"></i>Set an active term before creating a timetable.</div>@endif

    <form wire:submit.prevent="createTimetable" class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(440px,.9fr)]">
        <div class="space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="mb-6 flex items-start gap-4">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-100 font-black text-sky-700">1</span>
                    <div><h2 class="text-xl font-bold text-slate-900">Timetable identity</h2><p class="mt-1 text-sm text-slate-500">Choose the class and give this term's timetable a clear title.</p></div>
                </div>
                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-bold text-slate-700">Class <span class="text-red-500">*</span></label>
                        <select wire:model="my_class_id" class="w-full rounded-xl border-slate-300 px-3 py-3 focus:border-sky-500 focus:ring-sky-500"><option value="">Choose a class</option>@foreach($classes as $class)<option value="{{ $class->id }}">{{ $class->name }}</option>@endforeach</select>
                        @error('my_class_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-bold text-slate-700">Title <span class="text-red-500">*</span></label>
                        <input type="text" wire:model="name" placeholder="e.g. JSS 1A – First Term" class="w-full rounded-xl border-slate-300 px-3 py-3 focus:border-sky-500 focus:ring-sky-500">
                        @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-2 block text-sm font-bold text-slate-700">Administrative note <span class="font-normal text-slate-400">(optional)</span></label>
                        <textarea wire:model="description" rows="2" placeholder="Any note about this timetable or term..." class="w-full rounded-xl border-slate-300 px-3 py-3 focus:border-sky-500 focus:ring-sky-500"></textarea>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="mb-6 flex items-start gap-4">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 font-black text-emerald-700">2</span>
                    <div><h2 class="text-xl font-bold text-slate-900">School-day structure</h2><p class="mt-1 text-sm text-slate-500">Define lessons and pauses. The end time is calculated automatically.</p></div>
                </div>
                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <div><label class="mb-2 block text-sm font-bold text-slate-700">Day starts</label><input type="time" wire:model.live="schoolDayStartsAt" class="w-full rounded-xl border-slate-300 px-3 py-3"></div>
                    <div><label class="mb-2 block text-sm font-bold text-slate-700">Number of lessons</label><input type="number" min="1" max="12" wire:model.live.debounce.300ms="lessonCount" class="w-full rounded-xl border-slate-300 px-3 py-3"></div>
                    <div><label class="mb-2 block text-sm font-bold text-slate-700">Minutes per lesson</label><input type="number" min="20" max="120" step="5" wire:model.live.debounce.300ms="lessonDuration" class="w-full rounded-xl border-slate-300 px-3 py-3"></div>
                </div>
                <div class="mt-6 grid gap-5 rounded-xl bg-slate-50 p-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div><label class="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-500">Short break after</label><select wire:model.live="shortBreakAfter" class="w-full rounded-lg border-slate-300"><option value="">No break</option>@for($i=1;$i<(int)$lessonCount;$i++)<option value="{{ $i }}">Period {{ $i }}</option>@endfor</select></div>
                    <div><label class="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-500">Break minutes</label><input type="number" min="5" max="60" step="5" wire:model.live.debounce.300ms="shortBreakDuration" class="w-full rounded-lg border-slate-300"></div>
                    <div><label class="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-500">Lunch after</label><select wire:model.live="lunchAfter" class="w-full rounded-lg border-slate-300"><option value="">No lunch</option>@for($i=1;$i<(int)$lessonCount;$i++)<option value="{{ $i }}">Period {{ $i }}</option>@endfor</select></div>
                    <div><label class="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-500">Lunch minutes</label><input type="number" min="10" max="120" step="5" wire:model.live.debounce.300ms="lunchDuration" class="w-full rounded-lg border-slate-300"></div>
                </div>
                @foreach(['schoolDayStartsAt','lessonCount','lessonDuration','shortBreakAfter','shortBreakDuration','lunchAfter','lunchDuration'] as $field)@error($field)<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror @endforeach
            </section>
        </div>

        <aside class="rounded-2xl border border-slate-200 bg-white shadow-sm xl:sticky xl:top-6">
            <div class="border-b border-slate-200 px-6 py-5"><p class="text-xs font-bold uppercase tracking-[0.18em] text-sky-700">Live preview</p><h2 class="mt-1 text-xl font-bold text-slate-900">Daily period plan</h2></div>
            <div class="p-4 sm:p-6">
                <div class="overflow-hidden rounded-xl border border-slate-200">
                    @foreach($this->generatedSchedulePreview() as $slot)
                        <div class="flex items-center justify-between gap-4 border-b border-slate-100 px-4 py-3 last:border-0 {{ $slot['is_break'] ? 'bg-amber-50' : 'bg-white' }}">
                            <div class="flex items-center gap-3"><span class="flex h-8 w-8 items-center justify-center rounded-lg {{ $slot['is_break'] ? 'bg-amber-100 text-amber-700' : 'bg-sky-100 text-sky-700' }}"><i class="fas {{ $slot['is_break'] ? 'fa-mug-hot' : 'fa-book-open' }} text-xs"></i></span><span class="font-bold text-slate-800">{{ $slot['label'] }}</span></div>
                            <span class="whitespace-nowrap text-sm font-semibold tabular-nums text-slate-500">{{ date('g:i A', strtotime($slot['start'])) }} – {{ date('g:i A', strtotime($slot['stop'])) }}</span>
                        </div>
                    @endforeach
                </div>
                @php($previewSlots = $this->generatedSchedulePreview())
                <div class="mt-4 rounded-xl bg-slate-950 p-4 text-sm text-slate-300">
                    <div class="flex justify-between"><span>Teaching periods</span><strong class="text-white">{{ (int)$lessonCount }}</strong></div>
                    <div class="mt-2 flex justify-between"><span>School closes</span><strong class="text-white">{{ date('g:i A', strtotime(collect($previewSlots)->last()['stop'])) }}</strong></div>
                </div>
                <button type="submit" wire:loading.attr="disabled" @disabled(!$activeSemesterId) class="mt-5 w-full rounded-xl bg-sky-600 px-6 py-3.5 font-bold text-white shadow-lg shadow-sky-200 hover:bg-sky-700 disabled:cursor-not-allowed disabled:opacity-50">
                    <span wire:loading.remove wire:target="createTimetable"><i class="fas fa-table-cells mr-2"></i>Create &amp; open weekly grid</span><span wire:loading wire:target="createTimetable"><i class="fas fa-spinner fa-spin mr-2"></i>Building timetable...</span>
                </button>
                <p class="mt-3 text-center text-xs leading-5 text-slate-500">Next, click each cell to assign the correct subject or school activity.</p>
            </div>
        </aside>
    </form>
</div>
