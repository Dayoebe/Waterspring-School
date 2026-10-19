<div class="mt-5 space-y-5">
    <label class="block text-sm font-bold text-slate-800">
        Title
        <input wire:model="editableMaterial.title" class="mt-2 w-full rounded-xl border-slate-300 text-base font-semibold" />
    </label>

    @if(array_key_exists('summary', $editableMaterial))
        <label class="block text-sm font-bold text-slate-800">
            Lesson summary
            <textarea wire:model="editableMaterial.summary" rows="4" class="mt-2 w-full rounded-xl border-slate-300 text-sm leading-6"></textarea>
        </label>
    @endif

    @if(!empty($editableMaterial['objectives']))
        <fieldset class="rounded-2xl border border-slate-200 p-4">
            <legend class="px-2 font-black text-slate-900">Learning objectives</legend>
            <div class="mt-2 space-y-3">
                @foreach($editableMaterial['objectives'] as $index => $objective)
                    <label class="flex gap-3 text-sm text-slate-600">
                        <span class="mt-3 flex size-6 shrink-0 items-center justify-center rounded-full bg-sky-100 text-xs font-bold text-sky-800">{{ $index + 1 }}</span>
                        <textarea wire:model="editableMaterial.objectives.{{ $index }}" rows="2" class="w-full rounded-xl border-slate-300 text-sm leading-6"></textarea>
                    </label>
                @endforeach
            </div>
        </fieldset>
    @endif

    @if(!empty($editableMaterial['sections']))
        <div>
            <h3 class="font-black text-slate-900">Lesson content</h3>
            <div class="mt-3 space-y-4">
                @foreach($editableMaterial['sections'] as $index => $section)
                    <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <label class="block text-xs font-bold uppercase tracking-wider text-sky-700">Section heading
                            <input wire:model="editableMaterial.sections.{{ $index }}.heading" class="mt-2 w-full rounded-xl border-slate-300 bg-white text-base font-bold normal-case tracking-normal text-slate-900" />
                        </label>
                        <label class="mt-3 block text-sm font-bold text-slate-700">Teaching notes
                            <textarea wire:model="editableMaterial.sections.{{ $index }}.content" rows="5" class="mt-2 w-full rounded-xl border-slate-300 bg-white text-sm font-normal leading-6"></textarea>
                        </label>
                    </article>
                @endforeach
            </div>
        </div>
    @endif

    @foreach(['activities' => 'Class activities', 'discussion_questions' => 'Discussion questions', 'homework' => 'Homework', 'revision' => 'Revision notes'] as $key => $heading)
        @if(!empty($editableMaterial[$key]) && is_array($editableMaterial[$key]))
            <fieldset class="rounded-2xl border border-slate-200 p-4">
                <legend class="px-2 font-black text-slate-900">{{ $heading }}</legend>
                <div class="mt-2 space-y-3">
                    @foreach($editableMaterial[$key] as $index => $item)
                        @if(is_string($item))
                            <label class="flex gap-3">
                                <span class="mt-3 text-sm font-bold text-slate-400">{{ $index + 1 }}.</span>
                                <textarea wire:model="editableMaterial.{{ $key }}.{{ $index }}" rows="2" class="w-full rounded-xl border-slate-300 text-sm leading-6"></textarea>
                            </label>
                        @endif
                    @endforeach
                </div>
            </fieldset>
        @endif
    @endforeach

    @if(array_key_exists('instructions', $editableMaterial))
        <label class="block text-sm font-bold text-slate-800">Instructions
            <textarea wire:model="editableMaterial.instructions" rows="4" class="mt-2 w-full rounded-xl border-slate-300 text-sm leading-6"></textarea>
        </label>
    @endif

    @if(!empty($editableMaterial['questions']))
        <div>
            <h3 class="font-black text-slate-900">Questions</h3>
            <div class="mt-3 space-y-4">
                @foreach($editableMaterial['questions'] as $index => $question)
                    <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="flex items-center justify-between gap-3"><h4 class="font-black text-slate-900">Question {{ $index + 1 }}</h4>@if(isset($question['marks']))<label class="text-xs font-bold text-slate-600">Marks <input wire:model="editableMaterial.questions.{{ $index }}.marks" type="number" min="1" class="ml-2 w-20 rounded-lg border-slate-300 bg-white" /></label>@endif</div>
                        <label class="mt-3 block text-sm font-bold text-slate-700">Question
                            <textarea wire:model="editableMaterial.questions.{{ $index }}.prompt" rows="3" class="mt-2 w-full rounded-xl border-slate-300 bg-white text-sm font-normal leading-6"></textarea>
                        </label>
                        @if(!empty($question['options']) && is_array($question['options']))
                            <div class="mt-3 space-y-2"><p class="text-sm font-bold text-slate-700">Answer options</p>@foreach($question['options'] as $optionIndex => $option)<input wire:model="editableMaterial.questions.{{ $index }}.options.{{ $optionIndex }}" class="w-full rounded-xl border-slate-300 bg-white text-sm" />@endforeach</div>
                        @endif
                        @if(array_key_exists('answer', $question))<label class="mt-3 block text-sm font-bold text-emerald-800">Answer<textarea wire:model="editableMaterial.questions.{{ $index }}.answer" rows="2" class="mt-2 w-full rounded-xl border-emerald-200 bg-emerald-50 text-sm font-normal leading-6"></textarea></label>@endif
                        @if(array_key_exists('marking_guide', $question))<label class="mt-3 block text-sm font-bold text-violet-800">Marking guide<textarea wire:model="editableMaterial.questions.{{ $index }}.marking_guide" rows="2" class="mt-2 w-full rounded-xl border-violet-200 bg-violet-50 text-sm font-normal leading-6"></textarea></label>@endif
                    </article>
                @endforeach
            </div>
        </div>
    @endif

    @if(array_key_exists('total_marks', $editableMaterial))
        <label class="block text-sm font-bold text-slate-800">Total marks
            <input wire:model="editableMaterial.total_marks" type="number" min="1" class="mt-2 block w-32 rounded-xl border-slate-300" />
        </label>
    @endif
</div>
