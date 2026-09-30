<?php

namespace Modules\Payment\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Facades\Payment;

class PaymentWebhookController extends Controller
{
    /**
     * Handle incoming webhooks from any payment provider.
     */
    public function handle(string $gateway, Request $request): JsonResponse
    {
        try {
            $result = Payment::handleWebhook($gateway, $request);

            if (! $result->isHandled) {
                return response()->json([
                    'status' => 'error',
                    'message' => $result->message,
                ], 400);
            }

            return response()->json([
                'status' => 'success',
                'event' => $result->eventType,
                'message' => $result->message,
            ]);
        } catch (PaymentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error while processing webhook',
            ], 500);
        }
    }
}
