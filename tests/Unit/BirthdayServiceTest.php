<?php

namespace Tests\Unit;

use App\Services\BirthdayService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BirthdayServiceTest extends TestCase
{
    #[DataProvider('birthdayCases')]
    public function test_it_calculates_the_next_recurring_birthday(string $birthday, string $today, string $expected, int $days): void
    {
        $service = app(BirthdayService::class);
        $from = CarbonImmutable::parse($today, 'Africa/Lagos');
        $next = $service->nextBirthday($birthday, $from);

        $this->assertSame($expected, $next?->toDateString());
        $this->assertSame($days, (int) $from->startOfDay()->diffInDays($next));
    }

    public static function birthdayCases(): array
    {
        return [
            'today' => ['2010-11-19', '2026-11-19', '2026-11-19', 0],
            'tomorrow' => ['2010-11-20', '2026-11-19', '2026-11-20', 1],
            'december to january' => ['2010-01-02', '2026-12-31', '2027-01-02', 2],
            'leap birthday in leap year' => ['2012-02-29', '2028-02-28', '2028-02-29', 1],
            'leap birthday in non leap year' => ['2012-02-29', '2027-02-27', '2027-02-28', 1],
        ];
    }

    public function test_it_handles_empty_invalid_dates_and_timezone_boundaries(): void
    {
        $service = app(BirthdayService::class);
        $this->assertNull($service->nextBirthday(null));
        $this->assertNull($service->nextBirthday('not-a-date'));

        $utc = CarbonImmutable::parse('2026-12-31 23:30:00', 'UTC');
        $this->assertSame('2027-01-01', $service->nextBirthday('2000-01-01', $utc)?->toDateString());
    }
}
