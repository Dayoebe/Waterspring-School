<div class="space-y-6">
    @if(session()->has('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>
    @endif

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.18em] text-sky-700">Student records</p>
                    <h2 class="mt-2 text-2xl font-black text-slate-950">Login readiness</h2>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">Complete real email addresses and replace temporary admission passwords before giving students access to their dashboard.</p>
                </div>
                <div class="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <strong>{{ $students->total() }}</strong> student{{ $students->total() === 1 ? '' : 's' }} need attention
                </div>
            </div>
            <div class="mt-5 max-w-xl">
                <label for="credential-search" class="sr-only">Search students</label>
                <div class="relative"><i class="fas fa-search absolute left-4 top-3.5 text-slate-400"></i><input id="credential-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Search name, admission number, or email" class="w-full rounded-xl border-slate-300 py-3 pl-11 pr-4 text-sm focus:border-sky-500 focus:ring-sky-500"></div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Student</th><th class="px-5 py-3">Current email</th><th class="px-5 py-3">What is missing</th><th class="px-5 py-3 text-right">Action</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($students as $student)
                        <tr wire:key="credential-student-{{ $student->id }}">
                            <td class="px-5 py-4"><p class="font-bold text-slate-900">{{ $student->name }}</p><p class="mt-1 text-xs text-slate-500">{{ $student->studentRecord?->admission_number ?: 'No admission number' }} · {{ $student->studentRecord?->baseClass?->name ?: 'No class' }}</p></td>
                            <td class="px-5 py-4 text-sm"><span class="{{ $student->email_is_placeholder ? 'text-amber-700' : 'text-slate-700' }}">{{ $student->email_is_placeholder ? 'Temporary admission email' : ($student->email ?: 'No email') }}</span></td>
                            <td class="px-5 py-4"><div class="flex flex-wrap gap-2">@if($student->email_is_placeholder || blank($student->email))<span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-800">Real email</span>@endif @if($student->requires_password_change || blank($student->password))<span class="rounded-full bg-rose-100 px-2.5 py-1 text-xs font-bold text-rose-800">Secure password</span>@endif</div></td>
                            <td class="px-5 py-4 text-right"><button wire:click="edit({{ $student->id }})" class="rounded-lg bg-sky-700 px-4 py-2 text-sm font-bold text-white hover:bg-sky-800">Complete details</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-14 text-center"><i class="fas fa-circle-check text-3xl text-emerald-500"></i><h3 class="mt-3 font-bold text-slate-900">All student accounts are ready</h3><p class="mt-1 text-sm text-slate-500">There are no missing login details.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($students->hasPages())<div class="border-t border-slate-200 p-4">{{ $students->links() }}</div>@endif
    </section>

    @if($editingStudentId)
        @php($editingStudent = $students->getCollection()->firstWhere('id', $editingStudentId))
        <section class="rounded-2xl border border-sky-200 bg-white p-6 shadow-sm">
            <div class="flex items-start justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-widest text-sky-700">Complete credentials</p><h2 class="mt-1 text-xl font-black text-slate-950">{{ $editingStudent?->name ?? 'Student account' }}</h2></div><button wire:click="cancel" class="text-slate-400 hover:text-slate-700" aria-label="Close"><i class="fas fa-times"></i></button></div>
            <form wire:submit="save" class="mt-5 grid gap-4 md:grid-cols-2">
                <div class="md:col-span-2"><label class="mb-1.5 block text-sm font-semibold text-slate-700">Student login email</label><input type="email" wire:model="email" autocomplete="off" class="w-full rounded-xl border-slate-300 px-4 py-3 focus:border-sky-500 focus:ring-sky-500" placeholder="student@example.com">@error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror<p class="mt-1.5 text-xs text-slate-500">This becomes the username for dashboard sign-in and is marked verified after administrative confirmation.</p></div>
                <x-password-input wire:model="password" input-id="student-ready-password" label="New password" autocomplete="new-password" class="border px-4 py-3 focus:border-sky-500 focus:ring-sky-500" />
                <x-password-input wire:model="passwordConfirmation" input-id="student-ready-password-confirmation" label="Confirm password" autocomplete="new-password" class="border px-4 py-3 focus:border-sky-500 focus:ring-sky-500" />
                @error('password')<p class="text-xs text-red-600 md:col-span-2">{{ $message }}</p>@enderror
                <div class="md:col-span-2 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900"><strong>Before saving:</strong> securely give the email and new password to the student or parent. The password is stored securely and cannot be displayed again.</div>
                <div class="md:col-span-2 flex justify-end gap-2"><button type="button" wire:click="cancel" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700">Cancel</button><button type="submit" class="rounded-xl bg-sky-700 px-5 py-2.5 text-sm font-bold text-white hover:bg-sky-800">Save login details</button></div>
            </form>
        </section>
    @endif
</div>
