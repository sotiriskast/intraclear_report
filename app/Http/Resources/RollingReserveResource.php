<?php

namespace App\Http\Resources;

use Carbon\Carbon;
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
            'amount' => round($this->original_amount / 100, 2), // Convert from cents
            'currency' => $this->original_currency,
            'amount_eur' => round($this->reserve_amount_eur / 100, 2), // Convert from cents
            'exchange_rate' => $this->exchange_rate,
            'period_start' => $this->period_start->format('Y-m-d'),
            'period_end' => $this->period_end->format('Y-m-d'),
            'release_due_date' => $this->release_due_date->format('Y-m-d'),
            'released_at' => $this->released_at ? $this->released_at->format('Y-m-d H:i:s') : null,
            'status' => $this->status,
            'is_released' => $this->status === 'released',
            'days_until_release' => $this->status === 'pending'
                ? Carbon::today()->diffInDays($this->release_due_date, false)
                : 0,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
