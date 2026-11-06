<?php

namespace Tests\Feature\Auth;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_and_session_authentication_work_after_the_framework_upgrade(): void
    {
        $this->get('/login')->assertOk();

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
}
