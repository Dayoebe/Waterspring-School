<?php

namespace Tests\Feature\Fees;

use App\Livewire\Dashboard\DashboardStats;
use App\Models\ClassGroup;
use App\Models\Fee;
use App\Models\FeeCategory;
use App\Models\FeeInvoice;
use App\Models\FeeInvoiceRecord;
use App\Models\MyClass;
use App\Models\School;
use App\Models\StudentRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FeeDashboardVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    public function test_parent_dashboard_shows_only_linked_child_outstanding_balances(): void
    {
        $school = $this->school('PARENT');
        $parent = $this->user($school, 'parent');
        $parent->assignRole(Role::findOrCreate('parent', 'web'));
        $linkedChild = $this->user($school, 'linked-child');
        $linkedChild->assignRole(Role::findOrCreate('student', 'web'));
        StudentRecord::query()->create(['user_id' => $linkedChild->id, 'admission_number' => 'LINKED-001']);
        DB::table('parent_records')->insert(['user_id' => $parent->id, 'student_id' => $linkedChild->id]);

        $this->invoiceRecord($school, $linkedChild, 100, 30, 10, 5);
        $unlinkedChild = $this->user($school, 'unlinked-child');
        $this->invoiceRecord($school, $unlinkedChild, 500, 0, 0, 0);

        Livewire::actingAs($parent)
            ->test(DashboardStats::class)
            ->assertSet('parentPanel.outstanding_minor', 6500)
            ->assertSet('parentPanel.outstanding_invoice_count', 1)
            ->assertSet('parentPanel.children.0.name', $linkedChild->name)
            ->assertSet('parentPanel.children.0.outstanding_minor', 6500)
            ->assertSee('Outstanding school fees')
            ->assertDontSee($unlinkedChild->name);
    }

    public function test_class_teacher_sees_unpaid_status_only_for_students_in_managed_classes(): void
    {
        $school = $this->school('TEACHER');
        $classGroup = ClassGroup::query()->create(['school_id' => $school->id, 'name' => 'Junior School']);
        $managedClass = MyClass::query()->create(['class_group_id' => $classGroup->id, 'name' => 'JSS 1']);
        $otherClass = MyClass::query()->create(['class_group_id' => $classGroup->id, 'name' => 'JSS 2']);
        $teacher = $this->user($school, 'class-teacher');
        $teacher->assignRole(Role::findOrCreate('teacher', 'web'));
        $managedClass->teachers()->attach($teacher->id);

        $unpaidStudent = $this->user($school, 'unpaid-student');
        $paidStudent = $this->user($school, 'paid-student');
        $outsideStudent = $this->user($school, 'outside-student');
        StudentRecord::query()->create(['user_id' => $unpaidStudent->id, 'my_class_id' => $managedClass->id, 'admission_number' => 'UNPAID-1']);
        StudentRecord::query()->create(['user_id' => $paidStudent->id, 'my_class_id' => $managedClass->id, 'admission_number' => 'PAID-1']);
        StudentRecord::query()->create(['user_id' => $outsideStudent->id, 'my_class_id' => $otherClass->id, 'admission_number' => 'OUTSIDE-1']);
        $this->invoiceRecord($school, $unpaidStudent, 100, 20, 0, 0);
        $this->invoiceRecord($school, $paidStudent, 100, 100, 0, 0);
        $this->invoiceRecord($school, $outsideStudent, 100, 0, 0, 0);

        $component = Livewire::actingAs($teacher)
            ->test(DashboardStats::class)
            ->assertSet('teacherPanel.fee_arrears.unpaid_count', 1)
            ->assertSet('teacherPanel.fee_arrears.students.0.name', $unpaidStudent->name)
            ->assertSet('teacherPanel.fee_arrears.students.0.invoice_count', 1)
            ->assertSee('Students with outstanding school fees')
            ->assertDontSee($outsideStudent->name);

        $this->assertArrayNotHasKey('outstanding_minor', $component->get('teacherPanel')['fee_arrears']['students'][0]);
    }

    private function invoiceRecord(
        School $school,
        User $student,
        int|float $amount,
        int|float $paid,
        int|float $waiver,
        int|float $fine
    ): FeeInvoiceRecord {
        $invoice = FeeInvoice::query()->create([
            'name' => 'Invoice '.$student->id,
            'user_id' => $student->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addMonth()->toDateString(),
        ]);
        $category = FeeCategory::query()->create([
            'school_id' => $school->id,
            'name' => 'Tuition '.$this->sequence++,
        ]);
        $fee = Fee::query()->create([
            'fee_category_id' => $category->id,
            'name' => 'School fees '.$this->sequence++,
        ]);

        return FeeInvoiceRecord::query()->forceCreate([
            'fee_invoice_id' => $invoice->id,
            'fee_id' => $fee->id,
            'amount' => $amount,
            'paid' => $paid,
            'waiver' => $waiver,
            'fine' => $fine,
        ]);
    }

    private function school(string $suffix): School
    {
        $this->sequence++;

        return School::query()->create([
            'name' => "Dashboard Fee School {$suffix}",
            'code' => "DASH-FEE-{$suffix}-{$this->sequence}",
        ]);
    }

    private function user(School $school, string $label): User
    {
        $this->sequence++;

        return User::query()->forceCreate([
            'name' => ucfirst(str_replace('-', ' ', $label)),
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
