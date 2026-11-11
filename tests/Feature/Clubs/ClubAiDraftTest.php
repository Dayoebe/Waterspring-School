<?php

namespace Tests\Feature\Clubs;

use App\Livewire\Clubs\ClubHub;
use App\Models\AcademicYear;
use App\Models\Club;
use App\Models\ClubTermPlan;
use App\Models\School;
use App\Models\Semester;
use App\Models\User;
use App\Services\ClassPilot\Contracts\AiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClubAiDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_drafts_a_club_profile_without_overwriting_or_saving(): void
    {
        $data = $this->clubContext();
        $provider = new FakeClubProvider;
        app()->instance(AiProvider::class, $provider);

        $component = Livewire::actingAs($data['user'])
            ->test(ClubHub::class)
            ->call('createClub')
            ->set('name', 'Young Authors Club')
            ->set('category', 'Teacher-selected category');

        $this->assertSame([], $provider->calls);
        $component->call('generateClubProfileWithAi')
            ->assertHasNoErrors()
            ->assertSet('name', 'Young Authors Club')
            ->assertSet('category', 'Teacher-selected category')
            ->assertSet('description', 'Students explore storytelling, editing, and responsible publishing.')
            ->assertSee('AI filled the blank club profile fields');

        $this->assertCount(1, $provider->calls);
        $this->assertDatabaseCount('clubs', 1);
        $this->assertDatabaseHas('class_pilot_usage', [
            'user_id' => $data['user']->id,
            'feature' => 'club_profile',
            'status' => 'completed',
        ]);
    }

    public function test_ai_drafts_activity_details_without_changing_schedule_or_saving(): void
    {
        $data = $this->clubContext();
        $provider = new FakeClubProvider;
        app()->instance(AiProvider::class, $provider);

        $component = Livewire::actingAs($data['user'])
            ->test(ClubHub::class)
            ->call('createActivity')
            ->set('activityTitle', 'Student Story Showcase')
            ->set('activityLocation', 'School hall');
        $startsAt = $component->get('activityStartsAt');

        $component->call('generateActivityWithAi')
            ->assertHasNoErrors()
            ->assertSet('activityTitle', 'Student Story Showcase')
            ->assertSet('activityLocation', 'School hall')
            ->assertSet('activityStartsAt', $startsAt)
            ->assertSet('activityDescription', 'Students prepare, present, and reflect on a short creative showcase.')
            ->assertSee('AI filled the blank activity details');

        $this->assertDatabaseCount('club_activities', 0);
    }

    public function test_ai_drafts_a_term_plan_but_keeps_goal_notes_and_database_untouched(): void
    {
        $data = $this->clubContext();
        $provider = new FakeClubProvider;
        app()->instance(AiProvider::class, $provider);

        Livewire::actingAs($data['user'])
            ->test(ClubHub::class)
            ->call('editTermPlan')
            ->set('planGoal', 'Publish a student-created anthology.')
            ->set('planStrengths', 'Keep this coordinator-written strength.')
            ->set('planNotes', 'Keep this private planning note.')
            ->call('generateTermPlanWithAi')
            ->assertHasNoErrors()
            ->assertSet('planGoal', 'Publish a student-created anthology.')
            ->assertSet('planObjectives', 'Plan, draft, revise, and present original student writing.')
            ->assertSet('planStrengths', 'Keep this coordinator-written strength.')
            ->assertSet('planNotes', 'Keep this private planning note.')
            ->assertSee('AI filled the blank term-plan fields');

        $this->assertDatabaseCount('club_term_plans', 0);
    }

    public function test_ai_drafts_weekly_agenda_details_without_saving(): void
    {
        $data = $this->clubContext(createPlan: true);
        $provider = new FakeClubProvider;
        app()->instance(AiProvider::class, $provider);

        Livewire::actingAs($data['user'])
            ->test(ClubHub::class)
            ->call('createAgenda')
            ->set('agendaTitle', 'Planning our anthology')
            ->set('agendaScheduledFor', '2030-09-12')
            ->call('generateAgendaWithAi')
            ->assertHasNoErrors()
            ->assertSet('agendaTitle', 'Planning our anthology')
            ->assertSet('agendaScheduledFor', '2030-09-12')
            ->assertSet('agendaDetails', 'Review the goal, assign writing roles, plan drafts, and close with a reflection.')
            ->assertSee('AI filled the blank weekly-agenda details');

        $this->assertDatabaseCount('club_weekly_agendas', 0);
    }

    public function test_malformed_output_is_rejected_and_unauthorised_user_cannot_trigger_ai(): void
    {
        $data = $this->clubContext();
        $provider = new FakeClubProvider;
        $provider->malformedFeature = 'club_activity';
        app()->instance(AiProvider::class, $provider);

        Livewire::actingAs($data['user'])
            ->test(ClubHub::class)
            ->call('createActivity')
            ->set('activityTitle', 'Showcase')
            ->call('generateActivityWithAi')
            ->assertHasErrors('activityAiGeneration')
            ->assertSet('activityDescription', '');

        $unauthorised = $this->clubContext(grantManagement: false);
        app()->instance(AiProvider::class, new FakeClubProvider);
        Livewire::actingAs($unauthorised['user'])
            ->test(ClubHub::class)
            ->set('selectedClubId', $unauthorised['club']->id)
            ->set('activityTitle', 'Showcase')
            ->call('generateActivityWithAi')
            ->assertForbidden();
    }

    private function clubContext(bool $createPlan = false, bool $grantManagement = true): array
    {
        $school = School::query()->create(['name' => 'Club School', 'code' => 'CLB'.uniqid()]);
        $year = AcademicYear::query()->create([
            'start_year' => '2030',
            'stop_year' => '2031',
            'school_id' => $school->id,
        ]);
        $term = Semester::query()->create([
            'name' => 'First Term',
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
        ]);
        $school->update(['academic_year_id' => $year->id, 'semester_id' => $term->id]);
        $user = User::query()->forceCreate([
            'name' => 'Club Coordinator',
            'email' => 'club-'.$school->id.'@example.test',
            'email_verified_at' => now(),
            'password' => bcrypt('Password1!'),
            'school_id' => $school->id,
            'locked' => false,
        ]);
        Role::findOrCreate('teacher', 'web');
        Role::findOrCreate('student', 'web');
        $user->assignRole(Role::findOrCreate('principal', 'web'));
        $user->givePermissionTo(Permission::findOrCreate('view clubs', 'web'));
        if ($grantManagement) {
            $user->givePermissionTo(Permission::findOrCreate('manage clubs', 'web'));
        }
        $club = Club::query()->create([
            'school_id' => $school->id,
            'name' => 'Young Authors Club',
            'category' => 'Creative',
            'description' => 'Students develop and share original writing.',
            'colour' => 'sky',
            'is_active' => true,
            'created_by' => $user->id,
        ]);
        $plan = null;
        if ($createPlan) {
            $plan = ClubTermPlan::query()->create([
                'club_id' => $club->id,
                'academic_year_id' => $year->id,
                'semester_id' => $term->id,
                'goal' => 'Publish a student-created anthology.',
                'objectives' => 'Plan, draft, revise, and present original student writing.',
                'published_at' => now(),
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
        }

        return compact('school', 'year', 'term', 'user', 'club', 'plan');
    }
}

class FakeClubProvider implements AiProvider
{
    public array $calls = [];

    public ?string $malformedFeature = null;

    public function generate(string $system, string $prompt): array
    {
        $this->calls[] = compact('system', 'prompt');

        foreach (['club_profile', 'club_activity', 'club_term_plan', 'club_agenda'] as $feature) {
            if ($this->malformedFeature === $feature && str_contains($system, $feature)) {
                return [];
            }
        }

        return match (true) {
            str_contains($system, 'club_profile') => [
                'category' => 'Creative',
                'description' => 'Students explore storytelling, editing, and responsible publishing.',
            ],
            str_contains($system, 'club_activity') => [
                'description' => 'Students prepare, present, and reflect on a short creative showcase.',
            ],
            str_contains($system, 'club_term_plan') => [
                'objectives' => 'Plan, draft, revise, and present original student writing.',
                'strengths' => 'Curiosity, imagination, and willingness to share ideas.',
                'focus_areas' => 'Planning, drafting, revision, feedback, and presentation.',
                'resources_needed' => 'Coordinator-reviewed writing materials and presentation tools.',
                'success_indicators' => 'Students complete revised work and contribute to the anthology.',
            ],
            default => [
                'agenda' => 'Review the goal, assign writing roles, plan drafts, and close with a reflection.',
            ],
        };
    }

    public function name(): string
    {
        return 'fake';
    }

    public function model(): string
    {
        return 'club-test-model';
    }

    public function configured(): bool
    {
        return true;
    }
}
