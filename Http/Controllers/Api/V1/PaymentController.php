<?php

namespace Modules\Payment\Http\Controllers\Api\V1;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Payment\DTO\CustomerData;
use Modules\Payment\DTO\PaymentChargeRequest;
use Modules\Payment\Http\Resources\V1\PaymentGatewayResource;
use Modules\Payment\Http\Resources\V1\PaymentResource;
use Modules\Payment\Models\Payment;
use Modules\Payment\Models\PaymentGatewayConfig;
use Modules\Payment\Services\PaymentService;

class PaymentController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected PaymentService $paymentService
    ) {}

    /**
     * List active payment gateways.
     */
    public function gateways(): AnonymousResourceCollection
    {
        $gateways = PaymentGatewayConfig::query()
            ->active()
            ->get();

        return PaymentGatewayResource::collection($gateways);
    }

    /**
     * Initiate a new payment / checkout session.
     */
    public function checkout(Request $request): JsonResponse
    {
        $user = $request->user('sanctum');

        if (! $user && ! config('payment.api.allow_guest_checkout', false)) {
            return response()->json([
                'message' => 'Authentication is required to initiate payment checkout.',
            ], 401);
        }

        $maxAmount = (float) config('payment.api.max_checkout_amount', 100000);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.$maxAmount],
            'currency' => ['required', 'string', 'size:3'],
            'gateway' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:255'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'return_url' => ['nullable', 'url'],
            'cancel_url' => ['nullable', 'url'],
            'metadata' => ['nullable', 'array'],
        ]);

        $currency = strtoupper($validated['currency']);
        $gatewayCode = $validated['gateway'] ?? config('payment.default', 'mock');
        $gatewayConfig = PaymentGatewayConfig::where('code', $gatewayCode)->first();
        if ($gatewayConfig && ! empty($gatewayConfig->supported_currencies)
            && ! in_array($currency, array_map('strtoupper', $gatewayConfig->supported_currencies), true)) {
            return response()->json([
                'message' => "Currency [{$currency}] is not supported by gateway [{$gatewayCode}].",
            ], 422);
        }

        $customer = CustomerData::fromArray([
            'name' => $validated['customer_name'] ?? $user?->name,
            'email' => $validated['customer_email'] ?? $user?->email,
            'phone' => $validated['customer_phone'] ?? $user?->phone,
            'ipAddress' => $request->ip(),
        ]);

        $chargeRequest = PaymentChargeRequest::make(
            (float) $validated['amount'],
            strtoupper($validated['currency'])
        )
            ->withCustomer($customer)
            ->withUrls($validated['return_url'] ?? null, $validated['cancel_url'] ?? null)
            ->withMetadata($validated['metadata'] ?? []);

        if (! empty($validated['description'])) {
            $chargeRequest = $chargeRequest->withDescription($validated['description']);
        }

        if ($user) {
            $chargeRequest = $chargeRequest->withPayable($user);
        }

        $response = $this->paymentService->charge($chargeRequest, $validated['gateway'] ?? null);

        return response()->json([
            'success' => $response->isSuccessful() || $response->isActionRequired() || $response->isPending(),
            'status' => $response->status->value,
            'reference' => $response->reference,
            'transaction_id' => $response->transactionId,
            'redirect_url' => $response->redirectUrl,
            'error_message' => $response->errorMessage,
        ], 201);
    }

    /**
     * List payments (Admin).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Payment::class);

        $perPage = min(max($request->integer('per_page', 15), 1), 50);

        $query = Payment::query()->latest('id');

        return PaymentResource::collection($query->paginate($perPage)->withQueryString());
    }

    /**
     * Get payment status and details by UUID or reference (Admin).
     */
    public function show(string $uuidOrReference): PaymentResource
    {
        $payment = Payment::query()
            ->where('uuid', $uuidOrReference)
            ->orWhere('reference', $uuidOrReference)
            ->firstOrFail();

        $this->authorize('view', $payment);

        return new PaymentResource($payment);
    }

    /**
     * Refund a paid payment (Admin).
     */
    public function refund(Request $request, string $uuidOrReference): JsonResponse
    {
        $payment = Payment::query()
            ->where('uuid', $uuidOrReference)
            ->orWhere('reference', $uuidOrReference)
            ->firstOrFail();

        $this->authorize('update', $payment);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $response = $this->paymentService->refund(
            $payment,
            (float) $validated['amount'],
            $validated['reason'] ?? null
        );

        return response()->json([
            'success' => $response->isSuccessful,
            'refund_id' => $response->refundId,
            'amount' => $response->amount,
            'error_message' => $response->errorMessage,
        ]);
    }
}
