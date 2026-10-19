<div class="space-y-6">
    <section class="overflow-hidden rounded-3xl border border-sky-100 bg-white shadow-sm">
        <div class="bg-gradient-to-br from-sky-950 via-sky-800 to-cyan-700 px-6 py-8 text-white sm:px-8">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <p class="text-xs font-bold uppercase tracking-[0.24em] text-cyan-200">Watersprings teaching & learning</p>
                    <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">ClassPilot AI</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-sky-100">
                        @if(auth()->user()->hasRole('student')) A guided study partner that uses your approved school material when it is available.
                        @elseif(auth()->user()->hasRole('parent') && !auth()->user()->hasAnyRole(['teacher','principal','admin','super-admin','super_admin'])) Clear, authorised support for understanding your child’s learning and helping at home.
                        @else Create curriculum-aware teaching drafts, review every detail, and approve only when ready. @endif
                    </p>
                </div>
                <div class="rounded-2xl border border-white/20 bg-white/10 px-4 py-3 text-sm backdrop-blur">
                    <span class="block text-xs font-bold uppercase tracking-wider text-cyan-200">AI status</span>
                    <span class="mt-1 block font-semibold">{{ config('class-pilot.enabled') && config('class-pilot.api_key') ? 'Ready' : 'Setup required' }}</span>
                </div>
            </div>
        </div>

        <nav class="flex gap-2 overflow-x-auto border-b border-slate-200 px-4 py-3" aria-label="ClassPilot sections">
            @if(auth()->user()->hasAnyRole(['teacher','principal','admin','super-admin','super_admin']))
                @foreach(['lesson' => 'Lesson & assessment', 'materials' => 'Saved materials', 'insights' => 'Class insights'] as $key => $label)
                    <button wire:click="switchTab('{{ $key }}')" class="whitespace-nowrap rounded-xl px-4 py-2 text-sm font-bold {{ $tab === $key ? 'bg-sky-700 text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ $label }}</button>
                @endforeach
            @elseif(auth()->user()->hasRole('student'))
                @foreach(['tutor' => 'AI tutor', 'practice' => 'Practice', 'progress' => 'Learning progress', 'sessions' => 'Saved sessions'] as $key => $label)
                    <button wire:click="switchTab('{{ $key }}')" class="whitespace-nowrap rounded-xl px-4 py-2 text-sm font-bold {{ $tab === $key ? 'bg-sky-700 text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ $label }}</button>
                @endforeach
            @else
                @foreach(['family' => 'Family assistant', 'progress' => 'Child progress', 'weekly' => 'Weekly summary', 'sessions' => 'Saved sessions'] as $key => $label)
                    <button wire:click="switchTab('{{ $key }}')" class="whitespace-nowrap rounded-xl px-4 py-2 text-sm font-bold {{ $tab === $key ? 'bg-sky-700 text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ $label }}</button>
                @endforeach
            @endif
        </nav>
    </section>

    @if(!config('class-pilot.api_key'))
        <div class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950">
            <strong>AI generation is not configured.</strong> An administrator needs to add <code>{{ config('class-pilot.provider') === 'groq' ? 'GROQ_API_KEY' : 'GEMINI_API_KEY' }}</code> on the server. Your existing records remain available and ClassPilot will not create pretend responses.
        </div>
    @endif
    @if($error)<div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-800" role="alert">{{ $error }}</div>@endif
    @if($notice)<div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-800" role="status">{{ $notice }}</div>@endif

    @if($tab === 'lesson')
        <div class="grid gap-6 xl:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)]">
            <form wire:submit="generateTeacher" class="space-y-5 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div><p class="text-xs font-bold uppercase tracking-wider text-sky-700">Teacher workspace</p><h2 class="mt-1 text-xl font-black text-slate-900">Prepare an editable draft</h2></div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="text-sm font-semibold text-slate-700">Class<select wire:model.live="classId" class="mt-2 w-full rounded-xl border-slate-300"><option value="">Choose class</option>@foreach($classes as $class)<option value="{{ $class->id }}">{{ $class->name }}</option>@endforeach</select></label>
                    <label class="text-sm font-semibold text-slate-700">Subject<select wire:model="subjectId" class="mt-2 w-full rounded-xl border-slate-300"><option value="">Choose subject</option>@foreach($subjects as $subject)<option value="{{ $subject->id }}">{{ $subject->name }}</option>@endforeach</select></label>
                    <label class="text-sm font-semibold text-slate-700">Draft type<select wire:model="materialType" class="mt-2 w-full rounded-xl border-slate-300"><option value="lesson">Lesson material</option><option value="assessment">Assessment</option></select></label>
                    <label class="text-sm font-semibold text-slate-700">Topic<input wire:model="topic" class="mt-2 w-full rounded-xl border-slate-300" placeholder="e.g. Fractions" /></label>
                    <label class="text-sm font-semibold text-slate-700">Subtopic<input wire:model="subtopic" class="mt-2 w-full rounded-xl border-slate-300" placeholder="Optional" /></label>
                    <label class="text-sm font-semibold text-slate-700">Duration (minutes)<input wire:model="duration" type="number" min="10" max="240" class="mt-2 w-full rounded-xl border-slate-300" /></label>
                </div>
                <label class="block text-sm font-semibold text-slate-700">Learning objectives<textarea wire:model="objectives" rows="3" class="mt-2 w-full rounded-xl border-slate-300" placeholder="One objective per line"></textarea></label>
                <div x-data="{ assessment: @entangle('materialType') }" x-show="assessment === 'assessment'" class="grid gap-4 rounded-2xl bg-slate-50 p-4 sm:grid-cols-3">
                    <label class="text-sm font-semibold">Questions<input wire:model="questionCount" type="number" min="1" max="30" class="mt-2 w-full rounded-xl border-slate-300" /></label>
                    <label class="text-sm font-semibold">Total marks<input wire:model="totalMarks" type="number" min="1" max="200" class="mt-2 w-full rounded-xl border-slate-300" /></label>
                    <label class="text-sm font-semibold">Format<select wire:model="questionFormat" class="mt-2 w-full rounded-xl border-slate-300"><option value="mixed">Mixed</option><option value="multiple_choice">Multiple choice</option><option value="short_answer">Short answer</option><option value="structured">Structured</option><option value="higher_order">Higher-order</option><option value="practical">Practical</option></select></label>
                </div>
                @if($errors->any())<div class="rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
                <button class="w-full rounded-xl bg-sky-700 px-5 py-3 font-bold text-white hover:bg-sky-800 disabled:opacity-60" wire:loading.attr="disabled"><span wire:loading.remove>Generate and save draft</span><span wire:loading>ClassPilot is preparing your draft…</span></button>
            </form>
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                @if($selectedMaterialId)
                    <div class="flex flex-wrap items-center justify-between gap-3"><div><p class="text-xs font-bold uppercase tracking-wider text-amber-600">AI-generated draft</p><h2 class="mt-1 text-xl font-black text-slate-900">Teacher review required</h2></div><span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-800">Not published</span></div>
                    <p class="mt-3 text-sm text-slate-600">Edit the structured content below. Approval does not publish an exam, answer key, or assignment.</p>
                    <textarea wire:model="editableContent" rows="24" class="mt-5 w-full rounded-2xl border-slate-300 font-mono text-xs leading-5"></textarea>
                    @error('editableContent')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                    <div class="mt-4 flex flex-wrap gap-3"><button wire:click="saveMaterial" class="rounded-xl border border-sky-700 px-4 py-2 text-sm font-bold text-sky-800">Save edits</button><button wire:click="approveMaterial" wire:confirm="Approve this reviewed draft? It will still not be published as an official assignment or exam." class="rounded-xl bg-emerald-700 px-4 py-2 text-sm font-bold text-white">Approve reviewed draft</button></div>
                    <div class="mt-5 rounded-2xl bg-slate-50 p-4 text-sm text-slate-600"><strong class="text-slate-900">Curriculum grounding:</strong> {{ count($sourceSummary) ? collect($sourceSummary)->pluck('title')->join(', ') : 'No approved school curriculum source was available; general AI knowledge must be reviewed carefully.' }}</div>
                @else
                    <div class="flex min-h-[28rem] flex-col items-center justify-center text-center"><div class="flex size-16 items-center justify-center rounded-2xl bg-sky-100 text-2xl text-sky-800"><i class="fas fa-file-pen"></i></div><h2 class="mt-5 text-xl font-black text-slate-900">Your draft will appear here</h2><p class="mt-2 max-w-md text-sm leading-6 text-slate-600">ClassPilot saves generated teaching material as a private draft. Nothing reaches students until you use an authorised publication workflow.</p></div>
                @endif
            </section>
        </div>
    @elseif($tab === 'materials')
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"><h2 class="text-xl font-black text-slate-900">Saved teaching materials</h2><div class="mt-5 grid gap-4 lg:grid-cols-2">@forelse($materials as $material)<article class="rounded-2xl border border-slate-200 p-5"><div class="flex items-start justify-between gap-3"><div><p class="text-xs font-bold uppercase text-sky-700">{{ $material->type }}</p><h3 class="mt-1 font-black text-slate-900">{{ $material->title }}</h3><p class="mt-2 text-sm text-slate-600">{{ $material->schoolClass?->name }} · {{ $material->subject?->name }} · {{ $material->topic }}</p></div><span class="rounded-full px-3 py-1 text-xs font-bold {{ $material->status === 'approved' ? 'bg-emerald-100 text-emerald-800' : ($material->status === 'archived' ? 'bg-slate-100 text-slate-600' : 'bg-amber-100 text-amber-800') }}">{{ ucfirst($material->status) }}</span></div>@if($material->status !== 'archived')<button wire:click="archiveMaterial({{ $material->id }})" wire:confirm="Archive this material?" class="mt-4 text-sm font-bold text-slate-600 hover:text-red-700">Archive</button>@endif</article>@empty<p class="text-sm text-slate-600">No materials have been generated yet.</p>@endforelse</div></section>
    @elseif($tab === 'tutor')
        <div class="grid gap-6 lg:grid-cols-[0.8fr_1.2fr]">
            <form wire:submit="askTutor" class="space-y-5 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"><div><p class="text-xs font-bold uppercase tracking-wider text-sky-700">Guided learning</p><h2 class="mt-1 text-xl font-black">Ask ClassPilot</h2><p class="mt-2 text-sm text-slate-600">Ask for a hint, simpler explanation, worked example, or a similar question.</p></div><label class="block text-sm font-semibold">Subject<select wire:model="subjectId" class="mt-2 w-full rounded-xl border-slate-300"><option value="">Choose subject</option>@foreach($subjects as $subject)<option value="{{ $subject->id }}">{{ $subject->name }}</option>@endforeach</select></label><label class="block text-sm font-semibold">Your question<textarea wire:model="question" rows="7" class="mt-2 w-full rounded-xl border-slate-300" placeholder="What would you like help understanding?"></textarea></label>@if($errors->any())<p class="text-sm text-red-700">{{ $errors->first() }}</p>@endif<button class="w-full rounded-xl bg-sky-700 px-5 py-3 font-bold text-white" wire:loading.attr="disabled"><span wire:loading.remove>Help me understand</span><span wire:loading>Thinking it through…</span></button></form>
            @include('livewire.class-pilot.partials.response')
        </div>
    @elseif($tab === 'practice')
        <div class="grid gap-6 lg:grid-cols-[0.8fr_1.2fr]"><form wire:submit="generatePractice" class="space-y-5 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"><h2 class="text-xl font-black">Build a practice set</h2><label class="block text-sm font-semibold">Subject<select wire:model="subjectId" class="mt-2 w-full rounded-xl border-slate-300"><option value="">Choose subject</option>@foreach($subjects as $subject)<option value="{{ $subject->id }}">{{ $subject->name }}</option>@endforeach</select></label><label class="block text-sm font-semibold">Topic<input wire:model="topic" class="mt-2 w-full rounded-xl border-slate-300" /></label><label class="block text-sm font-semibold">Questions<input wire:model="questionCount" type="number" min="1" max="15" class="mt-2 w-full rounded-xl border-slate-300" /></label><button class="w-full rounded-xl bg-sky-700 px-5 py-3 font-bold text-white">Create practice</button></form>@include('livewire.class-pilot.partials.response')</div>
    @elseif($tab === 'family')
        <div class="grid gap-6 lg:grid-cols-[0.85fr_1.15fr]">
            <form wire:submit="askFamily" class="space-y-5 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"><div><p class="text-xs font-bold uppercase tracking-wider text-sky-700">Family learning support</p><h2 class="mt-1 text-xl font-black">Ask about your child’s learning</h2></div><label class="block text-sm font-semibold">Child<select wire:model.live="studentRecordId" class="mt-2 w-full rounded-xl border-slate-300"><option value="">Choose child</option>@foreach($children as $child)<option value="{{ $child->studentRecord?->id }}">{{ $child->name }}</option>@endforeach</select></label><label class="block text-sm font-semibold">Subject (optional)<select wire:model="subjectId" class="mt-2 w-full rounded-xl border-slate-300"><option value="">General support</option>@foreach($subjects as $subject)<option value="{{ $subject->id }}">{{ $subject->name }}</option>@endforeach</select></label><label class="block text-sm font-semibold">Published result (optional)<select wire:model="resultId" class="mt-2 w-full rounded-xl border-slate-300"><option value="">No result selected</option>@foreach($results as $result)<option value="{{ $result->id }}">{{ $result->subject?->name }} — {{ $result->academicYear?->name }} / {{ $result->semester?->name }} ({{ $result->total_score }})</option>@endforeach</select></label><label class="block text-sm font-semibold">How can ClassPilot help?<textarea wire:model="question" rows="6" class="mt-2 w-full rounded-xl border-slate-300" placeholder="Explain this published result, suggest home practice, or help me prepare questions for the teacher."></textarea></label>@if($errors->any())<p class="text-sm text-red-700">{{ $errors->first() }}</p>@endif<button class="w-full rounded-xl bg-sky-700 px-5 py-3 font-bold text-white">Create family guidance</button><p class="text-xs leading-5 text-slate-500">Only linked children and published results can be used. Suggestions are not official assignments or teacher statements.</p></form>
            @include('livewire.class-pilot.partials.response')
        </div>
    @elseif(in_array($tab, ['progress','insights','weekly'], true))
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center gap-4"><div class="flex size-12 items-center justify-center rounded-2xl bg-cyan-100 text-cyan-800"><i class="fas fa-chart-line"></i></div><div><p class="text-xs font-bold uppercase tracking-wider text-cyan-700">Recorded evidence only</p><h2 class="text-xl font-black text-slate-900">{{ $tab === 'weekly' ? 'Family learning summary' : ($tab === 'insights' ? 'Class learning evidence' : 'Learning progress') }}</h2></div></div>
            @if(!empty($familyOverview))
                <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <article class="rounded-2xl bg-emerald-50 p-4"><p class="text-xs font-bold uppercase text-emerald-700">Attendance · 30 days</p><p class="mt-2 text-sm text-emerald-950">{{ $familyOverview['attendance']->map(fn($count, $status) => ucfirst($status).': '.$count)->join(' · ') ?: 'No recorded sessions' }}</p></article>
                    <article class="rounded-2xl bg-sky-50 p-4"><p class="text-xs font-bold uppercase text-sky-700">Upcoming assignments</p><ul class="mt-2 space-y-1 text-sm text-sky-950">@forelse($familyOverview['assignments'] as $item)<li>{{ $item->title }} · {{ $item->due_at->format('d M') }}</li>@empty<li>None recorded</li>@endforelse</ul></article>
                    <article class="rounded-2xl bg-amber-50 p-4"><p class="text-xs font-bold uppercase text-amber-700">School events</p><ul class="mt-2 space-y-1 text-sm text-amber-950">@forelse($familyOverview['events'] as $item)<li>{{ $item->title }} · {{ $item->starts_at->format('d M') }}</li>@empty<li>None recorded</li>@endforelse</ul></article>
                    <article class="rounded-2xl bg-violet-50 p-4"><p class="text-xs font-bold uppercase text-violet-700">Current notices</p><ul class="mt-2 space-y-1 text-sm text-violet-950">@forelse($familyOverview['notices'] as $item)<li>{{ $item->title }}</li>@empty<li>None recorded</li>@endforelse</ul></article>
                </div>
            @endif
            <div class="mt-6 grid gap-4 md:grid-cols-2">@forelse($attempts as $attempt)<article class="rounded-2xl bg-slate-50 p-4"><p class="font-bold text-slate-900">{{ $attempt->subject?->name ?? 'General learning' }}</p><p class="mt-1 text-sm text-slate-600">{{ $attempt->topic ?: str_replace('_',' ',ucfirst($attempt->activity_type)) }} · {{ $attempt->created_at->diffForHumans() }}</p><p class="mt-2 text-xs font-semibold text-slate-500">Informal practice — does not affect official results</p></article>@empty<div class="rounded-2xl bg-slate-50 p-5 text-sm text-slate-600">There is not enough ClassPilot learning evidence yet. Complete practice or learning sessions to build this view; no performance claims will be invented.</div>@endforelse</div>
        </section>
    @elseif($tab === 'sessions')
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"><h2 class="text-xl font-black text-slate-900">Saved learning sessions</h2><div class="mt-5 space-y-4">@forelse($sessions as $session)<details class="rounded-2xl border border-slate-200 p-4"><summary class="cursor-pointer font-bold text-slate-900">{{ $session->title }} <span class="ml-2 text-xs font-medium text-slate-500">{{ $session->last_activity_at?->diffForHumans() }}</span></summary><div class="mt-4 space-y-3">@foreach($session->messages as $message)<div class="rounded-xl p-3 text-sm {{ $message->role === 'user' ? 'bg-sky-50 text-sky-950' : 'bg-slate-50 text-slate-700' }}"><strong>{{ $message->role === 'user' ? 'You' : 'ClassPilot' }}:</strong> {{ $message->content }}</div>@endforeach</div></details>@empty<p class="text-sm text-slate-600">No saved sessions yet.</p>@endforelse</div></section>
    @endif
</div>
