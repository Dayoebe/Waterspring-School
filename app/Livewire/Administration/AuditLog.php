<?php

namespace App\Livewire\Administration;

use App\Models\AuditEvent;
use Livewire\Component;
use Livewire\WithPagination;

class AuditLog extends Component
{
    use WithPagination;

    public string $search = '';

    public string $category = 'all';

    public string $actorId = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public ?int $selectedEventId = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'category' => ['except' => 'all'],
        'actorId' => ['except' => ''],
        'dateFrom' => ['except' => ''],
        'dateTo' => ['except' => ''],
    ];

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('view audit log'), 403);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'category', 'actorId', 'dateFrom', 'dateTo'], true)) {
            $this->resetPage();
        }
    }

    public function selectEvent(int $eventId): void
    {
        $this->selectedEventId = $this->baseQuery()->whereKey($eventId)->value('id');
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'actorId', 'dateFrom', 'dateTo', 'selectedEventId']);
        $this->category = 'all';
        $this->resetPage();
    }

    private function baseQuery()
    {
        return AuditEvent::query()->where('school_id', auth()->user()->school_id);
    }

    public function render()
    {
        $query = $this->baseQuery()
            ->with('actor:id,name')
            ->when($this->category !== 'all', fn ($query) => $query->where('category', $this->category))
            ->when(ctype_digit($this->actorId), fn ($query) => $query->where('actor_id', (int) $this->actorId))
            ->when($this->dateFrom !== '', fn ($query) => $query->whereDate('occurred_at', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn ($query) => $query->whereDate('occurred_at', '<=', $this->dateTo))
            ->when(trim($this->search) !== '', function ($query): void {
                $search = '%'.trim($this->search).'%';
                $query->where(fn ($inner) => $inner
                    ->where('description', 'like', $search)
                    ->orWhere('event', 'like', $search)
                    ->orWhere('actor_name', 'like', $search)
                    ->orWhere('subject_label', 'like', $search));
            })
            ->latest('occurred_at')
            ->latest('id');

        $events = $query->paginate(25);
        $actors = $this->baseQuery()->whereNotNull('actor_id')->select('actor_id', 'actor_name')
            ->distinct()->orderBy('actor_name')->get();
        $selectedEvent = $this->selectedEventId ? $this->baseQuery()->find($this->selectedEventId) : null;

        return view('livewire.administration.audit-log', [
            'events' => $events,
            'actors' => $actors,
            'selectedEvent' => $selectedEvent,
            'categories' => [
                'results' => 'Results and marks',
                'finance' => 'Invoices and payments',
                'access' => 'Roles and permissions',
                'support' => 'Student support access',
                'classpilot' => 'ClassPilot approvals',
            ],
        ])->layout('layouts.dashboard', [
            'breadcrumbs' => [
                ['href' => route('dashboard'), 'text' => 'Dashboard'],
                ['href' => route('audit-log.index'), 'text' => 'Audit & Accountability', 'active' => true],
            ],
        ])->title('Audit & Accountability');
    }
}
