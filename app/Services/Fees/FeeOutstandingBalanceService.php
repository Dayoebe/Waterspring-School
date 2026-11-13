<?php

namespace App\Services\Fees;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FeeOutstandingBalanceService
{
    private const OUTSTANDING_EXPRESSION = 'CASE WHEN records.amount + records.fine - records.paid - records.waiver > 0 '
        .'THEN records.amount + records.fine - records.paid - records.waiver ELSE 0 END';

    /**
     * @param  Collection<int, int>|array<int, int>  $studentIds
     * @return Collection<int, array{outstanding_minor: int, invoice_count: int}>
     */
    public function balancesByStudentIds(Collection|array $studentIds, int $schoolId): Collection
    {
        $studentIds = collect($studentIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();

        if ($studentIds->isEmpty()) {
            return collect();
        }

        return DB::table('fee_invoices as invoices')
            ->join('fee_invoice_records as records', 'records.fee_invoice_id', '=', 'invoices.id')
            ->join('users as students', 'students.id', '=', 'invoices.user_id')
            ->where('students.school_id', $schoolId)
            ->whereIn('students.id', $studentIds)
            ->whereNull('students.deleted_at')
            ->whereNull('invoices.deleted_at')
            ->select('students.id as student_id')
            ->selectRaw('SUM('.self::OUTSTANDING_EXPRESSION.') as outstanding_minor')
            ->selectRaw('COUNT(DISTINCT CASE WHEN '.self::OUTSTANDING_EXPRESSION.' > 0 THEN invoices.id END) as invoice_count')
            ->groupBy('students.id')
            ->get()
            ->mapWithKeys(fn ($row): array => [
                (int) $row->student_id => [
                    'outstanding_minor' => (int) $row->outstanding_minor,
                    'invoice_count' => (int) $row->invoice_count,
                ],
            ]);
    }

    /**
     * @param  Collection<int, int>|array<int, int>  $managedClassIds
     * @return array{unpaid_count: int, hidden_count: int, students: array<int, array<string, mixed>>}
     */
    public function classTeacherArrears(
        User $teacher,
        Collection|array $managedClassIds,
        ?int $academicYearId,
        int $limit = 10
    ): array {
        $classIds = collect($managedClassIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();

        if (! $teacher->school_id || $classIds->isEmpty()) {
            return ['unpaid_count' => 0, 'hidden_count' => 0, 'students' => []];
        }

        $students = DB::table('student_records as student_records')
            ->join('users as students', 'students.id', '=', 'student_records.user_id')
            ->when($academicYearId, function ($query, $yearId) {
                $query->join('academic_year_student_record as year_record', function ($join) use ($yearId) {
                    $join->on('year_record.student_record_id', '=', 'student_records.id')
                        ->where('year_record.academic_year_id', '=', $yearId);
                });
            })
            ->join('my_classes as classes', function ($join) use ($academicYearId) {
                $join->on('classes.id', '=', $academicYearId ? 'year_record.my_class_id' : 'student_records.my_class_id');
            })
            ->where('students.school_id', $teacher->school_id)
            ->whereIn($academicYearId ? 'year_record.my_class_id' : 'student_records.my_class_id', $classIds)
            ->where('student_records.is_graduated', false)
            ->whereNull('students.deleted_at')
            ->whereNull('classes.deleted_at')
            ->select('students.id', 'students.name', 'classes.name as class_name')
            ->orderBy('classes.name')
            ->orderBy('students.name')
            ->get()
            ->unique('id')
            ->values();

        $balances = $this->balancesByStudentIds($students->pluck('id'), (int) $teacher->school_id);
        $unpaid = $students
            ->map(function ($student) use ($balances): ?array {
                $balance = $balances->get((int) $student->id, ['outstanding_minor' => 0, 'invoice_count' => 0]);
                if ($balance['outstanding_minor'] <= 0) {
                    return null;
                }

                return [
                    'student_id' => (int) $student->id,
                    'name' => $student->name,
                    'class_name' => $student->class_name,
                    'invoice_count' => $balance['invoice_count'],
                ];
            })
            ->filter()
            ->values();

        return [
            'unpaid_count' => $unpaid->count(),
            'hidden_count' => max($unpaid->count() - $limit, 0),
            'students' => $unpaid->take($limit)->all(),
        ];
    }
}
