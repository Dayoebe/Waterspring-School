<?php 
// app/Console/Commands/AssignSubjectsCommand.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\School;
use App\Models\Subject;

class AssignSubjectsCommand extends Command
{
    protected $signature = 'subjects:assign';
    protected $description = 'Assign subjects to students';

    public function handle(): int
    {
        $schools = School::query()->whereNotNull('academic_year_id')->get();
        $assigned = 0;

        foreach ($schools as $school) {
            $subjects = Subject::query()
                ->where('school_id', $school->id)
                ->where('is_legacy', false)
                ->with('classes:id')
                ->get();

            foreach ($subjects as $subject) {
                foreach ($subject->classes as $class) {
                    $assigned += $subject->autoAssignToClassStudents(
                        $class->id,
                        $school->academic_year_id
                    );
                }
            }
        }
        
        $this->info("Subject assignments synchronized. {$assigned} student assignment(s) added or refreshed.");

        return self::SUCCESS;
    }
}
