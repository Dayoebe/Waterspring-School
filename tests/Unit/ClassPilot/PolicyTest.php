<?php

namespace Tests\Unit\ClassPilot;

use App\Models\ClassPilot\LearningSession;
use App\Models\ClassPilot\Material;
use App\Models\User;
use App\Policies\ClassPilotMaterialPolicy;
use App\Policies\ClassPilotSessionPolicy;
use Tests\TestCase;

class PolicyTest extends TestCase
{
    public function test_conversation_ownership_and_school_are_both_required(): void
    {
        $user = new User(['school_id' => 3]);
        $user->id = 7;
        $own = new LearningSession(['school_id' => 3, 'user_id' => 7]);
        $otherUser = new LearningSession(['school_id' => 3, 'user_id' => 8]);
        $otherSchool = new LearningSession(['school_id' => 4, 'user_id' => 7]);
        $policy = new ClassPilotSessionPolicy;
        $this->assertTrue($policy->view($user, $own));
        $this->assertFalse($policy->view($user, $otherUser));
        $this->assertFalse($policy->view($user, $otherSchool));
    }

    public function test_material_edits_require_same_school_author_and_draft_state(): void
    {
        $user = new User(['school_id' => 3]);
        $user->id = 7;
        $policy = new ClassPilotMaterialPolicy;
        $this->assertTrue($policy->update($user, new Material(['school_id' => 3, 'author_id' => 7, 'status' => 'draft'])));
        $this->assertFalse($policy->update($user, new Material(['school_id' => 3, 'author_id' => 7, 'status' => 'approved'])));
        $this->assertFalse($policy->update($user, new Material(['school_id' => 4, 'author_id' => 7, 'status' => 'draft'])));
    }
}
