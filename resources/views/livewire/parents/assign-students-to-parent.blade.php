<div class="space-y-6">
    
    <!-- Header -->
    <div class="bg-teal-600 rounded-lg shadow-lg overflow-hidden">
        <div class="px-6 py-8">
            <div class="flex flex-col md:flex-row items-center md:items-start gap-6">
                <img src="{{ $parent->profile_photo_url }}" alt="{{ $parent->name }}" 
                     class="w-24 h-24 rounded-full border-4 border-white shadow-lg object-cover">
                
                <div class="flex-1 text-white text-center md:text-left">
                    <h2 class="text-3xl font-bold mb-2">Manage students linked to {{ $parent->name }}</h2>
                    <p class="text-white/80">Current guardian links: {{ $assignedStudents->count() }}</p>
                </div>
                
                <a href="{{ route('parents.show', $parent->id) }}" 
                   class="px-4 py-2 bg-white/20 backdrop-blur-sm hover:bg-white/30 text-white rounded-lg transition">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Profile
                </a>
            </div>
        </div>
    </div>

    <!-- Assigned Students -->
    @if($assignedStudents->isNotEmpty())
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="dashboard-section-heading bg-green-600 px-6 py-4">
                <h2 class="text-xl font-bold text-white">
                    <i class="fas fa-check-circle mr-2"></i>Assigned Students ({{ $assignedStudents->count() }})
                </h2>
            </div>
            
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($assignedStudents as $student)
                        <div class="bg-green-50 border-2 border-green-200 rounded-lg p-4">
                            <div class="flex items-start gap-3">
                                <img src="{{ $student->profile_photo_url }}" 
                                     alt="{{ $student->name }}" 
                                     class="w-12 h-12 rounded-full object-cover border-2 border-green-300">
                                <div class="flex-1">
                                    <h4 class="font-bold text-gray-900">{{ $student->name }}</h4>
                                    <p class="text-sm text-gray-600">
                                        {{ $student->studentRecord->myClass->name ?? 'N/A' }}
                                        @if($student->studentRecord && $student->studentRecord->section)
                                            - {{ $student->studentRecord->section->name }}
                                        @endif
                                    </p>
                                    <p class="text-xs text-gray-500 mt-1">
                                        {{ $student->studentRecord->admission_number ?? 'N/A' }}
                                    </p>
                                    <p class="mt-2 text-xs font-semibold uppercase tracking-wide text-emerald-700">
                                        {{ str_replace('_', ' ', $student->pivot->relationship_type ?: 'guardian') }}
                                        @if($student->pivot->is_primary_contact) · Primary contact @endif
                                    </p>
                                </div>
                            </div>
                            <div class="mt-3 rounded-lg bg-emerald-100 px-3 py-2 text-center text-sm font-semibold text-emerald-800">
                                <i class="fas fa-shield-halved mr-2"></i>Verified guardian relationship
                            </div>
                            <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                                @foreach([
                                    'can_view_academics' => 'Academics',
                                    'can_view_attendance' => 'Attendance',
                                    'can_view_portfolio' => 'Portfolio',
                                    'receives_digest' => 'Digest',
                                    'can_consent_events' => 'Event consent',
                                ] as $field => $label)
                                    <button type="button" wire:click="toggleAuthority({{ $student->id }}, '{{ $field }}')"
                                            class="rounded-lg border px-2 py-2 font-semibold {{ $student->pivot->{$field} ? 'border-emerald-300 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-white text-slate-500' }}">
                                        <i class="fas {{ $student->pivot->{$field} ? 'fa-check' : 'fa-minus' }} mr-1" aria-hidden="true"></i>{{ $label }}
                                    </button>
                                @endforeach
                            </div>
                            @unless($student->pivot->is_primary_contact)
                                <button type="button" wire:click="makePrimary({{ $student->id }})" wire:confirm="Make this guardian the primary contact for this student?"
                                        class="mt-3 w-full rounded-lg border border-sky-300 bg-sky-50 px-3 py-2 text-sm font-semibold text-sky-800 hover:bg-sky-100">
                                    Make primary contact
                                </button>
                            @endunless
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <!-- Available Students to Assign -->
    <div class="bg-white rounded-lg shadow-lg overflow-hidden">
        <div class="dashboard-section-heading bg-blue-600 px-6 py-4">
            <h2 class="text-xl font-bold text-white">
                <i class="fas fa-user-plus mr-2"></i>Add another guardian relationship
            </h2>
        </div>

        <div class="grid gap-4 border-b bg-sky-50 p-6 md:grid-cols-2 lg:grid-cols-3">
            <label class="block">
                <span class="mb-2 block text-sm font-semibold text-slate-700">Relationship</span>
                <select wire:model="relationshipType" class="w-full rounded-lg border-slate-300">
                    @foreach(\App\Services\GuardianLinkService::RELATIONSHIP_TYPES as $type)
                        <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                    @endforeach
                </select>
            </label>
            <div class="space-y-2 text-sm text-slate-700">
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="canViewAcademics" class="rounded text-sky-700"> Academic records</label>
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="canViewAttendance" class="rounded text-sky-700"> Attendance records</label>
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="canViewPortfolio" class="rounded text-sky-700"> Approved portfolio items</label>
            </div>
            <div class="space-y-2 text-sm text-slate-700">
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="receivesDigest" class="rounded text-sky-700"> Receive weekly digest when enabled</label>
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="canConsentEvents" class="rounded text-sky-700"> May provide event consent</label>
            </div>
            @error('relationshipType') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <!-- Filters -->
        <div class="p-6 bg-gray-50 border-b">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <input wire:model.live.debounce.300ms="search" 
                       type="text" 
                       placeholder="Search students..." 
                       class="rounded-lg border-2 border-gray-300 px-4 py-2.5 focus:ring-2 focus:ring-blue-500">
                
                <select wire:model.live="selectedClass" 
                        class="rounded-lg border-2 border-gray-300 px-4 py-2.5 focus:ring-2 focus:ring-blue-500">
                    <option value="">All Classes</option>
                    @foreach($classes as $class)
                        <option value="{{ $class->id }}">{{ $class->name }}</option>
                    @endforeach
                </select>

                @if($sections->isNotEmpty())
                    <select wire:model.live="selectedSection" 
                            class="rounded-lg border-2 border-gray-300 px-4 py-2.5 focus:ring-2 focus:ring-blue-500">
                        <option value="">All Sections</option>
                        @foreach($sections as $section)
                            <option value="{{ $section->id }}">{{ $section->name }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
        </div>

        <!-- Student List -->
        <div class="p-6">
            @if($availableStudents->isNotEmpty())
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($availableStudents as $student)
                        <div class="bg-gray-50 border-2 border-gray-200 rounded-lg p-4 hover:border-blue-400 transition">
                            <div class="flex items-start gap-3">
                                <img src="{{ $student->profile_photo_url }}" 
                                     alt="{{ $student->name }}" 
                                     class="w-12 h-12 rounded-full object-cover border-2 border-gray-300">
                                <div class="flex-1">
                                    <h4 class="font-bold text-gray-900">{{ $student->name }}</h4>
                                    <p class="text-sm text-gray-600">
                                        {{ $student->studentRecord->myClass->name ?? 'N/A' }}
                                        @if($student->studentRecord && $student->studentRecord->section)
                                            - {{ $student->studentRecord->section->name }}
                                        @endif
                                    </p>
                                    <p class="text-xs text-gray-500 mt-1">
                                        {{ $student->studentRecord->admission_number ?? 'N/A' }}
                                    </p>
                                </div>
                            </div>
                            <button wire:click="assignStudent({{ $student->id }})" 
                                    class="mt-3 w-full px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm font-semibold">
                                <i class="fas fa-plus mr-2"></i>Link to Guardian
                            </button>
                        </div>
                    @endforeach
                </div>

                <!-- Pagination -->
                <div class="mt-6">
                    {{ $availableStudents->links() }}
                </div>
            @else
                <div class="text-center py-12">
                    <i class="fas fa-search text-gray-300 text-5xl mb-4"></i>
                    <p class="text-lg text-gray-500">No students found</p>
                    @if($search || $selectedClass || $selectedSection)
                        <button wire:click="$set('search', ''); $set('selectedClass', ''); $set('selectedSection', '')" 
                                class="mt-4 px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                            Clear Filters
                        </button>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
