<?php

namespace App\Services;

use App\Models\BirthdaySetting;
use App\Models\MyClass;
use App\Models\School;
use App\Models\StudentRecord;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BirthdayService
{
    public const STAFF_ROLES = ['teacher', 'principal', 'admin', 'super-admin', 'super_admin'];

    public function settings(int $schoolId): BirthdaySetting
    {
        return BirthdaySetting::query()->where('school_id', $schoolId)->first()
            ?? BirthdaySetting::defaultsFor($schoolId);
    }

    public function canView(User $viewer, ?BirthdaySetting $settings = null): bool
    {
        $settings ??= $this->settings((int) $viewer->school_id);

        if (! $settings->feature_enabled || ! $viewer->can('view birthdays')) {
            return false;
        }

        return $settings->viewer_scope !== 'staff'
            || $viewer->hasAnyRole(self::STAFF_ROLES);
    }

    public function today(int $schoolId, ?CarbonInterface $now = null): Collection
    {
        $today = $this->todayAt($now);

        return $this->upcoming($schoolId, 0, $today)
            ->filter(fn (array $celebrant): bool => $celebrant['days_until'] === 0)
            ->values();
    }

    public function upcoming(int $schoolId, int $days = 30, ?CarbonInterface $now = null): Collection
    {
        $today = $this->todayAt($now);

        return $this->celebrants($schoolId, $today)
            ->filter(fn (array $celebrant): bool => $celebrant['days_until'] <= max(0, $days))
            ->sortBy(fn (array $celebrant): string => sprintf('%03d-%s', $celebrant['days_until'], mb_strtolower($celebrant['name'])))
            ->values();
    }

    public function dashboard(int $schoolId, ?CarbonInterface $now = null): array
    {
        $today = $this->todayAt($now);
        $version = Cache::get("birthdays:{$schoolId}:version", 1);
        $key = "birthdays:{$schoolId}:dashboard:{$today->toDateString()}:{$version}";

        return Cache::remember($key, now()->addMinutes(10), function () use ($schoolId, $today): array {
            $upcoming = $this->upcoming($schoolId, 7, $today);
            $todayCelebrants = $upcoming->where('days_until', 0)->values();
            $next = $todayCelebrants->isEmpty()
                ? $this->celebrants($schoolId, $today)->sortBy('days_until')->first()
                : null;

            return [
                'today' => $todayCelebrants->all(),
                'next' => $next,
                'upcoming' => $upcoming->all(),
            ];
        });
    }

    public function celebrants(int $schoolId, ?CarbonInterface $now = null): Collection
    {
        $today = $this->todayAt($now);
        $version = Cache::get("birthdays:{$schoolId}:version", 1);
        $key = "birthdays:{$schoolId}:people:{$today->toDateString()}:{$version}";

        return Cache::remember($key, now()->addMinutes(10), fn (): Collection => $this->resolveCelebrants($schoolId, $today));
    }

    private function resolveCelebrants(int $schoolId, CarbonImmutable $today): Collection
    {
        $settings = $this->settings($schoolId);

        if (! $settings->feature_enabled || (! $settings->show_students && ! $settings->show_staff)) {
            return collect();
        }

        $query = User::query()
            ->where('school_id', $schoolId)
            ->where('locked', false)
            ->where('birthday_is_private', false)
            ->whereNotNull('birthday')
            ->whereHas('roles', function (Builder $query) use ($settings): void {
                $roles = [];
                if ($settings->show_students) {
                    $roles[] = 'student';
                }
                if ($settings->show_staff) {
                    $roles = array_merge($roles, self::STAFF_ROLES);
                }
                $query->whereIn('name', array_unique($roles));
            })
            ->with([
                'roles:id,name',
                'studentRecord:id,user_id,my_class_id,is_graduated',
                'studentRecord.baseClass:id,name',
                'staffProfile:id,user_id,staff_department_id,job_title',
                'staffProfile.department:id,name',
            ]);

        $users = $query->orderBy('name')->get();
        $academicYearId = School::query()->whereKey($schoolId)->value('academic_year_id');
        $activeStudentIds = StudentRecord::activeStudentRecordIdsForSchoolAcademicYear($schoolId, $academicYearId)->flip();
        $classNames = $this->currentClassNames($users, $academicYearId);

        return $users
            ->filter(function (User $user) use ($activeStudentIds, $settings): bool {
                if ($user->hasRole('student')) {
                    return $settings->show_students
                        && $user->studentRecord
                        && $activeStudentIds->has($user->studentRecord->id);
                }

                return $settings->show_staff && $user->hasAnyRole(self::STAFF_ROLES);
            })
            ->map(function (User $user) use ($today, $classNames): ?array {
                $next = $this->nextBirthday($user->birthday, $today);
                if (! $next) {
                    return null;
                }

                $student = $user->hasRole('student');
                $baseClass = $user->studentRecord?->baseClass;
                $staffProfile = $user->staffProfile;
                $label = $student
                    ? ($classNames[$user->id] ?? ($baseClass ? $baseClass->name : 'Student'))
                    : ($staffProfile?->job_title ?: ($staffProfile?->department ? $staffProfile->department->name : 'Staff'));

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'photo_url' => $user->profile_photo_url,
                    'type' => $student ? 'student' : 'staff',
                    'type_label' => $student ? 'Student' : 'Staff',
                    'context' => $label,
                    'month' => $next->month,
                    'day' => $next->day,
                    'birthday_label' => $next->format('F j'),
                    'next_birthday' => $next->toDateString(),
                    'days_until' => (int) $today->diffInDays($next),
                    'relative_label' => $this->relativeLabel((int) $today->diffInDays($next)),
                ];
            })
            ->filter()
            ->values();
    }

    public function nextBirthday(mixed $birthday, ?CarbonInterface $from = null): ?CarbonImmutable
    {
        if (blank($birthday)) {
            return null;
        }

        try {
            $birthDate = CarbonImmutable::parse($birthday);
        } catch (\Throwable) {
            return null;
        }

        $today = $this->todayAt($from);
        $candidate = $this->birthdayInYear($birthDate->month, $birthDate->day, $today->year, $today->timezone->getName());

        if ($candidate->lt($today)) {
            $candidate = $this->birthdayInYear($birthDate->month, $birthDate->day, $today->year + 1, $today->timezone->getName());
        }

        return $candidate;
    }

    public function relativeLabel(int $days): string
    {
        return match ($days) {
            0 => 'Today',
            1 => 'Tomorrow',
            7 => 'Next week',
            default => "In {$days} days",
        };
    }

    public function clearCache(int $schoolId): void
    {
        Cache::increment("birthdays:{$schoolId}:version");
    }

    private function todayAt(?CarbonInterface $now): CarbonImmutable
    {
        $timezone = config('birthdays.timezone', 'Africa/Lagos');

        return $now
            ? CarbonImmutable::instance($now)->setTimezone($timezone)->startOfDay()
            : CarbonImmutable::now($timezone)->startOfDay();
    }

    private function birthdayInYear(int $month, int $day, int $year, string $timezone): CarbonImmutable
    {
        if ($month === 2 && $day === 29 && ! CarbonImmutable::create($year, 1, 1)->isLeapYear()) {
            return CarbonImmutable::create($year, 2, 28, 0, 0, 0, $timezone);
        }

        return CarbonImmutable::create($year, $month, $day, 0, 0, 0, $timezone);
    }

    private function currentClassNames(Collection $users, ?int $academicYearId): array
    {
        if (! $academicYearId || $users->isEmpty()) {
            return [];
        }

        $rows = DB::table('academic_year_student_record as aysr')
            ->join('student_records as sr', 'sr.id', '=', 'aysr.student_record_id')
            ->where('aysr.academic_year_id', $academicYearId)
            ->whereIn('sr.user_id', $users->pluck('id'))
            ->select('sr.user_id', 'aysr.my_class_id')
            ->get();
        $classes = MyClass::query()->whereIn('id', $rows->pluck('my_class_id')->filter()->unique())->pluck('name', 'id');

        return $rows->mapWithKeys(fn ($row): array => [$row->user_id => $classes[$row->my_class_id] ?? null])
            ->filter()->all();
    }
}
