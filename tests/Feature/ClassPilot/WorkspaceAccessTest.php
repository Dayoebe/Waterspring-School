<?php

namespace Tests\Feature\ClassPilot;

use App\Models\ClassGroup;
use App\Models\MyClass;
use App\Models\School;
use App\Models\StudentRecord;
use App\Models\User;
use App\Services\ClassPilot\Access;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkspaceAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_student_and_parent_can_open_their_role_appropriate_workspace(): void
    {
        $school = $this->school('A');
        foreach (['teacher', 'student', 'parent'] as $roleName) {
            $user = $this->user($school, $roleName.'@example.test');
            $this->grant($user, $roleName);
            if ($roleName === 'student') {
                $this->student($user, $school);
            }
            $this->actingAs($user)->get(route('class-pilot.workspace'))->assertOk()->assertSee('ClassPilot AI');
        }
    }

    public function test_user_without_classpilot_permission_is_rejected(): void
    {
        $user = $this->user($this->school('A'), 'blocked@example.test');
        $this->actingAs($user)->get(route('class-pilot.workspace'))->assertForbidden();
    }

    public function test_parent_cannot_resolve_an_unlinked_or_cross_school_child(): void
    {
        $schoolA = $this->school('A');
        $schoolB = $this->school('B');
        $parent = $this->user($schoolA, 'parent@example.test');
        $this->grant($parent, 'parent');
        $ownChild = $this->user($schoolA, 'own@example.test');
        $this->grant($ownChild, 'student');
        $ownRecord = $this->student($ownChild, $schoolA);
        $otherChild = $this->user($schoolB, 'other@example.test');
        $this->grant($otherChild, 'student');
        $otherRecord = $this->student($otherChild, $schoolB);
        DB::table('parent_records')->insert(['user_id' => $parent->id, 'student_id' => $ownChild->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($parent);
        $this->assertSame($ownRecord->id, app(Access::class)->childRecord($parent, $ownRecord->id)->id);
        $this->expectException(ModelNotFoundException::class);
        app(Access::class)->childRecord($parent, $otherRecord->id);
    }

    private function school(string $suffix): School
    {
        return School::query()->create(['name' => 'School '.$suffix, 'code' => 'SCH'.$suffix]);
    }

    private function user(School $school, string $email): User
    {
        return User::query()->forceCreate(['name' => strtok($email, '@'), 'email' => $email, 'email_verified_at' => now(), 'password' => bcrypt('Password1!'), 'school_id' => $school->id, 'locked' => false]);
    }

    private function grant(User $user, string $roleName): void
    {
        $role = Role::findOrCreate($roleName, 'web');
        $permission = Permission::findOrCreate('access classpilot', 'web');
        $role->givePermissionTo($permission);
        $user->assignRole($role);
    }

    private function student(User $user, School $school): StudentRecord
    {
        $group = ClassGroup::query()->firstOrCreate(['name' => 'Junior '.$school->id, 'school_id' => $school->id]);
        $class = MyClass::query()->firstOrCreate(['name' => 'JSS 1 '.$school->id, 'class_group_id' => $group->id]);

        return StudentRecord::query()->create(['user_id' => $user->id, 'my_class_id' => $class->id, 'admission_number' => 'ADM'.$user->id]);
    }
}
