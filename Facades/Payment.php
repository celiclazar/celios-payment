<?php

namespace Modules\Payment\Facades;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Modules\Payment\Contracts\PaymentGatewayInterface;
use Modules\Payment\DTO\PaymentChargeRequest;
use Modules\Payment\DTO\PaymentResponse;
use Modules\Payment\DTO\RefundResponse;
use Modules\Payment\DTO\WebhookResult;
use Modules\Payment\Models\Payment as PaymentModel;
use Modules\Payment\Services\PaymentManager;
use Modules\Payment\Services\PaymentService;

/**
 * @method static PaymentGatewayInterface gateway(?string $name = null)
 * @method static PaymentGatewayInterface driver(?string $name = null)
 * @method static PaymentManager extend(string $driver, Closure $callback)
 * @method static array getAvailableDrivers()
 * @method static PaymentResponse charge(PaymentChargeRequest $request, ?string $gateway = null)
 * @method static RefundResponse refund(PaymentModel|string $payment, float $amount, ?string $reason = null)
 * @method static PaymentResponse authorize(PaymentChargeRequest $request, ?string $gateway = null)
 * @method static PaymentResponse capture(PaymentModel|string $payment, ?float $amount = null)
 * @method static PaymentResponse inquire(PaymentModel|string $payment)
 * @method static WebhookResult handleWebhook(string $gateway, Request $request)
 *
 * @see PaymentService
 * @see PaymentManager
 */
class Payment extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'payment';
    }

    /**
     * Helper to proxy calls directly to driver or manager if not found on service.
     */
    public static function driver(?string $name = null): PaymentGatewayInterface
    {
        /** @var PaymentService $service */
        $service = static::getFacadeRoot();

        return $service->getManager()->driver($name);
    }

    public static function extend(string $driver, Closure $callback): PaymentManager
    {
        /** @var PaymentService $service */
        $service = static::getFacadeRoot();

        return $service->getManager()->extend($driver, $callback);
    }

    public static function getAvailableDrivers(): array
    {
        /** @var PaymentService $service */
        $service = static::getFacadeRoot();

        return $service->getManager()->getAvailableDrivers();
    }
}
