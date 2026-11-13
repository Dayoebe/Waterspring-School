<?php

namespace Tests\Feature\Fees;

use App\Livewire\Fees\ManageFeeInvoices;
use App\Livewire\Fees\ManageFees;
use App\Mail\FeeApprovedParentMail;
use App\Models\Fee;
use App\Models\FeeCategory;
use App\Models\School;
use App\Models\User;
use App\Services\FeeApprovalService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FeeCatalogWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    public function test_category_and_percentage_waiver_pricing_can_be_created_from_the_same_page(): void
    {
        $school = $this->school('CREATE');
        $manager = $this->user($school, 'manager');
        $manager->givePermissionTo([
            Permission::findOrCreate('create fee', 'web'),
            Permission::findOrCreate('create fee category', 'web'),
        ]);

        $component = Livewire::actingAs($manager)
            ->test(ManageFees::class)
            ->call('switchMode', 'create')
            ->assertSee('Fee Name')
            ->assertSee('Waiver')
            ->assertSee('Percentage')
            ->set('showCategoryCreator', true)
            ->set('newCategoryName', 'Technology fees')
            ->set('newCategoryDescription', 'Technology and digital learning charges.')
            ->call('createCategoryInline')
            ->assertHasNoErrors();

        $category = FeeCategory::query()->where('name', 'Technology fees')->firstOrFail();
        $component
            ->assertSet('fee_category_id', $category->id)
            ->set('name', 'Digital learning subscription')
            ->set('amount', '12500.50')
            ->set('waiverType', Fee::WAIVER_PERCENTAGE)
            ->set('waiverPercentage', '12.5')
            ->set('fine', '300')
            ->set('description', 'Covers the approved digital learning platforms for the term.')
            ->call('createFee')
            ->assertHasNoErrors();

        $fee = Fee::query()->where('name', 'Digital learning subscription')->firstOrFail();
        $this->assertSame($school->id, $fee->feeCategory->school_id);
        $this->assertSame(1250050, (int) $fee->getRawOriginal('default_amount'));
        $this->assertSame(Fee::WAIVER_PERCENTAGE, $fee->default_waiver_type);
        $this->assertSame(1250, $fee->default_waiver_basis_points);
        $this->assertSame(156256, $fee->defaultWaiver()->getMinorAmount()->toInt());
        $this->assertSame(30000, (int) $fee->getRawOriginal('default_fine'));
        $this->assertSame('11237.94', (string) $fee->defaultTotal()->getAmount());
        $this->assertSame('draft', $fee->status);
        $this->assertNull($fee->approved_at);
    }

    public function test_approval_queues_one_explanatory_email_per_valid_school_parent(): void
    {
        Mail::fake();
        $school = $this->school('APPROVE');
        $otherSchool = $this->school('OTHER');
        $approver = $this->user($school, 'approver');
        $approver->givePermissionTo(Permission::findOrCreate('approve fee', 'web'));
        $parentA = $this->parent($school, 'parent-a');
        $parentB = $this->parent($school, 'parent-b');
        $placeholderParent = $this->parent($school, 'placeholder');
        $placeholderParent->update(['email_is_placeholder' => true]);
        $this->parent($otherSchool, 'other-parent');
        $fee = $this->fee($school, 'Term tuition', 50000, 'Covers teaching, learning materials, and core school services.');

        $result = app(FeeApprovalService::class)->approve($fee, $approver);

        $approved = $fee->fresh();
        $this->assertSame('approved', $approved->status);
        $this->assertSame(1, $approved->approval_version);
        $this->assertSame($approver->id, $approved->approved_by);
        $this->assertNotNull($approved->approved_at);
        $this->assertSame(['queued' => 2, 'failed' => 1, 'skipped' => 0], $result['notifications']);
        $this->assertDatabaseCount('fee_approval_notification_deliveries', 3);
        $this->assertDatabaseHas('audit_events', [
            'school_id' => $school->id,
            'event' => 'fee.approved',
            'actor_id' => $approver->id,
            'subject_id' => $fee->id,
        ]);
        Mail::assertQueued(FeeApprovedParentMail::class, function ($mail) use ($parentA): bool {
            return $mail->recipient->is($parentA)
                && $mail->fee->description === 'Covers teaching, learning materials, and core school services.';
        });
        Mail::assertQueued(FeeApprovedParentMail::class, fn ($mail) => $mail->recipient->is($parentB));
        Mail::assertQueued(FeeApprovedParentMail::class, 2);
    }

    public function test_approval_rejects_an_incomplete_fee_without_sending_mail(): void
    {
        Mail::fake();
        $school = $this->school('REJECT');
        $approver = $this->user($school, 'approver');
        $approver->givePermissionTo(Permission::findOrCreate('approve fee', 'web'));
        $fee = $this->fee($school, 'Incomplete fee', 0, 'Too short');

        try {
            app(FeeApprovalService::class)->approve($fee, $approver);
            $this->fail('Expected approval to reject a zero amount.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('amount greater than zero', $exception->getMessage());
        }

        $this->assertSame('draft', $fee->fresh()->status);
        $this->assertDatabaseCount('fee_approval_notification_deliveries', 0);
        Mail::assertNothingQueued();
    }

    public function test_only_approved_fees_are_available_and_default_amount_prefills_an_invoice(): void
    {
        $school = $this->school('INVOICE');
        $manager = $this->user($school, 'invoice-manager');
        $manager->givePermissionTo(Permission::findOrCreate('create fee invoice', 'web'));
        $approved = $this->fee($school, 'Approved tuition', 75000, 'Approved tuition explanation for parents.');
        $approved->update([
            'default_waiver_type' => Fee::WAIVER_PERCENTAGE,
            'default_waiver_basis_points' => 1000,
            'default_fine' => 2500,
            'status' => 'approved',
            'approval_version' => 1,
            'approved_at' => now(),
        ]);
        $draft = Fee::query()->create([
            'fee_category_id' => $approved->fee_category_id,
            'name' => 'Draft transport',
            'description' => 'Draft transport explanation for parents.',
            'default_amount' => 15000,
            'status' => 'draft',
            'approval_version' => 0,
        ]);

        $component = Livewire::actingAs($manager)
            ->test(ManageFeeInvoices::class)
            ->call('switchMode', 'create')
            ->set('selectedFeeCategory', $approved->fee_category_id)
            ->assertSet('selectedFee', '');

        $this->assertSame([$approved->id], $component->get('fees')->pluck('id')->all());

        $component
            ->set('selectedFee', $approved->id)
            ->call('addFee')
            ->assertSet("selectedFees.{$approved->id}.amount", '75000.00')
            ->assertSet("selectedFees.{$approved->id}.waiver", '7500.00')
            ->assertSet("selectedFees.{$approved->id}.fine", '2500.00');

        $this->assertArrayNotHasKey($draft->id, $component->get('selectedFees'));
    }

    public function test_editing_an_approved_fee_returns_it_to_draft_for_reapproval(): void
    {
        $school = $this->school('EDIT');
        $manager = $this->user($school, 'editor');
        $manager->givePermissionTo(Permission::findOrCreate('update fee', 'web'));
        $fee = $this->fee($school, 'Approved levy', 10000, 'Original approved explanation for parents.');
        $fee->update([
            'status' => 'approved',
            'approval_version' => 1,
            'approved_by' => $manager->id,
            'approved_at' => now(),
        ]);

        Livewire::actingAs($manager)
            ->test(ManageFees::class)
            ->call('switchMode', 'edit', $fee->id)
            ->set('amount', '12000')
            ->set('description', 'Revised approved explanation that requires another review.')
            ->call('updateFee')
            ->assertHasNoErrors();

        $fee->refresh();
        $this->assertSame('draft', $fee->status);
        $this->assertSame(1200000, (int) $fee->getRawOriginal('default_amount'));
        $this->assertNull($fee->approved_by);
        $this->assertNull($fee->approved_at);
        $this->assertSame(1, $fee->approval_version);
    }

    public function test_fixed_waiver_cannot_be_greater_than_the_fee_amount(): void
    {
        $school = $this->school('WAIVER');
        $manager = $this->user($school, 'waiver-manager');
        $manager->givePermissionTo(Permission::findOrCreate('create fee', 'web'));
        $category = FeeCategory::query()->create([
            'school_id' => $school->id,
            'name' => 'Waiver validation category',
        ]);

        Livewire::actingAs($manager)
            ->test(ManageFees::class)
            ->call('switchMode', 'create')
            ->set('name', 'Invalid waiver fee')
            ->set('fee_category_id', $category->id)
            ->set('amount', '1000')
            ->set('waiverType', Fee::WAIVER_FIXED)
            ->set('waiverAmount', '1000.01')
            ->set('fine', '0')
            ->set('description', 'This fee deliberately has an invalid fixed waiver amount.')
            ->call('createFee')
            ->assertHasErrors(['waiverAmount']);

        $this->assertDatabaseMissing('fees', ['name' => 'Invalid waiver fee']);
    }

    private function fee(School $school, string $name, int|float $amount, string $description): Fee
    {
        $category = FeeCategory::query()->create([
            'school_id' => $school->id,
            'name' => $name.' category '.$this->sequence,
        ]);

        return Fee::query()->create([
            'fee_category_id' => $category->id,
            'name' => $name,
            'description' => $description,
            'default_amount' => $amount,
            'status' => 'draft',
            'approval_version' => 0,
        ]);
    }

    private function parent(School $school, string $label): User
    {
        $parent = $this->user($school, $label);
        $parent->assignRole(Role::findOrCreate('parent', 'web'));

        return $parent;
    }

    private function school(string $suffix): School
    {
        $this->sequence++;

        return School::query()->create([
            'name' => "Fee Workflow School {$suffix}",
            'code' => "FEE-{$suffix}-{$this->sequence}",
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
