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
                    <p class="mt-3 text-sm text-slate-600">Review and edit the draft using the plain-language fields below. Approval does not publish an exam, answer key, or assignment.</p>
                    @include('livewire.class-pilot.partials.material-editor')
                    @error('editableMaterial.title')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                    <div class="mt-4 flex flex-wrap gap-3"><button wire:click="saveMaterial" class="rounded-xl border border-sky-700 px-4 py-2 text-sm font-bold text-sky-800">Save edits</button><button wire:click="approveMaterial" wire:confirm="Approve this reviewed draft? It will still not be published as an official assignment or exam." class="rounded-xl bg-emerald-700 px-4 py-2 text-sm font-bold text-white">Approve reviewed draft</button></div>
                    <div class="mt-5 rounded-2xl bg-slate-50 p-4 text-sm text-slate-600"><strong class="text-slate-900">Curriculum grounding:</strong> {{ count($sourceSummary) ? collect($sourceSummary)->pluck('title')->join(', ') : 'No approved school curriculum source was available; general AI knowledge must be reviewed carefully.' }}</div>
                @else
                    <div class="flex min-h-[28rem] flex-col items-center justify-center text-center"><div class="flex size-16 items-center justify-center rounded-2xl bg-sky-100 text-2xl text-sky-800"><i class="fas fa-file-pen"></i></div><h2 class="mt-5 text-xl font-black text-slate-900">Your draft will appear here</h2><p class="mt-2 max-w-md text-sm leading-6 text-slate-600">ClassPilot saves generated teaching material as a private draft. Nothing reaches students until you use an authorised publication workflow.</p></div>
                @endif
            </section>
        </div>
    @elseif($tab === 'materials')
        @php($openMaterial = $materials->firstWhere('id', $selectedMaterialId))
        <div class="grid gap-6 xl:grid-cols-[minmax(18rem,0.72fr)_minmax(0,1.28fr)]">
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-start justify-between gap-3"><div><p class="text-xs font-bold uppercase tracking-wider text-sky-700">Your library</p><h2 class="mt-1 text-xl font-black text-slate-900">Saved teaching materials</h2><p class="mt-2 text-sm text-slate-600">{{ $materials->count() }} {{ Str::plural('material', $materials->count()) }} shown</p></div><button type="button" wire:click="startNewMaterial" class="shrink-0 rounded-xl bg-sky-700 px-3 py-2 text-sm font-bold text-white hover:bg-sky-800"><i class="fas fa-plus mr-1"></i> New</button></div>
                <div class="mt-5 space-y-3 rounded-2xl bg-slate-50 p-4">
                    <label class="relative block"><span class="sr-only">Search materials</span><i class="fas fa-search absolute left-3 top-3.5 text-sm text-slate-400"></i><input wire:model.live.debounce.300ms="materialsSearch" class="w-full rounded-xl border-slate-300 bg-white pl-9 text-sm" placeholder="Search title or topic…" /></label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="text-xs font-bold text-slate-600">Status<select wire:model.live="materialsStatus" class="mt-1 w-full rounded-xl border-slate-300 bg-white text-sm font-normal"><option value="all">All statuses</option><option value="draft">Draft</option><option value="approved">Approved</option><option value="archived">Archived</option></select></label>
                        <label class="text-xs font-bold text-slate-600">Type<select wire:model.live="materialsType" class="mt-1 w-full rounded-xl border-slate-300 bg-white text-sm font-normal"><option value="all">All types</option><option value="lesson">Lessons</option><option value="assessment">Assessments</option></select></label>
                    </div>
                    <label class="block text-xs font-bold text-slate-600">Sort by<select wire:model.live="materialsSort" class="mt-1 w-full rounded-xl border-slate-300 bg-white text-sm font-normal"><option value="newest">Newest first</option><option value="oldest">Oldest first</option><option value="title_asc">Title A–Z</option><option value="title_desc">Title Z–A</option><option value="status">Status</option></select></label>
                </div>
                <div class="mt-5 space-y-3">
                    @forelse($materials as $material)
                        <article class="rounded-2xl border p-4 transition {{ $selectedMaterialId === $material->id ? 'border-sky-500 bg-sky-50 ring-2 ring-sky-100' : 'border-slate-200 hover:border-sky-300' }}">
                            <button type="button" wire:click="openMaterial({{ $material->id }})" class="w-full text-left">
                                <div class="flex items-start justify-between gap-3"><div><p class="text-xs font-bold uppercase text-sky-700">{{ $material->type }} @if($material->source_material_id) · revised copy @endif</p><h3 class="mt-1 font-black text-slate-900">{{ $material->title }}</h3></div><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $material->status === 'approved' ? 'bg-emerald-100 text-emerald-800' : ($material->status === 'archived' ? 'bg-slate-100 text-slate-600' : 'bg-amber-100 text-amber-800') }}">{{ ucfirst($material->status) }}</span></div>
                                <p class="mt-2 text-sm text-slate-600">{{ $material->schoolClass?->name }} · {{ $material->subject?->name }}</p><p class="mt-1 text-xs text-slate-500">Saved {{ $material->updated_at->diffForHumans() }}</p>
                            </button>
                            <div class="mt-3 flex flex-wrap items-center gap-3 border-t border-slate-200 pt-3">
                                <button type="button" wire:click="openMaterial({{ $material->id }})" class="text-xs font-bold text-sky-700 hover:text-sky-900"><i class="fas fa-eye mr-1"></i> Open</button>
                                @if($material->status !== 'archived')<button type="button" wire:click="archiveMaterial({{ $material->id }})" wire:confirm="Archive this material?" class="text-xs font-bold text-slate-500 hover:text-amber-700"><i class="fas fa-box-archive mr-1"></i> Archive</button>@else<button type="button" wire:click="restoreMaterial({{ $material->id }})" class="text-xs font-bold text-emerald-700 hover:text-emerald-900"><i class="fas fa-rotate-left mr-1"></i> Restore</button>@endif
                                <button type="button" wire:click="deleteMaterial({{ $material->id }})" wire:confirm="Permanently delete this material? This cannot be undone." class="ml-auto text-xs font-bold text-red-600 hover:text-red-800"><i class="fas fa-trash mr-1"></i> Delete</button>
                            </div>
                        </article>
                    @empty
                        <p class="rounded-2xl bg-slate-50 p-5 text-sm text-slate-600">No materials have been generated yet.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                @if($openMaterial)
                    <div class="flex flex-wrap items-center justify-between gap-3"><div><p class="text-xs font-bold uppercase tracking-wider text-amber-600">Saved {{ $openMaterial->type }}</p><h2 class="mt-1 text-xl font-black text-slate-900">Read and edit material</h2></div><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">Material #{{ $openMaterial->id }}</span></div>
                    @include('livewire.class-pilot.partials.material-editor')
                    @error('editableMaterial.title')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                    <div class="mt-4 flex flex-wrap gap-3">
                        @if($openMaterial->status === 'draft')<button wire:click="saveMaterial" class="rounded-xl border border-sky-700 px-4 py-2 text-sm font-bold text-sky-800">Save edits</button>@endif
                        @if($openMaterial->status === 'draft')<button wire:click="approveMaterial" wire:confirm="Approve this reviewed draft?" class="rounded-xl bg-emerald-700 px-4 py-2 text-sm font-bold text-white">Approve reviewed draft</button>@endif
                    </div>
                    <form wire:submit="reviseMaterial" class="mt-6 rounded-2xl border border-violet-200 bg-violet-50 p-5">
                        <label class="block text-sm font-black text-violet-950">Ask ClassPilot to revise this material
                            <textarea wire:model="revisionPrompt" rows="3" class="mt-2 w-full rounded-xl border-violet-200 bg-white text-sm font-normal leading-6" placeholder="For example: Make the activities more practical, simplify the explanation, or add five discussion questions."></textarea>
                        </label>
                        @error('revisionPrompt')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                        <div class="mt-3 flex flex-wrap items-center justify-between gap-3"><p class="text-xs leading-5 text-violet-800">A new draft copy will be created. This original stays unchanged.</p><button class="rounded-xl bg-violet-700 px-4 py-2 text-sm font-bold text-white disabled:opacity-60" wire:loading.attr="disabled"><span wire:loading.remove>Create revised copy</span><span wire:loading>Revising…</span></button></div>
                    </form>
                    <div class="mt-5 rounded-2xl bg-slate-50 p-4 text-sm text-slate-600"><strong class="text-slate-900">Curriculum grounding:</strong> {{ count($sourceSummary) ? collect($sourceSummary)->pluck('title')->join(', ') : 'No approved school curriculum source was available; general AI knowledge must be reviewed carefully.' }}</div>
                @else
                    <div class="flex min-h-[32rem] flex-col items-center justify-center text-center"><div class="flex size-16 items-center justify-center rounded-2xl bg-sky-100 text-2xl text-sky-800"><i class="fas fa-folder-open"></i></div><h2 class="mt-5 text-xl font-black text-slate-900">Choose a saved material</h2><p class="mt-2 max-w-md text-sm leading-6 text-slate-600">Its complete content will open here in a readable, editable format.</p></div>
                @endif
            </section>
        </div>
    @elseif($tab === 'tutor')
        <div class="grid gap-6 lg:grid-cols-[0.8fr_1.2fr]">
            <section class="space-y-5 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"><div class="flex items-start justify-between gap-3"><div><p class="text-xs font-bold uppercase tracking-wider text-sky-700">Guided learning</p><h2 class="mt-1 text-xl font-black">Ask ClassPilot</h2><p class="mt-2 text-sm text-slate-600">Choose a subject, then chat in the message box. Every question is saved automatically.</p></div>@if($activeSessionId)<button type="button" wire:click="startNewConversation" class="shrink-0 rounded-xl border border-sky-200 px-3 py-2 text-xs font-bold text-sky-800">New chat</button>@endif</div><label class="block text-sm font-semibold">Subject<select wire:model="subjectId" class="mt-2 w-full rounded-xl border-slate-300"><option value="">Choose subject</option>@foreach($subjects as $subject)<option value="{{ $subject->id }}">{{ $subject->name }}</option>@endforeach</select></label><div class="rounded-2xl bg-sky-50 p-4 text-sm leading-6 text-sky-900"><i class="fas fa-lightbulb mr-2 text-amber-500"></i>Ask for an explanation, hint, worked example, revision help, or follow-up question.</div></section>
            @include('livewire.class-pilot.partials.conversation')
        </div>
    @elseif($tab === 'practice')
        <div class="grid gap-6 lg:grid-cols-[0.8fr_1.2fr]"><form wire:submit="generatePractice" class="space-y-5 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"><h2 class="text-xl font-black">Build a practice set</h2><label class="block text-sm font-semibold">Subject<select wire:model="subjectId" class="mt-2 w-full rounded-xl border-slate-300"><option value="">Choose subject</option>@foreach($subjects as $subject)<option value="{{ $subject->id }}">{{ $subject->name }}</option>@endforeach</select></label><label class="block text-sm font-semibold">Topic<input wire:model="topic" class="mt-2 w-full rounded-xl border-slate-300" /></label><label class="block text-sm font-semibold">Questions<input wire:model="questionCount" type="number" min="1" max="15" class="mt-2 w-full rounded-xl border-slate-300" /></label><button class="w-full rounded-xl bg-sky-700 px-5 py-3 font-bold text-white">Create practice</button></form>@include('livewire.class-pilot.partials.response')</div>
    @elseif($tab === 'family')
        <div class="grid gap-6 lg:grid-cols-[0.85fr_1.15fr]">
            <section class="space-y-5 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"><div class="flex items-start justify-between gap-3"><div><p class="text-xs font-bold uppercase tracking-wider text-sky-700">Family learning support</p><h2 class="mt-1 text-xl font-black">Ask about your child’s learning</h2><p class="mt-2 text-sm text-slate-600">Choose the learning context, then use the message box inside the chat.</p></div>@if($activeSessionId)<button type="button" wire:click="startNewConversation" class="shrink-0 rounded-xl border border-sky-200 px-3 py-2 text-xs font-bold text-sky-800">New chat</button>@endif</div><label class="block text-sm font-semibold">Child<select wire:model.live="studentRecordId" class="mt-2 w-full rounded-xl border-slate-300"><option value="">Choose child</option>@foreach($children as $child)<option value="{{ $child->studentRecord?->id }}">{{ $child->name }}</option>@endforeach</select></label><label class="block text-sm font-semibold">Subject (optional)<select wire:model="subjectId" class="mt-2 w-full rounded-xl border-slate-300"><option value="">General support</option>@foreach($subjects as $subject)<option value="{{ $subject->id }}">{{ $subject->name }}</option>@endforeach</select></label><label class="block text-sm font-semibold">Published result (optional)<select wire:model="resultId" class="mt-2 w-full rounded-xl border-slate-300"><option value="">No result selected</option>@foreach($results as $result)<option value="{{ $result->id }}">{{ $result->subject?->name }} — {{ $result->academicYear?->name }} / {{ $result->semester?->name }} ({{ $result->total_score }})</option>@endforeach</select></label><p class="text-xs leading-5 text-slate-500">Only linked children and published results can be used. Suggestions are not official assignments or teacher statements.</p></section>
            @include('livewire.class-pilot.partials.conversation')
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
            <div class="mt-6 grid gap-4 md:grid-cols-2">@forelse($attempts as $attempt)<article class="rounded-2xl border border-slate-200 bg-slate-50 p-4"><div class="flex items-start justify-between gap-3"><div><p class="font-bold text-slate-900">{{ $attempt->subject?->name ?? 'General learning' }}</p><p class="mt-1 text-sm text-slate-600">{{ $attempt->topic ?: str_replace('_',' ',ucfirst($attempt->activity_type)) }} · {{ $attempt->created_at->diffForHumans() }}</p></div><span class="rounded-full bg-emerald-100 px-2 py-1 text-xs font-bold text-emerald-800">Saved</span></div><p class="mt-2 text-xs font-semibold text-slate-500">Informal practice — does not affect official results</p>@if(auth()->user()->hasRole('student') && !auth()->user()->hasAnyRole(['teacher','principal','admin','super-admin','super_admin']))<button type="button" wire:click="continuePractice({{ $attempt->id }})" class="mt-4 rounded-xl bg-sky-700 px-4 py-2 text-sm font-bold text-white hover:bg-sky-800"><i class="fas fa-play mr-1"></i> Continue learning</button>@endif</article>@empty<div class="rounded-2xl bg-slate-50 p-5 text-sm text-slate-600">There is not enough ClassPilot learning evidence yet. Complete practice or learning sessions to build this view; no performance claims will be invented.</div>@endforelse</div>
        </section>
    @elseif($tab === 'sessions')
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"><div><p class="text-xs font-bold uppercase tracking-wider text-sky-700">Conversation history</p><h2 class="mt-1 text-xl font-black text-slate-900">Saved learning sessions</h2><p class="mt-2 text-sm text-slate-600">Open any conversation and continue asking questions with its previous context.</p></div><div class="mt-5 space-y-4">@forelse($sessions as $session)<article class="rounded-2xl border border-slate-200 p-4"><div class="flex flex-wrap items-start justify-between gap-3"><div><h3 class="font-bold text-slate-900">{{ $session->title }}</h3><p class="mt-1 text-xs text-slate-500">{{ $session->messages->count() }} messages · {{ $session->last_activity_at?->diffForHumans() }}</p></div><button type="button" wire:click="openLearningSession({{ $session->id }})" class="rounded-xl bg-sky-700 px-4 py-2 text-sm font-bold text-white">Open & continue</button></div><div class="mt-3 line-clamp-2 text-sm leading-6 text-slate-600">{{ $session->messages->last() ? Str::limit(strip_tags(Str::markdown($session->messages->last()->content, ['html_input' => 'strip', 'allow_unsafe_links' => false])), 180) : 'No messages yet.' }}</div></article>@empty<p class="rounded-2xl bg-slate-50 p-5 text-sm text-slate-600">No saved conversations yet. Your first question will be saved automatically.</p>@endforelse</div></section>
    @endif
</div>
