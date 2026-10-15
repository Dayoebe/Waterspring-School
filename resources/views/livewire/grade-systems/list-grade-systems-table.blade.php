<div class="space-y-6">
    <section class="overflow-hidden rounded-3xl bg-slate-950 text-white shadow-xl">
        <div class="grid gap-6 px-6 py-8 lg:grid-cols-[1fr_auto] lg:items-end lg:px-9">
            <div>
                <div class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-bold uppercase tracking-[0.2em] text-emerald-200"><i class="fas fa-graduation-cap"></i> Academic standards</div>
                <h1 class="mt-4 text-3xl font-black tracking-tight">Grading Systems</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-300">Define the score ranges, grades, and remarks used on report cards for each class group.</p>
            </div>
            @can('create grade system')
                <a href="{{ route('grade-systems.create') }}" class="inline-flex items-center justify-center rounded-xl bg-emerald-500 px-5 py-3 font-bold text-slate-950 shadow-lg shadow-emerald-950/20 transition hover:bg-emerald-400">
                    <i class="fas fa-plus mr-2"></i>Add grade band
                </a>
            @endcan
        </div>
    </section>

    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 font-medium text-emerald-800"><i class="fas fa-check-circle mr-2"></i>{{ session('success') }}</div>
    @endif

    @if($classGroups->isEmpty())
        <section class="rounded-3xl border border-amber-200 bg-amber-50 p-10 text-center">
            <i class="fas fa-layer-group text-4xl text-amber-500"></i>
            <h2 class="mt-4 text-xl font-bold text-amber-950">Create a class group first</h2>
            <p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-amber-800">Grades are assigned by class group so primary, junior secondary, and senior secondary classes can use different standards.</p>
        </section>
    @else
        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
                <div>
                    <label for="class-group" class="mb-2 block text-xs font-bold uppercase tracking-[0.16em] text-slate-500">Class group</label>
                    <select id="class-group" wire:model.live="classGroup" class="w-full rounded-xl border-slate-300 bg-white px-4 py-3 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 lg:max-w-xl">
                        @foreach($classGroups as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-xl bg-slate-100 px-4 py-3 text-center"><p class="text-2xl font-black text-slate-900">{{ $grades->count() }}</p><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Grade bands</p></div>
                    <div class="rounded-xl {{ $coveredScores >= 101 ? 'bg-emerald-100' : 'bg-amber-100' }} px-4 py-3 text-center"><p class="text-2xl font-black {{ $coveredScores >= 101 ? 'text-emerald-800' : 'text-amber-800' }}">{{ number_format(($coveredScores / 101) * 100, 0) }}%</p><p class="text-xs font-bold uppercase tracking-wide {{ $coveredScores >= 101 ? 'text-emerald-700' : 'text-amber-700' }}">Coverage</p></div>
                </div>
            </div>
        </section>

        <div wire:loading class="w-full rounded-2xl border border-slate-200 bg-white p-8 text-center text-slate-500"><i class="fas fa-spinner fa-spin mr-2"></i>Loading grading system...</div>

        <section wire:loading.remove class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-2 border-b border-slate-200 bg-slate-50 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
                <div><p class="text-xs font-bold uppercase tracking-[0.18em] text-emerald-700">{{ $selectedClassGroup?->name }}</p><h2 class="mt-1 text-xl font-bold text-slate-900">Grade bands and remarks</h2></div>
                @if($grades->isNotEmpty() && $coveredScores < 101)<span class="self-start rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-800 sm:self-center"><i class="fas fa-triangle-exclamation mr-1"></i>Some scores are not covered</span>@endif
            </div>

            @if($grades->isEmpty())
                <div class="px-6 py-14 text-center">
                    <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-100 text-2xl text-emerald-700"><i class="fas fa-chart-simple"></i></span>
                    <h3 class="mt-5 text-xl font-bold text-slate-900">No grade bands configured</h3>
                    <p class="mx-auto mt-2 max-w-lg text-sm leading-6 text-slate-600">Start with the highest grade and add consecutive ranges until every score from 0 to 100 has a grade and remark.</p>
                    @can('create grade system')<a href="{{ route('grade-systems.create', ['class_group' => $classGroup]) }}" class="mt-5 inline-flex items-center rounded-xl bg-emerald-600 px-5 py-3 font-bold text-white hover:bg-emerald-700"><i class="fas fa-plus mr-2"></i>Create first grade band</a>@endcan
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left">
                        <thead class="border-b border-slate-200 bg-white text-xs font-bold uppercase tracking-wider text-slate-500"><tr><th class="px-6 py-4">Grade</th><th class="px-6 py-4">Score range</th><th class="px-6 py-4">Remark</th><th class="px-6 py-4 text-right">Actions</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($grades as $grade)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-6 py-4"><span class="inline-flex min-w-12 items-center justify-center rounded-xl bg-emerald-100 px-3 py-2 font-black text-emerald-800">{{ $grade->name }}</span></td>
                                    <td class="px-6 py-4"><span class="font-bold tabular-nums text-slate-900">{{ $grade->grade_from }}–{{ $grade->grade_till }}</span><span class="ml-1 text-sm text-slate-500">marks</span></td>
                                    <td class="px-6 py-4 text-sm text-slate-700">{{ $grade->remark ?: 'No remark specified' }}</td>
                                    <td class="px-6 py-4 text-right"><div class="inline-flex gap-2">
                                        @can('update', $grade)<a href="{{ route('grade-systems.edit', $grade) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-bold text-slate-700 hover:border-emerald-400 hover:text-emerald-700"><i class="fas fa-pen mr-1"></i>Edit</a>@endcan
                                        @can('delete', $grade)<form method="POST" action="{{ route('grade-systems.destroy', $grade) }}" onsubmit="return confirm('Delete this grade band? Scores in this range may no longer receive a grade.')">@csrf @method('DELETE')<button class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm font-bold text-red-700 hover:bg-red-100"><i class="fas fa-trash"></i></button></form>@endcan
                                    </div></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif
</div>
