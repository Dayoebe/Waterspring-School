<?php

namespace Tests\Feature\Auth;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthenticationCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_and_session_authentication_work_after_the_framework_upgrade(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Email or Username')
            ->assertSee('name="login"', false);

        $school = School::query()->create(['name' => 'Compatibility School', 'code' => 'COMP']);
        $user = User::query()->forceCreate([
            'name' => 'Compatibility User',
            'email' => 'compatibility@example.test',
            'email_verified_at' => now(),
            'password' => Hash::make('Password1!'),
            'school_id' => $school->id,
            'locked' => false,
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'Password1!',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_can_log_in_with_a_case_insensitive_username(): void
    {
        $school = School::query()->create(['name' => 'Username School', 'code' => 'USERNAME']);
        $user = User::query()->forceCreate([
            'name' => 'Username User',
            'username' => 'Student.One',
            'email' => 'student.one@example.test',
            'email_verified_at' => now(),
            'password' => 'Password1!',
            'school_id' => $school->id,
            'locked' => false,
        ]);

        $this->assertSame('student.one', $user->username);

        $this->post('/login', [
            'login' => '  STUDENT.ONE  ',
            'password' => 'Password1!',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_username_credentials_use_the_generic_authentication_error(): void
    {
        $this->post('/login', [
            'login' => 'unknown.user',
            'password' => 'IncorrectPassword!',
        ])->assertSessionHasErrors('login', __('auth.failed'));

        $this->assertGuest();
    }

    public function test_public_registration_requires_and_stores_a_unique_username(): void
    {
        Notification::fake();
        $school = School::query()->create(['name' => 'Registration School', 'code' => 'REGISTER']);
        Role::findOrCreate('student', 'web');

        $this->post('/register', [
            'name' => 'New Student',
            'username' => '  New.Student  ',
            'email' => 'new.student@example.test',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'school' => $school->id,
            'role' => 'student',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseHas('users', [
            'email' => 'new.student@example.test',
            'username' => 'new.student',
        ]);

        $applicant = User::query()->where('email', 'new.student@example.test')->firstOrFail();
        $this->assertTrue($applicant->hasRole('applicant'));
        Notification::assertNothingSent();

        $this->post('/login', [
            'login' => $applicant->username,
            'password' => 'Password1!',
        ])->assertRedirect(route('dashboard'));
        $this->get('/dashboard')
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');
        $this->assertGuest();
    }

    public function test_profile_can_set_a_unique_normalized_username(): void
    {
        $school = School::query()->create(['name' => 'Profile School', 'code' => 'PROFILE']);
        $user = User::query()->forceCreate([
            'name' => 'Profile User',
            'email' => 'profile@example.test',
            'email_verified_at' => now(),
            'password' => 'Password1!',
            'school_id' => $school->id,
            'locked' => false,
        ]);
        $user->givePermissionTo(Permission::findOrCreate('manage own profile', 'web'));

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'username' => '  Profile.User  ',
            'email' => $user->email,
        ])->assertRedirect(route('profile.edit'));

        $this->assertSame('profile.user', $user->fresh()->username);
    }

    public function test_profile_rejects_a_username_already_used_by_another_account(): void
    {
        $school = School::query()->create(['name' => 'Duplicate School', 'code' => 'DUPLICATE']);
        User::query()->create([
            'name' => 'First User',
            'username' => 'shared.name',
            'email' => 'first@example.test',
            'password' => 'Password1!',
            'school_id' => $school->id,
        ]);
        $second = User::query()->forceCreate([
            'name' => 'Second User',
            'email' => 'second@example.test',
            'email_verified_at' => now(),
            'password' => 'Password1!',
            'school_id' => $school->id,
            'locked' => false,
        ]);
        $second->givePermissionTo(Permission::findOrCreate('manage own profile', 'web'));

        $this->actingAs($second)->patch(route('profile.update'), [
            'name' => $second->name,
            'username' => 'SHARED.NAME',
            'email' => $second->email,
        ])->assertSessionHasErrors('username');

        $this->assertNull($second->fresh()->username);
    }
}
