<div class="space-y-6">
    @if(session()->has('success'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>@endif
    @if(session()->has('error'))<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800">{{ session('error') }}</div>@endif
    @if(session()->has('pickup_code'))
        <div class="rounded-2xl border-2 border-amber-400 bg-amber-50 p-5 text-amber-950">
            <p class="text-xs font-black uppercase tracking-[0.18em]">One-time pickup code</p>
            <p class="mt-2 font-mono text-4xl font-black tracking-[0.3em]">{{ session('pickup_code') }}</p>
            <p class="mt-2 text-sm font-semibold">Share this securely with the authorised guardian now. Only its hash is stored, so the code cannot be displayed again.</p>
        </div>
    @endif

    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-black uppercase tracking-[0.18em] text-red-700">School operations</p>
        <h1 class="mt-1 text-2xl font-black text-slate-950">Transport & authorised pickup</h1>
        <p class="mt-1 text-sm text-slate-600">Routes, student assignments, secure guardian verification, movement history, and parent arrival alerts.</p>
        @if($isParent && $children->isNotEmpty())
            <select wire:model.live="childFilter" class="mt-4 rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm font-semibold">
                @foreach($children as $child)<option value="{{ $child->id }}">{{ $child->name }}</option>@endforeach
            </select>
        @endif
    </div>

    @if(!$isParent)
        <div class="grid gap-4 xl:grid-cols-3">
            <details open class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <summary class="cursor-pointer text-lg font-black text-slate-900">1. Create route</summary>
                <div class="mt-4 grid gap-2 sm:grid-cols-2">
                    <input wire:model="routeName" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm sm:col-span-2" placeholder="Route name">
                    <input wire:model="routeCode" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm" placeholder="Route code">
                    <input wire:model="vehicleLabel" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm" placeholder="Vehicle / plate">
                    <input wire:model="driverName" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm" placeholder="Driver name">
                    <input wire:model="driverPhone" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm" placeholder="Driver phone">
                    <button wire:click="createRoute" class="rounded-xl bg-red-700 px-4 py-2.5 text-sm font-black text-white sm:col-span-2">Create route</button>
                </div>
            </details>

            <details open class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <summary class="cursor-pointer text-lg font-black text-slate-900">2. Add stop</summary>
                <div class="mt-4 grid gap-2 sm:grid-cols-2">
                    <select wire:model="stopRouteId" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm sm:col-span-2"><option value="">Select route</option>@foreach($routes as $route)<option value="{{ $route->id }}">{{ $route->name }}</option>@endforeach</select>
                    <input wire:model="stopName" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm" placeholder="Stop name">
                    <input wire:model="stopSequence" type="number" min="1" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm" placeholder="Sequence">
                    <div><label class="text-xs font-bold text-slate-500">Morning</label><input wire:model="morningTime" type="time" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"></div>
                    <div><label class="text-xs font-bold text-slate-500">Afternoon</label><input wire:model="afternoonTime" type="time" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"></div>
                    <input wire:model="landmark" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm sm:col-span-2" placeholder="Landmark">
                    <button wire:click="addStop" class="rounded-xl bg-red-700 px-4 py-2.5 text-sm font-black text-white sm:col-span-2">Add stop</button>
                </div>
            </details>

            <details open class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <summary class="cursor-pointer text-lg font-black text-slate-900">3. Assign student</summary>
                <div class="mt-4 grid gap-2">
                    <select wire:model="assignmentStudentId" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><option value="">Select student</option>@foreach($students as $student)<option value="{{ $student->id }}">{{ $student->name }}</option>@endforeach</select>
                    <select wire:model.live="assignmentRouteId" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><option value="">Select route</option>@foreach($routes->where('is_active', true) as $route)<option value="{{ $route->id }}">{{ $route->name }}</option>@endforeach</select>
                    <select wire:model="assignmentStopId" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><option value="">Select stop</option>@foreach($assignmentStops as $stop)<option value="{{ $stop->id }}">{{ $stop->name }}</option>@endforeach</select>
                    <select wire:model="assignmentDirection" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><option value="both">Morning + afternoon</option><option value="morning">Morning only</option><option value="afternoon">Afternoon only</option></select>
                    <button wire:click="assignStudent" class="rounded-xl bg-red-700 px-4 py-2.5 text-sm font-black text-white">Assign transport</button>
                </div>
            </details>

            <details class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <summary class="cursor-pointer text-lg font-black text-slate-900">4. Authorise guardian</summary>
                <div class="mt-4 grid gap-2">
                    <select wire:model="guardianStudentId" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><option value="">Select student</option>@foreach($students as $student)<option value="{{ $student->id }}">{{ $student->name }}</option>@endforeach</select>
                    <input wire:model="guardianName" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm" placeholder="Guardian full name">
                    <input wire:model="guardianRelationship" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm" placeholder="Relationship">
                    <input wire:model="guardianPhone" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm" placeholder="Phone">
                    <button wire:click="authorizeGuardian" class="rounded-xl bg-amber-600 px-4 py-2.5 text-sm font-black text-white">Authorise and generate code</button>
                </div>
            </details>

            <details class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <summary class="cursor-pointer text-lg font-black text-slate-900">5. Verify pickup</summary>
                <div class="mt-4 grid gap-2">
                    <select wire:model="verificationGuardianId" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><option value="">Select active guardian</option>@foreach($guardians->where('is_active', true) as $guardian)<option value="{{ $guardian->id }}">{{ $guardian->student->name }} · {{ $guardian->name }}</option>@endforeach</select>
                    <input wire:model="pickupCode" inputmode="numeric" maxlength="6" class="rounded-xl border border-slate-300 px-3 py-2.5 font-mono text-lg tracking-[0.25em]" placeholder="6-digit code">
                    <input wire:model="verificationNotes" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm" placeholder="Optional checkpoint note">
                    <button wire:click="verifyPickup" class="rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-black text-white">Verify guardian</button>
                </div>
            </details>

            <details open class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <summary class="cursor-pointer text-lg font-black text-slate-900">6. Record movement</summary>
                <div class="mt-4 grid gap-2">
                    <select wire:model="movementAssignmentId" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><option value="">Select active assignment</option>@foreach($assignments->where('status', 'active') as $assignment)<option value="{{ $assignment->id }}">{{ $assignment->student->name }} · {{ $assignment->route->name }}</option>@endforeach</select>
                    <select wire:model="movementType" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><option value="boarded">Boarded</option><option value="arrived">Arrived safely</option><option value="dropped_off">Dropped off</option></select>
                    <input wire:model="movementNotes" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm" placeholder="Optional operational note">
                    <button wire:click="recordMovement" class="rounded-xl bg-cyan-700 px-4 py-2.5 text-sm font-black text-white">Record event</button>
                </div>
            </details>
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-black text-slate-900">Routes & assignments</h2>
            @if(!$isParent)
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    @forelse($routes as $route)
                        <div class="rounded-xl border border-slate-200 p-3"><p class="font-black text-slate-900">{{ $route->name }} <span class="text-xs text-slate-500">{{ $route->code }}</span></p><p class="mt-1 text-xs text-slate-500">{{ $route->vehicle_label ?: 'Vehicle pending' }} · {{ $route->driver_name ?: 'Driver pending' }}</p><ol class="mt-2 space-y-1 text-xs text-slate-600">@foreach($route->stops as $stop)<li>{{ $stop->sequence }}. {{ $stop->name }} @if($stop->morning_time)· {{ substr($stop->morning_time, 0, 5) }}@endif</li>@endforeach</ol></div>
                    @empty<p class="text-sm text-slate-500">No transport routes yet.</p>@endforelse
                </div>
            @endif
            <div class="mt-4 space-y-2">
                @forelse($assignments as $assignment)
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3"><div class="flex items-start justify-between gap-3"><div><p class="text-sm font-black text-slate-900">{{ $assignment->student->name }}</p><p class="text-xs text-slate-600">{{ $assignment->route->name }} · {{ $assignment->stop->name }} · {{ str($assignment->direction)->title() }} · {{ ucfirst($assignment->status) }}</p></div>@if(!$isParent && $assignment->status === 'active')<button wire:click="deactivateAssignment({{ $assignment->id }})" wire:confirm="Deactivate this assignment?" class="text-xs font-bold text-rose-700">Deactivate</button>@endif</div></div>
                @empty<p class="text-sm text-slate-500">No transport assignment is available.</p>@endforelse
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-black text-slate-900">Authorised pickup guardians</h2>
            <div class="mt-4 space-y-2">
                @forelse($guardians as $guardian)
                    <div class="rounded-xl border border-slate-200 p-3"><div class="flex items-start justify-between gap-3"><div><p class="text-sm font-black text-slate-900">{{ $guardian->name }} <span class="text-xs font-semibold text-slate-500">· {{ $guardian->student->name }}</span></p><p class="text-xs text-slate-600">{{ $guardian->relationship }} · {{ $guardian->phone }} · {{ $guardian->is_active ? 'Active' : 'Revoked' }}</p>@if($guardian->last_verified_at)<p class="mt-1 text-xs text-emerald-700">Last verified {{ $guardian->last_verified_at->diffForHumans() }}</p>@endif</div>@if(!$isParent && $guardian->is_active)<button wire:click="revokeGuardian({{ $guardian->id }})" wire:confirm="Revoke this guardian’s pickup authority?" class="text-xs font-bold text-rose-700">Revoke</button>@endif</div></div>
                @empty<p class="text-sm text-slate-500">No authorised pickup guardians.</p>@endforelse
            </div>
        </section>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-black text-slate-900">Transport event history</h2>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm"><thead class="bg-slate-50"><tr><th class="px-3 py-2 text-left text-xs font-black uppercase text-slate-500">Time</th><th class="px-3 py-2 text-left text-xs font-black uppercase text-slate-500">Student</th><th class="px-3 py-2 text-left text-xs font-black uppercase text-slate-500">Event</th><th class="px-3 py-2 text-left text-xs font-black uppercase text-slate-500">Route / guardian</th><th class="px-3 py-2 text-left text-xs font-black uppercase text-slate-500">Recorded by</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($events as $event)<tr><td class="whitespace-nowrap px-3 py-3 text-xs text-slate-600">{{ $event->occurred_at->format('M d, h:i A') }}</td><td class="px-3 py-3 font-bold text-slate-800">{{ $event->student->name }}</td><td class="px-3 py-3"><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-bold text-slate-700">{{ str($event->event_type)->replace('_', ' ')->title() }}</span></td><td class="px-3 py-3 text-slate-600">{{ $event->route?->name ?: 'Pickup checkpoint' }}{{ $event->guardian ? ' · '.$event->guardian->name : '' }}</td><td class="px-3 py-3 text-slate-600">{{ $event->recorder_name }}</td></tr>@empty<tr><td colspan="5" class="px-3 py-10 text-center text-slate-500">No transport events recorded.</td></tr>@endforelse</tbody></table>
        </div>
    </section>
</div>
