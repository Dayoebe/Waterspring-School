<?php

namespace App\Livewire\Fees;

use App\Models\FeeInvoiceRecord;
use App\Models\FeePaymentTransaction;
use App\Models\MyClass;
use App\Services\FeePaymentLedger;
use Brick\Money\Money;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class ManageFeePayments extends Component
{
    use WithPagination;

    public string $search = '';

    public string $classFilter = '';

    public string $statusFilter = 'all';

    public int $perPage = 20;

    public array $classes = [];

    public ?int $payingRecordId = null;

    public string $paymentAmount = '';

    public string $paymentMethod = 'cash';

    public string $paymentReference = '';

    public string $paymentNote = '';

    public ?int $lastPaymentId = null;

    public function mount(): void
    {
        $this->ensurePermission('update fee invoice');
        $this->classes = MyClass::query()
            ->whereHas('classGroup', fn ($query) => $query
                ->where('school_id', auth()->user()->school_id))
            ->instructional()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (MyClass $class): array => [
                'id' => (int) $class->id,
                'name' => $class->name,
            ])
            ->all();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedClassFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'classFilter', 'statusFilter']);
        $this->statusFilter = 'all';
        $this->resetPage();
    }

    public function startPayment(int $recordId): void
    {
        $this->ensurePermission('update fee invoice');
        $record = $this->recordForCurrentSchool($recordId);
        $balance = $record->amount
            ->plus($record->fine)
            ->minus($record->waiver)
            ->minus($record->paid);

        if ($balance->isLessThanOrEqualTo(0)) {
            session()->flash('error', 'This fee has already been paid in full.');

            return;
        }

        $this->payingRecordId = $record->id;
        $this->paymentAmount = (string) $balance->getAmount();
        $this->paymentMethod = 'cash';
        $this->paymentReference = '';
        $this->paymentNote = '';
        $this->lastPaymentId = null;
        $this->resetValidation();
    }

    public function cancelPayment(): void
    {
        $this->reset([
            'payingRecordId',
            'paymentAmount',
            'paymentMethod',
            'paymentReference',
            'paymentNote',
        ]);
        $this->paymentMethod = 'cash';
        $this->resetValidation();
    }

    public function recordManualPayment(FeePaymentLedger $ledger): void
    {
        $this->ensurePermission('update fee invoice');
        $validated = $this->validate([
            'paymentAmount' => 'required|numeric|min:0.01|max:21474836.47|decimal:0,2',
            'paymentMethod' => ['required', Rule::in(array_keys(FeePaymentLedger::PAYMENT_METHODS))],
            'paymentReference' => 'nullable|string|max:120',
            'paymentNote' => 'nullable|string|max:1000',
        ]);

        try {
            $payment = $ledger->recordPayment(
                $this->recordForCurrentSchool($this->payingRecordId),
                $validated['paymentAmount'],
                auth()->user(),
                $validated['paymentMethod'],
                $validated['paymentReference'] ?: null,
                $validated['paymentNote'] ?: null,
            );
        } catch (DomainException $exception) {
            session()->flash('error', $exception->getMessage());

            return;
        }

        $receiptNumber = $payment->receipt_number;
        $this->cancelPayment();
        $this->lastPaymentId = $payment->id;
        session()->flash('success', "Manual payment recorded. Receipt {$receiptNumber} is ready.");
    }

    protected function baseRecordsQuery(): Builder
    {
        return FeeInvoiceRecord::query()
            ->join('fee_invoices as collection_invoices', 'collection_invoices.id', '=', 'fee_invoice_records.fee_invoice_id')
            ->join('users as collection_students', 'collection_students.id', '=', 'collection_invoices.user_id')
            ->leftJoin('my_classes as collection_classes', 'collection_classes.id', '=', 'collection_invoices.my_class_id')
            ->where('collection_students.school_id', auth()->user()->school_id)
            ->whereNull('collection_students.deleted_at')
            ->whereNull('collection_invoices.deleted_at');
    }

    protected function filteredRecordsQuery(): Builder
    {
        $query = $this->baseRecordsQuery()
            ->select('fee_invoice_records.*')
            ->with([
                'fee',
                'feeInvoice.user.studentRecord.myClass',
                'feeInvoice.user.studentRecord.section',
                'feeInvoice.billingClass',
                'feeInvoice.billingSection',
            ])
            ->when($this->search, function (Builder $query): void {
                $search = '%'.$this->search.'%';
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('collection_students.name', 'like', $search)
                        ->orWhere('collection_invoices.name', 'like', $search)
                        ->orWhereHas('fee', fn (Builder $feeQuery) => $feeQuery
                            ->where('name', 'like', $search));
                });
            })
            ->when($this->classFilter, fn (Builder $query) => $query
                ->where('collection_invoices.my_class_id', $this->classFilter));

        return $this->applyStatus($query, $this->statusFilter)
            ->orderBy('collection_classes.name')
            ->orderBy('collection_students.name')
            ->orderByDesc('collection_invoices.due_date');
    }

    protected function applyStatus(Builder $query, string $status): Builder
    {
        return match ($status) {
            'paid' => $query->whereRaw('(fee_invoice_records.amount + fee_invoice_records.fine) <= (fee_invoice_records.paid + fee_invoice_records.waiver)'),
            'partial' => $query
                ->where('fee_invoice_records.paid', '>', 0)
                ->whereRaw('(fee_invoice_records.amount + fee_invoice_records.fine) > (fee_invoice_records.paid + fee_invoice_records.waiver)'),
            'unpaid' => $query
                ->where('fee_invoice_records.paid', '<=', 0)
                ->whereRaw('(fee_invoice_records.amount + fee_invoice_records.fine) > fee_invoice_records.waiver'),
            default => $query,
        };
    }

    protected function recordForCurrentSchool(?int $recordId): FeeInvoiceRecord
    {
        abort_unless($recordId, 404);

        return FeeInvoiceRecord::query()
            ->whereHas('feeInvoice', fn ($invoiceQuery) => $invoiceQuery
                ->whereHas('user', fn ($userQuery) => $userQuery
                    ->where('school_id', auth()->user()->school_id)
                    ->whereNull('deleted_at')))
            ->with(['fee', 'feeInvoice.user'])
            ->findOrFail($recordId);
    }

    protected function ensurePermission(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }

    public function render()
    {
        $summaryBase = $this->baseRecordsQuery();
        $summary = [
            'total' => (clone $summaryBase)->count(),
            'paid' => $this->applyStatus(clone $summaryBase, 'paid')->count(),
            'partial' => $this->applyStatus(clone $summaryBase, 'partial')->count(),
            'unpaid' => $this->applyStatus(clone $summaryBase, 'unpaid')->count(),
            'outstanding' => Money::ofMinor(
                (int) (clone $summaryBase)
                    ->selectRaw('COALESCE(SUM(GREATEST((fee_invoice_records.amount + fee_invoice_records.fine) - (fee_invoice_records.paid + fee_invoice_records.waiver), 0)), 0) AS outstanding')
                    ->value('outstanding'),
                config('app.currency')
            ),
        ];

        return view('livewire.fees.manage-fee-payments', [
            'records' => $this->filteredRecordsQuery()->paginate($this->perPage),
            'summary' => $summary,
            'paymentMethods' => FeePaymentLedger::PAYMENT_METHODS,
            'selectedRecord' => $this->payingRecordId
                ? $this->recordForCurrentSchool($this->payingRecordId)
                : null,
            'lastPayment' => $this->lastPaymentId
                ? FeePaymentTransaction::query()
                    ->where('school_id', auth()->user()->school_id)
                    ->find($this->lastPaymentId)
                : null,
        ]);
    }
}
