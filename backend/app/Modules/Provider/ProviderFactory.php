<?php

namespace App\Modules\Provider;

use App\Modules\Provider\Contracts\PaymentProviderInterface;
use App\Modules\Provider\Adapters\Wave\WaveAdapter;
use App\Modules\Provider\Adapters\OrangeMoney\OrangeMoneyAdapter;
use InvalidArgumentException;

class ProviderFactory
{
    /**
     * Registered provider adapters
     */
    protected static array $adapters = [
        'wave' => WaveAdapter::class,
        'orange_money' => OrangeMoneyAdapter::class,
        // 'free_money' => FreeMoneyAdapter::class,
        // 'wizall' => WizallAdapter::class,
        // 'emoney' => EMoneyAdapter::class,
    ];

    /**
     * Cached provider instances
     */
    protected static array $instances = [];

    /**
     * Get a provider adapter instance
     */
    public static function make(string $provider, array $config = []): PaymentProviderInterface
    {
        $provider = strtolower($provider);

        if (!isset(self::$adapters[$provider])) {
            throw new InvalidArgumentException("Unknown payment provider: {$provider}");
        }

        $cacheKey = $provider . '_' . md5(json_encode($config));

        if (!isset(self::$instances[$cacheKey])) {
            $adapterClass = self::$adapters[$provider];
            self::$instances[$cacheKey] = new $adapterClass($config);
        }

        return self::$instances[$cacheKey];
    }

    /**
     * Get all available providers
     */
    public static function available(): array
    {
        $available = [];

        foreach (self::$adapters as $identifier => $adapterClass) {
            $adapter = self::make($identifier);
            if ($adapter->isAvailable()) {
                $available[$identifier] = [
                    'identifier' => $adapter->getIdentifier(),
                    'name' => $adapter->getName(),
                    'currencies' => $adapter->getSupportedCurrencies(),
                    'limits' => $adapter->getLimits(),
                    'fees' => $adapter->getFees(),
                ];
            }
        }

        return $available;
    }

    /**
     * Get all registered providers (available or not)
     */
    public static function all(): array
    {
        $all = [];

        foreach (self::$adapters as $identifier => $adapterClass) {
            $adapter = self::make($identifier);
            $all[$identifier] = [
                'identifier' => $adapter->getIdentifier(),
                'name' => $adapter->getName(),
                'available' => $adapter->isAvailable(),
            ];
        }

        return $all;
    }

    /**
     * Register a new provider adapter
     */
    public static function register(string $identifier, string $adapterClass): void
    {
        if (!is_subclass_of($adapterClass, PaymentProviderInterface::class)) {
            throw new InvalidArgumentException(
                "Adapter must implement PaymentProviderInterface"
            );
        }

        self::$adapters[strtolower($identifier)] = $adapterClass;
    }

    /**
     * Check if a provider is registered
     */
    public static function has(string $provider): bool
    {
        return isset(self::$adapters[strtolower($provider)]);
    }

    /**
     * Clear cached instances
     */
    public static function clearCache(): void
    {
        self::$instances = [];
    }
}
