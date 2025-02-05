<?php

namespace App\Services\Settlement\Fee\Configurations;

/**
 * Registry class for managing fee configurations
 * Provides a central storage and access point for all fee configurations
 * in the settlement system
 */
class FeeRegistry
{
    /**
     * Storage for registered fee configurations
     *
     * @var array<string, FeeConfiguration> Array of fee configurations indexed by their keys
     */
    private array $feeConfigurations = [];

    /**
     * Registers a new fee configuration in the registry
     * If a configuration with the same key exists, it will be overwritten
     *
     * @param FeeConfiguration $configuration The fee configuration to register
     * @return void
     */
    public function register(FeeConfiguration $configuration): void
    {
        $this->feeConfigurations[$configuration->key] = $configuration;
    }

    /**
     * Retrieves a specific fee configuration by its key
     *
     * @param string $key The unique identifier for the fee configuration
     * @return FeeConfiguration|null Returns the fee configuration if found, null otherwise
     */
    public function get(string $key): ?FeeConfiguration
    {
        return $this->feeConfigurations[$key] ?? null;
    }

    /**
     * Retrieves all registered fee configurations
     *
     * @return array<string, FeeConfiguration> Array of all registered fee configurations
     */
    public function all(): array
    {
        return $this->feeConfigurations;
    }
}
