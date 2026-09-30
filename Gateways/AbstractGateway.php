<?php

namespace Modules\Payment\Gateways;

use Illuminate\Support\Facades\Log;
use Modules\Payment\Contracts\PaymentGatewayInterface;

abstract class AbstractGateway implements PaymentGatewayInterface
{
    public function __construct(
        protected array $config = []
    ) {}

    public function isAvailable(): bool
    {
        return (bool) ($this->config['enabled'] ?? true);
    }

    public function isTestMode(): bool
    {
        return (bool) ($this->config['test_mode'] ?? true);
    }

    public function getSupportedCurrencies(): array
    {
        return $this->config['supported_currencies'] ?? [];
    }

    public function supportsCurrency(string $currency): bool
    {
        $supported = $this->getSupportedCurrencies();

        return empty($supported) || in_array(strtoupper($currency), $supported);
    }

    protected function config(string $key, mixed $default = null): mixed
    {
        return data_get($this->config, $key, $default);
    }

    protected function log(string $level, string $message, array $context = []): void
    {
        Log::channel($this->config('log_channel', 'stack'))->log(
            $level,
            "[Payment:{$this->getId()}] {$message}",
            $context
        );
    }
}
