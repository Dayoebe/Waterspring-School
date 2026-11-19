<?php

namespace Tests\Feature;

use App\Jobs\SendBirthdayWishEmail;
use App\Livewire\Birthdays\BirthdayWidget;
use App\Livewire\Birthdays\ManageBirthdaySettings;
use App\Mail\BirthdayWishMail;
use App\Models\BirthdaySetting;
use App\Models\School;
use App\Models\User;
use App\Services\BirthdayWishService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BirthdayCelebrationTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private Role $staffRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->school = School::query()->create(['name' => 'Water Spring Test School', 'code' => 'WSTS']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['view birthdays', 'send birthday wishes', 'manage birthday settings', 'view dashboard'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
        $this->staffRole = Role::findOrCreate('teacher', 'web');
        $this->staffRole->givePermissionTo(['view birthdays', 'send birthday wishes', 'view dashboard']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_visible_birthdays_render_without_private_contact_or_birth_year(): void
    {
        $viewer = $this->staff('Viewer', 'viewer@example.test', '1990-04-10');
        $celebrant = $this->staff('Visible Celebrant', 'secret@example.test', now('Africa/Lagos')->subYears(20)->format('Y-m-d'));
        $hidden = $this->staff('Private Celebrant', 'private@example.test', now('Africa/Lagos')->subYears(21)->format('Y-m-d'), true);

        $this->actingAs($viewer)->get(route('birthdays.index'))
            ->assertOk()
            ->assertSee($celebrant->name)
            ->assertDontSee($hidden->name)
            ->assertDontSee('secret@example.test')
            ->assertDontSee((string) $celebrant->birthday->year);
    }

    public function test_dashboard_widget_handles_today_and_empty_states(): void
    {
        $viewer = $this->staff('Viewer', 'viewer2@example.test', null);
        $today = $this->staff('Today Person', 'today@example.test', now('Africa/Lagos')->subYears(20)->format('Y-m-d'));
        $this->actingAs($viewer);

        Livewire::test(BirthdayWidget::class)->assertSee('Today Person')->assertSee('birthday today');
        $today->update(['birthday_is_private' => true]);
        Livewire::test(BirthdayWidget::class)->assertSee('No upcoming birthdays are available');
    }

    public function test_unauthorised_users_cannot_open_the_directory_or_settings(): void
    {
        $user = User::query()->create([
            'school_id' => $this->school->id, 'name' => 'No Permission', 'email' => 'none@example.test',
            'password' => 'Str0ng!Pass', 'requires_password_change' => false,
        ]);

        $this->actingAs($user)->get(route('birthdays.index'))->assertForbidden();
        $this->actingAs($user)->get(route('birthdays.settings'))->assertForbidden();
    }

    public function test_manual_wishes_queue_to_the_server_resolved_recipient_and_are_rate_limited(): void
    {
        Bus::fake();
        $sender = $this->staff('Sender', 'sender@example.test', '1990-04-10');
        $celebrant = $this->staff('Recipient', 'recipient@example.test', '1991-05-11');
        $service = app(BirthdayWishService::class);
        RateLimiter::clear("birthday-wishes:sender:{$sender->id}");

        $delivery = $service->sendManual($sender, $celebrant->id, 'Happy Birthday!', 'Wishing you a joyful and successful new year.');

        $this->assertSame('queued', $delivery->status);
        $this->assertSame($celebrant->id, $delivery->recipient_user_id);
        Bus::assertDispatched(SendBirthdayWishEmail::class, fn ($job) => $job->recipientEmail === 'recipient@example.test');

        for ($attempt = 1; $attempt < (int) config('birthdays.manual_limit_per_hour'); $attempt++) {
            $service->sendManual($sender, $celebrant->id, 'Happy Birthday!', 'Another warm birthday greeting for your special day.');
        }
        $this->expectException(ValidationException::class);
        $service->sendManual($sender, $celebrant->id, 'Happy Birthday!', 'This message exceeds the permitted hourly sending limit.');
    }

    public function test_missing_recipient_email_is_handled_without_queueing(): void
    {
        Bus::fake();
        $sender = $this->staff('Sender Missing', 'sender-missing@example.test', '1990-04-10');
        $celebrant = $this->staff('No Email', null, '1991-05-11');

        try {
            app(BirthdayWishService::class)->sendManual($sender, $celebrant->id, 'Happy Birthday!', 'A valid message that should not be queued.');
            $this->fail('Expected recipient validation to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('message', $exception->errors());
        }
        Bus::assertNothingDispatched();
    }

    public function test_automated_greetings_are_idempotent_and_delivery_updates_status(): void
    {
        Bus::fake();
        $celebrant = $this->staff('Automated Person', 'automated@example.test', now('Africa/Lagos')->subYears(30)->format('Y-m-d'));
        $settings = BirthdaySetting::query()->create([
            'school_id' => $this->school->id,
            'automated_emails_enabled' => true,
            'automated_staff_emails' => true,
            'automated_message' => config('birthdays.automated_message'),
        ]);
        $service = app(BirthdayWishService::class);

        $first = $service->queueAutomated($celebrant, $settings, now('Africa/Lagos')->year);
        $second = $service->queueAutomated($celebrant, $settings, now('Africa/Lagos')->year);
        $this->assertNotNull($first);
        $this->assertNull($second);
        $this->assertDatabaseCount('birthday_email_deliveries', 1);

        Mail::fake();
        $job = new SendBirthdayWishEmail($first->id, 'automated@example.test', 'Happy Birthday!', 'A warm automated birthday greeting.', 'Water Spring');
        $job->handle();
        Mail::assertSent(BirthdayWishMail::class, fn ($mail) => $mail->hasTo('automated@example.test'));
        $this->assertDatabaseHas('birthday_email_deliveries', ['id' => $first->id, 'status' => 'sent']);
    }

    public function test_staff_only_scope_blocks_students_even_with_permission(): void
    {
        $studentRole = Role::findOrCreate('student', 'web');
        $studentRole->givePermissionTo(['view birthdays', 'send birthday wishes']);
        $student = User::query()->create([
            'school_id' => $this->school->id, 'name' => 'Student Viewer', 'email' => 'student@example.test',
            'password' => 'Str0ng!Pass', 'requires_password_change' => false,
        ]);
        $student->assignRole($studentRole);
        BirthdaySetting::query()->create(['school_id' => $this->school->id, 'viewer_scope' => 'staff']);

        $this->actingAs($student)->get(route('birthdays.index'))->assertForbidden();
    }

    public function test_authorised_administrator_can_manage_settings_and_privacy(): void
    {
        $adminRole = Role::findOrCreate('admin', 'web');
        $adminRole->givePermissionTo(['view birthdays', 'send birthday wishes', 'manage birthday settings']);
        $admin = $this->staff('Birthday Admin', 'birthday-admin@example.test', null);
        $admin->syncRoles([$adminRole]);
        $person = $this->staff('Privacy Choice', 'privacy-choice@example.test', '1995-06-15');
        $this->actingAs($admin);

        Livewire::test(ManageBirthdaySettings::class)
            ->set('automatedEmailsEnabled', true)
            ->set('automatedStaffEmails', true)
            ->set('senderName', 'Water Spring Celebrations')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Birthday settings saved')
            ->call('togglePrivacy', $person->id);

        $this->assertDatabaseHas('birthday_settings', [
            'school_id' => $this->school->id,
            'automated_emails_enabled' => true,
            'automated_staff_emails' => true,
        ]);
        $this->assertTrue($person->fresh()->birthday_is_private);
    }

    private function staff(string $name, ?string $email, ?string $birthday, bool $private = false): User
    {
        $user = User::query()->create([
            'school_id' => $this->school->id,
            'name' => $name,
            'email' => $email,
            'email_is_placeholder' => false,
            'password' => 'Str0ng!Pass',
            'requires_password_change' => false,
            'birthday' => $birthday,
            'birthday_is_private' => $private,
            'locked' => false,
        ]);
        $user->assignRole($this->staffRole);

        return $user->fresh(['roles']);
    }
}
