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
use App\Services\PaystackPaymentService;
use App\Services\PaystackWebhookVerifier;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PaystackFeePaymentTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'services.paystack.enabled' => true,
            'services.paystack.secret_key' => 'sk_test_server_only',
            'services.paystack.webhook_secret' => 'whsec_test',
            'services.paystack.base_url' => 'https://api.paystack.co',
            'services.paystack.timeout' => 5,
            'services.paystack.webhook_tolerance' => 300,
        ]);
    }

    public function test_initialization_uses_the_server_calculated_minor_amount_and_invoice_identity(): void
    {
        [$school, $payer, $record] = $this->paymentContext();

        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/secure-code',
                    'access_code' => 'secure-code',
                    'reference' => 'ignored-provider-copy',
                ],
            ]),
        ]);

        $attempt = app(PaystackPaymentService::class)->initialize($record, $payer, '25.50');

        $this->assertSame($school->id, $attempt->school_id);
        $this->assertSame($record->id, $attempt->fee_invoice_record_id);
        $this->assertSame(2550, (int) $attempt->getRawOriginal('amount'));
        $this->assertSame('NGN', $attempt->currency);
        $this->assertSame('initialized', $attempt->status);

        Http::assertSent(function ($request) use ($attempt, $record): bool {
            return $request->url() === 'https://api.paystack.co/transaction/initialize'
                && $request['amount'] === '2550'
                && $request['reference'] === $attempt->reference
                && $request['metadata']['fee_invoice_record_id'] === $record->id
                && $request->hasHeader('Authorization', 'Bearer sk_test_server_only');
        });
    }

    public function test_verified_payment_posts_once_to_the_ledger_and_queues_one_receipt(): void
    {
        Mail::fake();
        [, $payer, $record] = $this->paymentContext();
        $attempt = $this->initializedAttempt($record, $payer, '40.00');

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response($this->verificationResponse($attempt, 4000)),
        ]);

        $service = app(PaystackPaymentService::class);
        $first = $service->verifyAndFulfill($attempt->reference);
        $second = $service->verifyAndFulfill($attempt->reference);

        $this->assertSame('paid', $first->status);
        $this->assertSame($first->fee_payment_transaction_id, $second->fee_payment_transaction_id);
        $this->assertDatabaseCount('fee_payment_transactions', 1);
        $this->assertDatabaseCount('fee_payment_notification_deliveries', 1);
        $this->assertSame(4000, (int) $record->fresh()->getRawOriginal('paid'));
        Mail::assertQueued(FeePaymentReceiptMail::class, 1);
    }

    public function test_amount_mismatch_is_quarantined_without_changing_the_fee_balance(): void
    {
        [, $payer, $record] = $this->paymentContext();
        $attempt = $this->initializedAttempt($record, $payer, '40.00');

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response($this->verificationResponse($attempt, 3999)),
        ]);

        try {
            app(PaystackPaymentService::class)->verifyAndFulfill($attempt->reference);
            $this->fail('Expected the mismatched amount to be rejected.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('does not match', $exception->getMessage());
        }

        $this->assertSame('review_required', $attempt->fresh()->status);
        $this->assertDatabaseCount('fee_payment_transactions', 0);
        $this->assertSame(0, (int) $record->fresh()->getRawOriginal('paid'));
    }

    public function test_signed_webhook_fulfils_payment_and_replay_is_idempotent(): void
    {
        Mail::fake();
        [, $payer, $record] = $this->paymentContext();
        $attempt = $this->initializedAttempt($record, $payer, '18.00');

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response($this->verificationResponse($attempt, 1800)),
        ]);

        $body = json_encode([
            'event' => 'charge.success',
            'data' => ['reference' => $attempt->reference],
        ], JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha512', $body, 'sk_test_server_only');

        foreach ([1, 2] as $delivery) {
            $this->call(
                'POST',
                route('api.fee-payments.paystack.webhook'),
                [],
                [],
                [],
                ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => $signature],
                $body
            )->assertOk()->assertJson(['matched' => true]);
        }

        $this->assertDatabaseCount('fee_payment_transactions', 1);
        $this->assertSame(1800, (int) $record->fresh()->getRawOriginal('paid'));
        Mail::assertQueued(FeePaymentReceiptMail::class, 1);
    }

    public function test_invalid_webhook_signature_is_rejected_before_verification(): void
    {
        Http::fake();

        $this->withHeader('x-paystack-signature', 'invalid')
            ->postJson(route('api.fee-payments.paystack.webhook'), [
                'event' => 'charge.success',
                'data' => ['reference' => 'WSP-PS-UNKNOWN'],
            ])
            ->assertUnauthorized();

        Http::assertNothingSent();
    }

    public function test_timestamped_webhook_signature_is_accepted_only_inside_the_replay_window(): void
    {
        $body = json_encode(['event' => 'charge.success'], JSON_THROW_ON_ERROR);
        $timestamp = now()->timestamp;
        $signature = hash_hmac('sha512', $timestamp.'.'.$body, 'whsec_test');
        $request = Request::create(
            '/api/payments/paystack/webhook',
            'POST',
            [],
            [],
            [],
            ['HTTP_PAYSTACK_SIGNATURE' => "t={$timestamp},v1=whk_test:{$signature}"],
            $body
        );

        $this->assertTrue(app(PaystackWebhookVerifier::class)->isValid($request));

        $staleTimestamp = now()->subMinutes(10)->timestamp;
        $staleSignature = hash_hmac('sha512', $staleTimestamp.'.'.$body, 'whsec_test');
        $request->headers->set('Paystack-Signature', "t={$staleTimestamp},v1=whk_test:{$staleSignature}");

        $this->assertFalse(app(PaystackWebhookVerifier::class)->isValid($request));
    }

    public function test_other_school_cannot_initialize_payment_for_the_invoice(): void
    {
        [, , $record] = $this->paymentContext();
        $otherSchool = $this->school('B');
        $otherUser = $this->user($otherSchool, 'outsider');
        $otherUser->givePermissionTo(Permission::findOrCreate('read fee invoice', 'web'));

        $this->actingAs($otherUser)
            ->post(route('fee-payments.paystack.initialize', [$record->feeInvoice, $record]), [
                'amount' => 10,
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('fee_payment_attempts', 0);
    }

    private function initializedAttempt(FeeInvoiceRecord $record, User $payer, string $amount): FeePaymentAttempt
    {
        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/secure-code',
                    'access_code' => 'secure-code',
                ],
            ]),
        ]);

        return app(PaystackPaymentService::class)->initialize($record, $payer, $amount);
    }

    private function verificationResponse(FeePaymentAttempt $attempt, int $amount): array
    {
        return [
            'status' => true,
            'data' => [
                'id' => 987654,
                'status' => 'success',
                'reference' => $attempt->reference,
                'amount' => $amount,
                'currency' => 'NGN',
                'gateway_response' => 'Successful',
                'paid_at' => now()->toIso8601String(),
                'channel' => 'card',
                'fees' => 100,
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
            'name' => "Paystack School {$suffix} {$this->sequence}",
            'code' => "PAY-{$suffix}-{$this->sequence}",
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
