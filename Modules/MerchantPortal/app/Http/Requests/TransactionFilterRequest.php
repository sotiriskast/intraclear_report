<?php

namespace Modules\MerchantPortal\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Decta\Models\DectaTransaction;

class TransactionFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->user_type === 'merchant';
    }

    public function rules(): array
    {
        return [
            'date_from' => 'nullable|date|before_or_equal:today',
            'date_to' => 'nullable|date|after_or_equal:date_from|before_or_equal:today',
            'status' => 'nullable|string|in:' . implode(',', [
                    DectaTransaction::STATUS_PENDING,
                    DectaTransaction::STATUS_PROCESSING,
                    DectaTransaction::STATUS_MATCHED,
                    DectaTransaction::STATUS_FAILED
                ]),
            'shop_id' => 'nullable|integer|exists:shops,id',
            'amount_min' => 'nullable|numeric|min:0',
            'amount_max' => 'nullable|numeric|gte:amount_min',
            'payment_id' => 'nullable|string|max:255',
            'merchant_name' => 'nullable|string|max:255',
            'card_type' => 'nullable|string|max:50',
            'currency' => 'nullable|string|size:3',
            'per_page' => 'nullable|integer|min:10|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'date_from.before_or_equal' => 'Start date cannot be in the future.',
            'date_to.after_or_equal' => 'End date must be after or equal to start date.',
            'date_to.before_or_equal' => 'End date cannot be in the future.',
            'amount_max.gte' => 'Maximum amount must be greater than or equal to minimum amount.',
            'currency.size' => 'Currency must be a 3-letter code.',
        ];
    }
}
