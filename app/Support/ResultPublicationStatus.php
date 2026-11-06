<?php

namespace App\Support;

use App\Models\ResultPublication;
use App\Models\User;
use App\Services\AuditTrail;

class ResultPublicationStatus
{
    public static function termKey(int $semesterId): string
    {
        return 'term:' . $semesterId;
    }

    public static function annualKey(): string
    {
        return 'annual';
    }

    public static function termIsPublished(int $schoolId, int $academicYearId, int $semesterId): bool
    {
        return self::isPublished($schoolId, $academicYearId, self::termKey($semesterId));
    }

    public static function annualIsPublished(int $schoolId, int $academicYearId): bool
    {
        return self::isPublished($schoolId, $academicYearId, self::annualKey());
    }

    public static function setTermPublished(
        int $schoolId,
        int $academicYearId,
        int $semesterId,
        bool $published,
        ?int $userId
    ): ResultPublication {
        return self::setPublished(
            $schoolId,
            $academicYearId,
            $semesterId,
            self::termKey($semesterId),
            $published,
            $userId
        );
    }

    public static function setAnnualPublished(
        int $schoolId,
        int $academicYearId,
        bool $published,
        ?int $userId
    ): ResultPublication {
        return self::setPublished(
            $schoolId,
            $academicYearId,
            null,
            self::annualKey(),
            $published,
            $userId
        );
    }

    protected static function isPublished(int $schoolId, int $academicYearId, string $periodKey): bool
    {
        return ResultPublication::query()
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->where('period_key', $periodKey)
            ->whereNotNull('published_at')
            ->exists();
    }

    protected static function setPublished(
        int $schoolId,
        int $academicYearId,
        ?int $semesterId,
        string $periodKey,
        bool $published,
        ?int $userId
    ): ResultPublication {
        $existing = ResultPublication::query()->where([
            'school_id' => $schoolId,
            'academic_year_id' => $academicYearId,
            'period_key' => $periodKey,
        ])->first();
        $wasPublished = $existing?->published_at !== null;

        $publication = ResultPublication::query()->updateOrCreate(
            [
                'school_id' => $schoolId,
                'academic_year_id' => $academicYearId,
                'period_key' => $periodKey,
            ],
            [
                'semester_id' => $semesterId,
                'published_at' => $published ? now() : null,
                'published_by' => $published ? $userId : null,
            ]
        );

        if ($wasPublished !== $published) {
            app(AuditTrail::class)->record(
                $schoolId,
                'results',
                $published ? 'results.published' : 'results.unpublished',
                $published ? 'Results were published.' : 'Result publication was revoked.',
                $publication,
                ['published' => $wasPublished],
                ['published' => $published],
                [
                    'academic_year_id' => $academicYearId,
                    'semester_id' => $semesterId,
                    'period_key' => $periodKey,
                ],
                $userId ? User::query()->find($userId) : null,
                $periodKey === 'annual' ? 'Annual results' : 'Term results',
            );
        }

        return $publication;
    }
}
