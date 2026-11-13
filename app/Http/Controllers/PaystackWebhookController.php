<?php

namespace App\Http\Controllers;

use App\Services\PaystackPaymentService;
use App\Services\PaystackWebhookVerifier;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class PaystackWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        PaystackWebhookVerifier $signatures,
        PaystackPaymentService $payments,
    ): JsonResponse {
        if (! $signatures->isValid($request)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $payload = $request->json()->all();
        if (($payload['event'] ?? null) !== 'charge.success') {
            return response()->json(['received' => true]);
        }

        $reference = $payload['data']['reference'] ?? null;
        if (! is_string($reference) || $reference === '') {
            return response()->json(['message' => 'Missing payment reference.'], 422);
        }

        try {
            $payments->verifyAndFulfill($reference);
        } catch (ModelNotFoundException) {
            return response()->json(['received' => true, 'matched' => false]);
        } catch (DomainException $exception) {
            report($exception);

            return response()->json(['received' => true, 'review_required' => true]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Payment verification failed.'], 500);
        }

        return response()->json(['received' => true, 'matched' => true]);
    }
}
