<?php

namespace Modules\Payment\Gateways\BankTransfer;

use Illuminate\Support\Str;
use Modules\Payment\Contracts\PaymentGatewayInterface;
use Modules\Payment\DTO\PaymentChargeRequest;
use Modules\Payment\DTO\PaymentResponse;
use Modules\Payment\Gateways\AbstractGateway;

class BankTransferGateway extends AbstractGateway implements PaymentGatewayInterface
{
    public function getId(): string
    {
        return 'bank_transfer';
    }

    public function getName(): string
    {
        return 'Direct Bank Wire Transfer';
    }

    public function charge(PaymentChargeRequest $request): PaymentResponse
    {
        $reference = $request->reference ?? 'BT-'.strtoupper(Str::random(10));

        $instructions = [
            'recipient_name' => $this->config('recipient_name', config('app.name', 'Celios CMS')),
            'account_number' => $this->config('account_number', '160-0000000000000-00'),
            'iban' => $this->config('iban', 'RS35160000000000000000'),
            'swift_bic' => $this->config('swift_bic', 'DBDBRSBG'),
            'payment_reference' => $reference,
            'amount' => $request->amount,
            'currency' => $request->currency,
            'note' => $this->config('note', 'Please use the payment reference as reference in your bank payment.'),
        ];

        return PaymentResponse::pending(
            transactionId: 'bt_'.Str::random(16),
            reference: $reference,
            amount: $request->amount,
            currency: $request->currency,
            message: 'Awaiting manual bank transfer confirmation',
            actionData: $instructions,
            raw: $instructions
        );
    }
}
