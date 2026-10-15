<section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-start gap-4">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-100 font-black text-indigo-700">01</span>
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-indigo-700">Report details</p>
                <h3 class="mt-1 text-xl font-bold text-slate-900">Report card information</h3>
                <p class="mt-1 text-sm text-slate-600">Set the announcement and resumption date shown to families.</p>
            </div>
        </div>
        <span class="inline-flex self-start rounded-full bg-slate-200 px-3 py-1 text-xs font-bold text-slate-700 sm:self-center">Global or class-specific</span>
    </div>

    <div class="space-y-6 p-6">
        @if($showSuccess)
            <div class="flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                <div class="flex items-center"><i class="fas fa-check-circle mr-3 text-xl text-emerald-600"></i><p class="font-semibold text-emerald-800">{{ $successMessage }}</p></div>
                <button wire:click="$set('showSuccess', false)" class="text-emerald-600 hover:text-emerald-800" aria-label="Dismiss message"><i class="fas fa-times"></i></button>
            </div>
        @endif

        @if($academicYearId && $semesterId)
            <div>
                <label class="mb-3 block text-xs font-bold uppercase tracking-[0.16em] text-slate-500">Settings scope</label>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="flex cursor-pointer items-center rounded-xl border border-slate-200 bg-slate-50 p-4 has-[:checked]:border-indigo-400 has-[:checked]:bg-indigo-50">
                        <input type="radio" wire:model.live="isGlobal" value="1" class="mr-3 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm font-semibold text-slate-700"><i class="fas fa-globe mr-2 text-indigo-600"></i>All classes</span>
                    </label>
                    <label class="flex cursor-pointer items-center rounded-xl border border-slate-200 bg-slate-50 p-4 has-[:checked]:border-indigo-400 has-[:checked]:bg-indigo-50">
                        <input type="radio" wire:model.live="isGlobal" value="0" class="mr-3 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm font-semibold text-slate-700"><i class="fas fa-school mr-2 text-indigo-600"></i>Specific class</span>
                    </label>
                </div>
            </div>

            <div class="grid gap-5 lg:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700"><i class="fas fa-calendar-alt mr-2 text-indigo-600"></i>Resumption date</label>
                    <input type="date" wire:model="resumptionDate" class="w-full rounded-xl border-slate-300 bg-white px-4 py-3 focus:border-indigo-500 focus:ring-indigo-500">
                    @error('resumptionDate')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                @if(!$isGlobal)
                    <div>
                        <label class="mb-2 block text-sm font-semibold text-slate-700"><i class="fas fa-school mr-2 text-indigo-600"></i>Class</label>
                        <select wire:model.live="selectedClassId" class="w-full rounded-xl border-slate-300 bg-white px-4 py-3 focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Select class</option>
                            @foreach($classes as $class)<option value="{{ $class->id }}">{{ $class->name }}</option>@endforeach
                        </select>
                        @error('selectedClassId')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                @endif
            </div>

            <div>
                <label class="mb-2 block text-sm font-semibold text-slate-700"><i class="fas fa-bullhorn mr-2 text-indigo-600"></i>Report announcement</label>
                <textarea wire:model="generalAnnouncement" rows="4" class="w-full rounded-xl border-slate-300 bg-white px-4 py-3 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Enter the message families should see on this term's report..."></textarea>
                <p class="mt-2 text-sm text-slate-500">Shown on reports for {{ !$isGlobal && $selectedClassId ? ($classes->firstWhere('id', $selectedClassId)->name ?? 'the selected class') : 'all classes' }}.</p>
                @error('generalAnnouncement')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            @if($generalAnnouncement || $resumptionDate)
                <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-5">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-indigo-700"><i class="fas fa-eye mr-2"></i>Family-facing preview</p>
                    <div class="mt-3 space-y-2 text-sm leading-6 text-slate-700">
                        @if($generalAnnouncement)<p><strong class="text-slate-900">Announcement:</strong> {{ $generalAnnouncement }}</p>@endif
                        @if($resumptionDate)<p><strong class="text-slate-900">Resumption date:</strong> {{ \Carbon\Carbon::parse($resumptionDate)->format('d M Y') }}</p>@endif
                    </div>
                </div>
            @endif

            <div class="flex justify-end border-t border-slate-100 pt-5">
                <button wire:click="save" wire:loading.attr="disabled" wire:target="save" class="inline-flex items-center rounded-xl bg-indigo-600 px-6 py-3 font-bold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-50">
                    <span wire:loading.remove wire:target="save"><i class="fas fa-save mr-2"></i>Save report settings</span>
                    <span wire:loading wire:target="save"><i class="fas fa-spinner fa-spin mr-2"></i>Saving...</span>
                </button>
            </div>
        @else
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-8 text-center">
                <i class="fas fa-info-circle mb-3 text-3xl text-amber-600"></i>
                <p class="font-semibold text-amber-900">Select an academic year and term to manage report settings.</p>
            </div>
        @endif
    </div>
</section>
