<?php

namespace Tests\Feature\Fees;

use App\Models\Fee;
use App\Models\FeeCategory;
use App\Models\FeeInvoice;
use App\Models\FeeInvoiceRecord;
use App\Models\School;
use App\Models\User;
use App\Services\FeePaymentLedger;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class FeePaymentLedgerTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    public function test_payment_creates_a_receipt_and_updates_only_the_derived_projection(): void
    {
        [$school, $cashier, $record] = $this->paymentContext();

        $payment = app(FeePaymentLedger::class)->recordPayment(
            $record,
            '35.50',
            $cashier,
            'bank_transfer',
            'BANK-2026-001',
            'First instalment'
        );

        $this->assertSame('payment', $payment->type);
        $this->assertSame($school->id, $payment->school_id);
        $this->assertSame($cashier->id, $payment->cashier_id);
        $this->assertSame($cashier->name, $payment->cashier_name);
        $this->assertSame('bank_transfer', $payment->payment_method);
        $this->assertSame('BANK-2026-001', $payment->external_reference);
        $this->assertMatchesRegularExpression('/^WSP-PAY-\d+-\d{8}-[A-Z0-9]{10}$/', $payment->receipt_number);
        $this->assertSame(3550, (int) $payment->getRawOriginal('amount'));
        $this->assertSame(3550, (int) $record->fresh()->getRawOriginal('paid'));
    }

    public function test_overpayment_is_rejected_without_changing_the_ledger(): void
    {
        [, $cashier, $record] = $this->paymentContext(amount: 100);
        $ledger = app(FeePaymentLedger::class);
        $ledger->recordPayment($record, 80, $cashier, 'cash');

        try {
            $ledger->recordPayment($record, 21, $cashier, 'cash');
            $this->fail('Expected overpayment to be rejected.');
        } catch (DomainException $exception) {
            $this->assertSame('Payment cannot be higher than the outstanding balance.', $exception->getMessage());
        }

        $this->assertDatabaseCount('fee_payment_transactions', 1);
        $this->assertSame(8000, (int) $record->fresh()->getRawOriginal('paid'));
    }

    public function test_reversal_is_linked_append_only_and_cannot_be_repeated(): void
    {
        [, $cashier, $record] = $this->paymentContext();
        $ledger = app(FeePaymentLedger::class);
        $payment = $ledger->recordPayment($record, 40, $cashier, 'pos', 'POS-44');
        $reversal = $ledger->reverse($payment, $cashier, 'POS settlement was voided.');

        $this->assertSame('reversal', $reversal->type);
        $this->assertSame($payment->id, $reversal->reverses_transaction_id);
        $this->assertSame(0, (int) $record->fresh()->getRawOriginal('paid'));
        $this->assertDatabaseCount('fee_payment_transactions', 2);

        try {
            $ledger->reverse($payment, $cashier, 'Second reversal attempt.');
            $this->fail('Expected a repeated reversal to be rejected.');
        } catch (DomainException $exception) {
            $this->assertSame('This payment has already been reversed.', $exception->getMessage());
        }

        $this->assertDatabaseCount('fee_payment_transactions', 2);
    }

    public function test_transactions_and_reconciliation_events_cannot_be_edited_or_deleted(): void
    {
        [, $cashier, $record] = $this->paymentContext();
        $ledger = app(FeePaymentLedger::class);
        $payment = $ledger->recordPayment($record, 25, $cashier, 'cash');
        $reconciliation = $ledger->reconcile(
            $payment,
            $cashier,
            'matched',
            'CASHBOOK-15',
            'Matched against daily cashbook.'
        );

        $this->assertSame('matched', $reconciliation->status);
        $this->assertSame($cashier->name, $reconciliation->reconciler_name);

        $this->assertImmutable(fn () => $payment->update(['notes' => 'Changed']), 'Fee payment transactions are immutable.');
        $this->assertImmutable(fn () => $payment->delete(), 'Fee payment transactions cannot be deleted.');
        $this->assertImmutable(
            fn () => $reconciliation->update(['status' => 'disputed']),
            'Fee reconciliation history is immutable.'
        );
        $this->assertImmutable(
            fn () => $reconciliation->delete(),
            'Fee reconciliation history cannot be deleted.'
        );
        $this->assertImmutable(
            function () use ($record): void {
                $record->setAttribute('paid', 1);
                $record->save();
            },
            'The paid total is maintained by the immutable payment ledger.'
        );
        $record->refresh();
        $this->assertImmutable(fn () => $record->delete(), 'Fee items with payment history cannot be deleted.');
        $this->assertImmutable(
            fn () => $record->feeInvoice->delete(),
            'Invoices with payment history cannot be deleted.'
        );

        $this->assertDatabaseCount('fee_payment_transactions', 1);
        $this->assertDatabaseCount('fee_payment_reconciliations', 1);
    }

    public function test_receipt_is_printable_only_inside_the_invoice_school(): void
    {
        [$schoolA, $cashier, $record] = $this->paymentContext();
        $payment = app(FeePaymentLedger::class)->recordPayment($record, 15, $cashier, 'cash');
        $cashier->givePermissionTo(Permission::findOrCreate('read fee invoice', 'web'));

        $this->actingAs($cashier)
            ->get(route('fee-payments.receipt', $payment->id))
            ->assertOk()
            ->assertSee($payment->receipt_number)
            ->assertSee($schoolA->name);

        $schoolB = $this->school('B');
        $otherManager = $this->user($schoolB, 'other-manager');
        $otherManager->givePermissionTo(Permission::findOrCreate('read fee invoice', 'web'));

        $this->actingAs($otherManager)
            ->get(route('fee-payments.receipt', $payment->id))
            ->assertNotFound();
    }

    /**
     * @return array{School, User, FeeInvoiceRecord}
     */
    private function paymentContext(int $amount = 100): array
    {
        $school = $this->school('A');
        $cashier = $this->user($school, 'cashier');
        $student = $this->user($school, 'student');
        $invoice = FeeInvoice::query()->create([
            'name' => 'Term invoice',
            'user_id' => $student->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addMonth()->toDateString(),
        ]);
        $category = FeeCategory::query()->create([
            'school_id' => $school->id,
            'name' => 'Tuition '.$this->sequence,
        ]);
        $fee = Fee::query()->create([
            'fee_category_id' => $category->id,
            'name' => 'School fees '.$this->sequence,
        ]);
        $record = FeeInvoiceRecord::query()->create([
            'fee_invoice_id' => $invoice->id,
            'fee_id' => $fee->id,
            'amount' => $amount,
            'waiver' => 0,
            'fine' => 0,
        ]);

        return [$school, $cashier, $record];
    }

    private function school(string $suffix): School
    {
        $this->sequence++;

        return School::query()->create([
            'name' => "Ledger School {$suffix} {$this->sequence}",
            'code' => "LEDGER-{$suffix}-{$this->sequence}",
        ]);
    }

    private function user(School $school, string $label): User
    {
        $this->sequence++;

        return User::query()->forceCreate([
            'name' => ucfirst(str_replace('-', ' ', $label)),
            'email' => "{$label}-{$this->sequence}@example.test",
            'email_verified_at' => now(),
            'password' => bcrypt('Password1!'),
            'school_id' => $school->id,
            'locked' => false,
            'requires_password_change' => false,
        ]);
    }

    private function assertImmutable(callable $operation, string $message): void
    {
        try {
            $operation();
            $this->fail('Expected immutable model operation to fail.');
        } catch (LogicException $exception) {
            $this->assertSame($message, $exception->getMessage());
        }
    }
}
