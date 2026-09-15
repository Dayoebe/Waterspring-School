<div x-data="{ activeTab: @entangle('activeTab') }" class="space-y-6">
    
    <!-- Header Card -->
    <div class="bg-teal-600 rounded-lg shadow-lg overflow-hidden">
        <div class="px-6 py-8">
            <div class="flex flex-col md:flex-row items-center md:items-start gap-6">
                <img src="{{ $student->profile_photo_url }}" alt="{{ $student->name }}" 
                     class="w-32 h-32 rounded-full border-4 border-white shadow-lg object-cover">
                
                <div class="flex-1 text-white text-center md:text-left">
                    <h2 class="text-3xl font-bold mb-2">{{ $student->name }}</h2>
                    <div class="flex flex-wrap gap-4 justify-center md:justify-start text-sm">
                        <span class="flex items-center">
                            <i class="fas fa-envelope mr-2"></i>{{ $student->email }}
                        </span>
                        <span class="flex items-center">
                            <i class="fas fa-id-card mr-2"></i>{{ $student->studentRecord->admission_number ?? 'N/A' }}
                        </span>
                        <span class="flex items-center">
                            <i class="fas fa-school mr-2"></i>{{ $student->studentRecord->myClass->name ?? 'N/A' }}
                        </span>
                        @if($student->studentRecord->section)
                            <span class="flex items-center">
                                <i class="fas fa-layer-group mr-2"></i>{{ $student->studentRecord->section->name }}
                            </span>
                        @endif
                    </div>
                </div>
                
                <div class="flex gap-3">
                    <a href="{{ route('students.index') }}" 
                       class="px-4 py-2 bg-white/20 backdrop-blur-sm hover:bg-white/30 text-white rounded-lg transition">
                        <i class="fas fa-arrow-left mr-2"></i>Back
                    </a>
                    <button wire:click="printProfile" 
                            class="px-4 py-2 bg-white text-black rounded-lg font-semibold shadow hover:shadow-lg transition">
                        <i class="fas fa-print mr-2"></i>Print Profile
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="flex border-b overflow-x-auto">
            <button @click="activeTab = 'profile'" 
                    :class="activeTab === 'profile' ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-gray-600 hover:text-indigo-600'"
                    class="px-6 py-4 font-semibold transition whitespace-nowrap">
                <i class="fas fa-user mr-2"></i>Profile
            </button>
            <button @click="activeTab = 'academic'" 
                    :class="activeTab === 'academic' ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-gray-600 hover:text-indigo-600'"
                    class="px-6 py-4 font-semibold transition whitespace-nowrap">
                <i class="fas fa-graduation-cap mr-2"></i>Academic Info
            </button>
            <button @click="activeTab = 'parent'"
                    :class="activeTab === 'parent' ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-gray-600 hover:text-indigo-600'"
                    class="px-6 py-4 font-semibold transition whitespace-nowrap">
                <i class="fas fa-people-roof mr-2"></i>Parent
            </button>
            <button @click="activeTab = 'fees'" 
                    :class="activeTab === 'fees' ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-gray-600 hover:text-indigo-600'"
                    class="px-6 py-4 font-semibold transition whitespace-nowrap">
                <i class="fas fa-money-bill-wave mr-2"></i>Fee Invoices
            </button>
        </div>

        <!-- Tab Content -->
        <div class="p-6">
            <!-- Profile Tab -->
            <div x-show="activeTab === 'profile'" x-transition>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <h3 class="text-xl font-bold text-gray-900 mb-4">Personal Information</h3>
                        
                        <div class="flex justify-between border-b pb-3">
                            <span class="font-semibold text-gray-600">Full Name:</span>
                            <span class="text-gray-900">{{ $student->name }}</span>
                        </div>
                        <div class="flex justify-between border-b pb-3">
                            <span class="font-semibold text-gray-600">Email:</span>
                            <span class="text-gray-900">{{ $student->email }}</span>
                        </div>
                        <div class="flex justify-between border-b pb-3">
                            <span class="font-semibold text-gray-600">Gender:</span>
                            <span class="text-gray-900">{{ ucfirst($student->gender ?? 'N/A') }}</span>
                        </div>
                        <div class="flex justify-between border-b pb-3">
                            <span class="font-semibold text-gray-600">Birthday:</span>
                            <span class="text-gray-900">
                                @if($student->birthday)
                                    @if($student->birthday instanceof \Carbon\Carbon)
                                        {{ $student->birthday->format('M d, Y') }}
                                    @else
                                        {{ \Carbon\Carbon::parse($student->birthday)->format('M d, Y') }}
                                    @endif
                                @else
                                    N/A
                                @endif
                            </span>
                        </div>
                        <div class="flex justify-between border-b pb-3">
                            <span class="font-semibold text-gray-600">Phone:</span>
                            <span class="text-gray-900">{{ $student->phone ?? 'N/A' }}</span>
                        </div>
                        <div class="flex justify-between border-b pb-3">
                            <span class="font-semibold text-gray-600">Blood Group:</span>
                            <span class="text-gray-900">{{ $student->blood_group ?? 'N/A' }}</span>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <h3 class="text-xl font-bold text-gray-900 mb-4">Other Information</h3>
                        
                        <div class="flex justify-between border-b pb-3">
                            <span class="font-semibold text-gray-600">Religion:</span>
                            <span class="text-gray-900">{{ $student->religion ?? 'N/A' }}</span>
                        </div>
                        <div class="flex justify-between border-b pb-3">
                            <span class="font-semibold text-gray-600">Nationality:</span>
                            <span class="text-gray-900">{{ $student->nationality ?? 'N/A' }}</span>
                        </div>
                        <div class="flex justify-between border-b pb-3">
                            <span class="font-semibold text-gray-600">State:</span>
                            <span class="text-gray-900">{{ $student->state ?? 'N/A' }}</span>
                        </div>
                        <div class="flex justify-between border-b pb-3">
                            <span class="font-semibold text-gray-600">City:</span>
                            <span class="text-gray-900">{{ $student->city ?? 'N/A' }}</span>
                        </div>
                        <div class="flex justify-between border-b pb-3">
                            <span class="font-semibold text-gray-600">Address:</span>
                            <span class="text-gray-900 text-right">{{ $student->address ?? 'N/A' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div x-show="activeTab === 'parent'" x-transition>
                @if($student->parents->isNotEmpty())
                    @foreach($student->parents as $parent)
                        <a href="{{ route('parents.show', $parent->id) }}" class="flex max-w-2xl flex-col gap-4 rounded-2xl border border-sky-200 bg-sky-50 p-5 transition hover:border-sky-400 hover:shadow-md sm:flex-row sm:items-center">
                            <img src="{{ $parent->profile_photo_url }}" alt="{{ $parent->name }}" class="h-16 w-16 rounded-full border-2 border-white object-cover shadow">
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-sky-700">Linked parent or guardian</p>
                                <h3 class="mt-1 text-xl font-bold text-gray-900">{{ $parent->name }}</h3>
                                <p class="mt-1 text-sm text-gray-600">{{ $parent->email }}@if($parent->phone) · {{ $parent->phone }}@endif</p>
                            </div>
                            <span class="font-semibold text-sky-700">View profile <i class="fas fa-arrow-right ml-1"></i></span>
                        </a>
                    @endforeach
                @else
                    <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-6 py-12 text-center">
                        <i class="fas fa-people-roof text-4xl text-gray-300"></i>
                        <p class="mt-3 font-semibold text-gray-700">No parent has been linked to this student.</p>
                    </div>
                @endif
            </div>

            <!-- Academic Tab -->
            <div x-show="activeTab === 'academic'" x-transition>
                <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-lg p-6">
                    <h3 class="text-xl font-bold text-gray-900 mb-6">Academic Information</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="bg-white p-6 rounded-lg shadow">
                            <div class="flex items-center mb-4">
                                <i class="fas fa-school text-3xl text-magenta-600 mr-4"></i>
                                <div>
                                    <p class="text-sm text-gray-600">Current Class</p>
                                    <p class="text-xl font-bold text-gray-900">
                                        {{ $student->studentRecord->myClass->name ?? 'N/A' }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white p-6 rounded-lg shadow">
                            <div class="flex items-center mb-4">
                                <i class="fas fa-layer-group text-3xl text-blue-600 mr-4"></i>
                                <div>
                                    <p class="text-sm text-gray-600">Section</p>
                                    <p class="text-xl font-bold text-gray-900">
                                        {{ $student->studentRecord->section->name ?? 'Not Assigned' }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white p-6 rounded-lg shadow">
                            <div class="flex items-center mb-4">
                                <i class="fas fa-id-card text-3xl text-green-600 mr-4"></i>
                                <div>
                                    <p class="text-sm text-gray-600">Admission Number</p>
                                    <p class="text-xl font-bold text-gray-900">
                                        {{ $student->studentRecord->admission_number ?? 'N/A' }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white p-6 rounded-lg shadow">
                            <div class="flex items-center mb-4">
                                <i class="fas fa-calendar-alt text-3xl text-orange-600 mr-4"></i>
                                <div>
                                    <p class="text-sm text-gray-600">Admission Date</p>
                                    <p class="text-xl font-bold text-gray-900">
                                        @if($student->studentRecord->admission_date)
                                            @if($student->studentRecord->admission_date instanceof \Carbon\Carbon)
                                                {{ $student->studentRecord->admission_date->format('M d, Y') }}
                                            @else
                                                {{ \Carbon\Carbon::parse($student->studentRecord->admission_date)->format('M d, Y') }}
                                            @endif
                                        @else
                                            N/A
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if($student->studentRecord->is_graduated)
                        <div class="mt-6 bg-green-100 border-l-4 border-green-500 p-4 rounded">
                            <div class="flex items-center">
                                <i class="fas fa-graduation-cap text-green-600 text-2xl mr-3"></i>
                                <span class="font-bold text-green-800">This student has graduated</span>
                            </div>
                        </div>
                    @endif

                    @if(auth()->user()->hasAnyRole(['super-admin', 'super_admin']))
                        <div class="mt-6 rounded-2xl border border-amber-200 bg-white p-6 shadow-sm">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-amber-700">Class Activity Status</p>
                                    <h4 class="mt-1 text-lg font-bold text-gray-900">Exclude a student without changing class history</h4>
                                    <p class="mt-1 max-w-3xl text-sm leading-6 text-gray-600">
                                        Use this only after confirming the student transferred, withdrew, became inactive, or was suspended.
                                        Earlier results and class placement remain unchanged. The student is excluded from the selected term onward.
                                    </p>
                                </div>
                                <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-800">Super admin only</span>
                            </div>

                            @if(session()->has('success'))
                                <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                                    {{ session('success') }}
                                </div>
                            @endif

                            <form wire:submit="savePeriodStatus" class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                <label class="block">
                                    <span class="mb-1 block text-sm font-semibold text-gray-700">Academic year</span>
                                    <select wire:model.live="statusAcademicYearId" class="w-full rounded-xl border-gray-300 px-3 py-2.5 text-sm">
                                        <option value="">Select year</option>
                                        @foreach($statusAcademicYears as $year)
                                            <option value="{{ $year->id }}">{{ $year->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('statusAcademicYearId') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                                </label>

                                <label class="block">
                                    <span class="mb-1 block text-sm font-semibold text-gray-700">Effective from</span>
                                    <select wire:model="statusSemesterId" class="w-full rounded-xl border-gray-300 px-3 py-2.5 text-sm">
                                        <option value="">Beginning of academic year</option>
                                        @foreach($statusSemesters as $semester)
                                            <option value="{{ $semester->id }}">{{ trim($semester->name) }}</option>
                                        @endforeach
                                    </select>
                                </label>

                                <label class="block">
                                    <span class="mb-1 block text-sm font-semibold text-gray-700">Status</span>
                                    <select wire:model="enrollmentStatus" class="w-full rounded-xl border-gray-300 px-3 py-2.5 text-sm">
                                        <option value="withdrawn">Withdrawn</option>
                                        <option value="transferred">Transferred to another school</option>
                                        <option value="inactive">Inactive</option>
                                        <option value="suspended">Suspended</option>
                                    </select>
                                </label>

                                <label class="block">
                                    <span class="mb-1 block text-sm font-semibold text-gray-700">Reason</span>
                                    <input wire:model="statusReason" type="text" maxlength="100"
                                        placeholder="e.g. Parent confirmed transfer"
                                        class="w-full rounded-xl border-gray-300 px-3 py-2.5 text-sm">
                                    @error('statusReason') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                                </label>

                                <label class="block md:col-span-2 xl:col-span-3">
                                    <span class="mb-1 block text-sm font-semibold text-gray-700">Internal note <span class="font-normal text-gray-500">(optional)</span></span>
                                    <textarea wire:model="statusNotes" rows="2" maxlength="1000"
                                        placeholder="Add confirmation details for the school record"
                                        class="w-full rounded-xl border-gray-300 px-3 py-2.5 text-sm"></textarea>
                                </label>

                                <div class="flex items-end">
                                    <button type="submit" wire:confirm="Confirm this student should be excluded from class activities from the selected period onward?"
                                        class="inline-flex w-full items-center justify-center rounded-xl bg-amber-600 px-5 py-3 text-sm font-bold text-white hover:bg-amber-700">
                                        <i class="fas fa-user-slash mr-2"></i>Save activity status
                                    </button>
                                </div>
                            </form>

                            @if($statusHistory->isNotEmpty())
                                <div class="mt-6 overflow-x-auto">
                                    <table class="min-w-full text-left text-sm">
                                        <thead class="border-b bg-gray-50 text-xs uppercase tracking-wider text-gray-600">
                                            <tr>
                                                <th class="px-4 py-3">Year</th>
                                                <th class="px-4 py-3">Effective from</th>
                                                <th class="px-4 py-3">Status</th>
                                                <th class="px-4 py-3">Reason</th>
                                                <th class="px-4 py-3 text-right">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y">
                                            @foreach($statusHistory as $periodStatus)
                                                <tr>
                                                    <td class="px-4 py-3 font-semibold">{{ $periodStatus->academicYear?->name }}</td>
                                                    <td class="px-4 py-3">{{ trim($periodStatus->semester?->name ?? 'Beginning of year') }}</td>
                                                    <td class="px-4 py-3">
                                                        <span class="rounded-full bg-rose-100 px-2.5 py-1 text-xs font-bold capitalize text-rose-800">
                                                            {{ $periodStatus->status }}
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-3 text-gray-600">{{ $periodStatus->reason }}</td>
                                                    <td class="px-4 py-3 text-right">
                                                        <button type="button" wire:click="restorePeriodActivity({{ $periodStatus->id }})"
                                                            wire:confirm="Restore this student to class activities for this academic year?"
                                                            class="font-semibold text-emerald-700 hover:text-emerald-900">
                                                            Restore activity
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            <!-- Fees Tab -->
            <div x-show="activeTab === 'fees'" x-transition>
                @if($student->feeInvoices->isNotEmpty())
                    <div class="space-y-4">
                        @foreach($student->feeInvoices as $invoice)
                            <a href="{{ route('fee-invoices.show', $invoice->id) }}" 
                               class="block p-6 bg-gradient-to-r from-gray-50 to-gray-100 rounded-lg border-2 border-gray-200 hover:border-indigo-400 hover:shadow-lg transition-all">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <h4 class="text-lg font-bold text-gray-900">{{ $invoice->name }}</h4>
                                        <p class="text-sm text-gray-600 mt-1">
                                            Due: 
                                            @if($invoice->due_date instanceof \Carbon\Carbon)
                                                {{ $invoice->due_date->format('M d, Y') }}
                                            @else
                                                {{ \Carbon\Carbon::parse($invoice->due_date)->format('M d, Y') }}
                                            @endif
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-2xl font-bold text-gray-900">{{ number_format($invoice->amount, 2) }}</p>
                                        <p class="text-sm text-gray-600">Paid: {{ number_format($invoice->paid, 2) }}</p>
                                        @if($invoice->balance <= 0)
                                            <span class="inline-block mt-2 px-3 py-1 bg-green-100 text-green-800 rounded-full text-xs font-bold">
                                                <i class="fas fa-check mr-1"></i>Paid
                                            </span>
                                        @elseif($invoice->paid > 0)
                                            <span class="inline-block mt-2 px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-bold">
                                                <i class="fas fa-exclamation mr-1"></i>Partial
                                            </span>
                                        @else
                                            <span class="inline-block mt-2 px-3 py-1 bg-red-100 text-red-800 rounded-full text-xs font-bold">
                                                <i class="fas fa-times mr-1"></i>Unpaid
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-12 bg-gray-50 rounded-lg">
                        <i class="fas fa-file-invoice text-gray-300 text-5xl mb-4"></i>
                        <p class="text-lg text-gray-500">No fee invoices found for this student</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
Livewire.on('print-profile', () => {
    window.print();
});
</script>
@endpush
