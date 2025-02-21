<?php

namespace App\Api\V1\Resources\RollingReserve;

use Illuminate\Http\Resources\Json\JsonResource;

class RollingReserveSummaryResource extends JsonResource
{
    public function toArray($request): array
    {
        $data = is_array($this->resource) ? $this->resource : [];

        return [
            'pending_reserves' => collect($data['pending_reserves'] ?? [])
                ->map(fn($amount) => round($amount, 2))
                ->toArray(),
            'statistics' => [
                'pending_count' => $data['pending_count'] ?? 0,
                'released_count' => $data['released_count'] ?? 0
            ],
            'upcoming_releases' => collect($data['upcoming_releases'] ?? [])
                ->map(fn($amount) => round($amount, 2))
                ->toArray()
        ];
    }
}
