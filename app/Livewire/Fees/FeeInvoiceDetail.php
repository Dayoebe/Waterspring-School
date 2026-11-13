<?php

namespace App\Livewire\Fees;

use App\Models\Fee;
use App\Models\FeeCategory;
use App\Models\FeeInvoice;
use App\Models\FeeInvoiceRecord;
use App\Models\FeePaymentTransaction;
use App\Services\FeePaymentLedger;
use App\Traits\ResolvesAccessibleStudents;
use Brick\Money\Money;
use DomainException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

class FeeInvoiceDetail extends Component
{
    use AuthorizesRequests;
    use ResolvesAccessibleStudents;

    public FeeInvoice $feeInvoice;

    public $activeTab = 'details';

    public bool $canManage = false;

    // For adding new fees
    public $feeCategories = [];

    public $fees = [];

    public $selectedFeeCategory = '';

    public $selectedFee = '';

    public $newFeeAmount = 0;

    public $newFeeWaiver = 0;

    public $newFeeFine = 0;

    // For editing records
    public $editingRecordId = null;

    public $editAmount = 0;

    public $editWaiver = 0;

    public $editFine = 0;

    // For payments
    public $payingRecordId = null;

    public $paymentAmount = 0;

    public string $paymentMethod = 'cash';

    public string $paymentReference = '';

    public string $paymentNote = '';

    public ?int $reversingTransactionId = null;

    public string $reversalReason = '';

    public ?int $reconcilingTransactionId = null;

    public string $reconciliationStatus = 'matched';

    public string $reconciliationReference = '';

    public string $reconciliationNote = '';

    public function mount($feeInvoiceId)
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

        $this->feeInvoice = $query->with([
            'user.studentRecord.myClass',
            'user.studentRecord.section',
            'feeInvoiceRecords.fee',
            'feeInvoiceRecords.paymentTransactions.cashier',
            'feeInvoiceRecords.paymentTransactions.reversal',
            'feeInvoiceRecords.paymentTransactions.reconciliations',
        ])->findOrFail($feeInvoiceId);

        $this->canManage = auth()->user()?->can('update fee invoice') ?? false;
        if (! $this->canManage) {
            $this->activeTab = 'details';
        }

