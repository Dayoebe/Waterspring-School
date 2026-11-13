<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('fee_invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('fee_invoice_record_id')->constrained()->restrictOnDelete();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider', 40)->default('paystack');
            $table->string('reference', 120)->unique();
            $table->string('access_code', 120)->nullable();
            $table->text('authorization_url')->nullable();
            $table->unsignedBigInteger('provider_transaction_id')->nullable();
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3);
            $table->string('payer_email');
            $table->string('status', 32)->default('created');
            $table->string('gateway_response')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('provider_payload')->nullable();
            $table->foreignId('fee_payment_transaction_id')
                ->nullable()
                ->unique()
                ->constrained('fee_payment_transactions')
                ->restrictOnDelete();
            $table->timestamp('initialized_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['provider', 'provider_transaction_id'],
                'fee_attempts_provider_transaction_unique'
            );
            $table->index(['school_id', 'status'], 'fee_attempts_school_status_index');
            $table->index(
                ['fee_invoice_record_id', 'status'],
                'fee_attempts_record_status_index'
            );
        });

        Schema::create('fee_payment_notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fee_payment_transaction_id');
            $table->foreign('fee_payment_transaction_id', 'fee_notification_transaction_fk')
                ->references('id')
                ->on('fee_payment_transactions')
                ->restrictOnDelete();
            $table->foreignId('recipient_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient_email');
            $table->string('event', 40);
            $table->string('deduplication_key', 190)->unique();
            $table->string('status', 24)->default('pending');
            $table->timestamp('queued_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(
                ['fee_payment_transaction_id', 'event'],
                'fee_notification_transaction_event_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_payment_notification_deliveries');
        Schema::dropIfExists('fee_payment_attempts');
    }
};
