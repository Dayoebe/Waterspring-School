<div class="space-y-6">

        {{-- LIST VIEW --}}
        @if ($view === 'list')
            <section class="overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-950 via-indigo-900 to-sky-800 text-white shadow-xl">
                <div class="flex flex-col gap-6 p-6 sm:p-8 lg:flex-row lg:items-center lg:justify-between">
                    <div class="max-w-2xl">
                        <p class="text-xs font-bold uppercase tracking-[0.2em] text-sky-200">Academic structure</p>
                        <h2 class="mt-2 text-3xl font-black sm:text-4xl">Classes</h2>
                        <p class="mt-3 leading-7 text-indigo-100">Organise class levels, connect subjects and teachers, manage sections, and keep student placement clear for the current academic year.</p>
                    </div>
                    @can('create', App\Models\MyClass::class)
                        <button wire:click="showCreate"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-amber-400 px-5 py-3 font-black text-indigo-950 transition hover:bg-amber-300">
                            <i class="fas fa-plus"></i>
                            <span>Create class</span>
                        </button>
                    @endcan
                </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-3">
                @foreach ([
                    ['Classes', $totalClasses, 'fa-chalkboard', 'bg-indigo-100 text-indigo-700'],
                    ['Class groups', $totalClassGroups, 'fa-layer-group', 'bg-sky-100 text-sky-700'],
                    ['Active students', $totalStudents, 'fa-user-graduate', 'bg-emerald-100 text-emerald-700'],
                ] as [$label, $value, $icon, $style])
                    <article class="flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <span class="{{ $style }} flex h-12 w-12 items-center justify-center rounded-2xl"><i class="fas {{ $icon }}"></i></span>
                        <div><p class="text-sm font-semibold text-slate-500">{{ $label }}</p><p class="mt-1 text-2xl font-black text-slate-950">{{ number_format($value) }}</p></div>
                    </article>
                @endforeach
            </section>

            <div x-data="{ focused: false }" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400" :class="{ 'text-indigo-600': focused }"></i>
                    </div>
                    <input wire:model.live.debounce.300ms="search" @focus="focused = true" @blur="focused = false"
                        type="search" placeholder="Search classes by name..."
                        class="w-full rounded-xl border-slate-300 bg-slate-50 py-3 pl-12 pr-4 focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @forelse ($classes as $class)
                    <article class="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition duration-300 hover:-translate-y-1 hover:border-indigo-200 hover:shadow-xl">
                        <div class="h-1.5 bg-gradient-to-r from-indigo-600 via-sky-500 to-cyan-400"></div>
                        <div class="p-6">
                            <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-100">
                                <i class="fas fa-chalkboard-teacher text-2xl text-indigo-600"></i>
                            </div>

                            <h3 class="mb-2 text-xl font-black text-slate-950 transition group-hover:text-indigo-700">{{ $class->name }}</h3>

                            <div class="mb-3">
                                <span
                                    class="inline-flex items-center px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-xs font-medium">
                                    <i class="fas fa-layer-group mr-1.5"></i>
                                    {{ $class->classGroup->name }}
                                </span>
                            </div>

                            <div class="flex items-center gap-2 mb-4 text-sm">
                                <span class="px-2 py-1 bg-blue-50 text-blue-700 rounded font-medium">
                                    <i class="fas fa-users mr-1"></i>{{ $class->studentsCount() }}
                                </span>
                                <span class="px-2 py-1 bg-green-50 text-green-700 rounded font-medium">
                                    <i class="fas fa-book mr-1"></i>{{ $class->subjects->count() }}
                                </span>
                            </div>

                            <div class="flex items-center space-x-2">
                                @can('view', $class)
                                    <button wire:click="showView({{ $class->id }})"
                                        class="flex-1 px-3 py-2 bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-600 hover:text-white transition-all text-sm font-medium">
                                        <i class="fas fa-eye mr-1"></i> View
                                    </button>
                                @endcan
                                @can('update', $class)
                                    <button wire:click="showEdit({{ $class->id }})"
                                        class="px-3 py-2 bg-amber-50 text-amber-700 rounded-lg hover:bg-amber-600 hover:text-white transition-all">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                @endcan
                                @can('delete', $class)
                                    <button wire:click="delete({{ $class->id }})"
                                        wire:confirm="Are you sure you want to delete this class?"
                                        class="px-3 py-2 bg-red-50 text-red-700 rounded-lg hover:bg-red-600 hover:text-white transition-all">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                @endcan
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="col-span-full">
                        <div class="rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center">
                            <div class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-3xl bg-indigo-50">
                                <i class="fas fa-chalkboard-teacher text-3xl text-indigo-400"></i>
                            </div>
                            <h3 class="text-xl font-bold text-gray-900 mb-2">No classes yet</h3>
                            <p class="mx-auto mb-6 max-w-md text-slate-600">Create your first class. If your school has no class group yet, you can create one inside the same form.</p>
                            @can('create', App\Models\MyClass::class)
                                <button wire:click="showCreate"
                                    class="px-6 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-all font-semibold">
                                    <i class="fas fa-plus mr-2"></i> Create Class
                                </button>
                            @endcan
                        </div>
                    </div>
                @endforelse
            </div>

            <div class="mt-8">{{ $classes->links() }}</div>
        @endif

        {{-- CREATE VIEW --}}
        @if ($view === 'create')
            <section class="mx-auto max-w-5xl overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl">
                <div class="bg-gradient-to-r from-indigo-900 to-sky-800 p-6 text-white sm:p-8">
                    <button type="button" wire:click="showList" class="mb-5 inline-flex items-center gap-2 text-sm font-bold text-indigo-100 hover:text-white"><i class="fas fa-arrow-left"></i>Back to classes</button>
                    <div class="flex items-start gap-4"><span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-white/15 text-xl"><i class="fas fa-chalkboard"></i></span><div><p class="text-xs font-bold uppercase tracking-[0.18em] text-sky-200">Academic structure</p><h2 class="mt-1 text-3xl font-black">Create a class</h2><p class="mt-2 max-w-2xl text-sm leading-6 text-indigo-100">Add a class to an existing group, or create the class group here without leaving this page.</p></div></div>
                </div>

                <form wire:submit.prevent="create" class="grid gap-8 p-6 sm:p-8 2xl:grid-cols-[minmax(0,1fr)_260px]">
                    <div class="space-y-6">
                        <div>
                            <label class="mb-2 block text-sm font-bold text-slate-800">Class name <span class="text-red-500">*</span></label>
                            <input wire:model="name" type="text" placeholder="e.g. Year 4, JSS 1, SS 2"
                                class="w-full rounded-xl border-slate-300 bg-slate-50 px-4 py-3 focus:border-indigo-500 focus:ring-indigo-500">
                            <p class="mt-2 text-xs leading-5 text-slate-500">Use the name families and staff recognise on registers and reports.</p>
                            @error('name')<p class="mt-2 flex items-center text-sm text-red-600"><i class="fas fa-circle-exclamation mr-2"></i>{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                                <label class="text-sm font-bold text-slate-800">Class group <span class="text-red-500">*</span></label>
                                @can('create', App\Models\ClassGroup::class)
                                    @if(!$showInlineGroupForm)<button type="button" wire:click="openInlineGroupForm" class="text-sm font-bold text-indigo-700 hover:text-indigo-900"><i class="fas fa-plus-circle mr-1"></i>Create a new group</button>@endif
                                @endcan
                            </div>

                            @if($classGroups->isNotEmpty())
                                <select wire:model.live="class_group_id" class="w-full rounded-xl border-slate-300 bg-slate-50 px-4 py-3 focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Select a class group</option>
                                    @foreach ($classGroups as $group)<option value="{{ $group->id }}">{{ $group->name }} · {{ $group->classes_count }} class{{ $group->classes_count === 1 ? '' : 'es' }}</option>@endforeach
                                </select>
                            @else
                                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
                                    <div class="flex gap-3"><i class="fas fa-layer-group mt-1 text-amber-600"></i><div><p class="font-bold text-amber-950">No class group exists yet</p><p class="mt-1 text-sm leading-6 text-amber-800">Create your first group below, then it will be selected automatically for this class.</p></div></div>
                                </div>
                            @endif
                            @error('class_group_id')<p class="mt-2 flex items-center text-sm text-red-600"><i class="fas fa-circle-exclamation mr-2"></i>{{ $message }}</p>@enderror
                        </div>

                        @can('create', App\Models\ClassGroup::class)
                            @if($showInlineGroupForm || $classGroups->isEmpty())
                                <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-5">
                                    <div class="flex items-start justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-widest text-indigo-600">New class group</p><h3 class="mt-1 font-black text-indigo-950">Create without leaving this form</h3></div>@if($classGroups->isNotEmpty())<button type="button" wire:click="cancelInlineGroupForm" class="text-indigo-400 hover:text-indigo-700" aria-label="Close class group form"><i class="fas fa-times"></i></button>@endif</div>
                                    <div class="mt-4 flex flex-col gap-3 sm:flex-row"><input wire:model="newClassGroupName" wire:keydown.enter.prevent="createInlineClassGroup" type="text" placeholder="e.g. Primary, Junior Secondary" class="min-w-0 flex-1 rounded-xl border-indigo-200 bg-white px-4 py-3 focus:border-indigo-500 focus:ring-indigo-500"><button type="button" wire:click="createInlineClassGroup" class="rounded-xl bg-indigo-700 px-5 py-3 font-bold text-white hover:bg-indigo-800"><i class="fas fa-plus mr-2"></i>Create group</button></div>
                                    @error('newClassGroupName')<p class="mt-2 flex items-center text-sm text-red-600"><i class="fas fa-circle-exclamation mr-2"></i>{{ $message }}</p>@enderror
                                </div>
                            @endif
                        @endcan

                        <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:justify-end">
                            <button type="button" wire:click="showList" class="rounded-xl border border-slate-300 px-6 py-3 font-bold text-slate-700 hover:bg-slate-50">Cancel</button>
                            <button type="submit" @disabled(!$class_group_id) class="rounded-xl bg-indigo-700 px-6 py-3 font-black text-white transition hover:bg-indigo-800 disabled:cursor-not-allowed disabled:opacity-50"><i class="fas fa-check mr-2"></i>Create class</button>
                        </div>
                    </div>

                    <aside class="rounded-2xl bg-slate-50 p-5 2xl:self-start">
                        <p class="text-xs font-bold uppercase tracking-widest text-slate-500">How it is organised</p>
                        <ol class="mt-5 space-y-5">@foreach([['1','Class group','The broad school stage, such as Primary or Junior Secondary.'],['2','Class','The individual year or level, such as Year 4 or JSS 1.'],['3','Sections','Optional divisions such as A or B can be added after creating the class.']] as [$step,$label,$text])<li class="flex gap-3"><span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-black text-indigo-700">{{ $step }}</span><div><p class="text-sm font-bold text-slate-800">{{ $label }}</p><p class="mt-1 text-xs leading-5 text-slate-500">{{ $text }}</p></div></li>@endforeach</ol>
                    </aside>
                </form>
            </section>
        @endif

        {{-- EDIT VIEW --}}
        @if ($view === 'edit')
            <div class="max-w-2xl mx-auto bg-white rounded-lg shadow-lg overflow-hidden border border-gray-200">
                <div class="bg-amber-600 p-6">
                    <h2 class="text-2xl font-bold text-white flex items-center">
                        <div class="w-10 h-10 bg-white bg-opacity-20 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-edit"></i>
                        </div>
                        Edit Class
                    </h2>
                </div>

                <form wire:submit.prevent="update" class="p-6 space-y-6">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            Class Name <span class="text-red-500">*</span>
                        </label>
                        <input wire:model="name" type="text"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:border-amber-500 focus:ring-2 focus:ring-amber-200">
                        @error('name')
                            <p class="mt-2 text-sm text-red-600"><i class="fas fa-exclamation-circle mr-2"></i>{{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            Class Group <span class="text-red-500">*</span>
                        </label>
                        <select wire:model="class_group_id"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:border-amber-500 focus:ring-2 focus:ring-amber-200">
                            <option value="">Select a class group</option>
                            @foreach ($classGroups as $group)
                                <option value="{{ $group->id }}">{{ $group->name }}</option>
                            @endforeach
                        </select>
                        @error('class_group_id')
                            <p class="mt-2 text-sm text-red-600"><i class="fas fa-exclamation-circle mr-2"></i>{{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div class="flex space-x-3 pt-4">
                        <button type="submit"
                            class="flex-1 px-6 py-3 bg-amber-600 text-white rounded-lg hover:bg-amber-700 hover:shadow-lg transition-all font-bold">
                            <i class="fas fa-save mr-2"></i>Update Class
                        </button>
                        <button type="button" wire:click="showList"
                            class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-all font-semibold">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        @endif

        {{-- DETAILED VIEW --}}
        @if ($view === 'view')
            <div class="space-y-6">
                {{-- Header --}}
                <div class="bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden">
                    <div class="bg-indigo-600 p-6">
                        <div class="flex items-center justify-between flex-wrap gap-4">
                            <div>
                                <h2 class="text-3xl font-bold text-white">{{ $selectedClass->name }}</h2>
                                <div class="flex items-center gap-4 mt-2">
                                    <span class="text-indigo-100 flex items-center">
                                        <i class="fas fa-layer-group mr-2"></i>
                                        {{ $selectedClass->classGroup->name }}
                                    </span>
                                    @if(auth()->user()->school->academicYear)
                                        <span class="text-indigo-100 flex items-center">
                                            <i class="fas fa-calendar-alt mr-2"></i>
                                            {{ auth()->user()->school->academicYear->name }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="flex gap-3">
                                <button wire:click="showList"
                                    class="px-5 py-2.5 bg-white bg-opacity-20 text-white rounded-lg hover:bg-opacity-30 transition-all font-semibold">
                                    <i class="fas fa-arrow-left mr-2"></i>Back
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Management Actions --}}
                    <div class="p-4 bg-gray-50 border-b border-gray-200">
                        <div class="flex flex-wrap gap-3">
                            @can('update', $selectedClass)
                                <button wire:click="showCreateSection"
                                    class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-all text-sm font-semibold">
                                    <i class="fas fa-door-open mr-2"></i>Add Section
                                </button>
                                <button wire:click="showAddSubjects"
                                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-all text-sm font-semibold">
                                    <i class="fas fa-plus mr-2"></i>Add Subjects
                                </button>
                                <button wire:click="toggleTeacherModal"
                                    class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-all text-sm font-semibold">
                                    <i class="fas fa-user-plus mr-2"></i>Manage Teachers
                                </button>
                            @endcan
                        </div>
                    </div>

                    {{-- Quick Stats --}}
                    <div class="p-6 grid md:grid-cols-4 gap-4">
                        <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-blue-600 font-semibold">Students</p>
                                    <p class="text-2xl font-black text-blue-900">{{ $studentsCount }}</p>
                                </div>
                                <div class="w-12 h-12 bg-blue-600 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-users text-white text-xl"></i>
                                </div>
                            </div>
                        </div>

                        <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-green-600 font-semibold">Subjects</p>
                                    <p class="text-2xl font-black text-green-900">{{ $selectedClass->subjects->count() }}
                                    </p>
                                </div>
                                <div class="w-12 h-12 bg-green-600 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-book text-white text-xl"></i>
                                </div>
                            </div>
                        </div>

                        <div class="bg-orange-50 p-4 rounded-lg border border-orange-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-orange-600 font-semibold">Sections</p>
                                    <p class="text-2xl font-black text-orange-900">{{ $selectedClass->sections->count() }}
                                    </p>
                                </div>
                                <div class="w-12 h-12 bg-orange-600 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-door-open text-white text-xl"></i>
                                </div>
                            </div>
                        </div>

                        <div class="bg-purple-50 p-4 rounded-lg border border-purple-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-purple-600 font-semibold">Teachers</p>
                                    <p class="text-2xl font-black text-purple-900">{{ count($classTeachers) }}</p>
                                </div>
                                <div class="w-12 h-12 bg-purple-600 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-chalkboard-teacher text-white text-xl"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Class Teachers Section --}}
                <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden"
                    x-data="{ openTeachers: true }">
                    <button @click="openTeachers = !openTeachers"
                        class="w-full flex items-center justify-between p-6 bg-purple-50 hover:bg-purple-100 transition-all">
                        <h3 class="text-xl font-bold text-gray-800 flex items-center">
                            <div class="w-10 h-10 bg-purple-600 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-users text-white"></i>
                            </div>
                            Class Teachers
                            <span class="ml-3 px-3 py-1 bg-purple-600 text-white rounded-full text-sm font-medium">
                                {{ count($classTeachers) }}
                            </span>
                        </h3>
                        <i class="fas fa-chevron-down transition-transform" :class="{ 'rotate-180': openTeachers }"></i>
                    </button>
                    <div x-show="openTeachers" x-collapse class="p-5">
                        @if(empty($classTeachers))
                            <p class="text-center text-gray-500 py-4">No class teachers assigned yet</p>
                        @else
                            <div class="grid md:grid-cols-2 gap-4">
                                @foreach($classTeachers as $teacher)
                                    <div class="p-4 bg-purple-50 rounded-lg border border-purple-200 flex items-center space-x-4">
                                        <div
                                            class="w-12 h-12 bg-purple-600 rounded-full flex items-center justify-center text-white font-bold text-xl">
                                            {{ substr($teacher->name, 0, 1) }}
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-900">{{ $teacher->name }}</p>
                                            <p class="text-sm text-gray-600">{{ $teacher->email }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Sections --}}
                <div x-data="{ openSections: true }" class="bg-white rounded-lg shadow border border-gray-200">
                    <button @click="openSections = !openSections"
                        class="w-full flex items-center justify-between p-5 bg-orange-50 hover:bg-orange-100 transition-all border-b border-orange-200">
                        <h3 class="text-xl font-bold text-gray-800 flex items-center">
                            <div class="w-10 h-10 bg-orange-600 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-door-open text-white"></i>
                            </div>
                            Sections
                        </h3>
                        <i class="fas fa-chevron-down transition-transform" :class="{ 'rotate-180': openSections }"></i>
                    </button>

                    <div x-show="openSections" x-collapse class="p-5">
                        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            @forelse ($selectedClass->sections as $section)
                                <div class="bg-orange-50 p-5 rounded-lg border border-orange-200">
                                    <div class="flex items-start justify-between mb-3">
                                        <div class="w-12 h-12 bg-orange-600 rounded-lg flex items-center justify-center">
                                            <i class="fas fa-door-open text-white"></i>
                                        </div>
                                        @can('update', $selectedClass)
                                            <div class="flex gap-2">
                                                <button wire:click="showEditSection({{ $section->id }})"
                                                    class="text-orange-600 hover:text-orange-800">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button wire:click="deleteSection({{ $section->id }})"
                                                    wire:confirm="Delete this section?" class="text-red-600 hover:text-red-800">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        @endcan
                                    </div>
                                    <p class="font-bold text-gray-900 text-lg">{{ $section->name }}</p>
                                    <p class="text-sm text-gray-600 mt-1">{{ $section->studentsCount() }} students</p>
                                </div>
                            @empty
                                <div class="col-span-full text-center py-8 text-gray-500">No sections found</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                {{-- Students Management --}}
                <div class="bg-white rounded-lg shadow border border-gray-200">
                    <div class="flex items-center justify-between p-5 bg-blue-50 border-b border-blue-200">
                        <h3 class="text-xl font-bold text-gray-800 flex items-center">
                            <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-users text-white"></i>
                            </div>
                            Students ({{ $studentsCount }})
                        </h3>
                        @can('update', $selectedClass)
                            <button wire:click="showAddSubjects"
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-semibold">
                                <i class="fas fa-link mr-1"></i>Assign Subjects
                            </button>
                        @endcan
                    </div>

                    @if ($students && $students->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">
                                            <input type="checkbox" wire:model.live="selectAll" class="rounded">
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase w-12">
                                            #
                                        </th>
                                        <th wire:click="sortBy('name')"
                                            class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase cursor-pointer hover:bg-gray-100">
                                            <div class="flex items-center">
                                                <span>Name</span>
                                                @if($sortField === 'name')
                                                    <i
                                                        class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} ml-2 text-gray-600"></i>
                                                @else
                                                    <i class="fas fa-sort ml-2 text-gray-400"></i>
                                                @endif
                                            </div>
                                        </th>
                                        <th wire:click="sortBy('email')"
                                            class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase cursor-pointer hover:bg-gray-100">
                                            <div class="flex items-center">
                                                <span>Email</span>
                                                @if($sortField === 'email')
                                                    <i
                                                        class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} ml-2 text-gray-600"></i>
                                                @else
                                                    <i class="fas fa-sort ml-2 text-gray-400"></i>
                                                @endif
                                            </div>
                                        </th>
                                        <th wire:click="sortBy('section')"
                                            class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase cursor-pointer hover:bg-gray-100">
                                            <div class="flex items-center">
                                                <span>Section</span>
                                                @if($sortField === 'section')
                                                    <i
                                                        class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} ml-2 text-gray-600"></i>
                                                @else
                                                    <i class="fas fa-sort ml-2 text-gray-400"></i>
                                                @endif
                                            </div>
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">
                                            Subjects
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-100">
                                    @foreach ($students as $index => $student)
                                        <tr class="hover:bg-blue-50 transition-colors">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <input type="checkbox" wire:model.live="selectedStudents" value="{{ $student->id }}"
                                                    class="rounded">
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-medium">
                                                {{ $index + 1 }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div
                                                        class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold mr-3">
                                                        {{ substr($student->user->name, 0, 1) }}
                                                    </div>
                                                    <div>
                                                        <span class="font-medium text-gray-900">{{ $student->user->name }}</span>
                                                        @if($student->user->student_id)
                                                            <div class="text-xs text-gray-500 mt-1">
                                                                ID: {{ $student->user->student_id }}
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                @if($student->user->email)
                                                    <a href="mailto:{{ $student->user->email }}"
                                                        class="text-blue-600 hover:text-blue-800 hover:underline">
                                                        {{ $student->user->email }}
                                                    </a>
                                                @else
                                                    <span class="text-gray-400 italic">No email</span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                @if($student->section)
                                                    <span
                                                        class="px-3 py-1 bg-orange-100 text-orange-700 rounded-full text-xs font-medium cursor-pointer hover:bg-orange-200 transition-colors"
                                                        wire:click="showEditStudentSection({{ $student->id }})"
                                                        title="Click to change section">
                                                        {{ $student->section->name }}
                                                        <i class="fas fa-edit ml-1 text-xs"></i>
                                                    </span>
                                                @else
                                                    <span
                                                        class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-xs font-medium cursor-pointer hover:bg-gray-200 transition-colors"
                                                        wire:click="showEditStudentSection({{ $student->id }})"
                                                        title="Click to assign section">
                                                        No Section
                                                        <i class="fas fa-plus ml-1 text-xs"></i>
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span
                                                    class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-medium">
                                                    {{ $student->studentSubjects->count() }} subject(s)
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex gap-2">
                                                    <a href="{{ route('students.show', $student->user_id) }}"
                                                        class="px-3 py-1 bg-blue-50 text-blue-700 rounded hover:bg-blue-600 hover:text-white text-sm transition-colors"
                                                        title="View Student">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="{{ route('result.print', $student->id) }}"
                                                        class="px-3 py-1 bg-green-50 text-green-700 rounded hover:bg-green-600 hover:text-white text-sm transition-colors"
                                                        title="Print Results" target="_blank">
                                                        <i class="fas fa-file-alt"></i>
                                                    </a>
                                                    @if($student->user->email)
                                                        <a href="mailto:{{ $student->user->email }}"
                                                            class="px-3 py-1 bg-purple-50 text-purple-700 rounded hover:bg-purple-600 hover:text-white text-sm transition-colors"
                                                            title="Send Email">
                                                            <i class="fas fa-envelope"></i>
                                                        </a>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- Bulk Actions --}}
                        @if(count($selectedStudents) > 0)
                            <div class="p-4 bg-gray-50 border-t border-gray-200 space-y-4">
                                <div class="flex items-center gap-4">
                                    <span class="text-sm font-semibold text-gray-700">
                                        {{ count($selectedStudents) }} student(s) selected
                                    </span>
                                </div>

                                {{-- Section Move within Same Class --}}
                                <div class="flex flex-wrap items-center gap-4 p-4 bg-blue-50 rounded-lg border border-blue-200">
                                    <span class="text-sm font-semibold text-gray-700">Move within this class:</span>

                                    <div class="flex items-center gap-2">
                                        <select wire:model.live="targetSectionId"
                                            class="px-3 py-2 border border-gray-300 rounded-lg text-sm min-w-[200px]">
                                            <option value="">Select new section...</option>
                                            @foreach($selectedClass->sections as $section)
                                                <option value="{{ $section->id }}">{{ $section->name }}</option>
                                            @endforeach
                                        </select>

                                        <button wire:click="updateMultipleStudentsSection" @if(!$targetSectionId) disabled @endif
                                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:bg-blue-400 disabled:cursor-not-allowed text-sm font-semibold">
                                            <i class="fas fa-exchange-alt mr-1"></i>Change Section
                                        </button>
                                    </div>
                                </div>

                                {{-- Move to Different Class --}}
                                <div class="flex flex-wrap items-center gap-4 p-4 bg-green-50 rounded-lg border border-green-200">
                                    <span class="text-sm font-semibold text-gray-700">Move to different class:</span>

                                    <div class="flex flex-wrap items-center gap-2">
                                        <select wire:model.live="targetClassId"
                                            class="px-3 py-2 border border-gray-300 rounded-lg text-sm min-w-[200px]">
                                            <option value="">Select target class...</option>
                                            @foreach($allClasses->where('id', '!=', $selectedClass->id) as $class)
                                                <option value="{{ $class->id }}">{{ $class->name }}</option>
                                            @endforeach
                                        </select>

                                        @if($targetClassId)
                                            <select wire:model="targetSectionId"
                                                class="px-3 py-2 border border-gray-300 rounded-lg text-sm min-w-[200px]">
                                                <option value="">Select section (optional)...</option>
                                                @foreach($allClasses->find($targetClassId)->sections ?? [] as $section)
                                                    <option value="{{ $section->id }}">{{ $section->name }}</option>
                                                @endforeach
                                            </select>
                                        @endif

                                        <button wire:click="moveStudents" @if(!$targetClassId) disabled @endif
                                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:bg-green-400 disabled:cursor-not-allowed text-sm font-semibold">
                                            <i class="fas fa-exchange-alt mr-1"></i>Move to Class
                                        </button>
                                    </div>
                                </div>

                                {{-- Delete Option --}}
                                <div class="flex items-center justify-between pt-4 border-t border-gray-300">
                                    <span class="text-sm text-gray-600">Danger zone:</span>
                                    <button wire:click="deleteSelectedStudents"
                                        wire:confirm="Are you sure you want to delete {{ count($selectedStudents) }} student(s)? This action cannot be undone."
                                        class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm font-semibold">
                                        <i class="fas fa-trash mr-1"></i>Delete Selected Students
                                    </button>
                                </div>
                            </div>
                        @endif
                    @else
                        <div class="p-12 text-center">
                            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-user-slash text-gray-400 text-2xl"></i>
                            </div>
                            <p class="font-medium text-gray-700">No students found in this class</p>
                            <p class="text-sm text-gray-500 mt-1">Add students to this class to get started</p>
                        </div>
                    @endif
                </div>

                {{-- Subjects --}}
                <div x-data="{ openSubjects: true }" class="bg-white rounded-lg shadow border border-gray-200">
                    <button @click="openSubjects = !openSubjects"
                        class="w-full flex items-center justify-between p-5 bg-green-50 hover:bg-green-100 transition-all border-b border-green-200">
                        <h3 class="text-xl font-bold text-gray-800 flex items-center">
                            <div class="w-10 h-10 bg-green-600 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-book text-white"></i>
                            </div>
                            Subjects ({{ $selectedClass->subjects->count() }})
                        </h3>
                        <i class="fas fa-chevron-down transition-transform" :class="{ 'rotate-180': openSubjects }"></i>
                    </button>
                
                    <div x-show="openSubjects" x-collapse class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Subject Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Code</th>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Other Classes</th>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Teachers</th>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                @forelse ($selectedClass->subjects as $subject)
                                    <tr class="hover:bg-green-50 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="w-10 h-10 bg-green-600 rounded-full flex items-center justify-center text-white mr-3">
                                                    <i class="fas fa-book"></i>
                                                </div>
                                                <span class="font-medium text-gray-900">{{ $subject->name }}</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-3 py-1 bg-gray-100 text-gray-700 rounded text-xs font-medium">
                                                {{ $subject->short_name ?? 'N/A' }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            @php
                                                $otherClasses = $subject->classes->where('id', '!=', $selectedClass->id);
                                            @endphp
                                            @if($otherClasses->count() > 0)
                                                <div class="flex flex-wrap gap-1">
                                                    @foreach($otherClasses->take(3) as $class)
                                                        <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs">
                                                            {{ $class->name }}
                                                        </span>
                                                    @endforeach
                                                    @if($otherClasses->count() > 3)
                                                        <span class="px-2 py-1 bg-gray-100 text-gray-600 rounded text-xs">
                                                            +{{ $otherClasses->count() - 3 }} more
                                                        </span>
                                                    @endif
                                                </div>
                                            @else
                                                <span class="text-xs text-gray-500">Only in this class</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-3 py-1 bg-purple-100 text-purple-700 rounded-full text-xs font-medium">
                                                {{ $subject->teachers->count() }} {{ Str::plural('teacher', $subject->teachers->count()) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex gap-2">
                                                <a href="{{ route('subjects.show', $subject->id) }}"
                                                    class="px-3 py-1 bg-green-50 text-green-700 rounded hover:bg-green-600 hover:text-white text-sm"
                                                    title="View Subject">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                @can('update', $selectedClass)
                                                    <button wire:click="removeSubjectFromClass({{ $subject->id }})"
                                                        wire:confirm="Remove {{ $subject->name }} from this class? Students will lose access to this subject."
                                                        class="px-3 py-1 bg-red-50 text-red-700 rounded hover:bg-red-600 hover:text-white text-sm"
                                                        title="Remove from Class">
                                                        <i class="fas fa-unlink"></i>
                                                    </button>
                                                @endcan
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-6 py-12 text-center">
                                            <div class="text-gray-500">
                                                <i class="fas fa-book-open text-4xl mb-3"></i>
                                                <p class="font-medium">No subjects assigned yet</p>
                                                <p class="text-sm mt-1">Click "Add Subjects" to assign existing subjects to this class</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Quick Links --}}
                <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                    <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                        <div class="w-10 h-10 bg-indigo-600 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-link text-white"></i>
                        </div>
                        Quick Links
                    </h3>
                    <div class="grid md:grid-cols-4 gap-4">
                        @if(Route::has('result.view.class'))
                            <a href="{{ route('result.view.class') }}?class={{ $selectedClass->id }}"
                                class="p-4 bg-blue-50 rounded-lg border border-blue-200 hover:bg-blue-100 transition-all">
                                <i class="fas fa-chart-line text-blue-600 text-2xl mb-2"></i>
                                <p class="font-semibold text-gray-900">View Results</p>
                                <p class="text-sm text-gray-600">Check class performance</p>
                            </a>
                        @endif

                        @if(Route::has('result.upload.bulk'))
                            <a href="{{ route('result.upload.bulk') }}?class={{ $selectedClass->id }}"
                                class="p-4 bg-green-50 rounded-lg border border-green-200 hover:bg-green-100 transition-all">
                                <i class="fas fa-upload text-green-600 text-2xl mb-2"></i>
                                <p class="font-semibold text-gray-900">Upload Results</p>
                                <p class="text-sm text-gray-600">Bulk result upload</p>
                            </a>
                        @endif

                        @if(Route::has('timetables.index'))
                            <a href="{{ route('timetables.index') }}?class={{ $selectedClass->id }}"
                                class="p-4 bg-purple-50 rounded-lg border border-purple-200 hover:bg-purple-100 transition-all">
                                <i class="fas fa-calendar-alt text-purple-600 text-2xl mb-2"></i>
                                <p class="font-semibold text-gray-900">Timetable</p>
                                <p class="text-sm text-gray-600">Manage class schedule</p>
                            </a>
                        @endif

                        @if(Route::has('result.print-class') && auth()->user()->school->academicYear)
                            <a href="{{ route('result.print-class', ['academicYearId' => auth()->user()->school->academic_year_id, 'semesterId' => auth()->user()->school->academicYear->semesters->first()->id ?? 1, 'classId' => $selectedClass->id]) }}"
                                class="p-4 bg-orange-50 rounded-lg border border-orange-200 hover:bg-orange-100 transition-all"
                                target="_blank">
                                <i class="fas fa-print text-orange-600 text-2xl mb-2"></i>
                                <p class="font-semibold text-gray-900">Print Reports</p>
                                <p class="text-sm text-gray-600">Batch print results</p>
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    {{-- MODALS --}}

    {{-- Teacher Assignment Modal --}}
    @if($showTeacherModal && $view === 'view')
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
            wire:click.self="toggleTeacherModal">
            <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-hidden" @click.stop>
                <div class="bg-purple-600 p-6 flex items-center justify-between">
                    <h3 class="text-2xl font-bold text-white">Manage Class Teachers</h3>
                    <button wire:click="toggleTeacherModal" class="text-white hover:text-gray-200">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto" style="max-height: calc(90vh - 180px);">
                    <p class="text-gray-600 mb-4">Select teachers to assign as class teachers for {{ $selectedClass->name }}
                    </p>

                    <div class="space-y-2">
                        @foreach($teachers as $teacher)
                            <label
                                class="flex items-center p-4 bg-gray-50 rounded-lg border border-gray-200 hover:bg-purple-50 hover:border-purple-300 cursor-pointer transition-all">
                                <input type="checkbox" wire:model="selectedTeachers" value="{{ $teacher->id }}"
                                    class="w-5 h-5 text-purple-600 rounded">
                                <div class="ml-4 flex items-center flex-1">
                                    <div
                                        class="w-10 h-10 bg-purple-600 rounded-full flex items-center justify-center text-white font-bold mr-3">
                                        {{ substr($teacher->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-900">{{ $teacher->name }}</p>
                                        <p class="text-sm text-gray-600">{{ $teacher->email }}</p>
                                    </div>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="p-6 bg-gray-50 border-t border-gray-200 flex justify-end gap-3">
                    <button wire:click="toggleTeacherModal"
                        class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 font-semibold">
                        Cancel
                    </button>
                    <button wire:click="updateClassTeachers"
                        class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 font-semibold">
                        <i class="fas fa-save mr-2"></i>Save Changes
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Section Management Modal --}}
    @if($showSectionModal && $view === 'view')
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
            wire:click.self="$set('showSectionModal', false)">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4" @click.stop>
                <div class="bg-orange-600 p-6 flex items-center justify-between">
                    <h3 class="text-2xl font-bold text-white">
                        {{ $editingSectionId ? 'Edit' : 'Create' }} Section
                    </h3>
                    <button wire:click="$set('showSectionModal', false)" class="text-white hover:text-gray-200">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>

                <form wire:submit.prevent="saveSection" class="p-6">
                    <div class="mb-4">
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            Section Name <span class="text-red-500">*</span>
                        </label>
                        <input wire:model="sectionName" type="text" placeholder="e.g., Section A"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:border-orange-500 focus:ring-2 focus:ring-orange-200">
                        @error('sectionName')
                            <p class="mt-2 text-sm text-red-600"><i class="fas fa-exclamation-circle mr-2"></i>{{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div class="flex justify-end gap-3">
                        <button type="button" wire:click="$set('showSectionModal', false)"
                            class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 font-semibold">
                            Cancel
                        </button>
                        <button type="submit"
                            class="px-6 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 font-semibold">
                            <i class="fas fa-save mr-2"></i>{{ $editingSectionId ? 'Update' : 'Create' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Subject Management Modal --}}

    @if($showSubjectModal && $view === 'view')
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
        wire:click.self="$set('showSubjectModal', false)">
        <div class="bg-white rounded-lg shadow-xl max-w-3xl w-full mx-4 max-h-[90vh] overflow-hidden" 
             x-data="{ selectedCount: @entangle('selectedSubjectIds').live }"
             @click.stop>
            <div class="bg-green-600 p-6 flex items-center justify-between">
                <h3 class="text-2xl font-bold text-white">
                    Add Subjects to {{ $selectedClass->name }}
                </h3>
                <button wire:click="$set('showSubjectModal', false)" class="text-white hover:text-gray-200">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>

            <div class="p-6 space-y-4 overflow-y-auto" style="max-height: calc(90vh - 180px);">
                {{-- Search Box --}}
                <div>
                    <input wire:model.live="subjectSearch" 
                           type="text" 
                           placeholder="Search subjects..."
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:border-green-500 focus:ring-2 focus:ring-green-200">
                </div>

                {{-- Available Subjects List --}}
                @if($this->filteredAvailableSubjects->count() > 0)
                    <div class="space-y-2 max-h-96 overflow-y-auto">
                        @foreach($this->filteredAvailableSubjects as $subject)
                            <div class="border rounded-lg p-4 hover:bg-gray-50 transition-colors cursor-pointer"
                                 wire:click="toggleSubjectSelection({{ $subject->id }})">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-3 mb-2">
                                            <input type="checkbox" 
                                                   wire:model.live="selectedSubjectIds"
                                                   value="{{ $subject->id }}"
                                                   class="w-5 h-5 text-green-600 rounded"
                                                   onclick="event.stopPropagation()">
                                            <div>
                                                <h4 class="font-bold text-gray-900">{{ $subject->name }}</h4>
                                                @if($subject->short_name)
                                                    <p class="text-sm text-gray-600">Code: {{ $subject->short_name }}</p>
                                                @endif
                                            </div>
                                        </div>
                                        
                                        {{-- Show which classes already have this subject --}}
                                        @if($subject->classes->count() > 0)
                                            <div class="flex flex-wrap gap-2 mt-2">
                                                <span class="text-xs text-gray-500">Currently in:</span>
                                                @foreach($subject->classes as $class)
                                                    <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs">
                                                        {{ $class->name }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @else
                                            <p class="text-xs text-gray-500 mt-2">Not assigned to any class yet</p>
                                        @endif
                                    </div>
                                    
                                    @if(in_array($subject->id, $selectedSubjectIds))
                                        <div class="ml-3">
                                            <span class="inline-flex items-center px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm font-medium">
                                                <i class="fas fa-check mr-1"></i>Selected
                                            </span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-inbox text-4xl mb-3"></i>
                        <p>{{ $subjectSearch ? 'No subjects found matching your search' : 'All available subjects have been added to this class' }}</p>
                    </div>
                @endif

                {{-- Selected Count --}}
                <div x-show="selectedCount.length > 0" 
                     x-transition
                     class="bg-green-50 border border-green-200 rounded-lg p-3">
                    <p class="text-sm text-green-800 font-medium">
                        <i class="fas fa-check-circle mr-2"></i>
                        <span x-text="selectedCount.length"></span> subject(s) selected
                    </p>
                </div>
            </div>

            {{-- Footer Actions --}}
            <div class="p-6 bg-gray-50 border-t border-gray-200 flex justify-between items-center">
                <button type="button" wire:click="$set('showSubjectModal', false)"
                    class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 font-semibold">
                    Cancel
                </button>
                <button type="button" 
                        wire:click="addSelectedSubjects"
                        class="px-6 py-2 text-white rounded-lg font-semibold transition-all"
                        :class="selectedCount.length > 0 ? 'bg-green-600 hover:bg-green-700' : 'bg-gray-400 cursor-not-allowed'"
                        :disabled="selectedCount.length === 0">
                    <i class="fas fa-plus mr-2"></i>
                    <span>Add </span>
                    <span x-show="selectedCount.length > 0" x-text="selectedCount.length"></span>
                    <span> Subject(s)</span>
                </button>
            </div>
        </div>
    </div>
@endif    {{-- Student Section Change Modal --}}
    @if($editingStudentSectionId && $view === 'view')
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" x-data
            x-init="$wire.dispatch('open-modal', {id: 'student-section-modal'})">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4" @click.stop>
                <div class="bg-blue-600 p-6 flex items-center justify-between">
                    <h3 class="text-2xl font-bold text-white">Change Student Section</h3>
                    <button wire:click="$dispatch('close-modal', {id: 'student-section-modal'})"
                        class="text-white hover:text-gray-200">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>

                <form wire:submit.prevent="updateStudentSection" class="p-6">
                    <div class="mb-4">
                        <p class="text-sm text-gray-600 mb-4">
                            Change section for this student
                        </p>

                        <div class="mb-4">
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                Select Section
                            </label>
                            <select wire:model="editingStudentSection"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                                <option value="">No Section</option>
                                @foreach($selectedClass->sections as $section)
                                    <option value="{{ $section->id }}">{{ $section->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3">
                        <button type="button" wire:click="$dispatch('close-modal', {id: 'student-section-modal'})"
                            class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 font-semibold">
                            Cancel
                        </button>
                        <button type="submit"
                            class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold">
                            <i class="fas fa-save mr-2"></i>Update Section
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
