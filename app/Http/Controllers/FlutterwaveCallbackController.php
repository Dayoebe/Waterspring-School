<?php

namespace App\Http\Controllers;

use App\Services\FlutterwavePaymentService;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class FlutterwaveCallbackController extends Controller
{
    public function __invoke(Request $request, FlutterwavePaymentService $payments): View
    {
        $validated = $request->validate([
            'tx_ref' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9._-]+$/'],
            'status' => ['required', 'string', 'max:32'],
            'transaction_id' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            if (($validated['status'] ?? null) !== 'successful' || empty($validated['transaction_id'])) {
                $attempt = $payments->findAttempt($validated['tx_ref']);

                return view('fees.payment-result', [
                    'attempt' => $attempt,
                    'successful' => false,
                    'providerName' => 'Flutterwave',
                    'message' => 'Flutterwave did not report a successful payment. No fee balance was changed.',
                ]);
            }

            $attempt = $payments->verifyAndFulfill($validated['tx_ref'], (int) $validated['transaction_id']);
            $successful = $attempt->status === 'paid' && $attempt->transaction !== null;

            return view('fees.payment-result', compact('attempt', 'successful') + ['providerName' => 'Flutterwave']);
        } catch (DomainException $exception) {
            return view('fees.payment-result', [
                'attempt' => null,
                'successful' => false,
                'providerName' => 'Flutterwave',
                'message' => $exception->getMessage(),
            ]);
        } catch (ModelNotFoundException) {
            abort(404);
        } catch (Throwable $exception) {
            report($exception);

            return view('fees.payment-result', [
                'attempt' => null,
                'successful' => false,
                'providerName' => 'Flutterwave',
                'message' => 'We could not confirm the payment yet. Please check again shortly or contact the school.',
            ]);
        }
    }
}
