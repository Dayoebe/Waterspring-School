<div class="space-y-6">
    <section class="overflow-hidden rounded-2xl border border-sky-200 bg-white shadow-sm">
        <div class="bg-gradient-to-r from-sky-700 to-indigo-700 px-6 py-7 text-white">
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-sky-100">Classes and subjects</p>
            <h1 class="mt-2 text-3xl font-black">Subjects</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-sky-50">View the subjects and teachers assigned to {{ auth()->user()->hasRole('parent') ? 'your linked children’s classes' : 'your current class' }}.</p>
        </div>

        <div class="space-y-6 p-6">
            @forelse($students as $student)
                <article class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                    <div class="flex flex-col gap-2 border-b border-slate-200 pb-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            @if(auth()->user()->hasRole('parent'))<p class="text-xs font-bold uppercase tracking-widest text-indigo-700">{{ $student['name'] }}</p>@endif
                            <h2 class="{{ auth()->user()->hasRole('parent') ? 'mt-1' : '' }} text-xl font-black text-slate-900">{{ $student['class_name'] }}</h2>
                        </div>
                        <span class="w-fit rounded-full bg-sky-100 px-3 py-1 text-xs font-bold text-sky-800">{{ count($student['subjects']) }} subjects</span>
                    </div>

                    <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        @forelse($student['subjects'] as $subject)
                            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                                <div class="flex items-start gap-3">
                                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-sky-100 text-sky-700"><i class="fas fa-book-open"></i></span>
                                    <div class="min-w-0">
                                        <h3 class="font-bold text-slate-900">{{ $subject['name'] }}</h3>
                                        @if($subject['short_name'])<p class="mt-0.5 text-xs font-semibold text-slate-500">{{ $subject['short_name'] }}</p>@endif
                                        <p class="mt-3 text-sm text-slate-600"><i class="fas fa-chalkboard-user mr-1 text-sky-600"></i>{{ $subject['teachers'] !== [] ? implode(', ', $subject['teachers']) : 'Teacher not assigned yet' }}</p>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <p class="rounded-xl bg-white p-4 text-sm text-slate-500 md:col-span-2 xl:col-span-3">No subjects have been assigned to this class yet.</p>
                        @endforelse
                    </div>
                </article>
            @empty
                <p class="rounded-xl bg-slate-50 p-5 text-sm text-slate-600">No linked student class was found for this account.</p>
            @endforelse
        </div>
    </section>
</div>
