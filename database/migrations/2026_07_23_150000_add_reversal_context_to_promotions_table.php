<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->foreignId('from_academic_year_id')
                ->nullable()
                ->after('academic_year_id')
                ->constrained('academic_years')
                ->nullOnDelete();
            $table->string('movement_type', 20)->default('promotion')->after('from_academic_year_id');
            $table->json('student_snapshots')->nullable()->after('students');
        });

        $previousTargetStates = [];
        $academicYearsBySchool = DB::table('academic_years')
            ->orderBy('start_year')
            ->get()
            ->groupBy('school_id');

        DB::table('promotions')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->each(function ($promotion) use (&$previousTargetStates, $academicYearsBySchool) {
                $students = json_decode($promotion->students, true);
                if (!is_array($students)) {
                    return;
                }

                $targetYear = DB::table('academic_years')->find($promotion->academic_year_id);
                $fromYear = $targetYear
                    ? collect($academicYearsBySchool->get($promotion->school_id, collect()))
                        ->filter(fn ($year) => (int) $year->start_year < (int) $targetYear->start_year)
                        ->sortByDesc('start_year')
                        ->first()
                    : null;

                $studentRecords = DB::table('student_records')
                    ->whereIn('user_id', $students)
                    ->get()
                    ->keyBy('user_id');
                $sourceRows = $fromYear
                    ? DB::table('academic_year_student_record')
                        ->where('academic_year_id', $fromYear->id)
                        ->whereIn('student_record_id', $studentRecords->pluck('id'))
                        ->get()
                        ->keyBy('student_record_id')
                    : collect();

                $snapshots = [];
                foreach ($students as $userId) {
                    $record = $studentRecords->get($userId);
                    if (!$record) {
                        continue;
                    }

                    $stateKey = $promotion->school_id . ':' . $promotion->academic_year_id . ':' . $record->id;
                    $previous = $previousTargetStates[$stateKey] ?? null;
                    $source = $sourceRows->get($record->id);
                    $targetPreviouslyExisted = $previous !== null || $source !== null;

                    $snapshots[(string) $userId] = [
                        'student_record_id' => (int) $record->id,
                        'source_class_id' => (int) ($source->my_class_id ?? $promotion->old_class_id),
                        'source_section_id' => isset($source->section_id)
                            ? (int) $source->section_id
                            : ($promotion->old_section_id ? (int) $promotion->old_section_id : null),
                        // Legacy resets historically kept the student in the target
                        // year and restored the source class. Preserve that behaviour
                        // when reconstructing the first known movement.
                        'target_existed' => $targetPreviouslyExisted,
                        'previous_target_class_id' => $previous['class_id']
                            ?? ($source->my_class_id ?? $promotion->old_class_id),
                        'previous_target_section_id' => $previous['section_id']
                            ?? ($source->section_id ?? $promotion->old_section_id),
                    ];

                    $previousTargetStates[$stateKey] = [
                        'class_id' => (int) $promotion->new_class_id,
                        'section_id' => $promotion->new_section_id ? (int) $promotion->new_section_id : null,
                    ];
                }

                DB::table('promotions')->where('id', $promotion->id)->update([
                    'from_academic_year_id' => $fromYear?->id,
                    'movement_type' => $this->movementType(
                        (int) $promotion->old_class_id,
                        (int) $promotion->new_class_id
                    ),
                    'student_snapshots' => json_encode($snapshots),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropForeign(['from_academic_year_id']);
            $table->dropColumn(['from_academic_year_id', 'movement_type', 'student_snapshots']);
        });
    }

    private function movementType(int $oldClassId, int $newClassId): string
    {
        $classNames = DB::table('my_classes')
            ->whereIn('id', [$oldClassId, $newClassId])
            ->pluck('name', 'id');
        $oldRank = $this->classRank($classNames[$oldClassId] ?? null);
        $newRank = $this->classRank($classNames[$newClassId] ?? null);

        if ($oldRank !== null && $newRank !== null) {
            return $newRank > $oldRank ? 'promotion' : ($newRank < $oldRank ? 'demotion' : 'repeat');
        }

        return 'movement';
    }

    private function classRank(?string $name): ?int
    {
        $normalized = strtoupper(str_replace(' ', '', (string) $name));

        return [
            'JSS1' => 1,
            'JSS2' => 2,
            'JSS3' => 3,
            'SS1' => 4,
            'SSS1' => 4,
            'SS2' => 5,
            'SSS2' => 5,
            'SS3' => 6,
            'SSS3' => 6,
        ][$normalized] ?? null;
    }
};
