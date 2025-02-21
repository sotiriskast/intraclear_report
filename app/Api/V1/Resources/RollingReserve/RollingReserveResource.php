<?php
namespace App\Api\V1\Resources\RollingReserve;

use Illuminate\Http\Resources\Json\JsonResource;

class RollingReserveResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'amount' => round($this->original_amount / 100, 2),
            'currency' => $this->original_currency,
            'amount_eur' => round($this->reserve_amount_eur / 100, 2),
            'exchange_rate' => $this->exchange_rate,
            'period' => [
                'start' => $this->period_start->format('Y-m-d'),
                'end' => $this->period_end->format('Y-m-d')
            ],
            'release' => [
                'due_date' => $this->release_due_date->format('Y-m-d'),
                'days_until' => $this->status === 'pending'
                    ? now()->diffInDays($this->release_due_date, false)
                    : 0
            ],
            'status' => $this->status,
            'is_released' => $this->status === 'released',
            'released_at' => $this->released_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s')
        ];
    }
}
