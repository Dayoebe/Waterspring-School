<?php

namespace App\Http\Controllers;

use App\Services\FlutterwavePaymentService;
use App\Services\FlutterwaveWebhookVerifier;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class FlutterwaveWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        FlutterwaveWebhookVerifier $signatures,
        FlutterwavePaymentService $payments,
    ): JsonResponse {
        if (! $signatures->isValid($request)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $payload = $request->json()->all();
        if (($payload['event'] ?? null) !== 'charge.completed' || ($payload['data']['status'] ?? null) !== 'successful') {
            return response()->json(['received' => true]);
        }

        $reference = $payload['data']['tx_ref'] ?? null;
        $transactionId = $payload['data']['id'] ?? null;
        if (! is_string($reference) || $reference === '' || filter_var($transactionId, FILTER_VALIDATE_INT) === false) {
            return response()->json(['message' => 'Missing transaction identity.'], 422);
        }

        try {
            $payments->verifyAndFulfill($reference, (int) $transactionId);
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
