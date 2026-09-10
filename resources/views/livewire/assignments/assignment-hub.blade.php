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
                <div class="md:col-span-2 rounded-2xl border border-slate-200 bg-slate-50 p-5">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div><h3 class="font-bold text-slate-900">Structured questions</h3><p class="text-xs text-slate-500">Mix objective, short response and essay questions. Leave empty for a regular file or written assignment.</p></div>
                        @if($questions !== [])<span class="rounded-full bg-red-100 px-3 py-1 text-xs font-bold text-red-700">{{ count($questions) }} questions · {{ collect($questions)->sum('points') + 0 }} marks</span>@endif
                    </div>
                    @if($questions !== [])
                        <div class="mt-4 space-y-2">@foreach($questions as $index => $question)<div wire:key="draft-question-{{ $index }}" class="flex items-start justify-between gap-3 rounded-xl border border-slate-200 bg-white p-3"><div><p class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ $questionTypes[$question['type']] }} · {{ $question['points'] + 0 }} marks</p><p class="mt-1 text-sm font-semibold text-slate-800">{{ $index + 1 }}. {{ $question['prompt'] }}</p></div><button type="button" wire:click="removeQuestion({{ $index }})" class="text-red-500 hover:text-red-700" title="Remove question"><i class="fas fa-trash"></i></button></div>@endforeach</div>
                    @endif
                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <div><label class="mb-1 block text-xs font-bold text-slate-600">Question type</label><select wire:model.live="questionType" class="w-full rounded-xl border-slate-300 text-sm">@foreach($questionTypes as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                        <div><label class="mb-1 block text-xs font-bold text-slate-600">Marks</label><input type="number" min="0.01" step="0.01" wire:model="questionPoints" class="w-full rounded-xl border-slate-300 text-sm">@error('questionPoints')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                        <div class="md:col-span-2"><label class="mb-1 block text-xs font-bold text-slate-600">Question</label><textarea rows="3" wire:model="questionPrompt" class="w-full rounded-xl border-slate-300 text-sm" placeholder="Enter the question students will answer"></textarea>@error('questionPrompt')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                        @if(in_array($questionType, ['multiple_choice', 'multiple_select'], true))
                            <div class="md:col-span-2"><label class="mb-2 block text-xs font-bold text-slate-600">Options and correct answer</label><div class="grid gap-2 sm:grid-cols-2">@foreach($questionOptions as $optionIndex => $option)<label class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white p-2"><input type="{{ $questionType === 'multiple_choice' ? 'radio' : 'checkbox' }}" wire:model="questionCorrectAnswers" value="{{ $option }}" @disabled(blank($option)) class="border-slate-300 text-red-600 focus:ring-red-500"><input wire:model.live.debounce.300ms="questionOptions.{{ $optionIndex }}" class="min-w-0 flex-1 border-0 p-1 text-sm focus:ring-0" placeholder="Option {{ $optionIndex + 1 }}"></label>@endforeach</div>@error('questionOptions')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror @error('questionCorrectAnswers')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                        @elseif($questionType === 'true_false')
                            <div><label class="mb-1 block text-xs font-bold text-slate-600">Correct answer</label><select wire:model="questionCorrectAnswers.0" class="w-full rounded-xl border-slate-300 text-sm"><option value="">Select answer</option><option value="True">True</option><option value="False">False</option></select>@error('questionCorrectAnswers')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                        @elseif(in_array($questionType, ['fill_blank', 'short_answer'], true))
                            <div class="md:col-span-2"><label class="mb-1 block text-xs font-bold text-slate-600">Accepted answer(s) {{ $questionType === 'short_answer' ? '(optional for manual marking)' : '' }}</label><input wire:model="questionAcceptedAnswers" class="w-full rounded-xl border-slate-300 text-sm" placeholder="Separate alternative answers with |">@error('questionAcceptedAnswers')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                        @endif
                        <div class="md:col-span-2"><label class="mb-1 block text-xs font-bold text-slate-600">Answer explanation <span class="font-normal text-slate-400">(optional)</span></label><textarea rows="2" wire:model="questionExplanation" class="w-full rounded-xl border-slate-300 text-sm" placeholder="Shown with feedback after grading"></textarea></div>
                        <div class="flex items-center justify-between md:col-span-2"><label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700"><input type="checkbox" wire:model="questionRequired" class="rounded border-slate-300 text-red-600">Required question</label><button type="button" wire:click="addQuestion" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-800"><i class="fas fa-plus mr-2"></i>Add question</button></div>
                    </div>
                </div>
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
                            <div class="mt-4 flex flex-wrap items-center gap-4 text-xs text-slate-500"><span><i class="far fa-clock mr-1"></i>{{ $assignment->due_at->format('D, d M Y · g:i A') }}</span>@if($assignment->questions_count)<span><i class="fas fa-list-check mr-1"></i>{{ $assignment->questions_count }} questions</span>@endif @if($assignment->max_score)<span><i class="fas fa-star mr-1"></i>{{ $assignment->max_score + 0 }} marks</span>@endif @if($canManage)<span>{{ $assignment->submissions_count }}/{{ $assignment->recipients_count }} submitted</span>@elseif($ownSubmission)<span class="font-bold text-emerald-700">{{ str($ownSubmission->status)->replace('_', ' ')->title() }}</span>@endif</div>
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
                @if(!$isStudent && $selectedAssignment->questions->isNotEmpty())<div class="mt-5 space-y-2"><h3 class="font-bold text-slate-900">Questions</h3>@foreach($selectedAssignment->questions as $question)<div class="rounded-xl border border-slate-200 p-3"><p class="text-xs font-bold uppercase text-slate-500">{{ $loop->iteration }}. {{ $questionTypes[$question->question_type] }} · {{ $question->points + 0 }} marks</p><p class="mt-1 text-sm font-semibold text-slate-800">{{ $question->prompt }}</p>@if($canManage && $question->correct_answers)<p class="mt-2 text-xs text-emerald-700">Answer: {{ implode(', ', $question->correct_answers) }}</p>@endif</div>@endforeach</div>@endif

                @if($isStudent)
                    @php $submission = $selectedAssignment->submissions->firstWhere('student_record_id', auth()->user()->studentRecord?->id); @endphp
                    <form wire:submit="submitAssignment" class="mt-6 border-t border-slate-200 pt-5"><h3 class="font-bold text-slate-900">{{ $submission ? 'Update your submission' : 'Submit your work' }}</h3>
                        @if($selectedAssignment->questions->isNotEmpty())<div class="mt-4 space-y-5">@foreach($selectedAssignment->questions as $question)<div class="rounded-xl border border-slate-200 p-4"><p class="text-xs font-bold uppercase text-slate-500">Question {{ $loop->iteration }} · {{ $questionTypes[$question->question_type] }} · {{ $question->points + 0 }} marks</p><p class="mt-2 text-sm font-semibold leading-6 text-slate-900">{{ $question->prompt }} @if(!$question->is_required)<span class="text-xs font-normal text-slate-400">(optional)</span>@endif</p><div class="mt-3">@if(in_array($question->question_type, ['multiple_choice', 'true_false'], true))<div class="space-y-2">@foreach($question->options as $option)<label class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 p-3 text-sm hover:bg-slate-50"><input type="radio" wire:model="studentAnswers.{{ $question->id }}" value="{{ $option }}" class="text-red-600 focus:ring-red-500">{{ $option }}</label>@endforeach</div>@elseif($question->question_type === 'multiple_select')<div class="space-y-2">@foreach($question->options as $option)<label class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 p-3 text-sm hover:bg-slate-50"><input type="checkbox" wire:model="studentAnswers.{{ $question->id }}" value="{{ $option }}" class="rounded text-red-600 focus:ring-red-500">{{ $option }}</label>@endforeach</div>@elseif($question->question_type === 'long_essay')<textarea rows="7" wire:model="studentAnswers.{{ $question->id }}" class="w-full rounded-xl border-slate-300 text-sm" placeholder="Write your essay"></textarea>@else<input wire:model="studentAnswers.{{ $question->id }}" class="w-full rounded-xl border-slate-300 text-sm" placeholder="Enter your answer">@endif @error('studentAnswers.'.$question->id)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div></div>@endforeach</div>@endif
                        <textarea rows="4" wire:model="submissionText" class="mt-3 w-full rounded-xl border-slate-300 px-4 py-3 text-sm" placeholder="{{ $selectedAssignment->questions->isNotEmpty() ? 'Optional note for your teacher' : 'Write your answer or a note about the attached work.' }}"></textarea>@error('submissionText')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror<input type="file" wire:model="submissionFile" class="mt-3 block w-full rounded-xl border border-slate-300 px-3 py-2 text-xs">@error('submissionFile')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror @if($submission?->attachment_path)<button type="button" wire:click="downloadSubmission({{ $submission->id }})" class="mt-2 text-xs font-semibold text-blue-700"><i class="fas fa-paperclip mr-1"></i>{{ $submission->attachment_name }}</button>@endif<button class="mt-4 w-full rounded-xl bg-red-600 px-5 py-3 text-sm font-bold text-white hover:bg-red-700">{{ $submission ? 'Resubmit work' : 'Submit assignment' }}</button>@if($submission?->graded_at)<div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4"><p class="font-bold text-emerald-800">Score: {{ $submission->score + 0 }}{{ $selectedAssignment->max_score ? '/'.($selectedAssignment->max_score + 0) : '' }}</p>@if($submission->feedback)<p class="mt-2 text-sm text-emerald-800">{{ $submission->feedback }}</p>@endif</div>@endif</form>
                @elseif($canManage)
                    @if($gradingSubmissionId)
                        @php $gradingSubmission = $selectedAssignment->submissions->firstWhere('id', $gradingSubmissionId); @endphp
                        @if($gradingSubmission && $gradingSubmission->answers->isNotEmpty())
                            <div class="mt-6 rounded-2xl border border-blue-200 bg-blue-50 p-4">
                                <h3 class="font-bold text-blue-950">Mark {{ $gradingSubmission->studentRecord?->user?->name }}'s responses</h3>
                                <p class="mt-1 text-xs text-blue-700">Objective marks are already calculated. Enter marks for teacher-reviewed questions.</p>
                                <div class="mt-4 space-y-3">
                                    @foreach($gradingSubmission->answers->filter(fn($answer) => !$answer->question?->isAutomaticallyMarked()) as $answer)
                                        <div class="rounded-xl bg-white p-3">
                                            <p class="text-xs font-bold text-slate-500">Q{{ $answer->question?->position }} · {{ $answer->question?->points + 0 }} marks</p>
                                            <p class="mt-1 text-sm font-semibold text-slate-800">{{ $answer->question?->prompt }}</p>
                                            <p class="mt-2 whitespace-pre-line text-sm text-slate-600">{{ implode(', ', $answer->answer ?? []) ?: 'No answer' }}</p>
                                            <div class="mt-3 grid gap-2 sm:grid-cols-[120px_1fr]">
                                                <input type="number" min="0" max="{{ $answer->question?->points }}" step="0.01" wire:model="manualAnswerScores.{{ $answer->id }}" class="rounded-lg border-slate-300 text-sm" placeholder="Score">
                                                <input wire:model="manualAnswerFeedback.{{ $answer->id }}" class="rounded-lg border-slate-300 text-sm" placeholder="Question feedback (optional)">
                                            </div>
                                            @error('manualAnswerScores.'.$answer->id)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                        </div>
                                    @endforeach
                                    <textarea rows="3" wire:model="gradeFeedback" class="w-full rounded-xl border-blue-200 text-sm" placeholder="Overall feedback for the student"></textarea>
                                    <button type="button" wire:click="saveGrade" class="w-full rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white">Calculate total and save grade</button>
                                </div>
                            </div>
                        @endif
                    @endif
                    <div class="mt-6 border-t border-slate-200 pt-5"><h3 class="font-bold text-slate-900">Student submissions</h3><div class="mt-3 space-y-3">@forelse($selectedAssignment->submissions as $submission)<div class="rounded-xl border border-slate-200 p-3"><div class="flex items-center justify-between gap-2"><div><p class="text-sm font-bold text-slate-800">{{ $submission->studentRecord?->user?->name }}</p><p class="text-xs text-slate-500">{{ $submission->submitted_at->format('d M Y · g:i A') }} · {{ str($submission->status)->replace('_', ' ')->title() }}</p></div><button wire:click="beginGrading({{ $submission->id }})" class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-bold text-white">Grade</button></div>@if($submission->answers->isNotEmpty())<div class="mt-3 space-y-2">@foreach($submission->answers as $answer)<div class="rounded-lg bg-slate-50 p-3"><p class="text-xs font-semibold text-slate-500">Q{{ $answer->question?->position }}. {{ $answer->question?->prompt }}</p><p class="mt-1 text-sm text-slate-800">{{ implode(', ', $answer->answer ?? []) ?: 'No answer' }}</p>@if($answer->score !== null)<p class="mt-1 text-xs font-bold {{ $answer->is_correct ? 'text-emerald-700' : 'text-amber-700' }}">{{ $answer->score + 0 }}/{{ $answer->question?->points + 0 }} marks</p>@else<p class="mt-1 text-xs font-bold text-blue-700">Manual review required</p>@endif</div>@endforeach</div>@endif @if($submission->response_text)<p class="mt-3 whitespace-pre-line text-sm text-slate-600">{{ $submission->response_text }}</p>@endif @if($submission->attachment_path)<button wire:click="downloadSubmission({{ $submission->id }})" class="mt-2 text-xs font-semibold text-blue-700"><i class="fas fa-download mr-1"></i>{{ $submission->attachment_name }}</button>@endif @if($gradingSubmissionId === $submission->id)<div class="mt-4 grid gap-3 border-t border-slate-100 pt-4"><input type="number" min="0" step="0.01" wire:model="gradeScore" class="rounded-xl border-slate-300 text-sm" placeholder="Total score"><textarea rows="3" wire:model="gradeFeedback" class="rounded-xl border-slate-300 text-sm" placeholder="Feedback for the student"></textarea>@error('gradeScore')<p class="text-xs text-red-600">{{ $message }}</p>@enderror<button wire:click="saveGrade" class="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white">Save grade</button></div>@elseif($submission->graded_at)<p class="mt-3 rounded-lg bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-800">Score: {{ $submission->score + 0 }}{{ $selectedAssignment->max_score ? '/'.($selectedAssignment->max_score + 0) : '' }}{{ $submission->feedback ? ' · '.$submission->feedback : '' }}</p>@endif</div>@empty<p class="rounded-xl bg-slate-50 p-4 text-center text-sm text-slate-500">No submissions yet.</p>@endforelse</div></div>
                @elseif($isParent)
                    <div class="mt-6 border-t border-slate-200 pt-5"><h3 class="font-bold text-slate-900">Children's progress</h3><div class="mt-3 space-y-2">@foreach($selectedAssignment->recipients->whereIn('id', $childRecords->keys()) as $child)<div class="rounded-xl bg-slate-50 p-3"><p class="text-sm font-bold text-slate-800">{{ $child->user?->name }}</p>@php $childSubmission = $selectedAssignment->submissions->firstWhere('student_record_id', $child->id); @endphp @if($childSubmission)<p class="mt-1 text-xs font-semibold text-emerald-700">{{ ucfirst($childSubmission->status) }} {{ $childSubmission->submitted_at?->format('d M Y') }}</p>@if($childSubmission->graded_at)<p class="mt-1 text-xs text-slate-600">Score: {{ $childSubmission->score + 0 }}{{ $selectedAssignment->max_score ? '/'.($selectedAssignment->max_score + 0) : '' }}</p>@endif @else<p class="mt-1 text-xs font-semibold text-amber-700">Not submitted</p>@endif</div>@endforeach</div></div>
                @endif
            @else
                <div class="py-16 text-center"><div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"><i class="fas fa-arrow-left"></i></div><p class="mt-4 font-semibold text-slate-700">Select an assignment</p><p class="mt-1 text-sm text-slate-500">Details and available actions will appear here.</p></div>
            @endif
        </aside>
    </div>
</div>
