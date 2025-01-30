<?php

namespace App\Services\Settlement\Fee\Configurations;

class FeeRegistry
{
    private array $feeConfigurations = [];

    public function register(FeeConfiguration $configuration): void
    {
        $this->feeConfigurations[$configuration->key] = $configuration;
    }

    public function get(string $key): ?FeeConfiguration
    {
        return $this->feeConfigurations[$key] ?? null;
    }

    public function all(): array
    {
        return $this->feeConfigurations;
    }
}
