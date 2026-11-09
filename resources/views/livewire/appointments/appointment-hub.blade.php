<div class="space-y-6">
    @if(session()->has('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>
    @endif
    @if(session()->has('error'))
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800">{{ session('error') }}</div>
    @endif

    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-black uppercase tracking-[0.18em] text-red-700">Family engagement</p>
        <h1 class="mt-1 text-2xl font-black text-slate-950">Parent-teacher appointments</h1>
        <p class="mt-1 text-sm text-slate-600">Manage verified availability, meeting requests, reminders, shared notes, and follow-up actions.</p>
    </div>

    @if($isParent)
        <div class="grid gap-6 lg:grid-cols-[22rem_minmax(0,1fr)]">
            <section class="h-fit rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-black text-slate-900">Request a meeting</h2>
                <label class="mt-4 block text-xs font-bold uppercase tracking-wider text-slate-500">Child</label>
                <select wire:model.live="studentId" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
                    <option value="">Select child</option>
                    @foreach($children as $child)<option value="{{ $child->id }}">{{ $child->name }}</option>@endforeach
                </select>

                <label class="mt-4 block text-xs font-bold uppercase tracking-wider text-slate-500">Available time</label>
                <select wire:model="slotId" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
                    <option value="">Select an assigned teacher’s slot</option>
                    @foreach($availableSlots as $slot)
                        <option value="{{ $slot->id }}">{{ $slot->starts_at->format('M d, h:i A') }} · {{ $slot->teacher->name }}</option>
                    @endforeach
                </select>
                @if($studentId && $availableSlots->isEmpty())<p class="mt-2 text-xs text-amber-700">No assigned teacher has an open slot yet.</p>@endif

                <label class="mt-4 block text-xs font-bold uppercase tracking-wider text-slate-500">Meeting reason</label>
                <textarea wire:model="reason" rows="4" maxlength="2000" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" placeholder="What would you like to discuss?"></textarea>
                @error('reason')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                <button wire:click="requestAppointment" wire:loading.attr="disabled" class="mt-4 w-full rounded-xl bg-red-700 px-4 py-3 text-sm font-black text-white hover:bg-red-800">Send meeting request</button>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3"><h2 class="text-lg font-black text-slate-900">My requests</h2>@include('livewire.appointments.partials.status-filter')</div>
                @include('livewire.appointments.partials.appointment-list')
            </section>
        </div>
    @else
        <div class="grid gap-6 xl:grid-cols-[24rem_minmax(0,1fr)]">
            <div class="space-y-6">
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-black text-slate-900">Add availability</h2>
                    @if($isLeadership)
                        <label class="mt-4 block text-xs font-bold uppercase tracking-wider text-slate-500">Teacher</label>
                        <select wire:model="teacherId" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
                            <option value="">Select teacher</option>
                            @foreach($teachers as $teacher)<option value="{{ $teacher->id }}">{{ $teacher->name }}</option>@endforeach
                        </select>
                    @endif
                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <input wire:model="slotDate" type="date" class="col-span-2 rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        <div><label class="block text-xs font-bold text-slate-500">Starts</label><input wire:model="startTime" type="time" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"></div>
                        <div><label class="block text-xs font-bold text-slate-500">Ends</label><input wire:model="endTime" type="time" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"></div>
                        <select wire:model="mode" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
                            <option value="in_person">In person</option><option value="video">Video</option><option value="phone">Phone</option>
                        </select>
                        <input wire:model="location" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm" placeholder="Room or link">
                        <textarea wire:model="slotNotes" rows="2" class="col-span-2 rounded-xl border border-slate-300 px-3 py-2.5 text-sm" placeholder="Optional instructions"></textarea>
                    </div>
                    <button wire:click="createAvailability" class="mt-4 w-full rounded-xl bg-red-700 px-4 py-3 text-sm font-black text-white">Publish availability</button>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-black text-slate-900">Upcoming availability</h2>
                    <div class="mt-3 space-y-2">
                        @forelse($slots as $slot)
                            <div class="rounded-xl border border-slate-200 p-3">
                                <p class="text-sm font-bold text-slate-800">{{ $slot->starts_at->format('M d, Y h:i A') }}</p>
                                <p class="text-xs text-slate-500">{{ $slot->teacher->name }} · {{ str($slot->mode)->replace('_', ' ')->title() }} · {{ ucfirst($slot->status) }}</p>
                                @if($slot->active_appointments_count)<p class="mt-1 text-xs font-bold text-cyan-700">Active request attached</p>@endif
                                @if($slot->status === 'available' && !$slot->active_appointments_count)<button wire:click="cancelSlot({{ $slot->id }})" wire:confirm="Cancel this availability slot?" class="mt-2 text-xs font-bold text-rose-700">Cancel slot</button>@endif
                            </div>
                        @empty<p class="text-sm text-slate-500">No upcoming availability.</p>@endforelse
                    </div>
                </section>
            </div>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><h2 class="text-lg font-black text-slate-900">Meeting requests</h2>@include('livewire.appointments.partials.status-filter')</div>
                @include('livewire.appointments.partials.appointment-list')
            </section>
        </div>
    @endif
</div>
