<?php

namespace App\Livewire\Parents;

use App\Models\ParentRecord;
use App\Services\AuditTrail;
use App\Services\ParentDigestService;
use Livewire\Component;

class WeeklyDigest extends Component
{
    public array $preview = [];

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('view parent digest'), 403);
    }

    public function toggle(int $linkId): void
    {
        $link = $this->links()->findOrFail($linkId);
        $wasEnabled = $link->receives_digest;
        $link->update(['receives_digest' => ! $link->receives_digest]);
        app(AuditTrail::class)->record(
            (int) auth()->user()->school_id,
            'communications',
            'parent.digest.preference.updated',
            'A guardian updated a child-specific weekly digest preference.',
            $link,
            ['receives_digest' => $wasEnabled],
            ['receives_digest' => $link->receives_digest],
            ['student_id' => $link->student_id],
            auth()->user(),
        );
        $this->preview = [];
        session()->flash('success', $link->receives_digest ? 'Weekly digest enabled for this child.' : 'Weekly digest disabled for this child.');
    }

    public function preview(ParentDigestService $digests): void
    {
        $this->preview = $digests->preview(auth()->user());
        if ($this->preview['children'] === []) {
            $this->addError('preview', 'Enable the digest for at least one linked child to see a preview.');
        }
    }

    private function links()
    {
        return ParentRecord::query()->active()
            ->where('user_id', auth()->id())
            ->whereNotNull('verified_at')
            ->whereHas('student', fn ($query) => $query->where('school_id', auth()->user()->school_id))
            ->with('student');
    }

    public function render()
    {
        return view('livewire.parents.weekly-digest', ['links' => $this->links()->get()])
            ->layout('layouts.dashboard', [
                'breadcrumbs' => [
                    ['href' => route('dashboard'), 'text' => 'Dashboard'],
                    ['href' => route('parent-digest.index'), 'text' => 'Weekly Digest', 'active' => true],
                ],
            ])->title('Parent Weekly Digest');
    }
}
