<div class="space-y-6">
    @if (session()->has('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>
    @endif

    <section class="overflow-hidden rounded-2xl bg-slate-950 px-6 py-7 text-white shadow-sm">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-red-300">Academic workspace</p>
                <h1 class="mt-2 text-3xl font-black">Assignments</h1>
                <p class="mt-2 max-w-2xl text-sm text-slate-300">
                    @if ($isStudent) Review your work, submit answers and follow teacher feedback.
                    @elseif ($isParent) Follow assignments and submission progress for each child.
                    @else Publish class or subject work, review responses and record scores. @endif
                </p>
            </div>
            @if ($canManage)
                <button type="button" wire:click="$toggle('showCreate')" class="rounded-xl bg-red-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-red-700">
                    <i class="fas fa-plus mr-2"></i>{{ $showCreate ? 'Close form' : 'New assignment' }}
                </button>
            @endif
        </div>
        <div class="mt-6 grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl bg-white/10 p-4"><p class="text-xs text-slate-300">Visible assignments</p><p class="mt-1 text-2xl font-black">{{ $assignments->count() }}</p></div>
            <div class="rounded-xl bg-white/10 p-4"><p class="text-xs text-slate-300">Still open</p><p class="mt-1 text-2xl font-black">{{ $assignments->where('due_at', '>=', now())->count() }}</p></div>
            <div class="rounded-xl bg-white/10 p-4"><p class="text-xs text-slate-300">Submissions</p><p class="mt-1 text-2xl font-black">{{ $assignments->sum('submissions_count') }}</p></div>
        </div>
    </section>

    @if ($canManage && $showCreate)
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-5"><h2 class="text-xl font-bold text-slate-900">Publish an assignment</h2><p class="mt-1 text-sm text-slate-500">Choose a subject for subject work, or leave it class-wide if you are the class teacher.</p></div>
            <form wire:submit="createAssignment" class="grid gap-5 md:grid-cols-2">
                <div class="md:col-span-2"><label class="mb-1.5 block text-sm font-semibold text-slate-700">Title</label><input wire:model="title" class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm focus:border-red-500 focus:ring-red-500" placeholder="e.g. Algebra revision exercise">@error('title')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                <div><label class="mb-1.5 block text-sm font-semibold text-slate-700">Class</label><select wire:model.live="classId" class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm focus:border-red-500 focus:ring-red-500"><option value="">Select class</option>@foreach($classes as $class)<option value="{{ $class->id }}">{{ $class->name }}</option>@endforeach</select>@error('classId')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                <div><label class="mb-1.5 block text-sm font-semibold text-slate-700">Subject</label><select wire:model="subjectId" class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm focus:border-red-500 focus:ring-red-500"><option value="">Class-wide assignment</option>@foreach($subjects as $subject)<option value="{{ $subject->id }}">{{ $subject->name }}</option>@endforeach</select>@error('subjectId')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                <div><label class="mb-1.5 block text-sm font-semibold text-slate-700">Due date and time</label><input type="datetime-local" wire:model="dueAt" class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm focus:border-red-500 focus:ring-red-500">@error('dueAt')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                <div><label class="mb-1.5 block text-sm font-semibold text-slate-700">Maximum score <span class="font-normal text-slate-400">(optional)</span></label><input type="number" min="0" step="0.01" wire:model="maxScore" class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm focus:border-red-500 focus:ring-red-500" placeholder="100">@error('maxScore')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                <div class="md:col-span-2"><label class="mb-1.5 block text-sm font-semibold text-slate-700">Instructions</label><textarea rows="5" wire:model="instructions" class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm focus:border-red-500 focus:ring-red-500" placeholder="Explain what students should complete and how to submit it."></textarea>@error('instructions')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                <div class="md:col-span-2"><label class="mb-1.5 block text-sm font-semibold text-slate-700">Supporting file <span class="font-normal text-slate-400">(optional, 10 MB)</span></label><input type="file" wire:model="assignmentFile" class="block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:font-semibold">@error('assignmentFile')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                <div class="md:col-span-2 flex justify-end"><button class="rounded-xl bg-red-600 px-6 py-3 text-sm font-bold text-white hover:bg-red-700" wire:loading.attr="disabled"><span wire:loading.remove wire:target="createAssignment">Publish assignment</span><span wire:loading wire:target="createAssignment">Publishing...</span></button></div>
            </form>
        </section>
    @endif

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(360px,0.8fr)]">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row">
                <input wire:model.live.debounce.300ms="search" class="min-w-0 flex-1 rounded-xl border-slate-300 px-4 py-2.5 text-sm" placeholder="Search assignments">
                <select wire:model.live="statusFilter" class="rounded-xl border-slate-300 px-4 py-2.5 text-sm"><option value="all">All deadlines</option><option value="open">Open</option><option value="closed">Past due</option></select>
            </div>
            <div class="mt-5 space-y-3">
                @forelse($assignments as $assignment)
                    @php
                        $record = auth()->user()->studentRecord;
                        $ownSubmission = $record ? $assignment->submissions()->where('student_record_id', $record->id)->first() : null;
                        $pastDue = $assignment->due_at->isPast();
                    @endphp
                    <article wire:key="assignment-{{ $assignment->id }}" class="rounded-xl border p-4 transition {{ $selectedAssignmentId === $assignment->id ? 'border-red-300 bg-red-50/40' : 'border-slate-200 hover:border-slate-300' }}">
                        <button type="button" wire:click="selectAssignment({{ $assignment->id }})" class="w-full text-left">
                            <div class="flex items-start justify-between gap-4"><div><div class="flex flex-wrap gap-2"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600">{{ $assignment->myClass?->name }}</span><span class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-bold text-red-700">{{ $assignment->subject?->name ?? 'Class-wide' }}</span>@if($isParent)<span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-bold text-blue-700">{{ $assignment->recipients->whereIn('id', $childRecords->keys())->pluck('user.name')->filter()->join(', ') }}</span>@endif</div><h3 class="mt-3 font-bold text-slate-900">{{ $assignment->title }}</h3><p class="mt-1 text-xs text-slate-500">Set by {{ $assignment->teacher?->name }} · {{ $assignment->published_at->format('d M Y') }}</p></div><span class="shrink-0 rounded-lg px-2.5 py-1 text-xs font-bold {{ $pastDue ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700' }}">{{ $pastDue ? 'Past due' : $assignment->due_at->diffForHumans() }}</span></div>
                            <div class="mt-4 flex flex-wrap items-center gap-4 text-xs text-slate-500"><span><i class="far fa-clock mr-1"></i>{{ $assignment->due_at->format('D, d M Y · g:i A') }}</span>@if($assignment->max_score)<span><i class="fas fa-star mr-1"></i>{{ $assignment->max_score + 0 }} marks</span>@endif @if($canManage)<span>{{ $assignment->submissions_count }}/{{ $assignment->recipients_count }} submitted</span>@elseif($ownSubmission)<span class="font-bold text-emerald-700">{{ ucfirst($ownSubmission->status) }}</span>@endif</div>
                        </button>
                        @if($canManage)<div class="mt-3 border-t border-slate-100 pt-3 text-right"><button type="button" wire:click="deleteAssignment({{ $assignment->id }})" wire:confirm="Remove this assignment and all its submissions?" class="text-xs font-semibold text-red-600 hover:text-red-800">Remove</button></div>@endif
                    </article>
                @empty
                    <div class="py-14 text-center"><i class="fas fa-book-open text-4xl text-slate-300"></i><p class="mt-3 font-semibold text-slate-700">No assignments found</p><p class="mt-1 text-sm text-slate-500">New work will appear here when it is published.</p></div>
                @endforelse
            </div>
        </section>

        <aside class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm xl:sticky xl:top-5 xl:self-start">
            @if($selectedAssignment)
                <div class="flex items-start justify-between gap-3"><div><p class="text-xs font-bold uppercase tracking-wide text-red-600">{{ $selectedAssignment->subject?->name ?? 'Class-wide' }}</p><h2 class="mt-1 text-xl font-black text-slate-900">{{ $selectedAssignment->title }}</h2></div><button wire:click="$set('selectedAssignmentId', null)" class="text-slate-400 hover:text-slate-700"><i class="fas fa-times"></i></button></div>
                <p class="mt-4 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $selectedAssignment->instructions }}</p>
                <div class="mt-4 rounded-xl bg-slate-50 p-4 text-sm"><p><span class="font-semibold">Due:</span> {{ $selectedAssignment->due_at->format('D, d M Y · g:i A') }}</p><p class="mt-1"><span class="font-semibold">Class:</span> {{ $selectedAssignment->myClass?->name }}</p>@if($selectedAssignment->max_score)<p class="mt-1"><span class="font-semibold">Maximum score:</span> {{ $selectedAssignment->max_score + 0 }}</p>@endif</div>
                @if($selectedAssignment->attachment_path)<button wire:click="downloadAssignment({{ $selectedAssignment->id }})" class="mt-4 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50"><i class="fas fa-download mr-2"></i>{{ $selectedAssignment->attachment_name }}</button>@endif

                @if($isStudent)
                    @php $submission = $selectedAssignment->submissions->firstWhere('student_record_id', auth()->user()->studentRecord?->id); @endphp
                    <form wire:submit="submitAssignment" class="mt-6 border-t border-slate-200 pt-5"><h3 class="font-bold text-slate-900">{{ $submission ? 'Update your submission' : 'Submit your work' }}</h3><textarea rows="5" wire:model="submissionText" class="mt-3 w-full rounded-xl border-slate-300 px-4 py-3 text-sm" placeholder="Write your answer or a note about the attached work."></textarea>@error('submissionText')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror<input type="file" wire:model="submissionFile" class="mt-3 block w-full rounded-xl border border-slate-300 px-3 py-2 text-xs">@error('submissionFile')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror @if($submission?->attachment_path)<button type="button" wire:click="downloadSubmission({{ $submission->id }})" class="mt-2 text-xs font-semibold text-blue-700"><i class="fas fa-paperclip mr-1"></i>{{ $submission->attachment_name }}</button>@endif<button class="mt-4 w-full rounded-xl bg-red-600 px-5 py-3 text-sm font-bold text-white hover:bg-red-700">{{ $submission ? 'Resubmit work' : 'Submit assignment' }}</button>@if($submission?->graded_at)<div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4"><p class="font-bold text-emerald-800">Score: {{ $submission->score + 0 }}{{ $selectedAssignment->max_score ? '/'.($selectedAssignment->max_score + 0) : '' }}</p>@if($submission->feedback)<p class="mt-2 text-sm text-emerald-800">{{ $submission->feedback }}</p>@endif</div>@endif</form>
                @elseif($canManage)
                    <div class="mt-6 border-t border-slate-200 pt-5"><h3 class="font-bold text-slate-900">Student submissions</h3><div class="mt-3 space-y-3">@forelse($selectedAssignment->submissions as $submission)<div class="rounded-xl border border-slate-200 p-3"><div class="flex items-center justify-between gap-2"><div><p class="text-sm font-bold text-slate-800">{{ $submission->studentRecord?->user?->name }}</p><p class="text-xs text-slate-500">{{ $submission->submitted_at->format('d M Y · g:i A') }} · {{ ucfirst($submission->status) }}</p></div><button wire:click="beginGrading({{ $submission->id }})" class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-bold text-white">Grade</button></div>@if($submission->response_text)<p class="mt-3 whitespace-pre-line text-sm text-slate-600">{{ $submission->response_text }}</p>@endif @if($submission->attachment_path)<button wire:click="downloadSubmission({{ $submission->id }})" class="mt-2 text-xs font-semibold text-blue-700"><i class="fas fa-download mr-1"></i>{{ $submission->attachment_name }}</button>@endif @if($gradingSubmissionId === $submission->id)<div class="mt-4 grid gap-3 border-t border-slate-100 pt-4"><input type="number" min="0" step="0.01" wire:model="gradeScore" class="rounded-xl border-slate-300 text-sm" placeholder="Score"><textarea rows="3" wire:model="gradeFeedback" class="rounded-xl border-slate-300 text-sm" placeholder="Feedback for the student"></textarea>@error('gradeScore')<p class="text-xs text-red-600">{{ $message }}</p>@enderror<button wire:click="saveGrade" class="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white">Save grade</button></div>@elseif($submission->graded_at)<p class="mt-3 rounded-lg bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-800">Score: {{ $submission->score + 0 }}{{ $selectedAssignment->max_score ? '/'.($selectedAssignment->max_score + 0) : '' }}{{ $submission->feedback ? ' · '.$submission->feedback : '' }}</p>@endif</div>@empty<p class="rounded-xl bg-slate-50 p-4 text-center text-sm text-slate-500">No submissions yet.</p>@endforelse</div></div>
                @elseif($isParent)
                    <div class="mt-6 border-t border-slate-200 pt-5"><h3 class="font-bold text-slate-900">Children's progress</h3><div class="mt-3 space-y-2">@foreach($selectedAssignment->recipients->whereIn('id', $childRecords->keys()) as $child)<div class="rounded-xl bg-slate-50 p-3"><p class="text-sm font-bold text-slate-800">{{ $child->user?->name }}</p>@php $childSubmission = $selectedAssignment->submissions->firstWhere('student_record_id', $child->id); @endphp @if($childSubmission)<p class="mt-1 text-xs font-semibold text-emerald-700">{{ ucfirst($childSubmission->status) }} {{ $childSubmission->submitted_at?->format('d M Y') }}</p>@if($childSubmission->graded_at)<p class="mt-1 text-xs text-slate-600">Score: {{ $childSubmission->score + 0 }}{{ $selectedAssignment->max_score ? '/'.($selectedAssignment->max_score + 0) : '' }}</p>@endif @else<p class="mt-1 text-xs font-semibold text-amber-700">Not submitted</p>@endif</div>@endforeach</div></div>
                @endif
            @else
                <div class="py-16 text-center"><div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"><i class="fas fa-arrow-left"></i></div><p class="mt-4 font-semibold text-slate-700">Select an assignment</p><p class="mt-1 text-sm text-slate-500">Details and available actions will appear here.</p></div>
            @endif
        </aside>
    </div>
</div>
