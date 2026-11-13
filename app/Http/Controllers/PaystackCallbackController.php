<?php

namespace App\Http\Controllers;

use App\Services\PaystackPaymentService;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class PaystackCallbackController extends Controller
{
    public function __invoke(Request $request, PaystackPaymentService $payments): View
    {
        $validated = $request->validate([
            'reference' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9._-]+$/'],
        ]);

        try {
            $attempt = $payments->verifyAndFulfill($validated['reference']);
            $successful = $attempt->status === 'paid' && $attempt->transaction !== null;

            return view('fees.payment-result', compact('attempt', 'successful'));
        } catch (DomainException $exception) {
            return view('fees.payment-result', [
                'attempt' => null,
                'successful' => false,
                'message' => $exception->getMessage(),
            ]);
        } catch (ModelNotFoundException) {
            abort(404);
        } catch (Throwable $exception) {
            report($exception);

            return view('fees.payment-result', [
                'attempt' => null,
                'successful' => false,
                'message' => 'We could not confirm the payment yet. Please check again shortly or contact the school.',
            ]);
        }
    }
}
