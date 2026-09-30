<?php

namespace Modules\Payment\Console\Commands;

use Illuminate\Console\Command;
use Modules\Payment\DTO\CustomerData;
use Modules\Payment\DTO\PaymentChargeRequest;
use Modules\Payment\Facades\Payment;

class TestPaymentCommand extends Command
{
    protected $signature = 'payment:test 
                            {gateway=mock : Gateway driver (mock, bank_transfer, stripe)} 
                            {amount=50.00 : Amount to charge} 
                            {currency=EUR : Currency code} 
                            {--fail : Simulate gateway decline} 
                            {--3ds : Simulate 3D-Secure redirect}';

    protected $description = 'Test the payment module by processing a test charge';

    public function handle(): int
    {
        $gateway = $this->argument('gateway');
        $amount = (float) $this->argument('amount');
        $currency = strtoupper($this->argument('currency'));

        $this->info("💳 Initiating test payment of {$amount} {$currency} via [{$gateway}]...");

        $options = [];
        if ($this->option('fail')) {
            $options['simulate'] = 'failure';
        } elseif ($this->option('3ds')) {
            $options['simulate'] = '3ds';
        }

        $customer = CustomerData::fromArray([
            'name' => 'Demo Customer',
            'email' => 'customer@example.com',
            'phone' => '+38160123456',
        ]);

        $request = PaymentChargeRequest::make($amount, $currency)
            ->withDescription('CLI Test Payment')
            ->withCustomer($customer)
            ->withOptions($options);

        try {
            $response = Payment::charge($request, $gateway);

            $this->newLine();
            $this->components->info('Payment Charge Executed');

            $this->table(
                ['Field', 'Value'],
                [
                    ['Reference', $response->reference],
                    ['Gateway', strtoupper($gateway)],
                    ['Status', $response->status->label()],
                    ['Transaction ID', $response->transactionId ?: '—'],
                    ['Redirect URL', $response->redirectUrl ?: '—'],
                    ['Message', $response->message ?: '—'],
                    ['Error', $response->errorMessage ?: '—'],
                ]
            );

            if (!empty($response->actionData)) {
                $this->newLine();
                $this->line('<fg=yellow;options=bold>Next Step / Action Instructions:</>');
                foreach ($response->actionData as $key => $value) {
                    $valStr = is_array($value) ? json_encode($value) : $value;
                    $this->line("  <fg=gray>•</> <fg=cyan>{$key}:</> {$valStr}");
                }
            }

            $this->newLine();
            $this->line("Check this record in the admin panel at: <fg=green>/admin/payments</>");

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Payment execution failed: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
