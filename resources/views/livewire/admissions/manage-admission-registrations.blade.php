<div class="space-y-6">
    @if (session()->has('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-8">
        <button wire:click="$set('statusFilter','all')" class="rounded-xl border p-4 text-left transition {{ $statusFilter === 'all' ? 'border-red-300 bg-red-50' : 'border-slate-200 bg-white' }}">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">All</p>
            <p class="mt-1 text-2xl font-black text-slate-900">{{ $statusCounts['all'] }}</p>
        </button>
        <button wire:click="$set('statusFilter','pending')" class="rounded-xl border p-4 text-left transition {{ $statusFilter === 'pending' ? 'border-amber-300 bg-amber-50' : 'border-slate-200 bg-white' }}">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Pending</p>
            <p class="mt-1 text-2xl font-black text-amber-700">{{ $statusCounts['pending'] }}</p>
        </button>
        <button wire:click="$set('statusFilter','reviewed')" class="rounded-xl border p-4 text-left transition {{ $statusFilter === 'reviewed' ? 'border-blue-300 bg-blue-50' : 'border-slate-200 bg-white' }}">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Reviewed</p>
            <p class="mt-1 text-2xl font-black text-blue-700">{{ $statusCounts['reviewed'] }}</p>
        </button>
        <button wire:click="$set('statusFilter','interviews')" class="rounded-xl border p-4 text-left transition {{ str_starts_with($statusFilter, 'interview') ? 'border-violet-300 bg-violet-50' : 'border-slate-200 bg-white' }}">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Interviews</p>
            <p class="mt-1 text-2xl font-black text-violet-700">{{ $statusCounts['interviews'] }}</p>
        </button>
        @foreach(['offered' => 'Offered', 'accepted' => 'Accepted', 'declined' => 'Declined', 'enrolled' => 'Enrolled'] as $pipelineStatus => $label)
            <button wire:click="$set('statusFilter','{{ $pipelineStatus }}')" class="rounded-xl border p-4 text-left transition {{ $statusFilter === $pipelineStatus ? 'border-emerald-300 bg-emerald-50' : 'border-slate-200 bg-white' }}">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $label }}</p>
                <p class="mt-1 text-2xl font-black text-slate-800">{{ $statusCounts[$pipelineStatus] }}</p>
            </button>
        @endforeach
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
            <div class="md:col-span-2">
                <label class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-500">Search</label>
                <input wire:model.live.debounce.300ms="search" type="text"
                    placeholder="Reference, student, guardian, phone..."
                    class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200">
            </div>

            <div>
                <label class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-500">Class</label>
                <select wire:model.live="classFilter"
                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200">
                    <option value="">All Classes</option>
                    @foreach($classes as $myClass)
                        <option value="{{ $myClass->id }}">{{ $myClass->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-500">Status</label>
                <select wire:model.live="statusFilter"
                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200">
                    <option value="all">All</option>
                    <option value="pending">Pending</option>
                    <option value="reviewed">Reviewed</option>
                    <option value="interviews">All interviews</option>
                    <option value="interview_scheduled">Interview scheduled</option>
                    <option value="interviewed">Interviewed</option>
                    <option value="offered">Offered</option>
                    <option value="accepted">Accepted</option>
                    <option value="declined">Declined</option>
                    <option value="enrolled">Enrolled</option>
                </select>
            </div>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Reference</th>
                        <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Student</th>
                        <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Guardian</th>
                        <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Class</th>
                        <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Submitted</th>
                        <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($registrations as $registration)
                        <tr>
                            <td class="px-4 py-3 align-top">
                                <p class="text-sm font-semibold text-slate-900">{{ $registration->reference_no }}</p>
                                <p class="text-xs text-slate-500">{{ $registration->school?->name }}</p>
                            </td>
                            <td class="px-4 py-3 align-top">
                                <p class="text-sm font-semibold text-slate-900">{{ $registration->student_name }}</p>
                                <p class="text-xs text-slate-500">{{ $registration->student_email ?: 'No email' }}</p>
                                @if($registration->documents->isNotEmpty())
                                    <p class="mt-1 text-xs font-semibold text-blue-700">
                                        <i class="fas fa-paperclip mr-1"></i>
                                        Document attached
                                    </p>
                                @endif
                            </td>
                            <td class="px-4 py-3 align-top">
                                <p class="text-sm font-semibold text-slate-900">{{ $registration->guardian_name }}</p>
                                <p class="text-xs text-slate-500">{{ $registration->guardian_phone }}</p>
                            </td>
                            <td class="px-4 py-3 align-top">
                                <p class="text-sm text-slate-800">{{ $registration->myClass?->name ?: 'N/A' }}</p>
                                <p class="text-xs text-slate-500">{{ $registration->section?->name ?: 'No section' }}</p>
                            </td>
                            <td class="px-4 py-3 align-top">
                                @php
                                    $statusClass = match($registration->status) {
                                        'pending' => 'bg-amber-100 text-amber-800',
                                        'reviewed' => 'bg-blue-100 text-blue-800',
                                        'declined' => 'bg-rose-100 text-rose-800',
                                        'accepted', 'enrolled' => 'bg-emerald-100 text-emerald-800',
                                        'offered' => 'bg-cyan-100 text-cyan-800',
                                        'interview_scheduled', 'interviewed' => 'bg-violet-100 text-violet-800',
                                        default => 'bg-slate-100 text-slate-800',
                                    };
                                @endphp
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold {{ $statusClass }}">{{ str($registration->status)->replace('_', ' ')->title() }}</span>
                            </td>
                            <td class="px-4 py-3 align-top text-xs text-slate-600">
                                {{ $registration->created_at->format('M d, Y') }}<br>
                                {{ $registration->created_at->format('h:i A') }}
                            </td>
                            <td class="px-4 py-3 align-top">
                                @php
                                    $canReview = auth()->user()->can('review admission registration');
                                    $canReject = auth()->user()->can('reject admission registration');
                                    $canApprove = auth()->user()->can('approve admission registration') && auth()->user()->can('create student');
                                @endphp
                                <div class="flex flex-wrap gap-2">
                                    <button wire:click="viewAdmission({{ $registration->id }})" class="rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-200">
                                        View
                                    </button>

                                    @if($registration->status === 'pending' && $canReview)
                                        <button wire:click="markReviewed({{ $registration->id }})" class="rounded-lg bg-blue-100 px-2.5 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-200">
                                            Review
                                        </button>
                                    @endif

                                    @if(!in_array($registration->status, ['accepted', 'declined', 'enrolled'], true) && $canReject)
                                        <button wire:click="rejectAdmission({{ $registration->id }})" wire:confirm="Reject this admission registration?"
                                            class="rounded-lg bg-rose-100 px-2.5 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-200">
                                            Decline
                                        </button>
                                    @endif

                                    @if($registration->status === 'accepted' && $registration->feeConfirmation && $canApprove && auth()->user()->can('create parent'))
                                        <button wire:click="convertAdmission({{ $registration->id }})" wire:confirm="Create and link the student and parent accounts?"
                                            class="rounded-lg bg-emerald-100 px-2.5 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-200">
                                            Enrol
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">
                                No admission registrations found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-200 bg-white px-4 py-3">
            {{ $registrations->links() }}
        </div>
    </div>

    @if($selectedAdmission)
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <h3 class="text-lg font-black text-slate-900">Admission Details: {{ $selectedAdmission->reference_no }}</h3>
                <button wire:click="clearSelected" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-200">
                    Close
                </button>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Student</p>
                    <p class="mt-2 text-sm text-slate-800"><strong>Name:</strong> {{ $selectedAdmission->student_name }}</p>
                    <p class="mt-1 text-sm text-slate-800"><strong>Email:</strong> {{ $selectedAdmission->student_email ?: 'N/A' }}</p>
                    <p class="mt-1 text-sm text-slate-800"><strong>Gender:</strong> {{ ucfirst($selectedAdmission->gender ?: 'N/A') }}</p>
                    <p class="mt-1 text-sm text-slate-800"><strong>DOB:</strong> {{ $selectedAdmission->birthday?->format('M d, Y') ?: 'N/A' }}</p>
                    <p class="mt-1 text-sm text-slate-800"><strong>Class:</strong> {{ $selectedAdmission->myClass?->name ?: 'N/A' }}</p>
                    <p class="mt-1 text-sm text-slate-800"><strong>Section:</strong> {{ $selectedAdmission->section?->name ?: 'N/A' }}</p>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Guardian</p>
                    <p class="mt-2 text-sm text-slate-800"><strong>Name:</strong> {{ $selectedAdmission->guardian_name }}</p>
                    <p class="mt-1 text-sm text-slate-800"><strong>Phone:</strong> {{ $selectedAdmission->guardian_phone }}</p>
                    <p class="mt-1 text-sm text-slate-800"><strong>Email:</strong> {{ $selectedAdmission->guardian_email ?: 'N/A' }}</p>
                    <p class="mt-1 text-sm text-slate-800"><strong>Relationship:</strong> {{ $selectedAdmission->guardian_relationship ?: 'N/A' }}</p>
                    <p class="mt-1 text-sm text-slate-800"><strong>Address:</strong> {{ $selectedAdmission->address ?: 'N/A' }}</p>
                    <p class="mt-1 text-sm text-slate-800"><strong>Previous School:</strong> {{ $selectedAdmission->previous_school ?: 'N/A' }}</p>
                    <p class="mt-1 text-sm text-slate-800"><strong>Applicant Notes:</strong> {{ $selectedAdmission->notes ?: 'N/A' }}</p>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 md:col-span-2">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Processing</p>
                    <p class="mt-2 text-sm text-slate-800"><strong>Status:</strong> {{ ucfirst($selectedAdmission->status) }}</p>
                    <p class="mt-1 text-sm text-slate-800"><strong>Processed By:</strong> {{ $selectedAdmission->processedBy?->name ?: 'N/A' }}</p>
                    <p class="mt-1 text-sm text-slate-800"><strong>Processed At:</strong> {{ $selectedAdmission->processed_at?->format('M d, Y h:i A') ?: 'N/A' }}</p>
                    <p class="mt-1 text-sm text-slate-800"><strong>Created Student:</strong> {{ $selectedAdmission->enrolledUser?->name ?: 'Not created yet' }}</p>
                    <p class="mt-1 text-sm text-slate-800"><strong>Linked Parent:</strong> {{ $selectedAdmission->enrolledParent?->name ?: 'Not created yet' }}</p>
                    <p class="mt-1 text-sm text-slate-800"><strong>Admission Number:</strong> {{ $selectedAdmission->enrolledStudentRecord?->admission_number ?: 'N/A' }}</p>

                    <div class="mt-4 rounded-xl border border-slate-200 bg-white p-3">
                        <label class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-500">Decision summary</label>
                        <textarea wire:model.defer="adminNote" rows="3" placeholder="Internal review notes, decision reasons, or next steps"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200"></textarea>

                        @php
                            $canManageNote = auth()->user()->can('update admission admin note');
                            $canReview = auth()->user()->can('review admission registration');
                            $canReject = auth()->user()->can('reject admission registration');
                            $canApprove = auth()->user()->can('approve admission registration') && auth()->user()->can('create student');
                        @endphp

                        <div class="mt-3 flex flex-wrap gap-2">
                            @if($canManageNote)
                                <button wire:click="saveAdminNote({{ $selectedAdmission->id }})"
                                    class="rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-700">
                                    Save Note
                                </button>
                            @endif

                            @if($selectedAdmission->status === 'pending' && $canReview)
                                <button wire:click="markReviewed({{ $selectedAdmission->id }})"
                                    class="rounded-lg bg-blue-100 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-200">
                                    Mark Reviewed
                                </button>
                            @endif

                            @if(!in_array($selectedAdmission->status, ['accepted', 'declined', 'enrolled'], true) && $canReject)
                                <button wire:click="rejectAdmission({{ $selectedAdmission->id }})" wire:confirm="Reject this admission registration?"
                                    class="rounded-lg bg-rose-100 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-200">
                                    Decline
                                </button>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Secure documents</p>
                    <div class="mt-3 space-y-2">
                        @forelse($selectedAdmission->documents as $document)
                            <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                                <a href="{{ route('admissions.documents.download', $document) }}" class="text-sm font-semibold text-blue-700 hover:underline">
                                    {{ $document->original_name }}
                                </a>
                                <p class="text-xs text-slate-500">{{ str($document->category)->replace('_', ' ')->title() }} · {{ $document->uploadedBy?->name ?: 'Applicant' }}</p>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">No documents uploaded.</p>
                        @endforelse
                    </div>

                    @if($canManageNote)
                        <div class="mt-4 grid gap-2">
                            <select wire:model="documentCategory" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                                @foreach(['supporting', 'birth_certificate', 'previous_report', 'medical', 'offer', 'fee_receipt'] as $category)
                                    <option value="{{ $category }}">{{ str($category)->replace('_', ' ')->title() }}</option>
                                @endforeach
                            </select>
                            <input wire:model="pipelineDocument" type="file" class="block w-full text-sm text-slate-600">
                            @error('pipelineDocument') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                            <button wire:click="uploadPipelineDocument({{ $selectedAdmission->id }})" wire:loading.attr="disabled"
                                class="rounded-lg bg-slate-800 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-700">
                                Upload document
                            </button>
                        </div>
                    @endif
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Internal note history</p>
                    <div class="mt-3 max-h-56 space-y-2 overflow-y-auto">
                        @forelse($selectedAdmission->internalNotes as $note)
                            <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                                <p class="text-sm text-slate-700">{{ $note->note }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $note->author_name }} · {{ $note->created_at?->format('M d, Y h:i A') }}</p>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">No internal notes yet.</p>
                        @endforelse
                    </div>
                    @if($canManageNote)
                        <textarea wire:model="internalNote" rows="2" placeholder="Add an append-only internal note"
                            class="mt-3 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                        @error('internalNote') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                        <button wire:click="addInternalNote({{ $selectedAdmission->id }})" class="mt-2 rounded-lg bg-slate-800 px-3 py-2 text-xs font-semibold text-white">
                            Add internal note
                        </button>
                    @endif
                </div>

                <div class="rounded-xl border border-violet-200 bg-violet-50/50 p-4 md:col-span-2">
                    <p class="text-xs font-bold uppercase tracking-wider text-violet-700">Interview workflow</p>
                    <div class="mt-3 grid gap-3 lg:grid-cols-2">
                        <div class="space-y-2">
                            @forelse($selectedAdmission->interviews as $interview)
                                <div class="rounded-lg border border-violet-200 bg-white p-3">
                                    <p class="text-sm font-semibold text-slate-800">{{ $interview->scheduled_at->format('M d, Y h:i A') }} · {{ str($interview->mode)->replace('_', ' ')->title() }}</p>
                                    <p class="text-xs text-slate-500">{{ $interview->interviewer?->name ?: 'Unassigned' }} · {{ $interview->duration_minutes }} minutes · {{ ucfirst($interview->status) }}</p>
                                    @if($interview->location)<p class="mt-1 text-xs text-slate-600">{{ $interview->location }}</p>@endif
                                    @if($interview->outcome)<p class="mt-1 text-sm font-semibold text-violet-700">Outcome: {{ str($interview->outcome)->replace('_', ' ')->title() }}</p>@endif
                                    @if($interview->notes)<p class="mt-1 text-sm text-slate-700">{{ $interview->notes }}</p>@endif
                                    @if($interview->status === 'scheduled' && $canReview)
                                        <div class="mt-3 grid gap-2 sm:grid-cols-3">
                                            <select wire:model="interviewOutcome" class="rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                                                <option value="recommended">Recommended</option>
                                                <option value="hold">Hold</option>
                                                <option value="not_recommended">Not recommended</option>
                                            </select>
                                            <input wire:model="interviewNotes" placeholder="Outcome notes" class="rounded-lg border border-slate-300 px-2 py-1.5 text-xs sm:col-span-2">
                                            <button wire:click="completeInterview({{ $interview->id }})" class="rounded-lg bg-violet-700 px-3 py-1.5 text-xs font-semibold text-white sm:col-span-3">Record outcome</button>
                                        </div>
                                    @endif
                                </div>
                            @empty
                                <p class="text-sm text-slate-500">No interview has been scheduled.</p>
                            @endforelse
                        </div>
                        @if($canReview && in_array($selectedAdmission->status, ['pending', 'reviewed', 'interview_scheduled'], true))
                            <div class="grid content-start gap-2 rounded-lg border border-violet-200 bg-white p-3 sm:grid-cols-2">
                                <input wire:model="interviewDate" type="date" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <input wire:model="interviewTime" type="time" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <input wire:model="interviewDuration" type="number" min="15" max="240" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Minutes">
                                <select wire:model="interviewMode" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    <option value="in_person">In person</option>
                                    <option value="video">Video</option>
                                    <option value="phone">Phone</option>
                                </select>
                                <input wire:model="interviewLocation" class="rounded-lg border border-slate-300 px-3 py-2 text-sm sm:col-span-2" placeholder="Location or meeting link">
                                <button wire:click="scheduleInterview({{ $selectedAdmission->id }})" class="rounded-lg bg-violet-700 px-3 py-2 text-xs font-semibold text-white sm:col-span-2">Schedule interview</button>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="rounded-xl border border-cyan-200 bg-cyan-50/50 p-4 md:col-span-2">
                    <p class="text-xs font-bold uppercase tracking-wider text-cyan-700">Offer, acceptance, fee and enrolment</p>
                    <div class="mt-3 grid gap-4 lg:grid-cols-2">
                        <div class="rounded-lg border border-cyan-200 bg-white p-3">
                            @if(in_array($selectedAdmission->status, ['reviewed', 'interviewed'], true) && $canApprove)
                                <textarea wire:model="offerNote" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Offer terms or note"></textarea>
                                <label class="mt-2 block text-xs font-semibold text-slate-600">Offer validity (days)</label>
                                <input wire:model="offerValidDays" type="number" min="1" max="90" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <button wire:click="offerAdmission({{ $selectedAdmission->id }})" wire:confirm="Issue this admission offer?" class="mt-2 w-full rounded-lg bg-cyan-700 px-3 py-2 text-xs font-semibold text-white">Issue secure offer</button>
                            @elseif($selectedAdmission->status === 'offered')
                                <p class="text-sm font-semibold text-cyan-800">Offer issued {{ $selectedAdmission->offered_at?->diffForHumans() }}.</p>
                                <p class="mt-1 text-xs text-slate-600">Expires {{ $selectedAdmission->offer_expires_at?->format('M d, Y h:i A') }}. Acceptance links are stored only as secure hashes.</p>
                            @elseif(in_array($selectedAdmission->status, ['accepted', 'enrolled'], true))
                                <p class="text-sm font-semibold text-emerald-700">Accepted by {{ $selectedAdmission->accepted_by_name }} on {{ $selectedAdmission->accepted_at?->format('M d, Y h:i A') }}.</p>
                            @else
                                <p class="text-sm text-slate-500">Complete review or interview before issuing an offer.</p>
                            @endif
                        </div>

                        <div class="rounded-lg border border-cyan-200 bg-white p-3">
                            @if($selectedAdmission->feeConfirmation)
                                <p class="text-sm font-semibold text-emerald-700">Fee confirmed: {{ $selectedAdmission->feeConfirmation->amount }}</p>
                                <p class="mt-1 text-xs text-slate-600">{{ str($selectedAdmission->feeConfirmation->payment_method)->replace('_', ' ')->title() }} · {{ $selectedAdmission->feeConfirmation->reference ?: 'No external reference' }} · {{ $selectedAdmission->feeConfirmation->confirmer_name }}</p>
                            @elseif($selectedAdmission->status === 'accepted' && $canApprove)
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <input wire:model="feeAmount" type="number" min="0" step="0.01" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Amount">
                                    <select wire:model="feeMethod" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                        @foreach(['cash', 'bank_transfer', 'pos', 'cheque', 'online', 'waived'] as $method)
                                            <option value="{{ $method }}">{{ str($method)->replace('_', ' ')->title() }}</option>
                                        @endforeach
                                    </select>
                                    <input wire:model="feeReference" class="rounded-lg border border-slate-300 px-3 py-2 text-sm sm:col-span-2" placeholder="Payment reference">
                                    <textarea wire:model="feeNotes" rows="2" class="rounded-lg border border-slate-300 px-3 py-2 text-sm sm:col-span-2" placeholder="Confirmation note"></textarea>
                                    <button wire:click="confirmAdmissionFee({{ $selectedAdmission->id }})" class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-semibold text-white sm:col-span-2">Confirm admission fee</button>
                                </div>
                            @else
                                <p class="text-sm text-slate-500">Fee confirmation becomes available after offer acceptance.</p>
                            @endif
                        </div>
                    </div>

                    @if($selectedAdmission->status === 'accepted' && $selectedAdmission->feeConfirmation && $canApprove && auth()->user()->can('create parent'))
                        <button wire:click="convertAdmission({{ $selectedAdmission->id }})" wire:confirm="Create and link the student and parent accounts? This cannot be repeated."
                            class="mt-4 w-full rounded-lg bg-emerald-700 px-4 py-3 text-sm font-bold text-white hover:bg-emerald-800">
                            Enrol applicant and create linked student + parent records
                        </button>
                    @endif
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 md:col-span-2">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Status History</p>

                    <div class="mt-3 space-y-2">
                        @forelse($selectedAdmission->statusHistories as $history)
                            <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                <p class="font-semibold text-slate-800">
                                    {{ ucfirst($history->from_status ?? 'new') }} -> {{ ucfirst($history->to_status) }}
                                </p>
                                <p class="text-xs text-slate-500">
                                    {{ $history->changedBy?->name ?: 'System' }} • {{ $history->changed_at?->format('M d, Y h:i A') ?: $history->created_at?->format('M d, Y h:i A') }}
                                </p>
                                @if($history->note)
                                    <p class="mt-1 text-sm text-slate-700">{{ $history->note }}</p>
                                @endif
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">No status changes yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
