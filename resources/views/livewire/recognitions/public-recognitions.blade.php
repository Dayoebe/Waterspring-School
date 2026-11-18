<div class="bg-slate-50 text-slate-900">
    <section class="relative overflow-hidden bg-sky-950 text-white">
        <div class="absolute inset-0 opacity-20" aria-hidden="true"><div class="absolute -left-16 top-10 h-72 w-72 rounded-full bg-amber-300 blur-3xl"></div><div class="absolute -right-20 bottom-0 h-80 w-80 rounded-full bg-cyan-400 blur-3xl"></div></div>
        <div class="relative mx-auto max-w-7xl px-4 py-16 text-center sm:px-6 lg:px-8 lg:py-24">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full border border-amber-300/50 bg-amber-300 text-2xl text-sky-950 shadow-xl"><i class="fas fa-award"></i></div>
            <p class="mt-6 text-xs font-black uppercase tracking-[0.3em] text-amber-300">Excellence · Character · Growth · Recognition</p>
            <h1 class="mt-4 text-4xl font-black sm:text-6xl">Face of the Week</h1>
            <p class="mx-auto mt-5 max-w-3xl text-lg leading-8 text-sky-100">Celebrating excellence, character and outstanding contributions. Every week, we recognise students who inspire others through their actions, achievements and commitment to becoming better.</p>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-xs font-black uppercase tracking-[0.2em] text-sky-700">This week</p><h2 class="mt-2 text-3xl font-black sm:text-4xl">Students inspiring their classes</h2></div><p class="max-w-xl text-sm leading-6 text-slate-600">Each class selects one student, recognising the form of excellence they demonstrated that week.</p></div>
        @if($current->isEmpty())
            <div class="mt-8 rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center"><i class="fas fa-star text-4xl text-amber-300"></i><h3 class="mt-4 text-xl font-black">This week’s recognitions are being prepared</h3><p class="mx-auto mt-2 max-w-xl text-slate-600">Published class selections will appear here after review. You can explore earlier achievements in the Hall of Fame below.</p></div>
        @else
            <div class="mt-8 grid gap-7 md:grid-cols-2 xl:grid-cols-3">@foreach($current as $recognition)<x-recognition-card :recognition="$recognition"/>@endforeach</div>
        @endif
    </section>

    <section class="border-y border-sky-100 bg-white py-14">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div><p class="text-xs font-black uppercase tracking-[0.2em] text-amber-700">Permanent archive</p><h2 class="mt-2 text-3xl font-black sm:text-4xl">Recognition Hall of Fame</h2><p class="mt-3 max-w-3xl leading-7 text-slate-600">Explore published recognitions from every class and academic session. Drafts and records without public-display approval are never shown here.</p></div>
            <div class="mt-7 grid gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:grid-cols-2 lg:grid-cols-4">
                <label><span class="text-xs font-bold uppercase tracking-wide text-slate-600">Class</span><select wire:model.live="classFilter" class="mt-2 w-full rounded-xl border-slate-300 bg-white"><option value="">All classes</option>@foreach($classes as $class)<option value="{{ $class->id }}">{{ $class->name }}</option>@endforeach</select></label>
                <label><span class="text-xs font-bold uppercase tracking-wide text-slate-600">Category</span><select wire:model.live="categoryFilter" class="mt-2 w-full rounded-xl border-slate-300 bg-white"><option value="">All categories</option>@foreach(\App\Support\RecognitionCategories::all() as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
                <label><span class="text-xs font-bold uppercase tracking-wide text-slate-600">Academic session</span><select wire:model.live="academicYearFilter" class="mt-2 w-full rounded-xl border-slate-300 bg-white"><option value="">All sessions</option>@foreach($years as $year)<option value="{{ $year->id }}">{{ $year->name }}</option>@endforeach</select></label>
                <label><span class="text-xs font-bold uppercase tracking-wide text-slate-600">Calendar year</span><select wire:model.live="yearFilter" class="mt-2 w-full rounded-xl border-slate-300 bg-white"><option value="">All years</option>@foreach($calendarYears as $year)<option value="{{ $year }}">{{ $year }}</option>@endforeach</select></label>
            </div>
            <div wire:loading class="mt-4 text-sm font-semibold text-sky-700"><i class="fas fa-spinner fa-spin mr-2"></i>Updating the Hall of Fame…</div>
            @if($archive->isEmpty())
                <div class="mt-8 rounded-3xl border border-dashed border-slate-300 bg-slate-50 p-12 text-center"><i class="fas fa-medal text-4xl text-slate-300"></i><p class="mt-3 font-bold text-slate-700">No published recognitions match these filters.</p></div>
            @else
                <div class="mt-8 grid gap-7 md:grid-cols-2 xl:grid-cols-3">@foreach($archive as $recognition)<x-recognition-card :recognition="$recognition"/>@endforeach</div><div class="mt-8">{{ $archive->links() }}</div>
            @endif
        </div>
    </section>
</div>
