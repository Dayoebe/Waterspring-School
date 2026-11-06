<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\EnsureDefaultPasswordIsChanged;
use App\Http\Middleware\PreventGraduatedStudent;
use App\Http\Middleware\PreventLockAccountAccess;
use App\Models\ClassGroup;
use App\Models\MyClass;
use App\Models\School;
use App\Models\StudentRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccountSecurityMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private int $userSequence = 0;

    public function test_every_portal_route_uses_the_account_security_stack(): void
    {
        $exemptRouteNames = [
            'logout',
            'verification.notice',
            'verification.verify',
            'verification.send',
        ];
        $requiredMiddleware = [
            PreventLockAccountAccess::class,
            EnsureDefaultPasswordIsChanged::class,
            PreventGraduatedStudent::class,
            'verified',
        ];

        foreach (app('router')->getRoutes() as $route) {
            $middleware = $route->gatherMiddleware();
            $isAuthenticated = in_array('auth', $middleware, true)
                || in_array('auth:sanctum', $middleware, true);

            if (! $isAuthenticated || in_array($route->getName(), $exemptRouteNames, true)) {
                continue;
            }

            foreach ($requiredMiddleware as $required) {
                $this->assertContains(
                    $required,
                    $middleware,
                    "Route [{$route->uri()}] is missing [{$required}]."
                );
            }
        }
    }

    public function test_locked_accounts_are_logged_out_and_denied(): void
    {
        $user = $this->portalUser(verified: true, locked: true);

        $this->actingAs($user)->get('/dashboard')->assertForbidden();
        $this->assertGuest();
    }

    public function test_locked_accounts_are_denied_on_token_authenticated_api_routes(): void
    {
        $user = $this->portalUser(verified: true, locked: true);
        Sanctum::actingAs($user);

        $this->getJson('/api/user')
            ->assertForbidden()
            ->assertJsonPath('message', "This account is locked. If this is an error, contact your school's administrator.");
    }

    public function test_unverified_accounts_can_complete_the_verification_flow(): void
    {
        Notification::fake();
        $user = $this->portalUser(verified: false);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('verification.notice'));
        $this->get(route('verification.notice'))->assertOk()->assertSee('Verify your email address');
        $this->post(route('verification.send'))->assertSessionHas('status', 'verification-link-sent');

        $verificationUrl = URL::temporarySignedRoute('verification.verify', now()->addMinutes(10), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->get($verificationUrl)->assertRedirect(route('dashboard'));
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_forced_password_change_allows_only_the_password_flow_until_completed(): void
    {
        $user = $this->portalUser(verified: true, requiresPasswordChange: true);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('password.change'));
        $this->get(route('password.change'))->assertOk();

        $this->post(route('password.change.update'), [
            'current_password' => 'Password1!',
            'password' => 'UpdatedPassword2!',
            'password_confirmation' => 'UpdatedPassword2!',
        ])->assertRedirect(route('profile.edit'));

        $this->assertFalse($user->fresh()->requires_password_change);
        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_graduated_students_are_denied_without_a_redirect_loop(): void
    {
        $user = $this->portalUser(verified: true, role: 'student');
        $group = ClassGroup::query()->create(['name' => 'Graduated Group', 'school_id' => $user->school_id]);
        $class = MyClass::query()->create(['name' => 'Year 9', 'class_group_id' => $group->id]);
        StudentRecord::query()->create([
            'user_id' => $user->id,
            'my_class_id' => $class->id,
            'admission_number' => 'GRAD-'.$user->id,
            'is_graduated' => true,
        ]);

        $this->actingAs($user)->get('/dashboard')->assertForbidden();
    }

    private function portalUser(
        bool $verified,
        bool $locked = false,
        bool $requiresPasswordChange = false,
        ?string $role = null
    ): User {
        $this->userSequence++;
        $school = School::query()->firstOrCreate(
            ['code' => 'SEC'],
            ['name' => 'Security Test School']
        );
        $user = User::query()->forceCreate([
            'name' => 'Security User '.$this->userSequence,
            'email' => "security{$this->userSequence}@example.test",
            'email_verified_at' => $verified ? now() : null,
            'password' => Hash::make('Password1!'),
            'school_id' => $school->id,
            'locked' => $locked,
            'requires_password_change' => $requiresPasswordChange,
        ]);

        foreach (['view dashboard', 'change own password', 'manage own profile'] as $permissionName) {
            $user->givePermissionTo(Permission::findOrCreate($permissionName, 'web'));
        }

        if ($role !== null) {
            $user->assignRole(Role::findOrCreate($role, 'web'));
        }

        return $user;
    }
}