        $this->loadFeeCategories();
    }

    public function loadFeeCategories()
    {
        $this->feeCategories = FeeCategory::query()
            ->where('school_id', auth()->user()->school_id)
            ->orderBy('name')
            ->get();

        if ($this->feeCategories->isNotEmpty() && ! $this->selectedFeeCategory) {
            $this->selectedFeeCategory = $this->feeCategories->first()->id;
            $this->updatedSelectedFeeCategory();
        }
    }

    public function updatedSelectedFeeCategory()
    {
        if ($this->selectedFeeCategory) {
            $category = $this->getFeeCategoryForCurrentSchool($this->selectedFeeCategory);

            // Get fees not already in this invoice
            $existingFeeIds = $this->feeInvoice->feeInvoiceRecords->pluck('fee_id')->toArray();
            $this->fees = $category
                ? $category->fees()->approved()->whereNotIn('id', $existingFeeIds)->orderBy('name')->get()
                : collect();

            $this->selectedFee = $this->fees->isNotEmpty() ? $this->fees->first()->id : '';
            $this->updatedSelectedFee();
        }
    }

    public function updatedSelectedFee(): void
    {
        $fee = $this->fees->firstWhere('id', (int) $this->selectedFee);
        $defaults = $fee?->invoiceDefaults();
        $this->newFeeAmount = $defaults['amount'] ?? 0;
        $this->newFeeWaiver = $defaults['waiver'] ?? 0;
        $this->newFeeFine = $defaults['fine'] ?? 0;
    }

    public function changeTab($tab)
    {
        if (in_array($tab, ['payments', 'manage'], true) && ! $this->canManage) {
            $this->activeTab = 'details';

            return;
        }

        $this->activeTab = $tab;
    }

    public function startEditingRecord($recordId)
    {
        $this->ensureCanManage();

        $record = $this->getInvoiceRecordForCurrentInvoice($recordId);

        $this->editingRecordId = $recordId;
        $this->editAmount = $record->amount->getAmount()->toFloat();
        $this->editWaiver = $record->waiver->getAmount()->toFloat();
        $this->editFine = $record->fine->getAmount()->toFloat();
    }

    public function cancelEditingRecord()
    {
        $this->editingRecordId = null;
        $this->reset(['editAmount', 'editWaiver', 'editFine']);
    }

    public function updateRecord()
    {
        $this->ensureCanManage();

        $this->validate([
            'editAmount' => 'required|numeric|min:0',
            'editWaiver' => 'required|numeric|min:0',
            'editFine' => 'required|numeric|min:0',
        ]);

        $record = $this->getInvoiceRecordForCurrentInvoice($this->editingRecordId);

        $amount = Money::of($this->editAmount, config('app.currency'));
        $waiver = Money::of($this->editWaiver, config('app.currency'));
        $fine = Money::of($this->editFine, config('app.currency'));
        $paid = $record->paid;

        // Check if payment is higher than due
        $due = $amount->plus($fine)->minus($waiver);
        if ($due->isLessThan($paid)) {
            session()->flash('error', 'Due amount cannot be less than amount already paid');

            return;
        }

        DB::transaction(function () use ($record) {
            $record->update([
                'amount' => $this->editAmount,
                'waiver' => $this->editWaiver,
                'fine' => $this->editFine,
            ]);
        });

        $this->feeInvoice->refresh();
        $this->cancelEditingRecord();
        session()->flash('success', 'Fee record updated successfully');
    }

    public function addNewFee()
    {
        $this->ensureCanManage();

        $this->validate([
            'selectedFee' => 'required|exists:fees,id',
            'newFeeAmount' => 'required|numeric|min:0',
            'newFeeWaiver' => 'nullable|numeric|min:0',
            'newFeeFine' => 'nullable|numeric|min:0',
        ]);

        $fee = $this->getFeeForCurrentSchool($this->selectedFee);
        if (! $fee) {
            session()->flash('error', 'Selected fee is not in your current school.');

            return;
        }

        $alreadyExists = $this->feeInvoice->feeInvoiceRecords()
            ->where('fee_id', $fee->id)
            ->exists();
        if ($alreadyExists) {
            session()->flash('error', 'Selected fee is already attached to this invoice.');

            return;
        }

        $total = Money::of((string) $this->newFeeAmount, config('app.currency'))
            ->plus(Money::of((string) ($this->newFeeFine ?? 0), config('app.currency')))
            ->minus(Money::of((string) ($this->newFeeWaiver ?? 0), config('app.currency')));
        if ($total->isLessThan(0)) {
            $this->addError('newFeeWaiver', 'The waiver cannot make the fee total negative.');

            return;
        }

        DB::transaction(function () {
            $this->feeInvoice->feeInvoiceRecords()->create([
                'fee_id' => $this->selectedFee,
                'amount' => $this->newFeeAmount,
                'waiver' => $this->newFeeWaiver ?? 0,
                'fine' => $this->newFeeFine ?? 0,
            ]);
        });

        $this->feeInvoice->refresh();
        $this->reset(['newFeeAmount', 'newFeeWaiver', 'newFeeFine']);
        $this->updatedSelectedFeeCategory();

        session()->flash('success', 'Fee added to invoice successfully');
    }

    public function deleteRecord($recordId)
    {
        $this->ensureCanManage();

        $record = $this->getInvoiceRecordForCurrentInvoice($recordId);

        if ($record->paymentTransactions()->exists()) {
            session()->flash('error', 'Fee items with payment history cannot be deleted. Adjust the fee or reverse payments instead.');

            return;
        }

        DB::transaction(function () use ($record) {
            $record->delete();
        });

        $this->feeInvoice->refresh();
        session()->flash('success', 'Fee record deleted successfully');
    }

    public function startPayment($recordId)
    {
        $this->ensureCanManage();

        $this->payingRecordId = $recordId;
        $this->paymentAmount = 0;
        $this->paymentMethod = 'cash';
        $this->paymentReference = '';
        $this->paymentNote = '';
    }

    public function cancelPayment()
    {
        $this->payingRecordId = null;
        $this->paymentAmount = 0;
        $this->paymentMethod = 'cash';
        $this->paymentReference = '';
        $this->paymentNote = '';
        $this->resetValidation(['paymentAmount', 'paymentMethod', 'paymentReference', 'paymentNote']);
    }

    public function addPayment(FeePaymentLedger $ledger)
    {
        $this->ensureCanManage();

        $this->validate([
            'paymentAmount' => 'required|numeric|min:0.01',
            'paymentMethod' => ['required', Rule::in(array_keys(FeePaymentLedger::PAYMENT_METHODS))],
            'paymentReference' => 'nullable|string|max:120',
            'paymentNote' => 'nullable|string|max:1000',
        ]);

        $record = $this->getInvoiceRecordForCurrentInvoice($this->payingRecordId);

        try {
            $transaction = $ledger->recordPayment(
                $record,
                $this->paymentAmount,
                auth()->user(),
                $this->paymentMethod,
                $this->paymentReference,
                $this->paymentNote
            );
        } catch (DomainException $exception) {
            session()->flash('error', $exception->getMessage());

            return;
        }

        $this->refreshInvoice();
        $this->cancelPayment();
        session()->flash('success', "Payment recorded. Receipt {$transaction->receipt_number} is ready.");
    }

    public function startReversal(int $transactionId): void
    {
        $this->ensureCanManage();
        $this->getPaymentTransaction($transactionId);
        $this->reversingTransactionId = $transactionId;
        $this->reversalReason = '';
    }

    public function cancelReversal(): void
    {
        $this->reversingTransactionId = null;
        $this->reversalReason = '';
        $this->resetValidation('reversalReason');
    }

    public function reversePayment(FeePaymentLedger $ledger): void
    {
        $this->ensureCanManage();
        $this->validate(['reversalReason' => 'required|string|min:5|max:1000']);

        try {
            $reversal = $ledger->reverse(
                $this->getPaymentTransaction($this->reversingTransactionId),
                auth()->user(),
                $this->reversalReason
            );
        } catch (DomainException $exception) {
            session()->flash('error', $exception->getMessage());

            return;
        }

        $this->refreshInvoice();
        $this->cancelReversal();
        session()->flash('success', "Payment reversed with audit reference {$reversal->receipt_number}.");
    }

    public function startReconciliation(int $transactionId): void
    {
        $this->ensureCanManage();
        $this->getPaymentTransaction($transactionId);
        $this->reconcilingTransactionId = $transactionId;
        $this->reconciliationStatus = 'matched';
        $this->reconciliationReference = '';
        $this->reconciliationNote = '';
    }

    public function cancelReconciliation(): void
    {
        $this->reconcilingTransactionId = null;
        $this->reconciliationStatus = 'matched';
        $this->reconciliationReference = '';
        $this->reconciliationNote = '';
        $this->resetValidation([
            'reconciliationStatus',
            'reconciliationReference',
            'reconciliationNote',
        ]);
    }

    public function recordReconciliation(FeePaymentLedger $ledger): void
    {
        $this->ensureCanManage();
        $this->validate([
            'reconciliationStatus' => ['required', Rule::in(array_keys(FeePaymentLedger::RECONCILIATION_STATUSES))],
            'reconciliationReference' => 'nullable|string|max:120',
            'reconciliationNote' => 'nullable|string|max:1000',
        ]);

        try {
            $ledger->reconcile(
                $this->getPaymentTransaction($this->reconcilingTransactionId),
                auth()->user(),
                $this->reconciliationStatus,
                $this->reconciliationReference,
                $this->reconciliationNote
            );
        } catch (DomainException $exception) {
            session()->flash('error', $exception->getMessage());

            return;
        }

        $this->refreshInvoice();
        $this->cancelReconciliation();
        session()->flash('success', 'Reconciliation event added to the payment history.');
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

    protected function getInvoiceRecordForCurrentInvoice($recordId): FeeInvoiceRecord
    {
        return FeeInvoiceRecord::where('fee_invoice_id', $this->feeInvoice->id)
            ->whereHas('feeInvoice.user', function ($query) {
                $query->where('school_id', auth()->user()->school_id);
            })
            ->findOrFail($recordId);
    }

    protected function getPaymentTransaction(?int $transactionId): FeePaymentTransaction
    {
        abort_unless($transactionId, 404);

        return FeePaymentTransaction::query()
            ->where('school_id', auth()->user()->school_id)
            ->where('fee_invoice_id', $this->feeInvoice->id)
            ->findOrFail($transactionId);
    }

    protected function refreshInvoice(): void
    {
        $this->feeInvoice->refresh()->load([
            'user.studentRecord.myClass',
            'user.studentRecord.section',
            'feeInvoiceRecords.fee',
            'feeInvoiceRecords.paymentTransactions.cashier',
            'feeInvoiceRecords.paymentTransactions.reversal',
            'feeInvoiceRecords.paymentTransactions.reconciliations',
        ]);
    }

    protected function ensureCanManage(): void
    {
        abort_unless($this->canManage, 403);
    }

    public function render()
    {
        return view('livewire.fees.fee-invoice-detail', [
            'feeInvoice' => $this->feeInvoice,
            'paymentMethods' => FeePaymentLedger::PAYMENT_METHODS,
            'reconciliationStatuses' => FeePaymentLedger::RECONCILIATION_STATUSES,
            'paystackEnabled' => (bool) config('services.paystack.enabled')
                && filled(config('services.paystack.secret_key')),
        ])
            ->layout('layouts.dashboard', [
                'breadcrumbs' => [
                    ['href' => route('dashboard'), 'text' => 'Dashboard'],
                    ['href' => route('fees.index'), 'text' => 'Fees & Payments'],
                    ['href' => route('fee-invoices.show', $this->feeInvoice->id), 'text' => $this->feeInvoice->name, 'active' => true],
                ],
            ])
            ->title($this->feeInvoice->name);
    }
}
