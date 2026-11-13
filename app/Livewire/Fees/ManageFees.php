<?php

namespace App\Livewire\Fees;

use App\Models\Fee;
use App\Models\FeeCategory;
use App\Services\FeeApprovalService;
use Brick\Math\BigDecimal;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class ManageFees extends Component
{
    use AuthorizesRequests, WithPagination;

    public $mode = 'list';

    // Filters
    public $search = '';

    public $filterCategory = '';

    // Sorting & Pagination
    public $sortField = 'name';

    public $sortDirection = 'asc';

    public $perPage = 15;

    // Form fields
    public $feeId = null;

    public $name = '';

    public $description = '';

    public $fee_category_id = '';

    public $amount = '';

    public $waiverType = Fee::WAIVER_NONE;

    public $waiverAmount = 0;

    public $waiverPercentage = 0;

    public $fine = 0;

    public bool $showCategoryCreator = false;

    public string $newCategoryName = '';

    public string $newCategoryDescription = '';

    public $feeCategories = [];

    protected $queryString = [
        'mode' => ['except' => 'list'],
        'search' => ['except' => ''],
    ];

    public function mount()
    {
        $this->hydrateModeFromRoute();

        $this->loadFeeCategories();

        if ($this->mode === 'edit' && $this->feeId) {
            if ($this->modeIsAllowed('edit')) {
                $this->loadFeeForEdit();
            } else {
                $this->mode = 'list';
                $this->feeId = null;
            }
        } elseif ($this->mode === 'edit') {
            $this->mode = 'list';
            $this->feeId = null;
        } elseif ($this->mode === 'create') {
            if ($this->modeIsAllowed('create')) {
                $this->resetForm();
            } else {
                $this->mode = 'list';
            }
        }
    }

    protected function hydrateModeFromRoute(): void
    {
        $routeName = request()->route()?->getName();

        if ($routeName === 'fees.create') {
            $this->mode = 'create';
            $this->feeId = null;

            return;
        }

        if ($routeName === 'fees.edit') {
            $this->mode = 'edit';
            $this->feeId = $this->resolveRouteModelId(request()->route('fee'));
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

    public function loadFeeCategories()
    {
        $this->feeCategories = FeeCategory::query()
            ->where('school_id', auth()->user()->school_id)
            ->orderBy('name')
            ->get();
    }

    public function switchMode($mode, $feeId = null)
    {
        if (! $this->modeIsAllowed($mode)) {
            $this->mode = 'list';
            $this->feeId = null;

            return;
        }

        $this->mode = $mode;
        $this->feeId = $feeId;
        $this->resetValidation();

        if ($mode === 'edit' && $feeId) {
            $this->loadFeeForEdit();
        } elseif ($mode === 'create') {
            $this->resetForm();
        }
    }

    public function loadFeeForEdit()
    {
        $this->ensurePermission('update fee');

        $fee = $this->getFeeForCurrentSchool($this->feeId);

        $this->fill([
            'name' => $fee->name,
            'description' => $fee->description ?? '',
            'fee_category_id' => $fee->fee_category_id,
            'amount' => (string) $fee->default_amount->getAmount(),
            'waiverType' => $fee->default_waiver_type,
            'waiverAmount' => (string) $fee->default_waiver_amount->getAmount(),
            'waiverPercentage' => $fee->waiverPercentage(),
            'fine' => (string) $fee->default_fine->getAmount(),
        ]);
    }

    public function createCategoryInline(): void
    {
        $this->ensurePermission('create fee category');

        $validated = $this->validate([
            'newCategoryName' => [
                'required',
                'string',
                'max:100',
                Rule::unique('fee_categories', 'name')
                    ->where(fn ($query) => $query
                        ->where('school_id', auth()->user()->school_id)
                        ->whereNull('deleted_at')),
            ],
            'newCategoryDescription' => 'nullable|string|max:1000',
        ]);

        $category = DB::transaction(fn () => FeeCategory::query()->create([
            'school_id' => auth()->user()->school_id,
            'name' => trim($validated['newCategoryName']),
            'description' => filled($validated['newCategoryDescription'] ?? null)
                ? trim($validated['newCategoryDescription'])
                : null,
        ]));

        $this->loadFeeCategories();
        $this->fee_category_id = $category->id;
        $this->showCategoryCreator = false;
        $this->reset(['newCategoryName', 'newCategoryDescription']);
        $this->resetValidation(['newCategoryName', 'newCategoryDescription']);
        session()->flash('success', 'Fee category created and selected. Complete the fee details below.');
    }

    public function createFee()
    {
        $this->ensurePermission('create fee');

        $this->validate($this->feeRules());

        // Verify category belongs to school
        $this->getFeeCategoryForCurrentSchool($this->fee_category_id);

        DB::transaction(function () {
            Fee::create([
                'name' => $this->name,
                'description' => $this->description ?: null,
                'fee_category_id' => $this->fee_category_id,
                ...$this->pricingAttributes(),
                'status' => 'draft',
                'approval_version' => 0,
            ]);
        });

        session()->flash('success', 'Fee created as a draft. Approve it when the amount and explanation are ready for parents.');
        $this->switchMode('list');
    }

    public function updateFee()
    {
        $this->ensurePermission('update fee');

        $fee = $this->getFeeForCurrentSchool($this->feeId);

        $this->validate($this->feeRules());

        // Verify new category belongs to school
        $this->getFeeCategoryForCurrentSchool($this->fee_category_id);

        DB::transaction(function () use ($fee) {
            $fee->update([
                'name' => $this->name,
                'description' => $this->description ?: null,
                'fee_category_id' => $this->fee_category_id,
                ...$this->pricingAttributes(),
                'status' => 'draft',
                'approved_by' => null,
                'approved_at' => null,
            ]);
        });

        session()->flash('success', 'Fee updated and returned to draft for approval.');
        $this->switchMode('list');
    }

    public function approveFee(int $feeId, FeeApprovalService $approvals): void
    {
        $this->ensurePermission('approve fee');
        $fee = $this->getFeeForCurrentSchool($feeId);

        try {
            $result = $approvals->approve($fee, auth()->user());
        } catch (DomainException $exception) {
            session()->flash('error', $exception->getMessage());

            return;
        }

        $notifications = $result['notifications'];
        session()->flash(
            'success',
            "Fee approved. {$notifications['queued']} parent email(s) queued"
            .($notifications['failed'] > 0 ? "; {$notifications['failed']} could not be queued." : '.')
        );
    }

    public function deleteFee($feeId)
    {
        $this->ensurePermission('delete fee');

        $fee = $this->getFeeForCurrentSchool($feeId);

        DB::transaction(function () use ($fee) {
            $fee->delete();
        });

        session()->flash('success', 'Fee deleted successfully');
    }

    public function clearFilters()
    {
        $this->reset(['search', 'filterCategory']);
        $this->resetPage();
    }

    public function sortBy($field)
    {
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
            'feeId', 'name', 'description', 'fee_category_id', 'amount',
            'waiverType', 'waiverAmount', 'waiverPercentage', 'fine',
            'showCategoryCreator', 'newCategoryName', 'newCategoryDescription',
        ]);

        $this->waiverType = Fee::WAIVER_NONE;
        $this->waiverAmount = 0;
        $this->waiverPercentage = 0;
        $this->fine = 0;

        if ($this->feeCategories->isNotEmpty()) {
            $this->fee_category_id = $this->feeCategories->first()->id;
        }
    }

    protected function getFeesQuery()
    {
        return Fee::whereHas('feeCategory', function ($q) {
            $q->where('school_id', auth()->user()->school_id);
        })
            ->with('feeCategory')
            ->when($this->search, function ($q) {
                $q->where(function ($query) {
                    $query->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('description', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->filterCategory, function ($q) {
                $q->whereHas('feeCategory', function ($query) {
                    $query->where('id', $this->filterCategory)
                        ->where('school_id', auth()->user()->school_id);
                });
            })
            ->orderBy($this->sortField, $this->sortDirection);
    }

    /** @return array<string, mixed> */
    protected function feeRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'required|string|min:10|max:1000',
            'amount' => 'required|numeric|min:0.01|max:21474836.47|decimal:0,2',
            'waiverType' => ['required', Rule::in(array_keys(Fee::waiverTypes()))],
            'waiverAmount' => 'nullable|required_if:waiverType,fixed|numeric|min:0|max:21474836.47|decimal:0,2|lte:amount',
            'waiverPercentage' => 'nullable|required_if:waiverType,percentage|numeric|min:0|max:100|decimal:0,2',
            'fine' => 'required|numeric|min:0|max:21474836.47|decimal:0,2',
            'fee_category_id' => [
                'required',
                Rule::exists('fee_categories', 'id')
                    ->where(fn ($query) => $query
                        ->where('school_id', auth()->user()->school_id)
                        ->whereNull('deleted_at')),
            ],
        ];
    }

    /** @return array<string, mixed> */
    protected function pricingAttributes(): array
    {
        return [
            'default_amount' => $this->amount,
            'default_waiver_type' => $this->waiverType,
            'default_waiver_amount' => $this->waiverType === Fee::WAIVER_FIXED ? $this->waiverAmount : 0,
            'default_waiver_basis_points' => $this->waiverType === Fee::WAIVER_PERCENTAGE
                ? BigDecimal::of((string) $this->waiverPercentage)->multipliedBy(100)->toInt()
                : 0,
            'default_fine' => $this->fine,
        ];
    }

    protected function getFeeCategoryForCurrentSchool($feeCategoryId): FeeCategory
    {
        return FeeCategory::query()
            ->where('school_id', auth()->user()->school_id)
            ->findOrFail($feeCategoryId);
    }

    protected function getFeeForCurrentSchool($feeId): Fee
    {
        return Fee::whereHas('feeCategory', function ($query) {
            $query->where('school_id', auth()->user()->school_id);
        })->with('feeCategory')->findOrFail($feeId);
    }

    protected function modeIsAllowed(string $mode): bool
    {
        return match ($mode) {
            'create' => auth()->user()?->can('create fee') ?? false,
            'edit' => auth()->user()?->can('update fee') ?? false,
            default => true,
        };
    }

    protected function ensurePermission(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }

    public function render()
    {
        $fees = collect();

        if ($this->mode === 'list') {
            $fees = $this->getFeesQuery()->paginate($this->perPage);
        }

        return view('livewire.fees.manage-fees', compact('fees'))
            ->layout('layouts.dashboard', [
                'breadcrumbs' => [
                    ['href' => route('dashboard'), 'text' => 'Dashboard'],
                    ['href' => route('fees.index'), 'text' => 'Fees', 'active' => true],
                ],
            ])
            ->title('Manage Fees');
    }
}
