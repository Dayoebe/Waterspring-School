<div class="mt-4 space-y-3">
    @forelse($appointments as $appointment)
        <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2"><h3 class="font-black text-slate-900">{{ $appointment->student->name }}</h3><span class="rounded-full bg-white px-2.5 py-1 text-xs font-bold text-slate-700">{{ ucfirst($appointment->status) }}</span></div>
                    <p class="mt-1 text-sm font-semibold text-slate-700">{{ $appointment->slot->starts_at->format('M d, Y h:i A') }} – {{ $appointment->slot->ends_at->format('h:i A') }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $appointment->parent->name }} ↔ {{ $appointment->teacher->name }} · {{ str($appointment->slot->mode)->replace('_', ' ')->title() }} · {{ $appointment->slot->location ?: 'Location pending' }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if($isParent && in_array($appointment->status, ['requested', 'confirmed'], true))
                        <button wire:click="cancelAppointment({{ $appointment->id }})" wire:confirm="Cancel this appointment?" class="rounded-lg bg-rose-100 px-3 py-1.5 text-xs font-bold text-rose-700">Cancel</button>
                    @elseif(!$isParent && $appointment->status === 'requested')
                        <button wire:click="respond({{ $appointment->id }}, 'confirmed')" class="rounded-lg bg-emerald-100 px-3 py-1.5 text-xs font-bold text-emerald-700">Confirm</button>
                        <button wire:click="respond({{ $appointment->id }}, 'declined')" wire:confirm="Decline this meeting request?" class="rounded-lg bg-rose-100 px-3 py-1.5 text-xs font-bold text-rose-700">Decline</button>
                    @elseif(!$isParent && $appointment->status === 'confirmed')
                        <button wire:click="completeAppointment({{ $appointment->id }})" class="rounded-lg bg-cyan-100 px-3 py-1.5 text-xs font-bold text-cyan-700">Complete</button>
                    @endif
                    @if(!$isParent && in_array($appointment->status, ['confirmed', 'completed'], true))
                        <button wire:click="prepareNote({{ $appointment->id }})" class="rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-bold text-white">Add note</button>
                    @endif
                </div>
            </div>
            <div class="mt-3 rounded-xl border border-slate-200 bg-white p-3"><p class="text-xs font-bold uppercase tracking-wider text-slate-500">Reason</p><p class="mt-1 text-sm text-slate-700">{{ $appointment->reason }}</p></div>
            @if($appointment->notes->isNotEmpty())
                <div class="mt-3 space-y-2">
                    @foreach($appointment->notes as $appointmentNote)
                        <div class="rounded-xl border border-slate-200 bg-white px-3 py-2"><p class="text-xs font-black uppercase tracking-wider text-cyan-700">{{ str($appointmentNote->type)->replace('_', ' ')->title() }}</p><p class="mt-1 text-sm text-slate-700">{{ $appointmentNote->note }}</p><p class="mt-1 text-xs text-slate-400">{{ $appointmentNote->author_name }} · {{ $appointmentNote->created_at?->format('M d, Y h:i A') }}</p></div>
                    @endforeach
                </div>
            @endif
            @if(!$isParent && $noteAppointmentId === $appointment->id)
                <div class="mt-3 grid gap-2 sm:grid-cols-[11rem_1fr_auto]">
                    <select wire:model="noteType" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"><option value="meeting_note">Meeting note</option><option value="follow_up">Follow-up action</option></select>
                    <input wire:model="note" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Record the note or action">
                    <button wire:click="addNote" class="rounded-lg bg-cyan-700 px-4 py-2 text-xs font-bold text-white">Save</button>
                </div>
            @endif
        </article>
    @empty
        <div class="rounded-xl border border-dashed border-slate-300 px-4 py-10 text-center text-sm text-slate-500">No appointments match this view.</div>
    @endforelse
</div>
