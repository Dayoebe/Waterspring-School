<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $classes = DB::table('my_classes as mc')
            ->join('class_groups as cg', 'cg.id', '=', 'mc.class_group_id')
            ->whereIn('mc.name', ['S S S 1', 'S S S 2'])
            ->get(['mc.id', 'mc.name', 'cg.school_id']);

        foreach ($classes as $class) {
            $subjectNames = $class->name === 'S S S 1'
                ? ['English Language', 'Further Mathematics']
                : ['Further Mathematics'];

            $subjectIds = DB::table('subjects')
                ->where('school_id', $class->school_id)
                ->where('is_legacy', false)
                ->whereIn('name', $subjectNames)
                ->pluck('id');

            foreach ($subjectIds as $subjectId) {
                DB::table('class_subject')->updateOrInsert(
                    [
                        'my_class_id' => $class->id,
                        'subject_id' => $subjectId,
                    ],
                    [
                        'school_id' => $class->school_id,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }

        $obsoleteIgboIds = DB::table('subjects')
            ->where('name', 'Igbo Language')
            ->pluck('id');

        DB::table('student_subject')
            ->whereIn('subject_id', $obsoleteIgboIds)
            ->whereIn('student_record_id', function ($query) {
                $query->select('id')
                    ->from('student_records')
                    ->where('is_graduated', false);
            })
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('student_records as sr')
                    ->join('class_subject as cs', function ($join) {
                        $join->on('cs.my_class_id', '=', 'sr.my_class_id')
                            ->on('cs.subject_id', '=', 'student_subject.subject_id');
                    })
                    ->whereColumn('sr.id', 'student_subject.student_record_id');
            })
            ->delete();
    }

    public function down(): void
    {
        // Student enrollment data cannot be reconstructed safely. Curriculum
        // links are intentionally retained when rolling back this repair.
    }
};
