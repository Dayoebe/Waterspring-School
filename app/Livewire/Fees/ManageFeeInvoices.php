<?php

namespace App\Livewire\Fees;

use App\Models\Fee;
use App\Models\FeeCategory;
use App\Models\FeeInvoice;
use App\Models\MyClass;
use App\Models\Section;
use App\Models\StudentRecord;
use App\Traits\ResolvesAccessibleStudents;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class ManageFeeInvoices extends Component
{
    use AuthorizesRequests;
    use ResolvesAccessibleStudents;
    use WithPagination;

    public $mode = 'list';

    public string $tab = 'invoices';

    // Filters
    public $search = '';

    public $statusFilter = 'all';

    public $yearFilter = '';

    public $classFilter = '';

    // Sorting & Pagination
    public $sortField = 'due_date';

    public $sortDirection = 'desc';

    public $perPage = 15;

    // Form fields for invoice
    public $feeInvoiceId = null;

    public $issue_date = '';

    public $due_date = '';

    public $note = '';

    // For creating invoices
    public $selectedStudents = [];

    public $selectedFees = [];

    public $classes = [];

    public $sections = [];

    public $students = [];

    public $feeCategories = [];

    public $fees = [];

    public $selectedClass = '';

    public $selectedSection = '';

    public $selectedStudent = '';

    public string $recipientScope = 'class';

    public $selectedFeeCategory = '';

    public $selectedFee = '';

    public $feeAmounts = [];

    protected $queryString = [
        'mode' => ['except' => 'list'],
        'tab' => ['except' => 'invoices'],
        'search' => ['except' => ''],
        'statusFilter' => ['except' => 'all'],
        'classFilter' => ['except' => ''],
    ];

    public function mount()
    {
        $this->hydrateModeFromRoute();

        $this->yearFilter = date('Y');

        if ($this->mode === 'create') {
            if ($this->modeIsAllowed('create')) {
                $this->loadDataForCreate();
                $this->prefillApprovedFeeFromRequest();
            } else {
                $this->mode = 'list';
            }
        } elseif ($this->mode === 'edit' && $this->feeInvoiceId) {
            if ($this->modeIsAllowed('edit')) {
                $this->loadFeeInvoiceForEdit();
            } else {
                $this->mode = 'list';
                $this->feeInvoiceId = null;
            }
        } elseif ($this->mode === 'edit') {
            $this->mode = 'list';
            $this->feeInvoiceId = null;
        }

        if ($this->mode === 'list') {
            $this->loadClasses();
            $this->normaliseWorkspaceTab();
        }
    }

    public function showTab(string $tab): void
    {
        abort_unless(in_array($tab, ['invoices', 'catalog', 'collections'], true), 404);

        $permission = match ($tab) {
            'catalog' => 'read fee',
            'collections' => 'update fee invoice',
            default => 'read fee invoice',
        };
        $this->ensurePermission($permission);
        $this->tab = $tab;
        $this->resetPage();
    }

    public function updatedTab(): void
    {
        $this->normaliseWorkspaceTab();
        $this->resetPage();
    }

    protected function normaliseWorkspaceTab(): void
    {
        $canReadInvoices = auth()->user()?->can('read fee invoice') ?? false;
        $canReadCatalog = auth()->user()?->can('read fee') ?? false;
        $canManageCollections = auth()->user()?->can('update fee invoice') ?? false;

        if ($this->tab === 'collections' && $canManageCollections) {
            return;
        }

        if ($this->tab === 'catalog' && $canReadCatalog) {
            return;
        }

        if ($this->tab === 'invoices' && $canReadInvoices) {
            return;
        }

        $this->tab = $canReadCatalog && ! $canReadInvoices ? 'catalog' : 'invoices';
    }

    protected function hydrateModeFromRoute(): void
    {
        $routeName = request()->route()?->getName();

        if (in_array($routeName, ['fee-invoices.create', 'fees.create'], true)) {
            $this->mode = 'create';
            $this->feeInvoiceId = null;

            return;
        }

        if ($routeName === 'fee-invoices.edit') {
            $this->mode = 'edit';
            $this->feeInvoiceId = $this->resolveRouteModelId(request()->route('feeInvoice'));
        }
    }

    protected function resolveRouteModelId(mixed $value): ?int
    {
        if ($value instanceof Model) {
            return (int) $value->getKey();
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    public function loadDataForCreate()
    {
        $this->ensurePermission('create fee invoice');

        $this->loadClasses();

        $this->feeCategories = FeeCategory::query()
            ->where('school_id', auth()->user()->school_id)
            ->orderBy('name')
            ->get();

        $this->issue_date = date('Y-m-d');
        $this->due_date = date('Y-m-d', strtotime('+30 days'));
    }

    public function updatedSelectedClass()
    {
        if (! $this->selectedClass) {
            $this->sections = collect();
            $this->students = collect();
            $this->selectedSection = '';
            $this->selectedStudent = '';
            $this->selectedStudents = [];

            return;
        }

        $class = $this->getClassForCurrentSchool($this->selectedClass);
        $this->sections = $class
            ? $class->sections()->orderBy('name')->get()
            : collect();
        $this->selectedSection = '';
        $this->selectedStudent = '';
        $this->updateStudentsList();
        $this->syncSelectedStudentsForBilling();
    }

    public function updatedSelectedSection()
    {
        $this->selectedStudent = '';
        $this->updateStudentsList();
        $this->syncSelectedStudentsForBilling();
    }

    public function updatedRecipientScope(): void
    {
        if (! in_array($this->recipientScope, ['class', 'individual'], true)) {
            $this->recipientScope = 'class';
        }

        if ($this->recipientScope === 'class') {
            $this->selectedStudent = '';
        }

        $this->syncSelectedStudentsForBilling();
    }

    public function updatedSelectedStudent(): void
    {
        $this->syncSelectedStudentsForBilling();
    }

    public function updateStudentsList()
    {
        if (! $this->selectedClass) {
            $this->students = collect();

            return;
        }

        $this->students = $this->studentSelectionQuery()->get();
    }

    protected function syncSelectedStudentsForBilling(): void
    {
        $this->selectedStudents = [];

        if (! $this->selectedClass) {
            return;
        }

        $students = collect($this->students);

        if ($this->recipientScope === 'individual') {
            $students = $this->selectedStudent
                ? $students->where('id', (int) $this->selectedStudent)
                : collect();
        }

        foreach ($students as $student) {
            $this->selectedStudents[$student->id] = [
                'id' => $student->id,
                'name' => $student->name,
                'email' => $student->email,
            ];
        }
    }

    public function updatedSelectedFeeCategory()
    {
        if ($this->selectedFeeCategory) {
            $category = $this->getFeeCategoryForCurrentSchool($this->selectedFeeCategory);
            $this->fees = $category ? $category->fees()->approved()->orderBy('name')->get() : collect();
            $this->selectedFee = '';
        }
    }

    public function addFee()
    {
        $this->ensurePermission('create fee invoice');

        if ($this->selectedFee) {
            $fee = $this->getFeeForCurrentSchool($this->selectedFee);
            if ($fee && ! isset($this->selectedFees[$fee->id])) {
                $this->selectedFees[$fee->id] = $fee->invoiceDefaults();
            }
        } elseif ($this->selectedFeeCategory) {
            $category = $this->getFeeCategoryForCurrentSchool($this->selectedFeeCategory);
            if ($category) {
                foreach ($category->fees()->approved()->orderBy('name')->get() as $fee) {
                    if (! isset($this->selectedFees[$fee->id])) {
                        $this->selectedFees[$fee->id] = $fee->invoiceDefaults();
                    }
                }
            }
        }
    }

    #[On('fee-approved-for-invoice')]
    public function addApprovedFeeFromCreator(int $feeId): void
    {
        $this->ensurePermission('create fee invoice');
        $fee = $this->getFeeForCurrentSchool($feeId);

        if (! $fee) {
            session()->flash('error', 'The new fee could not be added because it is not approved for this school.');

            return;
        }

        $this->selectApprovedFee($fee);
    }

    protected function prefillApprovedFeeFromRequest(): void
    {
        $feeId = request()->integer('fee');

        if ($feeId <= 0) {
            return;
        }

        $fee = $this->getFeeForCurrentSchool($feeId);

        if (! $fee) {
            session()->flash('error', 'That fee is unavailable or has not been approved for this school.');

            return;
        }

        $this->selectApprovedFee($fee);
    }

    protected function selectApprovedFee(Fee $fee): void
    {
        $this->feeCategories = FeeCategory::query()
            ->where('school_id', auth()->user()->school_id)
            ->orderBy('name')
            ->get();
        $this->selectedFeeCategory = $fee->fee_category_id;
        $this->fees = $fee->feeCategory->fees()->approved()->orderBy('name')->get();
        $this->selectedFee = $fee->id;
        $this->selectedFees[$fee->id] = $fee->invoiceDefaults();
    }

    public function removeFee($feeId)
    {
        unset($this->selectedFees[$feeId]);
    }

    public function switchMode($mode, $feeInvoiceId = null)
    {
        if (! $this->modeIsAllowed($mode)) {
            $this->mode = 'list';
            $this->feeInvoiceId = null;

            return;
        }

        $this->mode = $mode;
        $this->feeInvoiceId = $feeInvoiceId;
        $this->resetValidation();

        if ($mode === 'create') {
            $this->resetForm();
            $this->loadDataForCreate();
        } elseif ($mode === 'edit' && $feeInvoiceId) {
            $this->loadFeeInvoiceForEdit();
        }
    }

    public function loadFeeInvoiceForEdit()
    {
        $this->ensurePermission('update fee invoice');

        $feeInvoice = $this->getFeeInvoiceForCurrentSchool($this->feeInvoiceId);

        $this->fill([
            'issue_date' => $feeInvoice->issue_date->format('Y-m-d'),
            'due_date' => $feeInvoice->due_date->format('Y-m-d'),
            'note' => $feeInvoice->note ?? '',
        ]);
    }

    public function createFeeInvoice()
    {
        $this->ensurePermission('create fee invoice');

        $class = $this->getClassForCurrentSchool($this->selectedClass);
        if (! $class) {
            $this->addError('selectedClass', 'Select a class from your current school.');

            return;
        }

        if ($this->selectedSection && ! $this->getSectionForCurrentSchool($this->selectedSection, $class->id)) {
            $this->addError('selectedSection', 'Select a section that belongs to the chosen class.');

            return;
        }

        if ($this->recipientScope === 'individual' && ! $this->selectedStudent) {
            $this->addError('selectedStudent', 'Select the student who should receive this invoice.');

            return;
        }

        $this->updateStudentsList();
        $this->syncSelectedStudentsForBilling();

        $this->validate([
            'issue_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:issue_date',
            'note' => 'nullable|string|max:1000',
            'selectedFees.*.amount' => 'required|numeric|min:0.01|max:21474836.47|decimal:0,2',
            'selectedFees.*.waiver' => 'required|numeric|min:0|max:21474836.47|decimal:0,2',
            'selectedFees.*.fine' => 'required|numeric|min:0|max:21474836.47|decimal:0,2',
        ]);

        if (empty($this->selectedStudents)) {
            session()->flash('error', 'No active students were found for the selected billing scope.');

            return;
        }

        if (empty($this->selectedFees)) {
            session()->flash('error', 'Please select at least one fee');

            return;
        }

        if (! $this->selectedFeeTotalsAreValid()) {
            return;
        }

        $studentIds = collect($this->selectedStudents)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $students = $this->portalAccessibleStudentsQuery()
            ->whereIn('users.id', $studentIds)
            ->get(['users.id', 'users.name', 'users.email'])
            ->keyBy('id');

        if ($students->count() !== $studentIds->count()) {
            session()->flash('error', 'One or more selected students are not in your current school.');

            return;
        }

        $selectedFeesById = collect($this->selectedFees)->keyBy('id');
        $feeIds = $selectedFeesById->keys()->map(fn ($id) => (int) $id)->values();
        $fees = Fee::whereIn('id', $feeIds)
            ->approved()
            ->whereHas('feeCategory', function ($query) {
                $query->where('school_id', auth()->user()->school_id);
            })
            ->get(['id', 'name', 'default_amount'])
            ->keyBy('id');

        if ($fees->count() !== $feeIds->count()) {
            session()->flash('error', 'One or more selected fees are not in your current school.');

            return;
        }

        $recipientCount = $students->count();

        DB::transaction(function () use ($students, $fees, $selectedFeesById, $class) {
            foreach ($students as $student) {
                $invoiceName = $this->generateInvoiceNumber();

                $invoice = FeeInvoice::create([
                    'name' => $invoiceName,
                    'issue_date' => $this->issue_date,
                    'due_date' => $this->due_date,
                    'note' => $this->note ?: null,
                    'user_id' => $student->id,
                    'my_class_id' => $class->id,
                    'section_id' => $this->selectedSection ?: null,
                ]);

                foreach ($fees as $fee) {
                    $feeData = $selectedFeesById->get($fee->id, []);
                    $invoice->feeInvoiceRecords()->create([
                        'fee_id' => $fee->id,
                        'amount' => $feeData['amount'] ?? 0,
                        'waiver' => $feeData['waiver'] ?? 0,
                        'fine' => $feeData['fine'] ?? 0,
                    ]);
                }
            }
        });

        session()->flash('success', "Fee invoices created for {$recipientCount} student(s).");
        $this->switchMode('list');
    }

    public function updateFeeInvoice()
    {
        $this->ensurePermission('update fee invoice');

        $feeInvoice = $this->getFeeInvoiceForCurrentSchool($this->feeInvoiceId);

        $this->validate([
            'issue_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:issue_date',
            'note' => 'nullable|string|max:1000',
        ]);

        DB::transaction(function () use ($feeInvoice) {
            $feeInvoice->update([
                'issue_date' => $this->issue_date,
                'due_date' => $this->due_date,
                'note' => $this->note ?: null,
            ]);
        });

        session()->flash('success', 'Fee invoice updated successfully');
        $this->switchMode('list');
    }

    protected function selectedFeeTotalsAreValid(): bool
    {
        $valid = true;

        foreach ($this->selectedFees as $index => $fee) {
            $total = Money::of((string) ($fee['amount'] ?? 0), config('app.currency'))
                ->plus(Money::of((string) ($fee['fine'] ?? 0), config('app.currency')))
                ->minus(Money::of((string) ($fee['waiver'] ?? 0), config('app.currency')));

            if ($total->isLessThan(0)) {
                $this->addError("selectedFees.{$index}.waiver", 'The waiver cannot make the fee total negative.');
                $valid = false;
            }
        }

        return $valid;
    }

    public function deleteFeeInvoice($feeInvoiceId)
    {
        $this->ensurePermission('delete fee invoice');

        $feeInvoice = $this->getFeeInvoiceForCurrentSchool($feeInvoiceId);

        if ($feeInvoice->feeInvoiceRecords()->whereHas('paymentTransactions')->exists()) {
            session()->flash('error', 'Invoices with payment history cannot be deleted. Reverse payments and retain the audit record.');

            return;
        }

        DB::transaction(function () use ($feeInvoice) {
            $feeInvoice->delete();
        });

        session()->flash('success', 'Fee invoice deleted successfully');
    }

    protected function generateInvoiceNumber()
    {
        $school = auth()->user()->school;
        $schoolInitials = $school->initials ? $school->initials.'-' : '';

        do {
            $invoiceNumber = "Fee-Invoice-{$schoolInitials}".mt_rand(100000000, 999999999);
            $exists = FeeInvoice::where('name', $invoiceNumber)->exists();
        } while ($exists);

        return $invoiceNumber;
    }

    public function clearFilters()
    {
        $this->reset(['search', 'statusFilter', 'classFilter']);
        $this->yearFilter = date('Y');
        $this->resetPage();
    }

    public function sortBy($field)
    {
        if (! in_array($field, ['name', 'class', 'due_date'], true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function resetForm()
    {
        $this->reset([
            'feeInvoiceId', 'issue_date', 'due_date', 'note',
            'selectedStudents', 'selectedFees', 'selectedClass',
            'selectedSection', 'selectedStudent', 'selectedFeeCategory',
            'selectedFee', 'feeAmounts', 'recipientScope',
        ]);

        $this->recipientScope = 'class';
    }

    protected function getFeeInvoicesQuery()
    {
        $query = FeeInvoice::whereHas('user', function ($q) {
            $q->where('school_id', auth()->user()->school_id);
        })
            ->with([
                'user',
                'user.studentRecord.myClass',
                'user.studentRecord.section',
                'billingClass',
                'billingSection',
                'feeInvoiceRecords',
            ])
            ->when($this->search, function ($q) {
                $q->where(function ($query) {
                    $query->where('name', 'like', '%'.$this->search.'%')
                        ->orWhereHas('user', function ($q) {
                            $q->where('name', 'like', '%'.$this->search.'%');
                        });
                });
            })
            ->when($this->yearFilter, function ($q) {
                $q->whereYear('due_date', $this->yearFilter);
            })
            ->when($this->classFilter, function ($q) {
                $q->where(function ($query) {
                    $query->where('fee_invoices.my_class_id', $this->classFilter)
                        ->orWhere(function ($legacyQuery) {
                            $legacyQuery->whereNull('fee_invoices.my_class_id')
                                ->whereHas('user.studentRecord', fn ($studentQuery) => $studentQuery
                                    ->where('my_class_id', $this->classFilter));
                        });
                });
            });

        if ($this->isRestrictedStudentPortalViewer()) {
            $studentUserIds = $this->portalAccessibleStudentUserIds();

            if ($studentUserIds->isEmpty()) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('user_id', $studentUserIds);
            }
        }

        // Apply status filter
        if ($this->statusFilter === 'due') {
            $query->whereHas('feeInvoiceRecords', function ($q) {
                $q->whereRaw('(amount + fine) > (paid + waiver)');
            });
        } elseif ($this->statusFilter === 'paid') {
            $query->whereDoesntHave('feeInvoiceRecords', function ($q) {
                $q->whereRaw('(amount + fine) > (paid + waiver)');
            });
        }

        if ($this->sortField === 'class') {
            return $query
                ->orderBy(
                    MyClass::query()
                        ->select('name')
                        ->whereColumn('my_classes.id', 'fee_invoices.my_class_id'),
                    $this->sortDirection
                )
                ->orderBy('name');
        }

        return $query->orderBy($this->sortField, $this->sortDirection);
    }

    protected function loadClasses(): void
    {
        $classesQuery = MyClass::query()
            ->whereHas('classGroup', function ($query) {
                $query->where('school_id', auth()->user()->school_id);
            })
            ->instructional();

        if ($this->isRestrictedStudentPortalViewer()) {
            $classesQuery->whereIn('id', $this->accessibleClassIdsForStudentPortal());
        }

        $this->classes = $classesQuery->orderBy('name')->get();
    }

    protected function getClassForCurrentSchool($classId): ?MyClass
    {
        if (! $classId) {
            return null;
        }

        $query = MyClass::whereHas('classGroup', function ($query) {
            $query->where('school_id', auth()->user()->school_id);
        });

        if ($this->isRestrictedStudentPortalViewer()) {
            $query->whereIn('id', $this->accessibleClassIdsForStudentPortal());
        }

        return $query->find($classId);
    }

    protected function getSectionForCurrentSchool($sectionId, $classId = null): ?Section
    {
        if (! $sectionId) {
            return null;
        }

        $query = Section::whereHas('myClass.classGroup', function ($q) {
            $q->where('school_id', auth()->user()->school_id);
        })->where('id', $sectionId);

        if ($this->isRestrictedStudentPortalViewer()) {
            $query->whereIn('id', $this->accessibleSectionIdsForStudentPortal());
        }

        if ($classId) {
            $query->where('my_class_id', $classId);
        }

        return $query->first();
    }

    protected function getFeeCategoryForCurrentSchool($feeCategoryId): ?FeeCategory
    {
        if (! $feeCategoryId) {
            return null;
        }

        return FeeCategory::query()
            ->where('school_id', auth()->user()->school_id)
            ->find($feeCategoryId);
    }

    protected function getFeeForCurrentSchool($feeId): ?Fee
    {
        if (! $feeId) {
            return null;
        }

        return Fee::approved()->whereHas('feeCategory', function ($query) {
            $query->where('school_id', auth()->user()->school_id);
        })->find($feeId);
    }

    protected function getFeeInvoiceForCurrentSchool($feeInvoiceId): FeeInvoice
    {
        $query = FeeInvoice::whereHas('user', function ($query) {
            $query->where('school_id', auth()->user()->school_id);
        });

        if ($this->isRestrictedStudentPortalViewer()) {
            $studentUserIds = $this->portalAccessibleStudentUserIds();

            if ($studentUserIds->isEmpty()) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('user_id', $studentUserIds);
            }
        }

        return $query->with(['user', 'feeInvoiceRecords.fee'])->findOrFail($feeInvoiceId);
    }

    protected function modeIsAllowed(string $mode): bool
    {
        return match ($mode) {
            'create' => auth()->user()?->can('create fee invoice') ?? false,
            'edit' => auth()->user()?->can('update fee invoice') ?? false,
            default => true,
        };
    }

    protected function ensurePermission(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }

    protected function studentSelectionQuery()
    {
        $activeStudentRecordIds = StudentRecord::activeStudentRecordIdsForSchoolAcademicYear(
            auth()->user()?->school_id,
            auth()->user()?->school?->academic_year_id,
            $this->selectedClass ? (int) $this->selectedClass : null,
            $this->selectedSection ? (int) $this->selectedSection : null,
        );

        return $this->portalAccessibleStudentsQuery()
            ->with(['studentRecord.myClass', 'studentRecord.section'])
            ->whereHas('studentRecord', fn ($query) => $query->whereIn('id', $activeStudentRecordIds))
            ->orderBy('name');
    }

    protected function accessibleClassIdsForStudentPortal()
    {
        return $this->portalAccessibleStudentsQuery()
            ->with('studentRecord:id,user_id,my_class_id')
            ->get()
            ->pluck('studentRecord.my_class_id')
            ->filter()
            ->unique()
            ->values();
    }

    protected function accessibleSectionIdsForStudentPortal()
    {
        return $this->portalAccessibleStudentsQuery()
            ->with('studentRecord:id,user_id,section_id')
            ->get()
            ->pluck('studentRecord.section_id')
            ->filter()
            ->unique()
            ->values();
    }

    public function render()
    {
        $feeInvoices = collect();

        if ($this->mode === 'list' && $this->tab === 'invoices') {
            $feeInvoices = $this->getFeeInvoicesQuery()->paginate($this->perPage);
        }

        $pageTitle = match ($this->mode) {
            'create' => 'Create Fee / Invoice',
            'edit' => 'Edit Fee Invoice',
            default => 'Fees & Payments',
        };

        return view('livewire.fees.manage-fee-invoices', compact('feeInvoices'))
            ->layout('layouts.dashboard', [
                'breadcrumbs' => [
                    ['href' => route('dashboard'), 'text' => 'Dashboard'],
                    [
                        'href' => route('fees.index'),
                        'text' => 'Fees & Payments',
                        'active' => $this->mode === 'list',
                    ],
                    ...($this->mode === 'list' ? [] : [
                        ['href' => request()->url(), 'text' => $pageTitle, 'active' => true],
                    ]),
                ],
            ])
            ->title($pageTitle);
    }
}
