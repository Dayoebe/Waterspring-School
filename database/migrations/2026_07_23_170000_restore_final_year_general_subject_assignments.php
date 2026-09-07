<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $schools = DB::table('schools')
            ->whereNotNull('academic_year_id')
            ->get(['id', 'academic_year_id']);

        foreach ($schools as $school) {
            $ss3ClassIds = DB::table('my_classes')
                ->join('class_groups', 'class_groups.id', '=', 'my_classes.class_group_id')
                ->where('class_groups.school_id', $school->id)
                ->whereNull('my_classes.deleted_at')
                ->whereRaw("UPPER(REPLACE(my_classes.name, ' ', '')) IN ('SS3', 'SSS3')")
                ->pluck('my_classes.id');

            $englishSubjectId = DB::table('subjects')
                ->where('school_id', $school->id)
                ->where('is_legacy', false)
                ->whereNull('deleted_at')
                ->whereRaw("LOWER(TRIM(name)) = 'english language'")
                ->value('id');

            if ($englishSubjectId) {
                foreach ($ss3ClassIds as $classId) {
                    DB::table('class_subject')->insertOrIgnore([
                        'my_class_id' => $classId,
                        'subject_id' => $englishSubjectId,
                        'school_id' => $school->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            $placements = DB::table('academic_year_student_record')
                ->where('academic_year_id', $school->academic_year_id)
                ->get(['student_record_id', 'my_class_id', 'section_id']);

            foreach ($placements->groupBy('my_class_id') as $classId => $classPlacements) {
                $generalSubjectIds = DB::table('class_subject')
                    ->join('subjects', 'subjects.id', '=', 'class_subject.subject_id')
                    ->where('class_subject.school_id', $school->id)
                    ->where('class_subject.my_class_id', $classId)
                    ->where('subjects.school_id', $school->id)
                    ->where('subjects.is_general', true)
                    ->where('subjects.is_legacy', false)
                    ->whereNull('subjects.deleted_at')
                    ->pluck('subjects.id');

                foreach ($classPlacements as $placement) {
                    foreach ($generalSubjectIds as $subjectId) {
                        DB::table('student_subject')->updateOrInsert(
                            [
                                'student_record_id' => $placement->student_record_id,
                                'subject_id' => $subjectId,
                            ],
                            [
                                'my_class_id' => $classId,
                                'section_id' => $placement->section_id,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]
                        );
                    }
                }
            }
        }
    }

    public function down(): void
    {
        // Existing student-subject history cannot be distinguished safely from repaired rows.
    }
};
