<div class="min-h-screen bg-slate-50 text-slate-900">
    <section class="relative overflow-hidden bg-sky-950 text-white">
        <div class="absolute inset-0 opacity-30" aria-hidden="true">
            <div class="absolute -left-20 top-4 h-72 w-72 rounded-full bg-cyan-400/30 blur-3xl"></div>
            <div class="absolute -right-20 bottom-0 h-80 w-80 rounded-full bg-amber-300/25 blur-3xl"></div>
        </div>
        <div class="relative mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 lg:grid-cols-[1.15fr_.85fr] lg:px-8 lg:py-20">
            <div>
                <p class="inline-flex items-center gap-2 rounded-full border border-cyan-300/30 bg-cyan-300/10 px-4 py-2 text-xs font-black uppercase tracking-[0.2em] text-cyan-100">
                    <i class="fas fa-sparkles" aria-hidden="true"></i> Public AI-powered learning
                </p>
                <h1 class="mt-6 text-4xl font-black leading-tight sm:text-5xl lg:text-6xl">Open Learning</h1>
                <p class="mt-4 text-xl font-bold text-amber-300">Learn. Explore. Understand.</p>
                <p class="mt-5 max-w-2xl text-lg leading-8 text-sky-100">Explore subjects, understand difficult concepts and discover the world beyond your classroom with your personal AI learning companion.</p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="#learning-workspace" class="rounded-xl bg-amber-400 px-6 py-3 font-black text-sky-950 shadow-lg shadow-amber-950/20 transition hover:bg-amber-300">Start Learning</a>
                    @if(!empty($state))
                        <button type="button" wire:click="showLesson" class="rounded-xl border border-white/40 px-6 py-3 font-bold text-white transition hover:bg-white/10">Continue current lesson</button>
                    @endif
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:self-center">
                @foreach([
                    ['fa-route', 'Progressive lessons', 'Learn one clear section at a time.'],
                    ['fa-comments', 'Ask as you learn', 'Get explanations tied to your topic.'],
                    ['fa-flask', 'Real-world discovery', 'Connect ideas to life and careers.'],
                    ['fa-list-check', 'Helpful assessment', 'Review answers with full corrections.'],
                ] as [$icon, $title, $text])
                    <article class="rounded-2xl border border-white/15 bg-white/10 p-5 backdrop-blur">
                        <i class="fas {{ $icon }} text-xl text-amber-300" aria-hidden="true"></i>
                        <h2 class="mt-3 font-black">{{ $title }}</h2>
                        <p class="mt-2 text-sm leading-6 text-sky-100">{{ $text }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section id="learning-workspace" class="mx-auto max-w-7xl scroll-mt-28 px-4 py-10 sm:px-6 lg:px-8">
        @if($notice)
            <div role="status" class="mb-6 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
                <i class="fas fa-circle-check mt-0.5 text-emerald-600" aria-hidden="true"></i><p>{{ $notice }}</p>
            </div>
        @endif
        @if($error)
            <div role="alert" class="mb-6 flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900">
                <i class="fas fa-triangle-exclamation mt-0.5 text-rose-600" aria-hidden="true"></i>
                <div><p class="font-bold">The teaching companion could not complete that request.</p><p class="mt-1">{{ $error }}</p><p class="mt-1">Your completed work is still here. You can retry when ready.</p></div>
            </div>
        @endif

        <div wire:loading.flex wire:target="startLesson,continueLesson,quickAction,askQuestion,exploreBeyond,prepareAssessment,submitAssessment" class="mb-6 hidden items-center gap-3 rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sm font-semibold text-sky-900" aria-live="polite">
            <i class="fas fa-circle-notch fa-spin text-sky-600" aria-hidden="true"></i>
            <span>Your learning companion is preparing a thoughtful response…</span>
        </div>

        @if($phase === 'select' || empty($state))
            <div class="grid gap-8 lg:grid-cols-[1fr_.38fr]">
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                    <div class="flex items-start gap-4">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-sky-100 text-xl text-sky-800"><i class="fas fa-compass" aria-hidden="true"></i></span>
                        <div><p class="text-xs font-black uppercase tracking-[0.18em] text-sky-700">Choose your learning path</p><h2 class="mt-2 text-2xl font-black sm:text-3xl">What would you like to learn today?</h2><p class="mt-2 text-slate-600">Only curriculum that Watersprings staff have published is available here.</p></div>
                    </div>

                    @if(!$school)
                        <div class="mt-8 rounded-2xl border border-amber-200 bg-amber-50 p-6 text-amber-950"><h3 class="font-black">Open Learning needs a school context</h3><p class="mt-2 leading-7">The public Watersprings school has not been configured yet. An administrator can set <code class="rounded bg-amber-100 px-1">OPEN_LEARNING_SCHOOL_ID</code> to activate curriculum discovery.</p></div>
                    @elseif($classes->isEmpty())
                        <div class="mt-8 rounded-2xl border border-slate-200 bg-slate-50 p-6 text-center">
                            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-white text-2xl text-slate-400 shadow-sm"><i class="fas fa-book-open" aria-hidden="true"></i></span>
                            <h3 class="mt-4 text-lg font-black">Published learning topics are coming soon</h3>
                            <p class="mx-auto mt-2 max-w-xl leading-7 text-slate-600">There are no published schemes of work with topics for the current academic year yet. We will show them here as soon as the school publishes approved curriculum content.</p>
                        </div>
                    @else
                        <form wire:submit="startLesson" class="mt-8 space-y-7">
                            <div class="grid gap-5 sm:grid-cols-2">
                                <label class="block"><span class="mb-2 block text-sm font-bold text-slate-700"><span class="mr-2 text-sky-700">1.</span>Select class</span><select wire:model.live="classId" class="w-full rounded-xl border-slate-300 bg-white px-4 py-3 focus:border-sky-500 focus:ring-sky-500"><option value="">Choose a class</option>@foreach($classes as $class)<option value="{{ $class->id }}">{{ $class->name }}</option>@endforeach</select>@error('classId')<span class="mt-1 block text-sm text-rose-600">{{ $message }}</span>@enderror</label>
                                <label class="block"><span class="mb-2 block text-sm font-bold text-slate-700"><span class="mr-2 text-sky-700">2.</span>Select subject</span><select wire:model.live="subjectId" @disabled($classId === '') class="w-full rounded-xl border-slate-300 bg-white px-4 py-3 disabled:bg-slate-100 focus:border-sky-500 focus:ring-sky-500"><option value="">{{ $classId === '' ? 'Choose a class first' : ($subjects->isEmpty() ? 'No published subjects' : 'Choose a subject') }}</option>@foreach($subjects as $subject)<option value="{{ $subject->id }}">{{ $subject->name }}</option>@endforeach</select>@error('subjectId')<span class="mt-1 block text-sm text-rose-600">{{ $message }}</span>@enderror</label>
                                <label class="block"><span class="mb-2 block text-sm font-bold text-slate-700"><span class="mr-2 text-sky-700">3.</span>Select academic term</span><select wire:model.live="semesterId" @disabled($subjectId === '') class="w-full rounded-xl border-slate-300 bg-white px-4 py-3 disabled:bg-slate-100 focus:border-sky-500 focus:ring-sky-500"><option value="">{{ $subjectId === '' ? 'Choose a subject first' : ($terms->isEmpty() ? 'No published terms' : 'Choose a term') }}</option>@foreach($terms as $term)<option value="{{ $term->id }}">{{ $term->name }}</option>@endforeach</select>@error('semesterId')<span class="mt-1 block text-sm text-rose-600">{{ $message }}</span>@enderror</label>
                                <label class="block"><span class="mb-2 block text-sm font-bold text-slate-700"><span class="mr-2 text-sky-700">4.</span>Select topic</span><select wire:model.live="topicId" @disabled($semesterId === '') class="w-full rounded-xl border-slate-300 bg-white px-4 py-3 disabled:bg-slate-100 focus:border-sky-500 focus:ring-sky-500"><option value="">{{ $semesterId === '' ? 'Choose a term first' : ($topics->isEmpty() ? 'No published topics' : 'Choose a topic') }}</option>@foreach($topics as $topic)<option value="{{ $topic->id }}">Week {{ $topic->week_number }} — {{ $topic->topic }}</option>@endforeach</select>@error('topicId')<span class="mt-1 block text-sm text-rose-600">{{ $message }}</span>@enderror</label>
                            </div>

                            @if($classId !== '' && $subjects->isEmpty())
                                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-950"><h3 class="font-black">No subjects are assigned to this class yet</h3><p class="mt-1 leading-6">Choose another class, or check back after the school assigns subjects.</p></div>
                            @elseif($semesterId !== '' && $topics->isEmpty())
                                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-950"><h3 class="font-black">No published topics for this selection yet</h3><p class="mt-1 leading-6">The class, subject and term are available, but their scheme of work has not been published. Try another selection or check back later.</p></div>
                            @endif

                            <fieldset>
                                <legend class="text-sm font-black text-slate-800">Choose how you would like to learn</legend>
                                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                    @foreach([
                                        'beginner' => ['fa-seedling', 'Beginner Mode', 'Simple language and step-by-step foundations.'],
                                        'detailed' => ['fa-microscope', 'Detailed Mode', 'Deeper explanations and connected ideas.'],
                                        'practical' => ['fa-screwdriver-wrench', 'Practical Mode', 'Everyday examples and real applications.'],
                                        'examination' => ['fa-graduation-cap', 'Examination Mode', 'Definitions, worked examples and exam practice.'],
                                    ] as $value => [$icon, $label, $description])
                                        <label class="cursor-pointer rounded-2xl border p-4 transition {{ $teachingStyle === $value ? 'border-sky-500 bg-sky-50 ring-2 ring-sky-100' : 'border-slate-200 hover:border-sky-300' }}"><input type="radio" wire:model.live="teachingStyle" value="{{ $value }}" class="sr-only"><span class="flex gap-3"><i class="fas {{ $icon }} mt-1 text-sky-700" aria-hidden="true"></i><span><span class="block font-black">{{ $label }}</span><span class="mt-1 block text-sm leading-6 text-slate-600">{{ $description }}</span></span></span></label>
                                    @endforeach
                                </div>
                            </fieldset>

                            @if(!$aiConfigured)
                                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">The curriculum can be explored, but AI lessons cannot start until the server-side Groq teaching service is configured.</div>
                            @endif
                            <button type="submit" @disabled(!$aiConfigured || $topicId === '') class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-sky-700 px-6 py-3.5 font-black text-white shadow-lg shadow-sky-900/10 transition hover:bg-sky-800 disabled:cursor-not-allowed disabled:bg-slate-300 sm:w-auto"><i class="fas fa-play" aria-hidden="true"></i>Start this lesson</button>
                        </form>
                    @endif
                </div>

                <aside class="space-y-5">
                    <div class="rounded-3xl border border-cyan-200 bg-cyan-50 p-6"><i class="fas fa-shield-heart text-xl text-cyan-800" aria-hidden="true"></i><h2 class="mt-3 font-black text-cyan-950">Learn without an account</h2><p class="mt-2 text-sm leading-6 text-cyan-900">Your active lesson stays in a secure server-side session. No registration is required, and guest progress is temporary.</p></div>
                    @auth
                        @if($eligibleToSave && $savedRecords->isNotEmpty())
                            <div class="rounded-3xl border border-slate-200 bg-white p-6"><h2 class="font-black">My saved learning</h2><div class="mt-4 space-y-3">@foreach($savedRecords as $record)<button type="button" wire:click="loadRecord({{ $record->id }})" class="w-full rounded-xl border border-slate-200 p-3 text-left transition hover:border-sky-300 hover:bg-sky-50"><span class="block font-bold">{{ $record->title }}</span><span class="mt-1 block text-xs text-slate-500">{{ $record->subject?->name }} • {{ $record->progress_percentage }}% complete</span></button>@endforeach</div></div>
                        @endif
                    @endauth
                </aside>
            </div>
        @else
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <div class="min-w-0">
                    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                        <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                            <div><div class="flex flex-wrap gap-2 text-xs font-bold uppercase tracking-wider text-sky-700"><span>{{ $state['class_name'] }}</span><span aria-hidden="true">•</span><span>{{ $state['subject_name'] }}</span><span aria-hidden="true">•</span><span>{{ $state['term_name'] }}</span></div><h2 class="mt-2 text-2xl font-black sm:text-3xl">{{ $state['lesson_title'] ?? $state['topic_name'] }}</h2><p class="mt-2 text-slate-600">{{ $state['topic_name'] }}</p></div>
                            <div class="flex flex-wrap gap-2">@if(!auth()->check() || $eligibleToSave)<button type="button" wire:click="saveProgress" class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-2 text-sm font-bold text-sky-800 hover:bg-sky-100"><i class="fas fa-bookmark mr-1" aria-hidden="true"></i>Save My Learning Progress</button>@endif<button type="button" wire:click="startOver" wire:confirm="Start a different topic? Unsaved guest progress will be cleared." class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600 hover:bg-slate-50">Change topic</button></div>
                        </div>
                        <div class="mt-6"><div class="flex items-center justify-between text-xs font-bold text-slate-600"><span>Lesson progress</span><span>{{ $progress }}%</span></div><div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-sky-600 transition-all" style="width: {{ $progress }}%"></div></div></div>
                        <nav class="mt-6 flex gap-2 overflow-x-auto border-b border-slate-200" aria-label="Learning views">
                            <button type="button" wire:click="showLesson" class="whitespace-nowrap border-b-2 px-4 py-3 text-sm font-bold {{ $phase === 'lesson' ? 'border-sky-600 text-sky-800' : 'border-transparent text-slate-500' }}">Lesson</button>
                            <button type="button" wire:click="exploreBeyond" class="whitespace-nowrap border-b-2 px-4 py-3 text-sm font-bold {{ $phase === 'beyond' ? 'border-amber-500 text-amber-800' : 'border-transparent text-slate-500' }}">Beyond the Classroom</button>
                            @if(!empty($state['assessment']))<button type="button" wire:click="showAssessment" class="whitespace-nowrap border-b-2 px-4 py-3 text-sm font-bold {{ $phase === 'assessment' ? 'border-violet-600 text-violet-800' : 'border-transparent text-slate-500' }}">Assessment</button>@endif
                        </nav>
                    </div>

                    @if($phase === 'lesson')
                        @php $currentSection = data_get($state, 'sections.'.($state['current_section'] ?? 1)); @endphp
                        @if($currentSection)
                            <article class="mt-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                                <p class="text-xs font-black uppercase tracking-[0.18em] text-sky-700">Section {{ $state['current_section'] }} of {{ count($state['section_plan'] ?? []) }}</p>
                                <h3 class="mt-3 text-2xl font-black">{{ $currentSection['heading'] }}</h3>
                                <div class="mt-6 space-y-4 text-base leading-8 text-slate-700">@foreach($currentSection['content'] as $paragraph)<p>{{ $paragraph }}</p>@endforeach</div>
                                @if(!empty($currentSection['examples']))<div class="mt-7 rounded-2xl border border-cyan-200 bg-cyan-50 p-5"><h4 class="font-black text-cyan-950"><i class="fas fa-lightbulb mr-2 text-amber-500" aria-hidden="true"></i>Examples</h4><ul class="mt-3 space-y-2 text-sm leading-7 text-cyan-950">@foreach($currentSection['examples'] as $example)<li class="flex gap-2"><span aria-hidden="true">•</span><span>{{ $example }}</span></li>@endforeach</ul></div>@endif
                                <div class="mt-7 grid gap-5 md:grid-cols-2"><div class="rounded-2xl bg-slate-50 p-5"><h4 class="font-black">Key points</h4><ul class="mt-3 space-y-2 text-sm leading-6 text-slate-700">@foreach($currentSection['key_points'] as $point)<li class="flex gap-2"><i class="fas fa-check mt-1 text-emerald-600" aria-hidden="true"></i><span>{{ $point }}</span></li>@endforeach</ul></div><div class="rounded-2xl bg-amber-50 p-5"><h4 class="font-black text-amber-950">Check your understanding</h4><p class="mt-3 text-sm leading-7 text-amber-950">{{ $currentSection['check_question'] }}</p></div></div>
                                <div class="mt-7 flex flex-wrap gap-2"><button type="button" wire:click="quickAction('simpler')" class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-bold hover:bg-slate-50">Make it simpler</button><button type="button" wire:click="quickAction('example')" class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-bold hover:bg-slate-50">Another example</button><button type="button" wire:click="quickAction('question')" class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-bold hover:bg-slate-50">Ask me a question</button><button type="button" wire:click="quickAction('deeper')" class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-bold hover:bg-slate-50">Go deeper</button></div>
                                <div class="mt-8 flex items-center justify-between gap-4 border-t border-slate-200 pt-6"><button type="button" wire:click="previousSection" @disabled(($state['current_section'] ?? 1) <= 1) class="rounded-xl border border-slate-200 px-4 py-2.5 font-bold text-slate-600 disabled:opacity-40">← Previous</button><button type="button" wire:click="continueLesson" class="rounded-xl bg-sky-700 px-5 py-2.5 font-black text-white hover:bg-sky-800">{{ ($state['current_section'] ?? 1) >= count($state['section_plan'] ?? []) ? 'I understand — finish lesson' : 'I understand — continue' }} →</button></div>
                            </article>
                        @else
                            <section class="mt-6 rounded-3xl border border-amber-200 bg-white p-8 text-center shadow-sm"><span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-amber-100 text-amber-700"><i class="fas fa-rotate" aria-hidden="true"></i></span><h3 class="mt-4 text-xl font-black">This section is ready to retry</h3><p class="mx-auto mt-2 max-w-xl leading-7 text-slate-600">Your earlier sections and conversation are still safe. Retry only this section when the teaching service is available.</p><button type="button" wire:click="retryCurrentSection" class="mt-5 rounded-xl bg-sky-700 px-5 py-3 font-black text-white hover:bg-sky-800">Retry this section</button></section>
                        @endif

                        @if(!empty($state['messages']))
                            <section class="mt-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8"><h3 class="text-xl font-black">Learning conversation</h3><div class="mt-5 space-y-4">@foreach($state['messages'] as $message)<div class="flex {{ $message['role'] === 'learner' ? 'justify-end' : 'justify-start' }}"><div class="max-w-3xl rounded-2xl px-4 py-3 text-sm leading-7 {{ $message['role'] === 'learner' ? 'bg-sky-700 text-white' : 'border border-slate-200 bg-slate-50 text-slate-800' }}"><p class="mb-1 text-[11px] font-black uppercase tracking-wider {{ $message['role'] === 'learner' ? 'text-sky-100' : 'text-sky-700' }}">{{ $message['role'] === 'learner' ? 'You' : 'Learning companion' }}</p><p class="whitespace-pre-line">{{ $message['content'] }}</p></div></div>@endforeach</div></section>
                        @endif
                    @elseif($phase === 'beyond')
                        @if(!empty($state['beyond']))
                            @php $beyond = $state['beyond']; @endphp
                            <section class="mt-6 rounded-3xl border border-amber-200 bg-white p-6 shadow-sm sm:p-8"><p class="text-xs font-black uppercase tracking-[0.18em] text-amber-700">Explore beyond the classroom</p><h3 class="mt-3 text-2xl font-black">Where {{ $state['topic_name'] }} meets the wider world</h3><p class="mt-4 leading-8 text-slate-700">{{ $beyond['introduction'] }}</p><div class="mt-8 grid gap-5 md:grid-cols-2">@foreach(['everyday_examples' => ['fa-house', 'Everyday examples'], 'real_world_applications' => ['fa-earth-africa', 'Real-world applications'], 'industry_applications' => ['fa-industry', 'Industry applications'], 'careers' => ['fa-briefcase', 'Related careers'], 'practical_scenarios' => ['fa-person-chalkboard', 'Practical scenarios'], 'interesting_discoveries' => ['fa-magnifying-glass', 'Interesting discoveries'], 'subject_connections' => ['fa-link', 'Connections to other subjects']] as $key => [$icon, $label])<article class="rounded-2xl border border-slate-200 p-5"><h4 class="font-black text-slate-900"><i class="fas {{ $icon }} mr-2 text-amber-600" aria-hidden="true"></i>{{ $label }}</h4><ul class="mt-3 space-y-2 text-sm leading-7 text-slate-700">@foreach($beyond[$key] as $item)<li class="flex gap-2"><span class="text-amber-600" aria-hidden="true">•</span><span>{{ $item }}</span></li>@endforeach</ul></article>@endforeach</div></section>
                        @endif
                    @elseif($phase === 'assessment' && $assessment)
                        @if(!empty($assessment['result']))
                            @php $result = $assessment['result']; @endphp
                            <section class="mt-6 rounded-3xl border border-violet-200 bg-white p-6 shadow-sm sm:p-8"><div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between"><div><p class="text-xs font-black uppercase tracking-[0.18em] text-violet-700">Assessment result</p><h3 class="mt-2 text-2xl font-black">{{ $assessment['title'] }}</h3></div><div class="rounded-2xl bg-violet-50 px-6 py-4 text-center"><p class="text-3xl font-black text-violet-800">{{ $result['percentage'] }}%</p><p class="text-sm font-bold text-violet-700">{{ $result['score'] }} / {{ $result['total'] }} marks</p></div></div><div class="mt-6 rounded-2xl bg-sky-50 p-5 text-sky-950"><p class="font-bold">{{ $result['recommendations']['guidance'] }}</p></div><div class="mt-7 space-y-5">@foreach($result['results'] as $index => $item)<article class="rounded-2xl border p-5 {{ $item['correct'] ? 'border-emerald-200 bg-emerald-50/50' : 'border-rose-200 bg-rose-50/50' }}"><div class="flex items-start justify-between gap-4"><h4 class="font-black">{{ $index + 1 }}. {{ $item['prompt'] }}</h4><span class="shrink-0 rounded-full px-3 py-1 text-xs font-black {{ $item['correct'] ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">{{ $item['awarded'] }}/{{ $item['marks'] }}</span></div><dl class="mt-4 grid gap-3 text-sm"><div><dt class="font-bold text-slate-500">Your answer</dt><dd class="mt-1 text-slate-800">{{ $item['answer'] !== '' ? $item['answer'] : 'No answer provided' }}</dd></div><div><dt class="font-bold text-slate-500">Expected answer</dt><dd class="mt-1 text-slate-800">{{ $item['expected_answer'] }}</dd></div><div><dt class="font-bold text-slate-500">Feedback</dt><dd class="mt-1 leading-7 text-slate-800">{{ $item['feedback'] }}</dd></div></dl></article>@endforeach</div><div class="mt-7 flex flex-wrap gap-3"><button type="button" wire:click="prepareAssessment" class="rounded-xl bg-violet-700 px-5 py-3 font-black text-white hover:bg-violet-800">Try a fresh assessment</button><button type="button" wire:click="showLesson" class="rounded-xl border border-slate-200 px-5 py-3 font-bold">Review the lesson</button></div></section>
                        @else
                            @php $question = $assessment['questions'][$assessmentIndex] ?? null; @endphp
                            @if($question)<section class="mt-6 rounded-3xl border border-violet-200 bg-white p-6 shadow-sm sm:p-8"><div class="flex items-center justify-between gap-4"><div><p class="text-xs font-black uppercase tracking-[0.18em] text-violet-700">Question {{ $assessmentIndex + 1 }} of {{ count($assessment['questions']) }}</p><h3 class="mt-2 text-xl font-black">{{ $assessment['title'] }}</h3></div><span class="rounded-full bg-violet-50 px-3 py-1 text-xs font-bold text-violet-800">{{ str_replace('_', ' ', ucfirst($question['type'])) }} • {{ $question['marks'] }} {{ Str::plural('mark', $question['marks']) }}</span></div><div class="mt-5 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full bg-violet-600" style="width: {{ (($assessmentIndex + 1) / count($assessment['questions'])) * 100 }}%"></div></div><p class="mt-7 text-lg font-bold leading-8">{{ $question['prompt'] }}</p>@if(in_array($question['type'], ['multiple_choice', 'true_false'], true))<div class="mt-5 grid gap-3">@foreach($question['options'] as $option)<label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 p-4 hover:bg-violet-50"><input type="radio" wire:model="answers.{{ $question['id'] }}" value="{{ $option }}" class="border-slate-300 text-violet-600 focus:ring-violet-500"><span>{{ $option }}</span></label>@endforeach</div>@else<label class="mt-5 block"><span class="sr-only">Your answer</span><textarea wire:model="answers.{{ $question['id'] }}" rows="{{ in_array($question['type'], ['theory', 'calculation'], true) ? 6 : 3 }}" maxlength="3000" placeholder="Write your answer{{ $question['type'] === 'calculation' ? ' and show your working' : '' }}…" class="w-full rounded-xl border-slate-300 focus:border-violet-500 focus:ring-violet-500"></textarea></label>@endif<div class="mt-7 flex items-center justify-between gap-3 border-t border-slate-200 pt-6"><button type="button" wire:click="assessmentPrevious" @disabled($assessmentIndex === 0) class="rounded-xl border border-slate-200 px-4 py-2.5 font-bold disabled:opacity-40">← Previous</button>@if($assessmentIndex + 1 < count($assessment['questions']))<button type="button" wire:click="assessmentNext" class="rounded-xl bg-violet-700 px-5 py-2.5 font-black text-white">Next →</button>@else<button type="button" wire:click="submitAssessment('{{ $assessment['submission_token'] }}')" wire:confirm="Submit this assessment? You will then see the answers and corrections." class="rounded-xl bg-violet-700 px-5 py-2.5 font-black text-white">Submit assessment</button>@endif</div></section>@endif
                        @endif
                    @endif

                    @if(in_array($phase, ['lesson', 'beyond'], true))
                        <section class="mt-6 rounded-3xl border border-sky-200 bg-white p-6 shadow-sm"><div class="flex items-start gap-3"><span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-100 text-sky-800"><i class="fas fa-comments" aria-hidden="true"></i></span><div><h3 class="font-black">Ask your learning companion</h3><p class="mt-1 text-sm text-slate-600">Ask about this topic, a prerequisite idea, an example, or one of the careers above.</p></div></div><form wire:submit="askQuestion" class="mt-5 flex flex-col gap-3 sm:flex-row"><label class="flex-1"><span class="sr-only">Your question</span><textarea wire:model="learnerQuestion" rows="2" maxlength="{{ config('open-learning.max_message_characters') }}" placeholder="What would you like explained?" class="w-full rounded-xl border-slate-300 focus:border-sky-500 focus:ring-sky-500"></textarea>@error('learnerQuestion')<span class="mt-1 block text-sm text-rose-600">{{ $message }}</span>@enderror</label><button type="submit" class="self-stretch rounded-xl bg-sky-700 px-5 py-3 font-black text-white hover:bg-sky-800 sm:self-start">Ask</button></form></section>
                    @endif
                </div>

                <aside class="space-y-5 lg:sticky lg:top-28 lg:self-start">
                    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"><h3 class="font-black">Teaching style</h3><div class="mt-3 grid gap-2">@foreach(['beginner' => 'Beginner', 'detailed' => 'Detailed', 'practical' => 'Practical', 'examination' => 'Examination'] as $value => $label)<button type="button" wire:click="changeStyle('{{ $value }}')" class="rounded-xl border px-3 py-2 text-left text-sm font-bold {{ $teachingStyle === $value ? 'border-sky-500 bg-sky-50 text-sky-800' : 'border-slate-200 text-slate-600 hover:bg-slate-50' }}">{{ $label }} Mode</button>@endforeach</div></div>
                    @if(!empty($state['section_plan']))<div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"><h3 class="font-black">Lesson map</h3><ol class="mt-4 space-y-2">@foreach($state['section_plan'] as $index => $heading)@php $number = $index + 1; $available = isset($state['sections'][(string) $number]); $done = in_array($number, $state['completed_sections'] ?? [], true); @endphp<li><button type="button" wire:click="revisitSection({{ $number }})" @disabled(!$available) class="flex w-full items-start gap-3 rounded-xl p-2 text-left text-sm disabled:cursor-not-allowed disabled:opacity-50 {{ ($state['current_section'] ?? 1) === $number ? 'bg-sky-50 text-sky-900' : 'text-slate-600 hover:bg-slate-50' }}"><span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-black {{ $done ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $done ? '✓' : $number }}</span><span>{{ $heading }}</span></button></li>@endforeach</ol></div>@endif
                    <div class="rounded-3xl border border-violet-200 bg-violet-50 p-5"><h3 class="font-black text-violet-950">Check your understanding</h3><label class="mt-4 block text-sm font-bold text-violet-900">Difficulty<select wire:model="assessmentDifficulty" class="mt-2 w-full rounded-xl border-violet-200 bg-white focus:border-violet-500 focus:ring-violet-500"><option value="easy">Easy</option><option value="intermediate">Intermediate</option><option value="challenging">Challenging</option></select></label><button type="button" wire:click="prepareAssessment" class="mt-4 w-full rounded-xl bg-violet-700 px-4 py-3 font-black text-white hover:bg-violet-800">{{ empty($state['assessment']) ? 'Create assessment' : 'Create fresh assessment' }}</button></div>
                    <div class="rounded-3xl border border-slate-200 bg-white p-5 text-sm leading-6 text-slate-600"><p class="font-black text-slate-900"><i class="fas fa-user-shield mr-2 text-sky-700" aria-hidden="true"></i>Your privacy</p><p class="mt-2">Guest progress is temporary. AI responses may contain mistakes, so compare important facts with your teacher and approved learning materials.</p></div>
                </aside>
            </div>
        @endif
    </section>

    @if($showSavePrompt)
        <div class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/60 p-4" role="dialog" aria-modal="true" aria-labelledby="save-progress-title" x-data x-on:keydown.escape.window="$wire.set('showSavePrompt', false)">
            <div class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl sm:p-8">
                <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-sky-100 text-xl text-sky-800"><i class="fas fa-bookmark" aria-hidden="true"></i></span>
                <h2 id="save-progress-title" class="mt-5 text-2xl font-black">Save your learning progress</h2>
                <p class="mt-3 leading-7 text-slate-600">To save your learning progress permanently, sign in with your existing Water Spring student, staff or parent account.</p>
                <p class="mt-3 text-sm text-slate-500">You can continue this lesson as a guest without signing in. Open Learning does not create public accounts.</p>
                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"><button type="button" wire:click="$set('showSavePrompt', false)" class="rounded-xl border border-slate-200 px-5 py-3 font-bold text-slate-700">Keep learning as guest</button><a href="{{ route('login') }}" class="rounded-xl bg-sky-700 px-5 py-3 text-center font-black text-white hover:bg-sky-800">Sign in</a></div>
            </div>
        </div>
    @endif

    <section class="border-y border-slate-200 bg-white py-12"><div class="mx-auto grid max-w-6xl gap-6 px-4 sm:px-6 md:grid-cols-3 lg:px-8"><article><h2 class="font-black text-slate-900">Curriculum-grounded</h2><p class="mt-2 text-sm leading-6 text-slate-600">Lessons begin with published Watersprings schemes of work and syllabi. Supplementary knowledge is not presented as official curriculum.</p></article><article><h2 class="font-black text-slate-900">Built for thoughtful learning</h2><p class="mt-2 text-sm leading-6 text-slate-600">The companion encourages reasoning, explains misconceptions respectfully and breaks complex topics into manageable parts.</p></article><article><h2 class="font-black text-slate-900">Safe by design</h2><p class="mt-2 text-sm leading-6 text-slate-600">Requests are validated and rate-limited on the server. Private student records and AI credentials are never exposed to public visitors.</p></article></div></section>
</div>
