<?php

namespace Tests\Feature\Fees;

use App\Mail\FeePaymentReceiptMail;
use App\Models\Fee;
use App\Models\FeeCategory;
use App\Models\FeeInvoice;
use App\Models\FeeInvoiceRecord;
use App\Models\FeePaymentAttempt;
use App\Models\School;
use App\Models\User;
use App\Services\FlutterwavePaymentService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class FlutterwaveFeePaymentTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'services.flutterwave.enabled' => true,
            'services.flutterwave.secret_key' => 'FLWSECK_TEST-12345678901234567890-X',
            'services.flutterwave.secret_hash' => 'flutterwave-test-secret-hash',
            'services.flutterwave.base_url' => 'https://api.flutterwave.com/v3',
            'services.flutterwave.callback_url' => 'https://school.example/payments/flutterwave/callback',
            'services.flutterwave.timeout' => 5,
        ]);
        $this->app['url']->forceRootUrl('https://school.example');
        $this->app['url']->forceScheme('https');
    }

    public function test_initialization_uses_server_amount_invoice_identity_and_secret_key(): void
    {
        [$school, $payer, $record] = $this->paymentContext();

        Http::fake([
            'api.flutterwave.com/v3/payments' => Http::response([
                'status' => 'success',
                'data' => ['link' => 'https://checkout.flutterwave.com/v3/hosted/pay/secure-code'],
            ]),
        ]);

        $attempt = app(FlutterwavePaymentService::class)->initialize($record, $payer, '25.50');

        $this->assertSame($school->id, $attempt->school_id);
        $this->assertSame($record->id, $attempt->fee_invoice_record_id);
        $this->assertSame('flutterwave', $attempt->provider);
        $this->assertSame(2550, (int) $attempt->getRawOriginal('amount'));
        $this->assertSame('NGN', $attempt->currency);
        $this->assertSame('initialized', $attempt->status);

        Http::assertSent(function ($request) use ($attempt, $record): bool {
            return $request->url() === 'https://api.flutterwave.com/v3/payments'
                && $request['amount'] === '25.50'
                && $request['tx_ref'] === $attempt->reference
                && $request['meta']['fee_invoice_record_id'] === $record->id
                && $request->hasHeader('Authorization', 'Bearer FLWSECK_TEST-12345678901234567890-X');
        });
    }

    public function test_verified_payment_posts_once_to_ledger_and_queues_one_receipt(): void
    {
        Mail::fake();
        [, $payer, $record] = $this->paymentContext();
        $attempt = $this->initializedAttempt($record, $payer, '40.00');
        $transactionId = 987654;

        Http::fake([
            "api.flutterwave.com/v3/transactions/{$transactionId}/verify" => Http::response(
                $this->verificationResponse($attempt, '40.00', $transactionId)
            ),
        ]);

        $service = app(FlutterwavePaymentService::class);
        $first = $service->verifyAndFulfill($attempt->reference, $transactionId);
        $second = $service->verifyAndFulfill($attempt->reference, $transactionId);

        $this->assertSame('paid', $first->status);
        $this->assertSame($first->fee_payment_transaction_id, $second->fee_payment_transaction_id);
        $this->assertDatabaseCount('fee_payment_transactions', 1);
        $this->assertDatabaseHas('fee_payment_transactions', [
            'provider' => 'flutterwave',
            'provider_reference' => 'FLW-987654',
            'external_reference' => (string) $transactionId,
        ]);
        $this->assertSame(4000, (int) $record->fresh()->getRawOriginal('paid'));
        Mail::assertQueued(FeePaymentReceiptMail::class, 1);
    }

    public function test_amount_mismatch_is_quarantined_without_changing_balance(): void
    {
        [, $payer, $record] = $this->paymentContext();
        $attempt = $this->initializedAttempt($record, $payer, '40.00');
        $transactionId = 987655;

        Http::fake([
            "api.flutterwave.com/v3/transactions/{$transactionId}/verify" => Http::response(
                $this->verificationResponse($attempt, '39.99', $transactionId)
            ),
        ]);

        try {
            app(FlutterwavePaymentService::class)->verifyAndFulfill($attempt->reference, $transactionId);
            $this->fail('Expected the mismatched amount to be rejected.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('does not match', $exception->getMessage());
        }

        $this->assertSame('review_required', $attempt->fresh()->status);
        $this->assertDatabaseCount('fee_payment_transactions', 0);
        $this->assertSame(0, (int) $record->fresh()->getRawOriginal('paid'));
    }

    public function test_untrusted_transaction_id_cannot_be_attached_to_a_different_reference(): void
    {
        [, $payer, $record] = $this->paymentContext();
        $attempt = $this->initializedAttempt($record, $payer, '40.00');
        $transactionId = 987659;
        $response = $this->verificationResponse($attempt, '40.00', $transactionId);
        $response['data']['tx_ref'] = 'WSP-FLW-DIFFERENT';

        Http::fake([
            "api.flutterwave.com/v3/transactions/{$transactionId}/verify" => Http::response($response),
        ]);

        try {
            app(FlutterwavePaymentService::class)->verifyAndFulfill($attempt->reference, $transactionId);
            $this->fail('Expected the mismatched reference to be rejected.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('different payment reference', $exception->getMessage());
        }

        $this->assertNull($attempt->fresh()->provider_transaction_id);
        $this->assertDatabaseCount('fee_payment_transactions', 0);
        $this->assertSame(0, (int) $record->fresh()->getRawOriginal('paid'));
    }

    public function test_signed_webhook_fulfils_payment_and_replay_is_idempotent(): void
    {
        Mail::fake();
        [, $payer, $record] = $this->paymentContext();
        $attempt = $this->initializedAttempt($record, $payer, '18.00');
        $transactionId = 987656;

        Http::fake([
            "api.flutterwave.com/v3/transactions/{$transactionId}/verify" => Http::response(
                $this->verificationResponse($attempt, '18.00', $transactionId)
            ),
        ]);

        $payload = [
            'event' => 'charge.completed',
            'data' => [
                'id' => $transactionId,
                'tx_ref' => $attempt->reference,
                'status' => 'successful',
            ],
        ];

        foreach ([1, 2] as $delivery) {
            $this->withHeader('verif-hash', 'flutterwave-test-secret-hash')
                ->postJson(route('api.fee-payments.flutterwave.webhook'), $payload)
                ->assertOk()
                ->assertJson(['matched' => true]);
        }

        $this->assertDatabaseCount('fee_payment_transactions', 1);
        $this->assertSame(1800, (int) $record->fresh()->getRawOriginal('paid'));
        Mail::assertQueued(FeePaymentReceiptMail::class, 1);
    }

    public function test_invalid_webhook_secret_is_rejected_before_verification(): void
    {
        Http::fake();

        $this->withHeader('verif-hash', 'wrong-secret')
            ->postJson(route('api.fee-payments.flutterwave.webhook'), [
                'event' => 'charge.completed',
                'data' => ['id' => 987657, 'tx_ref' => 'WSP-FLW-UNKNOWN', 'status' => 'successful'],
            ])
            ->assertUnauthorized();

        Http::assertNothingSent();
    }

    public function test_successful_callback_verifies_provider_data_before_showing_confirmation(): void
    {
        [, $payer, $record] = $this->paymentContext();
        $attempt = $this->initializedAttempt($record, $payer, '12.00');
        $transactionId = 987658;

        Http::fake([
            "api.flutterwave.com/v3/transactions/{$transactionId}/verify" => Http::response(
                $this->verificationResponse($attempt, '12.00', $transactionId)
            ),
        ]);

        $this->get(route('fee-payments.flutterwave.callback', [
            'status' => 'successful',
            'tx_ref' => $attempt->reference,
            'transaction_id' => $transactionId,
        ]))
            ->assertOk()
            ->assertSee('Payment confirmed');

        $this->assertSame(1200, (int) $record->fresh()->getRawOriginal('paid'));
        $this->assertDatabaseCount('fee_payment_transactions', 1);
    }

    public function test_other_school_cannot_initialize_flutterwave_for_invoice(): void
    {
        [, , $record] = $this->paymentContext();
        $otherSchool = $this->school('B');
        $otherUser = $this->user($otherSchool, 'outsider');
        $otherUser->givePermissionTo(Permission::findOrCreate('read fee invoice', 'web'));

        $this->actingAs($otherUser)
            ->post(route('fee-payments.flutterwave.initialize', [$record->feeInvoice, $record]), [
                'amount' => 10,
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('fee_payment_attempts', 0);
    }

    public function test_ready_flutterwave_is_offered_as_an_online_payment_choice(): void
    {
        [, $payer, $record] = $this->paymentContext();
        $payer->givePermissionTo(Permission::findOrCreate('read fee invoice', 'web'));

        $this->actingAs($payer)
            ->get(route('fee-invoices.show', $record->feeInvoice))
            ->assertOk()
            ->assertSee('Flutterwave')
            ->assertDontSee('Paystack');
    }

    private function initializedAttempt(FeeInvoiceRecord $record, User $payer, string $amount): FeePaymentAttempt
    {
        Http::fake([
            'api.flutterwave.com/v3/payments' => Http::response([
                'status' => 'success',
                'data' => ['link' => 'https://checkout.flutterwave.com/v3/hosted/pay/secure-code'],
            ]),
        ]);

        return app(FlutterwavePaymentService::class)->initialize($record, $payer, $amount);
    }

    private function verificationResponse(FeePaymentAttempt $attempt, string $amount, int $transactionId): array
    {
        return [
            'status' => 'success',
            'data' => [
                'id' => $transactionId,
                'tx_ref' => $attempt->reference,
                'flw_ref' => 'FLW-'.$transactionId,
                'status' => 'successful',
                'amount' => $amount,
                'charged_amount' => $amount,
                'currency' => 'NGN',
                'processor_response' => 'Approved',
                'payment_type' => 'card',
                'created_at' => now()->toIso8601String(),
            ],
        ];
    }

    /** @return array{School, User, FeeInvoiceRecord} */
    private function paymentContext(): array
    {
        $school = $this->school('A');
        $payer = $this->user($school, 'student');
        $invoice = FeeInvoice::query()->create([
            'name' => 'Term invoice',
            'user_id' => $payer->id,
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
            'amount' => 100,
            'waiver' => 0,
            'fine' => 0,
        ]);

        return [$school, $payer, $record];
    }

    private function school(string $suffix): School
    {
        $this->sequence++;

        return School::query()->create([
            'name' => "Flutterwave School {$suffix} {$this->sequence}",
            'code' => "FLW-{$suffix}-{$this->sequence}",
        ]);
    }

    private function user(School $school, string $label): User
    {
        $this->sequence++;

        return User::query()->forceCreate([
            'name' => ucfirst($label),
            'email' => "{$label}-{$this->sequence}@example.test",
            'email_is_placeholder' => false,
            'email_verified_at' => now(),
            'password' => bcrypt('Password1!'),
            'school_id' => $school->id,
            'locked' => false,
            'requires_password_change' => false,
        ]);
    }
}
