<?php

namespace Tests\Feature\Operations;

use App\Http\Middleware\VerifyCsrfToken;
use App\Jobs\SendBroadcastMessageEmail;
use App\Mail\AdmissionNotificationMail;
use App\Mail\ApplicationStatusChanged;
use App\Mail\AssignmentGradedMail;
use App\Mail\AssignmentPublishedMail;
use App\Mail\AssignmentReminderMail;
use App\Mail\AssignmentSubmissionReceivedMail;
use App\Mail\BroadcastMessageMail;
use App\Mail\ContactMessageReply;
use App\Mail\NewContactMessageAlert;
use App\Mail\NoticePublishedMail;
use App\Mail\StudentAttendanceAlertMail;
use App\Mail\StudentSupportCaseMail;
use App\Models\BroadcastMessage;
use App\Models\BroadcastMessageRecipient;
use App\Models\School;
use App\Models\User;
use App\Services\OperationalHealthService;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\TestCase;

class OperationalReliabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_reports_scheduler_database_and_queue_state_without_details(): void
    {
        Cache::put(OperationalHealthService::SCHEDULER_HEARTBEAT_KEY, now()->timestamp, 300);

        $this->get('/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database', 'ok')
            ->assertJsonPath('checks.scheduler', 'ok')
            ->assertJsonPath('checks.queue', 'ok')
            ->assertJsonStructure(['checked_at']);

        Cache::forget(OperationalHealthService::SCHEDULER_HEARTBEAT_KEY);
        $this->get('/health')->assertStatus(503)->assertJsonPath('checks.scheduler', 'failed');
    }

    public function test_security_headers_and_https_enforcement_are_applied_globally(): void
    {
        Cache::put(OperationalHealthService::SCHEDULER_HEARTBEAT_KEY, now()->timestamp, 300);

        $response = $this->get('/health')->assertOk();
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString("frame-ancestors 'none'", $response->headers->get('Content-Security-Policy'));

        config()->set('operations.force_https', true);
        $this->get('/health')->assertStatus(308)->assertRedirect('https://localhost/health');
        $this->post('/logout')->assertStatus(400);
    }

    public function test_queue_storage_exists_and_all_application_mail_is_queued_and_encrypted(): void
    {
        $this->assertTrue(Schema::hasTable('jobs'));
        $this->assertTrue(Schema::hasTable('failed_jobs'));

        foreach ($this->mailClasses() as $mailClass) {
            $this->assertTrue(is_subclass_of($mailClass, ShouldQueue::class), "{$mailClass} must be queued.");
            $this->assertTrue(is_subclass_of($mailClass, ShouldBeEncrypted::class), "{$mailClass} must be encrypted.");
        }

        Queue::fake();
        Mail::to('operator@example.test')->send(new ApplicationStatusChanged('approved'));
        Queue::assertPushed(
            SendQueuedMailable::class,
            fn (SendQueuedMailable $job): bool => $job->shouldBeEncrypted && $job->tries === 3
        );
    }

    public function test_csrf_middleware_has_no_route_exclusions(): void
    {
        $middleware = new ReflectionClass(VerifyCsrfToken::class);
        $property = $middleware->getProperty('except');

        $this->assertSame([], $property->getValue($middleware->newInstanceWithoutConstructor()));
    }

    public function test_broadcast_delivery_is_recorded_only_after_the_transport_succeeds(): void
    {
        Mail::fake();
        $school = School::query()->create(['name' => 'Queue School', 'code' => 'QUEUE-SCHOOL']);
        $recipient = User::query()->forceCreate([
            'name' => 'Queued Recipient',
            'email' => 'queued-recipient@example.test',
            'password' => bcrypt('Password1!'),
            'school_id' => $school->id,
            'locked' => false,
            'requires_password_change' => false,
        ]);
        $broadcast = BroadcastMessage::query()->create([
            'school_id' => $school->id,
            'title' => 'Queued announcement',
            'body' => 'This message should be tracked after transport success.',
            'target_type' => 'school',
            'send_portal' => false,
            'send_email' => true,
            'send_sms' => false,
            'sent_at' => now(),
        ]);
        $delivery = BroadcastMessageRecipient::query()->create([
            'broadcast_message_id' => $broadcast->id,
            'school_id' => $school->id,
            'user_id' => $recipient->id,
            'email' => $recipient->email,
            'channels' => ['email'],
        ]);

        $this->assertNull($delivery->email_delivered_at);
        (new SendBroadcastMessageEmail($broadcast->id, $recipient->id))->handle();

        Mail::assertSent(BroadcastMessageMail::class, fn ($mail): bool => $mail->recipient->is($recipient));
        $this->assertNotNull($delivery->fresh()->email_delivered_at);
        $this->assertNotNull($broadcast->fresh()->email_sent_at);
    }

    private function mailClasses(): array
    {
        return [
            AdmissionNotificationMail::class,
            ApplicationStatusChanged::class,
            AssignmentGradedMail::class,
            AssignmentPublishedMail::class,
            AssignmentReminderMail::class,
            AssignmentSubmissionReceivedMail::class,
            BroadcastMessageMail::class,
            ContactMessageReply::class,
            NewContactMessageAlert::class,
            NoticePublishedMail::class,
            StudentAttendanceAlertMail::class,
            StudentSupportCaseMail::class,
        ];
    }
}
