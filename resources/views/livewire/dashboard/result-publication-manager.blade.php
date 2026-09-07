<section class="rounded-[1.75rem] border border-violet-200 bg-violet-50 p-6 shadow-sm">
    <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
        <div class="max-w-2xl">
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-violet-700">Result Release Control</p>
            <h2 class="mt-2 text-2xl font-bold text-slate-900">Publish results when computation is complete</h2>
            <p class="mt-2 text-sm leading-6 text-slate-600">
                Unpublished results remain available to staff for computation and review, but are hidden from parent and student accounts.
            </p>
        </div>

        <div class="grid w-full gap-3 sm:grid-cols-2 xl:max-w-xl">
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-600">Academic year</span>
                <select wire:model.live="academicYearId"
                    class="w-full rounded-xl border-violet-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm focus:border-violet-400 focus:ring-violet-300">
                    <option value="">Select academic year</option>
                    @foreach($academicYears as $academicYear)
                        <option value="{{ $academicYear->id }}">{{ $academicYear->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-600">Term</span>
                <select wire:model.live="semesterId"
                    class="w-full rounded-xl border-violet-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm focus:border-violet-400 focus:ring-violet-300">
                    <option value="">Select term</option>
                    @foreach($semesters as $semester)
                        <option value="{{ $semester->id }}">{{ $semester->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </div>

    @if($statusMessage)
        <div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
            {{ $statusMessage }}
        </div>
    @endif

    @error('publication')
        <div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800">
            <i class="fas fa-triangle-exclamation mr-2"></i>{{ $message }}
        </div>
    @enderror

    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border {{ $termPublished ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }} p-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] {{ $termPublished ? 'text-emerald-700' : 'text-amber-700' }}">Term result</p>
                    <p class="mt-2 text-lg font-bold text-slate-900">{{ $termPublished ? 'Visible to families' : 'Hidden from families' }}</p>
                    <p class="mt-1 text-sm text-slate-600">Controls the selected academic year and term.</p>
                    @if($termReadiness)
                        <p class="mt-3 text-sm font-semibold {{ ($termReadiness['ready'] ?? false) ? 'text-emerald-700' : 'text-rose-700' }}">
                            {{ number_format($termReadiness['completed'] ?? 0) }} of
                            {{ number_format($termReadiness['expected'] ?? 0) }} required entries completed
                            @if(($termReadiness['missing'] ?? 0) > 0)
                                · {{ number_format($termReadiness['missing']) }} missing
                            @endif
                        </p>
                    @endif
                </div>
                <span class="rounded-full px-3 py-1 text-xs font-bold {{ $termPublished ? 'bg-emerald-200 text-emerald-900' : 'bg-amber-200 text-amber-900' }}">
                    {{ $termPublished ? 'Published' : 'Draft' }}
                </span>
            </div>
            <button type="button" wire:click="toggleTermPublication"
                wire:confirm="{{ $termPublished ? 'Hide this term result from all parents and students?' : 'Publish this term result to all parents and students?' }}"
                @disabled(!$academicYearId || !$semesterId || (!$termPublished && !($termReadiness['ready'] ?? false)))
                class="mt-5 inline-flex items-center rounded-xl px-5 py-3 text-sm font-semibold text-white shadow-sm disabled:cursor-not-allowed disabled:opacity-50 {{ $termPublished ? 'bg-rose-600 hover:bg-rose-700' : 'bg-emerald-600 hover:bg-emerald-700' }}">
                <i class="fas {{ $termPublished ? 'fa-eye-slash' : 'fa-paper-plane' }} mr-2"></i>
                {{ $termPublished ? 'Unpublish term result' : 'Publish term result' }}
            </button>
        </div>

        <div class="rounded-2xl border {{ $annualPublished ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }} p-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] {{ $annualPublished ? 'text-emerald-700' : 'text-amber-700' }}">Annual result</p>
                    <p class="mt-2 text-lg font-bold text-slate-900">{{ $annualPublished ? 'Visible to families' : 'Hidden from families' }}</p>
                    <p class="mt-1 text-sm text-slate-600">Controls the complete selected academic year.</p>
                    @if($annualReadiness)
                        <p class="mt-3 text-sm font-semibold {{ ($annualReadiness['ready'] ?? false) ? 'text-emerald-700' : 'text-rose-700' }}">
                            @if($annualReadiness['reason'] ?? null)
                                {{ $annualReadiness['reason'] }}
                            @else
                                {{ number_format($annualReadiness['completed'] ?? 0) }} of
                                {{ number_format($annualReadiness['expected'] ?? 0) }} required entries completed
                                @if(($annualReadiness['missing'] ?? 0) > 0)
                                    · {{ number_format($annualReadiness['missing']) }} missing
                                @endif
                            @endif
                        </p>
                    @endif
                </div>
                <span class="rounded-full px-3 py-1 text-xs font-bold {{ $annualPublished ? 'bg-emerald-200 text-emerald-900' : 'bg-amber-200 text-amber-900' }}">
                    {{ $annualPublished ? 'Published' : 'Draft' }}
                </span>
            </div>
            <button type="button" wire:click="toggleAnnualPublication"
                wire:confirm="{{ $annualPublished ? 'Hide this annual result from all parents and students?' : 'Publish this annual result to all parents and students?' }}"
                @disabled(!$academicYearId || (!$annualPublished && !($annualReadiness['ready'] ?? false)))
                class="mt-5 inline-flex items-center rounded-xl px-5 py-3 text-sm font-semibold text-white shadow-sm disabled:cursor-not-allowed disabled:opacity-50 {{ $annualPublished ? 'bg-rose-600 hover:bg-rose-700' : 'bg-violet-600 hover:bg-violet-700' }}">
                <i class="fas {{ $annualPublished ? 'fa-eye-slash' : 'fa-calendar-check' }} mr-2"></i>
                {{ $annualPublished ? 'Unpublish annual result' : 'Publish annual result' }}
            </button>
        </div>
    </div>

    <div class="mt-6 rounded-2xl border border-sky-200 bg-sky-50 p-5">
        <div class="flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-sky-700">Class Exam Participation</p>
                <h3 class="mt-1 text-lg font-bold text-slate-900">Record classes without internal examinations</h3>
                <p class="mt-1 max-w-3xl text-sm leading-6 text-slate-600">
                    Use this when an entire class wrote WAEC, NECO, BECE, or did not sit the school examination.
                    Students remain active, but the class stops blocking internal result publication for the selected term.
                </p>
            </div>
            <span class="rounded-full bg-sky-100 px-3 py-1 text-xs font-bold text-sky-800">Super admin only</span>
        </div>

        <form wire:submit="saveExamParticipation" class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-700">Class</span>
                <select wire:model="examClassId" class="w-full rounded-xl border-sky-200 bg-white px-3 py-2.5 text-sm">
                    <option value="">Select class</option>
                    @foreach($classes as $classOption)
                        <option value="{{ $classOption->id }}">{{ $classOption->name }}</option>
                    @endforeach
                </select>
                @error('examClassId') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-700">Participation status</span>
                <select wire:model.live="examParticipationStatus" class="w-full rounded-xl border-sky-200 bg-white px-3 py-2.5 text-sm">
                    <option value="external">External examination</option>
                    <option value="exempt">Exempt from internal exam</option>
                    <option value="postponed">Internal exam postponed</option>
                    <option value="cancelled">Internal exam cancelled</option>
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-700">External examination</span>
                <input wire:model="externalExaminationName" type="text" maxlength="100"
                    placeholder="e.g. WAEC"
                    class="w-full rounded-xl border-sky-200 bg-white px-3 py-2.5 text-sm">
                @error('externalExaminationName') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
            </label>

            <label class="block xl:col-span-2">
                <span class="mb-1 block text-sm font-semibold text-slate-700">Reason</span>
                <input wire:model="examParticipationReason" type="text" maxlength="255"
                    placeholder="e.g. SS3 wrote WAEC instead of the school examination"
                    class="w-full rounded-xl border-sky-200 bg-white px-3 py-2.5 text-sm">
                @error('examParticipationReason') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
            </label>

            <label class="block md:col-span-2 xl:col-span-4">
                <span class="mb-1 block text-sm font-semibold text-slate-700">Internal note <span class="font-normal text-slate-500">(optional)</span></span>
                <textarea wire:model="examParticipationNotes" rows="2" maxlength="1000"
                    placeholder="Add approval or examination details"
                    class="w-full rounded-xl border-sky-200 bg-white px-3 py-2.5 text-sm"></textarea>
            </label>

            <div class="flex items-end">
                <button type="submit"
                    wire:confirm="Confirm this entire class did not participate in the selected internal examination?"
                    @disabled(!$academicYearId || !$semesterId)
                    class="inline-flex w-full items-center justify-center rounded-xl bg-sky-700 px-5 py-3 text-sm font-bold text-white hover:bg-sky-800 disabled:cursor-not-allowed disabled:opacity-50">
                    <i class="fas fa-file-signature mr-2"></i>Save participation
                </button>
            </div>
        </form>

        @if($examParticipations->isNotEmpty())
            <div class="mt-5 grid gap-3 md:grid-cols-2">
                @foreach($examParticipations as $participation)
                    <div class="rounded-xl border border-sky-200 bg-white p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-bold text-slate-900">{{ $participation->myClass?->name }}</p>
                                <p class="mt-1 text-sm font-semibold capitalize text-sky-800">
                                    {{ str_replace('_', ' ', $participation->status) }}
                                    @if($participation->examination_name)
                                        · {{ $participation->examination_name }}
                                    @endif
                                </p>
                                <p class="mt-2 text-sm text-slate-600">{{ $participation->reason }}</p>
                            </div>
                            <button type="button" wire:click="restoreInternalExamParticipation({{ $participation->id }})"
                                wire:confirm="Restore this class to internal result requirements for the selected term?"
                                class="shrink-0 text-xs font-bold text-emerald-700 hover:text-emerald-900">
                                Restore internal exam
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    @if(!empty($termReadiness['missing_students']))
        <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <div class="flex flex-col gap-2 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="font-bold text-slate-900">Students blocking term publication</h3>
                    <p class="mt-1 text-sm text-slate-600">
                        Review students with no results before marking them inactive. Partial results normally mean computation is unfinished.
                    </p>
                </div>
                @if(($termReadiness['excluded_students'] ?? 0) > 0)
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">
                        {{ number_format($termReadiness['excluded_students']) }} already excluded
                    </span>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-600">
                        <tr>
                            <th class="px-5 py-3">Student</th>
                            <th class="px-5 py-3">Class</th>
                            <th class="px-5 py-3">Progress</th>
                            <th class="px-5 py-3">Missing subjects</th>
                            <th class="px-5 py-3 text-right">Review</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach(array_slice($termReadiness['missing_students'], 0, 25) as $missingStudent)
                            <tr class="{{ $missingStudent['has_no_results'] ? 'bg-amber-50/60' : '' }}">
                                <td class="px-5 py-3">
                                    <p class="font-semibold text-slate-900">{{ $missingStudent['name'] }}</p>
                                    @if($missingStudent['has_no_results'])
                                        <span class="mt-1 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-800">
                                            No results entered
                                        </span>
                                    @else
                                        <span class="mt-1 inline-flex rounded-full bg-blue-100 px-2 py-0.5 text-xs font-bold text-blue-800">
                                            Results partially entered
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-slate-700">{{ $missingStudent['class_name'] }}</td>
                                <td class="px-5 py-3">
                                    <span class="font-semibold text-slate-900">
                                        {{ number_format($missingStudent['completed']) }}/{{ number_format($missingStudent['expected']) }}
                                    </span>
                                    <span class="block text-xs text-rose-700">{{ number_format($missingStudent['missing']) }} missing</span>
                                </td>
                                <td class="max-w-md px-5 py-3 text-xs leading-5 text-slate-600">
                                    {{ collect($missingStudent['missing_subjects'])->take(5)->join(', ') }}
                                    @if(count($missingStudent['missing_subjects']) > 5)
                                        and {{ count($missingStudent['missing_subjects']) - 5 }} more
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('students.show', $missingStudent['user_id']) }}"
                                        class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:border-violet-400 hover:text-violet-700">
                                        <i class="fas fa-user-check mr-2"></i>
                                        {{ $missingStudent['has_no_results'] ? 'Confirm status' : 'Open student' }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if(count($termReadiness['missing_students']) > 25)
                <div class="border-t border-slate-200 bg-slate-50 px-5 py-3 text-sm text-slate-600">
                    Showing the 25 students with the most urgent missing results out of
                    {{ number_format(count($termReadiness['missing_students'])) }} affected students.
                </div>
            @endif
        </div>
    @endif
</section>
