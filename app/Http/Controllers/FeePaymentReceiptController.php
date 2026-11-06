<?php

namespace App\Http\Controllers;

use App\Models\FeePaymentTransaction;
use App\Traits\ResolvesAccessibleStudents;
use Illuminate\View\View;

class FeePaymentReceiptController extends Controller
{
    use ResolvesAccessibleStudents;

    public function show(int $transaction): View
    {
        $query = FeePaymentTransaction::query()
            ->whereKey($transaction)
            ->where('school_id', auth()->user()->school_id)
            ->whereHas('invoice.user', function ($query): void {
                $query->where('school_id', auth()->user()->school_id);
            });

        if ($this->isRestrictedStudentPortalViewer()) {
            $studentUserIds = $this->portalAccessibleStudentUserIds();

            $studentUserIds->isEmpty()
                ? $query->whereRaw('1 = 0')
                : $query->whereHas('invoice', fn ($invoice) => $invoice->whereIn('user_id', $studentUserIds));
        }

        $payment = $query->with([
            'school',
            'invoice.user',
            'invoiceRecord.fee',
            'cashier',
            'originalTransaction',
            'reconciliations',
        ])->firstOrFail();

        return view('fees.receipt', ['payment' => $payment]);
    }
}
