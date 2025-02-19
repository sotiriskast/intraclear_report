<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RollingReserveResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'original_amount' => $this->original_amount / 100, // Convert from cents
            'original_currency' => $this->original_currency,
            'reserve_amount_eur' => $this->reserve_amount_eur / 100,
            'exchange_rate' => $this->exchange_rate,
            'period_start' => $this->period_start->toDateString(),
            'period_end' => $this->period_end->toDateString(),
            'release_due_date' => $this->release_due_date->toDateString(),
            'released_at' => $this->released_at?->toDateTimeString(),
            'status' => $this->status,
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }
}
