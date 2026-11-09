<?php

namespace Tests\Feature\Appointments;

use App\Mail\ParentTeacherAppointmentMail;
use App\Models\ClassGroup;
use App\Models\MyClass;
use App\Models\School;
use App\Models\StudentRecord;
use App\Models\User;
use App\Services\ParentTeacherAppointmentService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use LogicException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ParentTeacherAppointmentTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    public function test_linked_parent_can_request_an_assigned_teacher_slot_and_teacher_can_manage_the_meeting(): void
    {
        [$school, $class, $teacher, $parent, $student] = $this->appointmentContext('FLOW');
        $service = app(ParentTeacherAppointmentService::class);
        $slot = $service->createSlot($teacher, $teacher, [
            'starts_at' => now()->addHours(23),
            'ends_at' => now()->addHours(24),
            'mode' => 'in_person',
            'location' => 'Consultation Room',
        ]);

        $appointment = $service->request($parent, $student->id, $slot, 'Discuss progress in class.');
        $this->assertSame('requested', $appointment->status);
        $this->assertSame($teacher->id, $appointment->teacher_id);
        $this->assertSame($class->id, $student->studentRecord->my_class_id);

        $service->respond($appointment, $teacher, 'confirmed');
        $note = $service->addNote($appointment->fresh(), $teacher, 'follow_up', 'Share a two-week reading practice plan.');
        $this->assertSame('confirmed', $appointment->fresh()->status);
        $this->assertDatabaseHas('parent_teacher_appointment_notes', [
            'id' => $note->id,
            'type' => 'follow_up',
            'author_id' => $teacher->id,
        ]);

        try {
            $service->complete($appointment->fresh(), $teacher);
            $this->fail('Expected early completion to be rejected.');
        } catch (DomainException $exception) {
            $this->assertSame('The appointment cannot be completed before its scheduled time.', $exception->getMessage());
        }

        $this->travelTo($slot->starts_at->copy()->addMinute());
        $service->complete($appointment->fresh(), $teacher);
        $this->assertSame('completed', $appointment->fresh()->status);

        try {
            $note->update(['note' => 'Rewritten']);
            $this->fail('Expected append-only notes to reject mutation.');
        } catch (LogicException $exception) {
            $this->assertSame('Appointment notes are append-only.', $exception->getMessage());
        }
    }

    public function test_unassigned_and_already_requested_slots_cannot_be_booked(): void
    {
        [$school, , $teacher, $parent, $student] = $this->appointmentContext('BOOKING');
        $service = app(ParentTeacherAppointmentService::class);
        $unassigned = $this->user($school, 'unassigned-teacher');
        $slot = $service->createSlot($unassigned, $unassigned, [
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHour(),
            'mode' => 'video',
        ]);

        try {
            $service->request($parent, $student->id, $slot, 'Request with unassigned teacher.');
            $this->fail('Expected an unassigned teacher request to fail.');
        } catch (DomainException $exception) {
            $this->assertSame('This teacher is not assigned to the selected child.', $exception->getMessage());
        }

        $assignedSlot = $service->createSlot($teacher, $teacher, [
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addHour(),
            'mode' => 'phone',
        ]);
        $service->request($parent, $student->id, $assignedSlot, 'First valid request.');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('This appointment slot has already been requested.');
        $service->request($parent, $student->id, $assignedSlot, 'Duplicate request.');
    }

    public function test_confirmed_appointment_reminders_are_queued_once_per_participant(): void
    {
        [, , $teacher, $parent, $student] = $this->appointmentContext('REMINDER');
        $service = app(ParentTeacherAppointmentService::class);
        $slot = $service->createSlot($teacher, $teacher, [
            'starts_at' => now()->addHours(12),
            'ends_at' => now()->addHours(13),
            'mode' => 'video',
            'location' => 'https://meet.example.test/family',
        ]);
        $appointment = $service->request($parent, $student->id, $slot, 'Review current learning targets.');
        $service->respond($appointment, $teacher, 'confirmed');
        Mail::fake();

        $this->artisan('appointments:send-reminders --hours=24')->assertSuccessful();
        $this->artisan('appointments:send-reminders --hours=24')->assertSuccessful();

        Mail::assertQueued(ParentTeacherAppointmentMail::class, 2);
        $this->assertDatabaseCount('appointment_notification_deliveries', 2);
        $this->assertDatabaseHas('appointment_notification_deliveries', [
            'parent_teacher_appointment_id' => $appointment->id,
            'recipient_id' => $parent->id,
            'event' => 'reminder',
        ]);
    }

    public function test_parent_and_teacher_can_open_the_tenant_scoped_workspace(): void
    {
        [, , $teacher, $parent] = $this->appointmentContext('ROUTE');

        $this->actingAs($parent)->get(route('appointments.index'))->assertOk()->assertSee('Parent-teacher appointments');
        $this->actingAs($teacher)->get(route('appointments.index'))->assertOk()->assertSee('Add availability');
    }

    private function appointmentContext(string $suffix): array
    {
        $this->sequence++;
        $school = School::query()->create(['name' => "Appointment School {$suffix}", 'code' => "APT-{$suffix}-{$this->sequence}"]);
        $group = ClassGroup::query()->create(['name' => "Appointment Group {$suffix}", 'school_id' => $school->id]);
        $class = MyClass::query()->create(['name' => "Appointment Class {$suffix}", 'class_group_id' => $group->id]);
        $teacher = $this->user($school, 'teacher-'.$suffix);
        $parent = $this->user($school, 'parent-'.$suffix);
        $student = $this->user($school, 'student-'.$suffix);
        $teacherRole = Role::findOrCreate('teacher', 'web');
        $teacherRole->givePermissionTo(Permission::findOrCreate('manage parent teacher appointments', 'web'));
        $teacher->assignRole($teacherRole);
        $parentRole = Role::findOrCreate('parent', 'web');
        $parentRole->givePermissionTo(Permission::findOrCreate('book parent teacher appointment', 'web'));
        $parent->assignRole($parentRole);
        $student->assignRole(Role::findOrCreate('student', 'web'));
        StudentRecord::query()->create([
            'user_id' => $student->id,
            'my_class_id' => $class->id,
            'admission_number' => 'APT-'.$student->id,
        ]);
        DB::table('parent_records')->insert([
            'user_id' => $parent->id,
            'student_id' => $student->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('class_teacher')->insert([
            'class_id' => $class->id,
            'teacher_id' => $teacher->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$school, $class, $teacher, $parent, $student];
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
