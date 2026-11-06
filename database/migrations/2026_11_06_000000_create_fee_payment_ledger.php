<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('fee_invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('fee_invoice_record_id')->constrained()->restrictOnDelete();
            $table->string('type', 24);
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3);
            $table->string('receipt_number', 80)->unique();
            $table->string('payment_method', 32);
            $table->string('external_reference', 120)->nullable();
            $table->string('provider', 40)->nullable();
            $table->string('provider_reference', 120)->nullable();
            $table->foreignId('cashier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cashier_name');
            $table->string('payer_name');
            $table->foreignId('reverses_transaction_id')
                ->nullable()
                ->unique()
                ->constrained('fee_payment_transactions')
                ->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->ipAddress('recorded_ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['school_id', 'occurred_at'], 'fee_payments_school_date_index');
            $table->index(['fee_invoice_record_id', 'type'], 'fee_payments_record_type_index');
            $table->unique(['provider', 'provider_reference'], 'fee_payments_provider_reference_unique');
        });

        Schema::create('fee_payment_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_payment_transaction_id')
                ->constrained()
                ->restrictOnDelete();
            $table->string('status', 24);
            $table->string('statement_reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reconciler_name');
            $table->timestamp('created_at')->useCurrent();

            $table->index(
                ['fee_payment_transaction_id', 'created_at'],
                'fee_reconciliation_transaction_date_index'
            );
        });

        $currency = strtoupper((string) config('app.currency', 'NGN'));

        DB::table('fee_invoice_records as records')
            ->join('fee_invoices as invoices', 'invoices.id', '=', 'records.fee_invoice_id')
            ->join('users', 'users.id', '=', 'invoices.user_id')
            ->where('records.paid', '>', 0)
            ->orderBy('records.id')
            ->select([
                'records.id as record_id',
                'records.fee_invoice_id',
                'records.paid',
                'records.created_at',
                'records.updated_at',
                'users.school_id',
                'users.name as payer_name',
            ])
            ->chunkById(250, function ($records) use ($currency): void {
                $now = now();
                $rows = $records->map(fn ($record) => [
                    'school_id' => $record->school_id,
                    'fee_invoice_id' => $record->fee_invoice_id,
                    'fee_invoice_record_id' => $record->record_id,
                    'type' => 'legacy',
                    'amount' => $record->paid,
                    'currency' => $currency,
                    'receipt_number' => 'LEGACY-FEE-'.$record->record_id,
                    'payment_method' => 'legacy',
                    'external_reference' => null,
                    'provider' => null,
                    'provider_reference' => null,
                    'cashier_id' => null,
                    'cashier_name' => 'Legacy balance import',
                    'payer_name' => $record->payer_name,
                    'reverses_transaction_id' => null,
                    'notes' => 'Opening balance imported from the historical paid total.',
                    'metadata' => json_encode(['source' => 'fee_invoice_records.paid']),
                    'recorded_ip' => null,
                    'user_agent' => null,
                    'occurred_at' => $record->updated_at ?? $record->created_at ?? $now,
                    'created_at' => $now,
                ])->all();

                if ($rows !== []) {
                    DB::table('fee_payment_transactions')->insert($rows);
                }
            }, 'records.id', 'record_id');
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_payment_reconciliations');
        Schema::dropIfExists('fee_payment_transactions');
    }
};
