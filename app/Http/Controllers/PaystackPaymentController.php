<?php

namespace App\Http\Controllers;

use App\Models\FeeInvoice;
use App\Models\FeeInvoiceRecord;
use App\Services\PaystackPaymentService;
use App\Traits\ResolvesAccessibleStudents;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class PaystackPaymentController extends Controller
{
    use ResolvesAccessibleStudents;

    public function initialize(
        Request $request,
        FeeInvoice $feeInvoice,
        FeeInvoiceRecord $record,
        PaystackPaymentService $payments,
    ): RedirectResponse {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'decimal:0,2'],
        ]);

        $this->ensureInvoiceAccess($feeInvoice, $record);

        try {
            $attempt = $payments->initialize($record, $request->user(), $validated['amount']);
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Paystack is temporarily unavailable. No payment was recorded; please try again.');
        }

        return redirect()->away($attempt->authorization_url);
    }

    private function ensureInvoiceAccess(FeeInvoice $invoice, FeeInvoiceRecord $record): void
    {
        abort_unless((int) $record->fee_invoice_id === (int) $invoice->id, 404);
        abort_unless((int) $invoice->user()->value('school_id') === (int) auth()->user()->school_id, 404);

        if ($this->isRestrictedStudentPortalViewer()) {
            abort_unless($this->portalAccessibleStudentUserIds()->contains((int) $invoice->user_id), 404);
        }
    }
}
