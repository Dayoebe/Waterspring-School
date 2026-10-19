<div class="space-y-6">
    <section class="overflow-hidden rounded-2xl border border-violet-200 bg-white shadow-sm">
        <div class="bg-gradient-to-r from-violet-700 to-indigo-700 px-6 py-7 text-white">
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-violet-100">School community</p>
            <h1 class="mt-2 text-3xl font-black">Teachers</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-violet-50">Class and subject teachers assigned to {{ auth()->user()->hasRole('parent') ? 'your linked children' : 'your current class' }}.</p>
        </div>

        <div class="space-y-6 p-6">
            @forelse($students as $student)
                <article class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                    <div class="border-b border-slate-200 pb-4">
                        @if(auth()->user()->hasRole('parent'))<p class="text-xs font-bold uppercase tracking-widest text-violet-700">{{ $student['name'] }}</p>@endif
                        <h2 class="{{ auth()->user()->hasRole('parent') ? 'mt-1' : '' }} text-xl font-black text-slate-900">{{ $student['class_name'] }}</h2>
                    </div>

                    <div class="mt-5 grid gap-6 xl:grid-cols-2">
                        <section class="rounded-xl border border-emerald-200 bg-emerald-50 p-5">
                            <p class="text-xs font-bold uppercase tracking-widest text-emerald-700">Class teacher</p>
                            <div class="mt-4 space-y-3">
                                @forelse($student['class_teachers'] as $teacher)
                                    <div class="flex items-center gap-3 rounded-xl bg-white p-4 shadow-sm">
                                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700"><i class="fas fa-user-tie"></i></span>
                                        <p class="font-bold text-slate-900">{{ $teacher }}</p>
                                    </div>
                                @empty
                                    <p class="rounded-xl bg-white p-4 text-sm text-slate-600">No class teacher has been assigned yet.</p>
                                @endforelse
                            </div>
                        </section>

                        <section class="rounded-xl border border-sky-200 bg-sky-50 p-5">
                            <p class="text-xs font-bold uppercase tracking-widest text-sky-700">Subject teachers</p>
                            <div class="mt-4 space-y-3">
                                @forelse($student['subjects'] as $subject)
                                    <div class="rounded-xl bg-white p-4 shadow-sm">
                                        <p class="font-bold text-slate-900">{{ $subject['name'] }}</p>
                                        <p class="mt-1 text-sm text-slate-600"><i class="fas fa-chalkboard-user mr-1 text-sky-600"></i>{{ $subject['teachers'] !== [] ? implode(', ', $subject['teachers']) : 'Teacher not assigned yet' }}</p>
                                    </div>
                                @empty
                                    <p class="rounded-xl bg-white p-4 text-sm text-slate-600">No subjects have been assigned to this class yet.</p>
                                @endforelse
                            </div>
                        </section>
                    </div>
                </article>
            @empty
                <p class="rounded-xl bg-slate-50 p-5 text-sm text-slate-600">No linked student class was found for this account.</p>
            @endforelse
        </div>
    </section>
</div>
