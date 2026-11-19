<?php

namespace App\Livewire\Birthdays;

use App\Services\BirthdayService;
use App\Services\BirthdayWishService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithPagination;

class BirthdayDirectory extends Component
{
    use WithPagination;

    public string $search = '';

    public string $type = 'all';

    public string $classFilter = 'all';

    public string $month = 'all';

    public string $range = '30';

    public int $calendarMonth;

    public ?int $selectedCalendarDay = null;

    public ?int $selectedCelebrantId = null;

    public string $selectedCelebrantName = '';

    public string $subject = '';

    public string $message = '';

    public bool $wishModalOpen = false;

    protected $queryString = [
        'search' => ['except' => ''],
        'type' => ['except' => 'all'],
        'classFilter' => ['except' => 'all', 'as' => 'class'],
        'month' => ['except' => 'all'],
        'range' => ['except' => '30'],
    ];

    public function mount(BirthdayService $birthdays): void
    {
        $user = auth()->user();
        abort_unless($user && $birthdays->canView($user), 403);
        $this->calendarMonth = (int) now(config('birthdays.timezone'))->month;

        $wishId = request()->integer('wish');
        if ($wishId > 0 && $user->can('send birthday wishes')) {
            $this->openWish($wishId);
        }
    }

    public function updated($property): void
    {
        if (in_array($property, ['search', 'type', 'classFilter', 'month', 'range'], true)) {
            $this->resetPage();
        }
    }

    public function previousCalendarMonth(): void
    {
        $this->calendarMonth = $this->calendarMonth === 1 ? 12 : $this->calendarMonth - 1;
    }

    public function nextCalendarMonth(): void
    {
        $this->calendarMonth = $this->calendarMonth === 12 ? 1 : $this->calendarMonth + 1;
    }

    public function selectCalendarDay(int $day): void
    {
        $this->selectedCalendarDay = $day;
    }

    public function openWish(int $celebrantId): void
    {
        abort_unless(auth()->user()?->can('send birthday wishes'), 403);
        $celebrant = app(BirthdayService::class)->celebrants((int) auth()->user()->school_id)->firstWhere('id', $celebrantId);
        abort_unless($celebrant, 404);

        $this->selectedCelebrantId = $celebrantId;
        $this->selectedCelebrantName = $celebrant['name'];
        $this->subject = "Happy Birthday, {$celebrant['name']}! 🎉";
        $this->message = config('birthdays.templates.0');
        $this->wishModalOpen = true;
        $this->resetValidation();
    }

    public function useTemplate(int $index): void
    {
        $template = config("birthdays.templates.{$index}");
        if (is_string($template)) {
            $this->message = $template;
        }
    }

    public function closeWish(): void
    {
        $this->reset(['selectedCelebrantId', 'selectedCelebrantName', 'subject', 'message', 'wishModalOpen']);
        $this->resetValidation();
    }

    public function sendWish(BirthdayWishService $wishes): void
    {
        $validated = $this->validate([
            'selectedCelebrantId' => ['required', 'integer'],
            'subject' => ['required', 'string', 'min:3', 'max:160'],
            'message' => ['required', 'string', 'min:10', 'max:'.config('birthdays.message_max_length', 1200)],
        ]);

        $wishes->sendManual(
            auth()->user(),
            (int) $validated['selectedCelebrantId'],
            trim($validated['subject']),
            trim($validated['message']),
        );

        $this->closeWish();
        session()->flash('birthday_success', 'Your birthday wish has been queued for secure delivery.');
    }

    public function render(BirthdayService $birthdays)
    {
        $all = $birthdays->celebrants((int) auth()->user()->school_id)->sortBy('days_until')->values();
        $today = $all->where('days_until', 0)->values();
        $upcoming = $this->applyRange($all)->take(24)->values();
        $filtered = $this->applyDirectoryFilters($all);
        $perPage = 18;
        $page = $this->getPage();
        $directory = new LengthAwarePaginator(
            $filtered->forPage($page, $perPage)->values(),
            $filtered->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        $calendar = $this->calendarDays($all, $this->calendarMonth);
        $selectedCalendarCelebrants = $this->selectedCalendarDay
            ? $all->where('month', $this->calendarMonth)->where('day', $this->selectedCalendarDay)->values()
            : collect();
        $classes = $all->where('type', 'student')->pluck('context')->filter()->unique()->sort()->values();
        $settings = $birthdays->settings((int) auth()->user()->school_id);

        return view('livewire.birthdays.birthday-directory', compact(
            'today', 'upcoming', 'directory', 'calendar', 'classes', 'settings', 'selectedCalendarCelebrants'
        ))->layout('layouts.dashboard', [
            'title' => 'Birthdays',
            'description' => 'Celebrate our school community without exposing private birth details.',
            'icon' => 'fas fa-cake-candles',
        ]);
    }

    private function applyRange(Collection $all): Collection
    {
        return match ($this->range) {
            '7', '14', '30' => $all->where('days_until', '<=', (int) $this->range),
            'month' => $all->where('month', (int) now(config('birthdays.timezone'))->month),
            default => $all,
        };
    }

    private function applyDirectoryFilters(Collection $all): Collection
    {
        $search = mb_strtolower(trim($this->search));

        return $all
            ->when($search !== '', fn (Collection $items) => $items->filter(fn (array $item): bool => str_contains(mb_strtolower($item['name']), $search)))
            ->when($this->type !== 'all', fn (Collection $items) => $items->where('type', $this->type))
            ->when($this->classFilter !== 'all', fn (Collection $items) => $items->where('type', 'student')->where('context', $this->classFilter))
            ->when($this->month !== 'all', fn (Collection $items) => $items->where('month', (int) $this->month))
            ->values();
    }

    private function calendarDays(Collection $all, int $month): array
    {
        $year = now(config('birthdays.timezone'))->year;
        $start = now(config('birthdays.timezone'))->setYear($year)->setMonth($month)->startOfMonth();
        $days = collect(range(1, $start->daysInMonth))->map(fn (int $day): array => [
            'day' => $day,
            'celebrants' => $all->where('month', $month)->where('day', $day)->values(),
        ])->all();

        return [
            'label' => $start->format('F'),
            'leading' => $start->dayOfWeek,
            'days' => $days,
        ];
    }
}
