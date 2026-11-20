<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Services\InterventionAlertService;
use Illuminate\Console\Command;

class GenerateInterventionAlerts extends Command
{
    protected $signature = 'interventions:generate {--school=}';

    protected $description = 'Evaluate transparent student-support rules without making disciplinary decisions';

    public function handle(InterventionAlertService $service): int
    {
        $schools = School::query()->when($this->option('school'), fn ($q, $id) => $q->whereKey($id))->pluck('id');
        foreach ($schools as $schoolId) {
            $result = $service->generateForSchool((int) $schoolId);
            $this->line("School {$schoolId}: checked {$result['checked']}, created {$result['created']}, skipped incomplete {$result['incomplete']}.");
        }

        return self::SUCCESS;
    }
}
