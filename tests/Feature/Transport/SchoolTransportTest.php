<?php

namespace Tests\Feature\Transport;

use App\Mail\TransportArrivalMail;
use App\Models\ClassGroup;
use App\Models\MyClass;
use App\Models\School;
use App\Models\StudentRecord;
use App\Models\User;
use App\Services\SchoolTransportService;
use App\Services\TransportNotificationService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use LogicException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SchoolTransportTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    public function test_route_assignment_guardian_verification_and_arrival_notification_work_end_to_end(): void
    {
        [$school, $manager, $parent, $student] = $this->context('FLOW');
        $service = app(SchoolTransportService::class);
        $route = $service->createRoute($manager, [
            'name' => 'North Estate Route',
            'code' => 'north-01',
            'vehicle_label' => 'WS-101',
            'driver_name' => 'Safe Driver',
            'driver_phone' => '+234800000001',
        ]);
        $stop = $service->addStop($route, $manager, [
            'name' => 'Community Gate',
            'sequence' => 1,
            'morning_time' => '06:30',
            'afternoon_time' => '16:10',
            'landmark' => 'Main security post',
        ]);
        $assignment = $service->assignStudent($student, $route, $stop, $manager, 'both');
        $authorization = $service->authorizeGuardian($student, $manager, [
            'name' => 'Trusted Aunt',
            'relationship' => 'Aunt',
            'phone' => '+234800000002',
        ]);
        $guardian = $authorization['guardian'];

        $this->assertSame('NORTH-01', $route->code);
        $this->assertSame('active', $assignment->status);
        $this->assertNotSame($authorization['pickup_code'], $guardian->getRawOriginal('pickup_code_hash'));
        $this->assertTrue(Hash::check($authorization['pickup_code'], $guardian->getRawOriginal('pickup_code_hash')));

        try {
            $service->verifyPickup($guardian, $manager, '000000');
            $this->fail('Expected an invalid pickup code to fail.');
        } catch (DomainException $exception) {
            $this->assertSame('Pickup verification failed. Confirm the active guardian and code.', $exception->getMessage());
        }

        $pickup = $service->verifyPickup($guardian, $manager, $authorization['pickup_code'], 'Verified at the school gate.');
        $this->assertSame('pickup_verified', $pickup->event_type);
        $this->assertNotNull($guardian->fresh()->last_verified_at);

        Mail::fake();
        $arrival = $service->recordMovement($assignment, $manager, 'arrived', 'Vehicle arrived at school.');
        app(TransportNotificationService::class)->notifyParents($arrival);
        app(TransportNotificationService::class)->notifyParents($arrival);

        Mail::assertQueued(TransportArrivalMail::class, 1);
        $this->assertDatabaseCount('transport_notification_deliveries', 1);
        $this->assertDatabaseHas('transport_notification_deliveries', [
            'transport_event_id' => $arrival->id,
            'recipient_id' => $parent->id,
            'status' => 'queued',
        ]);

        try {
            $arrival->update(['notes' => 'Rewritten']);
            $this->fail('Expected transport history to be immutable.');
        } catch (LogicException $exception) {
            $this->assertSame('Transport events are immutable.', $exception->getMessage());
        }
    }

    public function test_assignment_and_verification_reject_cross_school_or_duplicate_records(): void
    {
        [, $manager, , $student] = $this->context('SCOPE-A');
        [$otherSchool, $otherManager] = $this->context('SCOPE-B');
        $service = app(SchoolTransportService::class);
        $route = $service->createRoute($manager, ['name' => 'Scoped route', 'code' => 'SCOPE-A']);
        $stop = $service->addStop($route, $manager, [
            'name' => 'Scoped stop', 'sequence' => 1, 'morning_time' => null, 'afternoon_time' => null,
        ]);
        $service->assignStudent($student, $route, $stop, $manager, 'morning');

        try {
            $service->assignStudent($student, $route, $stop, $manager, 'afternoon');
            $this->fail('Expected a duplicate active assignment to fail.');
        } catch (DomainException $exception) {
            $this->assertSame('This student already has an active transport assignment.', $exception->getMessage());
        }

        $otherRoute = $service->createRoute($otherManager, ['name' => 'Other route', 'code' => 'OTHER']);
        $otherStop = $service->addStop($otherRoute, $otherManager, [
            'name' => 'Other stop', 'sequence' => 1, 'morning_time' => null, 'afternoon_time' => null,
        ]);
        $this->assertSame($otherSchool->id, $otherRoute->school_id);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Select a student from your school.');
        $service->assignStudent($student, $otherRoute, $otherStop, $otherManager, 'both');
    }

    public function test_pickup_verification_locks_after_repeated_failures(): void
    {
        [, $manager, , $student] = $this->context('LOCKOUT');
        $service = app(SchoolTransportService::class);
        $authorization = $service->authorizeGuardian($student, $manager, [
            'name' => 'Pickup Guardian', 'relationship' => 'Uncle', 'phone' => '+234800000003',
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            try {
                $service->verifyPickup($authorization['guardian']->fresh(), $manager, '000000');
            } catch (DomainException $exception) {
                $this->assertSame('Pickup verification failed. Confirm the active guardian and code.', $exception->getMessage());
            }
        }

        $guardian = $authorization['guardian']->fresh();
        $this->assertSame(5, $guardian->failed_verification_attempts);
        $this->assertTrue($guardian->verification_locked_until->isFuture());

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Pickup verification is temporarily locked after repeated failed attempts.');
        $service->verifyPickup($guardian, $manager, $authorization['pickup_code']);
    }

    public function test_parent_transport_view_excludes_unlinked_students(): void
    {
        [$school, $manager, $parent, $student] = $this->context('PARENT');
        $otherStudent = $this->student($school, 'unlinked-student');
        $service = app(SchoolTransportService::class);
        $route = $service->createRoute($manager, ['name' => 'Family route', 'code' => 'FAMILY']);
        $stop = $service->addStop($route, $manager, [
            'name' => 'Family stop', 'sequence' => 1, 'morning_time' => null, 'afternoon_time' => null,
        ]);
        $ownAssignment = $service->assignStudent($student, $route, $stop, $manager, 'both');
        $otherAssignment = $service->assignStudent($otherStudent, $route, $stop, $manager, 'both');
        $service->recordMovement($ownAssignment, $manager, 'boarded', 'Linked child event.');
        $service->recordMovement($otherAssignment, $manager, 'boarded', 'Unlinked child event.');

        $this->actingAs($parent)->get(route('transport.index'))
            ->assertOk()
            ->assertSee($student->name)
            ->assertDontSee($otherStudent->name);
    }

    private function context(string $suffix): array
    {
        $this->sequence++;
        $school = School::query()->create(['name' => "Transport School {$suffix}", 'code' => "TRN-{$suffix}-{$this->sequence}"]);
        $manager = $this->user($school, 'manager-'.$suffix);
        $principal = Role::findOrCreate('principal', 'web');
        $principal->givePermissionTo(Permission::findOrCreate('manage school transport', 'web'));
        $manager->assignRole($principal);
        $parent = $this->user($school, 'parent-'.$suffix);
        $parentRole = Role::findOrCreate('parent', 'web');
        $parentRole->givePermissionTo(Permission::findOrCreate('view child transport', 'web'));
        $parent->assignRole($parentRole);
        $student = $this->student($school, 'student-'.$suffix);
        DB::table('parent_records')->insert([
            'user_id' => $parent->id,
            'student_id' => $student->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$school, $manager, $parent, $student];
    }

    private function student(School $school, string $label): User
    {
        $student = $this->user($school, $label);
        $student->assignRole(Role::findOrCreate('student', 'web'));
        $group = ClassGroup::query()->firstOrCreate(['name' => 'Transport Group '.$school->id, 'school_id' => $school->id]);
        $class = MyClass::query()->firstOrCreate(['name' => 'Transport Class '.$school->id, 'class_group_id' => $group->id]);
        StudentRecord::query()->create([
            'user_id' => $student->id,
            'my_class_id' => $class->id,
            'admission_number' => 'TRN-'.$student->id,
        ]);

        return $student;
    }

    private function user(School $school, string $label): User
    {
        $this->sequence++;

        return User::query()->forceCreate([
            'name' => str($label)->replace('-', ' ')->title(),
            'email' => strtolower($label)."-{$this->sequence}@example.test",
            'email_verified_at' => now(),
            'password' => bcrypt('Password1!'),
            'school_id' => $school->id,
            'locked' => false,
            'requires_password_change' => false,
        ]);
    }
}
