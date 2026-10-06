<section class="overflow-hidden rounded-2xl border border-sky-200 bg-white">
    <button type="button" wire:click="toggleChildrenPanel" class="flex w-full items-center justify-between gap-4 bg-sky-50 px-6 py-5 text-left hover:bg-sky-100" aria-expanded="{{ $showChildrenPanel ? 'true' : 'false' }}">
        <span class="flex items-start gap-4"><span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-sky-100 text-sky-700"><i class="fas fa-children" aria-hidden="true"></i></span><span><span class="block text-lg font-bold text-slate-900">Children</span><span class="mt-1 block text-sm font-normal text-slate-600">Select existing students or create a new student without leaving this page.</span></span></span>
        <i class="fas fa-chevron-{{ $showChildrenPanel ? 'up' : 'down' }} text-sky-700" aria-hidden="true"></i>
    </button>

    @if($showChildrenPanel)
        <div class="space-y-5 border-t border-sky-100 p-6">
            @if($assignedChildren->isNotEmpty())
                <div><p class="text-sm font-bold text-slate-800">Already linked</p><div class="mt-3 grid gap-3 sm:grid-cols-2">@foreach($assignedChildren as $child)<div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3"><p class="font-bold text-slate-900">{{ $child->name }}</p><p class="mt-1 text-xs text-slate-600">{{ $child->studentRecord?->admission_number ?: 'No admission number' }}@if($child->studentRecord?->myClass) · {{ $child->studentRecord->myClass->name }}@endif</p></div>@endforeach</div><p class="mt-2 text-xs text-slate-500">Existing parent relationships are permanent and cannot be removed here.</p></div>
            @endif

            <div class="grid gap-3 sm:grid-cols-2">
                <label class="flex cursor-pointer gap-3 rounded-xl border p-4 {{ $childEntryMode === 'existing' ? 'border-sky-400 bg-sky-50' : 'border-slate-200' }}"><input type="radio" wire:model.live="childEntryMode" value="existing" class="mt-1 text-sky-700"><span><span class="block font-bold text-slate-900">Select existing students</span><span class="mt-1 block text-xs text-slate-600">Only students without a linked parent are shown.</span></span></label>
                @can('create student')<label class="flex cursor-pointer gap-3 rounded-xl border p-4 {{ $childEntryMode === 'new' ? 'border-sky-400 bg-sky-50' : 'border-slate-200' }}"><input type="radio" wire:model.live="childEntryMode" value="new" class="mt-1 text-sky-700"><span><span class="block font-bold text-slate-900">Create a new student</span><span class="mt-1 block text-xs text-slate-600">The student and relationship are saved together.</span></span></label>@endcan
            </div>

            @if($childEntryMode === 'existing')
                <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700">Find students</label><input type="search" wire:model.live.debounce.300ms="studentSearch" placeholder="Start typing a name, email, or admission number" autocomplete="off" class="w-full rounded-xl border-slate-300 px-4 py-3">
                    <div class="mt-3 max-h-72 space-y-2 overflow-y-auto rounded-xl border border-slate-200 p-3">
                        <div wire:loading wire:target="studentSearch" class="p-5 text-center text-sm font-semibold text-sky-700"><i class="fas fa-spinner fa-spin mr-2"></i>Searching students…</div>
                        <div wire:loading.remove wire:target="studentSearch">
                            @if(trim($studentSearch) === '')
                                <p class="p-5 text-center text-sm text-slate-500"><i class="fas fa-search mb-2 block text-xl text-sky-500"></i>Start typing above to find an unassigned student.</p>
                            @else
                                @forelse($availableStudents as $student)
                                    <label class="flex cursor-pointer items-start gap-3 rounded-lg p-3 hover:bg-sky-50"><input type="checkbox" wire:model="selectedStudentIds" value="{{ $student->id }}" class="mt-1 rounded text-sky-700"><span><span class="block font-bold text-slate-900">{{ $student->name }}</span><span class="text-xs text-slate-600">{{ $student->studentRecord?->admission_number ?: $student->email }}@if($student->studentRecord?->myClass) · {{ $student->studentRecord->myClass->name }}@endif</span></span></label>
                                @empty <p class="p-5 text-center text-sm text-slate-500">No unassigned students match “{{ trim($studentSearch) }}”.</p>@endforelse
                            @endif
                        </div>
                    </div>
                    @error('selectedStudentIds')<span class="mt-2 block text-sm text-red-600">{{ $message }}</span>@enderror
                </div>
            @else
                @can('create student')
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5"><div class="grid gap-4 md:grid-cols-2">
                        <div><label class="mb-1 block text-sm font-semibold">Student name *</label><input wire:model="newStudentName" type="text" class="w-full rounded-xl border-slate-300 px-4 py-3">@error('newStudentName')<span class="text-sm text-red-600">{{ $message }}</span>@enderror</div>
                        <div><label class="mb-1 block text-sm font-semibold">Student email *</label><input wire:model="newStudentEmail" type="email" class="w-full rounded-xl border-slate-300 px-4 py-3">@error('newStudentEmail')<span class="text-sm text-red-600">{{ $message }}</span>@enderror</div>
                        <div><x-password-input label="Student password *" wire:model="newStudentPassword" />@error('newStudentPassword')<span class="text-sm text-red-600">{{ $message }}</span>@enderror</div>
                        <div><label class="mb-1 block text-sm font-semibold">Gender *</label><select wire:model="newStudentGender" class="w-full rounded-xl border-slate-300 px-4 py-3"><option value="">Select gender</option><option value="male">Male</option><option value="female">Female</option></select>@error('newStudentGender')<span class="text-sm text-red-600">{{ $message }}</span>@enderror</div>
                        <div><label class="mb-1 block text-sm font-semibold">Class *</label><select wire:model.live="newStudentClassId" class="w-full rounded-xl border-slate-300 px-4 py-3"><option value="">Select class</option>@foreach($classes as $class)<option value="{{ $class->id }}">{{ $class->name }}</option>@endforeach</select>@error('newStudentClassId')<span class="text-sm text-red-600">{{ $message }}</span>@enderror</div>
                        <div><label class="mb-1 block text-sm font-semibold">Section</label><select wire:model="newStudentSectionId" class="w-full rounded-xl border-slate-300 px-4 py-3"><option value="">No section</option>@foreach($newStudentSections as $section)<option value="{{ $section->id }}">{{ $section->name }}</option>@endforeach</select>@error('newStudentSectionId')<span class="text-sm text-red-600">{{ $message }}</span>@enderror</div>
                        <div><label class="mb-1 block text-sm font-semibold">Admission number</label><input wire:model="newStudentAdmissionNumber" type="text" placeholder="Generated if empty" class="w-full rounded-xl border-slate-300 px-4 py-3">@error('newStudentAdmissionNumber')<span class="text-sm text-red-600">{{ $message }}</span>@enderror</div>
                        <div><label class="mb-1 block text-sm font-semibold">Admission date</label><input wire:model="newStudentAdmissionDate" type="date" class="w-full rounded-xl border-slate-300 px-4 py-3">@error('newStudentAdmissionDate')<span class="text-sm text-red-600">{{ $message }}</span>@enderror</div>
                        <div><label class="mb-1 block text-sm font-semibold">Birthday</label><input wire:model="newStudentBirthday" type="date" class="w-full rounded-xl border-slate-300 px-4 py-3">@error('newStudentBirthday')<span class="text-sm text-red-600">{{ $message }}</span>@enderror</div>
                        <div><label class="mb-1 block text-sm font-semibold">Phone</label><input wire:model="newStudentPhone" type="tel" class="w-full rounded-xl border-slate-300 px-4 py-3">@error('newStudentPhone')<span class="text-sm text-red-600">{{ $message }}</span>@enderror</div>
                    </div></div>
                @endcan
            @endif
        </div>
    @endif
</section>
