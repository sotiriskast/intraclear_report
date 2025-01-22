<?php

namespace App\Repositories\Interfaces;

interface RollingReserveRepositoryInterface
{
    public function getMerchantReserveSettings(int $merchantId, string $currency, string $date = null);
    public function createReserveEntry(array $data);
    public function getReleaseableFunds(int $merchantId, string $date);
    public function markReserveAsReleased(array $entryIds);
}
