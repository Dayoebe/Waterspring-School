<?php

namespace Tests\Feature\Fees;

use App\Livewire\Fees\ManageFeeInvoices;
use App\Livewire\Fees\ManageFeePayments;
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
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FeeClassBillingAndCollectionsTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    public function test_class_billing_creates_an_invoice_for_every_active_student_and_can_filter_by_class(): void
    {
        [$school, $manager, $class, $students, $fee] = $this->classBillingContext();
        $otherClass = MyClass::query()->create([
            'class_group_id' => $class->class_group_id,
            'name' => 'JSS 2',
        ]);
        $outsideStudent = $this->student($school, $otherClass, 'outside-student');

        Livewire::actingAs($manager)
            ->test(ManageFeeInvoices::class, ['mode' => 'create'])
            ->set('selectedClass', $class->id)
            ->assertCount('selectedStudents', 2)
            ->set('selectedFeeCategory', $fee->fee_category_id)
            ->set('selectedFee', $fee->id)
            ->call('addFee')
            ->set('issue_date', now()->toDateString())
            ->set('due_date', now()->addMonth()->toDateString())
            ->call('createFeeInvoice')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('fee_invoices', 2);
        $this->assertDatabaseCount('fee_invoice_records', 2);
        foreach ($students as $student) {
            $this->assertDatabaseHas('fee_invoices', [
                'user_id' => $student->id,
                'my_class_id' => $class->id,
            ]);
        }
        $this->assertDatabaseMissing('fee_invoices', ['user_id' => $outsideStudent->id]);

        Livewire::actingAs($manager)
            ->test(ManageFeeInvoices::class)
            ->set('classFilter', $class->id)
            ->assertSee($students[0]->name)
            ->assertSee($students[1]->name)
            ->assertDontSee($outsideStudent->name)
            ->call('sortBy', 'class')
            ->assertSet('sortField', 'class');
    }

    public function test_finance_staff_can_filter_collections_and_record_a_manual_payment(): void
    {
        [$school, $manager, $class, $students, $fee] = $this->classBillingContext();
        $invoice = FeeInvoice::query()->create([
            'name' => 'Class fee invoice',
            'user_id' => $students[0]->id,
            'my_class_id' => $class->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addMonth()->toDateString(),
        ]);
        $record = FeeInvoiceRecord::query()->create([
            'fee_invoice_id' => $invoice->id,
            'fee_id' => $fee->id,
            'amount' => 100,
            'waiver' => 0,
            'fine' => 0,
        ]);

        Livewire::actingAs($manager)
            ->test(ManageFeeInvoices::class)
            ->call('showTab', 'collections')
            ->assertSet('tab', 'collections')
            ->assertSee('Payment Management');

        Livewire::actingAs($manager)
            ->test(ManageFeePayments::class)
            ->assertSee('Payment Management')
            ->assertSee($students[0]->name)
            ->set('classFilter', $class->id)
            ->set('statusFilter', 'unpaid')
            ->assertSee($students[0]->name)
            ->call('startPayment', $record->id)
            ->assertSet('paymentAmount', '100.00')
            ->set('paymentAmount', '40')
            ->set('paymentMethod', 'cash')
            ->set('paymentReference', 'CASHBOOK-001')
            ->set('paymentNote', 'Paid at the bursary desk.')
            ->call('recordManualPayment')
            ->assertHasNoErrors();

        $this->assertSame(4000, (int) $record->fresh()->getRawOriginal('paid'));
        $this->assertDatabaseHas('fee_payment_transactions', [
            'fee_invoice_record_id' => $record->id,
            'type' => 'payment',
            'payment_method' => 'cash',
            'external_reference' => 'CASHBOOK-001',
            'cashier_id' => $manager->id,
        ]);

        Livewire::actingAs($manager)
            ->test(ManageFeePayments::class)
            ->set('statusFilter', 'partial')
            ->assertSee($students[0]->name)
            ->set('statusFilter', 'paid')
            ->assertDontSee($students[0]->name);
    }

    /**
     * @return array{School, User, MyClass, array<int, User>, Fee}
     */
    private function classBillingContext(): array
    {
        $school = $this->school('CLASS');
        $manager = $this->user($school, 'finance-manager');
        $manager->assignRole(Role::findOrCreate('admin', 'web'));
        $manager->givePermissionTo([
            Permission::findOrCreate('read fee invoice', 'web'),
            Permission::findOrCreate('create fee invoice', 'web'),
            Permission::findOrCreate('update fee invoice', 'web'),
        ]);
        $classGroup = ClassGroup::query()->create([
            'school_id' => $school->id,
            'name' => 'Junior School',
        ]);
        $class = MyClass::query()->create([
            'class_group_id' => $classGroup->id,
            'name' => 'JSS 1',
        ]);
        $students = [
            $this->student($school, $class, 'first-student'),
            $this->student($school, $class, 'second-student'),
        ];
        $category = FeeCategory::query()->create([
            'school_id' => $school->id,
            'name' => 'Class fees',
        ]);
        $fee = Fee::query()->create([
            'fee_category_id' => $category->id,
            'name' => 'Class development fee',
            'description' => 'A fee assigned to every active student in the selected class.',
            'default_amount' => 100,
            'status' => 'approved',
            'approval_version' => 1,
            'approved_at' => now(),
        ]);

        return [$school, $manager, $class, $students, $fee];
    }

    private function student(School $school, MyClass $class, string $label): User
    {
        $student = $this->user($school, $label);
        $student->assignRole(Role::findOrCreate('student', 'web'));
        StudentRecord::query()->create([
            'user_id' => $student->id,
            'my_class_id' => $class->id,
            'admission_number' => 'ADM-'.$student->id,
            'is_graduated' => false,
        ]);

        return $student;
    }

    private function school(string $suffix): School
    {
        $this->sequence++;

        return School::query()->create([
            'name' => "Class Billing School {$suffix}",
            'code' => "CLASS-{$suffix}-{$this->sequence}",
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
