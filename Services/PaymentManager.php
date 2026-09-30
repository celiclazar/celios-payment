<?php

namespace Modules\Payment\Services;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;
use Modules\Payment\Contracts\PaymentGatewayInterface;
use Modules\Payment\Exceptions\GatewayNotFoundException;
use Modules\Payment\Gateways\BankTransfer\BankTransferGateway;
use Modules\Payment\Gateways\Mock\MockGateway;
use Modules\Payment\Gateways\Stripe\StripeGateway;
use Modules\Payment\Models\PaymentGatewayConfig;

class PaymentManager
{
    /**
     * The array of resolved gateway instances.
     *
     * @var array<string, PaymentGatewayInterface>
     */
    protected array $drivers = [];

    /**
     * The registered custom driver creators.
     *
     * @var array<string, Closure>
     */
    protected array $customCreators = [];

    public function __construct(
        protected Application $app
    ) {}

    /**
     * Get a payment gateway instance by name.
     */
    public function driver(?string $name = null): PaymentGatewayInterface
    {
        $name = $name ?: $this->getDefaultDriver();

        if (empty($name)) {
            throw new InvalidArgumentException('Default payment gateway is not configured.');
        }

        if (! isset($this->drivers[$name])) {
            $this->drivers[$name] = $this->resolve($name);
        }

        return $this->drivers[$name];
    }

    /**
     * Register a custom gateway driver creator Closure.
     */
    public function extend(string $driver, Closure $callback): self
    {
        $this->customCreators[$driver] = $callback;
        unset($this->drivers[$driver]);

        return $this;
    }

    /**
     * Get the default driver name.
     */
    public function getDefaultDriver(): string
    {
        return $this->app['config']['payment.default'] ?? 'mock';
    }

    /**
     * Set the default driver name.
     */
    public function setDefaultDriver(string $name): void
    {
        $this->app['config']['payment.default'] = $name;
    }

    /**
     * Get all currently available gateway drivers.
     *
     * @return array<string, PaymentGatewayInterface>
     */
    public function getAvailableDrivers(): array
    {
        $configured = array_keys($this->app['config']['payment.gateways'] ?? []);
        $custom = array_keys($this->customCreators);
        $allKeys = array_unique(array_merge($configured, $custom, ['mock', 'bank_transfer', 'stripe']));

        $available = [];
        foreach ($allKeys as $key) {
            try {
                $driver = $this->driver($key);
                if ($driver->isAvailable()) {
                    $available[$key] = $driver;
                }
            } catch (\Throwable) {
                // Ignore unavailable or misconfigured drivers
            }
        }

        return $available;
    }

    /**
     * Resolve the given gateway driver.
     */
    protected function resolve(string $name): PaymentGatewayInterface
    {
        $config = $this->getGatewayConfig($name);

        if (isset($this->customCreators[$name])) {
            return $this->callCustomCreator($name, $config);
        }

        $method = 'create'.str_replace('_', '', ucwords($name, '_')).'Driver';

        if (method_exists($this, $method)) {
            return $this->$method($config);
        }

        // Check if config specifies a custom driver class
        if (! empty($config['driver']) && class_exists($config['driver'])) {
            return $this->app->make($config['driver'], ['config' => $config]);
        }

        throw GatewayNotFoundException::forGateway($name);
    }

    /**
     * Call a custom driver creator.
     */
    protected function callCustomCreator(string $driver, array $config): PaymentGatewayInterface
    {
        return $this->customCreators[$driver]($this->app, $config);
    }

    /**
     * Get configuration for a specific gateway (merging file config with database overrides).
     */
    protected function getGatewayConfig(string $name): array
    {
        $fileConfig = $this->app['config']["payment.gateways.{$name}"] ?? [];

        // Check for database overrides if table exists
        try {
            $dbConfig = PaymentGatewayConfig::where('code', $name)->first();
            if ($dbConfig) {
                return array_merge($fileConfig, [
                    'enabled' => $dbConfig->is_active,
                    'test_mode' => $dbConfig->is_test_mode,
                    'supported_currencies' => $dbConfig->supported_currencies ?? ($fileConfig['supported_currencies'] ?? []),
                ], (array) $dbConfig->settings);
            }
        } catch (\Throwable) {
            // DB not yet migrated or table not present
        }

        return $fileConfig;
    }

    /**
     * Create an instance of the Mock driver.
     */
    protected function createMockDriver(array $config): MockGateway
    {
        return new MockGateway($config);
    }

    /**
     * Create an instance of the Bank Transfer driver.
     */
    protected function createBankTransferDriver(array $config): BankTransferGateway
    {
        return new BankTransferGateway($config);
    }

    /**
     * Create an instance of the Stripe driver.
     */
    protected function createStripeDriver(array $config): StripeGateway
    {
        return new StripeGateway($config);
    }
}
