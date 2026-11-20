<?php

namespace App\Livewire\Parents;

use App\Jobs\SendParentWeeklyDigest;
use App\Models\ParentDigestDelivery;
use App\Models\ParentDigestSetting;
use App\Services\AuditTrail;
use App\Services\ParentDigestService;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class ManageWeeklyDigest extends Component
{
    use WithPagination;

    public bool $enabled = false;

    public int $deliveryDay = 1;

    public string $deliveryTime = '07:00';

    public string $timezone = 'Africa/Lagos';

    public int $upcomingDays = 7;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('manage parent digest'), 403);
        $settings = ParentDigestSetting::forSchool((int) auth()->user()->school_id);
        $this->enabled = (bool) $settings->enabled;
        $this->deliveryDay = (int) $settings->delivery_day;
        $this->deliveryTime = substr((string) $settings->delivery_time, 0, 5);
        $this->timezone = (string) $settings->timezone;
        $this->upcomingDays = (int) $settings->upcoming_days;
    }

    public function save(): void
    {
        $this->validate([
            'enabled' => ['boolean'], 'deliveryDay' => ['required', 'integer', 'between:1,7'],
            'deliveryTime' => ['required', 'date_format:H:i'],
            'timezone' => ['required', Rule::in(['Africa/Lagos', 'UTC'])],
            'upcomingDays' => ['required', 'integer', 'between:1,30'],
        ]);
        $settings = ParentDigestSetting::query()->updateOrCreate(['school_id' => auth()->user()->school_id], [
            'enabled' => $this->enabled, 'delivery_day' => $this->deliveryDay,
            'delivery_time' => $this->deliveryTime, 'timezone' => $this->timezone,
            'upcoming_days' => $this->upcomingDays, 'updated_by' => auth()->id(),
        ]);
        app(AuditTrail::class)->record(
            (int) auth()->user()->school_id,
            'communications',
            'parent.digest.settings.updated',
            'Parent weekly digest settings were updated.',
            $settings,
            null,
            ['enabled' => $settings->enabled, 'delivery_day' => $settings->delivery_day, 'delivery_time' => $settings->delivery_time],
            actor: auth()->user(),
        );
        session()->flash('success', 'Weekly digest settings saved. Guardian opt-in remains required for each child.');
    }

    public function queueNow(ParentDigestService $digests): void
    {
        $result = $digests->queueForSchool((int) auth()->user()->school_id);
        session()->flash('success', "Queued {$result['queued']} digest(s); skipped {$result['skipped']} duplicate(s).");
    }

    public function retry(int $deliveryId): void
    {
        $delivery = ParentDigestDelivery::query()->where('school_id', auth()->user()->school_id)->where('status', 'failed')->findOrFail($deliveryId);
        $delivery->update(['status' => 'queued', 'queued_at' => now(), 'failed_at' => null, 'failure_code' => null]);
        SendParentWeeklyDigest::dispatch($delivery->id);
        app(AuditTrail::class)->record(
            (int) auth()->user()->school_id,
            'communications',
            'parent.digest.retry.queued',
            'A failed parent digest was queued for retry.',
            $delivery,
            ['status' => 'failed'],
            ['status' => 'queued'],
            actor: auth()->user(),
        );
        session()->flash('success', 'Failed digest queued for retry.');
    }

    public function render()
    {
        $deliveries = ParentDigestDelivery::query()->where('school_id', auth()->user()->school_id)
            ->with('guardian:id,name')->latest('week_start_date')->latest('id')->paginate(20);

        return view('livewire.parents.manage-weekly-digest', compact('deliveries'))
            ->layout('layouts.dashboard')->title('Manage Parent Digests');
    }
}
