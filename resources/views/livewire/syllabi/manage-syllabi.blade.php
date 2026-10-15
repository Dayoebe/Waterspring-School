<div class="space-y-6">
    @if (session()->has('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-700">
            {{ session('success') }}
        </div>
    @endif

    @if ($mode === 'list')
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Syllabi</h2>
                    <p class="text-sm text-gray-600">Manage syllabi by class and subject.</p>
                    @if ($isRestrictedTeacherSyllabusManager)
                        <p class="mt-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-900">
                            Only your assigned teaching classes and subjects are available here.
                        </p>
                    @endif
                </div>
                @can('create syllabus')
                    <a href="{{ route('syllabi.create') }}"
                        wire:navigate
                        class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                        <i class="fas fa-plus mr-2"></i>Create Syllabus
                    </a>
                @endcan
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                @if (!auth()->user()->hasRole('student'))
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">Class</label>
                        <select wire:model.live="class" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">All Classes</option>
                            @foreach ($classes as $item)
                                <option value="{{ $item->id }}">{{ $item->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">Search</label>
                    <input type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search name, description, subject..."
                        class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">Per Page</label>
                    <select wire:model.live="perPage" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach ([10, 20, 50] as $size)
                            <option value="{{ $size }}">{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Subject</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Class</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Created</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse ($syllabi as $item)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $item->name }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600">{{ $item->subject?->name ?? 'N/A' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600">{{ $item->schoolClass?->name ?? 'Legacy syllabus' }}</td>
                                <td class="px-6 py-4 text-sm"><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $item->is_published ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-800' }}">{{ $item->is_published ? 'Published' : 'Draft' }}</span></td>
                                <td class="px-6 py-4 text-sm text-gray-600">{{ $item->created_at?->format('M d, Y') }}</td>
                                <td class="px-6 py-4 text-right text-sm">
                                    <div class="inline-flex items-center gap-3">
                                        <a href="{{ route('syllabi.show', $item->id) }}" wire:navigate class="text-blue-600 hover:text-blue-800" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        @if($item->file)<a href="{{ asset('storage/'.$item->file) }}" class="text-emerald-600 hover:text-emerald-800" title="Download legacy PDF" download>
                                            <i class="fas fa-download"></i>
                                        </a>@endif
                                        @can('delete syllabus')
                                            <button
                                                wire:click="deleteSyllabus({{ $item->id }})"
                                                wire:confirm="Delete this syllabus?"
                                                class="text-red-600 hover:text-red-800"
                                                title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500">No syllabi found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($syllabi instanceof \Illuminate\Contracts\Pagination\Paginator && $syllabi->hasPages())
                <div class="border-t border-gray-200 px-6 py-4">
                    {{ $syllabi->links() }}
                </div>
            @endif
        </div>
    @elseif ($mode === 'create')
        <section class="overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-indigo-950 to-sky-900 p-6 text-white shadow-xl sm:p-8">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-xs font-bold uppercase tracking-[.2em] text-amber-300">Curriculum authoring</p><h1 class="mt-3 text-3xl font-black sm:text-4xl">Create a complete syllabus</h1><p class="mt-4 max-w-3xl leading-7 text-sky-100">Define what will be taught, why it matters, how learning will happen, and how progress will be measured throughout the term.</p></div><a href="{{ route('syllabi.index') }}" wire:navigate class="rounded-xl bg-white/10 px-4 py-2.5 text-sm font-bold hover:bg-white/20"><i class="fas fa-arrow-left mr-2"></i>Back to syllabi</a></div>
        </section>

        <form wire:submit.prevent="createSyllabus" class="space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><div class="mb-5"><p class="text-xs font-bold uppercase tracking-widest text-indigo-700">1. Context</p><h2 class="mt-1 text-xl font-black text-slate-950">Class, subject and identity</h2></div>
                @if ($errors->has('semester'))
                    <div class="rounded-md bg-red-50 px-4 py-3 text-sm text-red-700">
                        {{ $errors->first('semester') }}
                    </div>
                @endif
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-bold text-slate-700">Class <span class="text-red-500">*</span></label>
                        <select wire:model.live="class" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 @error('class') border-red-500 @enderror">
                            <option value="">Select Class</option>
                            @foreach ($classes as $item)
                                <option value="{{ $item->id }}">{{ $item->name }}</option>
                            @endforeach
                        </select>
                        @error('class')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div><label class="mb-2 block text-sm font-bold text-slate-700">Subject <span class="text-red-500">*</span></label><select wire:model="subject" class="w-full rounded-lg border-gray-300 @error('subject') border-red-500 @enderror" @if($subjects->isEmpty()) disabled @endif><option value="">Select Subject</option>@foreach ($subjects as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select>@error('subject')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                    <div class="md:col-span-2"><label class="mb-2 block text-sm font-bold text-slate-700">Syllabus title <span class="text-red-500">*</span></label><input wire:model="name" class="w-full rounded-lg border-gray-300" placeholder="e.g. Year 8 Mathematics — First Term Syllabus">@error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                    <div class="md:col-span-2"><label class="mb-2 block text-sm font-bold text-slate-700">Course description <span class="text-red-500">*</span></label><textarea wire:model="description" rows="3" class="w-full rounded-lg border-gray-300" placeholder="A concise summary of the subject, term focus, and its relevance to learners"></textarea>@error('description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                </div>
            </section>

            <section class="rounded-2xl border border-sky-200 bg-white p-6 shadow-sm"><div class="mb-5"><p class="text-xs font-bold uppercase tracking-widest text-sky-700">2. Curriculum direction</p><h2 class="mt-1 text-xl font-black text-slate-950">Purpose and expected learning</h2></div><div class="grid gap-4 lg:grid-cols-2">
                <div><label class="mb-2 block text-sm font-bold">Curriculum overview <span class="text-red-500">*</span></label><textarea wire:model="overview" rows="5" class="w-full rounded-xl border-slate-300" placeholder="Describe the major concepts, strands and learning journey for the term"></textarea>@error('overview')<p class="text-sm text-red-600">{{ $message }}</p>@enderror</div>
                <div><label class="mb-2 block text-sm font-bold">Broad aims <span class="text-red-500">*</span></label><textarea wire:model="aims" rows="5" class="w-full rounded-xl border-slate-300" placeholder="State the broad educational aims, one per line where helpful"></textarea>@error('aims')<p class="text-sm text-red-600">{{ $message }}</p>@enderror</div>
                <div class="lg:col-span-2"><label class="mb-2 block text-sm font-bold">Learning outcomes <span class="text-red-500">*</span></label><textarea wire:model="learningOutcomes" rows="6" class="w-full rounded-xl border-slate-300" placeholder="By the end of the term, learners should be able to…"></textarea>@error('learningOutcomes')<p class="text-sm text-red-600">{{ $message }}</p>@enderror</div>
                <div class="lg:col-span-2"><label class="mb-2 block text-sm font-bold">Scope and sequence <span class="text-red-500">*</span></label><textarea wire:model="scopeAndSequence" rows="8" class="w-full rounded-xl border-slate-300" placeholder="Organise units or themes in teaching order. Include suggested duration and core concepts for each unit."></textarea>@error('scopeAndSequence')<p class="text-sm text-red-600">{{ $message }}</p>@enderror</div>
                <div class="lg:col-span-2"><label class="mb-2 block text-sm font-bold">Prerequisite knowledge and skills</label><textarea wire:model="prerequisites" rows="3" class="w-full rounded-xl border-slate-300" placeholder="What learners should already know or be able to do"></textarea></div>
            </div></section>

            <section class="rounded-2xl border border-emerald-200 bg-white p-6 shadow-sm"><div class="mb-5"><p class="text-xs font-bold uppercase tracking-widest text-emerald-700">3. Delivery and evidence</p><h2 class="mt-1 text-xl font-black text-slate-950">How learning will be taught and assessed</h2></div><div class="grid gap-4 lg:grid-cols-2">
                <div><label class="mb-2 block text-sm font-bold">Teaching methodology <span class="text-red-500">*</span></label><textarea wire:model="teachingMethodology" rows="5" class="w-full rounded-xl border-slate-300" placeholder="Discussion, modelling, guided practice, inquiry, collaborative work, practical activities…"></textarea>@error('teachingMethodology')<p class="text-sm text-red-600">{{ $message }}</p>@enderror</div>
                <div><label class="mb-2 block text-sm font-bold">Assessment plan <span class="text-red-500">*</span></label><textarea wire:model="assessmentPlan" rows="5" class="w-full rounded-xl border-slate-300" placeholder="Diagnostic, formative and summative assessment methods, frequency and expectations"></textarea>@error('assessmentPlan')<p class="text-sm text-red-600">{{ $message }}</p>@enderror</div>
                <div><label class="mb-2 block text-sm font-bold">Learning resources <span class="text-red-500">*</span></label><textarea wire:model="learningResources" rows="4" class="w-full rounded-xl border-slate-300" placeholder="Texts, equipment, manipulatives, digital tools and local materials"></textarea>@error('learningResources')<p class="text-sm text-red-600">{{ $message }}</p>@enderror</div>
                <div><label class="mb-2 block text-sm font-bold">Differentiation and learner support</label><textarea wire:model="differentiation" rows="4" class="w-full rounded-xl border-slate-300" placeholder="Support, extension, accessibility and varied learning needs"></textarea></div>
                <div><label class="mb-2 block text-sm font-bold">Cross-curricular links</label><textarea wire:model="crossCurricularLinks" rows="3" class="w-full rounded-xl border-slate-300" placeholder="Connections to other subjects, values, life skills or projects"></textarea></div>
                <div><label class="mb-2 block text-sm font-bold">Homework and independent learning policy</label><textarea wire:model="homeworkPolicy" rows="3" class="w-full rounded-xl border-slate-300" placeholder="Frequency, purpose, expected effort and feedback approach"></textarea></div>
                <div class="lg:col-span-2"><label class="mb-2 block text-sm font-bold">References and recommended materials</label><textarea wire:model="references" rows="4" class="w-full rounded-xl border-slate-300" placeholder="Books, curriculum documents, websites and further learning materials"></textarea></div>
            </div></section>

            <section class="flex flex-col gap-4 rounded-2xl border border-amber-200 bg-amber-50 p-5 sm:flex-row sm:items-center sm:justify-between"><label class="flex items-start gap-3"><input type="checkbox" wire:model="isPublished" class="mt-1 rounded border-amber-300 text-indigo-700"><span><strong class="block text-slate-900">Publish this syllabus</strong><small class="text-slate-600">Published content is ready for authorised readers. Leave unchecked to keep it as a draft.</small></span></label><div class="flex gap-3"><a href="{{ route('syllabi.index') }}" wire:navigate class="rounded-xl border border-slate-300 bg-white px-5 py-3 font-bold text-slate-700">Cancel</a><button type="submit" class="rounded-xl bg-indigo-700 px-6 py-3 font-black text-white hover:bg-indigo-800"><i class="fas fa-save mr-2"></i>Create syllabus</button></div></section>
        </form>
    @elseif ($mode === 'show' && $selectedSyllabus)
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">{{ $selectedSyllabus->name }}</h2>
                    <p class="mt-1 text-sm text-gray-600">{{ $selectedSyllabus->schoolClass?->name }} · {{ $selectedSyllabus->subject?->name ?? 'N/A' }} · {{ $selectedSyllabus->semester?->name }}</p>
                    <p class="mt-1 text-sm text-gray-500">Created: {{ $selectedSyllabus->created_at?->format('M d, Y h:i A') }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('syllabi.index') }}"
                        wire:navigate
                        class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">
                        <i class="fas fa-arrow-left mr-2"></i>Back
                    </a>
                    @if($selectedSyllabus->file)<a href="{{ asset('storage/'.$selectedSyllabus->file) }}"
                        download
                        class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                        <i class="fas fa-download mr-2"></i>Download legacy PDF
                    </a>@endif
                    @can('delete syllabus')
                        <button
                            wire:click="deleteSyllabus({{ $selectedSyllabus->id }})"
                            wire:confirm="Delete this syllabus?"
                            class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">
                            <i class="fas fa-trash mr-2"></i>Delete
                        </button>
                    @endcan
                </div>
            </div>

            <div class="mt-6 space-y-5"><article class="rounded-2xl bg-indigo-50 p-5"><p class="text-xs font-bold uppercase tracking-widest text-indigo-700">Course description</p><p class="mt-2 whitespace-pre-line leading-7 text-slate-700">{{ $selectedSyllabus->description ?: 'No description provided.' }}</p></article>@foreach([['Curriculum overview',$selectedSyllabus->overview],['Broad aims',$selectedSyllabus->aims],['Learning outcomes',$selectedSyllabus->learning_outcomes],['Scope and sequence',$selectedSyllabus->scope_and_sequence],['Prerequisites',$selectedSyllabus->prerequisites],['Teaching methodology',$selectedSyllabus->teaching_methodology],['Assessment plan',$selectedSyllabus->assessment_plan],['Learning resources',$selectedSyllabus->learning_resources],['Differentiation and learner support',$selectedSyllabus->differentiation],['Cross-curricular links',$selectedSyllabus->cross_curricular_links],['Homework policy',$selectedSyllabus->homework_policy],['References',$selectedSyllabus->references]] as [$label,$content])@if($content)<article class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-xs font-bold uppercase tracking-widest text-slate-500">{{ $label }}</p><p class="mt-2 whitespace-pre-line leading-7 text-slate-700">{{ $content }}</p></article>@endif @endforeach</div>
        </div>
    @endif
</div>
