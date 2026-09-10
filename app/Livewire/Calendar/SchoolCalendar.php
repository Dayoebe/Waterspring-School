<?php

namespace App\Livewire\Calendar;

use App\Models\Assignment;
use App\Models\SchoolEvent;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class SchoolCalendar extends Component
{
    public string $month = '';
    public string $search = '';
    public string $categoryFilter = 'all';
    public ?int $editingEventId = null;
    public string $title = '';
    public string $description = '';
    public string $category = 'event';
    public string $startsAt = '';
    public string $endsAt = '';
    public bool $allDay = false;
    public string $location = '';
    public array $audiences = ['all'];

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('read school calendar'), 403);
        $this->month = now()->format('Y-m');
    }

    public function previousMonth(): void
    {
        $this->month = $this->monthStart()->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->month = $this->monthStart()->addMonth()->format('Y-m');
    }

    public function today(): void
    {
        $this->month = now()->format('Y-m');
    }

    public function createEvent(): void
    {
        $this->ensureCanManage();
        $this->resetForm();
        $this->startsAt = now()->addDay()->format('Y-m-d\TH:i');
    }

    public function editEvent(int $eventId): void
    {
        $this->ensureCanManage();
        $event = SchoolEvent::query()->findOrFail($eventId);
        $this->editingEventId = $event->id;
        $this->title = $event->title;
        $this->description = (string) $event->description;
        $this->category = $event->category;
        $this->startsAt = $event->starts_at->format('Y-m-d\TH:i');
        $this->endsAt = $event->ends_at?->format('Y-m-d\TH:i') ?? '';
        $this->allDay = $event->all_day;
        $this->location = (string) $event->location;
        $this->audiences = $event->audiences ?: ['all'];
    }

    public function saveEvent(): void
    {
        $this->ensureCanManage();
        $validated = $this->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['required', 'in:event,academic,deadline,holiday,meeting,activity'],
            'startsAt' => ['required', 'date'],
            'endsAt' => ['nullable', 'date', 'after_or_equal:startsAt'],
            'allDay' => ['boolean'],
            'location' => ['nullable', 'string', 'max:180'],
            'audiences' => ['required', 'array', 'min:1'],
            'audiences.*' => ['in:all,staff,teacher,student,parent'],
        ]);

        $event = $this->editingEventId
            ? SchoolEvent::query()->findOrFail($this->editingEventId)
            : new SchoolEvent(['created_by' => auth()->id()]);
        $event->fill([
            'title' => trim($validated['title']),
            'description' => trim($validated['description']) ?: null,
            'category' => $validated['category'],
            'starts_at' => $validated['startsAt'],
            'ends_at' => $validated['endsAt'] ?: null,
            'all_day' => $validated['allDay'],
            'location' => trim($validated['location']) ?: null,
            'audiences' => in_array('all', $validated['audiences'], true) ? ['all'] : array_values(array_unique($validated['audiences'])),
        ])->save();

        $this->resetForm();
        session()->flash('success', 'Calendar event saved successfully.');
    }

    public function deleteEvent(int $eventId): void
    {
        $this->ensureCanManage();
        SchoolEvent::query()->findOrFail($eventId)->delete();
        if ($this->editingEventId === $eventId) {
            $this->resetForm();
        }
        session()->flash('success', 'Calendar event deleted.');
    }

    public function cancelEditing(): void
    {
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->reset(['editingEventId', 'title', 'description', 'startsAt', 'endsAt', 'location']);
        $this->category = 'event';
        $this->allDay = false;
        $this->audiences = ['all'];
        $this->resetValidation();
    }

    protected function ensureCanManage(): void
    {
        abort_unless(auth()->user()?->can('manage school calendar'), 403);
    }

    protected function monthStart(): Carbon
    {
        try {
            return Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();
        } catch (\Throwable) {
            return now()->startOfMonth();
        }
    }

    protected function visibleEvents(Carbon $start, Carbon $end)
    {
        $user = auth()->user();
        $roles = $user->getRoleNames()->all();

        return SchoolEvent::query()
            ->with('creator:id,name')
            ->whereBetween('starts_at', [$start, $end])
            ->when($this->categoryFilter !== 'all', fn (Builder $query) => $query->where('category', $this->categoryFilter))
            ->when($this->search !== '', function (Builder $query): void {
                $query->where(function (Builder $query): void {
                    $query->where('title', 'like', '%'.$this->search.'%')
                        ->orWhere('description', 'like', '%'.$this->search.'%')
                        ->orWhere('location', 'like', '%'.$this->search.'%');
                });
            })
            ->get()
            ->filter(function (SchoolEvent $event) use ($roles): bool {
                $audiences = $event->audiences ?: ['all'];
                if (in_array('all', $audiences, true)) {
                    return true;
                }
                if (array_intersect($roles, $audiences)) {
                    return true;
                }

                return in_array('staff', $audiences, true)
                    && (bool) array_intersect($roles, ['teacher', 'principal', 'admin', 'super-admin', 'super_admin']);
            });
    }

    protected function assignmentDeadlines(Carbon $start, Carbon $end)
    {
        if (! in_array($this->categoryFilter, ['all', 'deadline'], true)) {
            return collect();
        }

        $user = auth()->user();
        $query = Assignment::query()
            ->with(['myClass:id,name', 'subject:id,name'])
            ->where('school_id', $user->school_id)
            ->whereNotNull('published_at')
            ->whereBetween('due_at', [$start, $end])
            ->when($this->search !== '', fn (Builder $query) => $query->where('title', 'like', '%'.$this->search.'%'));

        if ($user->hasRole('student')) {
            $query->whereHas('recipients', fn (Builder $query) => $query->where('user_id', $user->id));
        } elseif ($user->hasRole('parent')) {
            $query->whereHas('recipients.user.parents', fn (Builder $query) => $query->where('users.id', $user->id));
        } elseif ($user->hasRole('teacher')) {
            $query->where('teacher_id', $user->id);
        }

        return $query->orderBy('due_at')->get();
    }

    public function render()
    {
        $start = $this->monthStart();
        $end = $start->copy()->endOfMonth();
        $events = $this->visibleEvents($start, $end);
        $deadlines = $this->assignmentDeadlines($start, $end);
        $days = collect(range(1, $start->daysInMonth))->map(function (int $day) use ($start, $events, $deadlines): array {
            $date = $start->copy()->day($day);

            return [
                'date' => $date,
                'events' => $events->filter(fn (SchoolEvent $event) => $event->starts_at->isSameDay($date))->values(),
                'deadlines' => $deadlines->filter(fn (Assignment $assignment) => $assignment->due_at->isSameDay($date))->values(),
            ];
        });

        return view('livewire.calendar.school-calendar', [
            'days' => $days,
            'leadingBlankDays' => $start->dayOfWeek,
            'monthLabel' => $start->format('F Y'),
            'canManage' => auth()->user()?->can('manage school calendar') ?? false,
            'upcoming' => $events->sortBy('starts_at')->take(8),
        ])->layout('layouts.dashboard', [
            'breadcrumbs' => [
                ['href' => route('dashboard'), 'text' => 'Dashboard'],
                ['href' => route('calendar.index'), 'text' => 'School Calendar', 'active' => true],
            ],
        ])->title('School Calendar');
    }
}
